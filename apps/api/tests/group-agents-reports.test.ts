import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

function cookieOf(response: request.Response) {
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

async function signInAs(phone: string, password: string) {
  return cookieOf(await request(app).post("/api/v1/auth/login").send({ phone, password }).expect(200));
}

type Totals = Record<string, unknown>;

/**
 * Several VA / CBTs per group (26 Sep 2026).
 *
 * A second agent must reach the group everywhere the lead does (caseload,
 * group statement, portfolio), be counted in the programme report, and be
 * named wherever the report lists a group's agents, while every total stays
 * exactly what it was: a group served by two agents is still one group.
 */
describe("a group served by several agents", () => {
  let admin: string[];
  let leadLogin: string[];
  let secondLogin: string[];
  let leadId: string;
  let secondId: string;
  let groupId: string;
  const before: { portfolio?: Totals; programme?: Totals; analytics?: Totals; foundationFunds?: number } = {};

  beforeAll(async () => {
    await seedDatabase();
    const adminAccount = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
    const agentAccount = demoAccounts.find((account) => account.role === "VILLAGE_AGENT")!;
    admin = await signInAs(adminAccount.phone, demoPassword);
    leadLogin = await signInAs(agentAccount.phone, demoPassword);

    const leadUser = await prisma.user.findFirstOrThrow({ where: { role: "VILLAGE_AGENT" }, select: { villageAgentId: true } });
    leadId = leadUser.villageAgentId!;
    const group = await prisma.group.findFirstOrThrow({ where: { villageAgentId: leadId, isDemo: false }, select: { id: true } });
    groupId = group.id;

    const second = await prisma.villageAgent.create({
      data: { name: "Second CBT For Reports", phone: `+2547${Date.now().toString().slice(-8)}` },
      select: { id: true }
    });
    secondId = second.id;
    const password = "A-long-enough-password-2";
    const phone = `+2541${Date.now().toString().slice(-8)}`;
    await request(app)
      .post("/api/v1/users")
      .set("Cookie", admin)
      .send({ name: "Second CBT For Reports", email: `second.reports.${Date.now()}@intellicash.test`, phone, password, role: "VILLAGE_AGENT", villageAgentId: secondId })
      .expect(201);
    secondLogin = await signInAs(phone, password);

    // The figures BEFORE the group gains its second agent.
    before.portfolio = (await request(app).get("/api/v1/reports/portfolio-financials").set("Cookie", admin).expect(200)).body.data.totals;
    const programme = (await request(app).get("/api/v1/reports/programme-performance").set("Cookie", admin).expect(200)).body.data;
    before.programme = { reach: programme.reach.groups, savings: programme.performance.totals.savingsCents, loans: programme.performance.totals.loanBookCents };
    before.analytics = (await request(app).get("/api/v1/analytics/portfolio").set("Cookie", admin).expect(200)).body.data;
    before.foundationFunds = (await request(app).get("/api/v1/reports/foundation").set("Cookie", admin).expect(200)).body.data.fundAccounts.length;

    await request(app)
      .patch(`/api/v1/groups/${groupId}`)
      .set("Cookie", admin)
      .send({ agentIds: [leadId, secondId], leadAgentId: leadId })
      .expect(200);
  }, 120000);

  it("gives the second agent the group in lists, the group page and its statement", async () => {
    const groups = (await request(app).get("/api/v1/groups").set("Cookie", secondLogin).expect(200)).body.data;
    const rows = (groups.items ?? groups) as Array<{ id: string }>;
    expect(rows.map((row) => row.id)).toEqual([groupId]);

    await request(app).get(`/api/v1/groups/${groupId}`).set("Cookie", secondLogin).expect(200);
    const statement = await request(app).get(`/api/v1/reports/group/${groupId}`).set("Cookie", secondLogin).expect(200);
    expect(statement.body.data.group.id).toBe(groupId);
  });

  it("puts the group in BOTH agents' caseload reports", async () => {
    const second = (await request(app).get("/api/v1/reports/agent").set("Cookie", secondLogin).expect(200)).body.data;
    expect(second.groups.map((group: { id: string }) => group.id)).toEqual([groupId]);
    expect(second.summary.groups).toBe(1);

    const lead = (await request(app).get("/api/v1/reports/agent").set("Cookie", leadLogin).expect(200)).body.data;
    expect(lead.groups.map((group: { id: string }) => group.id)).toContain(groupId);
  });

  it("lists the group under each agent on the VA / CBT page, lead marked", async () => {
    const agents = (await request(app).get("/api/v1/village-agents").set("Cookie", admin).expect(200)).body.data as Array<{
      id: string;
      groups: Array<{ id: string; isLead: boolean }>;
      _count: { groups: number };
    }>;
    const second = agents.find((agent) => agent.id === secondId)!;
    const lead = agents.find((agent) => agent.id === leadId)!;
    expect(second.groups).toEqual([expect.objectContaining({ id: groupId, isLead: false })]);
    expect(second._count.groups).toBe(1);
    expect(lead.groups.find((group) => group.id === groupId)?.isLead).toBe(true);
  });

  it("counts both agents in the programme report without counting the group twice", async () => {
    const programme = (await request(app).get("/api/v1/reports/programme-performance").set("Cookie", admin).expect(200)).body.data;
    expect(programme.reach.groups).toBe(before.programme!.reach);
    expect(programme.performance.totals.savingsCents).toBe(before.programme!.savings);
    expect(programme.performance.totals.loanBookCents).toBe(before.programme!.loans);
    const second = programme.delivery.cbts.find((row: { id: string }) => row.id === secondId);
    const lead = programme.delivery.cbts.find((row: { id: string }) => row.id === leadId);
    expect(second?.groupsAssigned).toBe(1);
    expect(lead?.groupsAssigned).toBeGreaterThanOrEqual(1);
  });

  it("leaves every portfolio total exactly as it was", async () => {
    const portfolio = (await request(app).get("/api/v1/reports/portfolio-financials").set("Cookie", admin).expect(200)).body.data.totals;
    expect(portfolio).toEqual(before.portfolio);
    const analytics = (await request(app).get("/api/v1/analytics/portfolio").set("Cookie", admin).expect(200)).body.data;
    expect(analytics).toEqual(before.analytics);
    const foundation = (await request(app).get("/api/v1/reports/foundation").set("Cookie", admin).expect(200)).body.data;
    expect(foundation.fundAccounts.length).toBe(before.foundationFunds);
  });

  it("names every agent of the group in the report data, lead first", async () => {
    const foundation = (await request(app).get("/api/v1/reports/foundation").set("Cookie", admin).expect(200)).body.data;
    const account = foundation.fundAccounts.find((row: { group: { id: string } }) => row.group.id === groupId);
    const names = account.group.agentLinks.map((link: { villageAgent: { name: string } }) => link.villageAgent.name);
    expect(names).toHaveLength(2);
    expect(names[1]).toBe("Second CBT For Reports");
  });

  it("lets the second agent do an agent's work in the group", async () => {
    await request(app)
      .put(`/api/v1/groups/${groupId}/documents/REGISTRATION_CERTIFICATE`)
      .set("Cookie", secondLogin)
      .send({ presence: "PRESENT" })
      .expect(200);
  });

  it("taking the group off one agent's caseload leaves the other agent in place", async () => {
    await request(app).patch(`/api/v1/village-agents/${secondId}`).set("Cookie", admin).send({ groupIds: [] }).expect(200);
    await request(app).get(`/api/v1/groups/${groupId}`).set("Cookie", secondLogin).expect(404);
    await request(app).get(`/api/v1/groups/${groupId}`).set("Cookie", leadLogin).expect(200);
    const group = await prisma.group.findUniqueOrThrow({ where: { id: groupId }, select: { villageAgentId: true } });
    expect(group.villageAgentId).toBe(leadId);
    const links = await prisma.groupAgent.findMany({ where: { groupId }, select: { villageAgentId: true, isLead: true } });
    expect(links).toEqual([{ villageAgentId: leadId, isLead: true }]);
  });
});
