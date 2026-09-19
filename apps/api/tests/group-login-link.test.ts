import request from "supertest";
import bcrypt from "bcryptjs";
import { afterAll, beforeAll, describe, expect, it } from "vitest";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { AUTO_GROUP_SOURCE, ensureGroupForLogin, normaliseGroupName } from "../src/services/group-login-link";

const app = createApp();

/**
 * No group login is left opening nothing.
 *
 * The rule's safety is in what it refuses to guess: a name that could be one of
 * two real groups must not be attached to either, or one group's books open
 * for another group's champion.
 */

const PREFIX = "LinkTest";
let seq = 0;
const uniquePhone = () => `25479900${String(3000 + seq++).padStart(4, "0")}`;

async function orphan(name: string, phone = uniquePhone(), password = "orphan-pass-1") {
  return prisma.user.create({
    data: {
      name,
      email: `${phone}@accounts.intellicash.app`,
      phone,
      passwordHash: await bcrypt.hash(password, 10),
      role: "GROUP_ACCOUNT"
    },
    select: { id: true, phone: true }
  });
}

async function group(name: string, contactPhone?: string) {
  return prisma.group.create({
    data: { name, code: `IWL-LNK-${Date.now()}-${seq++}`, phase: "MOBILISATION", county: "Embu", contactPhone },
    select: { id: true }
  });
}

describe("linking group logins to their groups", () => {
  beforeAll(async () => {
    await cleanup();
  });
  afterAll(cleanup);

  async function cleanup() {
    const users = await prisma.user.findMany({
      where: { OR: [{ name: { startsWith: PREFIX } }, { phone: { startsWith: "254799003" } }] },
      select: { id: true }
    });
    const ids = users.map((u) => u.id);
    await prisma.session.deleteMany({ where: { userId: { in: ids } } });
    await prisma.auditEvent.deleteMany({ where: { entityId: { in: ids } } });
    await prisma.user.deleteMany({ where: { id: { in: ids } } });
    const groups = await prisma.group.findMany({ where: { name: { startsWith: PREFIX } }, select: { id: true } });
    await prisma.fundAccount.deleteMany({ where: { groupId: { in: groups.map((g) => g.id) } } });
    await prisma.group.deleteMany({ where: { id: { in: groups.map((g) => g.id) } } });
  }

  it("reads 'S H G', 'SHG' and 'Self Help Group' alike, but keeps '(II)' distinct", () => {
    expect(normaliseGroupName("Mwikuria S H G")).toBe(normaliseGroupName("Mwikuria Self Help Group"));
    expect(normaliseGroupName("Gaciangara SHG")).toBe(normaliseGroupName("Gaciangara Self-Help Group"));
    expect(normaliseGroupName("Mwikuria Self Help Group (II)")).not.toBe(normaliseGroupName("Mwikuria SHG"));
  });

  it("links by the champion's number first", async () => {
    const login = await orphan(`${PREFIX} Some Other Spelling`);
    const target = await group(`${PREFIX} Marui Women Group`, login.phone!);
    const result = await ensureGroupForLogin(login.id);
    expect(result.outcome).toBe("LINKED_BY_PHONE");
    expect(result.groupId).toBe(target.id);
  });

  it("links by name when the number is not on record", async () => {
    const target = await group(`${PREFIX} Kiguru Self Help Group`);
    const login = await orphan(`${PREFIX} Kiguru S H G`);
    const result = await ensureGroupForLogin(login.id);
    expect(result.outcome).toBe("LINKED_BY_NAME");
    expect(result.groupId).toBe(target.id);
  });

  it("will not choose between a group and its '(II)' twin — it gives the login its own group", async () => {
    const first = await group(`${PREFIX} Mwikuria Self Help Group`);
    const second = await group(`${PREFIX} Mwikuria Self Help Group (II)`);
    const login = await orphan(`${PREFIX} Mwikuria S H G`);
    const result = await ensureGroupForLogin(login.id);
    expect(result.outcome).toBe("GROUP_CREATED");
    expect([first.id, second.id]).not.toContain(result.groupId);
    expect(result.possibleDuplicates).toEqual(
      expect.arrayContaining([`${PREFIX} Mwikuria Self Help Group (II)`])
    );
    const created = await prisma.group.findUniqueOrThrow({
      where: { id: result.groupId! },
      select: { sourceSystem: true, onboardingFeedback: true, _count: { select: { fundAccounts: true } } }
    });
    expect(created.sourceSystem).toBe(AUTO_GROUP_SOURCE);
    expect(created.onboardingFeedback).toMatch(/Possible duplicate/);
    expect(created._count.fundAccounts).toBeGreaterThan(0);
  });

  it("gives a login that matches nothing a group of its own", async () => {
    const login = await orphan(`${PREFIX} Osiepe`);
    const result = await ensureGroupForLogin(login.id);
    expect(result.outcome).toBe("GROUP_CREATED");
    expect(result.groupId).toBeTruthy();
  });

  it("links an unlinked login the moment it signs in", async () => {
    const login = await orphan(`${PREFIX} Waigiri Vision`, uniquePhone(), "waigiri-pass-1");
    const response = await request(app)
      .post("/api/v1/auth/login")
      .send({ phone: login.phone, password: "waigiri-pass-1" })
      .expect(200);
    expect(response.body.data.groupId).toBeTruthy();
    const stored = await prisma.user.findUniqueOrThrow({ where: { id: login.id }, select: { groupId: true } });
    expect(stored.groupId).toBe(response.body.data.groupId);
  });

  it("leaves a login that already has its group alone", async () => {
    const target = await group(`${PREFIX} Already Linked Group`);
    const login = await orphan(`${PREFIX} Already Linked Group`);
    await prisma.user.update({ where: { id: login.id }, data: { groupId: target.id } });
    const result = await ensureGroupForLogin(login.id);
    expect(result).toEqual({ outcome: "ALREADY_LINKED", groupId: target.id });
  });

  it("leaves a closed account alone — it cannot sign in, and a group for it is clutter", async () => {
    const login = await orphan(`${PREFIX} Closed Login`);
    await prisma.user.update({ where: { id: login.id }, data: { status: "CLOSED" } });
    const result = await ensureGroupForLogin(login.id);
    expect(result.outcome).toBe("CLOSED_SKIPPED");
    expect(result.groupId).toBeNull();
  });
});
