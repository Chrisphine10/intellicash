import { describe, expect, it } from "vitest";
import {
  dueReminder,
  meetingReminderText,
  nairobiDayBounds,
  nextMeetingSlot,
  readMeetingSchedule,
  type MeetingSchedule
} from "../src/domain/meeting-schedule";

// Nairobi is UTC+3: 14:00 there is 11:00Z.
const at = (iso: string) => new Date(iso);
const weekly = (days: number[], time = "14:00"): MeetingSchedule => ({ frequency: "WEEKLY", days, time });

describe("next meeting from a group's schedule", () => {
  // 2026-09-24 is a Thursday.
  const thursdayMorning = at("2026-09-24T06:00:00.000Z"); // 09:00 Nairobi

  it("offers later the same day when the meeting time has not passed", () => {
    expect(nextMeetingSlot(weekly([4]), thursdayMorning)?.toISOString()).toBe("2026-09-24T11:00:00.000Z");
  });

  it("moves to next week once today's meeting time has passed", () => {
    const afternoon = at("2026-09-24T12:00:00.000Z"); // 15:00 Nairobi
    expect(nextMeetingSlot(weekly([4]), afternoon)?.toISOString()).toBe("2026-10-01T11:00:00.000Z");
  });

  it("takes the nearest of several meeting days", () => {
    // Monday and Saturday: from Thursday the next is Saturday the 26th.
    expect(nextMeetingSlot(weekly([1, 6]), thursdayMorning)?.toISOString()).toBe("2026-09-26T11:00:00.000Z");
  });

  it("never offers a day the group already met", () => {
    const heldThisMorning = at("2026-09-24T05:00:00.000Z");
    expect(nextMeetingSlot(weekly([4]), thursdayMorning, heldThisMorning)?.toISOString()).toBe(
      "2026-10-01T11:00:00.000Z"
    );
  });

  it("fortnightly skips the week after the last meeting", () => {
    const lastMet = at("2026-09-17T11:00:00.000Z"); // Thursday a week ago
    const schedule: MeetingSchedule = { frequency: "BIWEEKLY", days: [4], time: "14:00" };
    expect(nextMeetingSlot(schedule, thursdayMorning, lastMet)?.toISOString()).toBe("2026-10-01T11:00:00.000Z");
  });

  it("monthly is the chosen weekday in the first week of the month", () => {
    const schedule: MeetingSchedule = { frequency: "MONTHLY", days: [1], time: "10:30" };
    // First Monday of October 2026 is the 5th; 10:30 Nairobi = 07:30Z.
    expect(nextMeetingSlot(schedule, thursdayMorning)?.toISOString()).toBe("2026-10-05T07:30:00.000Z");
  });

  it("uses the Nairobi day, not the UTC day, near midnight", () => {
    // 22:30Z Wednesday is 01:30 Thursday in Nairobi, so Thursday is "today".
    expect(nextMeetingSlot(weekly([4]), at("2026-09-23T22:30:00.000Z"))?.toISOString()).toBe(
      "2026-09-24T11:00:00.000Z"
    );
  });
});

describe("reading a stored schedule", () => {
  it("rejects anything incomplete rather than guessing", () => {
    expect(readMeetingSchedule({ meetingFrequency: "WEEKLY", meetingDays: "[4]", meetingTime: "14:00" })).toEqual(
      weekly([4])
    );
    expect(readMeetingSchedule({ meetingFrequency: "WEEKLY", meetingDays: "[]", meetingTime: "14:00" })).toBeNull();
    expect(readMeetingSchedule({ meetingFrequency: "DAILY", meetingDays: "[4]", meetingTime: "14:00" })).toBeNull();
    expect(readMeetingSchedule({ meetingFrequency: "WEEKLY", meetingDays: "[4]", meetingTime: "2pm" })).toBeNull();
    expect(readMeetingSchedule({ meetingFrequency: "WEEKLY", meetingDays: "not json", meetingTime: "14:00" })).toBeNull();
  });
});

describe("when a reminder is due", () => {
  const meeting = at("2026-09-25T11:00:00.000Z");
  const before = (hours: number) => new Date(meeting.getTime() - hours * 3600 * 1000);

  it("sends nothing more than a day ahead", () => {
    expect(dueReminder(meeting, before(25))).toBeNull();
  });

  it("sends the day-before reminder from 24 hours until 2 hours before", () => {
    expect(dueReminder(meeting, before(24))).toBe("H24");
    expect(dueReminder(meeting, before(3))).toBe("H24");
  });

  it("sends the two-hour reminder until the start", () => {
    expect(dueReminder(meeting, before(2))).toBe("H2");
    expect(dueReminder(meeting, before(0.1))).toBe("H2");
  });

  it("sends nothing once the meeting time has come - a missed meeting is for an official to cancel", () => {
    expect(dueReminder(meeting, meeting)).toBeNull();
    expect(dueReminder(meeting, new Date(meeting.getTime() + 3600 * 1000))).toBeNull();
  });
});

describe("the reminder text", () => {
  it("says when and where in Nairobi time", () => {
    const text = meetingReminderText({
      groupName: "Tujijenge",
      scheduledAt: at("2026-09-25T11:00:00.000Z"),
      kind: "H24",
      location: "Kibera chief's camp"
    });
    expect(text).toBe("Reminder: Tujijenge meeting on Friday at 2:00 pm at Kibera chief's camp. Please attend.");
    expect(text.length).toBeLessThanOrEqual(160);
  });

  it("says today for the two-hour reminder", () => {
    expect(
      meetingReminderText({ groupName: "Umoja", scheduledAt: at("2026-09-25T06:00:00.000Z"), kind: "H2" })
    ).toBe("Reminder: Umoja meeting today at 9:00 am. Please attend.");
  });
});

describe("Nairobi day bounds", () => {
  it("covers midnight to midnight Nairobi", () => {
    const { start, end } = nairobiDayBounds(at("2026-09-24T11:00:00.000Z"));
    expect(start.toISOString()).toBe("2026-09-23T21:00:00.000Z");
    expect(end.toISOString()).toBe("2026-09-24T21:00:00.000Z");
  });
});
