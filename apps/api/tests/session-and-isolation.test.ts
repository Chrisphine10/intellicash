import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import bcrypt from "bcryptjs";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();
const DEMO = "IntellicashDemo#2026";

function sessionTokenFrom(res: request.Response) {
  const raw = res.headers["set-cookie"];
  const header = Array.isArray(raw) ? raw.join(";") : String(raw ?? "");
  return (header.match(/ic_session=([^;]+)/) ?? [])[1];
}

describe("signing out ends the session on the server", () => {
  beforeAll(async () => {
    await seedDatabase();
  }, 60000);

  it("revokes a bearer token, which is what the phone holds", async () => {
    // The app does not use cookies — it keeps the session token and sends it
    // as a Bearer. Clearing it on the handset alone would leave the session
    // alive on the server, which matters because these phones get passed
    // around a group.
    const login = await request(app)
      .post("/api/v1/auth/login")
      .send({ email: "member@intellicash.co.ke", password: DEMO })
      .expect(200);
    const token = sessionTokenFrom(login);
    expect(token).toBeTruthy();

    const auth = `Bearer ${token}`;
    await request(app).get("/api/v1/auth/me").set("Authorization", auth).expect(200);

    await request(app).post("/api/v1/auth/logout").set("Authorization", auth).send({}).expect(200);

    await request(app).get("/api/v1/auth/me").set("Authorization", auth).expect(401);
    await request(app).get("/api/v1/members/me").set("Authorization", auth).expect(401);
  });

  it("is safe to call twice", async () => {
    // Sign-out is fire-and-forget on a flaky connection; a retry must not error.
    await request(app).post("/api/v1/auth/logout").send({}).expect(200);
  });
});

/**
 * Someone who saves with two VSLAs may only ever see the one they are
 * currently viewing. Belonging to both is not permission to read both at once.
 */
describe("a member of two groups sees only the one in view", () => {
  let agent: ReturnType<typeof request.agent>;
  let groupA: { id: string; name: string };
  let groupB: { id: string; name: string };

  beforeAll(async () => {
    const a = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0001" } });
    const b = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0002" } });
    groupA = { id: a.id, name: a.name };
    groupB = { id: b.id, name: b.name };

    await prisma.user.deleteMany({ where: { email: "both-groups@example.com" } });
    await prisma.member.deleteMany({ where: { phone: "254790111222" } });
    const inA = await prisma.member.create({
      data: { groupId: a.id, fullName: "Belongs To Both", phone: "254790111222", status: "ACTIVE" }
    });
    const inB = await prisma.member.create({
      data: { groupId: b.id, fullName: "Belongs To Both", phone: "254790111222", status: "ACTIVE" }
    });
    const user = await prisma.user.create({
      data: {
        name: "Belongs To Both",
        email: "both-groups@example.com",
        phone: "254790111222",
        passwordHash: await bcrypt.hash("Both#2026", 12),
        role: "MEMBER",
        memberId: inA.id,
        groupId: a.id
      }
    });
    await prisma.userMembership.createMany({
      data: [
        { userId: user.id, memberId: inA.id, groupId: a.id },
        { userId: user.id, memberId: inB.id, groupId: b.id }
      ]
    });

    agent = request.agent(app);
    await agent
      .post("/api/v1/auth/login")
      .send({ email: "both-groups@example.com", password: "Both#2026" })
      .expect(200);
  }, 60000);

  it("finds both groups it belongs to", async () => {
    const res = await agent.get("/api/v1/members/me/memberships").expect(200);
    expect(res.body.data.map((m: { groupName: string }) => m.groupName).sort()).toEqual(
      [groupA.name, groupB.name].sort()
    );
    expect(res.body.data.filter((m: { isActive: boolean }) => m.isActive)).toHaveLength(1);
  });

  it("cannot read the group it is not currently viewing", async () => {
    await agent.get(`/api/v1/groups/${groupA.id}`).expect(200);
    await agent.get(`/api/v1/groups/${groupB.id}`).expect(404);
    await agent.get(`/api/v1/groups/${groupB.id}/ledger`).expect(404);
    await agent.get(`/api/v1/groups/${groupB.id}/members`).expect(404);
  });

  it("swaps which group is readable when the member switches", async () => {
    await agent
      .post("/api/v1/members/me/active-membership")
      .send({ groupId: groupB.id })
      .expect(200);

    await agent.get(`/api/v1/groups/${groupB.id}`).expect(200);
    // The one that was readable a moment ago no longer is.
    await agent.get(`/api/v1/groups/${groupA.id}`).expect(404);
    await agent.get(`/api/v1/groups/${groupA.id}/ledger`).expect(404);
  });

  it("reports the passbook of whichever group is in view", async () => {
    const res = await agent.get("/api/v1/members/me").expect(200);
    expect(res.body.data.member.group.id).toBe(groupB.id);
  });
});
