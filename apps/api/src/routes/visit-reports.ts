import { Router } from "express";
import { requireAuth } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import { actionItemState, actionPlanSummary } from "../domain/action-plan";
import { documentStatus, registerSummary } from "../domain/group-document-state";
import { buildGroupMeal, buildMealReport } from "../services/meal-report";

export const visitReportsRouter = Router();

/**
 * What the visits programme is actually doing.
 *
 * Deliberately **no materialized trend table**. SQLite answers these directly
 * at this volume, and a denormalized copy is one more thing that can quietly
 * stop being true — the failure mode being a report that looks authoritative
 * and is stale.
 *
 * Also deliberately not a new top-level dashboard. Only four questions here are
 * genuinely new — coverage, band distribution, action ageing, document gaps —
 * and a second place to look is a second place for the numbers to disagree.
 */

const MS_PER_DAY = 24 * 60 * 60 * 1000;

/**
 * A group's assessment history, section by section.
 *
 * Sections join on their KEY, not their title or position, because that is the
 * only thing guaranteed stable across template versions. Where the version
 * changed between two visits the series carries a marker, so a step in the line
 * is not silently read as the group improving when the questions moved.
 */
visitReportsRouter.get(
  "/groups/:groupId/visit-trend",
  requireAuth("visits:read"),
  async (req, res, next) => {
    try {
      const group = await prisma.group.findFirst({
        where: { AND: [{ id: req.params.groupId as string }, scopeGroupWhere(req.user)] },
        select: { id: true, name: true, code: true }
      });
      if (!group) {
        throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
      }

      const assessments = await prisma.groupVisitAssessment.findMany({
        where: { visit: { groupId: group.id } },
        include: { visit: { select: { id: true, startedAt: true, visitType: true } } },
        orderBy: { createdAt: "asc" }
      });

      let previousVersion: number | null = null;
      const points = assessments.map((assessment) => {
        const breakdown = JSON.parse(assessment.breakdownJson) as {
          sections?: { sectionKey: string; percentage: number | null; earnedPoints: number }[];
        };
        const templateChanged =
          previousVersion !== null && previousVersion !== assessment.templateVersion;
        previousVersion = assessment.templateVersion;

        return {
          visitId: assessment.visitId,
          visitedAt: assessment.visit.startedAt,
          visitType: assessment.visit.visitType,
          percentage: assessment.percentage,
          bandLabel: assessment.bandLabel,
          templateVersion: assessment.templateVersion,
          /** The questions moved here — a step in the line may be the form, not the group. */
          templateChanged,
          sections: (breakdown.sections ?? []).map((section) => ({
            sectionKey: section.sectionKey,
            percentage: section.percentage,
            earnedPoints: section.earnedPoints
          }))
        };
      });

      // Per-section series, keyed so a section that only exists in some
      // versions still lines up on the visits where it was asked.
      const bySection = new Map<string, { visitedAt: Date | null; percentage: number | null }[]>();
      for (const point of points) {
        for (const section of point.sections) {
          const series = bySection.get(section.sectionKey) ?? [];
          series.push({ visitedAt: point.visitedAt, percentage: section.percentage });
          bySection.set(section.sectionKey, series);
        }
      }

      ok(res, {
        group,
        visits: points.length,
        overall: points.map((point) => ({
          visitedAt: point.visitedAt,
          percentage: point.percentage,
          bandLabel: point.bandLabel,
          templateVersion: point.templateVersion,
          templateChanged: point.templateChanged
        })),
        sections: [...bySection.entries()].map(([sectionKey, series]) => ({
          sectionKey,
          series,
          // First to last, so "is this getting better?" has a number rather
          // than a shape somebody has to eyeball.
          change:
            series.length >= 2 && series[0]!.percentage !== null && series.at(-1)!.percentage !== null
              ? Math.round((series.at(-1)!.percentage! - series[0]!.percentage!) * 100) / 100
              : null
        }))
      });
    } catch (error) {
      next(error);
    }
  }
);

/**
 * Programme-wide: who has been visited, how they scored, what is outstanding,
 * and which documents are missing.
 */
visitReportsRouter.get("/reports/visits", requireAuth("visits:read"), async (req, res, next) => {
  try {
    const scope = scopeGroupWhere(req.user);
    const groups = await prisma.group.findMany({
      where: scope,
      select: { id: true, name: true, code: true, county: true }
    });
    const groupIds = groups.map((group) => group.id);

    if (groupIds.length === 0) {
      ok(res, {
        coverage: { groups: 0, visited: 0, neverVisited: 0, percentVisited: 0 },
        bands: [],
        actions: actionPlanSummary([]),
        documents: { total: 0, verified: 0, missing: 0, expired: 0, needsAttention: 0, percentVerified: 0 },
        neverVisited: [],
        staleGroups: []
      });
      return;
    }

    const [visits, assessments, actionItems, documents] = await Promise.all([
      prisma.groupVisit.findMany({
        where: { groupId: { in: groupIds } },
        select: { groupId: true, startedAt: true }
      }),
      prisma.groupVisitAssessment.findMany({
        where: { visit: { groupId: { in: groupIds } } },
        select: { bandLabel: true, percentage: true, visit: { select: { groupId: true } } }
      }),
      prisma.visitActionItem.findMany({
        where: { groupId: { in: groupIds } },
        select: { status: true, dueDate: true }
      }),
      prisma.groupDocument.findMany({
        where: { groupId: { in: groupIds } },
        select: { presence: true, verification: true, expiresOn: true }
      })
    ]);

    const lastVisitByGroup = new Map<string, Date>();
    for (const visit of visits) {
      if (!visit.startedAt) continue;
      const current = lastVisitByGroup.get(visit.groupId);
      if (!current || visit.startedAt > current) lastVisitByGroup.set(visit.groupId, visit.startedAt);
    }

    const now = new Date();
    // A group visited once a year ago is not "covered" in any useful sense, so
    // coverage and staleness are reported separately rather than as one number.
    const staleGroups = groups
      .map((group) => {
        const last = lastVisitByGroup.get(group.id) ?? null;
        return {
          ...group,
          lastVisitAt: last,
          daysSinceVisit: last === null ? null : Math.floor((now.getTime() - last.getTime()) / MS_PER_DAY)
        };
      })
      .filter((group) => group.daysSinceVisit !== null && group.daysSinceVisit > 90)
      .sort((a, b) => (b.daysSinceVisit ?? 0) - (a.daysSinceVisit ?? 0));

    const bandCounts = new Map<string, number>();
    for (const assessment of assessments) {
      const label = assessment.bandLabel ?? "Not scored";
      bandCounts.set(label, (bandCounts.get(label) ?? 0) + 1);
    }

    const documentStatuses = documents.map((document) =>
      documentStatus({
        presence: document.presence,
        verification: document.verification,
        expiresOn: document.expiresOn
      })
    );

    ok(res, {
      coverage: {
        groups: groups.length,
        visited: lastVisitByGroup.size,
        neverVisited: groups.length - lastVisitByGroup.size,
        percentVisited: Math.round((lastVisitByGroup.size / groups.length) * 100)
      },
      bands: [...bandCounts.entries()].map(([band, count]) => ({ band, count })),
      actions: actionPlanSummary(
        actionItems.map((item) => actionItemState({ status: item.status, dueDate: item.dueDate }, now))
      ),
      documents: registerSummary(documentStatuses),
      neverVisited: groups
        .filter((group) => !lastVisitByGroup.has(group.id))
        .map((group) => ({ id: group.id, name: group.name, code: group.code })),
      staleGroups: staleGroups.slice(0, 20)
    });
  } catch (error) {
    next(error);
  }
});


// ---------------------------------------------------------------------------
// MEAL
// ---------------------------------------------------------------------------

/**
 * Monitoring, evaluation, accountability and learning, over the caller's scope.
 *
 * Answers the question the coverage report above cannot: not "how much did we
 * do", but "did any of it change anything". The arithmetic lives in
 * `domain/meal-indicators.ts` and the gathering in `services/meal-report.ts`;
 * this handler only resolves scope.
 *
 * The response carries its own methodology — every indicator's definition,
 * denominator and direction — because a figure that travels into a funder
 * report should carry its limits with it rather than depend on whoever pastes
 * it remembering them.
 */
visitReportsRouter.get("/reports/meal", requireAuth("visits:read"), async (req, res, next) => {
  try {
    const groups = await prisma.group.findMany({
      where: scopeGroupWhere(req.user),
      select: { id: true }
    });

    ok(res, await buildMealReport(groups.map((group) => group.id)));
  } catch (error) {
    next(error);
  }
});

/** One group's own baseline and latest, for the group page. */
visitReportsRouter.get(
  "/groups/:groupId/meal",
  requireAuth("visits:read"),
  async (req, res, next) => {
    try {
      const group = await prisma.group.findFirst({
        where: { AND: [{ id: req.params.groupId as string }, scopeGroupWhere(req.user)] },
        select: { id: true, name: true, code: true }
      });
      if (!group) {
        throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
      }

      ok(res, { group, ...(await buildGroupMeal(group.id)) });
    } catch (error) {
      next(error);
    }
  }
);
