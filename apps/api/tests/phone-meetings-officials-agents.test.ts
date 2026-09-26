import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword, meetingSteps } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

/**
 * 26 Sep 2026 fixes, end to end through the routes:
 * - a meeting closed on a phone reads as done on the console (steps, keys, notes);
 * - one holder per single office, whichever path appoints them, with history;
 * - an election decides the office;
 * - several agents / CBTs per group, each with full access;
 * - partners see only their own groups' applications, and no agent contacts.
 */
const app = createApp();

async function signIn(role: string) {
  const account = demoAccounts.find((entry) => entry.role === role)!;
  const response = await request(app).post("/api/v1/auth/login").send({ phone: account.phone, password: demoPassword }).expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

async function signInAs(phone: string, password: string) {
  const response = await request(app).post("/api/v1/auth/login").send({ phone, password }).expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

describe("phone meetings, officials and agents", () => {
  let admin: string[];
  let groupLogin: string[];
  let groupId: string;

  beforeAll(async () => {
    await seedDatabase();
    admin = await signIn("IWL_ADMIN");
    groupLogin = await signIn("GROUP_ACCOUNT");
    const account = demoAccounts.find((entry) => entry.role === "GROUP_ACCOUNT")!;
    groupId = (await prisma.user.findFirstOrThrow({ where: { email: account.email }, select: { groupId: true } })).groupId!;
  }, 180000);

  it("completes a phone meeting's workflow, keys and notes when the phone closes it", async () => {
    const created = await request(app)
      .post(`/api/v1/groups/${groupId}/meetings`)
      .set("Cookie", groupLogin)
      .send({ title: `Phone meeting ${Date.now()}`, scheduledAt: new Date().toISOString(), source: "PHONE" })
      .expect(201);
    const meetingId = created.body.data.id as string;
    const officials = await prisma.member.findMany({
      where: { groupId, status: "ACTIVE", role: { in: ["CHAIRPERSON", "SECRETARY", "TREASURER"] } },
      select: { id: true },
      take: 3
    });
    const url = `/api/v1/groups/${groupId}/meetings/${meetingId}/phone-lifecycle`;
    await request(app).post(url).set("Cookie", groupLogin).send({ event: "STARTED", at: new Date().toISOString() }).expect(200);
    const closed = await request(app)
      .post(url)
      .set("Cookie", groupLogin)
      .send({
        event: "CLOSED",
        at: new Date().toISOString(),
        unlockedByMemberIds: officials.map((member) => member.id),
        notes: "Agreed to raise the social fund."
      })
      .expect(200);

    const meeting = closed.body.data;
    expect(meeting.status).toBe("SEALED");
    expect(meeting.steps).toHaveLength(meetingSteps.length);
    expect(meeting.steps.every((step: { status: string }) => step.status === "COMPLETED")).toBe(true);
    expect(meeting.keySubmissions).toHaveLength(officials.length);
    expect(meeting.minutes).toBe("Agreed to raise the social fund.");
    expect(meeting.unlockStatus).toBe(officials.length >= 3 ? "OFFICIALS_VERIFIED" : "PHONE_KEYS_BELOW_QUORUM");

    // A retry changes nothing.
    const again = await request(app)
      .post(url)
      .set("Cookie", groupLogin)
      .send({ event: "CLOSED", at: new Date().toISOString(), unlockedByMemberIds: officials.map((member) => member.id) })
      .expect(200);
    expect(again.body.data.keySubmissions).toHaveLength(officials.length);
  });

  it("says a phone meeting had no keys instead of leaving it pending", async () => {
    const created = await request(app)
      .post(`/api/v1/groups/${groupId}/meetings`)
      .set("Cookie", groupLogin)
      .send({ title: `Phone meeting no keys ${Date.now()}`, scheduledAt: new Date().toISOString(), source: "PHONE" })
      .expect(201);
    const closed = await request(app)
      .post(`/api/v1/groups/${groupId}/meetings/${created.body.data.id}/phone-lifecycle`)
      .set("Cookie", groupLogin)
      .send({ event: "CLOSED", at: new Date().toISOString() })
      .expect(200);
    expect(closed.body.data.unlockStatus).toBe("NOT_RECORDED_ON_PHONE");
    expect(closed.body.data.steps.every((step: { status: string }) => step.status === "COMPLETED")).toBe(true);
  });

  it("keeps one chairperson when the console edits roles, and records the history", async () => {
    const [first, second] = await prisma.member.findMany({
      where: { groupId, status: "ACTIVE", role: "MEMBER" },
      select: { id: true },
      take: 2
    });
    for (const member of [first!, second!]) {
      await request(app)
        .patch(`/api/v1/groups/${groupId}/members/${member.id}`)
        .set("Cookie", admin)
        .send({ role: "CHAIRPERSON" })
        .expect(200);
    }
    const chairs = await prisma.member.findMany({ where: { groupId, role: "CHAIRPERSON" }, select: { id: true } });
    expect(chairs.map((chair) => chair.id)).toEqual([second!.id]);
    const firstTerm = await prisma.memberRoleAssignment.findFirstOrThrow({
      where: { groupId, memberId: first!.id, role: "CHAIRPERSON" }
    });
    expect(firstTerm.endedAt).not.toBeNull();
    const current = await prisma.memberRoleAssignment.findFirstOrThrow({
      where: { groupId, memberId: second!.id, role: "CHAIRPERSON", endedAt: null }
    });
    expect(current).toBeTruthy();
  });

  it("refuses to make a member a village agent", async () => {
    const member = await prisma.member.findFirstOrThrow({ where: { groupId, status: "ACTIVE" }, select: { id: true } });
    await request(app)
      .patch(`/api/v1/groups/${groupId}/members/${member.id}`)
      .set("Cookie", admin)
      .send({ role: "VILLAGE_AGENT" })
      .expect(400);
  });

  it("gives the office to the winner when an election closes", async () => {
    const candidate = await prisma.member.findFirstOrThrow({
      where: { groupId, status: "ACTIVE", role: "MEMBER" },
      select: { id: true }
    });
    // Voting is a programme module; make sure it is on for this group.
    await prisma.programme.updateMany({
      where: { OR: [{ groups: { some: { id: groupId } } }, { groupLinks: { some: { groupId } } }] },
      data: { votingEnabled: true }
    });

    const poll = await prisma.poll.create({
      data: {
        groupId,
        title: "Treasurer election",
        type: "ROLE_ELECTION",
        targetRole: "TREASURER",
        status: "OPEN",
        options: { create: [{ label: "Candidate", memberId: candidate.id, position: 0 }] }
      },
      include: { options: true }
    });
    await prisma.pollVote.create({ data: { pollId: poll.id, optionId: poll.options[0]!.id, memberId: candidate.id } });

    const closed = await request(app).post(`/api/v1/polls/${poll.id}/close`).set("Cookie", groupLogin).expect(200);
    expect(closed.body.data.elected).toMatchObject({ memberId: candidate.id, role: "TREASURER" });
    const treasurers = await prisma.member.findMany({ where: { groupId, role: "TREASURER" }, select: { id: true } });
    expect(treasurers.map((row) => row.id)).toEqual([candidate.id]);
  });

  it("lets several agents serve one group, each with full access", async () => {
    const lead = await prisma.villageAgent.findFirstOrThrow({ where: { userAccounts: { some: {} } }, select: { id: true } });
    const second = await prisma.villageAgent.create({
      data: { name: "Second CBT", phone: `+2547${Date.now().toString().slice(-8)}` },
      select: { id: true }
    });
    const password = "A-long-enough-password-1";
    const phone = `+2541${Date.now().toString().slice(-8)}`;
    await request(app)
      .post("/api/v1/users")
      .set("Cookie", admin)
      .send({ name: "Second CBT", email: `second.cbt.${Date.now()}@intellicash.test`, phone, password, role: "VILLAGE_AGENT", villageAgentId: second.id })
      .expect(201);
    const secondLogin = await signInAs(phone, password);

    await request(app)
      .patch(`/api/v1/groups/${groupId}`)
      .set("Cookie", admin)
      .send({ agentIds: [lead.id, second.id], leadAgentId: lead.id })
      .expect(200);
    await request(app).get(`/api/v1/groups/${groupId}`).set("Cookie", secondLogin).expect(200);
    const detail = await request(app).get(`/api/v1/groups/${groupId}`).set("Cookie", admin).expect(200);
    expect(detail.body.data.agentLinks.map((link: { villageAgent: { id: string } }) => link.villageAgent.id).sort()).toEqual(
      [lead.id, second.id].sort()
    );
    expect(detail.body.data.villageAgent.id).toBe(lead.id);

    // The old single field adds a lead without removing the other agent.
    await request(app).patch(`/api/v1/groups/${groupId}`).set("Cookie", admin).send({ villageAgentId: second.id }).expect(200);
    const afterLead = await prisma.groupAgent.findMany({ where: { groupId }, select: { villageAgentId: true, isLead: true } });
    expect(afterLead).toHaveLength(2);
    expect(afterLead.find((link) => link.isLead)?.villageAgentId).toBe(second.id);

    // Removing one agent leaves the other in place.
    await request(app).patch(`/api/v1/groups/${groupId}`).set("Cookie", admin).send({ agentIds: [second.id] }).expect(200);
    await request(app).get(`/api/v1/groups/${groupId}`).set("Cookie", secondLogin).expect(200);
    const group = await prisma.group.findUniqueOrThrow({ where: { id: groupId }, select: { villageAgentId: true } });
    expect(group.villageAgentId).toBe(second.id);

    // Restore the seed's agent so other checks see the usual caseload.
    await request(app).patch(`/api/v1/groups/${groupId}`).set("Cookie", admin).send({ agentIds: [lead.id, second.id], leadAgentId: lead.id }).expect(200);
  });

  it("shows a partner only its own groups' loan applications, and no agent contacts", async () => {
    const partnerLogin = await signIn("PARTNER_OFFICER");
    const partnerAccount = demoAccounts.find((entry) => entry.role === "PARTNER_OFFICER")!;
    const partnerId = (await prisma.user.findFirstOrThrow({ where: { email: partnerAccount.email }, select: { partnerId: true } })).partnerId!;

    const outside = await prisma.group.create({
      data: { name: "Outside Group", code: `IWL-OUT-${Date.now().toString().slice(-6)}`, phase: "MOBILISATION", county: "Kisumu" },
      select: { id: true }
    });
    const product = await prisma.externalLoanProduct.create({
      data: {
        partnerId,
        name: "Scope test loan",
        slug: `scope-test-${Date.now()}`,
        description: "test",
        minAmountCents: 100,
        maxAmountCents: 1_000_000,
        interestRateBps: 1000,
        termMonths: 6
      },
      select: { id: true }
    });
    const application = await prisma.externalLoanApplication.create({
      data: { productId: product.id, groupId: outside.id, amountCents: 50_000, purpose: "test" },
      select: { id: true }
    });

    const listed = await request(app).get("/api/v1/external-loans/applications").set("Cookie", partnerLogin).expect(200);
    expect(listed.body.data.map((row: { id: string }) => row.id)).not.toContain(application.id);

    const groups = await request(app).get("/api/v1/groups").set("Cookie", partnerLogin).expect(200);
    expect(groups.body.data.map((row: { id: string }) => row.id)).not.toContain(outside.id);
    for (const row of groups.body.data as Array<{ villageAgent: { phone: string; email: string | null } | null }>) {
      if (row.villageAgent) {
        expect(row.villageAgent.phone).toBe("");
        expect(row.villageAgent.email).toBeNull();
      }
    }

    await prisma.externalLoanApplication.delete({ where: { id: application.id } });
    await prisma.externalLoanProduct.delete({ where: { id: product.id } });
    await prisma.group.delete({ where: { id: outside.id } });
  });

  it("shows a group login no one else's service bookings", async () => {
    const response = await request(app).get("/api/v1/intelli-store/booking-requests").set("Cookie", groupLogin);
    // 403 when the store module is off for the group; otherwise an empty list.
    if (response.status === 200) expect(response.body.data).toEqual([]);
    else expect(response.status).toBe(403);
  });
});
