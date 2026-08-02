import request from "supertest";
import bcrypt from "bcryptjs";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * Correcting a member's name and phone number.
 *
 * The name is cosmetic. The phone is not: a join request is matched to an
 * existing member BY PHONE, so a member whose number no longer matches is
 * handed a fresh empty passbook instead of the savings already recorded
 * against her name. Until 2 Aug 2026 this endpoint stored whatever string was
 * typed — so "0712345678" and "+254 712 345 678" were two different people —
 * and it let two members in one group share a number.
 */
describe("editing member details", () => {
  let cookies: string[];
  let groupId: string;
  let memberId: string;
  let otherMemberId: string;

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirstOrThrow({ orderBy: { createdAt: "asc" } });
    groupId = group.id;

    const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
    const login = await request(app)
      .post("/api/v1/auth/login")
      .send({ phone: admin.phone, password: demoPassword })
      .expect(200);
    const cookie = login.headers["set-cookie"];
    cookies = Array.isArray(cookie) ? cookie : [cookie as unknown as string];

    const member = await prisma.member.create({
      data: { groupId, fullName: "Jane Wanjiku", phone: "254711000111", status: "ACTIVE" }
    });
    memberId = member.id;

    const other = await prisma.member.create({
      data: { groupId, fullName: "Somebody Else", phone: "254711000222", status: "ACTIVE" }
    });
    otherMemberId = other.id;
  }, 60000);

  function patch(id: string, body: Record<string, unknown>) {
    return request(app)
      .patch(`/api/v1/groups/${groupId}/members/${id}`)
      .set("Cookie", cookies)
      .send(body);
  }

  it("corrects a misspelled name", async () => {
    const response = await patch(memberId, { fullName: "Jane Wanjiru" }).expect(200);
    expect(response.body.data.fullName).toBe("Jane Wanjiru");
  });

  it("stores a phone in canonical form, however it was typed", async () => {
    // The same woman, written the way people actually write it.
    await patch(memberId, { phone: "0722 987 654" }).expect(200);

    const stored = await prisma.member.findUniqueOrThrow({ where: { id: memberId } });
    expect(stored.phone).toBe("254722987654");
  });

  it("recognises the same number written differently", async () => {
    // Re-saving the same person's number in another format is not a change,
    // and must not trip the duplicate check against herself.
    await patch(memberId, { phone: "+254 722 987 654" }).expect(200);
    const stored = await prisma.member.findUniqueOrThrow({ where: { id: memberId } });
    expect(stored.phone).toBe("254722987654");
  });

  it("refuses a number that already belongs to another member", async () => {
    const response = await patch(memberId, { phone: "254711000222" }).expect(409);
    expect(response.body.error.code).toBe("PHONE_ALREADY_IN_GROUP");
    expect(response.body.error.message).toMatch(/Somebody Else/);

    // Unchanged — a refused edit must not half-apply.
    const stored = await prisma.member.findUniqueOrThrow({ where: { id: memberId } });
    expect(stored.phone).toBe("254722987654");
  });

  it("catches a duplicate written in a different format", async () => {
    // The whole point of canonicalising: 0711000222 IS 254711000222.
    const response = await patch(memberId, { phone: "0711000222" }).expect(409);
    expect(response.body.error.code).toBe("PHONE_ALREADY_IN_GROUP");
  });

  it("refuses a number that is not usable", async () => {
    await patch(memberId, { phone: "12" }).expect(400);
  });

  it("moves the member's sign-in account to the new number", async () => {
    // A member with an account signs in WITH THIS NUMBER. Leaving the account
    // behind locks her out of her own savings while the roster looks correct.
    const account = await prisma.user.create({
      data: {
        name: "Jane Wanjiru",
        email: "jane.wanjiru@example.com",
        phone: "254722987654",
        passwordHash: await bcrypt.hash("unused-by-this-test", 12),
        role: "MEMBER",
        memberId
      }
    });

    await patch(memberId, { phone: "0733111222" }).expect(200);

    const reloaded = await prisma.user.findUniqueOrThrow({ where: { id: account.id } });
    expect(reloaded.phone).toBe("254733111222");
  });

  it("refuses when the new number already has a sign-in account", async () => {
    await prisma.user.create({
      data: {
        name: "Unrelated Account",
        email: "unrelated@example.com",
        phone: "254744555666",
        passwordHash: await bcrypt.hash("unused-by-this-test", 12),
        role: "MEMBER"
      }
    });

    const response = await patch(memberId, { phone: "0744555666" }).expect(409);
    expect(response.body.error.code).toBe("PHONE_ALREADY_HAS_ACCOUNT");

    // Neither side moved: the member keeps her number and the other account
    // keeps its own.
    const member = await prisma.member.findUniqueOrThrow({ where: { id: memberId } });
    expect(member.phone).toBe("254733111222");
  });

  it("records what the number was before it changed", async () => {
    // Without the old value nobody can tell a corrected typo from a member
    // quietly pointed at a different person.
    const before = await prisma.member.findUniqueOrThrow({ where: { id: memberId } });
    await patch(memberId, { phone: "0755666777" }).expect(200);

    const event = await prisma.auditEvent.findFirst({
      where: { entityType: "MEMBER", entityId: memberId, type: "MEMBER_UPDATED" },
      orderBy: { createdAt: "desc" }
    });
    expect(event).not.toBeNull();
    expect(JSON.stringify(event)).toContain(before.phone);
  });

  it("adding a member on an existing number is refused too", async () => {
    // Guarding the edit path while leaving the add path open would let
    // duplicates in the front door.
    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/members`)
      .set("Cookie", cookies)
      .send({ fullName: "Duplicate Person", phone: "0711000222" })
      .expect(409);

    expect(response.body.error.code).toBe("PHONE_ALREADY_IN_GROUP");
  });

  it("a new member's number is stored canonical", async () => {
    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/members`)
      .set("Cookie", cookies)
      .send({ fullName: "Newly Added", phone: "0766 123 456" })
      .expect(201);

    const created = await prisma.member.findUniqueOrThrow({
      where: { id: response.body.data.member?.id ?? response.body.data.id }
    });
    expect(created.phone).toBe("254766123456");
    expect(otherMemberId).toBeTruthy();
  });
});
