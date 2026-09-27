import { Router, type Request } from "express";
import { z } from "zod";
import { env } from "../config/env";
import { requireAdmin, requireAuth, type AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import {
  assertMayConfigureGroupPayments,
  assertMaySeeGroupPayments,
  mayConfigureGroupPayments
} from "../services/group-payment-access";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import { appendAuditEvent } from "../services/audit-service";
import { feesBalance } from "../services/fee-engine";
import {
  markPaymentReversed,
  OPEN_STATES,
  postVerifiedPayment,
  releaseHeldPayment
} from "../services/group-payment-service";
import { computeQuote, GATEWAY_PROVIDERS, paymentSettingsFor } from "../services/payment-settings-service";
import {
  approveDestination,
  approveSettlement,
  buildSettlements,
  checkSettlementWithProvider,
  DESTINATION_TYPES,
  destinationReady,
  executeSettlement,
  proposeDestination,
  rejectDestination,
  requeueSettlement,
  resolveSettlement,
  retireDestination
} from "../services/settlement-service";

/**
 * Payment administration.
 *
 * Group side (a platform admin, or the group's own account):
 *   payment settings, and proposing a settlement account.
 * Platform side (IWL admins only — requireAdmin on every route):
 *   fee rules, approving settlement accounts, reconciliation, settlements,
 *   and deciding held payments.
 *
 * No new permission strings (see role-permission-service: a new string never
 * reaches existing permission templates). Existing ones are used: payments:read
 * to look, payments:write to configure, payments:approve to release money —
 * each ALSO behind requireAdmin, because partner roles hold payments:* for
 * their wallets and must not see or move group money.
 */
const router = Router();

function actor(req: Request) {
  if (!req.user) throw new ApiHttpError(401, "UNAUTHENTICATED", "Please sign in to continue.");
  return req.user;
}

async function groupInScope(req: Request, groupId: string) {
  const group = await prisma.group.findFirst({
    where: scopeGroupWhere(req.user, { id: groupId }),
    select: { id: true, name: true, code: true }
  });
  if (!group) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
  return group;
}

function maskAccount(value: string) {
  return value.length <= 4 ? value : `${"•".repeat(Math.min(6, value.length - 4))}${value.slice(-4)}`;
}

// ---------------------------------------------------------------------------
// Group payment settings + settlement destinations
// ---------------------------------------------------------------------------

router.get("/groups/:groupId/payment-settings", requireAuth("groups:read"), async (req, res, next) => {
  try {
    const group = await groupInScope(req, String(req.params.groupId));
    assertMaySeeGroupPayments(req.user);
    const [settings, destinations, settlements] = await Promise.all([
      paymentSettingsFor(group.id),
      prisma.settlementDestination.findMany({ where: { groupId: group.id }, orderBy: { createdAt: "desc" }, take: 20 }),
      prisma.settlement.findMany({
        where: { groupId: group.id },
        orderBy: { createdAt: "desc" },
        take: 20,
        select: { id: true, amountCents: true, status: true, provider: true, internalReference: true, providerReceipt: true, createdAt: true, settledAt: true, failureReason: true }
      })
    ]);
    const active = destinations.find((destination) => destination.status === "ACTIVE") ?? null;
    const readiness = destinationReady(active);
    // The group sees its own account numbers in full; nobody else outside the
    // platform does.
    const canConfigure = mayConfigureGroupPayments(req.user, group.id);
    const fullNumbers = canConfigure;
    ok(res, {
      group,
      canConfigure,
      settings: {
        collectionMode: settings.collectionMode,
        explicit: settings.explicit,
        enabledProviders: settings.enabledProviders,
        memberSelfPayEnabled: settings.memberSelfPayEnabled,
        ownCredentialProviders: settings.ownCredentialProviders
      },
      destinations: destinations.map((destination) => ({
        ...destination,
        accountNumber: fullNumbers ? destination.accountNumber : maskAccount(destination.accountNumber)
      })),
      activeDestinationReady: readiness.ready,
      activeDestinationNote: readiness.ready ? null : readiness.reason,
      coolOffUntil: !readiness.ready && "until" in readiness ? readiness.until : null,
      settlements,
      automatedSettlement: env.ENABLE_AUTOMATED_SETTLEMENT
    });
  } catch (error) {
    next(error);
  }
});

const settingsSchema = z.object({
  collectionMode: z.enum(["SYSTEM", "OWN_ACCOUNT"]),
  // Empty is allowed: the group takes no online payments (cash and M-Pesa
  // Classic still work).
  enabledProviders: z.array(z.enum(GATEWAY_PROVIDERS)),
  memberSelfPayEnabled: z.boolean().default(false)
});

router.put("/groups/:groupId/payment-settings", requireAuth("groups:read"), async (req, res, next) => {
  try {
    const group = await groupInScope(req, String(req.params.groupId));
    assertMayConfigureGroupPayments(req.user, group.id);
    const body = settingsSchema.parse(req.body);
    const current = await paymentSettingsFor(group.id);
    if (body.collectionMode === "OWN_ACCOUNT") {
      const missing = body.enabledProviders.filter((provider) => !current.ownCredentialProviders.includes(provider));
      if (missing.length > 0) {
        throw new ApiHttpError(
          400,
          "GROUP_PROVIDER_NOT_CONFIGURED",
          "To collect into the group's own account, first add and switch on its own details for every provider it uses.",
          { missing }
        );
      }
    }
    const data = {
      collectionMode: body.collectionMode,
      enabledProvidersJson: JSON.stringify(body.enabledProviders),
      memberSelfPayEnabled: body.memberSelfPayEnabled,
      updatedById: req.user?.id ?? null
    };
    const saved = await prisma.groupPaymentSettings.upsert({
      where: { groupId: group.id },
      create: { groupId: group.id, ...data },
      update: data
    });
    await appendAuditEvent({
      actorUserId: req.user?.id ?? null,
      entityType: "GROUP",
      entityId: group.id,
      type: "GROUP_PAYMENT_SETTINGS_UPDATED",
      payload: { before: current, after: body }
    });
    ok(res, saved);
  } catch (error) {
    next(error);
  }
});

const destinationSchema = z.object({
  type: z.enum(DESTINATION_TYPES),
  accountNumber: z.string().trim().min(3).max(40),
  accountName: z.string().trim().min(3).max(120),
  bankCode: z.string().trim().max(40).optional(),
  accountReference: z.string().trim().max(40).optional(),
  note: z.string().trim().max(300).optional()
});

router.post("/groups/:groupId/settlement-destinations", requireAuth("groups:read"), async (req, res, next) => {
  try {
    const group = await groupInScope(req, String(req.params.groupId));
    assertMayConfigureGroupPayments(req.user, group.id);
    const body = destinationSchema.parse(req.body);
    ok(res.status(201), await proposeDestination(group.id, body, actor(req).id));
  } catch (error) {
    next(error);
  }
});

router.post("/settlement-destinations/:id/approve", requireAuth("payments:approve"), requireAdmin, async (req, res, next) => {
  try {
    ok(res, await approveDestination(String(req.params.id), actor(req).id));
  } catch (error) {
    next(error);
  }
});

router.post("/settlement-destinations/:id/reject", requireAuth("payments:approve"), requireAdmin, async (req, res, next) => {
  try {
    const { note } = z.object({ note: z.string().trim().min(3).max(300) }).parse(req.body);
    ok(res, await rejectDestination(String(req.params.id), actor(req).id, note));
  } catch (error) {
    next(error);
  }
});

/** Retiring stops payouts, so the group itself may do it as well as an admin. */
router.post("/settlement-destinations/:id/retire", requireAuth("groups:read"), async (req, res, next) => {
  try {
    const destination = await prisma.settlementDestination.findUnique({ where: { id: String(req.params.id) } });
    if (!destination) throw new ApiHttpError(404, "DESTINATION_NOT_FOUND", "Settlement account not found.");
    await groupInScope(req, destination.groupId);
    assertMayConfigureGroupPayments(req.user, destination.groupId);
    ok(res, await retireDestination(destination.id, actor(req).id));
  } catch (error) {
    next(error);
  }
});

// ---------------------------------------------------------------------------
// Fee rules (platform)
// ---------------------------------------------------------------------------

const feeRuleSchema = z
  .object({
    kind: z.enum(["PLATFORM", "PROVIDER"]),
    provider: z.enum(GATEWAY_PROVIDERS).nullable().optional(),
    minCents: z.number().int().min(0),
    maxCents: z.number().int().min(0).nullable().optional(),
    fixedCents: z.number().int().min(0).max(10_000_00),
    percentBps: z.number().int().min(0).max(4_999),
    active: z.boolean().default(true),
    note: z.string().trim().max(300).optional()
  })
  .refine((rule) => rule.maxCents == null || rule.maxCents >= rule.minCents, {
    message: "The upper bound must not be below the lower bound.",
    path: ["maxCents"]
  });

router.get("/payment-admin/fee-rules", requireAuth("payments:read"), requireAdmin, async (req, res, next) => {
  try {
    const includeRetired = req.query.all === "1";
    const rules = await prisma.feeRule.findMany({
      where: includeRetired ? {} : { active: true },
      orderBy: [{ kind: "asc" }, { provider: "asc" }, { minCents: "asc" }]
    });
    ok(res, rules);
  } catch (error) {
    next(error);
  }
});

router.post("/payment-admin/fee-rules", requireAuth("payments:write"), requireAdmin, async (req, res, next) => {
  try {
    const body = feeRuleSchema.parse(req.body);
    const rule = await prisma.feeRule.create({
      data: { ...body, provider: body.provider ?? null, maxCents: body.maxCents ?? null, createdById: req.user?.id ?? null }
    });
    await appendAuditEvent({ actorUserId: req.user?.id ?? null, entityType: "FEE_RULE", entityId: rule.id, type: "PAYMENT_FEE_RULE_CHANGED", payload: { created: rule } });
    ok(res.status(201), rule);
  } catch (error) {
    next(error);
  }
});

/** An edit retires the old rule and creates the next version. History stays. */
router.put("/payment-admin/fee-rules/:id", requireAuth("payments:write"), requireAdmin, async (req, res, next) => {
  try {
    const body = feeRuleSchema.parse(req.body);
    const previous = await prisma.feeRule.findUnique({ where: { id: String(req.params.id) } });
    if (!previous) throw new ApiHttpError(404, "FEE_RULE_NOT_FOUND", "Fee rule not found.");
    const next_ = await prisma.$transaction(async (tx) => {
      await tx.feeRule.update({ where: { id: previous.id }, data: { active: false } });
      return tx.feeRule.create({
        data: {
          ...body,
          provider: body.provider ?? null,
          maxCents: body.maxCents ?? null,
          version: previous.version + 1,
          replacesId: previous.id,
          createdById: req.user?.id ?? null
        }
      });
    });
    await appendAuditEvent({ actorUserId: req.user?.id ?? null, entityType: "FEE_RULE", entityId: next_.id, type: "PAYMENT_FEE_RULE_CHANGED", payload: { previous, next: next_ } });
    ok(res, next_);
  } catch (error) {
    next(error);
  }
});

router.post("/payment-admin/fee-rules/:id/deactivate", requireAuth("payments:write"), requireAdmin, async (req, res, next) => {
  try {
    const rule = await prisma.feeRule.update({ where: { id: String(req.params.id) }, data: { active: false } });
    await appendAuditEvent({ actorUserId: req.user?.id ?? null, entityType: "FEE_RULE", entityId: rule.id, type: "PAYMENT_FEE_RULE_CHANGED", payload: { deactivated: rule.id } });
    ok(res, rule);
  } catch (error) {
    next(error);
  }
});

router.post("/payment-admin/fee-preview", requireAuth("payments:read"), requireAdmin, async (req, res, next) => {
  try {
    const body = z
      .object({ provider: z.enum(GATEWAY_PROVIDERS), groupAmountCents: z.number().int().min(100) })
      .parse(req.body);
    ok(res, await computeQuote(body.provider, body.groupAmountCents));
  } catch (error) {
    next(error);
  }
});

// ---------------------------------------------------------------------------
// Reconciliation
// ---------------------------------------------------------------------------

const reconciliationQuery = z.object({
  from: z.string().optional(),
  to: z.string().optional(),
  groupId: z.string().optional(),
  provider: z.enum(GATEWAY_PROVIDERS).optional(),
  state: z.string().optional()
});

router.get("/payment-admin/reconciliation", requireAuth("payments:read"), requireAdmin, async (req, res, next) => {
  try {
    const query = reconciliationQuery.parse(req.query);
    const now = new Date();
    const from = query.from ? new Date(query.from) : new Date(now.getTime() - 30 * 86_400_000);
    const to = query.to ? new Date(query.to) : now;
    const payments = await prisma.groupPayment.findMany({
      where: {
        createdAt: { gte: from, lte: to },
        ...(query.groupId ? { groupId: query.groupId } : {}),
        ...(query.provider ? { provider: query.provider } : {}),
        ...(query.state ? { state: query.state } : {})
      },
      orderBy: { createdAt: "desc" },
      take: 1000,
      select: {
        id: true,
        createdAt: true,
        verifiedAt: true,
        groupId: true,
        group: { select: { name: true, code: true } },
        member: { select: { fullName: true } },
        purpose: true,
        provider: true,
        collectionMode: true,
        amountCents: true,
        groupAmountCents: true,
        platformFeeCents: true,
        providerFeeCents: true,
        platformFeeStatus: true,
        state: true,
        status: true,
        settlementStatus: true,
        settlementId: true,
        ledgerEntryId: true,
        internalReference: true,
        providerTransactionId: true,
        failureReason: true
      }
    });

    const received = new Set(["VERIFIED", "LEDGER_POSTED"]);
    const totals = {
      count: payments.length,
      collectedCents: 0,
      groupFundsCents: 0,
      platformFeesCollectedCents: 0,
      platformFeesReceivableCents: 0,
      providerChargesCents: 0,
      unsettledCents: 0,
      settledCents: 0
    };
    for (const payment of payments) {
      if (!received.has(payment.state)) continue;
      totals.collectedCents += payment.amountCents;
      totals.groupFundsCents += payment.groupAmountCents;
      totals.providerChargesCents += payment.providerFeeCents;
      if (payment.platformFeeStatus === "RECEIVABLE") totals.platformFeesReceivableCents += payment.platformFeeCents;
      else totals.platformFeesCollectedCents += payment.platformFeeCents;
      if (payment.collectionMode === "SYSTEM") {
        if (payment.settlementStatus === "SETTLED") totals.settledCents += payment.groupAmountCents;
        else totals.unsettledCents += payment.groupAmountCents;
      }
    }

    const halfHourAgo = now.getTime() - 30 * 60_000;
    const twoDaysAgo = now.getTime() - 48 * 3_600_000;
    const exceptions = payments.flatMap((payment) => {
      const reasons: string[] = [];
      if (payment.state === "HELD") reasons.push("HELD");
      if (payment.state === "VERIFIED") reasons.push("VERIFIED_NOT_POSTED");
      if ((OPEN_STATES as readonly string[]).includes(payment.state) && payment.createdAt.getTime() < halfHourAgo) {
        reasons.push("OPEN_TOO_LONG");
      }
      if (payment.settlementStatus === "UNKNOWN") reasons.push("SETTLEMENT_UNKNOWN");
      if (payment.settlementStatus === "SETTLEMENT_FAILED") reasons.push("SETTLEMENT_FAILED");
      if (payment.settlementStatus === "PENDING" && payment.verifiedAt && payment.verifiedAt.getTime() < twoDaysAgo) {
        reasons.push("NOT_SETTLED_48H");
      }
      if (payment.ledgerEntryId && ["FAILED", "CANCELLED", "EXPIRED", "HELD", "REVERSED", "REFUNDED"].includes(payment.state)) {
        reasons.push("IN_BOOKS_BUT_NOT_PAID");
      }
      if (payment.state !== "COMPLETED_LEGACY" && !feesBalance(payment)) reasons.push("FEES_DO_NOT_ADD_UP");
      return reasons.length > 0 ? [{ paymentId: payment.id, reasons }] : [];
    });

    const settlements = await prisma.settlement.findMany({
      where: { createdAt: { gte: from, lte: to }, ...(query.groupId ? { groupId: query.groupId } : {}) },
      orderBy: { createdAt: "desc" },
      take: 200,
      include: {
        group: { select: { name: true, code: true } },
        destination: { select: { type: true, accountName: true, accountNumber: true } },
        _count: { select: { payments: true } }
      }
    });

    ok(res, {
      range: { from: from.toISOString(), to: to.toISOString() },
      totals,
      payments,
      exceptions,
      settlements: settlements.map((settlement) => ({
        ...settlement,
        destination: { ...settlement.destination, accountNumber: maskAccount(settlement.destination.accountNumber) }
      })),
      automatedSettlement: env.ENABLE_AUTOMATED_SETTLEMENT,
      autoApproveLimitCents: env.SETTLEMENT_AUTO_MAX_CENTS
    });
  } catch (error) {
    next(error);
  }
});

router.get("/payment-admin/destinations", requireAuth("payments:read"), requireAdmin, async (req, res, next) => {
  try {
    const status = typeof req.query.status === "string" ? req.query.status : "PROPOSED";
    ok(
      res,
      await prisma.settlementDestination.findMany({
        where: { status },
        orderBy: { createdAt: "desc" },
        take: 200,
        include: { group: { select: { name: true, code: true } } }
      })
    );
  } catch (error) {
    next(error);
  }
});

// ---------------------------------------------------------------------------
// Held and unposted payments
// ---------------------------------------------------------------------------

const noteSchema = z.object({ note: z.string().trim().min(3).max(500) });

router.post("/payment-admin/payments/:id/release", requireAuth("payments:approve"), requireAdmin, async (req, res, next) => {
  try {
    const { note } = noteSchema.parse(req.body);
    const payment = await releaseHeldPayment(String(req.params.id), actor(req).id, note);
    if (!payment) throw new ApiHttpError(409, "PAYMENT_NOT_HELD", "Only a held payment can be released.");
    ok(res, payment);
  } catch (error) {
    next(error);
  }
});

router.post("/payment-admin/payments/:id/repost", requireAuth("payments:write"), requireAdmin, async (req, res, next) => {
  try {
    const payment = await postVerifiedPayment(String(req.params.id), actor(req).id);
    if (!payment) throw new ApiHttpError(404, "PAYMENT_NOT_FOUND", "Payment not found.");
    ok(res, payment);
  } catch (error) {
    next(error);
  }
});

router.post("/payment-admin/payments/:id/reverse", requireAuth("payments:approve"), requireAdmin, async (req, res, next) => {
  try {
    const body = noteSchema.extend({ refunded: z.boolean().default(false) }).parse(req.body);
    const result = await markPaymentReversed(String(req.params.id), actor(req).id, body.note, body.refunded);
    if (!result) throw new ApiHttpError(404, "PAYMENT_NOT_FOUND", "Payment not found.");
    if ("error" in result) {
      throw new ApiHttpError(
        409,
        "PAYMENT_ALREADY_POSTED",
        "This payment is already in the group's books. Correct it with a ledger adjustment, not here."
      );
    }
    ok(res, result.payment);
  } catch (error) {
    next(error);
  }
});

// ---------------------------------------------------------------------------
// Settlements
// ---------------------------------------------------------------------------

router.post("/payment-admin/settlements/build", requireAuth("payments:write"), requireAdmin, async (_req, res, next) => {
  try {
    ok(res, await buildSettlements());
  } catch (error) {
    next(error);
  }
});

router.post("/payment-admin/settlements/:id/approve", requireAuth("payments:approve"), requireAdmin, async (req, res, next) => {
  try {
    ok(res, await approveSettlement(String(req.params.id), actor(req).id));
  } catch (error) {
    next(error);
  }
});

/** Pay a queued settlement now. Only while automated settlement is switched on. */
router.post("/payment-admin/settlements/:id/pay", requireAuth("payments:approve"), requireAdmin, async (req, res, next) => {
  try {
    ok(res, await executeSettlement(String(req.params.id)));
  } catch (error) {
    next(error);
  }
});

router.post("/payment-admin/settlements/:id/check", requireAuth("payments:approve"), requireAdmin, async (req, res, next) => {
  try {
    ok(res, await checkSettlementWithProvider(String(req.params.id), actor(req).id));
  } catch (error) {
    next(error);
  }
});

router.post("/payment-admin/settlements/:id/resolve", requireAuth("payments:approve"), requireAdmin, async (req, res, next) => {
  try {
    const body = z
      .object({
        outcome: z.enum(["SETTLED", "FAILED"]),
        providerReceipt: z.string().trim().max(60).optional(),
        note: z.string().trim().min(3).max(500)
      })
      .parse(req.body);
    ok(res, await resolveSettlement(String(req.params.id), actor(req).id, body));
  } catch (error) {
    next(error);
  }
});

router.post("/payment-admin/settlements/:id/requeue", requireAuth("payments:approve"), requireAdmin, async (req, res, next) => {
  try {
    ok(res, await requeueSettlement(String(req.params.id), actor(req).id));
  } catch (error) {
    next(error);
  }
});

export { router as paymentAdminRouter };
