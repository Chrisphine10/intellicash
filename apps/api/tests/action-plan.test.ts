import { describe, expect, it } from "vitest";
import {
  DUE_SOON_DAYS,
  actionItemState,
  actionPlanSummary,
  averageRating,
  byUrgency,
  isValidMentorshipRating
} from "../src/domain/action-plan";

const DAY = 24 * 60 * 60 * 1000;
const now = new Date("2026-08-10T12:00:00Z");

function at(daysFromNow: number) {
  return new Date(now.getTime() + daysFromNow * DAY);
}

/**
 * The action plan.
 *
 * The property under test throughout: OVERDUE is worked out from the date, so
 * it is right the instant it is asked and needs no scheduled job to keep it
 * honest.
 */
describe("actionItemState", () => {
  it("marks an item past its date as overdue, with no job having run", () => {
    const state = actionItemState({ status: "OPEN", dueDate: at(-3) }, now);

    expect(state.state).toBe("OVERDUE");
    expect(state.daysOverdue).toBe(3);
    expect(state.open).toBe(true);
    // The STORED status is untouched — the derived state layers over it.
    expect(state.status).toBe("OPEN");
  });

  it("warns before an item slips rather than only after", () => {
    const state = actionItemState({ status: "OPEN", dueDate: at(DUE_SOON_DAYS - 1) }, now);

    expect(state.state).toBe("DUE_SOON");
    expect(state.daysOverdue).toBe(0);
  });

  it("leaves a distant item simply open", () => {
    const state = actionItemState({ status: "OPEN", dueDate: at(60) }, now);
    expect(state.state).toBe("OPEN");
  });

  it("keeps IN_PROGRESS visible when the date is still comfortable", () => {
    const state = actionItemState({ status: "IN_PROGRESS", dueDate: at(60) }, now);
    expect(state.state).toBe("IN_PROGRESS");
  });

  it("still flags IN_PROGRESS work that has gone past its date", () => {
    // Started is not finished. An item somebody began and then let slip is
    // exactly what a follow-up queue exists to surface.
    const state = actionItemState({ status: "IN_PROGRESS", dueDate: at(-1) }, now);
    expect(state.state).toBe("OVERDUE");
  });

  it("never calls a finished item overdue, however late it was closed", () => {
    // "You closed this late" is a different report from "this is outstanding".
    // Conflating them fills the follow-up queue with completed work.
    for (const status of ["DONE", "CANCELLED"]) {
      const state = actionItemState({ status, dueDate: at(-90) }, now);
      expect(state.state).toBe(status);
      expect(state.daysOverdue).toBe(0);
      expect(state.open).toBe(false);
    }
  });

  it("handles an item with no due date", () => {
    const state = actionItemState({ status: "OPEN", dueDate: null }, now);

    expect(state.state).toBe("OPEN");
    expect(state.daysUntilDue).toBeNull();
    expect(state.daysOverdue).toBe(0);
    expect(state.open).toBe(true);
  });

  it("is not overdue on the due date itself", () => {
    // A day is a day. Something due today has until the end of it.
    const state = actionItemState({ status: "OPEN", dueDate: at(0) }, now);
    expect(state.state).toBe("DUE_SOON");
    expect(state.daysOverdue).toBe(0);
  });
});

describe("the follow-up queue", () => {
  it("counts what is outstanding without counting finished work", () => {
    const summary = actionPlanSummary([
      actionItemState({ status: "OPEN", dueDate: at(-10) }, now),
      actionItemState({ status: "OPEN", dueDate: at(2) }, now),
      actionItemState({ status: "IN_PROGRESS", dueDate: at(40) }, now),
      actionItemState({ status: "DONE", dueDate: at(-40) }, now),
      actionItemState({ status: "CANCELLED", dueDate: at(-5) }, now)
    ]);

    expect(summary.total).toBe(5);
    expect(summary.open).toBe(3);
    expect(summary.overdue).toBe(1);
    expect(summary.dueSoon).toBe(1);
    expect(summary.done).toBe(1);
    expect(summary.worstDaysOverdue).toBe(10);
  });

  it("reports nothing overdue when everything is in hand", () => {
    const summary = actionPlanSummary([
      actionItemState({ status: "OPEN", dueDate: at(30) }, now),
      actionItemState({ status: "DONE", dueDate: at(-1) }, now)
    ]);

    expect(summary.overdue).toBe(0);
    expect(summary.worstDaysOverdue).toBe(0);
  });

  it("puts the longest-outstanding item first", () => {
    // An agent opening their queue should meet the thing that has been waiting
    // longest, not the newest thing added.
    const items = [
      actionItemState({ status: "OPEN", dueDate: at(5) }, now),
      actionItemState({ status: "OPEN", dueDate: at(-20) }, now),
      actionItemState({ status: "OPEN", dueDate: null }, now),
      actionItemState({ status: "DONE", dueDate: at(-30) }, now),
      actionItemState({ status: "OPEN", dueDate: at(-2) }, now)
    ].sort(byUrgency);

    expect(items.map((i) => i.daysUntilDue)).toEqual([-20, -2, 5, null, -30]);
    // Closed work sinks below everything still open, including undated work.
    expect(items.at(-1)!.open).toBe(false);
  });
});

describe("mentorship ratings", () => {
  it("accepts only whole scores from 1 to 5", () => {
    expect([1, 2, 3, 4, 5].every(isValidMentorshipRating)).toBe(true);
    for (const bad of [0, 6, -1, 3.5, Number.NaN, Number.POSITIVE_INFINITY]) {
      expect(isValidMentorshipRating(bad)).toBe(false);
    }
  });

  it("averages across dimensions", () => {
    expect(averageRating([5, 4, 4])).toBe(4.33);
    expect(averageRating([3])).toBe(3);
  });

  it("returns null when nobody rated, rather than zero", () => {
    // Zero is a score. A group that was never asked must not sit alongside one
    // that was rated badly — that distinction is the whole value of the number.
    expect(averageRating([])).toBeNull();
    expect(averageRating([0, 9, 2.5])).toBeNull();
  });

  it("ignores invalid scores mixed in with valid ones", () => {
    expect(averageRating([5, 0, 4])).toBe(4.5);
  });
});
