import { prisma } from "../lib/prisma";
import {
  MEAL_CONTRACT_VERSION,
  MEAL_INDICATORS,
  OBSERVED_CHANGE_CAVEAT,
  type IndicatorDefinition,
  type PairedChange,
  type PairedUnit,
  type Share,
  indicatorCatalogue,
  marketReachStep,
  median,
  movementOf,
  pairedChange,
  round,
  share
} from "../domain/meal-indicators";

/**
 * Builds the MEAL picture for a set of groups.
 *
 * All the arithmetic lives in `domain/meal-indicators.ts`; this file only
 * gathers the readings and hands them over. The split matters because it is
 * what keeps the rules — paired comparison, denominators, comparability — in
 * one tested place instead of re-implemented per query, which is how two
 * reports on the same programme end up disagreeing.
 *
 * Scope is always a caller-supplied list of group ids, resolved by the route
 * through `scopeGroupWhere`. Nothing here decides who may see what.
 */

/** How recent an assessment must be to count as current. */
export const ASSESSMENT_FRESHNESS_DAYS = 180;

const DAY_MS = 24 * 60 * 60 * 1000;

export interface MealIndicatorResult {
  definition: IndicatorDefinition;
  change?: PairedChange;
  share?: Share;
  value?: number | null;
  movement: ReturnType<typeof movementOf>;
}

/**
 * Assessment readings per group, first against latest.
 *
 * Comparability is the interesting part. A group assessed on scorecard v1 and
 * then on v2 has two numbers that are not the same measurement: a re-worded or
 * re-weighted question moves the score without anything changing in the group.
 * Those groups are marked `comparable: false` and the contract excludes and
 * counts them rather than averaging them in.
 */
async function assessmentSeries(groupIds: string[]): Promise<{
  units: PairedUnit[];
  perGroup: Array<{
    groupId: string;
    first: { percentage: number; version: number; at: Date } | null;
    latest: { percentage: number; version: number; at: Date } | null;
    readings: number;
    comparable: boolean;
  }>;
  freshGroupIds: Set<string>;
}> {
  const assessments = await prisma.groupVisitAssessment.findMany({
    where: { visit: { groupId: { in: groupIds } } },
    select: {
      percentage: true,
      templateVersion: true,
      templateId: true,
      createdAt: true,
      visit: { select: { groupId: true } }
    },
    orderBy: { createdAt: "asc" }
  });

  const byGroup = new Map<string, typeof assessments>();
  for (const assessment of assessments) {
    const groupId = assessment.visit.groupId;
    byGroup.set(groupId, [...(byGroup.get(groupId) ?? []), assessment]);
  }

  const freshThreshold = new Date(Date.now() - ASSESSMENT_FRESHNESS_DAYS * DAY_MS);
  const freshGroupIds = new Set<string>();
  const units: PairedUnit[] = [];
  const perGroup: Awaited<ReturnType<typeof assessmentSeries>>["perGroup"] = [];

  for (const groupId of groupIds) {
    const rows = byGroup.get(groupId) ?? [];
    const first = rows[0];
    const latest = rows.at(-1);

    if (latest && latest.createdAt >= freshThreshold) freshGroupIds.add(groupId);

    // The same reading twice is not two readings.
    const hasPair = rows.length >= 2 && first !== undefined && latest !== undefined;
    const comparable =
      hasPair && first.templateId === latest.templateId && first.templateVersion === latest.templateVersion;

    units.push({
      unitId: groupId,
      first: hasPair ? first.percentage : null,
      last: latest ? latest.percentage : null,
      comparable: hasPair ? comparable : true
    });

    perGroup.push({
      groupId,
      first: first ? { percentage: first.percentage, version: first.templateVersion, at: first.createdAt } : null,
      latest: latest
        ? { percentage: latest.percentage, version: latest.templateVersion, at: latest.createdAt }
        : null,
      readings: rows.length,
      comparable: hasPair ? comparable : true
    });
  }

  return { units, perGroup, freshGroupIds };
}

interface EnterpriseReading {
  at: Date;
  revenue: number | null;
  costs: number | null;
  reachStep: number | null;
  buyers: number | null;
}

/**
 * Enterprise readings, drawn from the per-visit snapshots plus the current row.
 *
 * The snapshots are the dated history. The current row is included as the most
 * recent reading because an enterprise edited outside a visit has no snapshot
 * for that edit — excluding it would report a business as unmeasured while its
 * figures sit on screen. Where the newest snapshot and the current row agree,
 * including both changes nothing: first and last are taken by date.
 */
async function enterpriseSeries(groupIds: string[]) {
  const enterprises = await prisma.groupEnterprise.findMany({
    where: { groupId: { in: groupIds } },
    include: { versions: { orderBy: { recordedAt: "asc" } } }
  });

  const revenue: PairedUnit[] = [];
  const margin: PairedUnit[] = [];
  const reach: PairedUnit[] = [];
  const buyers: PairedUnit[] = [];

  let askedAboutAgreement = 0;
  let withAgreement = 0;

  for (const enterprise of enterprises) {
    const readings: EnterpriseReading[] = enterprise.versions.map((version) => ({
      at: version.recordedAt,
      revenue: version.monthlyRevenueCents,
      costs: version.monthlyCostsCents,
      reachStep: marketReachStep(version.marketReach),
      buyers: version.buyerCount
    }));
    readings.push({
      at: enterprise.updatedAt,
      revenue: enterprise.monthlyRevenueCents,
      costs: enterprise.monthlyCostsCents,
      reachStep: marketReachStep(enterprise.marketReach),
      buyers: enterprise.buyerCount
    });
    readings.sort((a, b) => a.at.getTime() - b.at.getTime());

    const pick = (read: (reading: EnterpriseReading) => number | null) => {
      // Only readings where this particular figure was actually filled in. An
      // enterprise that reported revenue but never costs must not contribute a
      // margin of "revenue minus nothing".
      const answered = readings.filter((reading) => read(reading) !== null);
      const first = answered[0];
      const last = answered.at(-1);
      return {
        unitId: enterprise.id,
        first: answered.length >= 2 && first ? read(first) : null,
        last: last ? read(last) : null
      };
    };

    revenue.push(pick((reading) => reading.revenue));
    margin.push(
      pick((reading) => (reading.revenue === null || reading.costs === null ? null : reading.revenue - reading.costs))
    );
    reach.push(pick((reading) => reading.reachStep));
    buyers.push(pick((reading) => reading.buyers));

    // Null means not asked, which is not the same as no agreement — so the
    // denominator is enterprises asked, not enterprises.
    if (enterprise.hasFormalBuyerAgreement !== null) {
      askedAboutAgreement += 1;
      if (enterprise.hasFormalBuyerAgreement) withAgreement += 1;
    }
  }

  return {
    count: enterprises.length,
    revenue,
    margin,
    reach,
    buyers,
    askedAboutAgreement,
    withAgreement
  };
}

async function supportNeedSummary(groupIds: string[]) {
  const needs = await prisma.groupEnterpriseSupportNeed.findMany({
    where: { groupId: { in: groupIds } },
    select: {
      needKeySnapshot: true,
      needTitleSnapshot: true,
      needCategorySnapshot: true,
      priority: true,
      status: true,
      raisedAt: true,
      metAt: true
    }
  });

  const byNeed = new Map<string, { key: string; title: string; category: string; raised: number; met: number; high: number }>();
  const byCategory = new Map<string, { raised: number; met: number }>();
  const daysToMeet: number[] = [];

  for (const need of needs) {
    const row = byNeed.get(need.needKeySnapshot) ?? {
      key: need.needKeySnapshot,
      title: need.needTitleSnapshot,
      category: need.needCategorySnapshot,
      raised: 0,
      met: 0,
      high: 0
    };
    row.raised += 1;
    if (need.status === "MET") row.met += 1;
    if (need.priority === "HIGH") row.high += 1;
    byNeed.set(need.needKeySnapshot, row);

    const category = byCategory.get(need.needCategorySnapshot) ?? { raised: 0, met: 0 };
    category.raised += 1;
    if (need.status === "MET") category.met += 1;
    byCategory.set(need.needCategorySnapshot, category);

    if (need.status === "MET" && need.metAt) {
      daysToMeet.push((need.metAt.getTime() - need.raisedAt.getTime()) / DAY_MS);
    }
  }

  return {
    total: needs.length,
    met: needs.filter((need) => need.status === "MET").length,
    open: needs.filter((need) => need.status === "OPEN").length,
    medianDaysToMeet: round(median(daysToMeet), 0),
    // Ranked by how often it is asked for, which is the order a programme
    // manager deciding where to put money actually needs.
    ranked: [...byNeed.values()].sort((a, b) => b.raised - a.raised || a.title.localeCompare(b.title)),
    byCategory: [...byCategory.entries()]
      .map(([category, counts]) => ({ category, ...counts }))
      .sort((a, b) => b.raised - a.raised)
  };
}

async function mentorshipSummary(groupIds: string[]) {
  const [sessions, ratings] = await Promise.all([
    prisma.visitMentorshipSession.findMany({
      where: { visit: { groupId: { in: groupIds } } },
      select: { topicKeySnapshot: true, topicTitleSnapshot: true, durationMinutes: true }
    }),
    prisma.visitMentorshipRating.findMany({
      where: { visit: { groupId: { in: groupIds } } },
      select: { dimensionKeySnapshot: true, score: true, ratedByRole: true }
    })
  ]);

  const byTopic = new Map<string, { key: string; title: string; sessions: number; minutes: number }>();
  for (const session of sessions) {
    const row = byTopic.get(session.topicKeySnapshot) ?? {
      key: session.topicKeySnapshot,
      title: session.topicTitleSnapshot,
      sessions: 0,
      minutes: 0
    };
    row.sessions += 1;
    row.minutes += session.durationMinutes ?? 0;
    byTopic.set(session.topicKeySnapshot, row);
  }

  // Only the group's own ratings inform the score. An agent rating their own
  // coaching scores 4 or 5 every time and the aggregate says nothing.
  const fromGroup = ratings.filter((rating) => rating.ratedByRole === "GROUP_REPRESENTATIVE");

  const byDimension = new Map<string, number[]>();
  for (const rating of fromGroup) {
    byDimension.set(rating.dimensionKeySnapshot, [
      ...(byDimension.get(rating.dimensionKeySnapshot) ?? []),
      rating.score
    ]);
  }

  return {
    sessions: sessions.length,
    topics: [...byTopic.values()].sort((a, b) => b.sessions - a.sessions),
    ratingsTotal: ratings.length,
    ratingsFromGroup: fromGroup.length,
    averageFromGroup: round(
      fromGroup.length === 0 ? null : fromGroup.reduce((sum, r) => sum + r.score, 0) / fromGroup.length,
      2
    ),
    byDimension: [...byDimension.entries()]
      .map(([key, scores]) => ({
        key,
        responses: scores.length,
        average: round(scores.reduce((sum, score) => sum + score, 0) / scores.length, 2)
      }))
      .sort((a, b) => b.responses - a.responses)
  };
}

function result(
  indicatorKey: string,
  parts: { change?: PairedChange; share?: Share; value?: number | null }
): MealIndicatorResult {
  const definition = (MEAL_INDICATORS as Record<string, IndicatorDefinition>)[indicatorKey] as IndicatorDefinition;
  return {
    definition,
    ...parts,
    movement: movementOf(indicatorKey, parts.change?.change ?? null)
  };
}

/**
 * The whole picture for a scope of groups.
 *
 * Returns indicators grouped by results-chain level, so the reader moves from
 * what the programme did, to what that produced, to what changed — and lands on
 * how far any of it can be trusted. Presenting outcomes without the data-quality
 * block is how a rating average made entirely of agents scoring themselves gets
 * read as satisfaction.
 */
export async function buildMealReport(groupIds: string[]) {
  const [assessments, enterprises, needs, mentorship, visits, actions] = await Promise.all([
    assessmentSeries(groupIds),
    enterpriseSeries(groupIds),
    supportNeedSummary(groupIds),
    mentorshipSummary(groupIds),
    prisma.groupVisit.count({ where: { groupId: { in: groupIds } } }),
    prisma.visitActionItem.findMany({
      where: { groupId: { in: groupIds } },
      select: { status: true }
    })
  ]);

  const groupCount = groupIds.length;
  const assessedGroups = assessments.perGroup.filter((row) => row.readings > 0).length;

  const indicators: MealIndicatorResult[] = [
    result("visits.completed", { value: visits }),
    result("mentorship.sessions", { value: mentorship.sessions }),

    result("groups.assessed", { share: share("groups.assessed", assessedGroups, groupCount) }),
    result("enterprises.profiled", { value: enterprises.count }),
    result("needs.raised", { value: needs.total }),

    result("assessment.score", {
      change: pairedChange("assessment.score", assessments.units, {
        aggregate: "MEAN",
        eligibleUnits: groupCount
      })
    }),
    result("enterprise.revenue", {
      change: pairedChange("enterprise.revenue", enterprises.revenue, {
        eligibleUnits: enterprises.count
      })
    }),
    result("enterprise.margin", {
      change: pairedChange("enterprise.margin", enterprises.margin, {
        eligibleUnits: enterprises.count
      })
    }),
    result("enterprise.marketReach", {
      change: pairedChange("enterprise.marketReach", enterprises.reach, {
        eligibleUnits: enterprises.count
      })
    }),
    result("enterprise.buyers", {
      change: pairedChange("enterprise.buyers", enterprises.buyers, {
        eligibleUnits: enterprises.count
      })
    }),
    result("enterprise.formalAgreement", {
      share: share("enterprise.formalAgreement", enterprises.withAgreement, enterprises.askedAboutAgreement)
    }),
    result("needs.met", { share: share("needs.met", needs.met, needs.total) }),
    result("needs.daysToMeet", { value: needs.medianDaysToMeet }),
    result("actions.closed", {
      share: share(
        "actions.closed",
        actions.filter((item) => item.status === "DONE" || item.status === "CLOSED").length,
        actions.length
      )
    }),
    result("mentorship.rating", { value: mentorship.averageFromGroup }),

    result("data.assessmentCoverage", {
      share: share("data.assessmentCoverage", assessments.freshGroupIds.size, groupCount)
    }),
    result("data.ratingProvenance", {
      share: share("data.ratingProvenance", mentorship.ratingsFromGroup, mentorship.ratingsTotal)
    })
  ];

  const scoreChange = indicators.find((row) => row.definition.key === "assessment.score")?.change;

  return {
    contractVersion: MEAL_CONTRACT_VERSION,
    caveat: OBSERVED_CHANGE_CAVEAT,
    generatedAt: new Date().toISOString(),
    scope: { groups: groupCount, freshnessDays: ASSESSMENT_FRESHNESS_DAYS },
    indicators,
    /** Direction of travel, which an average can hide entirely. */
    assessmentMovement: {
      improved: scoreChange?.improved ?? 0,
      unchanged: scoreChange?.unchanged ?? 0,
      declined: scoreChange?.declined ?? 0,
      notComparable: scoreChange?.excludedForComparability ?? 0,
      noBaseline: assessments.perGroup.filter((row) => row.readings === 1).length,
      neverAssessed: assessments.perGroup.filter((row) => row.readings === 0).length
    },
    supportNeeds: needs,
    mentorship,
    /** Definitions travel with the figures, so nothing is read out of context. */
    methodology: indicatorCatalogue()
  };
}

/** One group's own baselines, for the group page. */
export async function buildGroupMeal(groupId: string) {
  const report = await buildMealReport([groupId]);
  const { perGroup } = await assessmentSeries([groupId]);

  return { ...report, assessment: perGroup[0] ?? null };
}
