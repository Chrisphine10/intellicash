/**
 * A VSLA group's financial statement for one cycle - the single place every
 * money figure in a report comes from.
 *
 * Before this, the group report, the portfolio, the programme report, the
 * passbook and the phone each added the ledger up their own way: lifetime
 * instead of per cycle, types summed without their direction, "savings" that
 * was really the loan fund's cash, debts without interest. The same group
 * showed different numbers on every screen. Every report now reads this.
 *
 * The VSLA model it follows:
 *  - Members buy SHARES; that money is the group's LOAN FUND (INTERNAL_LOAN).
 *  - Loans go out of the loan fund and come back into it with interest.
 *  - Social contributions and fines go to the SOCIAL (welfare) fund; welfare
 *    payments and the welfare share-out come out of it.
 *  - At the end of a cycle the group's equity - the loan fund's cash plus what
 *    borrowers still owe - is shared out in proportion to shares bought.
 *
 * Everything is integer cents, cycle-scoped (the ACTIVE cycle unless asked),
 * and signed by direction: CREDIT adds, DEBIT subtracts.
 */
import type { Loan, Prisma, PrismaClient } from "@prisma/client";
import { repaymentRatePercent } from "../domain/loan-math";
import { allocateLargestRemainder } from "../domain/share-out";
import { prisma } from "../lib/prisma";
import { loadLoanPositions } from "./loan-position-service";

type Db = Prisma.TransactionClient | PrismaClient;

const DAY_MS = 24 * 60 * 60 * 1000;

/** Opened on the server, or held on a phone (records in a SCHEDULED meeting). */
const OPENED_STATUSES = ["IN_PROGRESS", "SEALED", "SYNC_CONFLICT"];
const PRESENT_STATUSES = ["PRESENT", "LATE"];

/**
 * A meeting was HELD if someone opened it, or if anything was recorded in it.
 * A cancelled meeting and a plan the reminder planner made (AUTO_SCHEDULE)
 * with nothing in it were never held, and must not count in any rate.
 */
export function meetingWasHeld(meeting: {
  status: string;
  attendanceCount: number;
  ledgerCount: number;
}): boolean {
  if (meeting.status === "CANCELLED") return false;
  return OPENED_STATUSES.includes(meeting.status) || meeting.attendanceCount > 0 || meeting.ledgerCount > 0;
}

export interface StatementCycle {
  id: string;
  number: number;
  status: string;
  startedAt: string;
  closedAt: string | null;
}

export interface MemberStatementRow {
  memberId: string;
  fullName: string;
  role: string;
  status: string;
  sharesCents: number;
  socialCents: number;
  finesCents: number;
  /** Loans taken and repayments made in this cycle. */
  loanDisbursedCents: number;
  loanRepaidCents: number;
  loanOutstandingCents: number;
  /** Their pro-rata part of the group's equity if the cycle closed today. */
  projectedShareOutCents: number;
  /** Projected share-out less what they still owe. Negative = owes the group. */
  projectedNetCents: number;
}

export interface GroupStatement {
  generatedAt: string;
  asOf: string;
  group: { id: string; name: string; code: string; county: string; isDemo: boolean };
  cycle: StatementCycle;
  cycles: StatementCycle[];
  members: { active: number; total: number };
  meetings: {
    held: number;
    cancelled: number;
    /** Scheduled and not yet held (includes plans for reminders). */
    notHeld: number;
    /** Present or late, over the attendance recorded in held meetings. */
    attendanceRate: number | null;
  };
  loanFund: {
    openingCents: number;
    sharesCents: number;
    repaymentsCents: number;
    disbursedCents: number;
    shareOutPaidCents: number;
    otherCents: number;
    closingCents: number;
  };
  socialFund: {
    openingCents: number;
    contributionsCents: number;
    finesCents: number;
    welfarePaidCents: number;
    welfareShareOutCents: number;
    otherCents: number;
    closingCents: number;
  };
  loans: {
    activeCount: number;
    pastDueCount: number;
    principalOutstandingCents: number;
    /** Principal plus interest still owed. */
    outstandingCents: number;
    par30Cents: number;
    /** Share of the outstanding balance more than 30 days past due, 0-100. */
    par30Rate: number | null;
    /** Of what has fallen due, the share collected, 0-100. Null = nothing due yet. */
    repaymentRate: number | null;
    /** What repaymentRate is made of, so a portfolio can combine groups properly. */
    dueCents: number;
    dueCollectedCents: number;
    interestCollectedCents: number;
  };
  income: { interestCents: number; finesCents: number; totalCents: number };
  equity: {
    /** Loan fund cash plus what borrowers still owe - what a share-out would split. */
    totalCents: number;
    /** What members put in: share capital this cycle plus the loan fund carried in. */
    capitalCents: number;
    /** (equity - capital) / capital, as a percentage. */
    returnOnSavings: number | null;
    /** What each KES 100 saved is worth today, in cents. */
    valuePer100Cents: number | null;
  };
  cash: {
    /** Loan fund + social fund, from the ledger. */
    ledgerCents: number;
    /** The same, from the stored fund balances. */
    storedCents: number;
    reconciles: boolean;
  };
  /** Every ledger type in the cycle, signed. For auditors. */
  ledger: Array<{ type: string; netCents: number; entries: number }>;
  memberRows: MemberStatementRow[];
}

function serializeCycle(cycle: { id: string; number: number; status: string; startedAt: Date; closedAt: Date | null }): StatementCycle {
  return {
    id: cycle.id,
    number: cycle.number,
    status: cycle.status,
    startedAt: cycle.startedAt.toISOString(),
    closedAt: cycle.closedAt?.toISOString() ?? null
  };
}

const pct = (part: number, whole: number) => (whole > 0 ? Math.round((part / whole) * 1000) / 10 : null);

/** The cycles a group has had, newest first. */
export async function listGroupCycles(groupId: string, db: Db = prisma) {
  return db.cycle.findMany({
    where: { groupId },
    orderBy: { number: "desc" },
    select: { id: true, number: true, status: true, startedAt: true, closedAt: true }
  });
}

/**
 * The cycle a report is about: the one asked for, else the active one, else
 * the latest. A group older than cycles gets a synthetic "cycle 1" covering
 * all time, so it still has a statement.
 */
async function resolveCycle(groupId: string, cycleNumber: number, cycleId: string | undefined, db: Db) {
  const cycles = await listGroupCycles(groupId, db);
  const chosen =
    (cycleId ? cycles.find((cycle) => cycle.id === cycleId) : undefined) ??
    cycles.find((cycle) => cycle.status === "ACTIVE") ??
    cycles[0];
  const fallback = {
    id: `all-${groupId}`,
    number: cycleNumber,
    status: "ACTIVE",
    startedAt: new Date(0),
    closedAt: null as Date | null
  };
  return { cycle: chosen ?? fallback, cycles, synthetic: !chosen };
}

export async function buildGroupStatement(
  groupId: string,
  options: { cycleId?: string; asOf?: Date; db?: Db } = {}
): Promise<GroupStatement | null> {
  const db = options.db ?? prisma;
  const group = await db.group.findUnique({
    where: { id: groupId },
    select: {
      id: true,
      name: true,
      code: true,
      county: true,
      isDemo: true,
      cycleNumber: true,
      fundAccounts: { select: { type: true, balanceCents: true } }
    }
  });
  if (!group) return null;

  const { cycle, cycles, synthetic } = await resolveCycle(groupId, group.cycleNumber, options.cycleId, db);
  const isCurrent = cycle.status === "ACTIVE";
  // A closed cycle is reported as it stood when it closed.
  const asOf = options.asOf ?? (isCurrent ? new Date() : (cycle.closedAt ?? new Date()));

  // In this cycle: stamped with it, or (older rows with no stamp) inside its dates.
  const earlierCycleIds = cycles.filter((c) => c.number < cycle.number).map((c) => c.id);
  const inCycle: Prisma.LedgerEntryWhereInput = synthetic
    ? { groupId }
    : {
        groupId,
        OR: [
          { cycleId: cycle.id },
          {
            cycleId: null,
            createdAt: { gte: cycle.startedAt, ...(cycle.closedAt ? { lte: cycle.closedAt } : {}) }
          }
        ]
      };
  const beforeCycle: Prisma.LedgerEntryWhereInput | null = synthetic
    ? null
    : {
        groupId,
        OR: [{ cycleId: { in: earlierCycleIds } }, { cycleId: null, createdAt: { lt: cycle.startedAt } }]
      };

  const [cycleRows, openingRows, memberRows, allTimeFundRows, members, meetings] = await Promise.all([
    db.ledgerEntry.groupBy({
      by: ["type", "direction"],
      where: inCycle,
      _sum: { amountCents: true },
      _count: true
    }),
    beforeCycle
      ? db.ledgerEntry.findMany({
          where: beforeCycle,
          select: { amountCents: true, direction: true, fundAccount: { select: { type: true } } }
        })
      : Promise.resolve([]),
    db.ledgerEntry.groupBy({
      by: ["memberId", "type", "direction"],
      where: { ...inCycle, memberId: { not: null } },
      _sum: { amountCents: true }
    }),
    isCurrent
      ? db.ledgerEntry.findMany({
          where: { groupId },
          select: { amountCents: true, direction: true, fundAccount: { select: { type: true } } }
        })
      : Promise.resolve([]),
    db.member.findMany({
      where: { groupId },
      select: { id: true, fullName: true, role: true, status: true },
      orderBy: { fullName: "asc" }
    }),
    db.meeting.findMany({
      where: synthetic
        ? { groupId }
        : {
            groupId,
            OR: [
              { cycleId: cycle.id },
              {
                cycleId: null,
                scheduledAt: { gte: cycle.startedAt, ...(cycle.closedAt ? { lte: cycle.closedAt } : {}) }
              }
            ]
          },
      select: {
        status: true,
        _count: { select: { attendance: true, ledgerEntries: true } },
        attendance: { select: { status: true } }
      }
    })
  ]);

  // --- signed sums by type ------------------------------------------------
  const net = new Map<string, number>();
  const entries = new Map<string, number>();
  for (const row of cycleRows) {
    const signed = (row._sum.amountCents ?? 0) * (row.direction === "DEBIT" ? -1 : 1);
    net.set(row.type, (net.get(row.type) ?? 0) + signed);
    entries.set(row.type, (entries.get(row.type) ?? 0) + row._count);
  }
  const inflow = (type: string) => net.get(type) ?? 0;
  const outflow = (type: string) => -(net.get(type) ?? 0);

  const fundBalance = (rows: Array<{ amountCents: number; direction: string; fundAccount: { type: string } | null }>, fund: string) =>
    rows
      .filter((row) => row.fundAccount?.type === fund)
      .reduce((sum, row) => sum + row.amountCents * (row.direction === "DEBIT" ? -1 : 1), 0);

  // --- loan fund ------------------------------------------------------------
  const loanOpening = fundBalance(openingRows, "INTERNAL_LOAN");
  const sharesCents = inflow("SHARE_PURCHASE");
  const repaymentsCents = inflow("LOAN_REPAYMENT");
  const disbursedCents = outflow("INTERNAL_LOAN_DISBURSEMENT");
  const shareOutPaidCents = outflow("SHARE_OUT_PAYOUT");
  // --- social fund ------------------------------------------------------------
  const socialOpening = fundBalance(openingRows, "SOCIAL");
  const contributionsCents = inflow("SOCIAL_CONTRIBUTION");
  const finesCents = inflow("FINE_COLLECTION");
  const welfarePaidCents = outflow("WELFARE_EXPENSE");
  const welfareShareOutCents = outflow("WELFARE_SHARE_OUT");

  // Closing balances straight from the funds the entries were posted to, so a
  // type posted to an unusual fund (grants, VSLF) is still counted where it sat.
  const cycleFundRows = await db.ledgerEntry.findMany({
    where: inCycle,
    select: { amountCents: true, direction: true, fundAccount: { select: { type: true } } }
  });
  const loanClosing = loanOpening + fundBalance(cycleFundRows, "INTERNAL_LOAN");
  const socialClosing = socialOpening + fundBalance(cycleFundRows, "SOCIAL");
  const loanOther = loanClosing - (loanOpening + sharesCents + repaymentsCents - disbursedCents - shareOutPaidCents);
  const socialOther =
    socialClosing -
    (socialOpening + contributionsCents + finesCents - welfarePaidCents - welfareShareOutCents);

  // --- loans ------------------------------------------------------------------
  const positions = await loadLoanPositions(db, { groupIds: [groupId] }, asOf);
  const inScope = (loan: Loan, settled: boolean) =>
    synthetic || loan.cycleId === cycle.id || (!settled && loan.disbursedAt.getTime() <= asOf.getTime());
  const loanEntries = [...positions.values()].flatMap((position) =>
    position.loans.filter((entry) => inScope(entry.loan, entry.settled))
  );
  const outstandingByMember = new Map<string, number>();
  let principalOutstanding = 0;
  let outstanding = 0;
  let par30 = 0;
  let activeCount = 0;
  let pastDueCount = 0;
  let interestCollected = 0;
  for (const entry of loanEntries) {
    // Principal first: what was repaid beyond the principal is interest.
    interestCollected += Math.max(0, entry.repaidCents - entry.principalCents);
    if (entry.settled) continue;
    activeCount += 1;
    outstanding += entry.outstandingCents;
    principalOutstanding += Math.max(0, entry.principalCents - entry.repaidCents);
    outstandingByMember.set(
      entry.loan.memberId,
      (outstandingByMember.get(entry.loan.memberId) ?? 0) + entry.outstandingCents
    );
    const overdueMs = asOf.getTime() - entry.loan.dueAt.getTime();
    if (overdueMs > 0) pastDueCount += 1;
    if (overdueMs > 30 * DAY_MS) par30 += entry.outstandingCents;
  }
  let dueCents = 0;
  let dueCollectedCents = 0;
  for (const entry of loanEntries) {
    if (!entry.settled && entry.loan.dueAt.getTime() > asOf.getTime()) continue;
    const owed = entry.principalCents + entry.interestCents;
    dueCents += owed;
    dueCollectedCents += owed - entry.outstandingCents;
  }
  const repaymentRate = repaymentRatePercent(
    loanEntries.map((entry) => ({ ...entry, dueAt: entry.loan.dueAt })),
    asOf
  );

  // --- equity -----------------------------------------------------------------
  const equityCents = loanClosing + outstanding;
  const capitalCents = sharesCents + Math.max(0, loanOpening);
  const returnOnSavings = capitalCents > 0 ? Math.round(((equityCents - capitalCents) / capitalCents) * 1000) / 10 : null;

  // --- meetings ---------------------------------------------------------------
  let held = 0;
  let cancelled = 0;
  let present = 0;
  let recorded = 0;
  for (const meeting of meetings) {
    if (meeting.status === "CANCELLED") {
      cancelled += 1;
      continue;
    }
    if (!meetingWasHeld({ status: meeting.status, attendanceCount: meeting._count.attendance, ledgerCount: meeting._count.ledgerEntries })) {
      continue;
    }
    held += 1;
    recorded += meeting.attendance.length;
    present += meeting.attendance.filter((a) => PRESENT_STATUSES.includes(a.status)).length;
  }

  // --- members ----------------------------------------------------------------
  const activeMembers = members.filter((member) => member.status === "ACTIVE");
  const memberNet = (memberId: string, type: string) =>
    memberRows
      .filter((row) => row.memberId === memberId && row.type === type)
      .reduce((sum, row) => sum + (row._sum.amountCents ?? 0) * (row.direction === "DEBIT" ? -1 : 1), 0);
  // Anyone with shares or a debt is on the statement, active or not: their money is in the book.
  const listed = members.filter(
    (member) =>
      member.status === "ACTIVE" || memberNet(member.id, "SHARE_PURCHASE") !== 0 || outstandingByMember.has(member.id)
  );
  const shares = listed.map((member) => Math.max(0, memberNet(member.id, "SHARE_PURCHASE")));
  const projected = allocateLargestRemainder(Math.max(0, equityCents), shares);
  const memberStatementRows: MemberStatementRow[] = listed.map((member, index) => {
    const owed = outstandingByMember.get(member.id) ?? 0;
    return {
      memberId: member.id,
      fullName: member.fullName,
      role: member.role,
      status: member.status,
      sharesCents: shares[index] ?? 0,
      socialCents: memberNet(member.id, "SOCIAL_CONTRIBUTION"),
      finesCents: memberNet(member.id, "FINE_COLLECTION"),
      loanDisbursedCents: -memberNet(member.id, "INTERNAL_LOAN_DISBURSEMENT"),
      loanRepaidCents: memberNet(member.id, "LOAN_REPAYMENT"),
      loanOutstandingCents: owed,
      projectedShareOutCents: projected[index] ?? 0,
      projectedNetCents: (projected[index] ?? 0) - owed
    };
  });

  // --- cash reconciliation (current cycle only: stored balances are "now") -----
  const ledgerCash = isCurrent
    ? fundBalance(allTimeFundRows, "INTERNAL_LOAN") + fundBalance(allTimeFundRows, "SOCIAL")
    : loanClosing + socialClosing;
  const storedCash = group.fundAccounts
    .filter((fund) => fund.type === "INTERNAL_LOAN" || fund.type === "SOCIAL")
    .reduce((sum, fund) => sum + fund.balanceCents, 0);

  return {
    generatedAt: new Date().toISOString(),
    asOf: asOf.toISOString(),
    group: { id: group.id, name: group.name, code: group.code, county: group.county, isDemo: group.isDemo },
    cycle: serializeCycle(cycle),
    cycles: cycles.map(serializeCycle),
    members: { active: activeMembers.length, total: members.length },
    meetings: {
      held,
      cancelled,
      notHeld: meetings.length - held - cancelled,
      attendanceRate: recorded > 0 ? Math.round((present / recorded) * 1000) / 10 : null
    },
    loanFund: {
      openingCents: loanOpening,
      sharesCents,
      repaymentsCents,
      disbursedCents,
      shareOutPaidCents,
      otherCents: loanOther,
      closingCents: loanClosing
    },
    socialFund: {
      openingCents: socialOpening,
      contributionsCents,
      finesCents,
      welfarePaidCents,
      welfareShareOutCents,
      otherCents: socialOther,
      closingCents: socialClosing
    },
    loans: {
      activeCount,
      pastDueCount,
      principalOutstandingCents: principalOutstanding,
      outstandingCents: outstanding,
      par30Cents: par30,
      par30Rate: pct(par30, outstanding),
      repaymentRate,
      dueCents,
      dueCollectedCents,
      interestCollectedCents: interestCollected
    },
    income: {
      interestCents: interestCollected,
      finesCents,
      totalCents: interestCollected + finesCents
    },
    equity: {
      totalCents: equityCents,
      capitalCents,
      returnOnSavings,
      valuePer100Cents: capitalCents > 0 ? Math.round((equityCents / capitalCents) * 10_000) : null
    },
    cash: {
      ledgerCents: ledgerCash,
      storedCents: storedCash,
      reconciles: !isCurrent || ledgerCash === storedCash
    },
    ledger: [...net.entries()]
      .map(([type, netCents]) => ({ type, netCents, entries: entries.get(type) ?? 0 }))
      .sort((a, b) => a.type.localeCompare(b.type)),
    memberRows: memberStatementRows
  };
}

/** Roles that see group-level figures only (Kenya DPA 2019: minimisation). */
export const GROUP_LEVEL_ONLY_ROLES = ["PARTNER_OFFICER", "LENDER", "READ_ONLY"];

/** Below this many active members, a group's own money is not shown to those roles. */
export const SMALL_GROUP_THRESHOLD = 5;

/**
 * What a partner, lender or read-only viewer may see of a statement: no
 * member rows, and no money at all for a group small enough that its figures
 * would describe individuals.
 */
export function redactStatementForRole(statement: GroupStatement, role: string | undefined) {
  if (!GROUP_LEVEL_ONLY_ROLES.includes(role ?? "")) return { ...statement, suppressed: false };
  const suppressed = statement.members.active < SMALL_GROUP_THRESHOLD;
  return {
    ...statement,
    memberRows: [] as MemberStatementRow[],
    suppressed,
    ...(suppressed
      ? {
          loanFund: null,
          socialFund: null,
          loans: null,
          income: null,
          equity: null,
          cash: null,
          ledger: [] as GroupStatement["ledger"]
        }
      : {})
  };
}
