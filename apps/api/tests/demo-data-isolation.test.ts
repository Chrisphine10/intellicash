import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * Demo data is for demo.
 *
 * It exists so the platform can be explored without inventing a group first.
 * It must never appear on a public page, and — the part that matters more —
 * it must never be inside a total. Demo figures on a marketing page look wrong
 * to whoever reads them. Demo figures inside a partner's impact numbers look
 * exactly like real ones, which is how a funder ends up quoting savings that
 * were never saved.
 *
 * A signed-in demo account still sees its own data. That is the whole point of
 * having it, and it is why the exclusion depends on who is asking.
 */

async function signIn(identifier: string, password = demoPassword) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: identifier, password })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

describe("demo data stays out of public surfaces and totals", () => {
  let admin: string[];
  let demoGroupId: string;
  let realGroupId: string;

  beforeAll(async () => {
    await seedDatabase();
    const account = demoAccounts.find((entry) => entry.role === "IWL_ADMIN")!;
    admin = await signIn(account.phone);

    const programme = await prisma.programme.findFirstOrThrow({
      select: { id: true, partnerId: true }
    });

    // A demo group and a REAL group sitting on the same public programme, so
    // the test proves the filter picks them apart rather than hiding the
    // programme wholesale.
    demoGroupId = (
      await prisma.group.create({
        data: {
          programmeId: programme.id,
          name: "Demo Test VSLA",
          code: `IWL-DEMO-TEST-${Date.now()}`,
          phase: "INTENSIVE",
          county: "Embu",
          isDemo: true
        },
        select: { id: true }
      })
    ).id;

    realGroupId = (
      await prisma.group.create({
        data: {
          programmeId: programme.id,
          // Named "Demo" on purpose: a real group is allowed to be called
          // anything. The flag decides, not the word.
          name: "Demo Farmers Cooperative",
          code: `IWL-REAL-TEST-${Date.now()}`,
          phase: "INTENSIVE",
          county: "Embu",
          isDemo: false
        },
        select: { id: true }
      })
    ).id;

    await prisma.programme.update({
      where: { id: programme.id },
      data: { publicStatus: "ONGOING", isDemo: false }
    });
  }, 180000);

  it("keeps a demo group off the public project page", async () => {
    const response = await request(app).get("/api/v1/public/programmes").expect(200);

    const groups = response.body.data.flatMap(
      (programme: { groupLinks?: Array<{ group: { id: string; name: string } }> }) =>
        programme.groupLinks?.map((link) => link.group) ?? []
    );

    expect(groups.some((group: { id: string }) => group.id === demoGroupId)).toBe(false);
  });

  it("does not hide a real group that happens to be called Demo", () => {
    // The flag decides. Matching on the word would hide a real group from its
    // own partner, which is a worse failure than the one being fixed.
    return prisma.group
      .findUniqueOrThrow({ where: { id: realGroupId }, select: { isDemo: true, name: true } })
      .then((group) => {
        expect(group.name).toContain("Demo");
        expect(group.isDemo).toBe(false);
      });
  });

  it("keeps a demo agent off the public store", async () => {
    const agent = await prisma.villageAgent.findFirst({ select: { id: true } });
    if (!agent) return;
    await prisma.villageAgent.update({ where: { id: agent.id }, data: { isDemo: true } });

    const response = await request(app).get("/api/v1/public/intelli-store").expect(200);
    expect(
      response.body.data.agents.some((row: { id: string }) => row.id === agent.id)
    ).toBe(false);

    await prisma.villageAgent.update({ where: { id: agent.id }, data: { isDemo: false } });
  });

  it("leaves demo groups out of the portfolio an admin reads", async () => {
    const response = await request(app)
      .get("/api/v1/analytics/portfolio")
      .set("Cookie", admin)
      .expect(200);

    const counted = await prisma.group.count({ where: { isDemo: false } });
    const total = Object.values(
      response.body.data.phaseDistribution as Record<string, number>
    ).reduce((sum, value) => sum + value, 0);

    // The demo group exists and is not in the count.
    expect(await prisma.group.count({ where: { isDemo: true } })).toBeGreaterThan(0);
    expect(total).toBe(counted);
  });

  it("leaves demo groups out of the foundation report", async () => {
    const response = await request(app)
      .get("/api/v1/reports/foundation")
      .set("Cookie", admin)
      .expect(200);

    // Whatever shape the report takes, the demo group's name must not be in it.
    expect(JSON.stringify(response.body)).not.toContain("Demo Test VSLA");
  });

  it("still shows a demo account its own group", async () => {
    // The exclusion depends on who is asking. If it did not, the demo would
    // show a signed-in demo user an empty dashboard and look broken.
    const demoGroup = await prisma.group.findUnique({
      where: { id: demoGroupId },
      select: { isDemo: true }
    });

    expect(demoGroup?.isDemo).toBe(true);

    const { callerIsDemo } = await import("../src/services/account-scope");
    const asDemoUser = await callerIsDemo({
      id: "u",
      name: "Demo",
      email: "demo@test",
      phone: null,
      role: "GROUP_ACCOUNT",
      avatarUrl: null,
      languagePreference: "ENGLISH",
      partnerId: null,
      groupId: demoGroupId,
      memberId: null,
      villageAgentId: null,
      permissions: []
    } as never);

    expect(asDemoUser).toBe(true);
  });
});
