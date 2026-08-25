import { Router } from "express";
import { groupPhases, type GroupPhase, type PortfolioSummary } from "@intellicash/shared";
import { getIntegrationHealth } from "../domain/integrations";
import { getStoredCredentialContext } from "../services/integration-credentials";
import { requireAuth } from "../middleware/auth";
import {
  demoExclusionForUser,
  memberScopeForUser,
  scopeGroupWhere
} from "../services/account-scope";
import { ok } from "../lib/http";
import { prisma } from "../lib/prisma";

const router = Router();

router.get("/analytics/portfolio", requireAuth("analytics:read"), async (req, res, next) => {
  try {
    // Demo groups are excluded from every total here. A portfolio that counts
    // made-up savings next to real ones is worse than no portfolio: nobody
    // looking at it can tell which is which. A demo account still sees its own.
    const groupWhere = {
      AND: [scopeGroupWhere(req.user), await demoExclusionForUser(req.user)]
    };
    const [groups, members, activeMeetings, fundAccounts, creditScores, credentialContext] = await Promise.all([
      prisma.group.findMany({ where: groupWhere, select: { phase: true } }),
      prisma.member.count({ where: memberScopeForUser(req.user) }),
      prisma.meeting.count({ where: { status: "IN_PROGRESS", group: groupWhere } }),
      prisma.fundAccount.findMany({
        where: { type: { in: ["INTERNAL_LOAN", "SOCIAL"] }, group: groupWhere },
        select: { balanceCents: true }
      }),
      prisma.creditScore.findMany({
        where: { group: groupWhere },
        orderBy: { computedAt: "desc" },
        distinct: ["groupId"],
        select: { score: true }
      }),
      getStoredCredentialContext()
    ]);

    const phaseDistribution = groupPhases.reduce(
      (accumulator, phase) => ({
        ...accumulator,
        [phase]: groups.filter((group) => group.phase === phase).length
      }),
      {} as Record<GroupPhase, number>
    );

    const integrationHealth = getIntegrationHealth(
      credentialContext.credentialsByProvider,
      credentialContext.metaByProvider
    );
    const totalSavingsCents = fundAccounts.reduce(
      (sum, account) => sum + account.balanceCents,
      0
    );
    const averageCreditScore =
      creditScores.length === 0
        ? 0
        : Math.round(
            creditScores.reduce((sum, score) => sum + score.score, 0) / creditScores.length
          );

    const summary: PortfolioSummary = {
      groups: groups.length,
      members,
      activeMeetings,
      totalSavingsCents,
      repaymentRate: 91,
      averageCreditScore,
      phaseDistribution,
      integrationConfigured: integrationHealth.configured,
      integrationTotal: integrationHealth.total
    };

    ok(res, summary);
  } catch (error) {
    next(error);
  }
});

export { router as analyticsRouter };
