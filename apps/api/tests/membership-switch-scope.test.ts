import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import bcrypt from "bcryptjs";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * Switching the membership in view rewrites `User.groupId`, and GROUP_ACCOUNT
 * scoping resolves against exactly that field. So the switch has to be a
 * MEMBER-only operation — `members:read` is not a tight enough gate, since
 * group accounts hold it too.
 *
 * The way in is a leftover membership row: an account that was a member and
 * had its role changed keeps its rows, which is deliberate (changing a role
 * back should not lose their groups) but must not become a way into someone
 * else's books.
 */
describe("switching the group in view", () => {
  let groupA: { id: string; name: string };
  let groupB: { id: string; name: string };

  beforeAll(async () => {
    await seedDatabase();
    const a = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0001" } });
    const b = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0002" } });
    groupA = { id: a.id, name: a.name };
    groupB = { id: b.id, name: b.name };
  }, 60000);

  it("does not let a group account repoint itself at another group", async () => {
    await prisma.user.deleteMany({ where: { email: "stale-membership@example.com" } });
    await prisma.member.deleteMany({ where: { phone: "254789000222" } });
    const memberInA = await prisma.member.create({
      data: { groupId: groupA.id, fullName: "Stale Membership", phone: "254789000222", status: "ACTIVE" }
    });
    const account = await prisma.user.create({
      data: {
        name: "Stale Membership",
        email: "stale-membership@example.com",
        phone: "254789000222",
        passwordHash: await bcrypt.hash("Stale#2026", 12),
        // Speaks for group B, but carries a leftover membership in group A.
        role: "GROUP_ACCOUNT",
        groupId: groupB.id
      }
    });
    await prisma.userMembership.create({
      data: { userId: account.id, memberId: memberInA.id, groupId: groupA.id }
    });

    const agent = request.agent(app);
    await agent
      .post("/api/v1/auth/login")
      .send({ email: "stale-membership@example.com", password: "Stale#2026" })
      .expect(200);

    await agent.get(`/api/v1/groups/${groupA.id}`).expect(404);
    await agent
      .post("/api/v1/members/me/active-membership")
      .send({ groupId: groupA.id })
      .expect(404);

    // Still refused, and the account is still bound to its own group.
    await agent.get(`/api/v1/groups/${groupA.id}`).expect(404);
    await agent.get(`/api/v1/groups/${groupA.id}/ledger`).expect(404);
    const after = await prisma.user.findUniqueOrThrow({
      where: { id: account.id },
      select: { groupId: true }
    });
    expect(after.groupId).toBe(groupB.id);
  });

  it("still lets a real member move between their own groups", async () => {
    // The guard must not cost members the feature it protects.
    await prisma.user.deleteMany({ where: { email: "genuine-two@example.com" } });
    await prisma.member.deleteMany({ where: { phone: "254789000333" } });
    const inA = await prisma.member.create({
      data: { groupId: groupA.id, fullName: "Genuine Two", phone: "254789000333", status: "ACTIVE" }
    });
    const inB = await prisma.member.create({
      data: { groupId: groupB.id, fullName: "Genuine Two", phone: "254789000333", status: "ACTIVE" }
    });
    const user = await prisma.user.create({
      data: {
        name: "Genuine Two",
        email: "genuine-two@example.com",
        phone: "254789000333",
        passwordHash: await bcrypt.hash("Genuine#2026", 12),
        role: "MEMBER",
        memberId: inA.id,
        groupId: groupA.id
      }
    });
    await prisma.userMembership.createMany({
      data: [
        { userId: user.id, memberId: inA.id, groupId: groupA.id },
        { userId: user.id, memberId: inB.id, groupId: groupB.id }
      ]
    });

    const agent = request.agent(app);
    await agent
      .post("/api/v1/auth/login")
      .send({ email: "genuine-two@example.com", password: "Genuine#2026" })
      .expect(200);

    const both = await agent.get("/api/v1/members/me/memberships").expect(200);
    expect(both.body.data).toHaveLength(2);

    await agent
      .post("/api/v1/members/me/active-membership")
      .send({ groupId: groupB.id })
      .expect(200);
    await agent.get(`/api/v1/groups/${groupB.id}`).expect(200);
    await agent.get(`/api/v1/groups/${groupA.id}`).expect(404);

    await agent
      .post("/api/v1/members/me/active-membership")
      .send({ groupId: groupA.id })
      .expect(200);
    await agent.get(`/api/v1/groups/${groupA.id}`).expect(200);
  });
});
