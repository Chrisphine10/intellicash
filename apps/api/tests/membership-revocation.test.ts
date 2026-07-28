import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import bcrypt from "bcryptjs";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();
const PASSWORD = "Revoked#2026";

/**
 * What a member can still reach after a group takes them off its roster.
 *
 * `User.groupId` is NOT cleared when the Member row goes — it still points at
 * the group they were removed from. Nothing may be readable on the strength of
 * that stale pointer, so this pins the behaviour rather than trusting that
 * every role branch happens to use `memberId`.
 */
describe("a member removed from the roster", () => {
  let agent: ReturnType<typeof request.agent>;
  let userId: string;
  let memberId: string;
  let groupId: string;

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0001" } });
    groupId = group.id;

    await prisma.user.deleteMany({ where: { email: "revoked@example.com" } });
    const member = await prisma.member.create({
      data: { groupId, fullName: "Temporary Member", phone: "254798000111", status: "ACTIVE" }
    });
    memberId = member.id;
    const user = await prisma.user.create({
      data: {
        name: "Temporary Member",
        email: "revoked@example.com",
        phone: "254798000111",
        passwordHash: await bcrypt.hash(PASSWORD, 12),
        role: "MEMBER",
        memberId: member.id,
        groupId
      }
    });
    userId = user.id;
    await prisma.userMembership.create({ data: { userId, memberId, groupId } });

    agent = request.agent(app);
    await agent
      .post("/api/v1/auth/login")
      .send({ email: "revoked@example.com", password: PASSWORD })
      .expect(200);
  }, 60000);

  it("can read the group while they are still on the roster", async () => {
    await agent.get(`/api/v1/groups/${groupId}`).expect(200);
    await agent.get("/api/v1/members/me").expect(200);
  });

  it("loses access the moment the group removes them", async () => {
    await prisma.member.delete({ where: { id: memberId } });

    // The group is no longer theirs to read...
    await agent.get(`/api/v1/groups/${groupId}`).expect(404);
    await agent.get(`/api/v1/groups/${groupId}/ledger`).expect(404);
    await agent.get(`/api/v1/groups/${groupId}/members`).expect(404);
    // ...and there is no passbook to fetch.
    await agent.get("/api/v1/members/me").expect(400);
  });

  it("clears the pointers rather than leaving them aimed at that group", async () => {
    const user = await prisma.user.findUniqueOrThrow({
      where: { id: userId },
      select: { memberId: true, groupId: true }
    });
    // Deleting the Member nulls memberId and cascades the link away; the
    // reconcile then drops groupId too, since it points at a group this
    // account no longer belongs to.
    expect(user.memberId).toBeNull();
    expect(await prisma.userMembership.findFirst({ where: { userId } })).toBeNull();
    expect(user.groupId).toBeNull();
  });

  it("shows them no groups at all", async () => {
    const res = await agent.get("/api/v1/members/me/memberships").expect(200);
    expect(res.body.data).toEqual([]);
  });

  it("cannot switch back into the group it was removed from", async () => {
    const res = await agent
      .post("/api/v1/members/me/active-membership")
      .send({ groupId })
      .expect(404);
    expect(res.body.error.code).toBe("NOT_A_MEMBERSHIP");
  });

  it("reads nothing through a group pointer even if one is left behind", async () => {
    // Clearing the pointer is hygiene, not the protection. Force it back to
    // the group that removed her — as the raw cascade used to leave it — and
    // confirm scope still refuses, because it resolves through the membership
    // and not through this field. Programme scope in particular used to trust
    // it, which let a removed member keep reading her former group's
    // programmes.
    await prisma.user.update({ where: { id: userId }, data: { groupId } });

    const programmes = await agent.get("/api/v1/programmes").expect(200);
    expect(programmes.body.data).toEqual([]);
    const groups = await agent.get("/api/v1/groups").expect(200);
    expect(groups.body.data).toEqual([]);
    await agent.get(`/api/v1/groups/${groupId}`).expect(404);
  });
});

/**
 * People commonly save with more than one VSLA. Being dropped by one of them
 * must not cost them the others.
 */
describe("a member of several groups removed from the one in view", () => {
  let agent: ReturnType<typeof request.agent>;
  let keptGroupId: string;
  let keptGroupName: string;

  beforeAll(async () => {
    await seedDatabase();
    const dropping = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0001" } });
    const keeping = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0002" } });
    keptGroupId = keeping.id;
    keptGroupName = keeping.name;

    await prisma.user.deleteMany({ where: { email: "two-groups@example.com" } });
    const inDropping = await prisma.member.create({
      data: { groupId: dropping.id, fullName: "Belongs Twice", phone: "254791000123", status: "ACTIVE" }
    });
    const inKeeping = await prisma.member.create({
      data: { groupId: keeping.id, fullName: "Belongs Twice", phone: "254791000123", status: "ACTIVE" }
    });
    const user = await prisma.user.create({
      data: {
        name: "Belongs Twice",
        email: "two-groups@example.com",
        phone: "254791000123",
        passwordHash: await bcrypt.hash(PASSWORD, 12),
        role: "MEMBER",
        // Viewing the group that is about to drop her.
        memberId: inDropping.id,
        groupId: dropping.id
      }
    });
    await prisma.userMembership.createMany({
      data: [
        { userId: user.id, memberId: inDropping.id, groupId: dropping.id },
        { userId: user.id, memberId: inKeeping.id, groupId: keeping.id }
      ]
    });

    agent = request.agent(app);
    await agent
      .post("/api/v1/auth/login")
      .send({ email: "two-groups@example.com", password: PASSWORD })
      .expect(200);

    // The group she was viewing takes her off its roster.
    await prisma.member.delete({ where: { id: inDropping.id } });
  }, 60000);

  it("moves her to a group she still belongs to instead of stranding her", async () => {
    // Without this she is told she belongs to nowhere — and cannot rejoin,
    // because the server correctly says she is already a member.
    const res = await agent.get("/api/v1/members/me").expect(200);
    expect(res.body.data.member.group.id).toBe(keptGroupId);
  });

  it("lists the remaining group as the one in view", async () => {
    const res = await agent.get("/api/v1/members/me/memberships").expect(200);
    expect(res.body.data).toHaveLength(1);
    expect(res.body.data[0].groupName).toBe(keptGroupName);
    expect(res.body.data[0].isActive).toBe(true);
  });

  it("shows her the group she kept, and not the one that dropped her", async () => {
    const res = await agent.get("/api/v1/groups").expect(200);
    expect(res.body.data.map((g: { name: string }) => g.name)).toEqual([keptGroupName]);
  });
});
