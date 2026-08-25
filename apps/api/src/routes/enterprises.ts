import { Router } from "express";
import { z } from "zod";

import { requireAuth } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import {
  ENTERPRISE_STATUSES,
  MARKET_CHANNELS,
  MARKET_REACH_LADDER,
  SUPPORT_NEED_CATEGORIES,
  SUPPORT_NEED_PRIORITIES,
  SUPPORT_NEED_STATUSES,
  isMarketChannelKey,
  isMarketReachKey,
  marketChannelLabel,
  marketReachLabel,
  marketReachStep
} from "../domain/meal-indicators";

export const enterprisesRouter = Router();

/**
 * A group's enterprises: what it runs, who it sells to, and what it needs.
 *
 * Replaces the single `business-profile` route. That one carried a `groupId
 * @unique`, so a group running a poultry unit and a cereal store had to
 * overwrite one to record the other — and averaging two businesses with
 * different margins and different buyers produces a figure describing neither.
 *
 * Two additions beyond making it a list:
 *
 * **Market coverage.** Reach is stored as a rung on an ordered ladder rather
 * than free text, so a group that moves from selling at the farm gate to
 * selling into the county registers as a measurable step. Buyer count sits
 * beside it because revenue rising against a single buyer is growth and
 * concentration at once, and only one of those is good news.
 *
 * **Support needs as a controlled vocabulary.** "Twelve groups need cold
 * storage" is a sentence a programme manager can act on. The same twelve needs
 * typed twelve different ways is not, which is what the free-text
 * `supportNeeded` field could ever produce. That field is kept alongside rather
 * than replaced — the taxonomy makes needs countable, the sentence holds what
 * the taxonomy could not.
 *
 * Every write still appends a per-visit snapshot, which is the whole reason the
 * versions exist: without it, updating the revenue figure destroys the previous
 * one and "did this business grow between visits" becomes unanswerable.
 */

const enterpriseSchema = z.object({
  name: z.string().trim().min(1).max(200),
  enterpriseType: z.string().max(200).nullish(),
  description: z.string().max(2000).nullish(),
  monthlyRevenueCents: z.number().int().min(0).nullish(),
  monthlyCostsCents: z.number().int().min(0).nullish(),
  employsPeople: z.number().int().min(0).max(10000).nullish(),
  startedOn: z.coerce.date().nullish(),

  marketReach: z
    .string()
    .refine(isMarketReachKey, "Not a market reach on the ladder.")
    .nullish(),
  buyerCount: z.number().int().min(0).max(100000).nullish(),
  marketChannels: z
    .array(z.string().refine(isMarketChannelKey, "Not a known market channel."))
    .max(MARKET_CHANNELS.length)
    .optional(),
  hasFormalBuyerAgreement: z.boolean().nullish(),
  /** Months the enterprise actually sells in. A seasonal business compared
   *  month-on-month otherwise reads as collapsing. */
  salesMonths: z.array(z.number().int().min(1).max(12)).max(12).optional(),

  mainChallenge: z.string().max(2000).nullish(),
  supportNeeded: z.string().max(2000).nullish(),
  status: z.enum(ENTERPRISE_STATUSES).optional(),

  /** The visit this was recorded at, so the snapshot has an occasion. */
  visitId: z.string().optional()
});

const supportNeedSchema = z.object({
  needKey: z.string().trim().min(1).max(120),
  priority: z.enum(SUPPORT_NEED_PRIORITIES).default("MEDIUM"),
  status: z.enum(SUPPORT_NEED_STATUSES).default("OPEN"),
  detail: z.string().max(2000).nullish(),
  raisedAtVisitId: z.string().optional()
});

const supportNeedPatchSchema = z.object({
  priority: z.enum(SUPPORT_NEED_PRIORITIES).optional(),
  status: z.enum(SUPPORT_NEED_STATUSES).optional(),
  detail: z.string().max(2000).nullish(),
  metAtVisitId: z.string().optional()
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

/**
 * Out-of-scope reads 404 rather than 403, matching the house convention: a 403
 * confirms the record exists, which is itself a disclosure.
 */
async function loadEnterpriseInScope(
  userScope: ReturnType<typeof scopeGroupWhere>,
  enterpriseId: string
) {
  const enterprise = await prisma.groupEnterprise.findFirst({
    where: { AND: [{ id: enterpriseId }, { group: userScope }] },
    include: {
      group: { select: { id: true, name: true, code: true } },
      supportNeeds: { orderBy: [{ status: "asc" }, { raisedAt: "desc" }] }
    }
  });
  if (!enterprise) {
    throw new ApiHttpError(404, "ENTERPRISE_NOT_FOUND", "That enterprise does not exist.");
  }
  return enterprise;
}

function parseJsonArray(value: string): unknown[] {
  try {
    const parsed: unknown = JSON.parse(value);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    // A malformed column must not take the page down. An empty list is the
    // honest reading of "we cannot tell what was stored".
    return [];
  }
}

interface EnterpriseRow {
  id: string;
  groupId: string;
  name: string;
  enterpriseType: string | null;
  description: string | null;
  monthlyRevenueCents: number | null;
  monthlyCostsCents: number | null;
  employsPeople: number | null;
  startedOn?: Date | null;
  marketReach: string | null;
  buyerCount: number | null;
  marketChannelsJson: string;
  hasFormalBuyerAgreement: boolean | null;
  salesMonthsJson: string;
  mainChallenge: string | null;
  supportNeeded: string | null;
  status?: string | null;
  updatedAt?: Date;
  createdAt?: Date;
}

function serializeEnterprise(enterprise: EnterpriseRow) {
  const revenue = enterprise.monthlyRevenueCents;
  const costs = enterprise.monthlyCostsCents;
  const channels = parseJsonArray(enterprise.marketChannelsJson).filter(
    (value): value is string => typeof value === "string"
  );

  return {
    ...enterprise,
    marketChannelsJson: undefined,
    salesMonthsJson: undefined,
    // Computed, never stored: a margin column goes stale the moment either
    // figure is edited without it.
    monthlyMarginCents: revenue === null || costs === null ? null : revenue - costs,
    marketReachLabel: marketReachLabel(enterprise.marketReach),
    /** Rung on the ladder, so a caller can compare without re-deriving it. */
    marketReachStep: marketReachStep(enterprise.marketReach),
    marketChannels: channels.map((key) => ({ key, label: marketChannelLabel(key) })),
    salesMonths: parseJsonArray(enterprise.salesMonthsJson).filter(
      (value): value is number => typeof value === "number"
    )
  };
}

// ---------------------------------------------------------------------------
// Reference data
// ---------------------------------------------------------------------------

/**
 * The vocabularies a capture screen needs before it can render.
 *
 * Served rather than hardcoded in the clients so the ladder and the taxonomy
 * cannot drift apart between web, mobile and the reports that read them.
 */
enterprisesRouter.get(
  "/enterprise-reference",
  requireAuth("visits:read"),
  async (_req, res, next) => {
    try {
      const needTypes = await prisma.supportNeedType.findMany({
        where: { isActive: true },
        orderBy: [{ category: "asc" }, { position: "asc" }],
        select: { key: true, title: true, category: true, description: true }
      });

      ok(res, {
        marketReach: MARKET_REACH_LADDER.map((rung, index) => ({ ...rung, step: index + 1 })),
        marketChannels: MARKET_CHANNELS,
        supportNeedCategories: SUPPORT_NEED_CATEGORIES,
        supportNeedTypes: needTypes,
        priorities: SUPPORT_NEED_PRIORITIES,
        needStatuses: SUPPORT_NEED_STATUSES,
        enterpriseStatuses: ENTERPRISE_STATUSES
      });
    } catch (error) {
      next(error);
    }
  }
);

// ---------------------------------------------------------------------------
// Enterprises
// ---------------------------------------------------------------------------

enterprisesRouter.get(
  "/groups/:groupId/enterprises",
  requireAuth("visits:read"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(scopeGroupWhere(req.user), req.params.groupId as string);
      const enterprises = await prisma.groupEnterprise.findMany({
        where: { groupId: group.id },
        orderBy: [{ status: "asc" }, { createdAt: "asc" }],
        include: {
          supportNeeds: { orderBy: [{ status: "asc" }, { raisedAt: "desc" }] },
          versions: { orderBy: { recordedAt: "desc" }, take: 12 }
        }
      });

      ok(res, {
        group,
        enterprises: enterprises.map((enterprise) => ({
          ...serializeEnterprise(enterprise),
          supportNeeds: enterprise.supportNeeds,
          history: enterprise.versions.map((version) => ({
            visitId: version.visitId,
            recordedAt: version.recordedAt,
            ...serializeEnterprise({ ...version, id: version.id, name: version.name ?? "" })
          }))
        })),
        // Absent is a real answer: the group has not been asked yet, which is
        // different from having no enterprise.
        recorded: enterprises.length > 0
      });
    } catch (error) {
      next(error);
    }
  }
);

enterprisesRouter.post(
  "/groups/:groupId/enterprises",
  requireAuth("visits:write"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(scopeGroupWhere(req.user), req.params.groupId as string);
      const payload = enterpriseSchema.parse(req.body);

      const created = await prisma.$transaction(async (tx) => {
        const enterprise = await tx.groupEnterprise.create({
          data: { groupId: group.id, ...toColumns(payload) }
        });
        await writeSnapshot(tx, enterprise.id, group.id, payload);
        return enterprise;
      });

      res.status(201);
      ok(res, { group, enterprise: serializeEnterprise(created) });
    } catch (error) {
      next(error);
    }
  }
);

enterprisesRouter.patch(
  "/enterprises/:enterpriseId",
  requireAuth("visits:write"),
  async (req, res, next) => {
    try {
      const existing = await loadEnterpriseInScope(
        scopeGroupWhere(req.user),
        req.params.enterpriseId as string
      );
      const payload = enterpriseSchema.parse(req.body);

      const updated = await prisma.$transaction(async (tx) => {
        const enterprise = await tx.groupEnterprise.update({
          where: { id: existing.id },
          data: toColumns(payload)
        });
        await writeSnapshot(tx, enterprise.id, existing.groupId, payload);
        return enterprise;
      });

      ok(res, { group: existing.group, enterprise: serializeEnterprise(updated) });
    } catch (error) {
      next(error);
    }
  }
);

/**
 * Closing an enterprise, not deleting it.
 *
 * A closed business stays on the record: removing it erases the history that
 * explains a fall in group income, and leaves a revenue series with a step down
 * and no reason attached.
 */
enterprisesRouter.post(
  "/enterprises/:enterpriseId/close",
  requireAuth("visits:write"),
  async (req, res, next) => {
    try {
      const existing = await loadEnterpriseInScope(
        scopeGroupWhere(req.user),
        req.params.enterpriseId as string
      );
      const { status } = z
        .object({ status: z.enum(["DORMANT", "CLOSED"]).default("CLOSED") })
        .parse(req.body ?? {});

      const updated = await prisma.groupEnterprise.update({
        where: { id: existing.id },
        data: { status }
      });

      ok(res, { enterprise: serializeEnterprise(updated) });
    } catch (error) {
      next(error);
    }
  }
);

// ---------------------------------------------------------------------------
// Support needs
// ---------------------------------------------------------------------------

enterprisesRouter.post(
  "/enterprises/:enterpriseId/support-needs",
  requireAuth("visits:write"),
  async (req, res, next) => {
    try {
      const enterprise = await loadEnterpriseInScope(
        scopeGroupWhere(req.user),
        req.params.enterpriseId as string
      );
      const payload = supportNeedSchema.parse(req.body);

      const type = await prisma.supportNeedType.findUnique({
        where: { key: payload.needKey },
        select: { id: true, key: true, title: true, category: true, isActive: true }
      });
      if (!type || !type.isActive) {
        throw new ApiHttpError(
          400,
          "SUPPORT_NEED_TYPE_UNKNOWN",
          "That support need is not on the list. Add it under settings first."
        );
      }

      const need = await prisma.groupEnterpriseSupportNeed.create({
        data: {
          enterpriseId: enterprise.id,
          groupId: enterprise.groupId,
          typeId: type.id,
          // Snapshotted for the same reason a mentorship session snapshots its
          // topic: a record reading "needs <deleted>" is worthless as history,
          // and forbidding deletion instead leaves a settings screen nobody can
          // tidy.
          needKeySnapshot: type.key,
          needTitleSnapshot: type.title,
          needCategorySnapshot: type.category,
          priority: payload.priority,
          status: payload.status,
          detail: payload.detail ?? null,
          raisedAtVisitId: payload.raisedAtVisitId ?? null
        }
      });

      res.status(201);
      ok(res, { need });
    } catch (error) {
      next(error);
    }
  }
);

enterprisesRouter.patch(
  "/support-needs/:needId",
  requireAuth("visits:write"),
  async (req, res, next) => {
    try {
      const need = await prisma.groupEnterpriseSupportNeed.findFirst({
        where: {
          AND: [{ id: req.params.needId as string }, { enterprise: { group: scopeGroupWhere(req.user) } }]
        }
      });
      if (!need) {
        throw new ApiHttpError(404, "SUPPORT_NEED_NOT_FOUND", "That support need does not exist.");
      }

      const payload = supportNeedPatchSchema.parse(req.body);
      const becomingMet = payload.status === "MET" && need.status !== "MET";
      const leavingMet = payload.status !== undefined && payload.status !== "MET" && need.status === "MET";

      const updated = await prisma.groupEnterpriseSupportNeed.update({
        where: { id: need.id },
        data: {
          ...(payload.priority ? { priority: payload.priority } : {}),
          ...(payload.status ? { status: payload.status } : {}),
          ...(payload.detail === undefined ? {} : { detail: payload.detail ?? null }),
          // `metAt` is what days-to-meet is measured from, so it is stamped by
          // the server on the transition rather than accepted from the client.
          // Reopening clears it: a need that is met twice would otherwise carry
          // the first date and report a negative duration.
          ...(becomingMet
            ? { metAt: new Date(), metAtVisitId: payload.metAtVisitId ?? null }
            : {}),
          ...(leavingMet ? { metAt: null, metAtVisitId: null } : {})
        }
      });

      ok(res, { need: updated });
    } catch (error) {
      next(error);
    }
  }
);

// ---------------------------------------------------------------------------
// Shared write helpers
// ---------------------------------------------------------------------------

type EnterprisePayload = z.infer<typeof enterpriseSchema>;

function toColumns(payload: EnterprisePayload) {
  return {
    name: payload.name,
    enterpriseType: payload.enterpriseType ?? null,
    description: payload.description ?? null,
    monthlyRevenueCents: payload.monthlyRevenueCents ?? null,
    monthlyCostsCents: payload.monthlyCostsCents ?? null,
    employsPeople: payload.employsPeople ?? null,
    startedOn: payload.startedOn ?? null,
    marketReach: payload.marketReach ?? null,
    buyerCount: payload.buyerCount ?? null,
    marketChannelsJson: JSON.stringify(payload.marketChannels ?? []),
    hasFormalBuyerAgreement: payload.hasFormalBuyerAgreement ?? null,
    // Sorted and de-duplicated so two equal months lists compare equal, and a
    // seasonality chart does not depend on the order they were tapped in.
    salesMonthsJson: JSON.stringify([...new Set(payload.salesMonths ?? [])].sort((a, b) => a - b)),
    mainChallenge: payload.mainChallenge ?? null,
    supportNeeded: payload.supportNeeded ?? null,
    ...(payload.status ? { status: payload.status } : {})
  };
}

/**
 * Appends the per-visit snapshot.
 *
 * A snapshot needs an occasion. Without a visit there is nothing to compare
 * "between visits" against — and because SQLite treats NULLs as distinct, a
 * null `visitId` slips past the unique index and appends a fresh row on every
 * save rather than correcting the last one.
 */
async function writeSnapshot(
  tx: Parameters<Parameters<typeof prisma.$transaction>[0]>[0],
  enterpriseId: string,
  groupId: string,
  payload: EnterprisePayload
) {
  if (!payload.visitId) return;

  const snapshot = {
    name: payload.name,
    enterpriseType: payload.enterpriseType ?? null,
    description: payload.description ?? null,
    monthlyRevenueCents: payload.monthlyRevenueCents ?? null,
    monthlyCostsCents: payload.monthlyCostsCents ?? null,
    employsPeople: payload.employsPeople ?? null,
    marketReach: payload.marketReach ?? null,
    buyerCount: payload.buyerCount ?? null,
    marketChannelsJson: JSON.stringify(payload.marketChannels ?? []),
    hasFormalBuyerAgreement: payload.hasFormalBuyerAgreement ?? null,
    salesMonthsJson: JSON.stringify([...new Set(payload.salesMonths ?? [])].sort((a, b) => a - b)),
    mainChallenge: payload.mainChallenge ?? null,
    supportNeeded: payload.supportNeeded ?? null,
    status: payload.status ?? "ACTIVE"
  };

  // One snapshot per visit, corrected in place if the agent revises it during
  // the same visit — a resent document must not append a second reading and
  // make one visit look like two.
  await tx.groupEnterpriseVersion.upsert({
    where: { enterpriseId_visitId: { enterpriseId, visitId: payload.visitId } },
    create: { enterpriseId, groupId, visitId: payload.visitId, ...snapshot },
    update: snapshot
  });
}
