import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { __resetAssessmentTemplateBootstrapForTests } from "../src/services/assessment-template-bootstrap";

const app = createApp();

/**
 * A field agent must be able to open the assessment form.
 *
 * On production there was no published template at all. The v1 question set was
 * only ever published by running `seed-assessment-template-v1.ts` by hand or by
 * a test — no migration writes it, `seed.ts` does not call it, and the seed does
 * not run on production. So every agent asking for the current form got
 * `NO_PUBLISHED_TEMPLATE`, the assessment step of a visit was unreachable, and
 * it presented as "the agent cannot see the form" — which reads like a
 * permissions fault rather than an empty table.
 *
 * The same shape as the support-need taxonomy: reference data that exists only
 * if somebody remembers to run a script.
 */

async function signIn(identifier: string, password = demoPassword) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: identifier, password })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

async function emptyTheTemplateTables() {
  await prisma.groupVisitAnswer.deleteMany({});
  await prisma.groupVisitAssessment.deleteMany({});
  await prisma.assessmentTemplateSnapshot.deleteMany({});
  await prisma.assessmentQuestion.deleteMany({});
  await prisma.assessmentSection.deleteMany({});
  await prisma.assessmentTemplate.deleteMany({});
  __resetAssessmentTemplateBootstrapForTests();
}

describe("the assessment form reaching a field agent", () => {
  let agent: string[];
  let admin: string[];

  beforeAll(async () => {
    await seedDatabase();
    const agentAccount = demoAccounts.find((entry) => entry.role === "VILLAGE_AGENT")!;
    const adminAccount = demoAccounts.find((entry) => entry.role === "IWL_ADMIN")!;
    agent = await signIn(agentAccount.phone);
    admin = await signIn(adminAccount.phone);
  }, 180000);

  it("is served to an agent on a database where no seed has ever run", async () => {
    // Production's exact condition.
    await emptyTheTemplateTables();
    expect(await prisma.assessmentTemplate.count()).toBe(0);

    const response = await request(app)
      .get("/api/v1/assessment-templates/current")
      .set("Cookie", agent)
      .expect(200);

    expect(response.body.data.snapshot.sections.length).toBeGreaterThan(0);
    expect(response.body.data.maxPoints).toBeGreaterThan(0);
    // The agent needs the answer options too, or the form renders with no way
    // to answer it.
    expect(response.body.data.choices.length).toBeGreaterThan(0);
  });

  it("computes maxPoints from the questions rather than carrying a constant", async () => {
    await emptyTheTemplateTables();

    const response = await request(app)
      .get("/api/v1/assessment-templates/current")
      .set("Cookie", agent)
      .expect(200);

    const snapshot = response.body.data.snapshot;
    const summed = snapshot.sections.reduce(
      (total: number, section: { questions: { weight: number }[] }) =>
        total + section.questions.reduce((inner: number, question) => inner + question.weight, 0),
      0
    );
    expect(response.body.data.maxPoints).toBe(summed);
  });

  it("publishes once, not once per request", async () => {
    await emptyTheTemplateTables();

    await request(app).get("/api/v1/assessment-templates/current").set("Cookie", agent).expect(200);
    await request(app).get("/api/v1/assessment-templates/current").set("Cookie", agent).expect(200);
    await request(app).get("/api/v1/assessment-templates/current").set("Cookie", admin).expect(200);

    // A second template in the family would collide on the version, and a
    // second snapshot would leave two "current" forms.
    expect(await prisma.assessmentTemplate.count()).toBe(1);
  });

  it("keeps its hands off a family somebody is already authoring", async () => {
    await emptyTheTemplateTables();

    // An admin has a draft in progress and nothing published yet.
    const draft = await prisma.assessmentTemplate.create({
      data: {
        familyKey: "vsla_field_assessment",
        version: 1,
        status: "DRAFT",
        title: "Half-written form"
      }
    });

    // Seeding a second v1 would collide on @@unique([familyKey, version]), and
    // publishing over somebody's draft would be worse than the bug.
    await request(app)
      .get("/api/v1/assessment-templates/current")
      .set("Cookie", agent)
      .expect(404);

    const templates = await prisma.assessmentTemplate.findMany({ select: { id: true, status: true } });
    expect(templates).toHaveLength(1);
    expect(templates[0]!.id).toBe(draft.id);
    expect(templates[0]!.status).toBe("DRAFT");
  });

  it("does not republish over a template that is already published", async () => {
    await emptyTheTemplateTables();

    await request(app).get("/api/v1/assessment-templates/current").set("Cookie", agent).expect(200);
    const first = await prisma.assessmentTemplate.findFirstOrThrow({ select: { id: true } });

    __resetAssessmentTemplateBootstrapForTests();
    await request(app).get("/api/v1/assessment-templates/current").set("Cookie", agent).expect(200);

    const after = await prisma.assessmentTemplate.findMany({ select: { id: true } });
    expect(after).toHaveLength(1);
    expect(after[0]!.id).toBe(first.id);
  });

  it("lets the agent submit against the form it was given", async () => {
    // The form being visible is only half of it — scoring has to work against
    // the snapshot the agent was handed.
    await emptyTheTemplateTables();

    const form = await request(app)
      .get("/api/v1/assessment-templates/current")
      .set("Cookie", agent)
      .expect(200);

    const group = await prisma.group.findFirstOrThrow({ select: { id: true } });
    const visit = await prisma.groupVisit.create({
      data: {
        groupId: group.id,
        clientRequestId: `template-availability-${Date.now()}`,
        visitType: "FOLLOW_UP",
        startedAt: new Date()
      }
    });

    const firstSection = form.body.data.snapshot.sections[0];
    const response = await request(app)
      .put(`/api/v1/visits/${visit.id}/assessment`)
      .set("Cookie", admin)
      .send({
        templateVersion: form.body.data.version,
        checksum: form.body.data.checksum,
        answers: [{ questionKey: firstSection.questions[0].key, choice: "YES" }]
      })
      .expect(200);

    expect(response.body.data.score.earnedPoints).toBeGreaterThan(0);
  });
});
