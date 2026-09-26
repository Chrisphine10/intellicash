import { resolve } from "node:path";
import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { WIPE_OVERRIDE_FLAG, wipeRefusal } from "../prisma/destructive-guard";

const app = createApp();

describe("scripts that wipe data refuse a live database", () => {
  const prismaDir = resolve(__dirname, "../prisma");

  it("refuses in production", () => {
    expect(wipeRefusal({ NODE_ENV: "production", DATABASE_URL: "file:./dev.db" }, [], prismaDir)).toMatch(/production/);
  });

  it("refuses a database outside apps/api/prisma", () => {
    expect(
      wipeRefusal({ NODE_ENV: "development", DATABASE_URL: "file:/var/www/intellicash/data/intellicash.db" }, [], prismaDir)
    ).toMatch(/outside/);
    expect(wipeRefusal({ NODE_ENV: "development", DATABASE_URL: "file:../../../live.db" }, [], prismaDir)).toMatch(/outside/);
  });

  it("allows the development and test databases", () => {
    expect(wipeRefusal({ NODE_ENV: "test", DATABASE_URL: "file:./qa-rules.db" }, [], prismaDir)).toBeNull();
    expect(wipeRefusal({ NODE_ENV: "development" }, [], prismaDir)).toBeNull();
  });

  it("lets a person override it on purpose", () => {
    expect(wipeRefusal({ NODE_ENV: "production" }, [WIPE_OVERRIDE_FLAG], prismaDir)).toBeNull();
  });
});

describe("a request id reused for a different entry", () => {
  let admin: string[];
  let groupId: string;
  let memberId: string;
  let fundAccountId: string;

  beforeAll(async () => {
    await seedDatabase();
    const account = demoAccounts.find((entry) => entry.role === "IWL_ADMIN")!;
    const login = await request(app).post("/api/v1/auth/login").send({ phone: account.phone, password: demoPassword }).expect(200);
    const cookie = login.headers["set-cookie"];
    admin = Array.isArray(cookie) ? cookie : [cookie as unknown as string];
    const member = await prisma.member.findFirstOrThrow({ where: { status: "ACTIVE", group: { isDemo: false } } });
    memberId = member.id;
    groupId = member.groupId;
    fundAccountId = (await prisma.fundAccount.findFirstOrThrow({ where: { groupId, type: "INTERNAL_LOAN" } })).id;
  }, 90_000);

  const send = (amountCents: number, clientRequestId: string) =>
    request(app)
      .post(`/api/v1/groups/${groupId}/ledger`)
      .set("Cookie", admin)
      .send({ memberId, fundAccountId, type: "SHARE_PURCHASE", amountCents, direction: "CREDIT", description: "Request id check", clientRequestId });

  it("answers a genuine retry with the entry already saved", async () => {
    const key = `rid-${Date.now()}`;
    const first = await send(10_000, key).expect(201);
    const again = await send(10_000, key);
    expect([200, 201]).toContain(again.status);
    expect(again.body.data.id).toBe(first.body.data.id);
  });

  it("answers the same id on a different amount with what was saved, and moves no money twice", async () => {
    const key = `rid-other-${Date.now()}`;
    const first = await send(10_000, key).expect(201);
    const retried = await send(20_000, key);
    expect([200, 201]).toContain(retried.status);
    expect(retried.body.data.id).toBe(first.body.data.id);
    expect(retried.body.data.amountCents).toBe(10_000);
    expect(await prisma.ledgerEntry.count({ where: { clientRequestId: key } })).toBe(1);
  });

  it("refuses an id another group already used, rather than handing over that group's entry", async () => {
    const key = `rid-cross-${Date.now()}`;
    await send(10_000, key).expect(201);
    const other = await prisma.member.findFirstOrThrow({ where: { status: "ACTIVE", groupId: { not: groupId }, group: { isDemo: false } } });
    const otherFund = await prisma.fundAccount.findFirstOrThrow({ where: { groupId: other.groupId, type: "INTERNAL_LOAN" } });
    const refused = await request(app)
      .post(`/api/v1/groups/${other.groupId}/ledger`)
      .set("Cookie", admin)
      .send({ memberId: other.id, fundAccountId: otherFund.id, type: "SHARE_PURCHASE", amountCents: 10_000, direction: "CREDIT", description: "Cross-group id", clientRequestId: key })
      .expect(409);
    expect(refused.body.error.code).toBe("REQUEST_ID_REUSED");
  });
});
