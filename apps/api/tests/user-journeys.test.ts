import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();
const DEMO = "IntellicashDemo#2026";

/**
 * End-to-end walks through what each kind of account actually does, in order,
 * each step depending on the one before.
 *
 * Endpoint tests elsewhere prove the pieces work. These prove the sequence
 * works — which is where a real person meets the system, and where a step that
 * quietly returns nothing strands them.
 */

async function signIn(body: Record<string, string>) {
  const agent = request.agent(app);
  const res = await agent.post("/api/v1/auth/login").send(body);
  return { agent, status: res.status, user: res.body?.data };
}

// ---------------------------------------------------------------------------

describe("JOURNEY: a woman joins a savings group and checks her passbook", () => {
  const PHONE = "0722334455";
  const PASSWORD = "Journey#2026";
  let member: ReturnType<typeof request.agent>;
  let groupCode: string;
  let groupId: string;

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0001" } });
    groupCode = group.code;
    groupId = group.id;

    const existing = await prisma.user.findFirst({ where: { phone: { contains: "722334455" } } });
    if (existing) {
      await prisma.notification.deleteMany({ where: { userId: existing.id } });
      await prisma.userMembership.deleteMany({ where: { userId: existing.id } });
      await prisma.groupJoinRequest.deleteMany({ where: { userId: existing.id } });
      await prisma.user.delete({ where: { id: existing.id } });
    }
  }, 60000);

  it("1. downloads the app and creates an account with her phone number", async () => {
    member = request.agent(app);
    const res = await member
      .post("/api/v1/auth/register")
      .send({ accountType: "MEMBER", name: "Wanjiru Kamau", phone: PHONE, password: PASSWORD })
      .expect(201);
    // Stored canonically, whatever she typed.
    expect(res.body.data.phone).toBe("254722334455");
  });

  it("2. sees she is not in any group yet — not an error, a starting point", async () => {
    const res = await member.get("/api/v1/members/me/memberships").expect(200);
    expect(res.body.data).toEqual([]);
  });

  it("3. enters the group code from her passbook, and is told it is pending", async () => {
    const res = await member
      .post("/api/v1/members/me/join-requests")
      .send({ groupCode })
      .expect(200);
    expect(res.body.data.status).toBe("PENDING");
    expect(res.body.data.groupName).toBeTruthy();
  });

  it("4. still cannot see any of the group's money while it is pending", async () => {
    // Knowing the code is not membership. This is the whole gate.
    await member.get(`/api/v1/groups/${groupId}`).expect(404);
    await member.get(`/api/v1/groups/${groupId}/ledger`).expect(404);
    expect((await member.get("/api/v1/members/me/memberships")).body.data).toEqual([]);
  });

  it("5. the treasurer is told someone is waiting", async () => {
    const official = await prisma.user.findFirstOrThrow({
      where: { groupId, role: "GROUP_ACCOUNT" }
    });
    const notices = await prisma.notification.findMany({ where: { userId: official.id } });
    expect(notices.some((n) => n.title === "Someone wants to join")).toBe(true);
  });

  it("6. the treasurer accepts her", async () => {
    const { agent: official, status } = await signIn({
      email: "group@intellicash.co.ke",
      password: DEMO
    });
    expect(status).toBe(200);

    const queue = await official.get(`/api/v1/groups/${groupId}/join-requests`).expect(200);
    const hers = queue.body.data.find((r: any) => r.phone === "254722334455");
    expect(hers).toBeTruthy();

    await official
      .post(`/api/v1/groups/${groupId}/join-requests/${hers.id}/decision`)
      .send({ decision: "APPROVE", confirmMemberId: hers.willLinkToMemberId ?? undefined })
      .expect(200);
  });

  it("7. she is told she is in, and the group now appears in her app", async () => {
    const user = await prisma.user.findFirstOrThrow({ where: { phone: "254722334455" } });
    const notices = await prisma.notification.findMany({ where: { userId: user.id } });
    expect(notices.some((n) => n.title.startsWith("You are now in"))).toBe(true);

    const mine = await member.get("/api/v1/members/me/memberships").expect(200);
    expect(mine.body.data).toHaveLength(1);
    expect(mine.body.data[0].isActive).toBe(true);
  });

  it("8. opens her passbook and it balances", async () => {
    const res = await member.get("/api/v1/members/me").expect(200);
    const s = res.body.data.summary;
    expect(s.totalPaidInCents).toBe(s.sharesCents + s.socialCents + s.finesCents);
    // A new member owes nothing, and never a negative.
    expect(s.loanOutstandingCents).toBeGreaterThanOrEqual(0);
  });

  it("9. her own report agrees with her passbook, to the cent", async () => {
    const passbook = await member.get("/api/v1/members/me").expect(200);
    const memberId = passbook.body.data.member.id;

    const { agent: official } = await signIn({ email: "group@intellicash.co.ke", password: DEMO });
    const groupsView = await official.get(`/api/v1/reports/member/${memberId}`).expect(200);

    // The group's copy of her figures and her own must never disagree.
    expect(groupsView.body.data.summary).toEqual(passbook.body.data.summary);
  });

  it("10. signs out, and the session is dead on the server", async () => {
    await member.post("/api/v1/auth/logout").send({}).expect(200);
    await member.get("/api/v1/members/me").expect(401);
    await member.get("/api/v1/members/me/memberships").expect(401);
  });

  it("11. can sign back in typing her number a different way", async () => {
    const { status, user } = await signIn({ phone: "+254 722 334 455", password: PASSWORD });
    expect(status).toBe(200);
    expect(user.name).toBe("Wanjiru Kamau");
  });
});

// ---------------------------------------------------------------------------

describe("JOURNEY: a group official runs their group", () => {
  let official: ReturnType<typeof request.agent>;
  let groupId: string;

  beforeAll(async () => {
    const signed = await signIn({ email: "group@intellicash.co.ke", password: DEMO });
    expect(signed.status).toBe(200);
    official = signed.agent;
    groupId = signed.user.groupId;
    expect(groupId).toBeTruthy();
  }, 60000);

  it("1. lands on their own group, and only theirs", async () => {
    const res = await official.get("/api/v1/groups").expect(200);
    expect(res.body.data).toHaveLength(1);
    expect(res.body.data[0].id).toBe(groupId);
  });

  it("2. opens the group and sees its funds", async () => {
    const res = await official.get(`/api/v1/groups/${groupId}`).expect(200);
    expect(res.body.data.name).toBeTruthy();
    expect(Array.isArray(res.body.data.fundAccounts)).toBe(true);
  });

  it("3. reads the roster", async () => {
    const res = await official.get(`/api/v1/groups/${groupId}/members`).expect(200);
    expect(res.body.data.length).toBeGreaterThan(0);
    for (const m of res.body.data) expect(m.fullName).toBeTruthy();
  });

  it("4. reviews who has asked to join", async () => {
    const res = await official.get(`/api/v1/groups/${groupId}/join-requests?status=ALL`).expect(200);
    expect(Array.isArray(res.body.data)).toBe(true);
    // Each row says plainly whether accepting hands over existing savings.
    for (const r of res.body.data) expect(r).toHaveProperty("willLinkToMemberName");
  });

  it("5. reads the meeting history", async () => {
    const res = await official.get(`/api/v1/groups/${groupId}/meetings`).expect(200);
    expect(Array.isArray(res.body.data)).toBe(true);
  });

  it("6. reads the ledger", async () => {
    const res = await official.get(`/api/v1/groups/${groupId}/ledger`).expect(200);
    expect(Array.isArray(res.body.data)).toBe(true);
  });

  it("7. pulls the group report, and its member rows reconcile with the roster", async () => {
    const report = await official.get(`/api/v1/reports/group/${groupId}`).expect(200);
    const roster = await official.get(`/api/v1/groups/${groupId}/members`).expect(200);

    expect(report.body.data.members).toHaveLength(roster.body.data.length);
    expect(report.body.data.group.memberCount).toBe(roster.body.data.length);
    // Money stays in integer cents all the way to the report.
    for (const row of report.body.data.ledger) {
      expect(Number.isInteger(row.totalCents)).toBe(true);
    }
  });

  it("8. cannot reach any other group", async () => {
    const other = await prisma.group.findFirstOrThrow({ where: { id: { not: groupId } } });
    await official.get(`/api/v1/groups/${other.id}`).expect(404);
    await official.get(`/api/v1/groups/${other.id}/ledger`).expect(404);
    await official.get(`/api/v1/groups/${other.id}/join-requests`).expect(404);
  });

  it("9. is not an administrator", async () => {
    await official.get("/api/v1/users").expect(403);
    await official.get("/api/v1/audit/events").expect(403);
  });

  it("10. signs out cleanly", async () => {
    await official.post("/api/v1/auth/logout").send({}).expect(200);
    await official.get(`/api/v1/groups/${groupId}`).expect(401);
  });
});

// ---------------------------------------------------------------------------

describe("JOURNEY: a village agent works their caseload", () => {
  let agent: ReturnType<typeof request.agent>;

  beforeAll(async () => {
    const signed = await signIn({ email: "agent@intellicash.co.ke", password: DEMO });
    expect(signed.status).toBe(200);
    expect(signed.user.role).toBe("VILLAGE_AGENT");
    agent = signed.agent;
  }, 60000);

  it("1. opens the caseload report in one request", async () => {
    const res = await agent.get("/api/v1/reports/agent").expect(200);
    expect(res.body.data.agent.name).toBeTruthy();
    expect(res.body.data.groups.length).toBeGreaterThan(0);
  });

  it("2. the summary agrees with the rows beneath it", async () => {
    const { summary, groups } = (await agent.get("/api/v1/reports/agent").expect(200)).body.data;
    expect(summary.groups).toBe(groups.length);
    expect(summary.needSupport).toBe(groups.filter((g: any) => g.needsSupport).length);
    expect(summary.totalMembers).toBe(
      groups.reduce((sum: number, g: any) => sum + g.memberCount, 0)
    );
  });

  it("3. an unassessed group is flagged for a visit, not hidden", async () => {
    const { groups } = (await agent.get("/api/v1/reports/agent").expect(200)).body.data;
    for (const g of groups) {
      if (!g.creditRating || g.creditRating.rated === false) {
        expect(g.needsSupport).toBe(true);
      }
    }
  });

  it("4. can open the groups on their caseload", async () => {
    const { groups } = (await agent.get("/api/v1/reports/agent").expect(200)).body.data;
    for (const g of groups) {
      await agent.get(`/api/v1/groups/${g.id}`).expect(200);
    }
  });

  it("5. cannot see or answer who is asking to join", async () => {
    // Deciding membership is the group's own business, not the agent's —
    // `members:write` alone would have let an agent do it across a caseload.
    const { groups } = (await agent.get("/api/v1/reports/agent").expect(200)).body.data;
    const res = await agent.get(`/api/v1/groups/${groups[0].id}/join-requests`).expect(403);
    expect(res.body.error.code).toBe("NOT_A_GROUP_OFFICIAL");
  });

  it("6. has no member passbook of their own", async () => {
    const res = await agent.get("/api/v1/members/me").expect(400);
    expect(res.body.error.code).toBe("NOT_A_MEMBER_ACCOUNT");
  });

  it("7. is not an administrator", async () => {
    await agent.get("/api/v1/users").expect(403);
  });

  it("8. signs out cleanly", async () => {
    await agent.post("/api/v1/auth/logout").send({}).expect(200);
    await agent.get("/api/v1/reports/agent").expect(401);
  });
});
