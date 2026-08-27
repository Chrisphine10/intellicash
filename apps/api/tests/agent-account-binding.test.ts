import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * A village agent account must be bound to a village agent.
 *
 * `scopeGroupWhere` scopes an agent by `user.villageAgentId`, and returns an
 * impossible filter when it is null — so an agent account without that link
 * sees no groups anywhere. Every screen answers 404 and it reads as "the app is
 * not loading", not as a mis-configured account.
 *
 * `normalizeUserBinding` had branches for admin, partner, group and member, and
 * none for VILLAGE_AGENT, so an agent fell through to the member branch: the
 * console demanded a member for an agent account, and never set the agent link
 * at all.
 */

async function signIn(identifier: string, password = demoPassword) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: identifier, password })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

describe("village agent accounts", () => {
  let admin: string[];
  let agentRecordId: string;

  beforeAll(async () => {
    await seedDatabase();
    const account = demoAccounts.find((entry) => entry.role === "IWL_ADMIN")!;
    admin = await signIn(account.phone);

    agentRecordId = (
      await prisma.villageAgent.findFirstOrThrow({ select: { id: true } })
    ).id;
  }, 180000);

  let made = 0;
  function newAgentAccount(body: Record<string, unknown> = {}) {
    made += 1;
    return request(app)
      .post("/api/v1/users")
      .set("Cookie", admin)
      .send({
        name: `Field Agent ${made}`,
        email: `field.agent.${made}.${Date.now()}@intellicash.test`,
        password: "A-long-enough-password-1",
        role: "VILLAGE_AGENT",
        ...body
      });
  }

  it("creates an agent account bound to the agent record", async () => {
    const response = await newAgentAccount({ villageAgentId: agentRecordId }).expect(201);

    // Without this the account is a village agent that is not any village
    // agent, and every group lookup 404s.
    const row = await prisma.user.findUniqueOrThrow({
      where: { id: response.body.data.id },
      select: { villageAgentId: true, memberId: true }
    });
    expect(row.villageAgentId).toBe(agentRecordId);
    expect(row.memberId).toBeNull();
  });

  it("refuses an agent account with no agent record", async () => {
    // Better a refusal at creation than an account that signs in, shows an
    // empty caseload, and looks like a broken app.
    const response = await newAgentAccount().expect(400);
    expect(response.body.error.code).toBe("VILLAGE_AGENT_REQUIRED");
  });

  it("refuses an agent record that does not exist", async () => {
    const response = await newAgentAccount({ villageAgentId: "no-such-agent" }).expect(404);
    expect(response.body.error.code).toBe("VILLAGE_AGENT_NOT_FOUND");
  });

  it("does not demand a member for an agent account", async () => {
    // The old fall-through asked for a member, which is a different kind of
    // account entirely.
    const response = await newAgentAccount({ villageAgentId: agentRecordId });
    expect(response.body.error?.code).not.toBe("MEMBER_REQUIRED");
  });

  it("lets an admin correct an agent's name and phone without breaking the link", async () => {
    const created = await newAgentAccount({ villageAgentId: agentRecordId }).expect(201);

    // The edit path runs the same normaliser. An agent whose phone is being
    // corrected must not lose their caseload as a side effect.
    await request(app)
      .patch(`/api/v1/users/${created.body.data.id}`)
      .set("Cookie", admin)
      .send({ name: "Corrected Agent Name", phone: "254705060708" })
      .expect(200);

    const row = await prisma.user.findUniqueOrThrow({
      where: { id: created.body.data.id },
      select: { villageAgentId: true, name: true, phone: true }
    });
    expect(row.villageAgentId).toBe(agentRecordId);
    expect(row.name).toBe("Corrected Agent Name");
    expect(row.phone).toBe("254705060708");
  });

  it("can move an account to a different agent record", async () => {
    const created = await newAgentAccount({ villageAgentId: agentRecordId }).expect(201);

    const other = await prisma.villageAgent.create({
      data: { name: "Second Agent", phone: `2547${Date.now()}`.slice(0, 12) },
      select: { id: true }
    });

    await request(app)
      .patch(`/api/v1/users/${created.body.data.id}`)
      .set("Cookie", admin)
      .send({ villageAgentId: other.id })
      .expect(200);

    const row = await prisma.user.findUniqueOrThrow({
      where: { id: created.body.data.id },
      select: { villageAgentId: true }
    });
    expect(row.villageAgentId).toBe(other.id);
  });

  it("clears the agent link when the account stops being an agent", async () => {
    const created = await newAgentAccount({ villageAgentId: agentRecordId }).expect(201);

    await request(app)
      .patch(`/api/v1/users/${created.body.data.id}`)
      .set("Cookie", admin)
      .send({ role: "READ_ONLY" })
      .expect(200);

    // A stale agent link on a non-agent account is a caseload nobody is
    // watching.
    const row = await prisma.user.findUniqueOrThrow({
      where: { id: created.body.data.id },
      select: { villageAgentId: true }
    });
    expect(row.villageAgentId).toBeNull();
  });

  it("sees its own groups once bound", async () => {
    // The end of the chain, and the thing the field actually reports: an agent
    // whose account is bound can read a group's enterprises.
    const group = await prisma.group.findFirst({
      where: { villageAgentId: agentRecordId },
      select: { id: true }
    });
    if (!group) return;

    const agentAccount = demoAccounts.find((entry) => entry.role === "VILLAGE_AGENT");
    if (!agentAccount) return;

    const cookies = await signIn(agentAccount.phone);
    await request(app)
      .get(`/api/v1/groups/${group.id}/enterprises`)
      .set("Cookie", cookies)
      .expect(200);
  });
});
