import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * Members the phone sends up during automatic sync.
 *
 * Members made on a handset never reached the server, so their attendance and
 * money stayed on the phone. The phone retries on a bad signal, so the
 * endpoint must find the member it already made rather than make another.
 */

async function signIn(role: string) {
  const account = demoAccounts.find((entry) => entry.role === role)!;
  const response = await request(app).post("/api/v1/auth/login").send({ phone: account.phone, password: demoPassword }).expect(200);
  const cookie = response.headers["set-cookie"];
  return { cookie: Array.isArray(cookie) ? cookie : [cookie as unknown as string], groupId: response.body.data.groupId as string };
}

describe("members sent up by the phone", () => {
  let group: { cookie: string[]; groupId: string };

  beforeAll(async () => {
    await seedDatabase();
    group = await signIn("GROUP_ACCOUNT");
  }, 120000);

  it("creates a member the server did not know, and a retry finds the same one", async () => {
    const first = await request(app)
      .post(`/api/v1/groups/${group.groupId}/members/sync`)
      .set("Cookie", group.cookie)
      .send({ fullName: "Ian Kamau", phone: "0799 004 411" })
      .expect(201);
    expect(first.body.data.matched).toBe(false);

    const retry = await request(app)
      .post(`/api/v1/groups/${group.groupId}/members/sync`)
      .set("Cookie", group.cookie)
      .send({ fullName: "Ian  Kamau", phone: "+254799004411" })
      .expect(200);
    expect(retry.body.data).toEqual({ id: first.body.data.id, matched: true });
  });

  it("accepts a member entered by name alone, and finds them again by name", async () => {
    const first = await request(app)
      .post(`/api/v1/groups/${group.groupId}/members/sync`)
      .set("Cookie", group.cookie)
      .send({ fullName: "Wanjiru Name Only" })
      .expect(201);
    const again = await request(app)
      .post(`/api/v1/groups/${group.groupId}/members/sync`)
      .set("Cookie", group.cookie)
      .send({ fullName: "wanjiru name only", phone: null })
      .expect(200);
    expect(again.body.data.id).toBe(first.body.data.id);
    const stored = await prisma.member.findUniqueOrThrow({ where: { id: first.body.data.id }, select: { phone: true } });
    expect(stored.phone).toBe("");
  });

  it("does not store an unusable number as though it identified someone", async () => {
    // Found on the emulator: the phone let "12345" through, and the server kept
    // it verbatim as the member's phone.
    const response = await request(app)
      .post(`/api/v1/groups/${group.groupId}/members/sync`)
      .set("Cookie", group.cookie)
      .send({ fullName: "Junk Number Member", phone: "12345" })
      .expect(201);
    const stored = await prisma.member.findUniqueOrThrow({ where: { id: response.body.data.id }, select: { phone: true, fullName: true } });
    expect(stored.fullName).toBe("Junk Number Member");
    expect(stored.phone).toBe("");
  });

  it("refuses to add a member with a number nobody could dial", async () => {
    const response = await request(app)
      .post(`/api/v1/groups/${group.groupId}/members`)
      .set("Cookie", group.cookie)
      .send({ fullName: "Short Number", phone: "1234567" })
      .expect(400);
    expect(response.body.error.message).toMatch(/valid phone number/i);
  });

  it("matches a member the server already had instead of duplicating them", async () => {
    const known = await prisma.member.findFirstOrThrow({
      where: { groupId: group.groupId, phone: { not: "" } },
      select: { id: true, fullName: true, phone: true }
    });
    const response = await request(app)
      .post(`/api/v1/groups/${group.groupId}/members/sync`)
      .set("Cookie", group.cookie)
      .send({ fullName: known.fullName, phone: known.phone })
      .expect(200);
    expect(response.body.data).toEqual({ id: known.id, matched: true });
  });

  it("does not text a PIN to members arriving from the phone", async () => {
    const before = await prisma.smsBroadcastRecipient.count();
    await request(app)
      .post(`/api/v1/groups/${group.groupId}/members/sync`)
      .set("Cookie", group.cookie)
      .send({ fullName: "No Sms Member", phone: "0799004412" })
      .expect(201);
    expect(await prisma.smsBroadcastRecipient.count()).toBe(before);
  });

  it("will not put a member into a group the account does not hold", async () => {
    const other = await prisma.group.findFirstOrThrow({ where: { id: { not: group.groupId } }, select: { id: true } });
    await request(app)
      .post(`/api/v1/groups/${other.id}/members/sync`)
      .set("Cookie", group.cookie)
      .send({ fullName: "Intruder" })
      .expect((response) => expect([403, 404]).toContain(response.status));
  });
});
