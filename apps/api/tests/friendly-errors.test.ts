import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import bcrypt from "bcryptjs";
import { z } from "zod";

import { createApp } from "../src/app";
import { knownDatabaseRefusal, validationMessage } from "../src/lib/http";
import { prisma } from "../src/lib/prisma";

const app = createApp();

/**
 * Errors are read by group treasurers on a shared handset, not by developers.
 * Each one has to say what went wrong and what to do next.
 */

const PHONE = "254799004455";
const PASSWORD = "right-password-1";

describe("sign-in errors a person can act on", () => {
  let userId: string;

  beforeAll(async () => {
    const user = await prisma.user.upsert({
      where: { email: "friendly.errors@intellicash.test" },
      create: {
        name: "Friendly Errors Group",
        email: "friendly.errors@intellicash.test",
        phone: PHONE,
        passwordHash: await bcrypt.hash(PASSWORD, 10),
        role: "GROUP_ACCOUNT"
      },
      update: { phone: PHONE, status: "ACTIVE", passwordHash: await bcrypt.hash(PASSWORD, 10) },
      select: { id: true }
    });
    userId = user.id;
  }, 120000);

  it("says what to do on a wrong password", async () => {
    const response = await request(app).post("/api/v1/auth/login").send({ phone: PHONE, password: "wrong-one" }).expect(401);
    expect(response.body.error.code).toBe("INVALID_CREDENTIALS");
    expect(response.body.error.message).toMatch(/do not match an account/);
    expect(response.body.error.message).toMatch(/code sent by SMS/);
  });

  it("answers an unknown number exactly as a wrong password, so accounts cannot be probed", async () => {
    const unknown = await request(app).post("/api/v1/auth/login").send({ phone: "254799009999", password: "x-anything-1" });
    const wrong = await request(app).post("/api/v1/auth/login").send({ phone: PHONE, password: "wrong-one" });
    expect(unknown.status).toBe(wrong.status);
    expect(unknown.body.error.message).toBe(wrong.body.error.message);
  });

  it("says the account is not active only to someone with the right password", async () => {
    await prisma.user.update({ where: { id: userId }, data: { status: "SUSPENDED" } });
    try {
      const right = await request(app).post("/api/v1/auth/login").send({ phone: PHONE, password: PASSWORD }).expect(403);
      expect(right.body.error.code).toBe("ACCOUNT_NOT_ACTIVE");
      expect(right.body.error.message).toMatch(/not active/);

      const wrong = await request(app).post("/api/v1/auth/login").send({ phone: PHONE, password: "wrong-one" }).expect(401);
      expect(wrong.body.error.code).toBe("INVALID_CREDENTIALS");
    } finally {
      await prisma.user.update({ where: { id: userId }, data: { status: "ACTIVE" } });
    }
  });
});

describe("validation messages name the field", () => {
  const schema = z.object({ groupName: z.string().min(3), phone: z.string(), meetingDay: z.string() });

  it("names a missing field", () => {
    const result = schema.safeParse({ groupName: "Marui" });
    expect(result.success).toBe(false);
    if (!result.success) expect(validationMessage(result.error)).toBe("Phone is required. Check the other fields too.");
  });

  it("names a field that is too short, in words", () => {
    const result = schema.safeParse({ groupName: "M", phone: "1", meetingDay: "Mon" });
    if (!result.success) expect(validationMessage(result.error)).toBe("Group name must be at least 3 characters.");
  });

  it("uses a written sentence as it is, rather than gluing it to the field name", () => {
    const schema = z.object({ amountCents: z.number().int().max(100, "That amount is too large to record.") });
    const result = schema.safeParse({ amountCents: 500 });
    expect(result.success).toBe(false);
    if (!result.success) expect(validationMessage(result.error)).toBe("That amount is too large to record.");
  });

  it("calls a money field an amount, not 'amount cents'", () => {
    const schema = z.object({ amountCents: z.number().int().min(1) });
    const result = schema.safeParse({ amountCents: 0 });
    expect(result.success).toBe(false);
    if (!result.success) expect(validationMessage(result.error)).toBe("Amount must be at least 1.");
  });

  it("reaches the API response instead of 'Request validation failed.'", async () => {
    const response = await request(app).post("/api/v1/auth/login").send({ password: 5 }).expect(400);
    expect(response.body.error.message).not.toBe("Request validation failed.");
    expect(response.body.error.message.length).toBeGreaterThan(10);
  });
});

describe("database refusals a person can fix", () => {
  it("a duplicate says what is already recorded, as a conflict and not our fault", () => {
    const refusal = knownDatabaseRefusal({ code: "P2002", meta: { target: ["phone"] } });
    expect(refusal).toEqual({
      status: 409,
      code: "ALREADY_EXISTS",
      message: "Something with this phone number is already recorded. Use a different one, or open the existing record."
    });
  });

  it("a duplicate on an unnamed index still reads as a sentence", () => {
    expect(knownDatabaseRefusal({ code: "P2002", meta: { target: "Group_code_key" } })?.message).toContain("these details");
  });

  it("a record that has gone is 'not found', and one still in use says so", () => {
    expect(knownDatabaseRefusal({ code: "P2025" })?.status).toBe(404);
    expect(knownDatabaseRefusal({ code: "P2003" })?.code).toBe("IN_USE");
  });

  it("anything else is left to the generic handler", () => {
    expect(knownDatabaseRefusal(new Error("boom"))).toBeNull();
    expect(knownDatabaseRefusal({ code: "ECONNRESET" })).toBeNull();
    expect(knownDatabaseRefusal({ code: "P1001" })).toBeNull();
  });
});

