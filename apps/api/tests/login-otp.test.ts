import request from "supertest";
import { beforeAll, beforeEach, describe, expect, it } from "vitest";
import bcrypt from "bcryptjs";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { verifyLoginOtp, requestLoginOtp } from "../src/services/login-otp-service";

const app = createApp();

/**
 * Signing in with a texted code and no password.
 *
 * Six digits is a million guesses, which is nothing to a machine — so none of
 * the safety here is in the code itself. It is in the things around it, and
 * every one of them is asserted below, because each is the sort of property
 * that can be removed in a refactor without anything appearing to break.
 */

const PHONE = "254799001122";

describe("signing in with a code", () => {
  let userId: string;

  beforeAll(async () => {
    const user = await prisma.user.upsert({
      where: { email: "otp.test@intellicash.test" },
      create: {
        name: "OTP Test Group",
        email: "otp.test@intellicash.test",
        phone: PHONE,
        passwordHash: await bcrypt.hash("irrelevant-for-this-flow", 10),
        role: "GROUP_ACCOUNT"
      },
      update: { phone: PHONE, status: "ACTIVE" },
      select: { id: true }
    });
    userId = user.id;
  }, 120000);

  beforeEach(async () => {
    await prisma.userLoginOtp.deleteMany({ where: { userId } });
  });

  async function issueCode() {
    const result = await requestLoginOtp(PHONE);
    // Present only because SMS network calls are off in tests, which is what
    // makes an end-to-end assertion of this flow possible at all.
    expect(result.devCode).toBeTruthy();
    return result.devCode as string;
  }

  it("signs in with the code and no password at all", async () => {
    const code = await issueCode();

    const response = await request(app)
      .post("/api/v1/auth/otp/verify")
      .send({ phone: PHONE, code })
      .expect(200);

    expect(response.body.data.id).toBe(userId);
    expect(response.headers["set-cookie"]).toBeTruthy();
  });

  it("accepts the number typed any way the person knows it", async () => {
    // Somebody whose account says 254799001122 will type 0799 001122.
    const code = await issueCode();

    await request(app)
      .post("/api/v1/auth/otp/verify")
      .send({ phone: "0799 001 122", code })
      .expect(200);
  });

  it("burns the code once it works", async () => {
    const code = await issueCode();

    await request(app).post("/api/v1/auth/otp/verify").send({ phone: PHONE, code }).expect(200);
    // A code read off a handset an hour later must be worthless.
    await request(app).post("/api/v1/auth/otp/verify").send({ phone: PHONE, code }).expect(401);
  });

  it("kills the old code when a new one is asked for", async () => {
    const first = await issueCode();
    // Past the resend throttle.
    await prisma.userLoginOtp.update({
      where: { userId },
      data: { lastSentAt: new Date(Date.now() - 120_000) }
    });
    const second = await issueCode();

    expect(second).not.toBe(first);
    // These handsets keep every message ever received; the older one is dead.
    await request(app).post("/api/v1/auth/otp/verify").send({ phone: PHONE, code: first }).expect(401);
    await request(app).post("/api/v1/auth/otp/verify").send({ phone: PHONE, code: second }).expect(200);
  });

  it("refuses a code that has expired", async () => {
    const code = await issueCode();
    await prisma.userLoginOtp.update({
      where: { userId },
      data: { expiresAt: new Date(Date.now() - 1000) }
    });

    await request(app).post("/api/v1/auth/otp/verify").send({ phone: PHONE, code }).expect(401);
  });

  it("destroys the code after five wrong guesses — but not the account", async () => {
    const code = await issueCode();

    for (let attempt = 0; attempt < 5; attempt += 1) {
      const wrong = String((Number(code) + attempt + 1) % 1_000_000).padStart(6, "0");
      await request(app).post("/api/v1/auth/otp/verify").send({ phone: PHONE, code: wrong });
    }

    // The right code no longer works: the code was burned.
    await request(app).post("/api/v1/auth/otp/verify").send({ phone: PHONE, code }).expect(401);

    // But the ACCOUNT is untouched. Locking it would let anyone who knows a
    // group's number keep that group out of its own books.
    const user = await prisma.user.findUniqueOrThrow({ where: { id: userId } });
    expect(user.status).toBe("ACTIVE");

    // And a fresh code still gets them in.
    await prisma.userLoginOtp.deleteMany({ where: { userId } });
    const fresh = await issueCode();
    await request(app).post("/api/v1/auth/otp/verify").send({ phone: PHONE, code: fresh }).expect(200);
  });

  it("never says whether a number has an account", async () => {
    // The tell that matters: this answer means "this savings group banks with
    // you", which is worth more to somebody picking a target than an ordinary
    // account check.
    const known = await request(app)
      .post("/api/v1/auth/otp/request")
      .send({ phone: PHONE })
      .expect(200);

    const unknown = await request(app)
      .post("/api/v1/auth/otp/request")
      .send({ phone: "254700999888" })
      .expect(200);

    // devCode is the one field that legitimately differs, and it exists only
    // with SMS switched off. Everything the real client sees must match.
    const strip = (body: Record<string, unknown>) => {
      const { devCode: _devCode, ...rest } = body as { devCode?: string };
      return rest;
    };
    expect(strip(unknown.body.data)).toEqual(strip(known.body.data));
  });

  it("gives the same answer for every kind of failure", async () => {
    await issueCode();

    const wrongCode = await request(app)
      .post("/api/v1/auth/otp/verify")
      .send({ phone: PHONE, code: "000000" });

    const noAccount = await request(app)
      .post("/api/v1/auth/otp/verify")
      .send({ phone: "254700999888", code: "000000" });

    // "That code expired" would tell a guesser they had the right number and
    // should ask for a fresh one.
    expect(wrongCode.status).toBe(401);
    expect(noAccount.status).toBe(401);
    expect(noAccount.body.error.message).toBe(wrongCode.body.error.message);
  });

  it("does not replace a code that is still in flight", async () => {
    await issueCode();
    // A second tap on "send code" while the first text is still arriving must
    // not invalidate the code about to land.
    const second = await requestLoginOtp(PHONE);
    expect(second.reason).toBe("TOO_SOON");
    expect(second.sent).toBe(false);
  });

  it("will not issue a code to a suspended account", async () => {
    await prisma.user.update({ where: { id: userId }, data: { status: "SUSPENDED" } });
    const result = await requestLoginOtp(PHONE);
    expect(result.reason).toBe("NO_ACCOUNT");
    await prisma.user.update({ where: { id: userId }, data: { status: "ACTIVE" } });
  });

  it("does not hydrate the user table for a junk number", async () => {
    // `contains: ""` would match every row, password hashes included.
    const result = await verifyLoginOtp("12", "000000");
    expect(result.ok).toBe(false);
  });

  it("stores the code hashed, never in the clear", async () => {
    const code = await issueCode();
    const record = await prisma.userLoginOtp.findUniqueOrThrow({ where: { userId } });
    // The database must not be a list of live door codes.
    expect(record.codeHash).not.toBe(code);
    expect(await bcrypt.compare(code, record.codeHash)).toBe(true);
  });
});

describe("resetting a forgotten password with a code", () => {
  const RESET_PHONE = "254799001133";
  let userId: string;

  beforeAll(async () => {
    const user = await prisma.user.upsert({
      where: { email: "reset.test@intellicash.test" },
      create: {
        name: "Reset Test Group",
        email: "reset.test@intellicash.test",
        phone: RESET_PHONE,
        passwordHash: await bcrypt.hash("the-old-password", 10),
        role: "GROUP_ACCOUNT"
      },
      update: { phone: RESET_PHONE, status: "ACTIVE", passwordHash: await bcrypt.hash("the-old-password", 10) },
      select: { id: true }
    });
    userId = user.id;
  }, 120000);

  beforeEach(async () => {
    await prisma.userLoginOtp.deleteMany({ where: { userId } });
  });

  async function resetCode() {
    const response = await request(app)
      .post("/api/v1/auth/password/reset/request")
      .send({ phone: RESET_PHONE })
      .expect(200);
    return response.body.data.devCode as string;
  }

  it("sets a new password that then works, and the old one stops", async () => {
    const code = await resetCode();
    await request(app)
      .post("/api/v1/auth/password/reset")
      .send({ phone: RESET_PHONE, code, newPassword: "a-brand-new-one" })
      .expect(200);

    await request(app)
      .post("/api/v1/auth/login")
      .send({ phone: RESET_PHONE, password: "a-brand-new-one" })
      .expect(200);
    await request(app)
      .post("/api/v1/auth/login")
      .send({ phone: RESET_PHONE, password: "the-old-password" })
      .expect(401);
  });

  it("ends every other session", async () => {
    // A reset is often the answer to "somebody else is in our account".
    await prisma.session.create({
      data: { userId, tokenHash: `stolen-${Date.now()}`, expiresAt: new Date(Date.now() + 3_600_000) }
    });
    const code = await resetCode();
    await request(app)
      .post("/api/v1/auth/password/reset")
      .send({ phone: RESET_PHONE, code, newPassword: "another-new-one" })
      .expect(200);

    const survivors = await prisma.session.findMany({ where: { userId, tokenHash: { startsWith: "stolen-" } } });
    expect(survivors).toHaveLength(0);
  });

  it("refuses a wrong code and leaves the password alone", async () => {
    await resetCode();
    await request(app)
      .post("/api/v1/auth/password/reset")
      .send({ phone: RESET_PHONE, code: "000000", newPassword: "should-not-stick" })
      .expect(401);
    await request(app)
      .post("/api/v1/auth/login")
      .send({ phone: RESET_PHONE, password: "should-not-stick" })
      .expect(401);
  });

  it("will not accept a password shorter than the normal rule", async () => {
    const code = await resetCode();
    await request(app)
      .post("/api/v1/auth/password/reset")
      .send({ phone: RESET_PHONE, code, newPassword: "short" })
      .expect(400);
  });

  it("points a duplicate sign-up at the code screen instead of a dead end", async () => {
    const response = await request(app)
      .post("/api/v1/auth/register")
      .send({ accountType: "GROUP", name: "Same Group Again", phone: RESET_PHONE, password: "whatever-123" });
    expect(response.status).toBe(409);
    expect(response.body.error.code).toBe("ACCOUNT_EXISTS");
    expect(response.body.error.details).toMatchObject({ canSignInWithCode: true, canResetPassword: true });
  });
});
