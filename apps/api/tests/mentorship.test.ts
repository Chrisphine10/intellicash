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
 * Mentorship, the group's verdict on it, and the work agreed for next time.
 *
 * The property that matters most: action items outlive the visit. A commitment
 * that cannot be found again at the start of the next visit is a note, not a
 * plan.
 */
describe("mentorship and the action plan", () => {
  let adminCookies: string[];
  let agentCookies: string[];
  let groupId: string;
  let visitId: string;
  let secondVisitId: string;

  beforeAll(async () => {
    await seedDatabase();
    await seedMentorshipReferenceData(prisma);
    await prisma.visitActionItem.deleteMany({});
    await prisma.visitMentorshipRating.deleteMany({});
    await prisma.visitMentorshipSession.deleteMany({});
    await prisma.groupVisit.deleteMany({});

    const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
    const agent = demoAccounts.find((account) => account.role === "VILLAGE_AGENT")!;
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
        clientRequestId: "visit-mentorship-1",
        visitType: "FOLLOW_UP",
        startedAt: new Date(),
        villageAgentId: agentUser!.villageAgentId!,
        submittedByUserId: agentUser!.id
      }
    });
    visitId = visit.id;

    const nextVisit = await prisma.groupVisit.create({
      data: {
        groupId,
        clientRequestId: "visit-mentorship-2",
        visitType: "FOLLOW_UP",
        startedAt: new Date(),
        villageAgentId: agentUser!.villageAgentId!
      }
    });
    secondVisitId = nextVisit.id;
  }, 180000);

  describe("reference data", () => {
    it("serves the topics and rating scale the phone renders", async () => {
      const response = await request(app)
        .get("/api/v1/mentorship-topics")
        .set("Cookie", agentCookies)
        .expect(200);

      expect(response.body.data.topics.length).toBeGreaterThan(0);
      expect(response.body.data.dimensions.length).toBeGreaterThan(0);
      expect(response.body.data.ratingScale).toEqual({ min: 1, max: 5 });
    });
  });

  describe("recording a session", () => {
    it("stores the coaching and the group's score", async () => {
      const response = await request(app)
        .put(`/api/v1/visits/${visitId}/mentorship`)
        .set("Cookie", agentCookies)
        .send({
          sessions: [
            { topicKey: "record_keeping", notes: "Walked through the ledger.", durationMinutes: 25 }
          ],
          ratings: [
            { dimensionKey: "clarity", score: 5 },
            { dimensionKey: "usefulness", score: 4 }
          ]
        })
        .expect(200);

      expect(response.body.data.sessions).toHaveLength(1);
      expect(response.body.data.sessions[0].topicTitle).toBe("Record keeping");
      expect(response.body.data.averageGroupRating).toBe(4.5);
      expect(response.body.data.ratedByGroup).toBe(true);
    });

    it("is idempotent — a retried document does not append a second session", async () => {
      const send = () =>
        request(app)
          .put(`/api/v1/visits/${visitId}/mentorship`)
          .set("Cookie", agentCookies)
          .send({
            sessions: [{ topicKey: "governance", notes: "Elections due." }],
            ratings: [{ dimensionKey: "clarity", score: 3 }]
          })
          .expect(200);

      await send();
      const second = await send();

      expect(second.body.data.sessions).toHaveLength(1);
      expect(await prisma.visitMentorshipSession.count({ where: { visitId } })).toBe(1);
    });

    it("excludes an agent's own score from the group's average", async () => {
      // An agent rating their own session scores 4 or 5 every time. Recorded,
      // because who answered is a fact worth keeping — but never averaged in.
      const response = await request(app)
        .put(`/api/v1/visits/${visitId}/mentorship`)
        .set("Cookie", agentCookies)
        .send({
          sessions: [],
          ratings: [
            { dimensionKey: "clarity", score: 2, ratedByRole: "GROUP_REPRESENTATIVE" },
            { dimensionKey: "usefulness", score: 5, ratedByRole: "AGENT" }
          ]
        })
        .expect(200);

      expect(response.body.data.ratings).toHaveLength(2);
      // Only the group's 2 counts.
      expect(response.body.data.averageGroupRating).toBe(2);
    });

    it("reports no group rating rather than zero when nobody was asked", async () => {
      const response = await request(app)
        .put(`/api/v1/visits/${secondVisitId}/mentorship`)
        .set("Cookie", agentCookies)
        .send({ sessions: [{ topicKey: "governance" }], ratings: [] })
        .expect(200);

      expect(response.body.data.averageGroupRating).toBeNull();
      expect(response.body.data.ratedByGroup).toBe(false);
    });

    it("keeps a session readable after its topic is retired", async () => {
      // The title is snapshotted at write time, so tidying the settings screen
      // later cannot turn history into "we coached them on <deleted>".
      await request(app)
        .put(`/api/v1/visits/${visitId}/mentorship`)
        .set("Cookie", agentCookies)
        .send({ sessions: [{ topicKey: "digital_tools" }], ratings: [] })
        .expect(200);

      await prisma.mentorshipTopic.delete({ where: { key: "digital_tools" } });

      const response = await request(app)
        .get(`/api/v1/visits/${visitId}/mentorship`)
        .set("Cookie", adminCookies)
        .expect(200);

      expect(response.body.data.sessions[0].topicKey).toBe("digital_tools");
      expect(response.body.data.sessions[0].topicTitle).toBe("Using Intelli-Cash");
    });

    it("refuses a score outside 1-5", async () => {
      await request(app)
        .put(`/api/v1/visits/${visitId}/mentorship`)
        .set("Cookie", agentCookies)
        .send({ sessions: [], ratings: [{ dimensionKey: "clarity", score: 9 }] })
        .expect(400);
    });
  });

  describe("action items outlive the visit", () => {
    let overdueItemId: string;

    it("raises work agreed at the visit", async () => {
      const response = await request(app)
        .post(`/api/v1/visits/${visitId}/action-items`)
        .set("Cookie", agentCookies)
        .send({
          title: "Write up the ledger to date",
          owner: "the treasurer",
          dueDate: new Date(Date.now() - 5 * DAY).toISOString()
        })
        .expect(201);

      overdueItemId = response.body.data.id;
      // Already past its date, and late without any job having run.
      expect(response.body.data.state.state).toBe("OVERDUE");
      expect(response.body.data.state.daysOverdue).toBeGreaterThanOrEqual(4);
    });

    it("surfaces the group's open work, worst first", async () => {
      // This is what the phone shows at the START of the next visit — the
      // difference between a follow-up and repeating last month's conversation.
      await request(app)
        .post(`/api/v1/visits/${visitId}/action-items`)
        .set("Cookie", agentCookies)
        .send({
          title: "Open a bank account",
          dueDate: new Date(Date.now() + 40 * DAY).toISOString()
        })
        .expect(201);

      const response = await request(app)
        .get(`/api/v1/groups/${groupId}/action-items`)
        .set("Cookie", agentCookies)
        .expect(200);

      expect(response.body.data.items[0].id).toBe(overdueItemId);
      expect(response.body.data.summary.open).toBe(2);
      expect(response.body.data.summary.overdue).toBe(1);
    });

    it("gives the agent one queue across their whole caseload", async () => {
      const response = await request(app)
        .get("/api/v1/agents/me/action-items")
        .set("Cookie", agentCookies)
        .expect(200);

      expect(response.body.data.items.length).toBeGreaterThanOrEqual(2);
      expect(response.body.data.items[0].group).toBeTruthy();
      expect(response.body.data.summary.overdue).toBeGreaterThanOrEqual(1);
    });

    it("closes an item against the visit that closed it", async () => {
      const response = await request(app)
        .patch(`/api/v1/action-items/${overdueItemId}`)
        .set("Cookie", agentCookies)
        .send({
          status: "DONE",
          closingNote: "Ledger seen, written up to last meeting.",
          closedAtVisitId: secondVisitId
        })
        .expect(200);

      // Closed late is not the same as outstanding. It leaves the queue.
      expect(response.body.data.state.state).toBe("DONE");
      expect(response.body.data.state.open).toBe(false);
      expect(response.body.data.state.daysOverdue).toBe(0);
      expect(response.body.data.closedAt).toBeTruthy();
    });

    it("drops a closed item out of the follow-up queue", async () => {
      const response = await request(app)
        .get(`/api/v1/groups/${groupId}/action-items`)
        .set("Cookie", agentCookies)
        .expect(200);

      expect(response.body.data.summary.overdue).toBe(0);
      expect(response.body.data.summary.open).toBe(1);
      // Still listed — the record of what was agreed survives being done.
      expect(response.body.data.summary.total).toBe(2);
    });

    it("clears the closed date when an item is reopened", async () => {
      // Otherwise a reopened item keeps claiming it was finished on a date in
      // the past, which reads as done in every report that looks at closedAt.
      const response = await request(app)
        .patch(`/api/v1/action-items/${overdueItemId}`)
        .set("Cookie", agentCookies)
        .send({ status: "OPEN" })
        .expect(200);

      expect(response.body.data.closedAt).toBeNull();
      expect(response.body.data.state.open).toBe(true);
    });

    it("refuses an action item outside the agent's caseload", async () => {
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

      const foreignVisit = await prisma.groupVisit.create({
        data: {
          groupId: otherGroupId,
          clientRequestId: "visit-foreign-mentorship",
          visitType: "FOLLOW_UP",
          startedAt: new Date()
        }
      });
      const foreignItem = await prisma.visitActionItem.create({
        data: { visitId: foreignVisit.id, groupId: otherGroupId, title: "Not yours" }
      });

      // 404, not 403 — "forbidden" would confirm it exists.
      await request(app)
        .patch(`/api/v1/action-items/${foreignItem.id}`)
        .set("Cookie", agentCookies)
        .send({ status: "DONE" })
        .expect(404);

      const queue = await request(app)
        .get("/api/v1/agents/me/action-items")
        .set("Cookie", agentCookies)
        .expect(200);
      expect(
        queue.body.data.items.some((item: { id: string }) => item.id === foreignItem.id)
      ).toBe(false);
    });
  });
});
