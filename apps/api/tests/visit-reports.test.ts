import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { seedMentorshipReferenceData } from "../prisma/seed-mentorship-topics";

const app = createApp();
const DAY = 24 * 60 * 60 * 1000;

async function signIn(identifier: string, password = demoPassword) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: identifier, password })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

/**
 * The programme-level questions: who has been visited, how they scored over
 * time, what is outstanding, and the group's enterprise.
 */
describe("visit reporting, topics and the business profile", () => {
  let adminCookies: string[];
  let agentCookies: string[];
  let groupId: string;
  let visitId: string;

  beforeAll(async () => {
    await seedDatabase();
    await seedMentorshipReferenceData(prisma);
    await prisma.groupBusinessProfileVersion.deleteMany({});
    await prisma.groupBusinessProfile.deleteMany({});
    await prisma.visitActionItem.deleteMany({});
    await prisma.groupVisit.deleteMany({});

    const admin = demoAccounts.find((a) => a.role === "IWL_ADMIN")!;
    const agent = demoAccounts.find((a) => a.role === "VILLAGE_AGENT")!;
    adminCookies = await signIn(admin.phone);
    agentCookies = await signIn(agent.phone);

    const agentUser = await prisma.user.findFirst({
      where: { role: "VILLAGE_AGENT" },
      select: { id: true, villageAgentId: true }
    });
    const group = await prisma.group.findFirst({
      where: { villageAgentId: agentUser!.villageAgentId! },
      select: { id: true }
    });
    groupId = group!.id;

    const visit = await prisma.groupVisit.create({
      data: {
        groupId,
        clientRequestId: "visit-reports-1",
        visitType: "FOLLOW_UP",
        startedAt: new Date(),
        villageAgentId: agentUser!.villageAgentId!
      }
    });
    visitId = visit.id;
  }, 180000);

  describe("coverage", () => {
    it("separates groups never visited from groups visited long ago", async () => {
      // One number cannot say both. A group visited once a year ago is not
      // covered in any useful sense, but it is not unvisited either.
      const response = await request(app)
        .get("/api/v1/reports/visits")
        .set("Cookie", adminCookies)
        .expect(200);

      const { coverage, neverVisited } = response.body.data;
      expect(coverage.groups).toBeGreaterThan(0);
      expect(coverage.visited).toBe(1);
      expect(coverage.neverVisited).toBe(coverage.groups - 1);
      expect(neverVisited.length).toBe(coverage.neverVisited);
      expect(response.body.data.staleGroups).toEqual([]);
    });

    it("counts open action items and flags what is overdue", async () => {
      await request(app)
        .post(`/api/v1/visits/${visitId}/action-items`)
        .set("Cookie", agentCookies)
        .send({
          title: "Write up the ledger",
          dueDate: new Date(Date.now() - 30 * DAY).toISOString()
        })
        .expect(201);

      const response = await request(app)
        .get("/api/v1/reports/visits")
        .set("Cookie", adminCookies)
        .expect(200);

      expect(response.body.data.actions.open).toBe(1);
      expect(response.body.data.actions.overdue).toBe(1);
      expect(response.body.data.actions.worstDaysOverdue).toBeGreaterThanOrEqual(29);
    });

    it("scopes the report to the agent's own caseload", async () => {
      const adminView = await request(app)
        .get("/api/v1/reports/visits")
        .set("Cookie", adminCookies)
        .expect(200);
      const agentView = await request(app)
        .get("/api/v1/reports/visits")
        .set("Cookie", agentCookies)
        .expect(200);

      // An agent must not be able to read coverage for groups they do not hold.
      expect(agentView.body.data.coverage.groups).toBeLessThanOrEqual(
        adminView.body.data.coverage.groups
      );
    });
  });

  describe("the assessment trend", () => {
    it("is empty, not broken, for a group never assessed", async () => {
      const response = await request(app)
        .get(`/api/v1/groups/${groupId}/visit-trend`)
        .set("Cookie", adminCookies)
        .expect(200);

      expect(response.body.data.visits).toBe(0);
      expect(response.body.data.overall).toEqual([]);
      expect(response.body.data.sections).toEqual([]);
    });

    it("refuses a group outside the agent's caseload", async () => {
      const detached = await prisma.group.findFirst({
        where: { villageAgentId: null },
        select: { id: true }
      });
      const otherGroupId =
        detached?.id ??
        (
          await prisma.group.update({
            where: {
              id: (
                await prisma.group.findFirstOrThrow({
                  where: { id: { not: groupId } },
                  select: { id: true }
                })
              ).id
            },
            data: { villageAgentId: null },
            select: { id: true }
          })
        ).id;

      await request(app)
        .get(`/api/v1/groups/${otherGroupId}/visit-trend`)
        .set("Cookie", agentCookies)
        .expect(404);
    });
  });

  describe("the group business profile", () => {
    it("says the group has not been asked, rather than inventing an empty one", async () => {
      const response = await request(app)
        .get(`/api/v1/groups/${groupId}/business-profile`)
        .set("Cookie", adminCookies)
        .expect(200);

      expect(response.body.data.recorded).toBe(false);
      expect(response.body.data.profile).toBeNull();
    });

    it("records the profile and computes the margin", async () => {
      const response = await request(app)
        .put(`/api/v1/groups/${groupId}/business-profile`)
        .set("Cookie", agentCookies)
        .send({
          enterpriseType: "Poultry",
          monthlyRevenueCents: 4500000,
          monthlyCostsCents: 1200000,
          employsPeople: 4,
          visitId
        })
        .expect(200);

      expect(response.body.data.profile.enterpriseType).toBe("Poultry");
      // Computed on read, never stored — a margin column goes stale the moment
      // either figure is edited without it.
      expect(response.body.data.profile.monthlyMarginCents).toBe(3300000);
    });

    it("keeps a snapshot per visit so growth between visits is a query", async () => {
      const second = await prisma.groupVisit.create({
        data: {
          groupId,
          clientRequestId: "visit-reports-2",
          visitType: "FOLLOW_UP",
          startedAt: new Date()
        }
      });

      await request(app)
        .put(`/api/v1/groups/${groupId}/business-profile`)
        .set("Cookie", agentCookies)
        .send({ enterpriseType: "Poultry", monthlyRevenueCents: 6000000, visitId: second.id })
        .expect(200);

      const response = await request(app)
        .get(`/api/v1/groups/${groupId}/business-profile`)
        .set("Cookie", adminCookies)
        .expect(200);

      expect(response.body.data.history).toHaveLength(2);
      const revenues = response.body.data.history.map(
        (v: { monthlyRevenueCents: number }) => v.monthlyRevenueCents
      );
      expect(revenues).toContain(4500000);
      expect(revenues).toContain(6000000);
    });

    it("corrects the same visit in place instead of appending", async () => {
      // An agent revising a figure during one visit must not leave two
      // snapshots for that occasion.
      await request(app)
        .put(`/api/v1/groups/${groupId}/business-profile`)
        .set("Cookie", agentCookies)
        .send({ enterpriseType: "Poultry", monthlyRevenueCents: 4900000, visitId })
        .expect(200);

      const versions = await prisma.groupBusinessProfileVersion.count({ where: { visitId } });
      expect(versions).toBe(1);
    });
  });

  describe("editing the topic list", () => {
    it("adds a topic and refuses a duplicate key", async () => {
      await request(app)
        .post("/api/v1/mentorship-topics")
        .set("Cookie", adminCookies)
        .send({ key: "market_linkage", title: "Market linkage" })
        .expect(201);

      // Keys are what sessions and trends join on, so reusing one would make a
      // past session ambiguous.
      const duplicate = await request(app)
        .post("/api/v1/mentorship-topics")
        .set("Cookie", adminCookies)
        .send({ key: "market_linkage", title: "Something else" })
        .expect(409);
      expect(duplicate.body.error.code).toBe("TOPIC_KEY_TAKEN");
    });

    it("deletes an unused topic outright", async () => {
      const response = await request(app)
        .delete("/api/v1/mentorship-topics/market_linkage")
        .set("Cookie", adminCookies)
        .expect(200);

      expect(response.body.data.deleted).toBe(true);
      expect(response.body.data.retired).toBe(false);
    });

    it("retires rather than deletes a topic that has been used", async () => {
      await request(app)
        .put(`/api/v1/visits/${visitId}/mentorship`)
        .set("Cookie", agentCookies)
        .send({ sessions: [{ topicKey: "governance" }], ratings: [] })
        .expect(200);

      const response = await request(app)
        .delete("/api/v1/mentorship-topics/governance")
        .set("Cookie", adminCookies)
        .expect(200);

      expect(response.body.data.retired).toBe(true);
      expect(response.body.data.deleted).toBe(false);
      expect(response.body.data.sessions).toBe(1);

      // Gone from the phone's list, but the session still reads.
      const list = await request(app)
        .get("/api/v1/mentorship-topics")
        .set("Cookie", agentCookies)
        .expect(200);
      expect(
        list.body.data.topics.some((t: { key: string }) => t.key === "governance")
      ).toBe(false);

      const session = await request(app)
        .get(`/api/v1/visits/${visitId}/mentorship`)
        .set("Cookie", adminCookies)
        .expect(200);
      expect(session.body.data.sessions[0].topicTitle).toBe("Governance and leadership");
    });

    it("refuses to let an agent edit the topic list", async () => {
      // An agent answers the form; they do not decide what it asks.
      await request(app)
        .post("/api/v1/mentorship-topics")
        .set("Cookie", agentCookies)
        .send({ key: "agents_own", title: "Agent's own topic" })
        .expect(403);
    });
  });
});
