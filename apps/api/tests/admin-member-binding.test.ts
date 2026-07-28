import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import bcrypt from "bcryptjs";

const app = createApp();
const DEMO = "IntellicashDemo#2026";

/**
 * An admin binding a login to a member must write the membership row, not just
 * `User.memberId`.
 *
 * The pointer alone is invisible to `UserMembership`, which is what the app
 * reads to answer "which groups do I belong to" — so an account created that
 * way showed the member no groups at all until something happened to repair
 * it. The seed had the same gap.
 */
describe("admin-created member accounts", () => {
  let admin: ReturnType<typeof request.agent>;
  let groupId: string;
  let unclaimedMemberId: string;
  let claimedMemberId: string;

  beforeAll(async () => {
    await seedDatabase();
    admin = request.agent(app);
    await admin.post("/api/v1/auth/login").send({ email: "admin@intellicash.co.ke", password: DEMO }).expect(200);

    const group = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0001" } });
    groupId = group.id;

    // One roster entry nobody has an account for, and one that is taken.
    const linked = await prisma.userMembership.findFirstOrThrow({ where: { groupId } });
    claimedMemberId = linked.memberId;
    const free = await prisma.member.findFirstOrThrow({
      where: { groupId, id: { not: claimedMemberId }, membershipLinks: { none: {} } }
    });
    unclaimedMemberId = free.id;
  }, 60000);

  it("seeds the membership row rather than only the pointer", async () => {
    const bound = await prisma.user.findMany({
      where: { memberId: { not: null } },
      select: { id: true, memberId: true }
    });
    expect(bound.length).toBeGreaterThan(0);
    for (const user of bound) {
      const link = await prisma.userMembership.findUnique({
        where: { memberId: user.memberId as string }
      });
      expect(link?.userId).toBe(user.id);
    }
  });

  it("records the membership when an admin creates a member account", async () => {
    const res = await admin
      .post("/api/v1/users")
      .send({
        name: "Admin Made Member",
        email: "admin-made@example.com",
        password: "AdminMade#2026",
        role: "MEMBER",
        memberId: unclaimedMemberId
      })
      .expect(201);

    const link = await prisma.userMembership.findUnique({ where: { memberId: unclaimedMemberId } });
    expect(link?.userId).toBe(res.body.data.id);
    expect(link?.groupId).toBe(groupId);
  });

  it("lets that account immediately find the group it belongs to", async () => {
    // The point of the row: without it this returns an empty list and the
    // member is told they are in no group.
    const member = request.agent(app);
    await member
      .post("/api/v1/auth/login")
      .send({ email: "admin-made@example.com", password: "AdminMade#2026" })
      .expect(200);

    const res = await member.get("/api/v1/members/me/memberships").expect(200);
    expect(res.body.data).toHaveLength(1);
    expect(res.body.data[0].groupId).toBe(groupId);
    expect(res.body.data[0].isActive).toBe(true);
  });

  it("refuses to bind a member who already has an account", async () => {
    // Silently taking it would detach the existing account from that person's
    // savings.
    const res = await admin
      .post("/api/v1/users")
      .send({
        name: "Second Claimant",
        email: "second-claimant@example.com",
        password: "SecondClaimant#2026",
        role: "MEMBER",
        memberId: claimedMemberId
      })
      .expect(409);
    expect(res.body.error.code).toBe("MEMBER_ALREADY_LINKED");

    // And the original holder still has it.
    const link = await prisma.userMembership.findUnique({ where: { memberId: claimedMemberId } });
    expect(link).toBeTruthy();
    const stillTheirs = await prisma.user.findUnique({ where: { id: link!.userId } });
    expect(stillTheirs?.email).not.toBe("second-claimant@example.com");
  });

  it("replaces the row when an admin re-binds within the same group", async () => {
    // Re-pointing at a different person on the SAME roster means the account
    // was bound to the wrong member. Nobody holds two places in one group, so
    // the mistaken row goes.
    const created = await prisma.user.findUniqueOrThrow({
      where: { email: "admin-made@example.com" }
    });
    const another = await prisma.member.findFirstOrThrow({
      where: { groupId, id: { notIn: [unclaimedMemberId, claimedMemberId] }, membershipLinks: { none: {} } }
    });

    await admin
      .patch(`/api/v1/users/${created.id}`)
      .send({ role: "MEMBER", groupId, memberId: another.id })
      .expect(200);

    const moved = await prisma.userMembership.findUnique({ where: { memberId: another.id } });
    expect(moved?.userId).toBe(created.id);
    const replaced = await prisma.userMembership.findUnique({
      where: { memberId: unclaimedMemberId }
    });
    expect(replaced).toBeNull();
    // Still exactly one membership — the correction did not multiply them.
    expect(await prisma.userMembership.count({ where: { userId: created.id } })).toBe(1);
  });
});

/**
 * The whole point of the link table: one person, several VSLAs. Anything that
 * edits an account must leave the groups they actually belong to alone.
 */
describe("an admin editing a member who saves with two groups", () => {
  let admin: ReturnType<typeof request.agent>;
  let userId: string;
  let inA: string;
  let inB: string;
  let groupA: string;
  let groupB: string;

  beforeAll(async () => {
    await seedDatabase();
    admin = request.agent(app);
    await admin.post("/api/v1/auth/login").send({ email: "admin@intellicash.co.ke", password: DEMO }).expect(200);

    const a = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0001" } });
    const b = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0002" } });
    groupA = a.id;
    groupB = b.id;

    await prisma.user.deleteMany({ where: { email: "two-vslas@example.com" } });
    await prisma.member.deleteMany({ where: { phone: "254793000111" } });
    const memberA = await prisma.member.create({
      data: { groupId: a.id, fullName: "Saves Twice", phone: "254793000111", status: "ACTIVE" }
    });
    const memberB = await prisma.member.create({
      data: { groupId: b.id, fullName: "Saves Twice", phone: "254793000111", status: "ACTIVE" }
    });
    inA = memberA.id;
    inB = memberB.id;

    const user = await prisma.user.create({
      data: {
        name: "Saves Twice",
        email: "two-vslas@example.com",
        phone: "254793000111",
        passwordHash: await bcrypt.hash("TwoVslas#2026", 12),
        role: "MEMBER",
        memberId: memberA.id,
        groupId: a.id
      }
    });
    userId = user.id;
    await prisma.userMembership.createMany({
      data: [
        { userId: user.id, memberId: memberA.id, groupId: a.id },
        { userId: user.id, memberId: memberB.id, groupId: b.id }
      ]
    });
  }, 60000);

  it("keeps both groups when the account is pointed at the other one", async () => {
    await admin
      .patch(`/api/v1/users/${userId}`)
      .send({ role: "MEMBER", groupId: groupB, memberId: inB })
      .expect(200);

    const links = await prisma.userMembership.findMany({ where: { userId } });
    expect(links.map((l) => l.memberId).sort()).toEqual([inA, inB].sort());
  });

  it("leaves the member able to reach both groups afterwards", async () => {
    const member = request.agent(app);
    await member
      .post("/api/v1/auth/login")
      .send({ email: "two-vslas@example.com", password: "TwoVslas#2026" })
      .expect(200);

    const res = await member.get("/api/v1/members/me/memberships").expect(200);
    expect(res.body.data).toHaveLength(2);

    // And she can still move back to the group the edit moved her off.
    await member
      .post("/api/v1/members/me/active-membership")
      .send({ groupId: groupA })
      .expect(200);
    const passbook = await member.get("/api/v1/members/me").expect(200);
    expect(passbook.body.data.member.group.id).toBe(groupA);
  });
});


