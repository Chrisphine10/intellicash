import request from "supertest";
import bcrypt from "bcryptjs";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * A digital champion getting into a group that already exists.
 *
 * On production the field team signed champions up and, because their number
 * matched nothing, got a new group login attached to no group — nineteen empty
 * front doors while the real books went unopened. These tests pin the way back.
 */

async function signIn(phone: string, password = demoPassword) {
  const response = await request(app).post("/api/v1/auth/login").send({ phone, password }).expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

let counter = 0;
async function freshGroup(villageAgentId?: string | null) {
  counter += 1;
  return prisma.group.create({
    data: {
      name: `Champion Test Group ${counter}`,
      code: `CHMP-${Date.now()}-${counter}`,
      county: "Embu",
      phase: "MOBILISATION",
      villageAgentId: villageAgentId ?? null
    },
    select: { id: true, name: true, code: true }
  });
}

const uniquePhone = () => `2547${String(Date.now() + counter++).slice(-8)}`;

describe("linking a champion to an existing group", () => {
  let admin: string[];
  let agent: string[];
  let agentRecordId: string;

  beforeAll(async () => {
    await seedDatabase();
    admin = await signIn(demoAccounts.find((a) => a.role === "IWL_ADMIN")!.phone);
    const agentAccount = demoAccounts.find((a) => a.role === "VILLAGE_AGENT")!;
    agent = await signIn(agentAccount.phone);
    agentRecordId = (
      await prisma.user.findFirstOrThrow({ where: { phone: { contains: agentAccount.phone.slice(-9) } }, select: { villageAgentId: true } })
    ).villageAgentId as string;
  }, 180000);

  it("puts a free number on the group's own login, so a code gets the champion in", async () => {
    const group = await freshGroup();
    const login = await prisma.user.create({
      data: {
        name: group.name,
        email: `${group.code.toLowerCase()}@groups.test`,
        passwordHash: await bcrypt.hash("unknown-to-anyone", 10),
        role: "GROUP_ACCOUNT",
        groupId: group.id
      }
    });
    const phone = uniquePhone();

    const response = await request(app)
      .put(`/api/v1/groups/${group.id}/champion`)
      .set("Cookie", admin)
      .send({ championName: "Mary Wanjiru", phone })
      .expect(200);

    expect(response.body.data.outcome).toBe("PHONE_ATTACHED");
    expect((await prisma.user.findUniqueOrThrow({ where: { id: login.id } })).phone).toBe(phone);

    // And the code path now reaches THIS group.
    const code = (await request(app).post("/api/v1/auth/otp/request").send({ phone })).body.data.devCode;
    const signedIn = await request(app).post("/api/v1/auth/otp/verify").send({ phone, code }).expect(200);
    expect(signedIn.body.data.groupId).toBe(group.id);
  });

  it("attaches the orphan login a field sign-up made, so the champion's own password opens the real group", async () => {
    // Exactly what production has nineteen of.
    const group = await freshGroup();
    const phone = uniquePhone();
    const signup = await request(app)
      .post("/api/v1/auth/register")
      .send({ accountType: "GROUP", name: "Marui Women Group", phone, password: "champion-knows-this" })
      .expect(201);
    expect(signup.body.data.groupId).toBeNull();

    const response = await request(app)
      .put(`/api/v1/groups/${group.id}/champion`)
      .set("Cookie", admin)
      .send({ phone: `0${phone.slice(3)}` })
      .expect(200);

    expect(response.body.data.outcome).toBe("EXISTING_LOGIN_LINKED");

    // Nothing deleted; the password they already know now opens the group.
    const login = await request(app)
      .post("/api/v1/auth/login")
      .send({ phone, password: "champion-knows-this" })
      .expect(200);
    expect(login.body.data.groupId).toBe(group.id);
  });

  it("refuses a number that already opens a different group", async () => {
    const first = await freshGroup();
    const second = await freshGroup();
    const phone = uniquePhone();
    await request(app).put(`/api/v1/groups/${first.id}/champion`).set("Cookie", admin).send({ phone }).expect(200);

    // A code sent to that number would open the first group, not the second.
    const response = await request(app)
      .put(`/api/v1/groups/${second.id}/champion`)
      .set("Cookie", admin)
      .send({ phone })
      .expect(409);
    expect(response.body.error.code).toBe("PHONE_ALREADY_HAS_ACCOUNT");
  });

  it("refuses a number that belongs to a member's account", async () => {
    const group = await freshGroup();
    const member = await prisma.user.findFirstOrThrow({ where: { role: "MEMBER", phone: { not: null } }, select: { phone: true } });
    await request(app)
      .put(`/api/v1/groups/${group.id}/champion`)
      .set("Cookie", admin)
      .send({ phone: member.phone })
      .expect(409);
  });

  it("creates a login for a group that never had one", async () => {
    const group = await freshGroup();
    const response = await request(app)
      .put(`/api/v1/groups/${group.id}/champion`)
      .set("Cookie", admin)
      .send({ phone: uniquePhone() })
      .expect(200);
    expect(response.body.data.outcome).toBe("LOGIN_CREATED");
  });

  it("is idempotent when repeated", async () => {
    const group = await freshGroup();
    const phone = uniquePhone();
    await request(app).put(`/api/v1/groups/${group.id}/champion`).set("Cookie", admin).send({ phone }).expect(200);
    const again = await request(app).put(`/api/v1/groups/${group.id}/champion`).set("Cookie", admin).send({ phone }).expect(200);
    expect(again.body.data.outcome).toBe("ALREADY_LINKED");
  });

  it("lets an agent do it for their own group, and no other", async () => {
    const mine = await freshGroup(agentRecordId);
    const notMine = await freshGroup(null);

    await request(app).put(`/api/v1/groups/${mine.id}/champion`).set("Cookie", agent).send({ phone: uniquePhone() }).expect(200);
    await request(app).put(`/api/v1/groups/${notMine.id}/champion`).set("Cookie", agent).send({ phone: uniquePhone() }).expect(404);
  });

  it("rejects a number that cannot receive a code", async () => {
    const group = await freshGroup();
    await request(app).put(`/api/v1/groups/${group.id}/champion`).set("Cookie", admin).send({ phone: "020 123 4567" }).expect(400);
  });

  it("records who did it", async () => {
    const group = await freshGroup();
    await request(app).put(`/api/v1/groups/${group.id}/champion`).set("Cookie", admin).send({ phone: uniquePhone() }).expect(200);
    const event = await prisma.auditEvent.findFirst({ where: { entityId: group.id, type: "GROUP_CHAMPION_LINKED" } });
    expect(event?.actorUserId).toBeTruthy();
  });
});
