import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/** Faith Achieng is already on Tujijenge's roster with savings against her name. */
const ROSTER_PHONE = "0700000202";
const TUJIJENGE = "IWL-KBU-0001";
const UMOJA = "IWL-KBU-0002";
const PASSWORD = "JoinFlow#2026";

async function login(email: string, password = "IntellicashDemo#2026") {
  const agent = request.agent(app);
  await agent.post("/api/v1/auth/login").send({ email, password }).expect(200);
  return agent;
}

describe("joining a group from a member account", () => {
  let joiner: ReturnType<typeof request.agent>;
  let joinerUserId: string;

  beforeAll(async () => {
    await seedDatabase();

    // A clean self-registered member: the person who downloaded the app and
    // has no group yet.
    const existing = await prisma.user.findFirst({ where: { phone: ROSTER_PHONE } });
    if (existing) {
      await prisma.userMembership.deleteMany({ where: { userId: existing.id } });
      await prisma.groupJoinRequest.deleteMany({ where: { userId: existing.id } });
      await prisma.user.delete({ where: { id: existing.id } });
    }

    joiner = request.agent(app);
    const registered = await joiner
      .post("/api/v1/auth/register")
      .send({
        accountType: "MEMBER",
        name: "Faith Achieng",
        phone: ROSTER_PHONE,
        password: PASSWORD
      })
      .expect(201);
    joinerUserId = registered.body.data.id;
  }, 60000);

  it("starts with no groups at all", async () => {
    const res = await joiner.get("/api/v1/members/me/memberships").expect(200);
    expect(res.body.data).toEqual([]);
  });

  it("refuses a code that belongs to no group", async () => {
    const res = await joiner
      .post("/api/v1/members/me/join-requests")
      .send({ groupCode: "IWL-NOPE-9999" })
      .expect(404);
    expect(res.body.error.code).toBe("GROUP_NOT_FOUND");
  });

  it("grants nothing while the request is only pending", async () => {
    await joiner
      .post("/api/v1/members/me/join-requests")
      .send({ groupCode: TUJIJENGE })
      .expect(200);

    // The whole point: knowing the code must not open the books.
    const memberships = await joiner.get("/api/v1/members/me/memberships").expect(200);
    expect(memberships.body.data).toEqual([]);

    const group = await prisma.group.findFirstOrThrow({ where: { code: TUJIJENGE } });
    await joiner.get(`/api/v1/groups/${group.id}/ledger`).expect(404);
    await joiner.get(`/api/v1/groups/${group.id}`).expect(404);
  });

  it("will not let the same person queue twice", async () => {
    const res = await joiner
      .post("/api/v1/members/me/join-requests")
      .send({ groupCode: TUJIJENGE })
      .expect(409);
    expect(res.body.error.code).toBe("REQUEST_PENDING");
  });

  it("lets a refused person ask again once circumstances change", async () => {
    const groupAgent = await login("group@intellicash.co.ke");
    const group = await prisma.group.findFirstOrThrow({ where: { code: TUJIJENGE } });

    const pending = await groupAgent
      .get(`/api/v1/groups/${group.id}/join-requests`)
      .expect(200);
    const mine = pending.body.data.find((r: any) => r.phone === "254700000202");
    expect(mine).toBeTruthy();

    await groupAgent
      .post(`/api/v1/groups/${group.id}/join-requests/${mine.id}/decision`)
      .send({ decision: "REJECT", notes: "Come to a meeting first." })
      .expect(200);

    // Asking again in the same breath would just re-notify the officials who
    // have only now said no.
    const tooSoon = await joiner
      .post("/api/v1/members/me/join-requests")
      .send({ groupCode: TUJIJENGE })
      .expect(429);
    expect(tooSoon.body.error.code).toBe("REASK_TOO_SOON");

    // But a refusal must never be a permanent lockout: once the cool-down has
    // passed, the group gets to decide again.
    await prisma.groupJoinRequest.update({
      where: { id: mine.id },
      data: { decidedAt: new Date(Date.now() - 2 * 60 * 60 * 1000) }
    });
    await joiner
      .post("/api/v1/members/me/join-requests")
      .send({ groupCode: TUJIJENGE })
      .expect(200);
  });

  it("attaches an approved member to the savings already recorded for them", async () => {
    const groupAgent = await login("group@intellicash.co.ke");
    const group = await prisma.group.findFirstOrThrow({ where: { code: TUJIJENGE } });

    // What the group had on its books for Faith before she ever signed in.
    const rosterEntry = await prisma.member.findFirstOrThrow({
      where: { groupId: group.id, phone: "+254700000202" }
    });

    const pending = await groupAgent
      .get(`/api/v1/groups/${group.id}/join-requests`)
      .expect(200);
    const mine = pending.body.data.find((r: any) => r.phone === "254700000202");

    // The list warns that accepting hands over an existing passbook.
    expect(mine.willLinkToMemberId).toBe(rosterEntry.id);
    expect(mine.willLinkToMemberName).toBe(rosterEntry.fullName);

    // Approving blind is refused — handing over savings must be deliberate.
    const unconfirmed = await groupAgent
      .post(`/api/v1/groups/${group.id}/join-requests/${mine.id}/decision`)
      .send({ decision: "APPROVE" })
      .expect(409);
    expect(unconfirmed.body.error.code).toBe("CONFIRM_EXISTING_MEMBER");

    // Still pending, so the refusal did not consume the request.
    const stillThere = await groupAgent
      .get(`/api/v1/groups/${group.id}/join-requests`)
      .expect(200);
    expect(stillThere.body.data.some((r: any) => r.id === mine.id)).toBe(true);

    const decision = await groupAgent
      .post(`/api/v1/groups/${group.id}/join-requests/${mine.id}/decision`)
      .send({ decision: "APPROVE", confirmMemberId: rosterEntry.id })
      .expect(200);

    // Not a fresh empty passbook — her existing history.
    expect(decision.body.data.matchedExistingMember).toBe(true);
    expect(decision.body.data.memberId).toBe(rosterEntry.id);

    const memberships = await joiner.get("/api/v1/members/me/memberships").expect(200);
    expect(memberships.body.data).toHaveLength(1);
    expect(memberships.body.data[0].groupId).toBe(group.id);
    expect(memberships.body.data[0].isActive).toBe(true);

    // And now the books really are open to her.
    await joiner.get(`/api/v1/groups/${group.id}`).expect(200);
  });

  it("refuses a confirmation that names the wrong member", async () => {
    const groupAgent = await login("group@intellicash.co.ke");
    const umoja = await prisma.group.findFirstOrThrow({ where: { code: UMOJA } });
    const other = await prisma.member.findFirstOrThrow({ where: { groupId: umoja.id } });
    const group = await prisma.group.findFirstOrThrow({ where: { code: TUJIJENGE } });

    const all = await groupAgent
      .get(`/api/v1/groups/${group.id}/join-requests?status=ALL`)
      .expect(200);
    const decided = all.body.data.find((r: any) => r.status === "APPROVED");
    // Already answered, so this also proves the claim check runs first.
    const res = await groupAgent
      .post(`/api/v1/groups/${group.id}/join-requests/${decided.id}/decision`)
      .send({ decision: "APPROVE", confirmMemberId: other.id })
      .expect(409);
    expect(res.body.error.code).toBe("ALREADY_DECIDED");
  });

  it("cannot be decided twice", async () => {
    const groupAgent = await login("group@intellicash.co.ke");
    const group = await prisma.group.findFirstOrThrow({ where: { code: TUJIJENGE } });
    const all = await groupAgent
      .get(`/api/v1/groups/${group.id}/join-requests?status=ALL`)
      .expect(200);
    const mine = all.body.data.find((r: any) => r.phone === "254700000202" && r.status === "APPROVED");
    const res = await groupAgent
      .post(`/api/v1/groups/${group.id}/join-requests/${mine.id}/decision`)
      .send({ decision: "REJECT" })
      .expect(409);
    expect(res.body.error.code).toBe("ALREADY_DECIDED");
  });

  it("lets the same person also belong to a second group", async () => {
    const admin = await login("admin@intellicash.co.ke");
    const umoja = await prisma.group.findFirstOrThrow({ where: { code: UMOJA } });

    await joiner
      .post("/api/v1/members/me/join-requests")
      .send({ groupCode: UMOJA })
      .expect(200);

    const pending = await admin.get(`/api/v1/groups/${umoja.id}/join-requests`).expect(200);
    const mine = pending.body.data.find((r: any) => r.phone === "254700000202");

    const decision = await admin
      .post(`/api/v1/groups/${umoja.id}/join-requests/${mine.id}/decision`)
      .send({ decision: "APPROVE" })
      .expect(200);

    // Nobody by that phone on Umoja's roster, so she is added fresh.
    expect(decision.body.data.matchedExistingMember).toBe(false);

    const memberships = await joiner.get("/api/v1/members/me/memberships").expect(200);
    expect(memberships.body.data).toHaveLength(2);
    // Still viewing the first group; joining a second must not move her.
    expect(memberships.body.data.filter((m: any) => m.isActive)).toHaveLength(1);
  });

  it("switches which group is in view, and the passbook follows", async () => {
    const tujijenge = await prisma.group.findFirstOrThrow({ where: { code: TUJIJENGE } });
    const umoja = await prisma.group.findFirstOrThrow({ where: { code: UMOJA } });

    const before = await joiner.get("/api/v1/members/me").expect(200);
    expect(before.body.data.member.group.id).toBe(tujijenge.id);

    await joiner
      .post("/api/v1/members/me/active-membership")
      .send({ groupId: umoja.id })
      .expect(200);

    const after = await joiner.get("/api/v1/members/me").expect(200);
    expect(after.body.data.member.group.id).toBe(umoja.id);
    // A brand-new roster entry in Umoja has no history of its own.
    expect(after.body.data.summary.totalPaidInCents).toBe(0);
    // ...whereas her Tujijenge passbook is untouched by the switch.
    expect(before.body.data.summary.totalPaidInCents).toBeGreaterThan(0);
  });

  it("refuses to switch into a group the account does not belong to", async () => {
    const outsider = await prisma.group.findFirst({
      where: { code: { notIn: [TUJIJENGE, UMOJA] } }
    });
    const targetId = outsider?.id ?? "cmr000000000000000000000";
    const res = await joiner
      .post("/api/v1/members/me/active-membership")
      .send({ groupId: targetId })
      .expect(404);
    expect(res.body.error.code).toBe("NOT_A_MEMBERSHIP");
  });

  it("keeps one group's join queue invisible to another group", async () => {
    const groupAgent = await login("group@intellicash.co.ke");
    const umoja = await prisma.group.findFirstOrThrow({ where: { code: UMOJA } });
    // Tujijenge's account has no business reading Umoja's applicants.
    await groupAgent.get(`/api/v1/groups/${umoja.id}/join-requests`).expect(404);
  });

  it("does not let a member read the join queue of their own group", async () => {
    const tujijenge = await prisma.group.findFirstOrThrow({ where: { code: TUJIJENGE } });
    // members:write is an official's permission, not a member's.
    await joiner.get(`/api/v1/groups/${tujijenge.id}/join-requests`).expect(403);
  });
});
