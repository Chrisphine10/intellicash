import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { seedAssessmentTemplateV1 } from "../prisma/seed-assessment-template-v1";

const app = createApp();

/**
 * The MEAL report, end to end.
 *
 * `meal-indicators.test.ts` pins the arithmetic. This pins the wiring — that the
 * right readings reach it — because the rules only protect anything if the
 * service actually feeds them the data it claims to.
 *
 * The case that matters most is comparability: two scores from two scorecard
 * versions are not one measurement, and a report that averages them reads a
 * re-worded question as a group improving.
 */

async function signIn(identifier: string, password = demoPassword) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: identifier, password })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

interface IndicatorRow {
  definition: { key: string; name: string; definition: string; denominator: string; unit: string };
  change?: {
    baseline: number | null;
    latest: number | null;
    change: number | null;
    pairedUnits: number;
    excludedForComparability: number;
    notes: string[];
  };
  share?: { numerator: number; denominator: number; percent: number | null };
  value?: number | null;
  movement: string;
}

function indicator(body: { data: { indicators: IndicatorRow[] } }, key: string): IndicatorRow {
  const row = body.data.indicators.find((entry) => entry.definition.key === key);
  if (!row) throw new Error(`no indicator ${key} in the report`);
  return row;
}

describe("the MEAL report", () => {
  let adminCookies: string[];
  let groupA: string;
  let groupB: string;
  let snapshotId: string;
  let templateId: string;

  beforeAll(async () => {
    await seedDatabase();

    await prisma.groupEnterpriseSupportNeed.deleteMany({});
    await prisma.groupEnterpriseVersion.deleteMany({});
    await prisma.groupEnterprise.deleteMany({});
    await prisma.groupVisitAnswer.deleteMany({});
    await prisma.groupVisitAssessment.deleteMany({});
    await prisma.assessmentTemplateSnapshot.deleteMany({});
    await prisma.assessmentSection.deleteMany({});
    await prisma.assessmentTemplate.deleteMany({});
    await prisma.visitMentorshipRating.deleteMany({});
    await prisma.visitActionItem.deleteMany({});
    await prisma.groupVisit.deleteMany({});

    await seedAssessmentTemplateV1(prisma);
    const template = await prisma.assessmentTemplate.findFirstOrThrow({
      where: { status: "PUBLISHED" },
      include: { snapshot: true }
    });
    snapshotId = template.snapshot!.id;
    templateId = template.id;

    const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
    adminCookies = await signIn(admin.phone);

    const groups = await prisma.group.findMany({ select: { id: true }, take: 2, orderBy: { id: "asc" } });
    groupA = groups[0]!.id;
    groupB = groups[1]!.id;

    // Group A: two readings on the SAME scorecard version — a real comparison.
    await assess(groupA, "a1", 40, 1);
    await assess(groupA, "a2", 55, 1);

    // Group B: two readings across a version change — not the same
    // measurement, and the large apparent jump is exactly what would flatter
    // the average if it were counted.
    await assess(groupB, "b1", 30, 1);
    await assess(groupB, "b2", 95, 2);
  }, 180000);

  async function assess(groupId: string, key: string, percentage: number, version: number) {
    const visit = await prisma.groupVisit.create({
      data: {
        groupId,
        clientRequestId: `meal-${key}`,
        visitType: "FOLLOW_UP",
        startedAt: new Date()
      }
    });
    await prisma.groupVisitAssessment.create({
      data: {
        visitId: visit.id,
        templateSnapshotId: snapshotId,
        templateId,
        templateVersion: version,
        scoringContractVersion: "1.0.0",
        earnedPoints: percentage,
        applicablePoints: 100,
        maxPoints: 100,
        scaledPoints: percentage,
        percentage,
        complete: true,
        breakdownJson: "{}"
      }
    });
    return visit.id;
  }

  it("excludes a group whose scorecard version changed underneath it", async () => {
    const response = await request(app)
      .get("/api/v1/reports/meal")
      .set("Cookie", adminCookies)
      .expect(200);

    const score = indicator(response.body, "assessment.score");

    // Only group A is comparable. Group B's 30 to 95 is a different form, not a
    // transformed group, and counting it would report a 45-point programme gain.
    expect(score.change?.pairedUnits).toBe(1);
    expect(score.change?.excludedForComparability).toBe(1);
    expect(score.change?.baseline).toBe(40);
    expect(score.change?.latest).toBe(55);
    expect(score.change?.notes.join(" ")).toMatch(/measurement changed/i);
  });

  it("reports movement, not just an average", async () => {
    const response = await request(app)
      .get("/api/v1/reports/meal")
      .set("Cookie", adminCookies)
      .expect(200);

    // An average can hide a programme where half the groups go backwards.
    expect(response.body.data.assessmentMovement.improved).toBe(1);
    expect(response.body.data.assessmentMovement.notComparable).toBe(1);
    expect(response.body.data.assessmentMovement).toHaveProperty("neverAssessed");
  });

  it("carries its own methodology and the contribution caveat", async () => {
    const response = await request(app)
      .get("/api/v1/reports/meal")
      .set("Cookie", adminCookies)
      .expect(200);

    // A figure that ends up in a funder report should carry its limits with it
    // rather than depend on whoever pastes it remembering them.
    expect(response.body.data.caveat).toMatch(/not what the programme caused/i);
    expect(response.body.data.methodology.length).toBeGreaterThan(10);
    for (const definition of response.body.data.methodology) {
      expect(definition.definition.length).toBeGreaterThan(20);
    }
  });

  it("tracks enterprise revenue between dated readings", async () => {
    const enterprise = await prisma.groupEnterprise.create({
      data: { groupId: groupA, name: "Poultry unit", monthlyRevenueCents: 900000 }
    });
    await prisma.groupEnterpriseVersion.createMany({
      data: [
        {
          enterpriseId: enterprise.id,
          groupId: groupA,
          visitId: "meal-ent-1",
          monthlyRevenueCents: 400000,
          recordedAt: new Date("2026-01-10")
        },
        {
          enterpriseId: enterprise.id,
          groupId: groupA,
          visitId: "meal-ent-2",
          monthlyRevenueCents: 900000,
          recordedAt: new Date("2026-06-10")
        }
      ]
    });

    const response = await request(app)
      .get("/api/v1/reports/meal")
      .set("Cookie", adminCookies)
      .expect(200);

    const revenue = indicator(response.body, "enterprise.revenue");
    expect(revenue.change?.baseline).toBe(400000);
    expect(revenue.change?.latest).toBe(900000);
    expect(revenue.movement).toBe("IMPROVED");
  });

  it("measures a widening market as a step up the ladder", async () => {
    const enterprise = await prisma.groupEnterprise.create({
      data: { groupId: groupB, name: "Cereal store", marketReach: "COUNTY" }
    });
    await prisma.groupEnterpriseVersion.createMany({
      data: [
        {
          enterpriseId: enterprise.id,
          groupId: groupB,
          visitId: "meal-reach-1",
          marketReach: "VILLAGE",
          recordedAt: new Date("2026-01-10")
        },
        {
          enterpriseId: enterprise.id,
          groupId: groupB,
          visitId: "meal-reach-2",
          marketReach: "COUNTY",
          recordedAt: new Date("2026-06-10")
        }
      ]
    });

    const response = await request(app)
      .get("/api/v1/reports/meal")
      .set("Cookie", adminCookies)
      .expect(200);

    const reach = indicator(response.body, "enterprise.marketReach");
    // Village is rung 2, county is rung 5.
    expect(reach.change?.change).toBe(3);
  });

  it("ranks what groups actually ask for", async () => {
    const enterprise = await prisma.groupEnterprise.findFirstOrThrow({ where: { groupId: groupA } });
    await prisma.groupEnterpriseSupportNeed.createMany({
      data: [
        {
          enterpriseId: enterprise.id,
          groupId: groupA,
          needKeySnapshot: "cold-chain",
          needTitleSnapshot: "Cold chain",
          needCategorySnapshot: "INFRASTRUCTURE",
          status: "OPEN"
        },
        {
          enterpriseId: enterprise.id,
          groupId: groupA,
          needKeySnapshot: "buyer-linkage",
          needTitleSnapshot: "Linkage to a reliable buyer",
          needCategorySnapshot: "MARKET",
          status: "MET",
          raisedAt: new Date("2026-01-01"),
          metAt: new Date("2026-01-31")
        }
      ]
    });

    const response = await request(app)
      .get("/api/v1/reports/meal")
      .set("Cookie", adminCookies)
      .expect(200);

    // The whole point of the taxonomy: this list is only possible because the
    // needs are keys rather than sentences.
    expect(response.body.data.supportNeeds.total).toBe(2);
    expect(response.body.data.supportNeeds.ranked.map((row: { key: string }) => row.key)).toContain(
      "cold-chain"
    );
    expect(response.body.data.supportNeeds.medianDaysToMeet).toBe(30);

    const met = indicator(response.body, "needs.met");
    expect(met.share).toMatchObject({ numerator: 1, denominator: 2, percent: 50 });
  });

  it("keeps agents' self-ratings out of the mentorship score", async () => {
    const visit = await prisma.groupVisit.findFirstOrThrow({ where: { groupId: groupA } });
    await prisma.visitMentorshipRating.createMany({
      data: [
        {
          visitId: visit.id,
          dimensionKeySnapshot: "clarity",
          score: 3,
          ratedByRole: "GROUP_REPRESENTATIVE"
        },
        { visitId: visit.id, dimensionKeySnapshot: "usefulness", score: 5, ratedByRole: "AGENT" }
      ]
    });

    const response = await request(app)
      .get("/api/v1/reports/meal")
      .set("Cookie", adminCookies)
      .expect(200);

    // The agent's 5 must not lift the score. An agent rating their own coaching
    // scores 4 or 5 every time and the aggregate then says nothing.
    expect(indicator(response.body, "mentorship.rating").value).toBe(3);

    // And the dashboard must be able to see how much of the average is the
    // group's own voice.
    const provenance = indicator(response.body, "data.ratingProvenance");
    expect(provenance.share).toMatchObject({ numerator: 1, denominator: 2 });
  });

  it("gives one group its own baseline", async () => {
    const response = await request(app)
      .get(`/api/v1/groups/${groupA}/meal`)
      .set("Cookie", adminCookies)
      .expect(200);

    expect(response.body.data.group.id).toBe(groupA);
    expect(response.body.data.assessment.first.percentage).toBe(40);
    expect(response.body.data.assessment.latest.percentage).toBe(55);
    expect(response.body.data.assessment.readings).toBe(2);
  });

  it("404s a group outside the caller's scope", async () => {
    await request(app)
      .get("/api/v1/groups/does-not-exist/meal")
      .set("Cookie", adminCookies)
      .expect(404);
  });
});
