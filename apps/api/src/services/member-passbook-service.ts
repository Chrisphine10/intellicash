/**
 * One member's passbook, aggregated on the server.
 *
 * The mobile app used to pull a member's raw ledger rows and add them up on
 * the phone. This is the single definition, shared by `GET /members/me` and
 * the member report so the two can never disagree. All money is integer cents.
 */

import { prisma } from "../lib/prisma";
import { loadLoanPositions } from "./loan-position-service";

/**
 * One member's passbook, aggregated on the server.
 *
 * The mobile app used to pull a member's raw ledger rows and add them up on
 * the phone. That worked, but it meant every client re-implemented the
 * arithmetic (and a paginated or trimmed ledger would silently under-count).
 * This is the single definition, shared by `GET /members/me` and the member
 * report so the two can never disagree.
 *
 * All money is integer cents.
 */

/** Ledger types that make up a member's savings position. */
const SHARES = "SHARE_PURCHASE";
const SOCIAL = "SOCIAL_CONTRIBUTION";
const FINES = "FINE_COLLECTION";
const LOAN_REPAYMENT = "LOAN_REPAYMENT";
const LOAN_DISBURSEMENT = "INTERNAL_LOAN_DISBURSEMENT";

/**
 * A member's passbook for one cycle - the group's active cycle unless
 * `cycleId` names another, or "all" for every cycle.
 *
 * The savings figures are the cycle's, because that is what a VSLA member is
 * asked: shares bought since the last share-out, which is what the next one
 * pays out on. Adding up every cycle ever counted money already handed back.
 * `lifetime` keeps the all-cycles totals for anyone who wants them.
 */
export async function buildMemberPassbook(memberId: string, options: { cycleId?: string | "all" } = {}) {
  const member = await prisma.member.findUnique({
    where: { id: memberId },
    select: {
      id: true,
      fullName: true,
      role: true,
      status: true,
      phone: true,
      joinedAt: true,
      group: { select: { id: true, name: true, code: true, cycleNumber: true } }
    }
  });
  if (!member) return null;

  const asOf = new Date();
  const cycles = await prisma.cycle.findMany({
    where: { groupId: member.group.id },
    orderBy: { number: "desc" },
    select: { id: true, number: true, status: true, startedAt: true, closedAt: true }
  });
  const cycle =
    options.cycleId === "all"
      ? null
      : ((options.cycleId ? cycles.find((c) => c.id === options.cycleId) : undefined) ??
        cycles.find((c) => c.status === "ACTIVE") ??
        cycles[0] ??
        null);
  // Stamped with the cycle, or (rows older than cycles) inside its dates.
  const inCycle = cycle
    ? {
        OR: [
          { cycleId: cycle.id },
          { cycleId: null, createdAt: { gte: cycle.startedAt, ...(cycle.closedAt ? { lte: cycle.closedAt } : {}) } }
        ]
      }
    : {};
  const meetingInCycle = cycle
    ? {
        OR: [
          { cycleId: cycle.id },
          { cycleId: null, scheduledAt: { gte: cycle.startedAt, ...(cycle.closedAt ? { lte: cycle.closedAt } : {}) } }
        ]
      }
    : {};

  const [byTypeSigned, lifetimeByType, attendance, recent, positions, welfareReceived, shareOuts] = await Promise.all([
    prisma.ledgerEntry.groupBy({
      by: ["type", "direction"],
      where: { memberId, ...inCycle },
      _sum: { amountCents: true },
      _count: true
    }),
    prisma.ledgerEntry.groupBy({
      by: ["type", "direction"],
      where: { memberId },
      _sum: { amountCents: true },
      _count: true
    }),
    // Attendance at meetings that happened this cycle - a cancelled meeting
    // is not one a member could have missed.
    prisma.attendance.groupBy({
      by: ["status"],
      where: { memberId, meeting: { status: { not: "CANCELLED" }, ...meetingInCycle } },
      _count: true
    }),
    prisma.ledgerEntry.findMany({
      where: { memberId },
      orderBy: { createdAt: "desc" },
      take: 20,
      select: {
        id: true,
        type: true,
        direction: true,
        amountCents: true,
        description: true,
        createdAt: true
      }
    }),
    // Loans as the projection sees them, judged together with every repayment
    // the member made, so interest is computed rather than ignored and a
    // settled loan stays settled.
    loadLoanPositions(prisma, { memberIds: [memberId] }, asOf),
    // Welfare a member RECEIVED. Distinct from what they contributed — a
    // passbook showing only contributions misses half the relationship.
    prisma.ledgerEntry.findMany({
      where: { memberId, type: "WELFARE_EXPENSE" },
      orderBy: { createdAt: "desc" },
      select: { id: true, amountCents: true, description: true, createdAt: true }
    }),
    prisma.ledgerEntry.findMany({
      // Both halves of a share-out: the pro-rata payout from the loan fund
      // and the welfare remainder from the social fund. Counting only the
      // first would understate what the member was actually handed.
      where: { memberId, type: { in: ["SHARE_OUT_PAYOUT", "WELFARE_SHARE_OUT"] } },
      orderBy: { createdAt: "desc" },
      select: { id: true, amountCents: true, description: true, createdAt: true }
    })
  ]);

  // Signed by direction: a DEBIT against a type (a correction) takes away.
  type Row = { type: string; direction: string; _sum: { amountCents: number | null }; _count: number };
  const signedTotal = (rows: Row[], type: string) =>
    Math.abs(
      rows
        .filter((row) => row.type === type)
        .reduce((sum, row) => sum + (row._sum.amountCents ?? 0) * (row.direction === "DEBIT" ? -1 : 1), 0)
    );
  const collapse = (rows: Row[]) => {
    const byType = new Map<string, { totalCents: number; entries: number }>();
    for (const row of rows) {
      const current = byType.get(row.type) ?? { totalCents: 0, entries: 0 };
      byType.set(row.type, { totalCents: 0, entries: current.entries + row._count });
    }
    return [...byType.entries()].map(([type, value]) => ({
      type,
      totalCents: signedTotal(rows, type),
      entries: value.entries
    }));
  };
  const totalFor = (type: string) => signedTotal(byTypeSigned, type);
  const lifetimeFor = (type: string) => signedTotal(lifetimeByType, type);

  const sharesCents = totalFor(SHARES);
  const socialCents = totalFor(SOCIAL);
  const finesCents = totalFor(FINES);
  const loansReceivedCents = totalFor(LOAN_DISBURSEMENT);
  const loansRepaidCents = totalFor(LOAN_REPAYMENT);

  // Per-loan balances, interest included. The previous figure was simply
  // disbursed minus repaid, which IGNORES INTEREST and understates what a
  // member owes — on a flat monthly loan that gap widens every month.
  // Newest first, as this list has always been shown.
  const loanDetail = [...(positions.get(memberId)?.loans ?? [])]
    .reverse()
    .map(({ loan, id, settledAt, ...balance }) => ({
      id,
      status: settledAt ? "REPAID" : loan.status,
      disbursedAt: loan.disbursedAt.toISOString(),
      dueAt: loan.dueAt.toISOString(),
      termMonths: loan.termMonths,
      interestRateBps: loan.interestRateBps,
      ...balance,
      settledAt: settledAt ? settledAt.toISOString() : null,
      overdue: !balance.settled && loan.dueAt < asOf && balance.outstandingCents > 0
    }));
  const loanInterestCents = loanDetail.reduce((s, l) => s + l.interestCents, 0);
  const ledgerOnlyOutstandingCents = Math.max(0, lifetimeFor(LOAN_DISBURSEMENT) - lifetimeFor(LOAN_REPAYMENT));
  /**
   * Interest-aware outstanding — but NEVER below what the ledger already
   * proves is owed.
   *
   * `Loan` is a projection, and loans disbursed before it existed have no
   * Loan row until `prisma/backfill-loans.ts` has been run. Summing only the
   * projected loans then yields ZERO for a member the ledger says owes real
   * money, and a client that trusts this field would tell them their debt is
   * cleared. Found on a seeded database where the ledger showed 4,500.00
   * outstanding and this figure came back 0.
   *
   * With no projection to consult, the ledger difference is the only truth
   * available: it omits interest, which understates, but understating is a
   * smaller lie than reporting nothing owed at all.
   */
  const loanOutstandingWithInterestCents = loanDetail.length === 0
    ? ledgerOnlyOutstandingCents
    : loanDetail.reduce((s, l) => s + l.outstandingCents, 0);
  const welfareReceivedCents = welfareReceived.reduce((s, e) => s + e.amountCents, 0);
  const shareOutReceivedCents = shareOuts.reduce((s, e) => s + e.amountCents, 0);

  const attendanceTotal = attendance.reduce((sum, row) => sum + row._count, 0);
  // Late is still there: the same rule as every group and programme report.
  const attendancePresent = attendance
    .filter((row) => row.status === "PRESENT" || row.status === "LATE")
    .reduce((sum, row) => sum + row._count, 0);

  return {
    generatedAt: new Date().toISOString(),
    member,
    cycle: cycle
      ? {
          id: cycle.id,
          number: cycle.number,
          status: cycle.status,
          startedAt: cycle.startedAt.toISOString(),
          closedAt: cycle.closedAt?.toISOString() ?? null
        }
      : null,
    cycles: cycles.map((c) => ({ id: c.id, number: c.number, status: c.status })),
    /// Pre-computed so a phone shows the same figures the server would.
    /// Savings and loan flows are THIS CYCLE's; debts are whatever is owed now.
    summary: {
      sharesCents,
      socialCents,
      finesCents,
      totalPaidInCents: sharesCents + socialCents + finesCents,
      loansReceivedCents,
      loansRepaidCents,
      // Never show a negative balance when someone overpays.
      loanOutstandingCents: ledgerOnlyOutstandingCents,
      // The line above is the LEDGER difference and ignores interest. Kept
      // for older clients (tests pin it); every report shows the
      // interest-aware figure below.
      loanInterestCents,
      loanOutstandingWithInterestCents,
      welfareReceivedCents,
      shareOutReceivedCents
    },
    loans: loanDetail,
    welfareReceived: welfareReceived.map((e) => ({ id: e.id, amountCents: e.amountCents, description: e.description, createdAt: e.createdAt.toISOString() })),
    shareOutHistory: shareOuts.map((e) => ({ id: e.id, amountCents: e.amountCents, description: e.description, createdAt: e.createdAt.toISOString() })),
    totals: collapse(byTypeSigned),
    lifetime: {
      sharesCents: lifetimeFor(SHARES),
      socialCents: lifetimeFor(SOCIAL),
      finesCents: lifetimeFor(FINES),
      totals: collapse(lifetimeByType)
    },
    attendance: {
      present: attendancePresent,
      total: attendanceTotal,
      rate: attendanceTotal > 0 ? attendancePresent / attendanceTotal : null
    },
    recentEntries: recent
  };
}

/** One group's slice of the overview below. */
export type MemberPassbook = NonNullable<Awaited<ReturnType<typeof buildMemberPassbook>>>;

/**
 * One person's whole savings position, across every group they belong to.
 *
 * A member who saves with three VSLAs has three passbooks and, until now, no
 * way to see the total. The per-group figures come from exactly the same
 * builder as the single passbook, so a group's page and this report can never
 * disagree about that group.
 */
export async function buildMemberOverview(
  userId: string,
  options: { includeGroup?: (groupId: string) => boolean } = {}
) {
  const [links, account] = await Promise.all([
    prisma.userMembership.findMany({
      where: { userId },
      orderBy: { createdAt: "asc" },
      select: { memberId: true }
    }),
    prisma.user.findUnique({
      where: { id: userId },
      select: { name: true, phone: true, memberId: true }
    })
  ]);

  const groups: Array<MemberPassbook & { isActive: boolean }> = [];
  for (const link of links) {
    const passbook = await buildMemberPassbook(link.memberId);
    // A membership whose Member row has gone is skipped rather than counted
    // as zero — a silent zero would understate someone's savings.
    // Groups that have switched member sign-ins off drop out of the view
    // (their figures are the group's to share, not the member's).
    if (passbook && (!options.includeGroup || options.includeGroup(passbook.member.group.id))) {
      groups.push({ ...passbook, isActive: link.memberId === account?.memberId });
    }
  }

  const sum = (pick: (g: MemberPassbook) => number) =>
    groups.reduce((total, g) => total + pick(g), 0);

  return {
    generatedAt: new Date().toISOString(),
    member: { name: account?.name ?? "Member", phone: account?.phone ?? null },
    groupCount: groups.length,
    combined: {
      sharesCents: sum((g) => g.summary.sharesCents),
      socialCents: sum((g) => g.summary.socialCents),
      finesCents: sum((g) => g.summary.finesCents),
      totalPaidInCents: sum((g) => g.summary.totalPaidInCents),
      loansReceivedCents: sum((g) => g.summary.loansReceivedCents),
      loansRepaidCents: sum((g) => g.summary.loansRepaidCents),
      // Each group's outstanding is already floored at zero, so overpaying in
      // one group can never cancel a real debt in another.
      loanOutstandingCents: sum((g) => g.summary.loanOutstandingCents),
      // The line above is the LEDGER difference and ignores interest — it is
      // kept only so older clients keep working. Every figure below rolls up
      // the same interest-aware numbers the single-group passbook reports.
      // Without them, someone borrowing in two groups saw a combined debt
      // smaller than either group would actually collect.
      loanInterestCents: sum((g) => g.summary.loanInterestCents),
      loanOutstandingWithInterestCents: sum(
        (g) => g.summary.loanOutstandingWithInterestCents
      ),
      welfareReceivedCents: sum((g) => g.summary.welfareReceivedCents),
      shareOutReceivedCents: sum((g) => g.summary.shareOutReceivedCents)
    },
    groups
  };
}
