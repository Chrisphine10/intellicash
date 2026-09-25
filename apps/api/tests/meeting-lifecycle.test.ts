import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { planUpcomingMeetings, sendDueReminders } from "../src/services/meeting-reminder-service";
import { encryptCredentials } from "../src/services/integration-credentials";

const app = createApp();
const TUJIJENGE = "IWL-KBU-0001";

// Far enough ahead that nothing the seed made is inside any reminder window.
// 2031-03-06 is a Thursday.
const THURSDAY_MORNING = new Date("2031-03-06T06:00:00.000Z"); // 09:00 Nairobi
const hoursBefore = (at: Date, hours: number) => new Date(at.getTime() - hours * 3600 * 1000);

async function adminAgent() {
  const agent = request.agent(app);
  await agent
    .post("/api/v1/auth/login")
    .send({ email: "admin@intellicash.co.ke", password: "IntellicashDemo#2026" })
    .expect(200);
  return agent;
}

async function schedule(agent: ReturnType<typeof request.agent>, groupId: string, scheduledAt: string, title = "Planned meeting") {
  const response = await agent
    .post(`/api/v1/groups/${groupId}/meetings`)
    .send({ title, scheduledAt })
    .expect(201);
  return response.body.data as { id: string; status: string; source: string };
}

async function reminderTexts(meetingId: string) {
  return prisma.smsBroadcast.count({ where: { kind: "MEETING_REMINDER", meetingId } });
}

describe("meetings start only when a person starts them", () => {
  let admin: ReturnType<typeof request.agent>;
  let groupId: string;

  beforeAll(async () => {
    await seedDatabase();
    // A configured provider, so reminder texts are logged; every send below
    // passes networkEnabled: false, so nothing leaves the machine.
    const credentials = encryptCredentials({
      BONGA_SMS_CLIENT_ID: "1120",
      BONGA_SMS_API_KEY: "api-key-demo",
      BONGA_SMS_API_SECRET: "api-secret-demo",
      BONGA_SMS_SERVICE_ID: "5843"
    });
    await prisma.integrationConfig.upsert({
      where: { provider: "BONGA_SMS" },
      create: {
        provider: "BONGA_SMS",
        displayName: "Bonga SMS",
        requiredEnvJson: JSON.stringify([
          "BONGA_SMS_CLIENT_ID",
          "BONGA_SMS_API_KEY",
          "BONGA_SMS_API_SECRET",
          "BONGA_SMS_SERVICE_ID"
        ]),
        credentialsJson: credentials
      },
      update: { credentialsJson: credentials }
    });
    admin = await adminAgent();
    const group = await prisma.group.findFirstOrThrow({ where: { code: TUJIJENGE } });
    groupId = group.id;
  }, 60000);

  it("saves a structured schedule and keeps the readable day for older screens", async () => {
    const response = await admin
      .put(`/api/v1/groups/${groupId}/meeting-schedule`)
      .send({ frequency: "WEEKLY", days: [5, 5, 4], time: "14:00" })
      .expect(200);
    expect(response.body.data.meetingDays).toEqual([4, 5]);
    expect(response.body.data.meetingDay).toBe("Thursday, Friday");

    await admin
      .put(`/api/v1/groups/${groupId}/meeting-schedule`)
      .send({ frequency: "WEEKLY", days: [4], time: "2pm" })
      .expect(400);
  });

  it("puts each meeting day of the coming week on the calendar as SCHEDULED, once, and never opens it", async () => {
    // One plan per run and per group: runs repeat until the week is covered,
    // then add nothing.
    const runs = [];
    for (let i = 0; i < 4; i++) runs.push(await planUpcomingMeetings(THURSDAY_MORNING));
    expect(runs.slice(-2)).toEqual([0, 0]);

    const planned = await prisma.meeting.findMany({
      where: { groupId, source: "AUTO_SCHEDULE" },
      orderBy: { scheduledAt: "asc" }
    });
    // Thursday and Friday 14:00 Nairobi (the schedule saved above is Thu + Fri).
    expect(planned.map((m) => m.scheduledAt.toISOString())).toEqual([
      "2031-03-06T11:00:00.000Z",
      "2031-03-07T11:00:00.000Z"
    ]);
    expect(planned.every((m) => m.status === "SCHEDULED" && m.openedAt === null)).toBe(true);

    // Hours after its time, still nobody started it: it stays SCHEDULED.
    await planUpcomingMeetings(new Date("2031-03-06T15:00:00.000Z"));
    const later = await prisma.meeting.findUniqueOrThrow({ where: { id: planned[0]!.id } });
    expect(later.status).toBe("SCHEDULED");
  });

  it("reminds the day before and two hours before, each once, and not after the start", async () => {
    const meeting = await prisma.meeting.findFirstOrThrow({
      where: { groupId, source: "AUTO_SCHEDULE" },
      orderBy: { scheduledAt: "asc" }
    });
    const start = meeting.scheduledAt;
    const offline = { networkEnabled: false };

    // Two hours ahead of a Thursday meeting planned on Thursday morning is
    // the H2 window; move to the evening before for H24 first.
    await sendDueReminders(hoursBefore(start, 20), offline);
    await sendDueReminders(hoursBefore(start, 19), offline);
    expect(await prisma.meetingReminder.count({ where: { meetingId: meeting.id, kind: "H24" } })).toBe(1);

    await sendDueReminders(hoursBefore(start, 1), offline);
    await sendDueReminders(hoursBefore(start, 0.5), offline);
    expect(await prisma.meetingReminder.count({ where: { meetingId: meeting.id, kind: "H2" } })).toBe(1);
    expect(await reminderTexts(meeting.id)).toBe(2);

    const bell = await prisma.notification.count({ where: { type: "MEETING_REMINDER" } });
    expect(bell).toBeGreaterThan(0);

    await sendDueReminders(new Date(start.getTime() + 60_000), offline);
    expect(await prisma.meetingReminder.count({ where: { meetingId: meeting.id } })).toBe(2);
  });

  it("gives a moved meeting fresh reminders", async () => {
    const meeting = await schedule(admin, groupId, "2031-04-10T08:00:00.000Z", "Moved meeting");
    await sendDueReminders(new Date("2031-04-09T12:00:00.000Z"), { networkEnabled: false });
    await admin
      .patch(`/api/v1/groups/${groupId}/meetings/${meeting.id}`)
      .send({ scheduledAt: "2031-04-11T08:00:00.000Z" })
      .expect(200);
    await sendDueReminders(new Date("2031-04-10T12:00:00.000Z"), { networkEnabled: false });
    expect(await prisma.meetingReminder.count({ where: { meetingId: meeting.id, kind: "H24" } })).toBe(2);
  });

  it("an official cancels a meeting that did not happen; it gets no reminders and its day is not re-planned", async () => {
    const meeting = await prisma.meeting.findFirstOrThrow({
      where: { groupId, source: "AUTO_SCHEDULE" },
      orderBy: { scheduledAt: "asc" }
    });
    const cancelled = await admin
      .post(`/api/v1/groups/${groupId}/meetings/${meeting.id}/cancel`)
      .send({ reason: "Rain - nobody came" })
      .expect(200);
    expect(cancelled.body.data.status).toBe("CANCELLED");
    expect(cancelled.body.data.cancelReason).toBe("Rain - nobody came");

    // Idempotent.
    await admin
      .post(`/api/v1/groups/${groupId}/meetings/${meeting.id}/cancel`)
      .send({ reason: "Rain - nobody came" })
      .expect(200);

    await planUpcomingMeetings(THURSDAY_MORNING);
    expect(
      await prisma.meeting.count({
        where: { groupId, scheduledAt: meeting.scheduledAt, status: { not: "CANCELLED" } }
      })
    ).toBe(0);

    // Cannot be opened afterwards.
    await admin
      .post(`/api/v1/groups/${groupId}/meetings/${meeting.id}/open`)
      .send({ gpsCompliant: false, keySubmissions: [] })
      .expect(400);
  });

  it("refuses to cancel a meeting that has attendance or money in it", async () => {
    const held = await prisma.meeting.findFirstOrThrow({
      where: { groupId, status: { in: ["SCHEDULED", "KEY_UNLOCK_PENDING"] }, attendance: { some: {} } }
    }).catch(() => null);
    const meeting = held ?? (await schedule(admin, groupId, "2031-05-01T08:00:00.000Z"));
    if (!held) {
      const member = await prisma.member.findFirstOrThrow({ where: { groupId, status: "ACTIVE" } });
      await prisma.attendance.create({ data: { meetingId: meeting.id, memberId: member.id, status: "PRESENT" } });
    }
    const response = await admin
      .post(`/api/v1/groups/${groupId}/meetings/${meeting.id}/cancel`)
      .send({ reason: "Trying to hide it" })
      .expect(409);
    expect(response.body.error.code).toBe("MEETING_HAS_RECORDS");
  });

  it("a phone that starts a meeting adopts the day's scheduled one instead of duplicating it", async () => {
    const planned = await schedule(admin, groupId, "2031-06-05T11:00:00.000Z", "Planned for the 5th");
    const adopted = await admin
      .post(`/api/v1/groups/${groupId}/meetings`)
      .send({ title: "Meeting #9", scheduledAt: "2031-06-05T07:30:00.000Z", adoptScheduled: true, source: "PHONE" })
      .expect(200);
    expect(adopted.body.data.id).toBe(planned.id);

    // A different day makes a new one, marked as held on a phone.
    const fresh = await admin
      .post(`/api/v1/groups/${groupId}/meetings`)
      .send({ title: "Meeting #10", scheduledAt: "2031-06-12T07:30:00.000Z", adoptScheduled: true, source: "PHONE" })
      .expect(201);
    expect(fresh.body.data.id).not.toBe(planned.id);
    expect(fresh.body.data.source).toBe("PHONE");
  });

  it("the phone's start and close move the server's status, safely on replay, never backwards", async () => {
    const meeting = await schedule(admin, groupId, "2031-07-03T11:00:00.000Z");
    const url = `/api/v1/groups/${groupId}/meetings/${meeting.id}/phone-lifecycle`;

    const started = await admin.post(url).send({ event: "STARTED", at: "2031-07-03T11:05:00.000Z" }).expect(200);
    expect(started.body.data.status).toBe("IN_PROGRESS");
    const replay = await admin.post(url).send({ event: "STARTED", at: "2031-07-03T11:05:00.000Z" }).expect(200);
    expect(replay.body.data.status).toBe("IN_PROGRESS");

    const closed = await admin.post(url).send({ event: "CLOSED", at: "2031-07-03T13:00:00.000Z" }).expect(200);
    expect(closed.body.data.status).toBe("SEALED");
    expect(closed.body.data.closedAt).toBeTruthy();

    const late = await admin.post(url).send({ event: "STARTED", at: "2031-07-03T11:05:00.000Z" }).expect(200);
    expect(late.body.data.status).toBe("SEALED");
  });

  it("a phone cannot start a cancelled meeting", async () => {
    const meeting = await schedule(admin, groupId, "2031-08-07T11:00:00.000Z");
    await admin
      .post(`/api/v1/groups/${groupId}/meetings/${meeting.id}/cancel`)
      .send({ reason: "Public holiday" })
      .expect(200);
    const response = await admin
      .post(`/api/v1/groups/${groupId}/meetings/${meeting.id}/phone-lifecycle`)
      .send({ event: "STARTED", at: new Date().toISOString() })
      .expect(409);
    expect(response.body.error.code).toBe("MEETING_CANCELLED");
  });

  it("a changed schedule withdraws the planner's future plans, never a person's", async () => {
    const future = new Date(Date.now() + 3 * 24 * 3600 * 1000);
    const auto = await prisma.meeting.create({
      data: { groupId, title: "Planned from old days", status: "SCHEDULED", source: "AUTO_SCHEDULE", scheduledAt: future }
    });
    const manual = await schedule(admin, groupId, future.toISOString(), "Planned by a person");

    // Same schedule again: nothing withdrawn.
    const current = await prisma.group.findUniqueOrThrow({ where: { id: groupId } });
    await admin
      .put(`/api/v1/groups/${groupId}/meeting-schedule`)
      .send({ frequency: current.meetingFrequency, days: JSON.parse(current.meetingDays!), time: current.meetingTime })
      .expect(200);
    expect(await prisma.meeting.count({ where: { id: auto.id } })).toBe(1);

    await admin
      .put(`/api/v1/groups/${groupId}/meeting-schedule`)
      .send({ frequency: "WEEKLY", days: [2], time: "09:00" })
      .expect(200);
    expect(await prisma.meeting.count({ where: { id: auto.id } })).toBe(0);
    expect(await prisma.meeting.count({ where: { id: manual.id } })).toBe(1);
  });
});
