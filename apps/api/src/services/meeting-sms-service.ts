import { loanBalance } from "../domain/loan-math";
import {
  buildMeetingSummarySms,
  buildSharePurchaseSms,
  formatSmsDate,
  type MemberMeetingTotals
} from "../domain/member-sms-messages";
import { prisma } from "../lib/prisma";
import { dispatchMemberSms, type MemberSmsRecipient } from "./member-sms-service";

/**
 * Gathers what a member needs to be told, and hands it to `member-sms-service`
 * to send.
 *
 * Split from the sending for the usual reason: this half knows the ledger and
 * nothing about providers, that half knows providers and nothing about VSLA.
 * The wording itself lives in `domain/member-sms-messages`, which knows neither.
 *
 * Both entry points are opt-in per group and both return quietly when a group
 * has not asked for them - see `GroupPolicy.sms*Enabled`. Turning these on
 * spends the platform's SMS credits on every meeting, so no group inherits
 * them.
 */

interface Dependencies {
  fetch?: typeof fetch;
  networkEnabled?: boolean;
}

async function smsPolicyFor(groupId: string) {
  const policy = await prisma.groupPolicy.findUnique({
    where: { groupId },
    select: { smsSharePurchaseEnabled: true, smsMeetingSummaryEnabled: true }
  });

  return {
    sharePurchase: policy?.smsSharePurchaseEnabled ?? false,
    meetingSummary: policy?.smsMeetingSummaryEnabled ?? false
  };
}

/** The ledger entries a caller just wrote, as far as this service cares. */
export interface NotifiableLedgerEntry {
  id: string;
  groupId: string;
  memberId: string | null;
  meetingId: string | null;
  cycleId: string | null;
  type: string;
  amountCents: number;
  createdAt: Date;
}

/**
 * Confirm share purchases recorded at a meeting.
 *
 * One text per entry, not per member: buying twice in a meeting is two
 * separate movements of money, and collapsing them would hide one of them from
 * the person whose passbook has to agree with the book.
 */
export async function notifySharePurchases(
  entries: NotifiableLedgerEntry[],
  options: { requestedByUserId?: string | null } = {},
  dependencies: Dependencies = {}
) {
  const purchases = entries.filter(
    (entry) => entry.type === "SHARE_PURCHASE" && entry.memberId && entry.meetingId
  );
  if (purchases.length === 0) return null;

  const groupId = purchases[0]!.groupId;
  const policy = await smsPolicyFor(groupId);
  if (!policy.sharePurchase) return null;

  const group = await prisma.group.findUnique({ where: { id: groupId }, select: { name: true } });
  if (!group) return null;

  const memberIds = [...new Set(purchases.map((entry) => entry.memberId as string))];
  const members = await prisma.member.findMany({
    where: { id: { in: memberIds }, groupId, status: "ACTIVE" },
    select: { id: true, fullName: true, phone: true }
  });
  const memberById = new Map(members.map((member) => [member.id, member]));

  // Shares to date in the cycle this entry belongs to - the figure a member
  // checks against their passbook. Grouped in one query rather than one per
  // member; a busy meeting can hold thirty of these.
  const cycleIds = purchases
    .map((entry) => entry.cycleId)
    .filter((cycleId): cycleId is string => Boolean(cycleId));
  const cycleTotals = await prisma.ledgerEntry.groupBy({
    by: ["memberId", "cycleId"],
    where: {
      groupId,
      type: "SHARE_PURCHASE",
      memberId: { in: memberIds },
      ...(cycleIds.length > 0 ? { cycleId: { in: [...new Set(cycleIds)] } } : {})
    },
    _sum: { amountCents: true }
  });
  const cycleShareTotal = (memberId: string, cycleId: string | null) =>
    cycleTotals.find((row) => row.memberId === memberId && row.cycleId === cycleId)?._sum
      .amountCents ?? 0;

  const recipients: MemberSmsRecipient[] = [];
  for (const entry of purchases) {
    const member = memberById.get(entry.memberId as string);
    if (!member) continue;

    recipients.push({
      memberId: member.id,
      memberName: member.fullName,
      phone: member.phone,
      message: buildSharePurchaseSms({
        memberName: member.fullName,
        groupName: group.name,
        amountCents: entry.amountCents,
        cycleSharesCents: cycleShareTotal(member.id, entry.cycleId),
        recordedAt: entry.createdAt
      })
    });
  }

  if (recipients.length === 0) return null;

  return dispatchMemberSms(
    {
      kind: "SHARE_PURCHASE",
      groupId,
      meetingId: purchases[0]!.meetingId,
      requestedByUserId: options.requestedByUserId ?? null,
      label: `Share purchase confirmations for ${recipients.length} member(s).`,
      recipients
    },
    dependencies
  );
}

const EMPTY_TOTALS: MemberMeetingTotals = {
  sharesCents: 0,
  socialCents: 0,
  finesCents: 0,
  loanRepaidCents: 0,
  loanReceivedCents: 0
};

/**
 * After a meeting is sealed, tell every active member what the books now say
 * about them.
 *
 * Every member, not only those who transacted. "Nothing was recorded for you"
 * is the message that catches a contribution posted against the wrong name,
 * and a member who was absent still needs to know a fine did not appear
 * against them.
 */
export async function sendMeetingSummaries(
  meetingId: string,
  options: { requestedByUserId?: string | null } = {},
  dependencies: Dependencies = {}
) {
  const meeting = await prisma.meeting.findUnique({
    where: { id: meetingId },
    select: {
      id: true,
      groupId: true,
      closedAt: true,
      scheduledAt: true,
      group: { select: { name: true } }
    }
  });
  if (!meeting) return null;

  const policy = await smsPolicyFor(meeting.groupId);
  if (!policy.meetingSummary) return null;

  const [members, byMemberType, loans] = await Promise.all([
    prisma.member.findMany({
      where: { groupId: meeting.groupId, status: "ACTIVE" },
      orderBy: { joinedAt: "asc" },
      select: { id: true, fullName: true, phone: true }
    }),
    prisma.ledgerEntry.groupBy({
      by: ["memberId", "type"],
      where: { meetingId, memberId: { not: null } },
      _sum: { amountCents: true }
    }),
    // Loans as the projection sees them, so the balance quoted includes
    // interest. A loan disbursed before the projection existed has no row, and
    // that member is told no balance rather than a wrong one.
    prisma.loan.findMany({
      where: { groupId: meeting.groupId, status: "ACTIVE" },
      select: {
        memberId: true,
        principalCents: true,
        interestRateBps: true,
        termMonths: true,
        disbursedAt: true,
        repayments: { select: { amountCents: true } }
      }
    })
  ]);

  const asOf = new Date();
  const outstandingByMember = new Map<string, number>();
  for (const loan of loans) {
    const repaid = loan.repayments.reduce((sum, row) => sum + row.amountCents, 0);
    const balance = loanBalance({
      principalCents: loan.principalCents,
      interestRateBps: loan.interestRateBps,
      termMonths: loan.termMonths,
      disbursedAt: loan.disbursedAt,
      repaidCents: repaid,
      asOf
    });
    outstandingByMember.set(
      loan.memberId,
      (outstandingByMember.get(loan.memberId) ?? 0) + balance.outstandingCents
    );
  }

  const totalFor = (memberId: string, type: string) =>
    byMemberType.find((row) => row.memberId === memberId && row.type === type)?._sum.amountCents ??
    0;

  const meetingDate = meeting.closedAt ?? meeting.scheduledAt;
  const recipients: MemberSmsRecipient[] = members.map((member) => ({
    memberId: member.id,
    memberName: member.fullName,
    phone: member.phone,
    message: buildMeetingSummarySms({
      memberName: member.fullName,
      groupName: meeting.group.name,
      meetingDate,
      totals: {
        ...EMPTY_TOTALS,
        sharesCents: totalFor(member.id, "SHARE_PURCHASE"),
        socialCents: totalFor(member.id, "SOCIAL_CONTRIBUTION"),
        finesCents: totalFor(member.id, "FINE_COLLECTION"),
        loanRepaidCents: totalFor(member.id, "LOAN_REPAYMENT"),
        loanReceivedCents: totalFor(member.id, "INTERNAL_LOAN_DISBURSEMENT")
      },
      loanBalanceCents: outstandingByMember.get(member.id) ?? null
    })
  }));

  if (recipients.length === 0) return null;

  return dispatchMemberSms(
    {
      kind: "MEETING_SUMMARY",
      groupId: meeting.groupId,
      meetingId: meeting.id,
      requestedByUserId: options.requestedByUserId ?? null,
      label: `Per-member summary of the meeting of ${formatSmsDate(meetingDate)}.`,
      recipients
    },
    dependencies
  );
}
