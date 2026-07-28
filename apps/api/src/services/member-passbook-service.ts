import { prisma } from "../lib/prisma";

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

  const [byType, attendance, recent] = await Promise.all([
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
    })
  ]);

  const totalFor = (type: string) =>
    byType.find((row) => row.type === type)?._sum.amountCents ?? 0;

  const sharesCents = totalFor(SHARES);
  const socialCents = totalFor(SOCIAL);
  const finesCents = totalFor(FINES);
  const loansReceivedCents = totalFor(LOAN_DISBURSEMENT);
  const loansRepaidCents = totalFor(LOAN_REPAYMENT);

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
      loanOutstandingCents: Math.max(0, loansReceivedCents - loansRepaidCents)
    },
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
