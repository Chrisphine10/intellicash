import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * A village agent serves many programmes, all belonging to one partner.
 *
 * The old model gave an agent a single `programmeId`, so somebody setting up an
 * agent who works across three of a partner's programmes had to pick one and
 * drop the other two. The set now lives in a join table.
 *
 * The rule that matters is the second half: **one partner**. An agent whose
 * programmes spanned two partners would carry another partner's groups,
 * ratings and visit notes inside their own caseload, which is a confidentiality
 * failure rather than untidiness — so it is refused rather than warned about.
 */

async function signIn(identifier: string, password = demoPassword) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: identifier, password })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

describe("a village agent's programmes", () => {
  let admin: string[];
  let partnerA: string;
  let partnerB: string;
  let programmeA1: string;
  let programmeA2: string;
  let programmeB1: string;

  beforeAll(async () => {
    await seedDatabase();
    const account = demoAccounts.find((entry) => entry.role === "IWL_ADMIN")!;
    admin = await signIn(account.phone);

    const seeded = await prisma.programme.findFirstOrThrow({ select: { id: true, partnerId: true } });
    programmeA1 = seeded.id;
    partnerA = seeded.partnerId;

    // A second programme under the SAME partner — the case the change exists
    // for — and one under a different partner, which must be refused.
    programmeA2 = (
      await prisma.programme.create({
        data: { partnerId: partnerA, name: "Second Programme, Same Partner", country: "Kenya" },
        select: { id: true }
      })
    ).id;

    const otherPartner = await prisma.partner.create({
      data: { name: "Unrelated Partner", type: "NGO" },
      select: { id: true }
    });
    partnerB = otherPartner.id;
    programmeB1 = (
      await prisma.programme.create({
        data: { partnerId: partnerB, name: "Programme Of Another Partner", country: "Kenya" },
        select: { id: true }
      })
    ).id;
  }, 180000);

  // Not `async`: the supertest chain has to reach the caller so each test can
  // assert its own status.
  function createAgent(programmeIds: string[], name: string) {
    return request(app)
      .post("/api/v1/village-agents")
      .set("Cookie", admin)
      .send({
        name,
        phone: `2547${Math.floor(10_000_000 + Math.random() * 80_000_000)}`,
        programmeIds
      });
  }

  it("takes several programmes from one partner", async () => {
    const response = await createAgent([programmeA1, programmeA2], "Multi Programme Agent").expect(201);

    const served = response.body.data.programmeLinks.map(
      (link: { programme: { id: string } }) => link.programme.id
    );
    expect(served.sort()).toEqual([programmeA1, programmeA2].sort());

    // The partner is derived from the programmes rather than sent, so it can
    // never disagree with them.
    expect(response.body.data.partner?.id).toBe(partnerA);
  });

  it("refuses a set that spans two partners", async () => {
    const response = await createAgent([programmeA1, programmeB1], "Cross Partner Agent").expect(400);

    expect(response.body.error.code).toBe("AGENT_PROGRAMMES_CROSS_PARTNER");
    // The message names the programmes, because the person is choosing from a
    // list of names and needs to know which tick to undo.
    expect(response.body.error.message).toContain("Programme Of Another Partner");
  });

  it("leaves nothing behind when it refuses", async () => {
    const before = await prisma.villageAgent.count();
    await createAgent([programmeA1, programmeB1], "Never Created").expect(400);

    // The create and the assignment share one transaction. A half-made agent
    // with no programmes would be worse than the error.
    expect(await prisma.villageAgent.count()).toBe(before);
  });

  it("replaces the set rather than adding to it", async () => {
    const created = await createAgent([programmeA1, programmeA2], "Reassigned Agent").expect(201);
    const agentId = created.body.data.id;

    const updated = await request(app)
      .patch(`/api/v1/village-agents/${agentId}`)
      .set("Cookie", admin)
      .send({ programmeIds: [programmeA2] })
      .expect(200);

    expect(
      updated.body.data.programmeLinks.map((link: { programme: { id: string } }) => link.programme.id)
    ).toEqual([programmeA2]);
  });

  it("keeps the original assignment date when an unrelated field changes", async () => {
    const created = await createAgent([programmeA1], "Long Serving Agent").expect(201);
    const agentId = created.body.data.id;
    const linkedAt = await prisma.villageAgentProgramme.findFirstOrThrow({
      where: { villageAgentId: agentId, programmeId: programmeA1 },
      select: { createdAt: true }
    });

    await request(app)
      .patch(`/api/v1/village-agents/${agentId}`)
      .set("Cookie", admin)
      .send({ programmeIds: [programmeA1, programmeA2] })
      .expect(200);

    const after = await prisma.villageAgentProgramme.findFirstOrThrow({
      where: { villageAgentId: agentId, programmeId: programmeA1 },
      select: { createdAt: true }
    });

    // "Since when has this agent covered this programme" is a real question a
    // supervisor asks; it must survive an edit that had nothing to do with it.
    expect(after.createdAt.getTime()).toBe(linkedAt.createdAt.getTime());
  });

  it("accepts an agent with no programme at all", async () => {
    // Between assignments is a normal state, not an error.
    const response = await createAgent([], "Unassigned Agent").expect(201);
    expect(response.body.data.programmeLinks).toEqual([]);
  });

  it("still accepts a single programmeId from an older caller", async () => {
    const response = await request(app)
      .post("/api/v1/village-agents")
      .set("Cookie", admin)
      .send({
        name: "Legacy Shape Agent",
        phone: `2547${Math.floor(10_000_000 + Math.random() * 80_000_000)}`,
        programmeId: programmeA1
      })
      .expect(201);

    expect(
      response.body.data.programmeLinks.map((link: { programme: { id: string } }) => link.programme.id)
    ).toEqual([programmeA1]);
  });
});
