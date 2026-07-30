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
  expenseFundType: "SOCIAL"
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
  expenseFundType: z.enum(EXPENSE_FUND_TYPES).optional()
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
      // Existing loans keep the term they were made with; changing the default
      // must not silently reprice money already lent.
      message: `Saved. New loans default to ${policy.defaultLoanTermMonths} month(s); existing loans keep their agreed term.`
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
