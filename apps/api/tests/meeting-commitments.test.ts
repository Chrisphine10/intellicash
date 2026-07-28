import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

async function login(email: string) {
  const agent = request.agent(app);
  await agent
    .post("/api/v1/auth/login")
    .send({ email, password: "IntellicashDemo#2026" })
    .expect(200);
  return agent;
}

/**
 * Store purchases agreed during a meeting are tied to that sitting, so the
 * minute book can account for money the group committed while it sat.
 */
describe("store purchases tied to the meeting that agreed them", () => {
  let groupId: string;
  let meetingId: string;
  let productId: string;
  let programmeId: string;

  beforeAll(async () => {
    await seedDatabase();

    const group = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0001" } });
    groupId = group.id;
    const meeting = await prisma.meeting.findFirstOrThrow({ where: { groupId } });
    meetingId = meeting.id;

    const product = await prisma.storeProduct.findFirstOrThrow({
      include: { programmeLinks: true }
    });
    productId = product.id;
    programmeId =
      product.programmeLinks[0]?.programmeId ??
      (await prisma.programme.findFirstOrThrow()).id;
  }, 60000);

  async function buy(agent: request.Agent, body: Record<string, unknown>) {
    return agent.post("/api/v1/intelli-store/credit-requests").send({
      productId,
      programmeId,
      customerName: "Tujijenge Women VSLA",
      customerEmail: "group@intellicash.co.ke",
      phoneNumber: "254700000201",
      groupId,
      quantity: 1,
      ...body
    });
  }

  it("records the sitting a group purchase was agreed in", async () => {
    const group = await login("group@intellicash.co.ke");
    const res = await buy(group, { meetingId });
    expect([200, 201]).toContain(res.status);
    expect(res.body.data.meetingId).toBe(meetingId);
  });

  it("refuses a meeting belonging to a different group", async () => {
    const group = await login("group@intellicash.co.ke");
    const otherMeeting = await prisma.meeting.findFirst({
      where: { groupId: { not: groupId } }
    });
    if (!otherMeeting) return; // seed has no second group's meeting to borrow

    const res = await buy(group, { meetingId: otherMeeting.id });
    expect(res.status).toBe(404);
    expect(res.body.error.code).toBe("MEETING_NOT_FOUND");
  });

  it("refuses a meeting that does not exist at all", async () => {
    const group = await login("group@intellicash.co.ke");
    const res = await buy(group, { meetingId: "cmr000000000000000000000" });
    expect(res.status).toBe(404);
    expect(res.body.error.code).toBe("MEETING_NOT_FOUND");
  });

  it("still allows a purchase with no meeting at all", async () => {
    const group = await login("group@intellicash.co.ke");
    const res = await buy(group, {});
    expect([200, 201]).toContain(res.status);
    expect(res.body.data.meetingId).toBeNull();
  });

  it("shows the group what its sitting committed to", async () => {
    const group = await login("group@intellicash.co.ke");
    await buy(group, { meetingId });

    const detail = await group
      .get(`/api/v1/groups/${groupId}/meetings/${meetingId}`)
      .expect(200);

    const commitments = detail.body.data.storeCreditRequests;
    expect(Array.isArray(commitments)).toBe(true);
    expect(commitments.length).toBeGreaterThan(0);
    // Enough to read the minute book entry without another round trip.
    expect(commitments[0].product.name).toBeTruthy();
    expect(commitments[0].requestedAmountCents).toBeGreaterThan(0);
    // The linkage exists for external loans too, and is surfaced the same way.
    expect(detail.body.data.externalLoanApplications).toBeDefined();
  });

  it("does not show one member another buyer's commitments", async () => {
    const member = await login("member@intellicash.co.ke");
    const detail = await member
      .get(`/api/v1/groups/${groupId}/meetings/${meetingId}`)
      .expect(200);

    // Every purchase above was made by the group account, so a member — who
    // is scoped to their own records here, as they are for attendance and the
    // ledger — should see none of them.
    expect(detail.body.data.storeCreditRequests).toEqual([]);
  });
});
