/**
 * A group's meeting schedule, and when members are reminded of a meeting.
 *
 * The schedule exists for one purpose: reminding people. It never opens a
 * meeting - a meeting starts only when an official starts it. Nothing here
 * writes a status.
 *
 * Pure - no Prisma. The clock is passed in. The phone app implements the same
 * rules (lib/core/utils/meeting_schedule.dart); keep the two in step.
 *
 * Times are Africa/Nairobi, which has been UTC+3 all year since 1942 with no
 * daylight saving, so a fixed offset is exact.
 */

export const MEETING_FREQUENCIES = ["WEEKLY", "BIWEEKLY", "MONTHLY"] as const;
export type MeetingFrequency = (typeof MEETING_FREQUENCIES)[number];

export const REMINDER_KINDS = ["H24", "H2"] as const;
export type ReminderKind = (typeof REMINDER_KINDS)[number];

export const NAIROBI_OFFSET_MS = 3 * 60 * 60 * 1000;
const DAY_MS = 24 * 60 * 60 * 1000;
const HOUR_MS = 60 * 60 * 1000;

/** How far ahead the planner looks for the next meeting day. */
export const PLANNING_HORIZON_DAYS = 7;

export interface MeetingSchedule {
  frequency: MeetingFrequency;
  /** ISO weekdays, 1 = Monday ... 7 = Sunday. */
  days: number[];
  /** "HH:mm", Nairobi time. */
  time: string;
}

const DAY_NAMES = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

/** Reads the stored columns; null when the group has no usable schedule. */
export function readMeetingSchedule(input: {
  meetingFrequency?: string | null;
  meetingDays?: string | null;
  meetingTime?: string | null;
}): MeetingSchedule | null {
  const frequency = input.meetingFrequency as MeetingFrequency | undefined;
  if (!frequency || !MEETING_FREQUENCIES.includes(frequency)) return null;
  const days = parseMeetingDays(input.meetingDays);
  if (days.length === 0) return null;
  if (!isMeetingTime(input.meetingTime)) return null;
  return { frequency, days, time: input.meetingTime };
}

export function parseMeetingDays(value: string | null | undefined): number[] {
  if (!value) return [];
  try {
    const parsed: unknown = JSON.parse(value);
    if (!Array.isArray(parsed)) return [];
    const days = parsed.filter((d): d is number => Number.isInteger(d) && d >= 1 && d <= 7);
    return [...new Set(days)].sort((a, b) => a - b);
  } catch {
    return [];
  }
}

export function isMeetingTime(value: string | null | undefined): value is string {
  if (!value) return false;
  const match = /^(\d{2}):(\d{2})$/.exec(value);
  if (!match) return false;
  return Number(match[1]) <= 23 && Number(match[2]) <= 59;
}

/** "Monday, Thursday" - kept in Group.meetingDay for older screens. */
export function meetingDaysLabel(days: number[]): string {
  return days.map((d) => DAY_NAMES[d - 1]).join(", ");
}

/** Nairobi calendar date of an instant, as "YYYY-MM-DD". */
export function nairobiDateKey(at: Date): string {
  return new Date(at.getTime() + NAIROBI_OFFSET_MS).toISOString().slice(0, 10);
}

/** The UTC instants bounding the Nairobi calendar day that contains `at`. */
export function nairobiDayBounds(at: Date): { start: Date; end: Date } {
  const local = at.getTime() + NAIROBI_OFFSET_MS;
  const startLocal = local - (((local % DAY_MS) + DAY_MS) % DAY_MS);
  return { start: new Date(startLocal - NAIROBI_OFFSET_MS), end: new Date(startLocal - NAIROBI_OFFSET_MS + DAY_MS) };
}

/** ISO weekday (1 = Monday) of a Nairobi-local day number. */
function isoWeekdayOfLocalDay(localDay: number): number {
  // Day 0 (1970-01-01) was a Thursday.
  return ((localDay + 3) % 7 + 7) % 7 + 1;
}

/** Local day number of the Monday that starts the week containing `localDay`. */
function weekStartOf(localDay: number): number {
  return localDay - (isoWeekdayOfLocalDay(localDay) - 1);
}

/**
 * The next meeting time the schedule gives, strictly after `now`.
 *
 * - WEEKLY: every chosen weekday.
 * - BIWEEKLY: every other week, counted from the week of the group's last
 *   meeting (any week, when it has never met).
 * - MONTHLY: the chosen weekdays in the first seven days of each month
 *   ("the first Monday").
 *
 * A day on which the group already met (`lastMeetingAt`) is never offered
 * again. Returns null when nothing matches in the next two months, which only
 * happens with a broken schedule.
 */
export function nextMeetingSlot(schedule: MeetingSchedule, now: Date, lastMeetingAt: Date | null = null): Date | null {
  const [hours = 0, minutes = 0] = schedule.time.split(":").map(Number);
  const nowLocal = now.getTime() + NAIROBI_OFFSET_MS;
  const today = Math.floor(nowLocal / DAY_MS);
  const lastDay = lastMeetingAt ? Math.floor((lastMeetingAt.getTime() + NAIROBI_OFFSET_MS) / DAY_MS) : null;

  for (let day = today; day <= today + 62; day += 1) {
    if (!schedule.days.includes(isoWeekdayOfLocalDay(day))) continue;
    if (lastDay !== null && day === lastDay) continue;

    if (schedule.frequency === "BIWEEKLY" && lastDay !== null) {
      const weeks = (weekStartOf(day) - weekStartOf(lastDay)) / 7;
      if (weeks % 2 !== 0) continue;
    }
    if (schedule.frequency === "MONTHLY") {
      const dayOfMonth = new Date(day * DAY_MS).getUTCDate();
      if (dayOfMonth > 7) continue;
    }

    const slot = new Date(day * DAY_MS + (hours * 60 + minutes) * 60 * 1000 - NAIROBI_OFFSET_MS);
    if (slot.getTime() > now.getTime()) return slot;
  }
  return null;
}

/**
 * Which reminder, if any, is due for a meeting at `scheduledAt`.
 *
 * H24 from a day before until two hours before; H2 from two hours before
 * until the start. After the start nothing is due: a reminder for a meeting
 * already under way (or missed) is noise, and a missed meeting is for an
 * official to cancel, not for the system to chase.
 *
 * A meeting scheduled less than two hours ahead gets only the H2 reminder -
 * the day-before one would arrive after the fact.
 */
export function dueReminder(scheduledAt: Date, now: Date): ReminderKind | null {
  const until = scheduledAt.getTime() - now.getTime();
  if (until <= 0) return null;
  if (until <= 2 * HOUR_MS) return "H2";
  if (until <= 24 * HOUR_MS) return "H24";
  return null;
}

/** The text sent to a member. Short enough for one SMS page. */
export function meetingReminderText(input: {
  groupName: string;
  scheduledAt: Date;
  kind: ReminderKind;
  location?: string | null;
}): string {
  const local = new Date(input.scheduledAt.getTime() + NAIROBI_OFFSET_MS);
  const hh = local.getUTCHours();
  const mm = String(local.getUTCMinutes()).padStart(2, "0");
  const time = `${hh % 12 === 0 ? 12 : hh % 12}:${mm} ${hh < 12 ? "am" : "pm"}`;
  const when = input.kind === "H2" ? "today" : `on ${DAY_NAMES[(local.getUTCDay() + 6) % 7]}`;
  const where = input.location ? ` at ${input.location}` : "";
  return `Reminder: ${input.groupName} meeting ${when} at ${time}${where}. Please attend.`;
}
