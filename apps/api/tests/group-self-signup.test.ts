import request from "supertest";
import { afterAll, describe, expect, it } from "vitest";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";

const app = createApp();

/**
 * Signing up as a group from the app.
 *
 * It used to create only the login — no group behind it — so the account
 * opened onto nothing and staff had to attach it by hand. And most groups the
 * field team signs up already exist, so creating one blindly would turn that
 * problem into duplicate empty groups instead.
 */

const NEW_PHONE = "0799 002 211";
const EXISTING_PHONE = "254799002212";

describe("a group signing itself up", () => {
  afterAll(async () => {
    const users = await prisma.user.findMany({
      where: { phone: { in: ["254799002211", EXISTING_PHONE, "254799002213"] } },
      select: { id: true, groupId: true }
    });
    await prisma.session.deleteMany({ where: { userId: { in: users.map((u) => u.id) } } });
    await prisma.user.deleteMany({ where: { id: { in: users.map((u) => u.id) } } });
    const groupIds = users.map((u) => u.groupId).filter((id): id is string => Boolean(id));
    await prisma.fundAccount.deleteMany({ where: { groupId: { in: groupIds } } });
    await prisma.group.deleteMany({
      where: { OR: [{ id: { in: groupIds } }, { name: { in: ["Signup Existing Group"] } }] }
    });
  });

  it("gets the group itself, with its funds, linked to the login", async () => {
    const response = await request(app)
      .post("/api/v1/auth/register")
      .send({ accountType: "GROUP", name: "Brand New Harvest Group", phone: NEW_PHONE, password: "harvest-2026", county: "Embu" })
      .expect(201);

    const groupId = response.body.data.groupId;
    expect(groupId).toBeTruthy();
    const group = await prisma.group.findUniqueOrThrow({
      where: { id: groupId },
      select: { name: true, county: true, code: true, contactPhone: true, sourceSystem: true, _count: { select: { fundAccounts: true } } }
    });
    expect(group.name).toBe("Brand New Harvest Group");
    expect(group.code).toMatch(/^IWL-EMB-/);
    expect(group.contactPhone).toBe("254799002211");
    expect(group.sourceSystem).toBe("MOBILE_SELF_SIGNUP");
    expect(group._count.fundAccounts).toBeGreaterThan(0);

    // And the account can see it.
    const cookie = response.headers["set-cookie"];
    const groups = await request(app).get("/api/v1/groups").set("Cookie", cookie).expect(200);
    expect(groups.body.data.map((g: { id: string }) => g.id)).toContain(groupId);
  });

  it("does not make a second group for a number already on a group", async () => {
    await prisma.group.create({
      data: { name: "Signup Existing Group", code: `IWL-TST-${Date.now()}`, phase: "MOBILISATION", county: "Embu", contactPhone: EXISTING_PHONE }
    });
    const before = await prisma.group.count();
    const response = await request(app)
      .post("/api/v1/auth/register")
      .send({ accountType: "GROUP", name: "Some Other Spelling", phone: "0799002212", password: "harvest-2026" })
      .expect(409);
    expect(response.body.error.code).toBe("GROUP_EXISTS");
    expect(response.body.error.details.canSignInWithCode).toBe(true);
    expect(await prisma.group.count()).toBe(before);
  });

  it("does not make a second group with the same name in the same county", async () => {
    const response = await request(app)
      .post("/api/v1/auth/register")
      .send({ accountType: "GROUP", name: "  signup  existing GROUP ", phone: "0799002213", password: "harvest-2026", county: "Embu" })
      .expect(409);
    expect(response.body.error.code).toBe("GROUP_EXISTS");
    expect(response.body.error.message).toMatch(/programme officer/);
  });
});
