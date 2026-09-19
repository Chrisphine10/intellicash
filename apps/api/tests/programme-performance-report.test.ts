import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * The programme performance pack a partner reads.
 *
 * The data-protection properties are the point of these tests: they are the
 * kind that a later "just add the member column" change removes without any
 * other test noticing.
 */

async function signIn(role: string) {
  const account = demoAccounts.find((entry) => entry.role === role)!;
  const response = await request(app).post("/api/v1/auth/login").send({ phone: account.phone, password: demoPassword }).expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

describe("the programme performance report", () => {
  let partner: string[];
  let memberNames: string[];
  let memberPhones: string[];

  beforeAll(async () => {
    await seedDatabase();
    partner = await signIn("PARTNER_OFFICER");
    const members = await prisma.member.findMany({ select: { fullName: true, phone: true } });
    memberNames = members.map((member) => member.fullName);
    memberPhones = members.map((member) => member.phone).filter(Boolean) as string[];
  }, 120000);

  it("answers a partner with reach, performance, delivery and content", async () => {
    const response = await request(app).get("/api/v1/reports/programme-performance").set("Cookie", partner).expect(200);
    const report = response.body.data;
    expect(report.reach.groups).toBeGreaterThan(0);
    expect(report.performance.groups.length).toBe(report.reach.groups);
    expect(Array.isArray(report.delivery.cbts)).toBe(true);
    expect(Array.isArray(report.content.topics)).toBe(true);
    expect(report.dataProtection.statement).toMatch(/Data Protection Act/);
  });

  it("names no member and carries no phone number anywhere", async () => {
    const response = await request(app).get("/api/v1/reports/programme-performance").set("Cookie", partner).expect(200);
    const body = JSON.stringify(response.body);
    for (const name of memberNames) expect(body).not.toContain(name);
    for (const phone of memberPhones) expect(body).not.toContain(phone);
  });

  it("withholds money figures for a group too small to hide its members", async () => {
    const response = await request(app).get("/api/v1/reports/programme-performance").set("Cookie", partner).expect(200);
    const small = response.body.data.performance.groups.find((group: { activeMembers: number }) => group.activeMembers < 5);
    expect(small).toBeTruthy();
    expect(small.suppressed).toBe(true);
    expect(small.savingsCents).toBeNull();
    expect(small.loanBookCents).toBeNull();
  });

  it("never reports a loan-at-risk rate above 100%", async () => {
    const response = await request(app).get("/api/v1/reports/programme-performance").set("Cookie", partner).expect(200);
    for (const group of response.body.data.performance.groups) {
      if (group.par30Rate !== null) expect(group.par30Rate).toBeLessThanOrEqual(100);
    }
  });

  it("counts what a CBT delivered, by topic, from a real visit", async () => {
    const group = await prisma.group.findFirstOrThrow({
      where: { villageAgentId: { not: null } },
      select: { id: true, villageAgentId: true }
    });
    const visit = await prisma.groupVisit.create({
      data: {
        groupId: group.id,
        villageAgentId: group.villageAgentId,
        clientRequestId: `report-test-${Date.now()}`,
        startedAt: new Date(),
        withinGeofence: true,
        mentorship: {
          create: [
            { topicKeySnapshot: "record_keeping", topicTitleSnapshot: "Record keeping", durationMinutes: 40, notes: "Mary asked about the cash book" },
            { topicKeySnapshot: "loan_management", topicTitleSnapshot: "Loan management", durationMinutes: 30 }
          ]
        }
      },
      select: { id: true }
    });
    await prisma.visitMentorshipRating.createMany({
      data: [
        { visitId: visit.id, dimensionKeySnapshot: "clarity", score: 4, ratedByRole: "GROUP_REPRESENTATIVE" },
        // The agent's own rating never counts towards the group's verdict.
        { visitId: visit.id, dimensionKeySnapshot: "usefulness", score: 1, ratedByRole: "AGENT" }
      ]
    });

    const response = await request(app).get("/api/v1/reports/programme-performance").set("Cookie", partner).expect(200);
    const report = response.body.data;

    const cbt = report.delivery.cbts.find((row: { id: string }) => row.id === group.villageAgentId);
    expect(cbt.visits).toBeGreaterThanOrEqual(1);
    expect(cbt.mentorshipSessions).toBeGreaterThanOrEqual(2);
    expect(cbt.mentoringMinutes).toBeGreaterThanOrEqual(70);
    expect(cbt.groupRating).toBe(4);

    const recordKeeping = report.content.topics.find((row: { key: string }) => row.key === "record_keeping");
    expect(recordKeeping.sessions).toBeGreaterThanOrEqual(1);
    expect(recordKeeping.groupsReached).toBeGreaterThanOrEqual(1);

    // The session notes named a member; notes never leave the database.
    expect(JSON.stringify(response.body)).not.toContain("asked about the cash book");
  });

  it("counts a meeting as held when things were recorded in it, even if it was never opened", async () => {
    // A group that keeps its book on the phone records attendance and money
    // against a meeting without the server's open/seal steps, so the meeting
    // stays SCHEDULED. Counting only opened meetings reported an active group
    // as having held none, with a 0% attendance rate.
    const first = await request(app).get("/api/v1/reports/programme-performance").set("Cookie", partner).expect(200);
    const group = first.body.data.performance.groups.find((row: { meetingsScheduled: number }) => row.meetingsScheduled >= 0);
    const member = await prisma.member.findFirstOrThrow({ where: { groupId: group.id, status: "ACTIVE" }, select: { id: true } });
    const meeting = await prisma.meeting.create({
      data: { groupId: group.id, title: "Phone-recorded meeting", scheduledAt: new Date(), status: "SCHEDULED" }
    });
    const before = group.meetingsHeld as number;

    await prisma.attendance.create({ data: { meetingId: meeting.id, memberId: member.id, status: "PRESENT" } });

    const after = await request(app).get("/api/v1/reports/programme-performance").set("Cookie", partner).expect(200);
    const row = after.body.data.performance.groups.find((entry: { id: string }) => entry.id === group.id);
    expect(row.meetingsHeld).toBe(before + 1);
    expect(row.meetingsScheduled).toBe(group.meetingsScheduled + 1);
  });

  it("is not a member's report", async () => {
    const member = await signIn("MEMBER");
    const response = await request(app).get("/api/v1/reports/programme-performance").set("Cookie", member).expect(403);
    expect(response.body.error.message).toMatch(/programme staff and partners/);
  });

  it("explains a period that runs backwards", async () => {
    const response = await request(app)
      .get("/api/v1/reports/programme-performance?from=2026-09-30&to=2026-09-01")
      .set("Cookie", partner)
      .expect(400);
    expect(response.body.error.message).toMatch(/start of the period is after its end/);
  });
});

describe("the foundation report for partners", () => {
  it("gives ledger totals without saying which member paid", async () => {
    await seedDatabase();
    const partner = await signIn("PARTNER_OFFICER");
    const response = await request(app).get("/api/v1/reports/foundation").set("Cookie", partner).expect(200);
    const entries = response.body.data.ledgerEntries as Array<{ member: unknown; memberId: unknown }>;
    expect(entries.length).toBeGreaterThan(0);
    expect(entries.every((entry) => entry.member === null && entry.memberId === null)).toBe(true);
  }, 120000);

  it("still gives the field agent names — they serve those members", async () => {
    const agent = await signIn("VILLAGE_AGENT");
    const response = await request(app).get("/api/v1/reports/foundation").set("Cookie", agent).expect(200);
    const entries = response.body.data.ledgerEntries as Array<{ member: { fullName: string } | null }>;
    expect(entries.some((entry) => entry.member?.fullName)).toBe(true);
  });
});
