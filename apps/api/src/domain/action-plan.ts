/**
 * Action items agreed at a field visit, and whether they are late.
 *
 * **OVERDUE is derived, never stored.** A column holding it would be wrong
 * every night between midnight and whenever a job ran, and would need that job
 * to exist at all. Computing it from `dueDate` at read time means the answer is
 * correct the instant it is asked and there is nothing to schedule, nothing to
 * monitor, and no way for the database to disagree with the calendar.
 *
 * The same reasoning as document expiry — see `group-document-state.ts`. Both
 * are facts about a date, and a fact about a date should be worked out from the
 * date.
 */

/** What somebody actually did about the item. Stored. */
export const ACTION_ITEM_STATUSES = ["OPEN", "IN_PROGRESS", "DONE", "CANCELLED"] as const;
export type ActionItemStatus = (typeof ACTION_ITEM_STATUSES)[number];

/** What a reader should see. Derived. Note OVERDUE, which is never stored. */
export const ACTION_ITEM_STATES = [
  "OVERDUE",
  "DUE_SOON",
  "OPEN",
  "IN_PROGRESS",
  "DONE",
  "CANCELLED"
] as const;
export type ActionItemState = (typeof ACTION_ITEM_STATES)[number];

/** Inside this window an item is worth raising before it slips. */
export const DUE_SOON_DAYS = 7;

const MS_PER_DAY = 24 * 60 * 60 * 1000;

export interface ActionItemFacts {
  status: string;
  dueDate?: Date | null;
}

export interface ActionItemState_ {
  state: ActionItemState;
  status: string;
  dueDate: Date | null;
  /** Negative once past. Null when the item has no due date. */
  daysUntilDue: number | null;
  /** How late it is, in days. Zero unless overdue. */
  daysOverdue: number;
  /** Still requires somebody to do something. */
  open: boolean;
  label: string;
}

/**
 * Resolves the state a reader should see.
 *
 * A DONE or CANCELLED item is never overdue, however far past its date it sits.
 * "You closed this late" is a different report from "this is outstanding", and
 * conflating them makes the follow-up queue useless — it would fill with work
 * that is already finished.
 */
export function actionItemState(
  facts: ActionItemFacts,
  now: Date = new Date()
): ActionItemState_ {
  const dueDate = facts.dueDate ?? null;
  const daysUntilDue =
    dueDate === null ? null : Math.floor((dueDate.getTime() - now.getTime()) / MS_PER_DAY);

  if (facts.status === "DONE" || facts.status === "CANCELLED") {
    return {
      state: facts.status as ActionItemState,
      status: facts.status,
      dueDate,
      daysUntilDue,
      daysOverdue: 0,
      open: false,
      label: LABELS[facts.status as ActionItemState]
    };
  }

  const overdue = daysUntilDue !== null && daysUntilDue < 0;
  const dueSoon = daysUntilDue !== null && daysUntilDue >= 0 && daysUntilDue <= DUE_SOON_DAYS;

  const state: ActionItemState = overdue
    ? "OVERDUE"
    : dueSoon
      ? "DUE_SOON"
      : facts.status === "IN_PROGRESS"
        ? "IN_PROGRESS"
        : "OPEN";

  return {
    state,
    status: facts.status,
    dueDate,
    daysUntilDue,
    daysOverdue: overdue ? Math.abs(daysUntilDue) : 0,
    open: true,
    label: LABELS[state]
  };
}

const LABELS: Record<ActionItemState, string> = {
  OVERDUE: "Overdue",
  DUE_SOON: "Due soon",
  OPEN: "Open",
  IN_PROGRESS: "In progress",
  DONE: "Done",
  CANCELLED: "Cancelled"
};

/**
 * Ages a set of items for the follow-up queue.
 *
 * Ordered worst-first: an agent opening their queue should meet the thing that
 * has been outstanding longest, not the newest thing added.
 */
export function actionPlanSummary(states: readonly ActionItemState_[]) {
  const open = states.filter((item) => item.open);
  return {
    total: states.length,
    open: open.length,
    overdue: states.filter((item) => item.state === "OVERDUE").length,
    dueSoon: states.filter((item) => item.state === "DUE_SOON").length,
    done: states.filter((item) => item.state === "DONE").length,
    /** The longest anything has been outstanding. 0 when nothing is late. */
    worstDaysOverdue: states.reduce((worst, item) => Math.max(worst, item.daysOverdue), 0)
  };
}

/**
 * Sort key for the follow-up queue: most overdue first, then due soonest, then
 * items with no date at all.
 *
 * Undated items sort last deliberately. They are real work, but an item with a
 * date attached is one somebody committed to, and those come first.
 */
export function byUrgency(a: ActionItemState_, b: ActionItemState_) {
  if (a.open !== b.open) return a.open ? -1 : 1;
  if (a.daysUntilDue === null && b.daysUntilDue === null) return 0;
  if (a.daysUntilDue === null) return 1;
  if (b.daysUntilDue === null) return -1;
  return a.daysUntilDue - b.daysUntilDue;
}

/**
 * The 1-5 rating a group gives the mentorship it received.
 *
 * Deliberately collected from the group's representative, not the agent. An
 * agent rating their own session scores 4 or 5 every time and the aggregate
 * means nothing; the representative is already holding the phone for the
 * sign-off PIN, so asking them costs nothing extra.
 */
export const MIN_MENTORSHIP_RATING = 1;
export const MAX_MENTORSHIP_RATING = 5;

export function isValidMentorshipRating(score: number) {
  return (
    Number.isInteger(score) &&
    score >= MIN_MENTORSHIP_RATING &&
    score <= MAX_MENTORSHIP_RATING
  );
}

/**
 * Averages ratings across dimensions.
 *
 * Returns null rather than 0 for "nobody rated this". Zero is a score, and a
 * group that was never asked must not appear alongside one that was rated
 * badly — that difference is the whole value of the number.
 */
export function averageRating(scores: readonly number[]): number | null {
  const valid = scores.filter(isValidMentorshipRating);
  if (valid.length === 0) return null;
  return Math.round((valid.reduce((sum, score) => sum + score, 0) / valid.length) * 100) / 100;
}
