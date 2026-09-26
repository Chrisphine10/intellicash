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
import { repaymentRatePercent } from "../domain/loan-math";
import { ok } from "../lib/http";
import { loadLoanPositions } from "../services/loan-position-service";
import { activeCycleEntriesWhere } from "../services/cycle-service";
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
      prisma.group.findMany({ where: groupWhere, select: { id: true, phase: true } }),
      // Active members of the groups counted here - demo groups and people who
      // have left are not members of the portfolio.
      prisma.member.count({
        where: { AND: [memberScopeForUser(req.user), { status: "ACTIVE" }, { group: groupWhere }] }
      }),
      prisma.meeting.count({ where: { status: "IN_PROGRESS", group: groupWhere } }),
      prisma.fundAccount.findMany({
        where: { group: groupWhere },
        select: { type: true, balanceCents: true }
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
    // Savings = shares bought this cycle, signed by direction, by the
    // statement's rule (activeCycleEntriesWhere) so the dashboard and each
    // group's report add up to the same figure.
    const groupIds = groups.map((group) => group.id);
    const shareRows = await prisma.ledgerEntry.groupBy({
      by: ["direction"],
      where: { AND: [await activeCycleEntriesWhere(prisma, groupIds), { type: "SHARE_PURCHASE" }] },
      _sum: { amountCents: true }
    });
    const totalSavingsCents = shareRows.reduce(
      (sum, row) => sum + (row._sum.amountCents ?? 0) * (row.direction === "DEBIT" ? -1 : 1),
      0
    );
    const loanFundCents = fundAccounts
      .filter((a) => a.type === "INTERNAL_LOAN")
      .reduce((sum, account) => sum + account.balanceCents, 0);
    const totalSocialFundCents = fundAccounts
      .filter((a) => a.type === "SOCIAL")
      .reduce((sum, account) => sum + account.balanceCents, 0);
    const averageCreditScore =
      creditScores.length === 0
        ? 0
        : Math.round(
            creditScores.reduce((sum, score) => sum + score.score, 0) / creditScores.length
          );

    // Measured from the loans and what has been paid on them, through the same
    // balance maths as the passbook. This used to be the constant 91, shown to
    // partners as if it were a measurement.
    const now = new Date();
    const positions = await loadLoanPositions(prisma, { groupIds }, now);
    let loansOutstandingCents = 0;
    let par30Cents = 0;
    for (const position of positions.values()) {
      for (const entry of position.loans) {
        if (entry.settled) continue;
        loansOutstandingCents += entry.outstandingCents;
        if (now.getTime() - entry.loan.dueAt.getTime() > 30 * 24 * 60 * 60 * 1000) par30Cents += entry.outstandingCents;
      }
    }
    // The repayment rate over the same loans each group's statement counts:
    // lent this cycle, or still owed. Every loan ever made (what this was) let
    // a good year long ago hide a bad cycle now, and disagreed with the
    // financial reports.
    const cycles = await prisma.cycle.findMany({
      where: { groupId: { in: groupIds } },
      select: { id: true, groupId: true, status: true }
    });
    const activeCycleIds = new Set(cycles.filter((cycle) => cycle.status === "ACTIVE").map((cycle) => cycle.id));
    const groupsWithCycles = new Set(cycles.map((cycle) => cycle.groupId));
    const repaymentRate = repaymentRatePercent(
      [...positions.values()].flatMap((position) =>
        position.loans
          .filter(
            (entry) =>
              !entry.settled ||
              !groupsWithCycles.has(entry.loan.groupId) ||
              (entry.loan.cycleId !== null && activeCycleIds.has(entry.loan.cycleId))
          )
          .map((entry) => ({ ...entry, dueAt: entry.loan.dueAt }))
      ),
      now
    );

    const summary: PortfolioSummary = {
      groups: groups.length,
      members,
      activeMeetings,
      totalSavingsCents,
      loanFundCents,
      loansOutstandingCents,
      par30Rate: loansOutstandingCents > 0 ? Math.round((par30Cents / loansOutstandingCents) * 1000) / 10 : null,
      totalSocialFundCents,
      repaymentRate,
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
