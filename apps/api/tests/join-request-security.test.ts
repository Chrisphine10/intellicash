import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import bcrypt from "bcryptjs";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();
const TUJIJENGE = "IWL-KBU-0001";

async function login(email: string, password = "IntellicashDemo#2026") {
  const agent = request.agent(app);
  await agent.post("/api/v1/auth/login").send({ email, password }).expect(200);
  return agent;
}

async function pendingRequestFor(groupId: string, name: string, phone: string) {
  const email = `${phone}@probe.example.com`;
  await prisma.user.deleteMany({ where: { email } });
  const user = await prisma.user.create({
    data: {
      name,
      email,
      phone,
      passwordHash: await bcrypt.hash("Probe#2026", 12),
      role: "MEMBER"
    }
  });
  return prisma.groupJoinRequest.create({
    data: { groupId, userId: user.id, requestedName: name, phone, status: "PENDING" }
  });
}

describe("who may answer a request to join", () => {
  let groupId: string;

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirstOrThrow({ where: { code: TUJIJENGE } });
    groupId = group.id;
  }, 60000);

  it("keeps the queue away from the group's field agent", async () => {
    // VILLAGE_AGENT holds members:write and groupScopeForUser gives it every
    // group on the caseload, so a permission-only gate would let one agent
    // account approve logins across every group they support.
    const agent = await login("agent@intellicash.co.ke");
    const res = await agent.get(`/api/v1/groups/${groupId}/join-requests`).expect(403);
    expect(res.body.error.code).toBe("NOT_A_GROUP_OFFICIAL");
  });

  it("will not let a field agent decide either", async () => {
    const req = await pendingRequestFor(groupId, "Probe One", "254795000101");
    const agent = await login("agent@intellicash.co.ke");
    await agent
      .post(`/api/v1/groups/${groupId}/join-requests/${req.id}/decision`)
      .send({ decision: "APPROVE" })
      .expect(403);
  });

  it("lets the group's own account decide", async () => {
    const official = await login("group@intellicash.co.ke");
    await official.get(`/api/v1/groups/${groupId}/join-requests`).expect(200);
  });
});

describe("claiming someone else's savings by phone", () => {
  let groupId: string;
  let rosterMemberId: string;
  let rosterMemberName: string;
  let requestId: string;

  beforeAll(async () => {
    const group = await prisma.group.findFirstOrThrow({ where: { code: TUJIJENGE } });
    groupId = group.id;
    const roster = await prisma.member.findFirstOrThrow({
      where: { groupId, phone: { not: "" } }
    });
    rosterMemberId = roster.id;
    rosterMemberName = roster.fullName;

    // Nothing verifies the phone typed at registration, so anyone can claim a
    // roster member's number and look entirely legitimate to an approver.
    const digits = roster.phone.replace(/\D/g, "");
    const normalised = digits.startsWith("254") ? digits : `254${digits.replace(/^0/, "")}`;
    const req = await pendingRequestFor(groupId, "Not That Person", normalised);
    requestId = req.id;
  }, 60000);

  it("warns the official whose records accepting would hand over", async () => {
    const official = await login("group@intellicash.co.ke");
    const res = await official.get(`/api/v1/groups/${groupId}/join-requests`).expect(200);
    const row = res.body.data.find((r: any) => r.id === requestId);
    expect(row.willLinkToMemberId).toBe(rosterMemberId);
    expect(row.willLinkToMemberName).toBe(rosterMemberName);
  });

  it("refuses to hand over a passbook without explicit confirmation", async () => {
    const official = await login("group@intellicash.co.ke");
    const res = await official
      .post(`/api/v1/groups/${groupId}/join-requests/${requestId}/decision`)
      .send({ decision: "APPROVE" })
      .expect(409);
    expect(res.body.error.code).toBe("CONFIRM_EXISTING_MEMBER");
    expect(res.body.error.message).toContain(rosterMemberName);
  });

  it("refuses a confirmation for a different member than the one matched", async () => {
    const official = await login("group@intellicash.co.ke");
    const other = await prisma.member.findFirstOrThrow({
      where: { groupId, id: { not: rosterMemberId } }
    });
    await official
      .post(`/api/v1/groups/${groupId}/join-requests/${requestId}/decision`)
      .send({ decision: "APPROVE", confirmMemberId: other.id })
      .expect(409);
  });

  it("leaves the request answerable after a refusal", async () => {
    const official = await login("group@intellicash.co.ke");
    const res = await official.get(`/api/v1/groups/${groupId}/join-requests`).expect(200);
    expect(res.body.data.some((r: any) => r.id === requestId)).toBe(true);
  });
});

describe("two officials answering at once", () => {
  it("produces one member, not one per approval", async () => {
    const group = await prisma.group.findFirstOrThrow({ where: { code: TUJIJENGE } });
    const phone = "254794000777";
    await prisma.member.deleteMany({ where: { groupId: group.id, phone } });
    const req = await pendingRequestFor(group.id, "Concurrent Person", phone);

    const official = await login("group@intellicash.co.ke");
    const fire = () =>
      official
        .post(`/api/v1/groups/${group.id}/join-requests/${req.id}/decision`)
        .send({ decision: "APPROVE" });

    const results = await Promise.all([fire(), fire(), fire()]);
    const accepted = results.filter((r) => r.status === 200);
    const refused = results.filter((r) => r.status === 409);
    expect(accepted).toHaveLength(1);
    expect(refused).toHaveLength(2);

    // The part that actually corrupts a VSLA: a duplicate on the roster
    // inflates member counts, quorum and the share-out denominator.
    const members = await prisma.member.findMany({ where: { groupId: group.id, phone } });
    expect(members).toHaveLength(1);
    const only = members[0];
    if (!only) throw new Error("expected exactly one roster row");
    const links = await prisma.userMembership.count({ where: { memberId: only.id } });
    expect(links).toBe(1);
  }, 30000);
});

describe("login lookups by phone", () => {
  it("does not scan the user table for a meaningless number", async () => {
    // `contains: ""` would otherwise match every row and hydrate every
    // password hash, unauthenticated and repeatable.
    for (const junk of ["-", "7", "", "abc"]) {
      const res = await request(app)
        .post("/api/v1/auth/login")
        .send({ phone: junk, password: "whatever" });
      // Refused either by the schema (400) or by the credential check (401);
      // what matters is that neither path reaches a table-wide LIKE '%%'.
      expect([400, 401]).toContain(res.status);
      expect(res.body.data).toBeUndefined();
    }
  });
});
