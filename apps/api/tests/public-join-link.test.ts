import { afterAll, beforeAll, describe, expect, it } from "vitest";
import request from "supertest";
import bcrypt from "bcryptjs";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";

/**
 * A group's public invite link.
 *
 * The behaviour worth pinning is not that the happy path works - it is what the
 * link must NOT do. It must not admit anybody, it must not be guessable from
 * the group code, and it must not survive being rotated.
 */

const app = createApp();
const GROUP_CODE = "JOIN-LINK-TEST";
const OFFICIAL_EMAIL = "join-link-official@example.com";
const PASSWORD = "TestPassword#2026";

let groupId = "";
let agent: ReturnType<typeof request.agent>;

async function removeFixture() {
  const group = await prisma.group.findUnique({
    where: { code: GROUP_CODE },
    select: { id: true }
  });
  await prisma.user.deleteMany({
    where: { email: { in: [OFFICIAL_EMAIL, "254799123456@accounts.intellicash.app"] } }
  });
  if (!group) return;
  await prisma.groupJoinRequest.deleteMany({ where: { groupId: group.id } });
  await prisma.member.deleteMany({ where: { groupId: group.id } });
  await prisma.group.delete({ where: { id: group.id } });
}

async function currentToken() {
  const row = await prisma.group.findUniqueOrThrow({
    where: { id: groupId },
    select: { joinToken: true }
  });
  return row.joinToken;
}

describe("group invite link", () => {
  beforeAll(async () => {
    await removeFixture();
    const group = await prisma.group.create({
      data: { name: "Invite Link VSLA", code: GROUP_CODE, phase: "ACTIVE", county: "Kiambu" }
    });
    groupId = group.id;

    await prisma.user.create({
      data: {
        name: "Invite Official",
        email: OFFICIAL_EMAIL,
        phone: "254700999888",
        passwordHash: await bcrypt.hash(PASSWORD, 12),
        role: "GROUP_ACCOUNT",
        groupId
      }
    });

    agent = request.agent(app);
    const login = await agent
      .post("/api/v1/auth/login")
      .send({ email: OFFICIAL_EMAIL, password: PASSWORD });
    expect(login.status).toBe(200);
  }, 30000);

  afterAll(async () => {
    await removeFixture();
  });

  it("mints a link on first ask and keeps it stable afterwards", async () => {
    const first = await agent.get(`/api/v1/groups/${groupId}/join-link`);
    expect(first.status).toBe(200);
    expect(first.body.data.url).toContain(first.body.data.token);

    const second = await agent.get(`/api/v1/groups/${groupId}/join-link`);
    // A link that changed on every visit could never be printed on anything.
    expect(second.body.data.token).toBe(first.body.data.token);
  });

  it("does not build the link out of the group code", async () => {
    const token = await currentToken();

    // Codes read IWL-KBU-0001 and can be counted upwards. A link made from one
    // would let anybody walk the platform's whole group list.
    expect(token).not.toContain(GROUP_CODE);
    expect(token?.length ?? 0).toBeGreaterThanOrEqual(20);

    const byCode = await request(app).get(`/api/v1/public/join/${GROUP_CODE}`);
    expect(byCode.status).toBe(404);
  });

  it("shows a visitor the group's name and nothing about its money", async () => {
    const token = await currentToken();
    const response = await request(app).get(`/api/v1/public/join/${token}`);

    expect(response.status).toBe(200);
    expect(response.body.data.group.name).toBe("Invite Link VSLA");
    // An invite link is public. Everything it returns is public too, so the
    // roster, the balances and the member count stay out of it.
    const body = JSON.stringify(response.body);
    expect(body).not.toContain("balance");
    expect(body).not.toContain("members");
  });

  it("creates the account and a PENDING request, and admits nobody", async () => {
    const token = await currentToken();
    const response = await request(app)
      .post(`/api/v1/public/join/${token}`)
      .send({ name: "Faith Njeri", phone: "0799123456", password: "JoinMe#2026" });

    expect(response.status).toBe(201);
    expect(response.body.data.status).toBe("PENDING");

    const created = await prisma.groupJoinRequest.findFirstOrThrow({
      where: { groupId, requestedName: "Faith Njeri" },
      select: { status: true, phone: true, userId: true }
    });
    expect(created.status).toBe("PENDING");
    expect(created.phone).toBe("254799123456");

    // The whole point. A link that granted membership would hand the group's
    // book to anyone who forwarded it on WhatsApp.
    const membership = await prisma.userMembership.findFirst({
      where: { userId: created.userId, groupId }
    });
    expect(membership).toBeNull();
    const member = await prisma.member.findFirst({ where: { groupId } });
    expect(member).toBeNull();
  });

  it("refuses a second account on the same line, however it is written", async () => {
    const token = await currentToken();
    const response = await request(app)
      .post(`/api/v1/public/join/${token}`)
      // The same phone as above, written the international way.
      .send({ name: "Faith Again", phone: "+254 799 123 456", password: "JoinMe#2026" });

    expect(response.status).toBe(409);
    expect(response.body.error.code).toBe("ACCOUNT_EXISTS");
  });

  it("kills the old link when a new one is issued", async () => {
    const before = await currentToken();
    const rotated = await agent.post(`/api/v1/groups/${groupId}/join-link/rotate`);

    expect(rotated.status).toBe(200);
    expect(rotated.body.data.token).not.toBe(before);

    // A poster that ended up somewhere it should not have is now waste paper,
    // which is the only reason rotation exists.
    const dead = await request(app).get(`/api/v1/public/join/${before}`);
    expect(dead.status).toBe(404);

    const live = await request(app).get(`/api/v1/public/join/${rotated.body.data.token}`);
    expect(live.status).toBe(200);
  });

  it("refuses to hand the link to someone outside the group", async () => {
    const anonymous = await request(app).get(`/api/v1/groups/${groupId}/join-link`);
    expect(anonymous.status).toBe(401);
  });
});
