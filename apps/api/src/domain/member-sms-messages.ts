/**
 * The words a member actually receives.
 *
 * Pure, like `credit-rating-contract` and `visit-location`: no Prisma, no
 * clock, no I/O. Every figure is passed in, so the wording can be asserted
 * exactly without a database, and so nothing here can accidentally query a
 * balance it has not been given.
 *
 * Two constraints shape all of it:
 *
 *  * **An SMS segment is 160 GSM-7 characters.** Every character past that
 *    bills a second message to the group, on every meeting, for every member.
 *    Zero-valued lines are dropped rather than printed as "KES 0".
 *  * **A phone in a village is often shared.** These messages carry one
 *    member's own position and nothing about anybody else's, and they never
 *    include a PIN, a balance of the group's cash box, or another member's
 *    name.
 */

/** Africa/Nairobi, so a meeting held in the evening is not dated yesterday. */
const MEETING_TIME_ZONE = "Africa/Nairobi";

const DATE_FORMAT = new Intl.DateTimeFormat("en-GB", {
  day: "numeric",
  month: "short",
  year: "numeric",
  timeZone: MEETING_TIME_ZONE
});

/**
 * `KES 500` rather than `KES 500.00`.
 *
 * VSLA amounts are whole shillings almost without exception, and the two
 * characters saved on each of five figures is most of a second segment. Cents
 * are still printed when they exist, because silently rounding money a member
 * is checking against their passbook would be worse than a longer text.
 */
export function formatSmsMoney(cents: number) {
  const shillings = cents / 100;
  const whole = Number.isInteger(shillings);
  return `KES ${shillings.toLocaleString("en-KE", {
    minimumFractionDigits: whole ? 0 : 2,
    maximumFractionDigits: 2
  })}`;
}

export function formatSmsDate(value: Date) {
  return DATE_FORMAT.format(value);
}

/**
 * The name to open with.
 *
 * First name only: it is what a person is called, and a full name eats
 * characters that a figure needs. Falls back to the whole string when there is
 * no space in it.
 */
export function smsFirstName(fullName: string) {
  const trimmed = fullName.trim();
  if (!trimmed) return "Member";
  return trimmed.split(/\s+/)[0] ?? trimmed;
}

export interface SharePurchaseSmsInput {
  memberName: string;
  groupName: string;
  amountCents: number;
  /** Everything this member has put into shares in the running cycle, this purchase included. */
  cycleSharesCents: number;
  recordedAt: Date;
}

/**
 * Confirmation that a share purchase was recorded.
 *
 * The point is not the receipt. It is that a member who did NOT buy shares
 * learns, the same day, that the group's books say they did — which is the
 * whole reason VSLA methodology puts the passbook in the member's own hands.
 * So the message names the amount, the group and the day, and says who to ask.
 */
export function buildSharePurchaseSms(input: SharePurchaseSmsInput) {
  const name = smsFirstName(input.memberName);
  return (
    `${name}: ${formatSmsMoney(input.amountCents)} shares recorded at ` +
    `${input.groupName} on ${formatSmsDate(input.recordedAt)}. ` +
    `Your shares this cycle: ${formatSmsMoney(input.cycleSharesCents)}. ` +
    `Query? Ask your secretary.`
  );
}

export interface MemberMeetingTotals {
  sharesCents: number;
  socialCents: number;
  finesCents: number;
  loanRepaidCents: number;
  loanReceivedCents: number;
}

export interface MeetingSummarySmsInput {
  memberName: string;
  groupName: string;
  meetingDate: Date;
  totals: MemberMeetingTotals;
  /**
   * What this member still owes, interest included, or null when it is not
   * known. Null prints nothing at all — a member told "balance KES 0" when the
   * figure was simply unavailable would reasonably conclude their loan was
   * cleared.
   */
  loanBalanceCents: number | null;
}

/**
 * One member's own transactions at a meeting that has just been sealed.
 *
 * Sent to every member, each about themselves. Nobody is told what anybody
 * else did; a member who transacted nothing is told exactly that, because
 * "you were recorded as doing nothing" is the message that catches an entry
 * posted against the wrong person.
 */
export function buildMeetingSummarySms(input: MeetingSummarySmsInput) {
  const name = smsFirstName(input.memberName);
  const { totals } = input;

  const parts: string[] = [];
  if (totals.sharesCents > 0) parts.push(`shares ${formatSmsMoney(totals.sharesCents)}`);
  if (totals.socialCents > 0) parts.push(`social ${formatSmsMoney(totals.socialCents)}`);
  if (totals.loanRepaidCents > 0) parts.push(`loan repaid ${formatSmsMoney(totals.loanRepaidCents)}`);
  if (totals.loanReceivedCents > 0) parts.push(`loan taken ${formatSmsMoney(totals.loanReceivedCents)}`);
  if (totals.finesCents > 0) parts.push(`fine ${formatSmsMoney(totals.finesCents)}`);

  const activity = parts.length > 0 ? `You: ${parts.join(", ")}.` : "You: nothing recorded for you.";
  const balance =
    input.loanBalanceCents === null || input.loanBalanceCents <= 0
      ? ""
      : ` Loan balance ${formatSmsMoney(input.loanBalanceCents)}.`;

  return (
    `${name}: ${input.groupName} meeting of ${formatSmsDate(input.meetingDate)} is closed. ` +
    `${activity}${balance} Query? Ask your secretary.`
  );
}

/**
 * How many 160-character segments a body bills as.
 *
 * Recorded alongside each send so the cost of turning these on is a number
 * somebody can look up, rather than a surprise on the Bonga account.
 */
export function smsSegments(body: string) {
  return Math.max(1, Math.ceil(body.length / 160));
}
