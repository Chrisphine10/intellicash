import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * The mobile app parses these payloads in
 * `lib/data/models/remote/agent_report.dart` and `member_passbook.dart`.
 * Renaming a field on this side without changing those would leave an agent
 * or a group reading a report of zeroes, with nothing failing loudly.
 */
describe("agent caseload report contract", () => {
  let body: any;

  beforeAll(async () => {
    await seedDatabase();

    // The seed ships a village agent login with groups on her caseload.
    const client = request.agent(app);
    await client
      .post("/api/v1/auth/login")
      .send({ email: "agent@intellicash.co.ke", password: "IntellicashDemo#2026" })
      .expect(200);
    const res = await client.get("/api/v1/reports/agent").expect(200);
    body = res.body.data;
  }, 60000);

  it("carries the caseload summary the header shows", () => {
    expect(body.generatedAt).toBeTruthy();
    expect(body.agent.name).toBeTypeOf("string");
    for (const key of ["groups", "rated", "needSupport", "totalMembers"]) {
      expect(body.summary[key]).toBeTypeOf("number");
    }
  });

  it("gives each group the fields the caseload list renders", () => {
    expect(body.groups.length).toBeGreaterThan(0);
    for (const group of body.groups) {
      expect(group.id).toBeTypeOf("string");
      expect(group.name).toBeTypeOf("string");
      expect(group.memberCount).toBeTypeOf("number");
      expect(group.needsSupport).toBeTypeOf("boolean");
      // Either a rating with a band and score, or an explicit null.
      if (group.creditRating !== null) {
        expect(group.creditRating.band).toBeTypeOf("string");
        expect(group.creditRating.score).toBeTypeOf("number");
        expect(group.creditRating.rated).toBeTypeOf("boolean");
      }
    }
  });

  it("treats a group nobody has rated as one needing a visit", () => {
    // The app relies on this rather than deciding for itself, so that the
    // handset and the back office never disagree about who needs support.
    for (const group of body.groups) {
      if (group.creditRating === null || group.creditRating.rated === false) {
        expect(group.needsSupport).toBe(true);
      }
    }
  });
});

describe("member report contract", () => {
  let body: any;

  beforeAll(async () => {
    const group = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0001" } });
    const member = await prisma.member.findFirstOrThrow({ where: { groupId: group.id } });
    const client = request.agent(app);
    await client
      .post("/api/v1/auth/login")
      .send({ email: "group@intellicash.co.ke", password: "IntellicashDemo#2026" })
      .expect(200);
    const res = await client.get(`/api/v1/reports/member/${member.id}`).expect(200);
    body = res.body.data;
  }, 60000);

  it("carries the summary a member report renders", () => {
    for (const key of [
      "sharesCents",
      "socialCents",
      "finesCents",
      "totalPaidInCents",
      "loansReceivedCents",
      "loansRepaidCents",
      "loanOutstandingCents"
    ]) {
      expect(body.summary[key]).toBeTypeOf("number");
      expect(Number.isInteger(body.summary[key])).toBe(true);
    }
  });

  it("never reports a negative amount outstanding", () => {
    // Overpayment must read as nothing owed, not as a negative debt.
    expect(body.summary.loanOutstandingCents).toBeGreaterThanOrEqual(0);
  });

  it("carries the attendance the report shows", () => {
    expect(body.attendance.present).toBeTypeOf("number");
    expect(body.attendance.total).toBeTypeOf("number");
  });
});
