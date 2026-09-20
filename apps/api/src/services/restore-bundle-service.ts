import { prisma } from "../lib/prisma";

/**
 * Everything a phone needs to rebuild a group's record book: its cycles, meetings
 * and attendance, every ledger entry, and the loans those entries created.
 *
 * Read-only, compact and complete on purpose. The existing list routes each
 * return a slice of this with nested objects a phone does not need (the group
 * repeated on every ledger row, for one), and none of them says which cycle a
 * row belongs to or which loan a repayment paid. A phone restoring a group on a
 * new handset needs all of it, once, in one consistent snapshot - a loan
 * without its repayments, or repayments without their loan, would put the wrong
 * balance in front of a treasurer.
 *
 * Amounts are integer cents, dates are ISO strings, and every row carries the id
 * the server knows it by, so the phone can remember which of its rows are the
 * server's and never send them back.
 */
export async function buildRestoreBundle(groupId: string) {
  // One transaction, so the snapshot is consistent: a repayment recorded while
  // this runs cannot appear without the loan it paid, or the reverse.
  const [group, cycles, policy, meetings, attendance, entries, loans] = await prisma.$transaction([
    prisma.group.findUniqueOrThrow({
      where: { id: groupId },
      select: { id: true, name: true, cycleNumber: true }
    }),
    prisma.cycle.findMany({ where: { groupId }, orderBy: { number: "asc" } }),
    prisma.groupPolicy.findUnique({ where: { groupId } }),
    prisma.meeting.findMany({
      where: { groupId },
      orderBy: { scheduledAt: "asc" },
      select: { id: true, title: true, scheduledAt: true, status: true, closedAt: true, cycleId: true }
    }),
    prisma.attendance.findMany({
      where: { meeting: { groupId } },
      select: { meetingId: true, memberId: true, status: true }
    }),
    prisma.ledgerEntry.findMany({
      where: { groupId },
      orderBy: { createdAt: "asc" },
      select: {
        id: true,
        meetingId: true,
        memberId: true,
        loanId: true,
        cycleId: true,
        type: true,
        direction: true,
        amountCents: true,
        description: true,
        externalReference: true,
        createdAt: true
      }
    }),
    prisma.loan.findMany({
      where: { groupId },
      orderBy: { disbursedAt: "asc" },
      select: {
        id: true,
        memberId: true,
        cycleId: true,
        principalCents: true,
        interestRateBps: true,
        termMonths: true,
        disbursedAt: true,
        dueAt: true,
        status: true,
        disbursementEntryId: true
      }
    })
  ]);

  const cycleNumber = new Map(cycles.map((cycle) => [cycle.id, cycle.number]));
  const active = [...cycles].reverse().find((cycle) => cycle.status === "ACTIVE") ?? null;
  const numberOf = (cycleId: string | null) => (cycleId ? cycleNumber.get(cycleId) ?? null : null);

  return {
    group: {
      id: group.id,
      name: group.name,
      cycleNumber: active?.number ?? group.cycleNumber,
      // When the open cycle began: the line the phone draws its balances from.
      cycleStartedAt: active?.startedAt.toISOString() ?? null
    },
    policy: {
      configured: Boolean(policy),
      loanInterestRateBps: policy?.loanInterestRateBps ?? 0,
      defaultLoanTermMonths: policy?.defaultLoanTermMonths ?? 1
    },
    meetings: meetings.map((meeting) => ({
      id: meeting.id,
      title: meeting.title,
      scheduledAt: meeting.scheduledAt.toISOString(),
      status: meeting.status,
      closedAt: meeting.closedAt?.toISOString() ?? null,
      cycleNumber: numberOf(meeting.cycleId)
    })),
    attendance,
    entries: entries.map((entry) => ({
      id: entry.id,
      meetingId: entry.meetingId,
      memberId: entry.memberId,
      loanId: entry.loanId,
      cycleNumber: numberOf(entry.cycleId),
      type: entry.type,
      direction: entry.direction,
      amountCents: entry.amountCents,
      description: entry.description,
      externalReference: entry.externalReference,
      createdAt: entry.createdAt.toISOString()
    })),
    loans: loans.map((loan) => ({
      id: loan.id,
      memberId: loan.memberId,
      cycleNumber: numberOf(loan.cycleId),
      principalCents: loan.principalCents,
      interestRateBps: loan.interestRateBps,
      termMonths: loan.termMonths,
      disbursedAt: loan.disbursedAt.toISOString(),
      dueAt: loan.dueAt.toISOString(),
      status: loan.status,
      disbursementEntryId: loan.disbursementEntryId
    }))
  };
}
