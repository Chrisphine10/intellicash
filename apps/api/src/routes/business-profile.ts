import { Router } from "express";
import { z } from "zod";
import { requireAuth } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";

export const businessProfileRouter = Router();

/**
 * The group's collective enterprise.
 *
 * Every write keeps the current row AND appends a snapshot against the visit it
 * was recorded at. That is the whole design: without the snapshot, updating the
 * revenue figure destroys the previous one, and "did this group grow between
 * visits" — the only question the profile exists to answer — becomes
 * unanswerable the first time anyone edits it.
 *
 * Amounts are in cents, matching the ledger. Shillings-as-float is how a
 * financial record ends up out by a rounding error nobody can trace.
 */

const profileSchema = z.object({
  enterpriseType: z.string().max(200).nullish(),
  description: z.string().max(2000).nullish(),
  monthlyRevenueCents: z.number().int().min(0).nullish(),
  monthlyCostsCents: z.number().int().min(0).nullish(),
  employsPeople: z.number().int().min(0).max(10000).nullish(),
  startedOn: z.coerce.date().nullish(),
  mainChallenge: z.string().max(2000).nullish(),
  supportNeeded: z.string().max(2000).nullish(),
  /** The visit this was recorded at, so the snapshot has an occasion. */
  visitId: z.string().optional()
});

async function loadGroupInScope(userScope: ReturnType<typeof scopeGroupWhere>, groupId: string) {
  const group = await prisma.group.findFirst({
    where: { AND: [{ id: groupId }, userScope] },
    select: { id: true, name: true, code: true }
  });
  if (!group) {
    throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
  }
  return group;
}

function serialize(profile: {
  enterpriseType: string | null;
  description: string | null;
  monthlyRevenueCents: number | null;
  monthlyCostsCents: number | null;
  employsPeople: number | null;
  startedOn?: Date | null;
  mainChallenge: string | null;
  supportNeeded: string | null;
  updatedAt?: Date;
}) {
  const revenue = profile.monthlyRevenueCents;
  const costs = profile.monthlyCostsCents;
  return {
    ...profile,
    // Computed, never stored: a margin column would go stale the moment either
    // figure is edited without it.
    monthlyMarginCents: revenue === null || costs === null ? null : revenue - costs
  };
}

businessProfileRouter.get(
  "/groups/:groupId/business-profile",
  requireAuth("visits:read"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(scopeGroupWhere(req.user), req.params.groupId as string);
      const profile = await prisma.groupBusinessProfile.findUnique({
        where: { groupId: group.id },
        include: { versions: { orderBy: { recordedAt: "desc" }, take: 12 } }
      });

      if (!profile) {
        // Absent is a real answer — the group has not been asked yet, which is
        // different from having no enterprise.
        ok(res, { group, profile: null, history: [], recorded: false });
        return;
      }

      ok(res, {
        group,
        profile: serialize(profile),
        recorded: true,
        history: profile.versions.map((version) => ({
          visitId: version.visitId,
          recordedAt: version.recordedAt,
          ...serialize(version)
        }))
      });
    } catch (error) {
      next(error);
    }
  }
);

businessProfileRouter.put(
  "/groups/:groupId/business-profile",
  requireAuth("visits:write"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(scopeGroupWhere(req.user), req.params.groupId as string);
      const payload = profileSchema.parse(req.body);

      const data = {
        enterpriseType: payload.enterpriseType ?? null,
        description: payload.description ?? null,
        monthlyRevenueCents: payload.monthlyRevenueCents ?? null,
        monthlyCostsCents: payload.monthlyCostsCents ?? null,
        employsPeople: payload.employsPeople ?? null,
        startedOn: payload.startedOn ?? null,
        mainChallenge: payload.mainChallenge ?? null,
        supportNeeded: payload.supportNeeded ?? null
      };

      const profile = await prisma.$transaction(async (tx) => {
        const saved = await tx.groupBusinessProfile.upsert({
          where: { groupId: group.id },
          create: { groupId: group.id, ...data },
          update: data
        });

        // A snapshot needs an occasion. Without a visit there is nothing to
        // compare "between visits" against, and — because SQLite treats NULLs
        // as distinct — a null visitId would slip past the unique index and
        // append a fresh row on every save rather than correcting the last one.
        if (payload.visitId) {
          const snapshot = {
            enterpriseType: data.enterpriseType,
            description: data.description,
            monthlyRevenueCents: data.monthlyRevenueCents,
            monthlyCostsCents: data.monthlyCostsCents,
            employsPeople: data.employsPeople,
            mainChallenge: data.mainChallenge,
            supportNeeded: data.supportNeeded
          };
          // One snapshot per visit, corrected in place if the agent revises it
          // during the same visit — a resent document must not append twice.
          await tx.groupBusinessProfileVersion.upsert({
            where: {
              profileId_visitId: { profileId: saved.id, visitId: payload.visitId }
            },
            create: {
              profileId: saved.id,
              groupId: group.id,
              visitId: payload.visitId,
              ...snapshot
            },
            update: snapshot
          });
        }

        return saved;
      });

      ok(res, { group, profile: serialize(profile), recorded: true });
    } catch (error) {
      next(error);
    }
  }
);
