import { Router, type Request } from "express";
import { z } from "zod";
import { env } from "../config/env";
import { appendAuditEvent } from "../services/audit-service";
import { requireAuth } from "../middleware/auth";
import { memberScopeForUser, scopeGroupWhere } from "../services/account-scope";
import { createPaymentReference, initiateIncomingPayment } from "../services/payment-service";
import { assertFollowsGroupRules } from "../services/group-rules-service";
import { loadLoanPositions } from "../services/loan-position-service";
import { legacyStatus, reconcileOpenPayment } from "../services/group-payment-service";
import {
  assertProviderUsable,
  computeQuote,
  credentialGroupId,
  feesAreActive,
  ledgerTypeForPurpose,
  paymentSettingsFor,
  readQuote,
  signQuote,
  type GatewayProvider
} from "../services/payment-settings-service";
import { ApiHttpError, ok } from "../lib/http";
import { assertMaySeeGroupPayments } from "../services/group-payment-access";
import { prisma } from "../lib/prisma";

/**
 * Gateway payments into a VSLA group.
 *
 * Two of the app's money methods are automated and land here:
 *   - **M-Pesa** → a Daraja STK push; the member approves on their handset
 *     and the STK callback settles the payment.
 *   - **Paystack** → a hosted checkout link the payer opens.
 * "M-Pesa Classic" deliberately does NOT come through here: the member pays
 * on their own and the treasurer types the confirmation code onto the ledger
 * entry, which is why that method still asks for a reference.
 *
 * The member names what the GROUP should receive. The IWL fee and the
 * provider's cost are added on top (a quote shows the breakdown first); the
 * provider collects the total; the server verifies it and posts ONLY the
 * group amount to the ledger. See services/group-payment-service.ts.
 *
 * Gated on `ledger:write` rather than `payments:write`: this is a group's own
 * money movement, and it is the scope group accounts and the mobile
 * MOBILE_CORE keys already hold (`payments:write` is for partner wallets and
 * would not reach existing keys without re-minting them). Members paying for
 * themselves use the /members/me routes, which need no ledger permission
 * because the server does the posting.
 */
const router = Router();

const providerSchema = z.enum(["MPESA_DARAJA", "PAYSTACK"]);
const purposeSchema = z.enum(["SHARE_PURCHASE", "SOCIAL_FUND", "LOAN_REPAYMENT", "FINE", "OTHER"]);
const phoneSchema = z
  .string()
  .trim()
  .regex(/^(?:\+?254|0)?[17]\d{8}$/, "Enter a valid Kenyan phone number.");

const quoteSchema = z.object({
  provider: providerSchema,
  purpose: purposeSchema.default("SHARE_PURCHASE"),
  /** What the group should receive. */
  groupAmountCents: z.number().int().min(100),
  memberId: z.string().min(1).optional(),
  meetingId: z.string().min(1).optional()
});

const initiateSchema = z
  .object({
    provider: providerSchema,
    purpose: purposeSchema.default("SHARE_PURCHASE"),
    /** From POST .../payments/quote. Required once fees are switched on. */
    quoteId: z.string().min(10).optional(),
    /** Old phones: the amount, with no quote. Treated as the group amount. */
    amountCents: z.number().int().min(100).optional(),
    memberId: z.string().min(1).optional(),
    meetingId: z.string().min(1).optional(),
    phoneNumber: phoneSchema.optional(),
    customerEmail: z.string().trim().email().optional(),
    /// Lets a phone retry after a dropped response without paying twice.
    clientRequestId: z.string().trim().min(6).max(120).optional()
  })
  .refine((body) => Boolean(body.quoteId) || body.amountCents !== undefined, {
    message: "Send the quote for this payment.",
    path: ["quoteId"]
  })
  .refine((body) => body.provider !== "MPESA_DARAJA" || Boolean(body.phoneNumber), {
    message: "M-Pesa needs the phone number to prompt.",
    path: ["phoneNumber"]
  });
// Paystack needs an email on every charge, but the member is never asked:
// `paystackEmailFor` takes it from the system (see createPayment).

/** Daraja wants 2547XXXXXXXX. */
function toDarajaMsisdn(phone: string) {
  const digits = phone.replace(/[^0-9]/g, "");
  if (digits.startsWith("254")) return digits;
  if (digits.startsWith("0")) return `254${digits.slice(1)}`;
  return `254${digits}`;
}

export const paymentSelect = {
  id: true,
  groupId: true,
  memberId: true,
  meetingId: true,
  purpose: true,
  provider: true,
  amountCents: true,
  groupAmountCents: true,
  platformFeeCents: true,
  providerFeeCents: true,
  currency: true,
  phoneNumber: true,
  status: true,
  state: true,
  collectionMode: true,
  settlementStatus: true,
  ledgerEntryId: true,
  internalReference: true,
  providerReference: true,
  providerTransactionId: true,
  checkoutUrl: true,
  failureReason: true,
  createdAt: true,
  verifiedAt: true,
  completedAt: true,
  member: { select: { id: true, fullName: true } }
} as const;

/** Only purposes the server can post itself are taken online. */
function assertPostablePurpose(purpose: string) {
  if (!ledgerTypeForPurpose(purpose)) {
    throw new ApiHttpError(
      400,
      "PURPOSE_NOT_SUPPORTED",
      "This kind of payment cannot be taken online. Record it in the meeting."
    );
  }
}

async function groupInScope(req: Request, groupId: string) {
  const group = await prisma.group.findFirst({
    where: scopeGroupWhere(req.user, { id: groupId }),
    select: { id: true, name: true }
  });
  if (!group) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside this account.");
  return group;
}

async function assertMemberInGroup(req: Request, groupId: string, memberId: string) {
  const member = await prisma.member.findFirst({
    where: memberScopeForUser(req.user, { id: memberId, groupId }),
    select: { id: true, status: true }
  });
  if (!member) throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist in this group.");
  if (member.status !== "ACTIVE") {
    throw new ApiHttpError(400, "MEMBER_NOT_ACTIVE", "This member is not active in the group.");
  }
}

async function assertMeetingInGroup(groupId: string, meetingId: string) {
  const meeting = await prisma.meeting.findFirst({ where: { id: meetingId, groupId }, select: { id: true } });
  if (!meeting) throw new ApiHttpError(404, "MEETING_NOT_FOUND", "Meeting does not exist in this group.");
}

/**
 * What a member may pay: the group's share value and social-fund amount, and
 * what they still owe on loans. Shown before paying and enforced at quote time.
 */
async function memberPaymentContext(groupId: string, memberId: string | null) {
  const [policy, positions] = await Promise.all([
    prisma.groupPolicy.findUnique({ where: { groupId }, select: { shareValueCents: true, socialFundCents: true } }),
    memberId ? loadLoanPositions(prisma, { memberIds: [memberId] }, new Date()) : Promise.resolve(null)
  ]);
  return {
    shareValueCents: policy?.shareValueCents ?? null,
    socialFundCents: policy?.socialFundCents ?? null,
    loanOutstandingCents: memberId ? Math.max(0, positions?.get(memberId)?.outstandingCents ?? 0) : 0
  };
}

/** The breakdown shown to the member before they agree to pay. */
async function buildQuote(input: {
  groupId: string;
  provider: GatewayProvider;
  purpose: string;
  groupAmountCents: number;
  memberId: string | null;
  meetingId: string | null;
}) {
  assertPostablePurpose(input.purpose);
  const settings = await paymentSettingsFor(input.groupId);
  const collectionMode = assertProviderUsable(settings, input.provider);

  // Money into a group is always a member's money: the entry is booked to them.
  if (!input.memberId) {
    throw new ApiHttpError(400, "MEMBER_REQUIRED", "Choose the member this payment is for.");
  }
  // A loan repayment pays down what the member owes, never more — an
  // overpayment would sit in the loan fund as money nobody lent.
  if (input.purpose === "LOAN_REPAYMENT") {
    const context = await memberPaymentContext(input.groupId, input.memberId);
    if (context.loanOutstandingCents <= 0) {
      throw new ApiHttpError(400, "NO_LOAN_OUTSTANDING", "This member has no loan to repay.");
    }
    if (input.groupAmountCents > context.loanOutstandingCents) {
      throw new ApiHttpError(
        400,
        "REPAYMENT_TOO_LARGE",
        `This member owes KSh ${(context.loanOutstandingCents / 100).toLocaleString("en-KE")}; a repayment cannot be more.`,
        { loanOutstandingCents: context.loanOutstandingCents }
      );
    }
  }

  // The group's own rules: whole shares, the social fund amount. The server
  // posts this payment itself, so the check happens before any money moves.
  await assertFollowsGroupRules(prisma, input.groupId, {
    type: ledgerTypeForPurpose(input.purpose)!,
    amountCents: input.groupAmountCents,
    memberId: input.memberId,
    meetingId: input.meetingId
  });

  const fees = await computeQuote(input.provider, input.groupAmountCents);
  const signed = signQuote({
    groupId: input.groupId,
    memberId: input.memberId,
    provider: input.provider,
    purpose: input.purpose,
    groupAmountCents: fees.groupAmountCents,
    platformFeeCents: fees.platformFeeCents,
    providerFeeCents: fees.providerFeeCents,
    totalCents: fees.totalCents
  });
  return {
    quoteId: signed.quoteId,
    expiresAt: signed.expiresAt,
    provider: input.provider,
    purpose: input.purpose,
    collectionMode,
    groupAmountCents: fees.groupAmountCents,
    platformFeeCents: fees.platformFeeCents,
    providerFeeCents: fees.providerFeeCents,
    totalCents: fees.totalCents,
    currency: "KES"
  };
}

interface CreatePaymentInput {
  groupId: string;
  groupName: string;
  body: z.infer<typeof initiateSchema>;
  memberId: string | null;
  actorUserId?: string | null;
  customerName?: string | null;
}

/**
 * The email Paystack is given for a charge, without asking the member: their
 * own login's email, else that of the account making the request (the group's
 * login, charging at a meeting), else a per-member address on our own domain.
 * Paystack requires an email to open a checkout but delivers nothing the
 * group depends on to it, so a placeholder is safe; it is per member so
 * Paystack does not merge every member into one customer.
 */
async function paystackEmailFor(input: { memberId: string | null; actorUserId?: string | null; groupId: string }) {
  if (input.memberId) {
    const own = await prisma.user.findFirst({
      where: { memberId: input.memberId, status: { not: "CLOSED" } },
      select: { email: true }
    });
    if (own?.email) return own.email;
  }
  if (input.actorUserId) {
    const actor = await prisma.user.findUnique({ where: { id: input.actorUserId }, select: { email: true } });
    if (actor?.email) return actor.email;
  }
  return `member-${(input.memberId ?? input.groupId).toLowerCase()}@pay.intellicash.co.ke`;
}

/** Shared by the group route and the member self-pay route. */
async function createPayment(input: CreatePaymentInput) {
  const { body } = input;
  const customerEmail =
    body.provider === "PAYSTACK" ? body.customerEmail ?? (await paystackEmailFor(input)) : body.customerEmail;

  // Replaying the same clientRequestId returns the in-flight or settled
  // payment rather than prompting the member's phone a second time. A
  // FAILED attempt is different: the member must be able to try again, so
  // release the id and let a fresh attempt take it.
  if (body.clientRequestId) {
    const existing = await prisma.groupPayment.findUnique({
      where: { clientRequestId: body.clientRequestId },
      select: paymentSelect
    });
    if (existing && existing.groupId !== input.groupId) {
      throw new ApiHttpError(409, "REQUEST_ID_REUSED", "This request id belongs to another payment. Send a new one.");
    }
    if (existing && existing.status !== "FAILED" && existing.status !== "CANCELLED") return { payment: existing, replay: true };
    if (existing) {
      await prisma.groupPayment.update({ where: { id: existing.id }, data: { clientRequestId: null } });
    }
  }

  assertPostablePurpose(body.purpose);
  const settings = await paymentSettingsFor(input.groupId);
  const collectionMode = assertProviderUsable(settings, body.provider);

  // What will be charged. With a quote: recompute from today's rules and
  // refuse if anything moved, so the member pays exactly what they saw.
  let fees: { groupAmountCents: number; platformFeeCents: number; providerFeeCents: number; totalCents: number };
  let snapshot: unknown = null;
  if (body.quoteId) {
    const claims = readQuote(body.quoteId);
    if (
      claims.groupId !== input.groupId ||
      claims.provider !== body.provider ||
      claims.purpose !== body.purpose ||
      (claims.memberId ?? null) !== (input.memberId ?? null)
    ) {
      throw new ApiHttpError(400, "QUOTE_MISMATCH", "This quote was for a different payment. Get a new quote.");
    }
    const fresh = await computeQuote(body.provider, claims.groupAmountCents);
    if (fresh.totalCents !== claims.totalCents || fresh.platformFeeCents !== claims.platformFeeCents) {
      throw new ApiHttpError(409, "QUOTE_CHANGED", "The charges have changed since this quote. Get a new quote.", {
        quotedTotalCents: claims.totalCents,
        currentTotalCents: fresh.totalCents
      });
    }
    fees = fresh;
    snapshot = fresh.snapshot;
  } else {
    // An app that predates fees. While no fee is switched on it behaves as it
    // always did; once fees are on, it must update, or the member would be
    // charged a total its screen never showed.
    if (await feesAreActive()) {
      throw new ApiHttpError(
        426,
        "APP_UPDATE_REQUIRED",
        "Payment charges now apply and this app cannot show them. Update Intelli-Cash to pay online."
      );
    }
    const amount = body.amountCents!;
    fees = { groupAmountCents: amount, platformFeeCents: 0, providerFeeCents: 0, totalCents: amount };
    snapshot = { legacyClient: true };
  }

  const internalReference = createPaymentReference(body.provider === "MPESA_DARAJA" ? "GMP" : "GPS");
  const phoneNumber = body.phoneNumber ? toDarajaMsisdn(body.phoneNumber) : null;

  // Record the intent BEFORE calling the gateway, so a callback that beats
  // our own response still finds a row to settle.
  const created = await prisma.groupPayment.create({
    data: {
      groupId: input.groupId,
      memberId: input.memberId,
      meetingId: body.meetingId,
      purpose: body.purpose,
      provider: body.provider,
      amountCents: fees.totalCents,
      groupAmountCents: fees.groupAmountCents,
      platformFeeCents: fees.platformFeeCents,
      providerFeeCents: fees.providerFeeCents,
      feeSnapshotJson: JSON.stringify(snapshot),
      collectionMode,
      phoneNumber,
      customerEmail,
      internalReference,
      clientRequestId: body.clientRequestId,
      status: "PENDING",
      state: "INITIATED"
    },
    select: paymentSelect
  });

  let gateway;
  try {
    gateway = await initiateIncomingPayment({
      provider: body.provider,
      // SYSTEM collects with IWL's credentials; OWN_ACCOUNT with the group's.
      groupId: credentialGroupId(collectionMode, input.groupId),
      amountCents: fees.totalCents,
      internalReference,
      phoneNumber,
      customerEmail,
      customerName: input.customerName,
      description: `${body.purpose.replace(/_/g, " ").toLowerCase()} for ${input.groupName}`,
      metadata: { groupId: input.groupId, memberId: input.memberId, purpose: body.purpose },
      // WEB_ORIGIN may list several origins (CORS); the first is the public site.
      returnUrl: `${(env.WEB_ORIGIN.split(",")[0] ?? "").trim().replace(/\/$/, "")}/pay/complete`
    });
  } catch (error) {
    // The gateway refused — mark it failed so the row isn't left hanging.
    await prisma.groupPayment.update({
      where: { id: created.id },
      data: {
        status: "FAILED",
        state: "FAILED",
        failureReason: error instanceof Error ? error.message : "Payment could not be started."
      }
    });
    throw error;
  }

  const payment = await prisma.groupPayment.update({
    where: { id: created.id },
    data: {
      state: "PROCESSING",
      providerReference: gateway.providerReference,
      checkoutUrl: gateway.checkoutUrl ?? null,
      metadataJson: JSON.stringify({ initiate: gateway.metadata ?? {} })
    },
    select: paymentSelect
  });

  await appendAuditEvent({
    actorUserId: input.actorUserId ?? null,
    entityType: "GROUP_PAYMENT",
    entityId: payment.id,
    type: "GROUP_PAYMENT_INITIATED",
    payload: payment
  });

  return { payment, replay: false };
}

router.post("/groups/:id/payments/quote", requireAuth("ledger:write"), async (req, res, next) => {
  try {
    const body = quoteSchema.parse(req.body);
    const group = await groupInScope(req, String(req.params.id));
    if (body.memberId) await assertMemberInGroup(req, group.id, body.memberId);
    if (body.meetingId) await assertMeetingInGroup(group.id, body.meetingId);
    ok(
      res,
      await buildQuote({
        groupId: group.id,
        provider: body.provider,
        purpose: body.purpose,
        groupAmountCents: body.groupAmountCents,
        memberId: body.memberId ?? null,
        meetingId: body.meetingId ?? null
      })
    );
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/payments", requireAuth("ledger:write"), async (req, res, next) => {
  try {
    const body = initiateSchema.parse(req.body);
    const group = await groupInScope(req, String(req.params.id));
    if (body.memberId) await assertMemberInGroup(req, group.id, body.memberId);
    if (body.meetingId) await assertMeetingInGroup(group.id, body.meetingId);

    const result = await createPayment({
      groupId: group.id,
      groupName: group.name,
      body,
      memberId: body.memberId ?? null,
      actorUserId: req.user?.id,
      customerName: req.user?.name
    });
    ok(result.replay ? res : res.status(201), result.payment);
  } catch (error) {
    next(error);
  }
});

/** What a member of this group may pay online: share value, social fund, loan owed. */
router.get("/groups/:id/payments/member-context/:memberId", requireAuth("ledger:read"), async (req, res, next) => {
  try {
    const group = await groupInScope(req, String(req.params.id));
    assertMaySeeGroupPayments(req.user);
    await assertMemberInGroup(req, group.id, String(req.params.memberId));
    const settings = await paymentSettingsFor(group.id);
    ok(res, {
      ...(await memberPaymentContext(group.id, String(req.params.memberId))),
      providers: settings.enabledProviders
    });
  } catch (error) {
    next(error);
  }
});

/**
 * The group's gateway payments. `?unlinked=1` lists the ones the server has
 * posted that no phone entry has claimed yet — the phone offers to add them
 * to its book (a payment that finished after the phone stopped waiting).
 */
router.get("/groups/:id/payments", requireAuth("ledger:read"), async (req, res, next) => {
  try {
    const group = await groupInScope(req, String(req.params.id));
    // Names members and their phones: the group and IWL staff only, never a
    // partner (who reads the ledger, but gets group-level figures).
    assertMaySeeGroupPayments(req.user);
    const unlinked = req.query.unlinked === "1" || req.query.unlinked === "true";
    const payments = await prisma.groupPayment.findMany({
      where: {
        groupId: group.id,
        ...(unlinked ? { state: "LEDGER_POSTED" } : {})
      },
      select: paymentSelect,
      orderBy: { createdAt: "desc" },
      take: 100
    });
    if (!unlinked) {
      ok(res, payments);
      return;
    }
    // "Unlinked" = the ledger entry is the server's own (gp-...), i.e. no
    // phone entry claimed the payment.
    const entryIds = payments.map((payment) => payment.ledgerEntryId).filter((id): id is string => Boolean(id));
    const ownEntries = await prisma.ledgerEntry.findMany({
      where: { id: { in: entryIds }, clientRequestId: { startsWith: "gp-" } },
      select: { id: true, meetingId: true }
    });
    const own = new Map(ownEntries.map((entry) => [entry.id, entry]));
    ok(
      res,
      payments
        .filter((payment) => payment.ledgerEntryId && own.has(payment.ledgerEntryId))
        .map((payment) => ({ ...payment, postedMeetingId: own.get(payment.ledgerEntryId!)?.meetingId ?? null }))
    );
  } catch (error) {
    next(error);
  }
});

/**
 * Polled by the phone while the member approves the STK prompt. After a
 * minute with no callback, the server asks the provider itself, so a lost
 * callback cannot strand the member's money.
 */
router.get("/groups/:id/payments/:paymentId", requireAuth("ledger:read"), async (req, res, next) => {
  try {
    const group = await groupInScope(req, String(req.params.id));
    assertMaySeeGroupPayments(req.user);
    const full = await prisma.groupPayment.findFirst({
      where: { id: String(req.params.paymentId), groupId: group.id }
    });
    if (!full) throw new ApiHttpError(404, "PAYMENT_NOT_FOUND", "Payment not found.");
    await reconcileOpenPayment(full);
    const payment = await prisma.groupPayment.findUniqueOrThrow({ where: { id: full.id }, select: paymentSelect });
    ok(res, { ...payment, status: legacyStatus(payment.state) });
  } catch (error) {
    next(error);
  }
});

// ---------------------------------------------------------------------------
// A member paying into their own group, from their passbook.
// ---------------------------------------------------------------------------

const selfQuoteSchema = z.object({
  provider: providerSchema,
  purpose: z.enum(["SHARE_PURCHASE", "SOCIAL_FUND", "FINE", "LOAN_REPAYMENT"]).default("SHARE_PURCHASE"),
  groupAmountCents: z.number().int().min(100)
});

async function selfMember(req: Request) {
  if (req.user?.role !== "MEMBER" || !req.user.memberId) {
    throw new ApiHttpError(400, "NOT_A_MEMBER_ACCOUNT", "Only a member's own account can pay from the passbook.");
  }
  const member = await prisma.member.findUnique({
    where: { id: req.user.memberId },
    select: { id: true, groupId: true, status: true, group: { select: { name: true } } }
  });
  if (!member || member.status !== "ACTIVE") {
    throw new ApiHttpError(400, "MEMBER_NOT_ACTIVE", "This member is not active in the group.");
  }
  const settings = await paymentSettingsFor(member.groupId);
  if (!settings.memberSelfPayEnabled) {
    throw new ApiHttpError(403, "SELF_PAY_DISABLED", "Your group has not switched on paying from the passbook.");
  }
  return member;
}

/** Whether the passbook should offer "Pay online", and with what. */
router.get("/members/me/payments/options", requireAuth("members:read"), async (req, res, next) => {
  try {
    if (req.user?.role !== "MEMBER" || !req.user.memberId) {
      ok(res, { enabled: false, providers: [], shareValueCents: null, socialFundCents: null, loanOutstandingCents: 0 });
      return;
    }
    const member = await prisma.member.findUnique({
      where: { id: req.user.memberId },
      select: { groupId: true, status: true }
    });
    if (!member || member.status !== "ACTIVE") {
      ok(res, { enabled: false, providers: [], shareValueCents: null, socialFundCents: null, loanOutstandingCents: 0 });
      return;
    }
    const [settings, context] = await Promise.all([
      paymentSettingsFor(member.groupId),
      memberPaymentContext(member.groupId, req.user.memberId)
    ]);
    ok(res, {
      enabled: settings.memberSelfPayEnabled,
      providers: settings.memberSelfPayEnabled ? settings.enabledProviders : [],
      ...context
    });
  } catch (error) {
    next(error);
  }
});

router.post("/members/me/payments/quote", requireAuth("members:read"), async (req, res, next) => {
  try {
    const body = selfQuoteSchema.parse(req.body);
    const member = await selfMember(req);
    ok(
      res,
      await buildQuote({
        groupId: member.groupId,
        provider: body.provider,
        purpose: body.purpose,
        groupAmountCents: body.groupAmountCents,
        memberId: member.id,
        meetingId: null
      })
    );
  } catch (error) {
    next(error);
  }
});

router.post("/members/me/payments", requireAuth("members:read"), async (req, res, next) => {
  try {
    const body = initiateSchema.parse(req.body);
    if (!body.quoteId) throw new ApiHttpError(400, "QUOTE_REQUIRED", "Get a quote first.");
    if (!selfQuoteSchema.shape.purpose.removeDefault().safeParse(body.purpose).success) {
      throw new ApiHttpError(400, "PURPOSE_NOT_SUPPORTED", "Members can pay shares, the welfare fund, a fine or a loan repayment.");
    }
    const member = await selfMember(req);
    const result = await createPayment({
      groupId: member.groupId,
      groupName: member.group.name,
      body: { ...body, memberId: member.id, meetingId: undefined },
      memberId: member.id,
      actorUserId: req.user?.id,
      customerName: req.user?.name
    });
    ok(result.replay ? res : res.status(201), result.payment);
  } catch (error) {
    next(error);
  }
});

router.get("/members/me/payments", requireAuth("members:read"), async (req, res, next) => {
  try {
    if (!req.user?.memberId) throw new ApiHttpError(400, "NOT_A_MEMBER_ACCOUNT", "This account is not linked to a group member.");
    const payments = await prisma.groupPayment.findMany({
      where: { memberId: req.user.memberId },
      select: paymentSelect,
      orderBy: { createdAt: "desc" },
      take: 50
    });
    ok(res, payments);
  } catch (error) {
    next(error);
  }
});

router.get("/members/me/payments/:paymentId", requireAuth("members:read"), async (req, res, next) => {
  try {
    if (!req.user?.memberId) throw new ApiHttpError(400, "NOT_A_MEMBER_ACCOUNT", "This account is not linked to a group member.");
    const full = await prisma.groupPayment.findFirst({
      where: { id: String(req.params.paymentId), memberId: req.user.memberId }
    });
    if (!full) throw new ApiHttpError(404, "PAYMENT_NOT_FOUND", "Payment not found.");
    await reconcileOpenPayment(full);
    const payment = await prisma.groupPayment.findUniqueOrThrow({ where: { id: full.id }, select: paymentSelect });
    ok(res, { ...payment, status: legacyStatus(payment.state) });
  } catch (error) {
    next(error);
  }
});

export { router as groupPaymentsRouter };
