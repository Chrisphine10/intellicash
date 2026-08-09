import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { seedAssessmentTemplateV1 } from "../prisma/seed-assessment-template-v1";
import { DEFAULT_TEMPLATE_FAMILY } from "../src/services/visit-assessment-service";

const app = createApp();

/**
 * The assessment scorecard, end to end.
 *
 * The pure scoring rules are covered in `visit-assessment-contract.test.ts`.
 * What can only be proved against a database is here: that a published version
 * is genuinely immutable, that an assessment keeps scoring the same after the
 * template moves on, and that an agent can answer the form but not rewrite it.
 */

async function signIn(identifier: string, password = demoPassword) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: identifier, password })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

describe("visit assessments", () => {
  let adminCookies: string[];
  let agentCookies: string[];
  let groupId: string;
  let visitId: string;
  let templateId: string;
  let snapshotId: string;

  beforeAll(async () => {
    await seedDatabase();
    await prisma.groupVisitAnswer.deleteMany({});
    await prisma.groupVisitAssessment.deleteMany({});
    await prisma.assessmentTemplateSnapshot.deleteMany({});
    await prisma.assessmentSection.deleteMany({});
    await prisma.assessmentTemplate.deleteMany({});
    await prisma.groupVisit.deleteMany({});

    await seedAssessmentTemplateV1(prisma);

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
        clientRequestId: "visit-assessment-fixture",
        visitType: "FOLLOW_UP",
        startedAt: new Date(),
        villageAgentId: agentUser!.villageAgentId!,
        submittedByUserId: agentUser!.id
      }
    });
    visitId = visit.id;

    const template = await prisma.assessmentTemplate.findFirstOrThrow({
      where: { familyKey: DEFAULT_TEMPLATE_FAMILY, status: "PUBLISHED" },
      include: { snapshot: true }
    });
    templateId = template.id;
    snapshotId = template.snapshot!.id;
  }, 180000);

  describe("the published form", () => {
    it("serves the current snapshot to the agent who has to fill it in", async () => {
      const response = await request(app)
        .get("/api/v1/assessment-templates/current")
        .set("Cookie", agentCookies)
        .expect(200);

      const { data } = response.body;
      expect(data.version).toBe(1);
      expect(data.maxPoints).toBe(92);
      expect(data.snapshot.sections).toHaveLength(7);
      expect(data.checksum).toMatch(/^[0-9a-f]{64}$/);
      // The phone needs the answer set alongside the questions to render.
      expect(data.choices.map((c: { key: string }) => c.key)).toContain("NOT_APPLICABLE");
    });

    it("computes maxPoints from the questions rather than storing 92", async () => {
      const questions = await prisma.assessmentQuestion.count({
        where: { section: { templateId } }
      });
      const template = await prisma.assessmentTemplate.findUniqueOrThrow({
        where: { id: templateId }
      });

      expect(questions).toBe(46);
      expect(template.maxPoints).toBe(questions * 2);
    });
  });

  describe("an agent fills the form in but cannot author it", () => {
    it("scores the answers server-side and returns the band", async () => {
      const snapshot = await prisma.assessmentTemplateSnapshot.findUniqueOrThrow({
        where: { id: snapshotId }
      });
      const parsed = JSON.parse(snapshot.snapshotJson);
      const keys: string[] = parsed.sections.flatMap((section: { questions: { key: string }[] }) =>
        section.questions.map((question) => question.key)
      );

      const response = await request(app)
        .put(`/api/v1/visits/${visitId}/assessment`)
        .set("Cookie", agentCookies)
        .send({
          templateSnapshotId: snapshotId,
          expectedChecksum: snapshot.checksum,
          answers: keys.map((questionKey) => ({ questionKey, choice: "YES" }))
        })
        .expect(200);

      expect(response.body.data.score.earnedPoints).toBe(92);
      expect(response.body.data.score.bandKey).toBe("excellent");
      expect(response.body.data.score.complete).toBe(true);
      expect(response.body.data.checksumMismatch).toBe(false);
    });

    it("is idempotent: resending the same answers does not fork a second assessment", async () => {
      // The phone retries the whole visit document on every reconnect.
      const send = () =>
        request(app)
          .put(`/api/v1/visits/${visitId}/assessment`)
          .set("Cookie", agentCookies)
          .send({
            templateSnapshotId: snapshotId,
            answers: [{ questionKey: "constitution_written", choice: "YES" }]
          })
          .expect(200);

      await send();
      await send();

      const assessments = await prisma.groupVisitAssessment.count({ where: { visitId } });
      const answers = await prisma.groupVisitAnswer.count({
        where: { assessment: { visitId } }
      });
      expect(assessments).toBe(1);
      expect(answers).toBe(1);
    });

    it("records a partial assessment rather than refusing it", async () => {
      const response = await request(app)
        .put(`/api/v1/visits/${visitId}/assessment`)
        .set("Cookie", agentCookies)
        .send({
          templateSnapshotId: snapshotId,
          answers: [
            { questionKey: "constitution_written", choice: "YES" },
            { questionKey: "committee_complete", choice: "PARTIAL" }
          ]
        })
        .expect(200);

      const { score } = response.body.data;
      expect(score.earnedPoints).toBe(3);
      expect(score.complete).toBe(false);
      expect(score.unansweredKeys.length).toBe(44);
    });

    it("does not let an unknown question key lose the whole visit", async () => {
      const response = await request(app)
        .put(`/api/v1/visits/${visitId}/assessment`)
        .set("Cookie", agentCookies)
        .send({
          templateSnapshotId: snapshotId,
          answers: [
            { questionKey: "constitution_written", choice: "YES" },
            { questionKey: "question_from_an_older_form", choice: "YES" }
          ]
        })
        .expect(200);

      expect(response.body.data.score.unknownAnswerKeys).toEqual(["question_from_an_older_form"]);
      expect(response.body.data.score.earnedPoints).toBe(2);
    });

    it("reports a checksum mismatch instead of rejecting a stale phone", async () => {
      const response = await request(app)
        .put(`/api/v1/visits/${visitId}/assessment`)
        .set("Cookie", agentCookies)
        .send({
          templateSnapshotId: snapshotId,
          expectedChecksum: "0".repeat(64),
          answers: [{ questionKey: "constitution_written", choice: "YES" }]
        })
        .expect(200);

      // The visit still lands — losing a day's fieldwork over a cache
      // disagreement would be far worse than a flagged one.
      expect(response.body.data.checksumMismatch).toBe(true);
      expect(response.body.data.score.earnedPoints).toBe(2);
    });

    it("refuses to let an agent author a template", async () => {
      await request(app)
        .post("/api/v1/assessment-templates")
        .set("Cookie", agentCookies)
        .send({ title: "Agent's own scorecard", sections: [], bands: [] })
        .expect(403);

      await request(app)
        .post(`/api/v1/assessment-templates/${templateId}/clone`)
        .set("Cookie", agentCookies)
        .expect(403);
    });

    it("refuses an assessment on a visit outside the agent's caseload", async () => {
      const detached = await prisma.group.findFirst({
        where: { villageAgentId: null },
        select: { id: true }
      });
      const otherGroupId =
        detached?.id ??
        (
          await prisma.group.update({
            where: {
              id: (await prisma.group.findFirstOrThrow({
                where: { id: { not: groupId } },
                select: { id: true }
              })).id
            },
            data: { villageAgentId: null },
            select: { id: true }
          })
        ).id;

      const foreignVisit = await prisma.groupVisit.create({
        data: {
          groupId: otherGroupId,
          clientRequestId: "visit-outside-caseload",
          visitType: "FOLLOW_UP",
          startedAt: new Date()
        }
      });

      // 404, not 403: "forbidden" would confirm the visit exists.
      await request(app)
        .put(`/api/v1/visits/${foreignVisit.id}/assessment`)
        .set("Cookie", agentCookies)
        .send({ answers: [{ questionKey: "constitution_written", choice: "YES" }] })
        .expect(404);
    });
  });

  describe("a published version is immutable", () => {
    it("refuses to edit it", async () => {
      const response = await request(app)
        .put(`/api/v1/assessment-templates/${templateId}`)
        .set("Cookie", adminCookies)
        .send({ title: "Rewritten in place", sections: [], bands: [] })
        .expect(409);

      expect(response.body.error.code).toBe("TEMPLATE_NOT_EDITABLE");
    });

    it("refuses to publish it twice", async () => {
      const response = await request(app)
        .post(`/api/v1/assessment-templates/${templateId}/publish`)
        .set("Cookie", adminCookies)
        .expect(409);

      expect(response.body.error.code).toBe("TEMPLATE_ALREADY_PUBLISHED");
    });

    it("clones to a new draft at the next version, leaving v1 alone", async () => {
      const clone = await request(app)
        .post(`/api/v1/assessment-templates/${templateId}/clone`)
        .set("Cookie", adminCookies)
        .expect(200);

      expect(clone.body.data.version).toBe(2);
      expect(clone.body.data.status).toBe("DRAFT");

      const original = await prisma.assessmentTemplate.findUniqueOrThrow({
        where: { id: templateId }
      });
      expect(original.status).toBe("PUBLISHED");
      expect(original.maxPoints).toBe(92);

      // One draft at a time — a second would make "the next version" ambiguous.
      const second = await request(app)
        .post(`/api/v1/assessment-templates/${templateId}/clone`)
        .set("Cookie", adminCookies)
        .expect(409);
      expect(second.body.error.code).toBe("TEMPLATE_DRAFT_EXISTS");
    });

    it("refuses to publish a draft whose bands do not cover every score", async () => {
      const draftTemplate = await prisma.assessmentTemplate.findFirstOrThrow({
        where: { familyKey: DEFAULT_TEMPLATE_FAMILY, status: "DRAFT" }
      });

      await prisma.assessmentTemplate.update({
        where: { id: draftTemplate.id },
        // Top band now stops at 80 while the questions still total 92.
        data: {
          bandsJson: JSON.stringify([
            { key: "weak", label: "Weak", minPoints: 0, maxPoints: 40 },
            { key: "strong", label: "Strong", minPoints: 41, maxPoints: 80 }
          ])
        }
      });

      const response = await request(app)
        .post(`/api/v1/assessment-templates/${draftTemplate.id}/publish`)
        .set("Cookie", adminCookies)
        .expect(400);

      expect(response.body.error.code).toBe("TEMPLATE_INVALID");
      expect(JSON.stringify(response.body.error.details)).toMatch(/questions total 92/);
    });
  });

  describe("reproducibility", () => {
    it("keeps scoring a stored assessment from its own snapshot after the template changes", async () => {
      // Score under v1.
      await request(app)
        .put(`/api/v1/visits/${visitId}/assessment`)
        .set("Cookie", agentCookies)
        .send({
          templateSnapshotId: snapshotId,
          answers: [
            { questionKey: "constitution_written", choice: "YES" },
            { questionKey: "committee_complete", choice: "PARTIAL" }
          ]
        })
        .expect(200);

      const before = await request(app)
        .get(`/api/v1/visits/${visitId}/assessment`)
        .set("Cookie", adminCookies)
        .expect(200);

      // Now mutate the live template rows underneath — the exact thing the
      // snapshot exists to survive. (Editing a published version is refused
      // through the API, so this reaches past it deliberately.)
      await prisma.assessmentQuestion.updateMany({
        where: { key: "constitution_written" },
        data: { weight: 50, prompt: "Rewritten prompt" }
      });

      const after = await request(app)
        .get(`/api/v1/visits/${visitId}/assessment`)
        .set("Cookie", adminCookies)
        .expect(200);

      expect(after.body.data.earnedPoints).toBe(before.body.data.earnedPoints);
      expect(after.body.data.percentage).toBe(before.body.data.percentage);
      expect(after.body.data.maxPoints).toBe(92);
      // And the questions still read as they did when the agent answered them.
      const prompts = JSON.stringify(after.body.data.snapshot);
      expect(prompts).not.toContain("Rewritten prompt");
    });

    it("stamps the contract version onto the stored assessment", async () => {
      const stored = await prisma.groupVisitAssessment.findUniqueOrThrow({
        where: { visitId }
      });
      expect(stored.scoringContractVersion).toBe("1.0.0");
      expect(stored.templateVersion).toBe(1);
      expect(JSON.parse(stored.breakdownJson).sections.length).toBe(7);
    });
  });
});
