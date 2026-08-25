import { Router } from "express";
import type { Prisma } from "@prisma/client";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import {
  ledgerScopeForUser,
  memberScopeForUser,
  scopeGroupWhere,
  villageAgentScopeForUser
} from "../services/account-scope";
import { ok } from "../lib/http";
import { latestCreditRating } from "../services/credit-rating-service";
import { buildMemberPassbook } from "../services/member-passbook-service";
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

router.get("/reports/foundation", requireAuth("analytics:read"), async (req, res, next) => {
  try {
    const groupWhere = scopeGroupWhere(req.user);
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
            where: ledgerScopeForUser(req.user),
            orderBy: { createdAt: "desc" },
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
      fundAccounts,
      ledgerEntries,
      users,
      meetings,
      votes,
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

async function groupLedgerBreakdown(groupId: string) {
  const byType = await prisma.ledgerEntry.groupBy({
    by: ["type", "direction"],
    where: { groupId },
    _sum: { amountCents: true },
    _count: true
  });
  return byType.map((row) => ({
    type: row.type,
    direction: row.direction,
    totalCents: row._sum.amountCents ?? 0,
    entries: row._count
  }));
}

router.get("/reports/group/:id", requireAuth("groups:read"), async (req, res, next) => {
  try {
    const groupId = String(req.params.id);
    const group = await prisma.group.findFirst({
      where: scopeGroupWhere(req.user, { id: groupId }),
      include: {
        fundAccounts: { select: { type: true, balanceCents: true } },
        _count: { select: { members: true, meetings: true } }
      }
    });
    if (!group) {
      ok(res.status(404), null);
      return;
    }

    const [ledger, perMember, meetings, attendance, rating, externalLoans, storeRequests] =
      await Promise.all([
        groupLedgerBreakdown(groupId),
        prisma.ledgerEntry.groupBy({
          by: ["memberId", "type"],
          where: { groupId, memberId: { not: null } },
          _sum: { amountCents: true }
        }),
        prisma.meeting.groupBy({
          by: ["status"],
          where: { groupId },
          _count: true
        }),
        prisma.attendance.groupBy({
          by: ["status"],
          where: { meeting: { groupId } },
          _count: true
        }),
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

    const members = await prisma.member.findMany({
      where: { groupId },
      select: { id: true, fullName: true, role: true, status: true }
    });
    const memberRows = members.map((member) => {
      const rows = perMember.filter((row) => row.memberId === member.id);
      const totalFor = (type: string) =>
        rows.find((row) => row.type === type)?._sum.amountCents ?? 0;
      return {
        id: member.id,
        fullName: member.fullName,
        role: member.role,
        status: member.status,
        sharesCents: totalFor("SHARE_PURCHASE"),
        socialCents: totalFor("SOCIAL_CONTRIBUTION"),
        finesCents: totalFor("FINE_COLLECTION"),
        loanRepaymentsCents: totalFor("LOAN_REPAYMENT"),
        loanDisbursementsCents: totalFor("INTERNAL_LOAN_DISBURSEMENT")
      };
    });

    const attendanceTotal = attendance.reduce((sum, row) => sum + row._count, 0);
    const attendancePresent =
      attendance.find((row) => row.status === "PRESENT")?._count ?? 0;

    ok(res, {
      generatedAt: new Date().toISOString(),
      group: {
        id: group.id,
        name: group.name,
        code: group.code,
        county: group.county,
        phase: group.phase,
        cycleNumber: group.cycleNumber,
        memberCount: group._count.members,
        meetingCount: group._count.meetings
      },
      funds: group.fundAccounts.map((fund) => ({
        fundType: fund.type,
        balanceCents: fund.balanceCents
      })),
      ledger,
      members: memberRows,
      meetings: {
        byStatus: meetings.map((row) => ({ status: row.status, count: row._count })),
        attendanceRate: attendanceTotal > 0 ? attendancePresent / attendanceTotal : null
      },
      creditRating: rating
        ? { score: rating.score, band: rating.band, rated: rating.rated }
        : null,
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

router.get("/reports/member/:memberId", requireAuth("members:read"), async (req, res, next) => {
  try {
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
    const passbook = await buildMemberPassbook(memberId);
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
      const needsSupport =
        !rating || !rating.rated || rating.band === "C" || rating.band === "D";
      groupRows.push({
        id: group.id,
        name: group.name,
        code: group.code,
        county: group.county,
        cycleNumber: group.cycleNumber,
        memberCount: group._count.members,
        meetingCount: group._count.meetings,
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
        totalMembers: groupRows.reduce((sum, row) => sum + row.memberCount, 0)
      },
      groups: groupRows
    });
  } catch (error) {
    next(error);
  }
});

export { router as reportsRouter };
