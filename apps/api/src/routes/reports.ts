import { Router } from "express";
import type { Prisma } from "@prisma/client";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import {
  ledgerScopeForUser,
  memberScopeForUser,
  scopeGroupWhere,
  villageAgentScopeForUser,
  demoExclusionForUser
} from "../services/account-scope";
import { ApiHttpError, ok } from "../lib/http";
import { buildProgrammePerformanceReport } from "../services/programme-performance-report";
import { latestCreditRating } from "../services/credit-rating-service";
import { buildMemberPassbook } from "../services/member-passbook-service";
import {
  GROUP_LEVEL_ONLY_ROLES,
  SMALL_GROUP_THRESHOLD,
  buildGroupStatement,
  redactStatementForRole,
  type GroupStatement
} from "../services/vsla-statement-service";
import { prisma } from "../lib/prisma";

const router = Router();

function hasPermission(user: AuthenticatedUser | undefined, permission: string) {
  return Boolean(user && (user.permissions as readonly string[]).includes(permission));
}

function canReadImportedKpis(user: AuthenticatedUser | undefined) {
  if (!user || !hasPermission(user, "programmes:read")) return false;
  return ["IWL_ADMIN", "READ_ONLY", "PARTNER_OFFICER", "LENDER"].includes(user.role);
}

function reportUserWhere(user?: AuthenticatedUser): Prisma.UserWhereInput {
  if (!user) return { id: "__no_access__" };

  if (["IWL_ADMIN", "READ_ONLY"].includes(user.role)) return {};

  if (user.partnerId) return { partnerId: user.partnerId };
  if (user.groupId) return { groupId: user.groupId };
  return { id: user.id };
}

function reportAccountScope(user?: AuthenticatedUser) {
  if (!user) {
    return {
      userId: null,
      name: "Unauthenticated",
      email: null,
      role: null,
      scopeType: "NONE",
      scopeId: null,
      scopeName: "No account scope",
      permissions: []
    };
  }

  if (user.member) {
    return {
      userId: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
      scopeType: "MEMBER",
      scopeId: user.member.id,
      scopeName: `${user.member.fullName}${user.group ? ` in ${user.group.name}` : ""}`,
      permissions: user.permissions
    };
  }

  if (user.group) {
    return {
      userId: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
      scopeType: "GROUP",
      scopeId: user.group.id,
      scopeName: `${user.group.name} (${user.group.code})`,
      permissions: user.permissions
    };
  }

  if (user.partner) {
    return {
      userId: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
      scopeType: user.role === "LENDER" ? "LENDER" : "PARTNER",
      scopeId: user.partner.id,
      scopeName: user.partner.name,
      permissions: user.permissions
    };
  }

  return {
    userId: user.id,
    name: user.name,
    email: user.email,
    role: user.role,
    scopeType: "PLATFORM",
    scopeId: null,
    scopeName: "Platform portfolio",
    permissions: user.permissions
  };
}

/**
 * The programme performance pack — see `services/programme-performance-report`
 * for what it contains and how it keeps members unidentifiable.
 *
 * Period defaults to the last 90 days, the usual quarterly reporting window.
 */
const PROGRAMME_REPORT_ROLES = ["IWL_ADMIN", "PARTNER_OFFICER", "LENDER", "READ_ONLY", "VILLAGE_AGENT"];

router.get("/reports/programme-performance", requireAuth("analytics:read"), async (req, res, next) => {
  try {
    if (!req.user || !PROGRAMME_REPORT_ROLES.includes(req.user.role)) {
      throw new ApiHttpError(
        403,
        "FORBIDDEN",
        "This report is for programme staff and partners. Your group's own reports are under My Group."
      );
    }

    const parseDate = (value: unknown) => {
      if (typeof value !== "string" || !value) return null;
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? undefined : date;
    };
    const to = parseDate(req.query.to);
    const from = parseDate(req.query.from);
    if (to === undefined || from === undefined) {
      throw new ApiHttpError(400, "INVALID_PERIOD", "The report period dates are not valid. Use the date pickers.");
    }
    const periodTo = to ?? new Date();
    // End of the chosen day, so "to 30 Sep" includes the 30th.
    if (to) periodTo.setUTCHours(23, 59, 59, 999);
    const periodFrom = from ?? new Date(periodTo.getTime() - 90 * 24 * 60 * 60 * 1000);
    if (periodFrom > periodTo) {
      throw new ApiHttpError(400, "INVALID_PERIOD", "The start of the period is after its end.");
    }

    const groups = await prisma.group.findMany({
      where: { AND: [scopeGroupWhere(req.user), await demoExclusionForUser(req.user)] },
      select: { id: true }
    });

    ok(
      res,
      await buildProgrammePerformanceReport(
        groups.map((group) => group.id),
        { from: periodFrom, to: periodTo }
      )
    );
  } catch (error) {
    next(error);
  }
});

/**
 * Roles that read programme results but do not serve members directly. They get
 * ledger totals, never who paid what: a partner or lender judging a programme
 * has no need for a named member's individual savings, and the Data Protection
 * Act's minimisation principle says they should not receive it. Field agents
 * and the group itself keep names — they work with those members.
 */
const MEMBER_IDENTITY_WITHHELD_ROLES = ["PARTNER_OFFICER", "LENDER", "READ_ONLY"];

router.get("/reports/foundation", requireAuth("analytics:read"), async (req, res, next) => {
  try {
    // Same rule as the portfolio: this is the report a funder reads, so demo
    // figures must not be inside it.
    const groupWhere = {
      AND: [scopeGroupWhere(req.user), await demoExclusionForUser(req.user)]
    };
    const accessibleGroups = await prisma.group.findMany({
      where: groupWhere,
      select: { county: true }
    });
    const scopedCounties = Array.from(new Set(accessibleGroups.map((group) => group.county)));
    const countyWhere = Object.keys(groupWhere).length > 0 ? { county: { in: scopedCounties } } : {};
    const userWhere = reportUserWhere(req.user);
    const canReadLedger = hasPermission(req.user, "ledger:read");
    const canReadUsers = hasPermission(req.user, "users:read");
    const canReadMeetings = hasPermission(req.user, "meetings:read");
    const canReadVotes = hasPermission(req.user, "votes:read");
    const canReadKpis = canReadImportedKpis(req.user);
    const activeMemberCounts = await prisma.member.groupBy({
      by: ["groupId"],
      where: { status: "ACTIVE", group: groupWhere },
      _count: true
    });
    const activeMembersByGroup = new Map(activeMemberCounts.map((row) => [row.groupId, row._count]));
    const [
      fundAccounts,
      ledgerEntries,
      users,
      meetings,
      votes,
      ftmaCountyVslaKpis,
      ftmaCountyVslaTrainingMetrics,
      ftmaCountyFscKpis
    ] = await Promise.all([
      canReadLedger
        ? prisma.fundAccount.findMany({
            where: { group: groupWhere },
            orderBy: [{ type: "asc" }, { balanceCents: "desc" }],
            include: {
              group: {
                select: {
                  id: true,
                  name: true,
                  code: true,
                  county: true,
                  phase: true,
                  sourceSystem: true,
                  programme: { select: { name: true } },
                  villageAgent: { select: { name: true } },
                  _count: { select: { members: true, meetings: true, votes: true } }
                }
              }
            }
          })
        : Promise.resolve([]),
      canReadLedger
        ? prisma.ledgerEntry.findMany({
            // Demo rows are kept out like every other part of this report, and
            // the list is capped: it is a register to scan, not an export.
            where: { AND: [ledgerScopeForUser(req.user), { group: await demoExclusionForUser(req.user) }] },
            orderBy: { createdAt: "desc" },
            take: 500,
            include: {
              group: { select: { id: true, name: true, code: true, county: true, sourceSystem: true } },
              member: { select: { fullName: true } },
              fundAccount: { select: { type: true, currency: true } },
              meeting: { select: { title: true, status: true } }
            }
          })
        : Promise.resolve([]),
      canReadUsers
        ? prisma.user.findMany({
            where: userWhere,
            orderBy: { createdAt: "desc" },
            select: {
              id: true,
              name: true,
              email: true,
              role: true,
              status: true,
              createdAt: true,
              partner: { select: { name: true } },
              group: { select: { id: true, name: true, code: true } },
              member: { select: { id: true, fullName: true } },
              sessions: { select: { expiresAt: true, lastUsedAt: true } },
              apiKeys: { select: { revokedAt: true, lastUsedAt: true } }
            }
          })
        : Promise.resolve([]),
      canReadMeetings
        ? prisma.meeting.findMany({
            where: { group: groupWhere },
            orderBy: { scheduledAt: "desc" },
            include: {
              group: { select: { id: true, name: true, code: true, county: true, phase: true, sourceSystem: true } },
              _count: { select: { attendance: true, ledgerEntries: true, votes: true } }
            }
          })
        : Promise.resolve([]),
      canReadVotes
        ? prisma.vote.findMany({
            where: { group: groupWhere },
            orderBy: { createdAt: "desc" },
            include: {
              group: { select: { id: true, name: true, code: true, county: true, phase: true, sourceSystem: true } }
            }
          })
        : Promise.resolve([]),
      canReadKpis
        ? prisma.ftmaCountyVslaKpi.findMany({ where: countyWhere, orderBy: { county: "asc" } })
        : Promise.resolve([]),
      canReadKpis
        ? prisma.ftmaCountyVslaTrainingMetric.findMany({ where: countyWhere, orderBy: { county: "asc" } })
        : Promise.resolve([]),
      canReadKpis
        ? prisma.ftmaCountyFscKpi.findMany({ where: countyWhere, orderBy: { county: "asc" } })
        : Promise.resolve([])
    ]);

    ok(res, {
      account: reportAccountScope(req.user),
      visibility: {
        fundAccounts: canReadLedger,
        ledgerEntries: canReadLedger,
        users: canReadUsers,
        meetings: canReadMeetings,
        votes: canReadVotes,
        importedKpis: canReadKpis
      },
      // A small group's balances describe individuals, so partners, lenders and
      // read-only viewers do not get them (same threshold as every report).
      fundAccounts: MEMBER_IDENTITY_WITHHELD_ROLES.includes(req.user?.role ?? "")
        ? fundAccounts.filter((account) => (activeMembersByGroup.get(account.group.id) ?? 0) >= SMALL_GROUP_THRESHOLD)
        : fundAccounts,
      // Free text goes too: a description or a motion can name a person.
      ledgerEntries: MEMBER_IDENTITY_WITHHELD_ROLES.includes(req.user?.role ?? "")
        ? ledgerEntries.map((entry) => ({ ...entry, memberId: null, member: null, description: "", externalReference: null }))
        : ledgerEntries,
      users,
      meetings,
      votes: MEMBER_IDENTITY_WITHHELD_ROLES.includes(req.user?.role ?? "")
        ? votes.map((vote) => ({ ...vote, motion: "" }))
        : votes,
      ftmaCountyVslaKpis: ftmaCountyVslaKpis.map((row) => ({
        ...row,
        savingsCents: Number(row.savingsCents),
        outstandingLoanCents: Number(row.outstandingLoanCents),
        socialFundCents: Number(row.socialFundCents)
      })),
      ftmaCountyVslaTrainingMetrics,
      ftmaCountyFscKpis
    });
  } catch (error) {
    next(error);
  }
});

// ---------------------------------------------------------------------------
// Per-role comprehensive reports. The same endpoints serve the mobile app and
// the admin web portal: an IWL admin (or read-only auditor) can pull any
// group/member/agent report, while group accounts, members and agents are
// automatically limited to their own scope by the account-scope helpers.
// ---------------------------------------------------------------------------

/**
 * The Group Financial Statement for one cycle (the active one unless
 * ?cycleId= names another). Every figure comes from buildGroupStatement, so it
 * matches the portfolio, the passbook and the phone.
 *
 * The older fields (group, funds, ledger, members, meetings) are kept for the
 * phones already in the field, now worked out the same way: this cycle only,
 * signed by direction. Partners, lenders and read-only viewers get no member
 * rows, and no money for a group under the small-group threshold.
 */
router.get("/reports/group/:id", requireAuth("groups:read"), async (req, res, next) => {
  try {
    const groupId = String(req.params.id);
    const group = await prisma.group.findFirst({
      where: scopeGroupWhere(req.user, { id: groupId }),
      select: { id: true, name: true, code: true, county: true, phase: true, cycleNumber: true }
    });
    if (!group) {
      ok(res.status(404), null);
      return;
    }
    const cycleId = typeof req.query.cycleId === "string" ? req.query.cycleId : undefined;
    const full = await buildGroupStatement(groupId, { cycleId });
    if (!full) {
      ok(res.status(404), null);
      return;
    }
    const statement = redactStatementForRole(full, req.user?.role);

    const [rating, externalLoans, storeRequests] = await Promise.all([
      latestCreditRating(groupId),
      prisma.externalLoanApplication.groupBy({
        by: ["status"],
        where: { groupId },
        _count: true,
        _sum: { amountCents: true }
      }),
      prisma.storeCreditRequest.groupBy({
        by: ["status"],
        where: { groupId },
        _count: true,
        _sum: { requestedAmountCents: true }
      })
    ]);

    ok(res, {
      generatedAt: full.generatedAt,
      statement,
      group: {
        id: group.id,
        name: group.name,
        code: group.code,
        county: group.county,
        phase: group.phase,
        cycleNumber: full.cycle.number,
        memberCount: full.members.active,
        /** Meetings HELD this cycle - never cancelled ones or reminder plans. */
        meetingCount: full.meetings.held
      },
      funds: statement.suppressed
        ? []
        : [
            { fundType: "INTERNAL_LOAN", balanceCents: full.loanFund.closingCents },
            { fundType: "SOCIAL", balanceCents: full.socialFund.closingCents }
          ],
      // Signed per type and direction, this cycle. Kept for older phones.
      ledger: statement.suppressed
        ? []
        : full.ledger.map((row) => ({
            type: row.type,
            direction: row.netCents < 0 ? "DEBIT" : "CREDIT",
            totalCents: Math.abs(row.netCents),
            entries: row.entries
          })),
      members: statement.memberRows.map((row) => ({
        id: row.memberId,
        fullName: row.fullName,
        role: row.role,
        status: row.status,
        sharesCents: row.sharesCents,
        socialCents: row.socialCents,
        finesCents: row.finesCents,
        loanRepaymentsCents: row.loanRepaidCents,
        loanDisbursementsCents: row.loanDisbursedCents,
        loanOutstandingCents: row.loanOutstandingCents
      })),
      meetings: {
        held: full.meetings.held,
        cancelled: full.meetings.cancelled,
        attendanceRate: full.meetings.attendanceRate === null ? null : full.meetings.attendanceRate / 100
      },
      creditRating: rating ? { score: rating.score, band: rating.band, rated: rating.rated } : null,
      externalLoans: externalLoans.map((row) => ({
        status: row.status,
        count: row._count,
        totalCents: row._sum.amountCents ?? 0
      })),
      storeCredit: storeRequests.map((row) => ({
        status: row.status,
        count: row._count,
        totalCents: row._sum.requestedAmountCents ?? 0
      }))
    });
  } catch (error) {
    next(error);
  }
});

/**
 * The Portfolio Financial Report: every group a viewer may see, one row each,
 * with totals. Partners, lenders and read-only viewers see their programmes;
 * an IWL admin sees the platform (optionally one programme). Group-level only -
 * no member appears - and a group under the small-group threshold shows no
 * money in its own row, though it still counts in the totals.
 *
 * ?cycle=current (default) reports each group's active cycle; ?cycle=previous
 * its last closed one (groups that have never closed a cycle are left out).
 */
const PORTFOLIO_REPORT_ROLES = ["IWL_ADMIN", "PARTNER_OFFICER", "LENDER", "READ_ONLY", "VILLAGE_AGENT"];

function portfolioRow(statement: GroupStatement, hideMoney: boolean) {
  const money = !hideMoney;
  return {
    groupId: statement.group.id,
    name: statement.group.name,
    code: statement.group.code,
    county: statement.group.county,
    cycleNumber: statement.cycle.number,
    activeMembers: statement.members.active,
    meetingsHeld: statement.meetings.held,
    attendanceRate: statement.meetings.attendanceRate,
    suppressed: hideMoney,
    shareCapitalCents: money ? statement.loanFund.sharesCents : null,
    loanFundCents: money ? statement.loanFund.closingCents : null,
    socialFundCents: money ? statement.socialFund.closingCents : null,
    loansOutstandingCents: money ? statement.loans.outstandingCents : null,
    activeLoans: money ? statement.loans.activeCount : null,
    par30Rate: money ? statement.loans.par30Rate : null,
    repaymentRate: money ? statement.loans.repaymentRate : null,
    interestCents: money ? statement.income.interestCents : null,
    finesCents: money ? statement.income.finesCents : null,
    equityCents: money ? statement.equity.totalCents : null,
    returnOnSavings: money ? statement.equity.returnOnSavings : null,
    cashReconciles: statement.cash.reconciles
  };
}

router.get("/reports/portfolio-financials", requireAuth("analytics:read"), async (req, res, next) => {
  try {
    if (!PORTFOLIO_REPORT_ROLES.includes(req.user?.role ?? "")) {
      throw new ApiHttpError(403, "FORBIDDEN", "The portfolio report is for programme, partner and platform accounts.");
    }
    const which = req.query.cycle === "previous" ? "previous" : "current";
    const programmeId =
      typeof req.query.programmeId === "string" && req.query.programmeId ? req.query.programmeId : undefined;

    const groups = await prisma.group.findMany({
      where: {
        AND: [
          scopeGroupWhere(req.user),
          await demoExclusionForUser(req.user),
          ...(programmeId
            ? [{ OR: [{ programmeId }, { programmeLinks: { some: { programmeId } } }] }]
            : [])
        ]
      },
      select: { id: true },
      orderBy: { name: "asc" }
    });

    const groupLevelOnly = GROUP_LEVEL_ONLY_ROLES.includes(req.user?.role ?? "");
    const statements: GroupStatement[] = [];
    for (const group of groups) {
      let cycleId: string | undefined;
      if (which === "previous") {
        const closed = await prisma.cycle.findFirst({
          where: { groupId: group.id, status: "CLOSED" },
          orderBy: { number: "desc" },
          select: { id: true }
        });
        if (!closed) continue;
        cycleId = closed.id;
      }
      const statement = await buildGroupStatement(group.id, { cycleId });
      if (statement) statements.push(statement);
    }

    const sum = (pick: (s: GroupStatement) => number) => statements.reduce((total, s) => total + pick(s), 0);
    const outstanding = sum((s) => s.loans.outstandingCents);
    const due = sum((s) => s.loans.dueCents);
    const totalActive = sum((s) => s.members.active);
    const recordedHeld = sum((s) => s.meetings.held);
    const totalsHidden = groupLevelOnly && totalActive < SMALL_GROUP_THRESHOLD;

    ok(res, {
      generatedAt: new Date().toISOString(),
      cycle: which,
      scope: reportAccountScope(req.user),
      programmeId: programmeId ?? null,
      smallGroupThreshold: SMALL_GROUP_THRESHOLD,
      totals: {
        groups: statements.length,
        activeMembers: totalActive,
        meetingsHeld: recordedHeld,
        suppressed: totalsHidden,
        ...(totalsHidden
          ? {}
          : {
              shareCapitalCents: sum((s) => s.loanFund.sharesCents),
              loanFundCents: sum((s) => s.loanFund.closingCents),
              socialFundCents: sum((s) => s.socialFund.closingCents),
              loansOutstandingCents: outstanding,
              activeLoans: sum((s) => s.loans.activeCount),
              loansPastDue: sum((s) => s.loans.pastDueCount),
              par30Rate: outstanding > 0 ? Math.round((sum((s) => s.loans.par30Cents) / outstanding) * 1000) / 10 : null,
              repaymentRate: due > 0 ? Math.round((sum((s) => s.loans.dueCollectedCents) / due) * 100) : null,
              interestCents: sum((s) => s.income.interestCents),
              finesCents: sum((s) => s.income.finesCents),
              welfarePaidCents: sum((s) => s.socialFund.welfarePaidCents),
              shareOutPaidCents: sum((s) => s.loanFund.shareOutPaidCents),
              equityCents: sum((s) => s.equity.totalCents),
              returnOnSavings: (() => {
                const capital = sum((s) => s.equity.capitalCents);
                return capital > 0 ? Math.round(((sum((s) => s.equity.totalCents) - capital) / capital) * 1000) / 10 : null;
              })(),
              groupsNotReconciling: statements.filter((s) => !s.cash.reconciles).length
            })
      },
      groups: statements.map((statement) =>
        portfolioRow(statement, groupLevelOnly && statement.members.active < SMALL_GROUP_THRESHOLD)
      )
    });
  } catch (error) {
    next(error);
  }
});

router.get("/reports/member/:memberId", requireAuth("members:read"), async (req, res, next) => {
  try {
    // One named person's money is for them, their group, their agent and the
    // platform - not for a partner, lender or read-only viewer (Kenya DPA).
    if (GROUP_LEVEL_ONLY_ROLES.includes(req.user?.role ?? "")) {
      throw new ApiHttpError(403, "MEMBER_REPORT_NOT_AVAILABLE", "Member statements are not shared outside the group.");
    }
    const memberId = String(req.params.memberId);
    // Scope-check first: officials and admins may view a member, but only
    // one they are entitled to see.
    const allowed = await prisma.member.findFirst({
      where: memberScopeForUser(req.user, { id: memberId }),
      select: { id: true }
    });
    if (!allowed) {
      ok(res.status(404), null);
      return;
    }

    // Same aggregation the member's own passbook uses, so the group's copy
    // of a member's figures always matches the member's own.
    const passbook = await buildMemberPassbook(memberId, {
      cycleId: typeof req.query.cycleId === "string" ? req.query.cycleId : undefined
    });
    if (!passbook) {
      ok(res.status(404), null);
      return;
    }
    ok(res, passbook);
  } catch (error) {
    next(error);
  }
});

router.get("/reports/agent", requireAuth("village-agents:read"), async (req, res, next) => {
  try {
    // An agent gets their own caseload; an admin can pass ?agentId= for any.
    const requestedAgentId =
      typeof req.query.agentId === "string" && req.user?.role === "IWL_ADMIN"
        ? req.query.agentId
        : req.user?.villageAgentId;
    if (!requestedAgentId) {
      ok(res.status(400), null);
      return;
    }

    const agent = await prisma.villageAgent.findFirst({
      where: { AND: [villageAgentScopeForUser(req.user), { id: requestedAgentId }] },
      select: {
        id: true,
        name: true,
        phone: true,
        county: true,
        status: true,
        caseloadLimit: true,
        programmeLinks: {
          select: { programme: { select: { id: true, name: true } } },
          orderBy: { createdAt: "asc" }
        }
      }
    });
    if (!agent) {
      ok(res.status(404), null);
      return;
    }

    const groups = await prisma.group.findMany({
      where: { villageAgentId: agent.id },
      select: {
        id: true,
        name: true,
        code: true,
        county: true,
        cycleNumber: true,
        _count: { select: { members: true, meetings: true } }
      }
    });
    const groupRows = [];
    for (const group of groups) {
      const rating = await latestCreditRating(group.id);
      const statement = await buildGroupStatement(group.id);
      const needsSupport =
        !rating || !rating.rated || rating.band === "C" || rating.band === "D";
      groupRows.push({
        id: group.id,
        name: group.name,
        code: group.code,
        county: group.county,
        cycleNumber: group.cycleNumber,
        memberCount: group._count.members,
        meetingCount: statement?.meetings.held ?? group._count.meetings,
        shareCapitalCents: statement?.loanFund.sharesCents ?? 0,
        loansOutstandingCents: statement?.loans.outstandingCents ?? 0,
        par30Rate: statement?.loans.par30Rate ?? null,
        creditRating: rating
          ? { score: rating.score, band: rating.band, rated: rating.rated }
          : null,
        needsSupport
      });
    }

    ok(res, {
      generatedAt: new Date().toISOString(),
      agent,
      summary: {
        groups: groupRows.length,
        rated: groupRows.filter((row) => row.creditRating?.rated).length,
        needSupport: groupRows.filter((row) => row.needsSupport).length,
        totalMembers: groupRows.reduce((sum, row) => sum + row.memberCount, 0),
        shareCapitalCents: groupRows.reduce((sum, row) => sum + row.shareCapitalCents, 0),
        loansOutstandingCents: groupRows.reduce((sum, row) => sum + row.loansOutstandingCents, 0)
      },
      groups: groupRows
    });
  } catch (error) {
    next(error);
  }
});

export { router as reportsRouter };
