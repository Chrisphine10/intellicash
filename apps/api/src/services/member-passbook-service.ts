import { prisma } from "../lib/prisma";
import { loanBalance } from "../domain/loan-math";

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

export async function buildMemberPassbook(memberId: string) {
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

  const [byType, attendance, recent, loans, welfareReceived, shareOuts] = await Promise.all([
    prisma.ledgerEntry.groupBy({
      by: ["type"],
      where: { memberId },
      _sum: { amountCents: true },
      _count: true
    }),
    prisma.attendance.groupBy({
      by: ["status"],
      where: { memberId },
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
    // Loans as the projection sees them, with their repayments, so interest
    // can be computed rather than ignored.
    prisma.loan.findMany({
      where: { memberId },
      orderBy: { disbursedAt: "desc" },
      include: { repayments: { select: { amountCents: true } } }
    }),
    // Welfare a member RECEIVED. Distinct from what they contributed — a
    // passbook showing only contributions misses half the relationship.
    prisma.ledgerEntry.findMany({
      where: { memberId, type: "WELFARE_EXPENSE" },
      orderBy: { createdAt: "desc" },
      select: { id: true, amountCents: true, description: true, createdAt: true }
    }),
    prisma.ledgerEntry.findMany({
      where: { memberId, type: "SHARE_OUT_PAYOUT" },
      orderBy: { createdAt: "desc" },
      select: { id: true, amountCents: true, description: true, createdAt: true }
    })
  ]);

  const totalFor = (type: string) =>
    byType.find((row) => row.type === type)?._sum.amountCents ?? 0;

  const sharesCents = totalFor(SHARES);
  const socialCents = totalFor(SOCIAL);
  const finesCents = totalFor(FINES);
  const loansReceivedCents = totalFor(LOAN_DISBURSEMENT);
  const loansRepaidCents = totalFor(LOAN_REPAYMENT);

  // Per-loan balances, interest included. The previous figure was simply
  // disbursed minus repaid, which IGNORES INTEREST and understates what a
  // member owes — on a flat monthly loan that gap widens every month.
  const asOf = new Date();
  const loanDetail = loans.map((loan) => {
    const repaid = loan.repayments.reduce((sum, r) => sum + r.amountCents, 0);
    const balance = loanBalance({
      principalCents: loan.principalCents,
      interestRateBps: loan.interestRateBps,
      termMonths: loan.termMonths,
      disbursedAt: loan.disbursedAt,
      repaidCents: repaid,
      asOf
    });
    return {
      id: loan.id,
      status: loan.status,
      disbursedAt: loan.disbursedAt.toISOString(),
      dueAt: loan.dueAt.toISOString(),
      termMonths: loan.termMonths,
      interestRateBps: loan.interestRateBps,
      ...balance,
      overdue: loan.status === "ACTIVE" && loan.dueAt < asOf && balance.outstandingCents > 0
    };
  });
  const loanInterestCents = loanDetail.reduce((s, l) => s + l.interestCents, 0);
  const loanOutstandingWithInterestCents =
      loanDetail.reduce((s, l) => s + l.outstandingCents, 0);
  const welfareReceivedCents = welfareReceived.reduce((s, e) => s + e.amountCents, 0);
  const shareOutReceivedCents = shareOuts.reduce((s, e) => s + e.amountCents, 0);

  const attendanceTotal = attendance.reduce((sum, row) => sum + row._count, 0);
  const attendancePresent =
    attendance.find((row) => row.status === "PRESENT")?._count ?? 0;

  return {
    generatedAt: new Date().toISOString(),
    member,
    /// Pre-computed so a phone shows the same figures the server would.
    summary: {
      sharesCents,
      socialCents,
      finesCents,
      totalPaidInCents: sharesCents + socialCents + finesCents,
      loansReceivedCents,
      loansRepaidCents,
      // Never show a negative balance when someone overpays.
      loanOutstandingCents: Math.max(0, loansReceivedCents - loansRepaidCents),
      // The line above is the LEDGER difference and ignores interest. Kept
      // for compatibility; prefer the interest-aware figure below.
      loanInterestCents,
      loanOutstandingWithInterestCents,
      welfareReceivedCents,
      shareOutReceivedCents
    },
    loans: loanDetail,
    welfareReceived: welfareReceived.map((e) => ({ id: e.id, amountCents: e.amountCents, description: e.description, createdAt: e.createdAt.toISOString() })),
    shareOutHistory: shareOuts.map((e) => ({ id: e.id, amountCents: e.amountCents, description: e.description, createdAt: e.createdAt.toISOString() })),
    totals: byType.map((row) => ({
      type: row.type,
      totalCents: row._sum.amountCents ?? 0,
      entries: row._count
    })),
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
export async function buildMemberOverview(userId: string) {
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
    if (passbook) {
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
      loanOutstandingCents: sum((g) => g.summary.loanOutstandingCents)
    },
    groups
  };
}
