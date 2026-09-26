/**
 * Packages a group's data into a restorable bundle.
 *
 * Used when a group needs to be moved between environments or restored from a
 * snapshot. Produces a self-contained export of the group and everything
 * scoped to it.
 */

import { prisma } from "../lib/prisma";
import { memberAccountsEnabledFor } from "./member-accounts-service";
import { loadLoanPositions } from "./loan-position-service";

function gcd(a: number, b: number): number {
  return b === 0 ? a : gcd(b, a % b);
}

/**
 * The share value a group's recorded purchases were actually made in, for a
 * group whose rules never reached the server.
 *
 * The group row's value is kept when every purchase is a whole number of it
 * (it is then at least consistent with the book). Otherwise the largest value
 * every purchase is a multiple of is the group's share: a group buying
 * KSh 50, 100 and 150 of shares saves in KSh 50 shares. With no purchases at
 * all there is nothing to go on but the row.
 */
export function shareValueFromHistory(
  entries: Array<{ type: string; amountCents: number }>,
  rowValueCents: number
): number {
  const amounts = entries
    .filter((entry) => entry.type === "SHARE_PURCHASE" && entry.amountCents > 0)
    .map((entry) => entry.amountCents);
  if (amounts.length === 0) return rowValueCents;
  if (rowValueCents > 0 && amounts.every((amount) => amount % rowValueCents === 0)) return rowValueCents;
  return amounts.reduce((acc, amount) => gcd(acc, amount));
}

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
  const [group, cycles, policy, meetings, attendance, entries, loans, members] = await prisma.$transaction([
    prisma.group.findUniqueOrThrow({
      where: { id: groupId },
      select: {
        id: true,
        name: true,
        cycleNumber: true,
        shareValueCents: true,
        maxSharesPerMemberPerMeeting: true,
        meetingFrequency: true,
        meetingDays: true,
        meetingTime: true,
        remindersEnabled: true
      }
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
        interestType: true,
        termMonths: true,
        disbursedAt: true,
        dueAt: true,
        status: true,
        disbursementEntryId: true
      }
    }),
    prisma.member.findMany({
      where: { groupId },
      select: { id: true, fullName: true, phone: true, role: true, status: true }
    })
  ]);

  // How each repayment was actually applied: the server replays a member's
  // repayments across all their loans (oldest first, a surplus rolling on),
  // while a ledger row points at one loan only. Without this a restored phone
  // pinned each payment to that one loan, dropped any with no loan link, and
  // showed balances the server did not.
  const positions = await loadLoanPositions(prisma, { groupIds: [groupId] }, new Date());
  const allocations = [...positions.values()].flatMap((position) =>
    position.allocations
      .filter((slice) => slice.repaymentId)
      .map((slice) => ({ repaymentEntryId: slice.repaymentId!, loanId: slice.loanId, cents: slice.cents }))
  );

  const cycleNumber = new Map(cycles.map((cycle) => [cycle.id, cycle.number]));
  const active = [...cycles].reverse().find((cycle) => cycle.status === "ACTIVE") ?? null;
  const numberOf = (cycleId: string | null) => (cycleId ? cycleNumber.get(cycleId) ?? null : null);

  return {
    group: {
      id: group.id,
      name: group.name,
      cycleNumber: active?.number ?? group.cycleNumber,
      // When the open cycle began: the line the phone draws its balances from.
      cycleStartedAt: active?.startedAt.toISOString() ?? null,
      // Its schedule, so a restored phone meets on the group's real days.
      meetingFrequency: group.meetingFrequency,
      meetingDays: group.meetingDays,
      meetingTime: group.meetingTime,
      remindersEnabled: group.remindersEnabled
    },
    policy: {
      configured: Boolean(policy),
      loanInterestRateBps: policy?.loanInterestRateBps ?? 0,
      defaultLoanTermMonths: policy?.defaultLoanTermMonths ?? 1,
      // The group's own rules, so a restored phone computes exactly what the
      // old one did instead of starting from made-up defaults. The rule the
      // group set on its phone wins. A group that never pushed one gets the
      // share value its own history shows (see [shareValueFromHistory]) — the
      // group row's KSh 500 is a schema default most groups never chose.
      interestType: policy?.interestType ?? "FLAT",
      shareValueCents:
        policy?.shareValueCents ?? shareValueFromHistory(entries, group.shareValueCents),
      maxSharesPerMeeting: policy?.maxSharesPerMeeting ?? group.maxSharesPerMemberPerMeeting,
      socialFundCents: policy?.socialFundCents ?? null,
      loanMultiplierBps: policy?.loanMultiplierBps ?? null,
      memberAccountsEnabled: await memberAccountsEnabledFor(groupId)
    },
    members: members.map((member) => {
      const [firstName, ...rest] = member.fullName.split(" ");
      return {
        id: member.id,
        firstName,
        lastName: rest.join(" ") || "",
        phone: member.phone,
        role: member.role,
        status: member.status
      };
    }),
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
      interestType: loan.interestType,
      termMonths: loan.termMonths,
      disbursedAt: loan.disbursedAt.toISOString(),
      dueAt: loan.dueAt.toISOString(),
      status: loan.status,
      disbursementEntryId: loan.disbursementEntryId
    })),
    allocations
  };
}
