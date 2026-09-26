import request from "supertest";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword, rolePermissions, type Role } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

async function signIn(role: Role) {
  const account = demoAccounts.find((candidate) => candidate.role === role)!;
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: account.phone, password: demoPassword })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

const WRITES = [
  "groups:write",
  "members:write",
  "meetings:write",
  "ledger:write",
  "votes:write",
  "documents:write",
  "visits:write",
  "store:write",
  "users:write"
] as const;

/**
 * "A partner should not edit group welfare and others" (26 Sep 2026).
 *
 * Partners, lenders and read-only viewers oversee groups; they never change a
 * group's records. The rule is enforced where every guard's answer comes from,
 * so it holds even when a stored permission row says otherwise.
 */
describe("oversight roles are view-only", () => {
  let groupId: string;
  let stored: { role: string; permissionsJson: string }[] = [];

  beforeAll(async () => {
    await seedDatabase();
    stored = await prisma.rolePermissionTemplate.findMany({ select: { role: true, permissionsJson: true } });
    const group = await prisma.group.findFirst({ orderBy: { createdAt: "asc" } });
    groupId = group!.id;

    // Someone edits the rows directly, granting every group write.
    for (const role of ["PARTNER_OFFICER", "LENDER", "READ_ONLY"]) {
      const held = JSON.parse(stored.find((row) => row.role === role)!.permissionsJson) as string[];
      await prisma.rolePermissionTemplate.update({
        where: { role },
        data: { permissionsJson: JSON.stringify([...new Set([...held, ...WRITES])]) }
      });
    }
  }, 60000);

  afterAll(async () => {
    for (const row of stored) {
      await prisma.rolePermissionTemplate.update({ where: { role: row.role }, data: { permissionsJson: row.permissionsJson } });
    }
  });

  for (const role of ["PARTNER_OFFICER", "LENDER", "READ_ONLY"] as const) {
    it(`${role} holds no group write even when its stored row grants them`, async () => {
      const cookies = await signIn(role);
      const me = await request(app).get("/api/v1/auth/me").set("Cookie", cookies).expect(200);
      const held = me.body.data.permissions as string[];
      for (const permission of WRITES) expect(held).not.toContain(permission);
      // A lender still records its own programme contributions.
      if (role === "LENDER") expect(held).toContain("payments:write");

      const refused = [
        request(app)
          .post(`/api/v1/groups/${groupId}/welfare-expenses`)
          .set("Cookie", cookies)
          .send({ amountCents: 1000, category: "MEDICAL", payeeName: "Clinic" }),
        request(app).patch(`/api/v1/groups/${groupId}`).set("Cookie", cookies).send({ name: "Renamed" }),
        request(app)
          .post(`/api/v1/groups/${groupId}/members`)
          .set("Cookie", cookies)
          .send({ fullName: "Someone New", phone: "+254711000999" }),
        request(app)
          .post(`/api/v1/groups/${groupId}/meetings`)
          .set("Cookie", cookies)
          .send({ title: "Extra meeting", scheduledAt: new Date().toISOString() }),
        request(app)
          .post(`/api/v1/groups/${groupId}/ledger`)
          .set("Cookie", cookies)
          .send({ type: "SAVINGS", amountCents: 1000, direction: "CREDIT" }),
        request(app).put(`/api/v1/groups/${groupId}/policy`).set("Cookie", cookies).send({ shareValueCents: 1 })
      ];
      for (const response of await Promise.all(refused)) {
        expect(response.status).toBe(403);
      }
    });
  }

  it("the console refuses to grant a partner a write", async () => {
    const admin = await signIn("IWL_ADMIN");
    const response = await request(app)
      .patch("/api/v1/access-control/roles/PARTNER_OFFICER/permissions")
      .set("Cookie", admin)
      .send({ permissions: [...rolePermissions.PARTNER_OFFICER, "ledger:write"] })
      .expect(400);
    expect(response.body.error.code).toBe("ROLE_PERMISSION_GUARD");
    expect(response.body.error.message).toMatch(/view groups but not change them/);
  });

  it("the console refuses to give a group or member login a platform permission", async () => {
    const admin = await signIn("IWL_ADMIN");
    for (const role of ["GROUP_ACCOUNT", "MEMBER"] as const) {
      const response = await request(app)
        .patch(`/api/v1/access-control/roles/${role}/permissions`)
        .set("Cookie", admin)
        .send({ permissions: [...rolePermissions[role], "users:write"] })
        .expect(400);
      expect(response.body.error.message).toMatch(/work inside one group/);
    }
  });
});

describe("who may act for a group", () => {
  let agentGroupId: string;
  let memberGroupId: string;
  let agentCookies: string[];
  let memberCookies: string[];
  let lenderCookies: string[];
  let partnerCookies: string[];
  let adminCookies: string[];

  beforeAll(async () => {
    await seedDatabase();
    agentCookies = await signIn("VILLAGE_AGENT");
    memberCookies = await signIn("MEMBER");
    lenderCookies = await signIn("LENDER");
    partnerCookies = await signIn("PARTNER_OFFICER");
    adminCookies = await signIn("IWL_ADMIN");

    const agent = await prisma.user.findFirst({ where: { role: "VILLAGE_AGENT" }, select: { villageAgentId: true } });
    const agentGroup = await prisma.group.findFirst({
      where: { villageAgentId: agent!.villageAgentId! },
      select: { id: true }
    });
    agentGroupId = agentGroup!.id;

    const member = await prisma.user.findFirst({ where: { role: "MEMBER" }, select: { groupId: true } });
    memberGroupId = member!.groupId!;
  }, 60000);

  it("an agent adds members but cannot appoint a chairperson", async () => {
    await request(app)
      .post(`/api/v1/groups/${agentGroupId}/members`)
      .set("Cookie", agentCookies)
      .send({ fullName: "Agent Added Member", phone: "+254711000101" })
      .expect(201);

    const chair = await request(app)
      .post(`/api/v1/groups/${agentGroupId}/members`)
      .set("Cookie", agentCookies)
      .send({ fullName: "Agent Added Chair", phone: "+254711000102", role: "CHAIRPERSON" })
      .expect(403);
    expect(chair.body.error.message).toMatch(/appoint officials/);

    const someone = await prisma.member.findFirst({ where: { groupId: agentGroupId, role: "MEMBER" } });
    await request(app)
      .patch(`/api/v1/groups/${agentGroupId}/members/${someone!.id}`)
      .set("Cookie", agentCookies)
      .send({ role: "TREASURER" })
      .expect(403);
  });

  it("an agent's phone registers an official as an ordinary member", async () => {
    const response = await request(app)
      .post(`/api/v1/groups/${agentGroupId}/members/sync`)
      .set("Cookie", agentCookies)
      .send({ fullName: "Synced Secretary", phone: "+254711000103", role: "SECRETARY" })
      .expect(201);
    const member = await prisma.member.findUnique({ where: { id: response.body.data.id } });
    expect(member?.role).toBe("MEMBER");
  });

  it("an agent cannot issue a member's PIN or set their password", async () => {
    const someone = await prisma.member.findFirst({ where: { groupId: agentGroupId } });
    await request(app)
      .post(`/api/v1/groups/${agentGroupId}/members/${someone!.id}/pin`)
      .set("Cookie", agentCookies)
      .send({})
      .expect(403);
    await request(app)
      .put(`/api/v1/groups/${agentGroupId}/members/${someone!.id}/account/password`)
      .set("Cookie", agentCookies)
      .send({ password: "taken-over" })
      .expect(403);
    await request(app)
      .post(`/api/v1/groups/${agentGroupId}/members/${someone!.id}/account`)
      .set("Cookie", agentCookies)
      .send({ password: "taken-over" })
      .expect(403);
  });

  it("an agent cannot file a join request", async () => {
    const group = await prisma.group.findUnique({ where: { id: agentGroupId }, select: { code: true } });
    await request(app)
      .post("/api/v1/members/me/join-requests")
      .set("Cookie", agentCookies)
      .send({ groupCode: group!.code })
      .expect(403);
  });

  it("a member votes but cannot record a resolution, open a poll or prepare a phone", async () => {
    const resolution = await request(app)
      .post(`/api/v1/groups/${memberGroupId}/votes`)
      .set("Cookie", memberCookies)
      .send({
        resolutionType: "INTERNAL_LOAN_APPROVAL",
        motion: "Approve my own loan",
        result: "PASSED",
        quorumRequired: 50,
        yesCount: 20,
        noCount: 0,
        totalEligible: 20
      })
      .expect(403);
    expect(resolution.body.error.message).toMatch(/record a resolution/);

    await request(app)
      .post(`/api/v1/groups/${memberGroupId}/polls`)
      .set("Cookie", memberCookies)
      .send({ type: "DECISION", title: "Raise the share value?", options: [{ label: "Yes" }, { label: "No" }] })
      .expect(403);

    const someone = await prisma.member.findFirst({ where: { groupId: memberGroupId } });
    await request(app)
      .post(`/api/v1/groups/${memberGroupId}/offline-devices/prepare`)
      .set("Cookie", memberCookies)
      .send({ deviceId: "member-phone", memberPins: [{ memberId: someone!.id, pin: "1234" }] })
      .expect(403);
  });

  it("only the group's own account or an admin applies for a loan in its name", async () => {
    const partner = await prisma.partner.findFirst({ select: { id: true } });
    const product = await prisma.externalLoanProduct.create({
      data: {
        partnerId: partner!.id,
        name: "Test working capital",
        slug: `test-working-capital-${Date.now()}`,
        description: "For the permission test",
        minAmountCents: 100_000,
        maxAmountCents: 10_000_000,
        interestRateBps: 1200,
        termMonths: 6
      }
    });
    const body = { productId: product.id, amountCents: 500_000, purpose: "Buy stock for the group" };

    const byMember = await request(app)
      .post("/api/v1/external-loans/applications")
      .set("Cookie", memberCookies)
      .send(body)
      .expect(403);
    expect(byMember.body.error.message).toMatch(/group's own account/);

    // A lender is view-only over groups: no store:write at all.
    await request(app)
      .post("/api/v1/external-loans/applications")
      .set("Cookie", lenderCookies)
      .send({ ...body, groupId: memberGroupId })
      .expect(403);
  });

  it("partners see welfare as amounts and categories, never who received it", async () => {
    const visible = await request(app).get("/api/v1/groups").set("Cookie", partnerCookies).expect(200);
    const rows = (visible.body.data.items ?? visible.body.data) as { id: string }[];
    const groupId = rows[0]!.id;
    const payee = await prisma.member.findFirst({ where: { groupId } });
    const meeting = await prisma.meeting.create({
      data: { groupId, title: "Welfare meeting", scheduledAt: new Date(), status: "IN_PROGRESS", openedAt: new Date() }
    });
    // Enough in the fund to pay from.
    await prisma.fundAccount.updateMany({ where: { groupId, type: "SOCIAL" }, data: { balanceCents: { increment: 50_000 } } });

    await request(app)
      .post(`/api/v1/groups/${groupId}/welfare-expenses`)
      .set("Cookie", adminCookies)
      .send({ amountCents: 5_000, category: "BEREAVEMENT", payeeMemberId: payee!.id, note: "Funeral of her mother", meetingId: meeting.id })
      .expect(201);

    const asAdmin = await request(app).get(`/api/v1/groups/${groupId}/welfare-expenses`).set("Cookie", adminCookies).expect(200);
    expect(JSON.stringify(asAdmin.body.data.expenses)).toContain("Funeral of her mother");

    const asPartner = await request(app)
      .get(`/api/v1/groups/${groupId}/welfare-expenses`)
      .set("Cookie", partnerCookies)
      .expect(200);
    const text = JSON.stringify(asPartner.body.data.expenses);
    expect(asPartner.body.data.expenses.length).toBeGreaterThan(0);
    expect(text).not.toContain("Funeral of her mother");
    expect(text).not.toContain(payee!.fullName);
    expect(text).not.toContain(payee!.id);
    expect(asPartner.body.data.expenses[0].category).toBe("BEREAVEMENT");
    expect(asPartner.body.data.spentCents).toBe(asAdmin.body.data.spentCents);
  });
});
