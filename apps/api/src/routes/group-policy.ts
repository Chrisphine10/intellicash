import { Router } from "express";
import { z } from "zod";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";

export const groupPolicyRouter = Router();

/**
 * Per-group governance settings.
 *
 * Only two things are genuinely configurable. The 30 Jul decisions removed the
 * rest: fines and welfare net off a payout rather than barring share-out, and
 * loans always net off and are never carried forward — so neither an
 * eligibility gate nor an outstanding-loan strategy exists to configure.
 */
export const POLICY_DEFAULTS = {
  defaultLoanTermMonths: 1,
  expenseFundType: "SOCIAL",
  /**
   * 0 bps — interest-free until a group deliberately sets its rate.
   *
   * The alternative, defaulting to the common 10% a month, would charge every
   * unconfigured group's members money their constitution never agreed to.
   * Lending interest-free by default invents no debt.
   */
  loanInterestRateBps: 0,
  /**
   * Both off. A group opts in to being texted.
   *
   * Each message spends the platform's SMS credits, a monthly meeting of 30
   * members is 30 summaries plus a confirmation per share purchase, and the
   * content is a member's own financial position going to a handset that is
   * frequently shared in a household. Defaulting these on would start
   * spending money and disclosing balances for every group on the platform
   * the moment the feature deployed.
   */
  smsSharePurchaseEnabled: false,
  smsMeetingSummaryEnabled: false
} as const;

/** Which funds an expense may legitimately be drawn from. */
const EXPENSE_FUND_TYPES = ["SOCIAL", "SAVINGS", "INTERNAL_LOAN"] as const;

/**
 * The effective policy for a group.
 *
 * Never returns null: a group without a row gets the defaults, which reproduce
 * the behaviour that existed before this table. Callers therefore do not need
 * to handle "unconfigured" as a special case, which is how a missing policy
 * would otherwise turn into a crash or a zero-month loan term.
 */
export async function policyFor(groupId: string) {
  const row = await prisma.groupPolicy.findUnique({ where: { groupId } });
  return {
    groupId,
    defaultLoanTermMonths: row?.defaultLoanTermMonths ?? POLICY_DEFAULTS.defaultLoanTermMonths,
    expenseFundType: row?.expenseFundType ?? POLICY_DEFAULTS.expenseFundType,
    loanInterestRateBps: row?.loanInterestRateBps ?? POLICY_DEFAULTS.loanInterestRateBps,
    smsSharePurchaseEnabled: row?.smsSharePurchaseEnabled ?? POLICY_DEFAULTS.smsSharePurchaseEnabled,
    smsMeetingSummaryEnabled:
      row?.smsMeetingSummaryEnabled ?? POLICY_DEFAULTS.smsMeetingSummaryEnabled,
    /** False when the group is running on defaults — useful to a UI. */
    configured: Boolean(row),
    updatedByUserId: row?.updatedByUserId ?? null,
    updatedAt: row?.updatedAt?.toISOString() ?? null
  };
}

async function loadGroupInScope(user: AuthenticatedUser | undefined, groupId: string) {
  const group = await prisma.group.findFirst({
    where: { AND: [{ id: groupId }, scopeGroupWhere(user)] },
    select: { id: true, name: true, code: true }
  });
  if (!group) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
  return group;
}

/**
 * Same rule as cycles and payment providers: a platform admin, or the group's
 * own account. Expressed as a role/scope check rather than a new permission
 * string, because ensureRolePermissionTemplates upserts with `update: {}` and
 * a new permission would never reach existing template rows.
 */
function assertMayConfigure(user: AuthenticatedUser | undefined, groupId: string) {
  if (!user) throw new ApiHttpError(401, "UNAUTHENTICATED", "Authentication is required.");
  if (user.permissions.includes("groups:write")) return;
  if (user.role === "GROUP_ACCOUNT" && user.groupId === groupId) return;

  throw new ApiHttpError(
    403,
    "FORBIDDEN",
    "Only a platform admin or the group's own account may change its policy."
  );
}

groupPolicyRouter.get("/groups/:groupId/policy", requireAuth("groups:read"), async (req, res, next) => {
  try {
    const group = await loadGroupInScope(req.user, req.params.groupId as string);
    ok(res, {
      group,
      policy: await policyFor(group.id),
      defaults: POLICY_DEFAULTS,
      canConfigure:
        Boolean(req.user?.permissions.includes("groups:write")) ||
        (req.user?.role === "GROUP_ACCOUNT" && req.user?.groupId === group.id)
    });
  } catch (error) {
    next(error);
  }
});

const updateSchema = z.object({
  // 1..60 months. A zero-month loan would be due the instant it is made, and a
  // term measured in years is not a VSLA loan.
  defaultLoanTermMonths: z.number().int().min(1).max(60).optional(),
  expenseFundType: z.enum(EXPENSE_FUND_TYPES).optional(),
  // 0..2000 bps a month, i.e. up to 20%. VSLA groups commonly charge 10%
  // (1000). The ceiling is deliberate: a typo of 10000 for "10%" would charge
  // 100% a month and, on a flat rate over a 12-month term, bill a member
  // twelve times what they borrowed.
  loanInterestRateBps: z.number().int().min(0).max(2000).optional(),
  // Outbound member SMS. See POLICY_DEFAULTS for why both start off.
  smsSharePurchaseEnabled: z.boolean().optional(),
  smsMeetingSummaryEnabled: z.boolean().optional()
});

groupPolicyRouter.put("/groups/:groupId/policy", requireAuth("groups:read"), async (req, res, next) => {
  try {
    const group = await loadGroupInScope(req.user, req.params.groupId as string);
    assertMayConfigure(req.user, group.id);

    const body = updateSchema.parse(req.body ?? {});
    if (Object.keys(body).length === 0) {
      throw new ApiHttpError(400, "NOTHING_TO_UPDATE", "Send at least one setting to change.");
    }

    await prisma.groupPolicy.upsert({
      where: { groupId: group.id },
      create: { groupId: group.id, ...body, updatedByUserId: req.user?.id ?? null },
      update: { ...body, updatedByUserId: req.user?.id ?? null }
    });

    const policy = await policyFor(group.id);
    ok(res, {
      policy,
      // Existing loans keep the term AND the rate they were made with. Both
      // are copied onto the Loan row at disbursement precisely so that
      // changing policy can never reprice money already lent.
      message:
        `Saved. New loans default to ${policy.defaultLoanTermMonths} month(s) at ` +
        `${(policy.loanInterestRateBps / 100).toFixed(2)}% a month; ` +
        `existing loans keep the term and rate they were agreed with.` +
        (policy.smsSharePurchaseEnabled || policy.smsMeetingSummaryEnabled
          ? ` Members will now be texted${
              policy.smsSharePurchaseEnabled && policy.smsMeetingSummaryEnabled
                ? " when they buy shares and when a meeting closes"
                : policy.smsSharePurchaseEnabled
                  ? " when they buy shares"
                  : " when a meeting closes"
            }; each message costs SMS credits.`
          : "")
    });
  } catch (error) {
    next(error);
  }
});

groupPolicyRouter.delete(
  "/groups/:groupId/policy",
  requireAuth("groups:read"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      assertMayConfigure(req.user, group.id);

      await prisma.groupPolicy.delete({ where: { groupId: group.id } }).catch(() => undefined);
      ok(res, { policy: await policyFor(group.id), message: "This group is back on the platform defaults." });
    } catch (error) {
      next(error);
    }
  }
);
