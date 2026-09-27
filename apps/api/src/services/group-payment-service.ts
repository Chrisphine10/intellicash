/**
 * Money a member pays INTO a group through a gateway (M-Pesa STK push or
 * Paystack checkout): verification, then posting to the group's books.
 *
 * The order is fixed and every step is its own state:
 *
 *   INITIATED/PROCESSING → (provider says paid) SUCCESSFUL
 *     → (we asked the provider ourselves, amounts match) VERIFIED
 *     → (group amount written to the ledger) LEDGER_POSTED
 *
 * A callback is never trusted on its own word: the callback URLs are public.
 * Success is confirmed with the provider using the credentials that collected
 * the money, and the amount must match what we charged. Anything that does
 * not agree becomes HELD for a person to look at, and nothing is posted.
 *
 * Only the GROUP amount is posted. Fees are IWL's and the provider's, recorded
 * on the payment row, never in the VSLA ledger.
 *
 * Exactly one ledger entry per payment, whoever gets there first: the server
 * (on verification) or the phone (syncing the meeting where the purchase was
 * recorded). Both paths claim `GroupPayment.ledgerEntryId`, which is unique.
 *
 * The gateway callbacks in `routes/payments.ts` also serve partner wallets. A
 * callback carries only a reference, so each handler tries both; whichever
 * does not own the reference is a no-op.
 */

import type { GroupPayment, Prisma } from "@prisma/client";
import { prisma } from "../lib/prisma";
import { appendAuditEvent } from "./audit-service";
import { assertMeetingWritable } from "./cycle-service";
import { notifySharePurchases } from "./meeting-sms-service";
import { dispatchAfterResponse } from "./outbound-sms-service";
import { credentialGroupId, ledgerTypeForPurpose } from "./payment-settings-service";
import { queryMpesaStk, verifyPaystackTransaction, type ProviderCheck } from "./payment-service";
import { appendLedgerEntry, ledgerRuleFor, resolveFundAccount } from "../routes/groups";

type Tx = Prisma.TransactionClient;

/** States in which a payment may still become verified. */
export const OPEN_STATES = ["INITIATED", "PROCESSING", "SUCCESSFUL"] as const;
/**
 * States a provider SUCCESS may still move to VERIFIED. EXPIRED is included on
 * purpose: expiry only means nobody confirmed it in time. If the money did
 * arrive after all, it is verified and posted like any other payment.
 */
export const CLAIMABLE_STATES = [...OPEN_STATES, "EXPIRED"] as const;
/** Terminal failure states. */
export const FAILED_STATES = ["FAILED", "CANCELLED", "EXPIRED"] as const;

export const HELD_MESSAGE =
  "Payment received but held for checking. Do not record it by hand or pay again — an administrator will confirm it.";

/** What old phones read: PENDING / COMPLETED / FAILED. */
export function legacyStatus(state: string) {
  if (state === "VERIFIED" || state === "LEDGER_POSTED" || state === "COMPLETED_LEGACY") return "COMPLETED";
  if (state === "HELD" || state === "REVERSED" || state === "REFUNDED") return "FAILED";
  if ((FAILED_STATES as readonly string[]).includes(state)) return state === "CANCELLED" ? "CANCELLED" : "FAILED";
  return "PENDING";
}

export interface PaymentEvidence {
  source: "MPESA_CALLBACK" | "PAYSTACK_WEBHOOK" | "POLL" | "ADMIN";
  /** What the callback says was paid, in cents (M-Pesa sends shillings). */
  amountCents?: number | null;
  phoneNumber?: string | null;
  providerTransactionId?: string | null;
  raw?: unknown;
}

function mergeMetadata(previous: string | null, key: string, value: unknown) {
  let base: Record<string, unknown> = {};
  try {
    base = previous ? (JSON.parse(previous) as Record<string, unknown>) : {};
  } catch {
    base = { previous };
  }
  return JSON.stringify({ ...base, [key]: value ?? null });
}

/** Matches on the gateway's reference or the one we generated. */
function referenceWhere(reference: string) {
  return { OR: [{ providerReference: reference }, { internalReference: reference }] };
}

/** Last 9 digits, or null when the number is masked or missing. */
function comparablePhone(value: string | number | null | undefined) {
  if (value == null) return null;
  const text = String(value);
  if (/[^0-9+\s]/.test(text)) return null; // masked (e.g. 2547******123)
  const digits = text.replace(/[^0-9]/g, "");
  return digits.length >= 9 ? digits.slice(-9) : null;
}

/** Ask the provider about a payment, with the credentials that collected it. */
export async function checkWithProvider(payment: GroupPayment): Promise<ProviderCheck> {
  const groupId = credentialGroupId(payment.collectionMode, payment.groupId);
  if (payment.provider === "PAYSTACK") {
    return verifyPaystackTransaction(payment.providerReference ?? payment.internalReference, groupId);
  }
  if (!payment.providerReference) return { checked: true, status: "PENDING", reason: "No M-Pesa request id yet." };
  return queryMpesaStk(payment.providerReference, groupId);
}

/** Why the evidence and the provider's answer disagree with what we charged, or null. */
function mismatch(payment: GroupPayment, evidence: PaymentEvidence, check: ProviderCheck): string | null {
  if (check.checked && check.status === "FAILED") {
    return `The provider reports this payment as not paid (${check.reason ?? "no reason given"}).`;
  }
  const reported = check.amountCents ?? evidence.amountCents;
  if (reported != null && reported !== payment.amountCents) {
    return `Amount paid (${reported} cents) does not match the amount charged (${payment.amountCents} cents).`;
  }
  if (check.currency && check.currency.toUpperCase() !== payment.currency.toUpperCase()) {
    return `Currency ${check.currency} does not match ${payment.currency}.`;
  }
  const paid = comparablePhone(evidence.phoneNumber);
  const expected = comparablePhone(payment.phoneNumber);
  if (payment.provider === "MPESA_DARAJA" && paid && expected && paid !== expected) {
    return "The paying phone number does not match the number that was prompted.";
  }
  return null;
}

async function audit(paymentId: string, type: Parameters<typeof appendAuditEvent>[0]["type"], payload: unknown, actorUserId?: string | null) {
  await appendAuditEvent({ actorUserId: actorUserId ?? null, entityType: "GROUP_PAYMENT", entityId: paymentId, type, payload });
}

/**
 * The provider says a payment succeeded. Confirm it, then post it.
 * Safe to call any number of times for the same payment.
 */
export async function completeGroupPayment(reference: string, evidence: PaymentEvidence) {
  const normalized = evidence;
  const payment = await prisma.groupPayment.findFirst({ where: referenceWhere(reference) });
  if (!payment) return null; // not a group payment (probably a partner wallet one)
  if (!(CLAIMABLE_STATES as readonly string[]).includes(payment.state)) return payment; // already decided

  const check = await checkWithProvider(payment);

  if (check.checked && check.status === "PENDING") {
    // The provider does not confirm yet. Remember what we were told; the next
    // callback or poll decides.
    await prisma.groupPayment.updateMany({
      where: { id: payment.id, state: { in: [...CLAIMABLE_STATES] } },
      data: { state: "SUCCESSFUL", metadataJson: mergeMetadata(payment.metadataJson, "unconfirmedSuccess", normalized.raw) }
    });
    return prisma.groupPayment.findUnique({ where: { id: payment.id } });
  }

  const problem = mismatch(payment, normalized, check);
  if (problem) {
    const held = await prisma.groupPayment.updateMany({
      where: { id: payment.id, state: { in: [...CLAIMABLE_STATES] } },
      data: {
        state: "HELD",
        status: legacyStatus("HELD"),
        failureReason: `${HELD_MESSAGE} (${problem})`,
        verifiedAmountCents: check.amountCents ?? normalized.amountCents ?? null,
        providerTransactionId: check.providerTransactionId ?? normalized.providerTransactionId ?? payment.providerTransactionId,
        metadataJson: mergeMetadata(payment.metadataJson, "held", { problem, evidence: normalized.raw, check: check.raw ?? null })
      }
    });
    if (held.count > 0) await audit(payment.id, "GROUP_PAYMENT_HELD", { problem, source: normalized.source });
    return prisma.groupPayment.findUnique({ where: { id: payment.id } });
  }

  const now = new Date();
  const claimed = await prisma.groupPayment.updateMany({
    where: { id: payment.id, state: { in: [...CLAIMABLE_STATES] } },
    data: {
      state: "VERIFIED",
      status: legacyStatus("VERIFIED"),
      verifiedAt: now,
      completedAt: now,
      failureReason: null,
      verifiedAmountCents: check.amountCents ?? normalized.amountCents ?? payment.amountCents,
      providerTransactionId: check.providerTransactionId ?? normalized.providerTransactionId ?? payment.providerTransactionId,
      platformFeeStatus:
        payment.platformFeeCents > 0 ? (payment.collectionMode === "OWN_ACCOUNT" ? "RECEIVABLE" : "COLLECTED") : "NONE",
      metadataJson: mergeMetadata(payment.metadataJson, "verification", {
        source: normalized.source,
        checkedWithProvider: check.checked,
        callback: normalized.raw ?? null
      })
    }
  });
  if (claimed.count === 0) return prisma.groupPayment.findUnique({ where: { id: payment.id } }); // a duplicate won the race

  await audit(payment.id, "GROUP_PAYMENT_VERIFIED", {
    source: normalized.source,
    checkedWithProvider: check.checked,
    amountCents: payment.amountCents,
    groupAmountCents: payment.groupAmountCents
  });

  return postVerifiedPayment(payment.id);
}

/** The provider says the payment failed or was cancelled. */
export async function failGroupPayment(reference: string, reason: string, metadata: unknown = {}) {
  const payment = await prisma.groupPayment.findFirst({ where: referenceWhere(reference) });
  if (!payment || !(OPEN_STATES as readonly string[]).includes(payment.state)) return null;

  const cancelled = /cancel/i.test(reason);
  const state = cancelled ? "CANCELLED" : "FAILED";
  const updated = await prisma.groupPayment.updateMany({
    where: { id: payment.id, state: { in: [...OPEN_STATES] } },
    data: {
      state,
      status: legacyStatus(state),
      failureReason: reason,
      metadataJson: mergeMetadata(payment.metadataJson, "failure", metadata)
    }
  });
  if (updated.count > 0) await audit(payment.id, "GROUP_PAYMENT_FAILED", { reason });
  return prisma.groupPayment.findUnique({ where: { id: payment.id } });
}

/**
 * Write the group amount of a payment to the ledger, inside [tx]. Returns the
 * entry. The client request id `gp-<paymentId>` makes a repeat return the
 * same entry instead of a second one.
 */
export async function writePaymentEntry(tx: Tx, payment: GroupPayment) {
  const type = ledgerTypeForPurpose(payment.purpose);
  const rule = type ? ledgerRuleFor(type) : null;
  if (!type || !rule) return null;
  // The same rule the meeting uses: shares and loan repayments go to the loan
  // fund, social contributions and fines to the social (welfare) fund.
  const fund = await resolveFundAccount(tx, payment.groupId, rule.fundType);

  // Tie it to the meeting where it was taken, if that meeting can still take
  // entries. A payment that completes after its cycle closed still belongs
  // in the books; it is posted without the meeting rather than lost.
  let meetingId: string | null = payment.meetingId;
  if (meetingId) {
    try {
      await assertMeetingWritable(tx, meetingId);
    } catch {
      meetingId = null;
    }
  }
  return appendLedgerEntry(tx, {
    groupId: payment.groupId,
    memberId: payment.memberId,
    meetingId,
    fundAccountId: fund.id,
    type: type as never,
    amountCents: payment.groupAmountCents,
    direction: rule.direction,
    description: `${rule.label} paid by ${payment.provider === "PAYSTACK" ? "Paystack" : "M-Pesa"}${
      payment.meetingId && !meetingId ? " (after the meeting closed)" : ""
    }`,
    externalReference: payment.providerTransactionId ?? payment.internalReference,
    clientRequestId: `gp-${payment.id}`
  });
}

/** Link a verified payment to [ledgerEntryId] and move it on. */
async function markPosted(tx: Tx, payment: GroupPayment, ledgerEntryId: string) {
  return tx.groupPayment.updateMany({
    where: { id: payment.id, ledgerEntryId: null },
    data:
      payment.state === "VERIFIED"
        ? {
            ledgerEntryId,
            state: "LEDGER_POSTED",
            settlementStatus:
              payment.collectionMode === "SYSTEM" && payment.groupAmountCents > 0 ? "PENDING" : "NOT_REQUIRED",
            failureReason: null
          }
        : // Recorded by the phone before the provider confirmed. Linked, but
          // nothing is settled until verification moves it on.
          { ledgerEntryId }
  });
}

/**
 * Post a VERIFIED payment. If the phone already recorded it (ledgerEntryId is
 * set), just move it on. A posting that fails (for example a fund at its
 * limit) leaves the payment VERIFIED with the reason, for the reconciliation
 * screen, and can be retried.
 */
export async function postVerifiedPayment(paymentId: string, actorUserId?: string | null) {
  const outcome: { entry: Awaited<ReturnType<typeof writePaymentEntry>> } = { entry: null };
  try {
    await prisma.$transaction(async (tx) => {
      const payment = await tx.groupPayment.findUnique({ where: { id: paymentId } });
      if (!payment || payment.state !== "VERIFIED") return;
      if (payment.ledgerEntryId) {
        await tx.groupPayment.update({
          where: { id: payment.id },
          data: {
            state: "LEDGER_POSTED",
            settlementStatus:
              payment.collectionMode === "SYSTEM" && payment.groupAmountCents > 0 ? "PENDING" : "NOT_REQUIRED"
          }
        });
        return;
      }
      if (!ledgerTypeForPurpose(payment.purpose)) {
        await tx.groupPayment.update({
          where: { id: payment.id },
          data: { failureReason: `A ${payment.purpose.toLowerCase().replace(/_/g, " ")} payment is not posted automatically. Record it by hand.` }
        });
        return;
      }
      const entry = await writePaymentEntry(tx, payment);
      if (!entry) return;
      await markPosted(tx, payment, entry.id);
      outcome.entry = entry;
    });
  } catch (error) {
    const reason = error instanceof Error ? error.message : "The payment could not be posted.";
    await prisma.groupPayment.update({
      where: { id: paymentId },
      data: { failureReason: `Verified, not yet posted: ${reason}` }
    });
    return prisma.groupPayment.findUnique({ where: { id: paymentId } });
  }

  const entry = outcome.entry;
  if (entry) {
    await audit(paymentId, "GROUP_PAYMENT_POSTED", { ledgerEntryId: entry.id, amountCents: entry.amountCents }, actorUserId);
    dispatchAfterResponse(() => notifySharePurchases([entry]));
  }
  return prisma.groupPayment.findUnique({ where: { id: paymentId } });
}

/**
 * A phone is syncing a ledger entry that may be a gateway payment it already
 * saw complete. Find that payment. New phones send its id; phones in the
 * field (2.6.x) put `providerTransactionId ?? paymentId` in the reference.
 */
export async function findPaymentForPhoneEntry(
  tx: Tx,
  groupId: string,
  entry: { type: string; memberId?: string | null; externalReference?: string | null; groupPaymentId?: string | null }
) {
  if (!["SHARE_PURCHASE", "SOCIAL_CONTRIBUTION", "FINE_COLLECTION", "LOAN_REPAYMENT"].includes(entry.type)) return null;
  const or: Prisma.GroupPaymentWhereInput[] = [];
  if (entry.groupPaymentId) or.push({ id: entry.groupPaymentId });
  const reference = entry.externalReference?.trim();
  if (reference) or.push({ id: reference }, { providerTransactionId: reference }, { internalReference: reference });
  if (or.length === 0) return null;
  const payment = await tx.groupPayment.findFirst({
    where: {
      groupId,
      OR: or,
      // Payments from before this layer were only ever posted by the phone.
      state: { notIn: ["COMPLETED_LEGACY", "FAILED", "CANCELLED", "EXPIRED", "REVERSED", "REFUNDED"] }
    }
  });
  if (!payment) return null;
  if (ledgerTypeForPurpose(payment.purpose) !== entry.type) return null;
  if (payment.memberId && entry.memberId && payment.memberId !== entry.memberId) return null;
  return payment;
}

/**
 * Called when the phone's entry for [payment] has just been written (the
 * phone got there first). Claims the payment so the server never posts it
 * again.
 */
export async function linkPhoneEntry(tx: Tx, payment: GroupPayment, ledgerEntryId: string) {
  await markPosted(tx, payment, ledgerEntryId);
}

// ---------------------------------------------------------------------------
// Polling: a payment the phone is waiting on, whose callback has not come.
// ---------------------------------------------------------------------------

const lastProviderCheck = new Map<string, number>();
const POLL_CHECK_AFTER_MS = 60_000;
const POLL_CHECK_EVERY_MS = 20_000;

/**
 * When a payment has been open for over a minute, ask the provider directly
 * (at most every 20 s per payment). A lost callback then cannot strand a
 * member's money. Never used in mock mode, where nobody can be asked.
 */
export async function reconcileOpenPayment(payment: GroupPayment, now = Date.now()) {
  if (!(OPEN_STATES as readonly string[]).includes(payment.state)) return payment;
  if (now - payment.createdAt.getTime() < POLL_CHECK_AFTER_MS) return payment;
  if (now - (lastProviderCheck.get(payment.id) ?? 0) < POLL_CHECK_EVERY_MS) return payment;
  lastProviderCheck.set(payment.id, now);

  const check = await checkWithProvider(payment).catch(() => null);
  if (!check || !check.checked) return payment;
  if (check.status === "SUCCESS") {
    return (await completeGroupPayment(payment.providerReference ?? payment.internalReference, { source: "POLL", raw: check.raw })) ?? payment;
  }
  if (check.status === "FAILED") {
    return (await failGroupPayment(payment.providerReference ?? payment.internalReference, check.reason ?? "The payment was not completed.", check.raw)) ?? payment;
  }
  return payment;
}

// ---------------------------------------------------------------------------
// Administrator actions
// ---------------------------------------------------------------------------

/** A person has checked a HELD payment and confirms the group was paid. */
export async function releaseHeldPayment(paymentId: string, actorUserId: string, note: string) {
  const payment = await prisma.groupPayment.findUnique({ where: { id: paymentId } });
  if (!payment || payment.state !== "HELD") return null;
  const now = new Date();
  const updated = await prisma.groupPayment.updateMany({
    where: { id: paymentId, state: "HELD" },
    data: {
      state: "VERIFIED",
      status: legacyStatus("VERIFIED"),
      verifiedAt: now,
      completedAt: now,
      failureReason: null,
      platformFeeStatus:
        payment.platformFeeCents > 0 ? (payment.collectionMode === "OWN_ACCOUNT" ? "RECEIVABLE" : "COLLECTED") : "NONE",
      metadataJson: mergeMetadata(payment.metadataJson, "released", { by: actorUserId, note, at: now.toISOString() })
    }
  });
  if (updated.count === 0) return null;
  await audit(paymentId, "GROUP_PAYMENT_RELEASED", { note }, actorUserId);
  return postVerifiedPayment(paymentId, actorUserId);
}

/**
 * A HELD or not-yet-posted payment turned out not to be money the group
 * keeps (reversed by the provider, refunded). Posted payments are not undone
 * here: their correction is a ledger matter.
 */
export async function markPaymentReversed(paymentId: string, actorUserId: string, note: string, refunded = false) {
  const payment = await prisma.groupPayment.findUnique({ where: { id: paymentId } });
  if (!payment) return null;
  if (payment.ledgerEntryId) return { error: "POSTED" as const, payment };
  const state = refunded ? "REFUNDED" : "REVERSED";
  await prisma.groupPayment.update({
    where: { id: paymentId },
    data: {
      state,
      status: legacyStatus(state),
      settlementStatus: "NOT_REQUIRED",
      failureReason: note,
      metadataJson: mergeMetadata(payment.metadataJson, state.toLowerCase(), { by: actorUserId, note })
    }
  });
  await audit(paymentId, "GROUP_PAYMENT_REVERSED", { state, note }, actorUserId);
  return { payment: await prisma.groupPayment.findUnique({ where: { id: paymentId } }) };
}

// ---------------------------------------------------------------------------
// Stale payments
// ---------------------------------------------------------------------------

const STALE_AFTER_MS = 24 * 3_600_000;

/**
 * A prompt the member never answered, or a checkout never opened, would sit
 * "waiting" forever. After a day: ask the provider one last time, then mark
 * it EXPIRED. SUCCESSFUL (paid, not yet confirmed) is never expired — that is
 * money, and it is listed for a person instead. A late success on an expired
 * payment is still verified and posted (see CLAIMABLE_STATES).
 */
export async function expireStalePayments(now = new Date()) {
  const stale = await prisma.groupPayment.findMany({
    where: { state: { in: ["INITIATED", "PROCESSING"] }, createdAt: { lt: new Date(now.getTime() - STALE_AFTER_MS) } },
    take: 200
  });
  let expired = 0;
  let recovered = 0;
  for (const payment of stale) {
    const check = await checkWithProvider(payment).catch(() => null);
    const reference = payment.providerReference ?? payment.internalReference;
    if (check?.checked && check.status === "SUCCESS") {
      await completeGroupPayment(reference, { source: "POLL", raw: check.raw });
      recovered += 1;
      continue;
    }
    if (check?.checked && check.status === "FAILED") {
      await failGroupPayment(reference, check.reason ?? "The payment was not completed.", check.raw);
      continue;
    }
    const updated = await prisma.groupPayment.updateMany({
      where: { id: payment.id, state: { in: ["INITIATED", "PROCESSING"] } },
      data: {
        state: "EXPIRED",
        status: legacyStatus("EXPIRED"),
        failureReason: "Not completed within a day. If the member did pay, it will still be recorded when the provider confirms it."
      }
    });
    if (updated.count > 0) {
      expired += 1;
      await audit(payment.id, "GROUP_PAYMENT_FAILED", { reason: "EXPIRED", ageHours: Math.round((now.getTime() - payment.createdAt.getTime()) / 3_600_000) });
    }
  }
  return { expired, recovered };
}
