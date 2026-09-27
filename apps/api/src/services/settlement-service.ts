/**
 * Settlement: paying a group the money IWL collected on its behalf.
 *
 * Only payments collected in SYSTEM mode are settled (OWN_ACCOUNT money is
 * already with the group). A payment becomes settleable once it is posted to
 * the group's ledger; the settlement run gathers a group's settleable payments
 * into one Settlement and pays it to the group's ACTIVE destination.
 *
 * Rules that protect the money:
 * - A destination is proposed by the group side and approved by a DIFFERENT
 *   person holding payments:approve. Nothing is paid to a destination during
 *   its cool-off after activation, so a hijacked account cannot be emptied
 *   before anyone notices the change.
 * - A batch above SETTLEMENT_AUTO_MAX_CENTS waits for a sign-off.
 * - A payout whose outcome is not known (timeout, dropped connection) becomes
 *   UNKNOWN and is NEVER retried automatically: M-Pesa B2C/B2B requests are
 *   not idempotent, and a blind retry can pay the group twice. It is resolved
 *   by asking the provider, or by a person with the provider's reference.
 * - Payouts run only when ENABLE_AUTOMATED_SETTLEMENT is on. Batches are still
 *   built with it off, so they can be reviewed before it is switched on.
 */

import type { Prisma, SettlementDestination } from "@prisma/client";
import { env } from "../config/env";
import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";
import { appendAuditEvent } from "./audit-service";
import { expireStalePayments } from "./group-payment-service";
import {
  createPaymentReference,
  createPaystackTransferRecipient,
  fetchPaystackTransfer,
  initiatePayout,
  requestMpesaTransactionStatus
} from "./payment-service";

type Tx = Prisma.TransactionClient;

export const DESTINATION_TYPES = [
  "MPESA_PHONE",
  "MPESA_PAYBILL",
  "MPESA_TILL",
  "PAYSTACK_BANK",
  "PAYSTACK_MOBILE_MONEY"
] as const;
export type DestinationType = (typeof DESTINATION_TYPES)[number];

export function providerForDestination(type: DestinationType) {
  return type.startsWith("MPESA_") ? "MPESA_DARAJA" : "PAYSTACK";
}

async function audit(
  entityType: string,
  entityId: string,
  type: Parameters<typeof appendAuditEvent>[0]["type"],
  payload: unknown,
  actorUserId?: string | null
) {
  await appendAuditEvent({ actorUserId: actorUserId ?? null, entityType, entityId, type, payload });
}

function toMsisdn(phone: string) {
  const digits = phone.replace(/[^0-9]/g, "");
  if (digits.startsWith("254")) return digits;
  if (digits.startsWith("0")) return `254${digits.slice(1)}`;
  return `254${digits}`;
}

// ---------------------------------------------------------------------------
// Destinations
// ---------------------------------------------------------------------------

export interface DestinationInput {
  type: DestinationType;
  accountNumber: string;
  accountName: string;
  bankCode?: string | null;
  accountReference?: string | null;
  note?: string | null;
}

export function validateDestination(input: DestinationInput) {
  const number = input.accountNumber.trim();
  if (input.type === "MPESA_PHONE" && !/^(?:\+?254|0)?[17]\d{8}$/.test(number)) {
    throw new ApiHttpError(400, "DESTINATION_INVALID", "Enter a valid Kenyan M-Pesa phone number.");
  }
  if ((input.type === "MPESA_PAYBILL" || input.type === "MPESA_TILL") && !/^\d{5,7}$/.test(number)) {
    throw new ApiHttpError(400, "DESTINATION_INVALID", "A paybill or till number is 5 to 7 digits.");
  }
  if (input.type === "MPESA_PAYBILL" && !input.accountReference?.trim()) {
    throw new ApiHttpError(400, "DESTINATION_INVALID", "A paybill needs the account number to pay into.");
  }
  if (input.type.startsWith("PAYSTACK_") && !input.bankCode?.trim()) {
    throw new ApiHttpError(400, "DESTINATION_INVALID", "Choose the bank or mobile-money operator.");
  }
  if (input.accountName.trim().length < 3) {
    throw new ApiHttpError(400, "DESTINATION_INVALID", "Enter the account name exactly as the bank or M-Pesa shows it.");
  }
}

export async function proposeDestination(groupId: string, input: DestinationInput, actorUserId: string) {
  validateDestination(input);
  const destination = await prisma.settlementDestination.create({
    data: {
      groupId,
      type: input.type,
      provider: providerForDestination(input.type),
      accountNumber: input.type === "MPESA_PHONE" ? toMsisdn(input.accountNumber) : input.accountNumber.trim(),
      accountName: input.accountName.trim(),
      bankCode: input.bankCode?.trim() || null,
      accountReference: input.accountReference?.trim() || null,
      note: input.note?.trim() || null,
      proposedById: actorUserId
    }
  });
  await audit("SETTLEMENT_DESTINATION", destination.id, "SETTLEMENT_DESTINATION_PROPOSED", destination, actorUserId);
  return destination;
}

/**
 * Approve a proposed destination. The approver must not be the proposer.
 * The group's previous active destination is retired in the same step, so a
 * group never has two.
 */
export async function approveDestination(destinationId: string, actorUserId: string) {
  const destination = await prisma.settlementDestination.findUnique({ where: { id: destinationId } });
  if (!destination) throw new ApiHttpError(404, "DESTINATION_NOT_FOUND", "Settlement account not found.");
  if (destination.status !== "PROPOSED") {
    throw new ApiHttpError(409, "DESTINATION_NOT_PROPOSED", "Only a proposed account can be approved.");
  }
  if (destination.proposedById && destination.proposedById === actorUserId) {
    throw new ApiHttpError(
      403,
      "SAME_PERSON_APPROVAL",
      "The person who proposed a settlement account cannot also approve it. Ask another administrator."
    );
  }

  let recipientCode = destination.recipientCode;
  if (destination.type === "PAYSTACK_BANK" || destination.type === "PAYSTACK_MOBILE_MONEY") {
    const recipient = await createPaystackTransferRecipient({
      type: destination.type,
      accountName: destination.accountName,
      accountNumber: destination.accountNumber,
      bankCode: destination.bankCode ?? ""
    });
    recipientCode = recipient.recipientCode;
  }

  const now = new Date();
  const approved = await prisma.$transaction(async (tx) => {
    const retired = await tx.settlementDestination.updateMany({
      where: { groupId: destination.groupId, status: "ACTIVE" },
      data: { status: "RETIRED", retiredAt: now }
    });
    const claimed = await tx.settlementDestination.updateMany({
      where: { id: destination.id, status: "PROPOSED" },
      data: { status: "ACTIVE", verifiedById: actorUserId, verifiedAt: now, recipientCode }
    });
    if (claimed.count === 0) {
      throw new ApiHttpError(409, "DESTINATION_NOT_PROPOSED", "This account was decided by someone else just now.");
    }
    return { retired: retired.count, destination: await tx.settlementDestination.findUniqueOrThrow({ where: { id: destination.id } }) };
  });
  await audit(
    "SETTLEMENT_DESTINATION",
    destination.id,
    "SETTLEMENT_DESTINATION_APPROVED",
    { retiredPrevious: approved.retired, recipientCode },
    actorUserId
  );
  return approved.destination;
}

export async function rejectDestination(destinationId: string, actorUserId: string, note: string) {
  const updated = await prisma.settlementDestination.updateMany({
    where: { id: destinationId, status: "PROPOSED" },
    data: { status: "REJECTED", note, verifiedById: actorUserId, verifiedAt: new Date() }
  });
  if (updated.count === 0) throw new ApiHttpError(409, "DESTINATION_NOT_PROPOSED", "Only a proposed account can be rejected.");
  await audit("SETTLEMENT_DESTINATION", destinationId, "SETTLEMENT_DESTINATION_REJECTED", { note }, actorUserId);
  return prisma.settlementDestination.findUnique({ where: { id: destinationId } });
}

export async function retireDestination(destinationId: string, actorUserId: string) {
  const updated = await prisma.settlementDestination.updateMany({
    where: { id: destinationId, status: "ACTIVE" },
    data: { status: "RETIRED", retiredAt: new Date() }
  });
  if (updated.count === 0) throw new ApiHttpError(409, "DESTINATION_NOT_ACTIVE", "Only the active account can be retired.");
  await audit("SETTLEMENT_DESTINATION", destinationId, "SETTLEMENT_DESTINATION_RETIRED", {}, actorUserId);
  return prisma.settlementDestination.findUnique({ where: { id: destinationId } });
}

/** The destination money may be paid to right now, or why not. */
export function destinationReady(destination: SettlementDestination | null, now = new Date()) {
  if (!destination || destination.status !== "ACTIVE" || !destination.verifiedAt) {
    return { ready: false as const, reason: "NO_ACTIVE_DESTINATION" };
  }
  const coolOffEnds = destination.verifiedAt.getTime() + env.SETTLEMENT_DESTINATION_COOLOFF_HOURS * 3_600_000;
  if (now.getTime() < coolOffEnds) {
    return { ready: false as const, reason: "DESTINATION_COOLING_OFF", until: new Date(coolOffEnds) };
  }
  return { ready: true as const };
}

// ---------------------------------------------------------------------------
// Building settlements
// ---------------------------------------------------------------------------

/** Gather each group's posted, unsettled SYSTEM payments into one settlement. */
export async function buildSettlements(now = new Date()) {
  const pending = await prisma.groupPayment.groupBy({
    by: ["groupId"],
    where: { settlementStatus: "PENDING", collectionMode: "SYSTEM", state: "LEDGER_POSTED" },
    _count: true
  });

  const created: Array<{ settlementId: string; groupId: string; amountCents: number; status: string }> = [];
  const skipped: Array<{ groupId: string; reason: string }> = [];

  for (const row of pending) {
    const destination = await prisma.settlementDestination.findFirst({
      where: { groupId: row.groupId, status: "ACTIVE" },
      orderBy: { verifiedAt: "desc" }
    });
    const readiness = destinationReady(destination, now);
    if (!readiness.ready || !destination) {
      skipped.push({ groupId: row.groupId, reason: readiness.ready ? "NO_ACTIVE_DESTINATION" : readiness.reason });
      continue;
    }

    const settlement = await prisma.$transaction(async (tx) => {
      const payments = await tx.groupPayment.findMany({
        where: { groupId: row.groupId, settlementStatus: "PENDING", collectionMode: "SYSTEM", state: "LEDGER_POSTED" },
        select: { id: true, groupAmountCents: true }
      });
      const amountCents = payments.reduce((sum, payment) => sum + payment.groupAmountCents, 0);
      if (payments.length === 0 || amountCents <= 0) return null;

      const record = await tx.settlement.create({
        data: {
          groupId: row.groupId,
          destinationId: destination.id,
          amountCents,
          provider: destination.provider,
          internalReference: createPaymentReference("STL"),
          status: amountCents > env.SETTLEMENT_AUTO_MAX_CENTS ? "AWAITING_APPROVAL" : "QUEUED"
        }
      });
      const claimed = await tx.groupPayment.updateMany({
        where: { id: { in: payments.map((payment) => payment.id) }, settlementStatus: "PENDING" },
        data: { settlementStatus: "IN_SETTLEMENT", settlementId: record.id }
      });
      if (claimed.count !== payments.length) {
        // Another run took some of these payments. Roll back and let the next run rebuild.
        throw new ApiHttpError(409, "SETTLEMENT_RACE", "Payments changed while the settlement was being built.");
      }
      return record;
    });

    if (settlement) {
      created.push({
        settlementId: settlement.id,
        groupId: settlement.groupId,
        amountCents: settlement.amountCents,
        status: settlement.status
      });
      await audit("SETTLEMENT", settlement.id, "SETTLEMENT_CREATED", {
        amountCents: settlement.amountCents,
        status: settlement.status,
        destinationId: destination.id
      });
    }
  }

  return { created, skipped };
}

export async function approveSettlement(settlementId: string, actorUserId: string) {
  const updated = await prisma.settlement.updateMany({
    where: { id: settlementId, status: "AWAITING_APPROVAL" },
    data: { status: "QUEUED", approvedById: actorUserId, approvedAt: new Date() }
  });
  if (updated.count === 0) throw new ApiHttpError(409, "SETTLEMENT_NOT_AWAITING_APPROVAL", "This settlement is not waiting for approval.");
  await audit("SETTLEMENT", settlementId, "SETTLEMENT_APPROVED", {}, actorUserId);
  return prisma.settlement.findUnique({ where: { id: settlementId } });
}

// ---------------------------------------------------------------------------
// Paying out
// ---------------------------------------------------------------------------

async function recordAttempt(tx: Tx | typeof prisma, settlementId: string, provider: string, result: string, request?: unknown, response?: unknown) {
  await tx.settlementAttempt.create({
    data: {
      settlementId,
      provider,
      result,
      requestJson: request === undefined ? null : JSON.stringify(request),
      responseJson: response === undefined ? null : JSON.stringify(response)
    }
  });
}

/** Pay one QUEUED settlement. Claims it first, so it can never be paid twice. */
export async function executeSettlement(settlementId: string, options: { force?: boolean } = {}) {
  if (!env.ENABLE_AUTOMATED_SETTLEMENT && !options.force) {
    throw new ApiHttpError(409, "SETTLEMENT_DISABLED", "Automated settlement is switched off (ENABLE_AUTOMATED_SETTLEMENT).");
  }
  const claimed = await prisma.settlement.updateMany({
    where: { id: settlementId, status: "QUEUED" },
    data: { status: "PROCESSING" }
  });
  if (claimed.count === 0) return prisma.settlement.findUnique({ where: { id: settlementId } });

  const settlement = await prisma.settlement.findUniqueOrThrow({
    where: { id: settlementId },
    include: { destination: true }
  });
  const destination = settlement.destination;
  if (destination.status !== "ACTIVE") {
    await prisma.settlement.update({
      where: { id: settlementId },
      data: { status: "FAILED", failureReason: "The group's settlement account is no longer active." }
    });
    await releasePaymentsToFailed(settlementId);
    return prisma.settlement.findUnique({ where: { id: settlementId } });
  }

  const request = {
    provider: destination.provider as "MPESA_DARAJA" | "PAYSTACK",
    groupId: null, // settlements always pay out of IWL's own account
    amountCents: settlement.amountCents,
    internalReference: settlement.internalReference,
    description: `Intelli-Cash settlement ${settlement.internalReference}`,
    phoneNumber: destination.type === "MPESA_PHONE" ? destination.accountNumber : null,
    recipientCode: destination.recipientCode,
    mpesaPayoutKind:
      destination.type === "MPESA_PAYBILL" ? ("PAYBILL" as const) : destination.type === "MPESA_TILL" ? ("TILL" as const) : ("PHONE" as const),
    receiverShortcode: destination.type === "MPESA_PAYBILL" || destination.type === "MPESA_TILL" ? destination.accountNumber : null,
    accountReference: destination.accountReference
  };
  await recordAttempt(prisma, settlementId, destination.provider, "STARTED", request);
  await audit("SETTLEMENT", settlementId, "SETTLEMENT_INITIATED", { amountCents: settlement.amountCents, destinationId: destination.id });

  let gateway;
  try {
    gateway = await initiatePayout(request);
  } catch (error) {
    if (error instanceof ApiHttpError) {
      // The provider answered and refused: nothing moved. A definite failure.
      await recordAttempt(prisma, settlementId, destination.provider, "REJECTED", undefined, { code: error.code, details: error.details ?? null });
      await failSettlement(settlement.internalReference, error.message, { code: error.code });
    } else {
      // No answer. The money may or may not have moved: do not retry.
      await recordAttempt(prisma, settlementId, destination.provider, "ERROR", undefined, { message: String(error) });
      await markSettlementUnknown(settlement.internalReference, "No answer from the provider when the payout was sent.");
    }
    return prisma.settlement.findUnique({ where: { id: settlementId } });
  }

  await prisma.settlement.update({
    where: { id: settlementId },
    data: { providerReference: gateway.providerReference }
  });
  await recordAttempt(prisma, settlementId, destination.provider, "ACCEPTED", undefined, gateway.metadata);

  // Mock mode sends nothing and no result will ever arrive: settle at once.
  if ((gateway.metadata as { mode?: string }).mode === "mock") {
    await completeSettlement(gateway.providerReference, `MOCK-${settlement.internalReference.slice(-8)}`, gateway.metadata);
  }
  return prisma.settlement.findUnique({ where: { id: settlementId } });
}

function settlementWhere(reference: string) {
  return { OR: [{ providerReference: reference }, { internalReference: reference }] };
}

async function releasePaymentsToFailed(settlementId: string) {
  await prisma.groupPayment.updateMany({
    where: { settlementId },
    data: { settlementStatus: "SETTLEMENT_FAILED" }
  });
}

/** The provider confirms the payout. Returns null when the reference is not a settlement. */
export async function completeSettlement(reference: string, receipt?: string | null, raw?: unknown) {
  const settlement = await prisma.settlement.findFirst({ where: settlementWhere(reference) });
  if (!settlement) return null;
  const now = new Date();
  const updated = await prisma.$transaction(async (tx) => {
    const claimed = await tx.settlement.updateMany({
      where: { id: settlement.id, status: { in: ["PROCESSING", "UNKNOWN"] } },
      data: { status: "SETTLED", settledAt: now, providerReceipt: receipt ?? null, failureReason: null }
    });
    if (claimed.count === 0) return false;
    await tx.groupPayment.updateMany({ where: { settlementId: settlement.id }, data: { settlementStatus: "SETTLED" } });
    await recordAttempt(tx, settlement.id, settlement.provider, "RESULT_SUCCESS", undefined, raw ?? null);
    return true;
  });
  if (updated) await audit("SETTLEMENT", settlement.id, "SETTLEMENT_SETTLED", { receipt: receipt ?? null });
  return prisma.settlement.findUnique({ where: { id: settlement.id } });
}

/** The provider says the payout did not happen. */
export async function failSettlement(reference: string, reason: string, raw?: unknown) {
  const settlement = await prisma.settlement.findFirst({ where: settlementWhere(reference) });
  if (!settlement) return null;
  const updated = await prisma.settlement.updateMany({
    where: { id: settlement.id, status: { in: ["PROCESSING", "UNKNOWN"] } },
    data: { status: "FAILED", failureReason: reason }
  });
  if (updated.count > 0) {
    await releasePaymentsToFailed(settlement.id);
    await recordAttempt(prisma, settlement.id, settlement.provider, "RESULT_FAILED", undefined, raw ?? { reason });
    await audit("SETTLEMENT", settlement.id, "SETTLEMENT_FAILED", { reason });
  }
  return prisma.settlement.findUnique({ where: { id: settlement.id } });
}

/** The outcome is not known. Parked for a check, never retried blindly. */
export async function markSettlementUnknown(reference: string, reason: string, raw?: unknown) {
  const settlement = await prisma.settlement.findFirst({ where: settlementWhere(reference) });
  if (!settlement) return null;
  const updated = await prisma.settlement.updateMany({
    where: { id: settlement.id, status: "PROCESSING" },
    data: { status: "UNKNOWN", failureReason: reason }
  });
  if (updated.count > 0) {
    await prisma.groupPayment.updateMany({ where: { settlementId: settlement.id }, data: { settlementStatus: "UNKNOWN" } });
    await recordAttempt(prisma, settlement.id, settlement.provider, "TIMEOUT", undefined, raw ?? { reason });
    await audit("SETTLEMENT", settlement.id, "SETTLEMENT_UNKNOWN", { reason });
  }
  return prisma.settlement.findUnique({ where: { id: settlement.id } });
}

/** Ask the provider what happened to a PROCESSING or UNKNOWN payout. */
export async function checkSettlementWithProvider(settlementId: string, actorUserId?: string | null) {
  const settlement = await prisma.settlement.findUnique({ where: { id: settlementId } });
  if (!settlement) throw new ApiHttpError(404, "SETTLEMENT_NOT_FOUND", "Settlement not found.");
  if (!["PROCESSING", "UNKNOWN"].includes(settlement.status)) return { settlement, asked: false };

  if (settlement.provider === "PAYSTACK") {
    const check = await fetchPaystackTransfer(settlement.internalReference);
    await recordAttempt(prisma, settlement.id, settlement.provider, "STATUS_QUERY", undefined, check.raw ?? check);
    if (check.checked && check.status === "SUCCESS") {
      return { settlement: await completeSettlement(settlement.internalReference, check.providerTransactionId, check.raw), asked: true };
    }
    if (check.checked && check.status === "FAILED") {
      return { settlement: await failSettlement(settlement.internalReference, check.reason ?? "Paystack reports the transfer failed.", check.raw), asked: true };
    }
    return { settlement, asked: check.checked };
  }

  // M-Pesa answers a status query at a result URL, later.
  const originator = await prisma.settlementAttempt.findFirst({
    where: { settlementId, result: "ACCEPTED" },
    orderBy: { createdAt: "desc" }
  });
  let originatorConversationId: string | null = null;
  try {
    originatorConversationId =
      (JSON.parse(originator?.responseJson ?? "{}") as { OriginatorConversationID?: string }).OriginatorConversationID ?? null;
  } catch {
    originatorConversationId = null;
  }
  const request = await requestMpesaTransactionStatus({
    transactionId: settlement.providerReceipt ?? settlement.providerReference ?? settlement.internalReference,
    originatorConversationId,
    occasion: settlement.internalReference
  });
  await recordAttempt(prisma, settlement.id, settlement.provider, "STATUS_QUERY", undefined, request.payload);
  if (actorUserId) {
    await audit("SETTLEMENT", settlement.id, "SETTLEMENT_RESOLVED", { action: "STATUS_QUERY_SENT", requested: request.requested }, actorUserId);
  }
  return { settlement, asked: request.requested };
}

/** A person has confirmed the outcome of an UNKNOWN payout with the provider. */
export async function resolveSettlement(
  settlementId: string,
  actorUserId: string,
  input: { outcome: "SETTLED" | "FAILED"; providerReceipt?: string | null; note: string }
) {
  const settlement = await prisma.settlement.findUnique({ where: { id: settlementId } });
  if (!settlement) throw new ApiHttpError(404, "SETTLEMENT_NOT_FOUND", "Settlement not found.");
  if (!["UNKNOWN", "PROCESSING"].includes(settlement.status)) {
    throw new ApiHttpError(409, "SETTLEMENT_NOT_OPEN", "Only an unknown or in-progress payout can be resolved by hand.");
  }
  if (input.outcome === "SETTLED" && !input.providerReceipt?.trim()) {
    throw new ApiHttpError(400, "RECEIPT_REQUIRED", "Enter the provider's receipt or transaction code as proof.");
  }
  await recordAttempt(prisma, settlementId, settlement.provider, "MANUAL", { by: actorUserId, ...input });
  const result =
    input.outcome === "SETTLED"
      ? await completeSettlement(settlement.internalReference, input.providerReceipt, { manual: true, note: input.note })
      : await failSettlement(settlement.internalReference, `Resolved by hand: ${input.note}`, { manual: true });
  await audit("SETTLEMENT", settlementId, "SETTLEMENT_RESOLVED", input, actorUserId);
  return result;
}

/** A FAILED settlement's payments go back into the queue for the next run. */
export async function requeueSettlement(settlementId: string, actorUserId: string) {
  const outcome = await prisma.$transaction(async (tx) => {
    const cancelled = await tx.settlement.updateMany({
      where: { id: settlementId, status: "FAILED" },
      data: { status: "CANCELLED" }
    });
    if (cancelled.count === 0) return 0;
    const released = await tx.groupPayment.updateMany({
      where: { settlementId },
      data: { settlementStatus: "PENDING", settlementId: null }
    });
    return released.count;
  });
  if (outcome === 0) {
    const current = await prisma.settlement.findUnique({ where: { id: settlementId }, select: { status: true } });
    if (current?.status !== "CANCELLED") {
      throw new ApiHttpError(409, "SETTLEMENT_NOT_FAILED", "Only a failed settlement can be sent again.");
    }
  }
  await audit("SETTLEMENT", settlementId, "SETTLEMENT_REQUEUED", { payments: outcome }, actorUserId);
  return prisma.settlement.findUnique({ where: { id: settlementId } });
}

// ---------------------------------------------------------------------------
// The loop
// ---------------------------------------------------------------------------

export async function runSettlementCycle(now = new Date()) {
  // Housekeeping first: payments nobody completed are closed off, and any
  // that did go through after all are posted, before batches are built.
  const stale = await expireStalePayments(now);
  const built = await buildSettlements(now);
  const paid: string[] = [];
  if (env.ENABLE_AUTOMATED_SETTLEMENT) {
    const queued = await prisma.settlement.findMany({ where: { status: "QUEUED" }, select: { id: true } });
    for (const settlement of queued) {
      await executeSettlement(settlement.id);
      paid.push(settlement.id);
    }
  }
  return { ...built, paid, stale };
}

const SETTLEMENT_INTERVAL_MS = 15 * 60 * 1000;

/** Starts the settlement loop (not in tests). Returns a function that stops it. */
export function startSettlementLoop(): () => void {
  if (env.NODE_ENV === "test") return () => undefined;
  let running = false;
  const tick = async () => {
    if (running) return;
    running = true;
    try {
      const outcome = await runSettlementCycle();
      if (outcome.created.length > 0 || outcome.paid.length > 0) {
        console.log(`Settlements: built ${outcome.created.length}, paid ${outcome.paid.length}.`);
      }
    } catch (error) {
      console.error("Settlement run failed", error);
    } finally {
      running = false;
    }
  };
  const first = setTimeout(tick, 60_000);
  const timer = setInterval(tick, SETTLEMENT_INTERVAL_MS);
  first.unref();
  timer.unref();
  return () => {
    clearTimeout(first);
    clearInterval(timer);
  };
}
