import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * The mobile app parses this payload in
 * `lib/data/models/remote/group_report.dart`. Renaming a field here without
 * changing that file would leave groups reading a report full of zeroes, with
 * nothing failing loudly — so the shape is pinned.
 */
describe("group report contract with the mobile app", () => {
  let body: any;

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0001" } });
    const agent = request.agent(app);
    await agent
      .post("/api/v1/auth/login")
      .send({ email: "group@intellicash.co.ke", password: "IntellicashDemo#2026" })
      .expect(200);
    const res = await agent.get(`/api/v1/reports/group/${group.id}`).expect(200);
    body = res.body.data;
  }, 60000);

  it("carries the fields the report screen reads", () => {
    expect(body.generatedAt).toBeTruthy();
    expect(body.group.meetingCount).toBeTypeOf("number");
    expect(body.meetings).toHaveProperty("attendanceRate");
  });

  it("breaks the ledger down by type with a cents total", () => {
    expect(Array.isArray(body.ledger)).toBe(true);
    for (const row of body.ledger) {
      expect(row).toHaveProperty("type");
      expect(row).toHaveProperty("direction");
      expect(row.totalCents).toBeTypeOf("number");
    }
    // The types the app sums by name.
    const types = body.ledger.map((r: { type: string }) => r.type);
    expect(types).toContain("SHARE_PURCHASE");
    expect(types).toContain("SOCIAL_CONTRIBUTION");
  });

  it("gives every member the per-person totals the rows need", () => {
    expect(body.members.length).toBeGreaterThan(0);
    for (const member of body.members) {
      expect(member.fullName).toBeTypeOf("string");
      expect(member.role).toBeTypeOf("string");
      expect(member.sharesCents).toBeTypeOf("number");
      expect(member.socialCents).toBeTypeOf("number");
      expect(member.loanDisbursementsCents).toBeTypeOf("number");
      expect(member.loanRepaymentsCents).toBeTypeOf("number");
    }
  });

  it("keeps money in integer cents, never a rounded float", () => {
    // The app divides by 100 exactly once; a float here would compound.
    for (const row of body.ledger) {
      expect(Number.isInteger(row.totalCents)).toBe(true);
    }
    for (const member of body.members) {
      expect(Number.isInteger(member.sharesCents)).toBe(true);
    }
  });
});
