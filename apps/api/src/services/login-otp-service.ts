import bcrypt from "bcryptjs";
import { randomInt } from "node:crypto";

import { prisma } from "../lib/prisma";
import { normalisePhone, phoneTail, samePhone } from "../lib/phone";
import { dispatchSms } from "./outbound-sms-service";
import { maskPhone } from "./member-pin-service";

/**
 * Signing in with a phone and a texted code, no password.
 *
 * The people this is for run a savings group from one shared handset. A
 * password they must remember is the thing that actually keeps them out — and
 * the alternative in practice is not a stronger password, it is the password
 * written on the inside cover of the passbook.
 *
 * ## What the code is worth, and what protects it
 *
 * Six digits is a million guesses, which is nothing to a machine. Everything
 * that makes this safe is therefore around the code rather than in it:
 *
 * - **Five wrong guesses burn it.** Not the account — the CODE. Locking the
 *   account would hand anyone who knows a group's number a way to keep that
 *   group out of their own books.
 * - **Ten minutes.** Long enough for a text to arrive over a slow network,
 *   short enough that a phone left on a table is not a standing key.
 * - **One live code per user**, replaced on each request, so an older SMS is
 *   already dead. These handsets keep every message they have ever received.
 * - **Hashed at rest**, so the database is not a list of live door codes.
 * - **Single use** — consumed the moment it works.
 */

const CODE_LENGTH = 6;
const TTL_MINUTES = 10;

/** Wrong guesses before the code is destroyed. */
const MAX_ATTEMPTS = 5;

/** How soon a new code may be asked for. Below this, the last one still stands. */
const RESEND_SECONDS = 60;

export interface RequestLoginOtpResult {
  /**
   * Whether a message was actually sent.
   *
   * The ROUTE must not pass this to the caller. It is here for the audit trail
   * and for tests; telling the client would turn this endpoint into a way to
   * ask "does this group have an account?" one number at a time.
   */
  sent: boolean;
  reason: "SENT" | "NO_ACCOUNT" | "NO_PHONE" | "TOO_SOON" | "SMS_FAILED";
  /** Masked, for the audit trail. Never the full number. */
  maskedPhone?: string;
  /**
   * Test-only, and gated on `NODE_ENV === "test"` rather than on whether SMS
   * happens to be switched on.
   *
   * The first cut keyed this on `ENABLE_SMS_NETWORK_CALLS`, which is true in
   * development and could be false in a real deployment during an outage — so
   * a live server with a broken SMS provider would have started returning
   * sign-in codes in the HTTP response. This condition production cannot meet.
   */
  devCode?: string;
}

function generateCode() {
  return randomInt(0, 10 ** CODE_LENGTH)
    .toString()
    .padStart(CODE_LENGTH, "0");
}

/**
 * Finds an active user by phone, tolerating how the number was typed.
 *
 * Matched on the canonical form, never the raw string: somebody who registered
 * as 0712… has to get in typing +254712…, and the two are the same person.
 */
async function findUserByPhone(phone: string) {
  const tail = phoneTail(phone);
  // A short tail would make `contains` match half the table. Refuse rather
  // than hydrate every row for anyone who posts junk.
  if (tail.length < 9) return null;

  const candidates = await prisma.user.findMany({
    where: { phone: { contains: tail } },
    select: { id: true, name: true, phone: true, status: true }
  });

  const user = candidates.find((candidate) => samePhone(candidate.phone, phone));
  return user && user.status === "ACTIVE" ? user : null;
}

/**
 * What the code is for. It is the same code store either way — one live code
 * per person — but the text must say which, or somebody who asked to reset a
 * password reads "sign-in code" and assumes the wrong screen.
 */
export type LoginOtpPurpose = "SIGN_IN" | "PASSWORD_RESET";

export async function requestLoginOtp(
  phone: string,
  options: { requestedByUserId?: string; purpose?: LoginOtpPurpose } = {}
): Promise<RequestLoginOtpResult> {
  const user = await findUserByPhone(phone);

  // No account on that number. The caller is told the same thing either way.
  if (!user || !user.phone) {
    return { sent: false, reason: user ? "NO_PHONE" : "NO_ACCOUNT" };
  }

  const existing = await prisma.userLoginOtp.findUnique({
    where: { userId: user.id },
    select: { lastSentAt: true }
  });

  // A second tap on "send code" while the first text is still in flight must
  // not invalidate the code that is about to arrive.
  if (existing && Date.now() - existing.lastSentAt.getTime() < RESEND_SECONDS * 1000) {
    return { sent: false, reason: "TOO_SOON", maskedPhone: maskPhone(user.phone) };
  }

  const code = generateCode();
  const now = new Date();

  await prisma.userLoginOtp.upsert({
    where: { userId: user.id },
    create: {
      userId: user.id,
      codeHash: await bcrypt.hash(code, 10),
      expiresAt: new Date(now.getTime() + TTL_MINUTES * 60_000),
      lastSentAt: now
    },
    update: {
      codeHash: await bcrypt.hash(code, 10),
      expiresAt: new Date(now.getTime() + TTL_MINUTES * 60_000),
      lastSentAt: now,
      // A fresh code starts with a clean slate, or a previous lockout would be
      // inherited by a code the person has only just received.
      attempts: 0
    }
  });

  const result = await dispatchSms({
    kind: "LOGIN_OTP",
    label: options.purpose === "PASSWORD_RESET" ? "Password reset code" : "Sign-in code",
    requestedByUserId: options.requestedByUserId,
    recipients: [
      {
        // The console's SMS log shows this against a name; without one the row
        // reads as though it went nowhere.
        memberName: user.name,
        phone: normalisePhone(user.phone),
        // No group name and no link. A text read over somebody's shoulder
        // should not also say which book it opens.
        message:
          options.purpose === "PASSWORD_RESET"
            ? `${code} is your Intelli-Cash code to reset your password. It expires in ${TTL_MINUTES} minutes. If you did not ask for this, ignore it.`
            : `${code} is your Intelli-Cash sign-in code. It expires in ${TTL_MINUTES} minutes. Do not share it with anyone.`
      }
    ]
  });

  return {
    sent: result.sent > 0,
    reason: result.sent > 0 ? "SENT" : "SMS_FAILED",
    maskedPhone: maskPhone(user.phone),
    // Never in production, whatever the SMS provider is doing.
    devCode: process.env.NODE_ENV === "test" ? code : undefined
  };
}

export type VerifyLoginOtpResult =
  | { ok: true; userId: string }
  | { ok: false; reason: "NO_CODE" | "EXPIRED" | "TOO_MANY_ATTEMPTS" | "WRONG_CODE" };

export async function verifyLoginOtp(phone: string, code: string): Promise<VerifyLoginOtpResult> {
  const user = await findUserByPhone(phone);
  if (!user) return { ok: false, reason: "NO_CODE" };

  const record = await prisma.userLoginOtp.findUnique({ where: { userId: user.id } });
  if (!record) return { ok: false, reason: "NO_CODE" };

  if (record.expiresAt.getTime() < Date.now()) {
    await prisma.userLoginOtp.delete({ where: { userId: user.id } });
    return { ok: false, reason: "EXPIRED" };
  }

  if (record.attempts >= MAX_ATTEMPTS) {
    // The CODE is destroyed, not the account. Locking the account would let
    // anyone who knows a group's number keep that group out of its own books.
    await prisma.userLoginOtp.delete({ where: { userId: user.id } });
    return { ok: false, reason: "TOO_MANY_ATTEMPTS" };
  }

  const valid = await bcrypt.compare(code, record.codeHash);
  if (!valid) {
    const attempts = record.attempts + 1;
    if (attempts >= MAX_ATTEMPTS) {
      await prisma.userLoginOtp.delete({ where: { userId: user.id } });
    } else {
      await prisma.userLoginOtp.update({ where: { userId: user.id }, data: { attempts } });
    }
    return { ok: false, reason: "WRONG_CODE" };
  }

  // Single use. Consumed before the session exists, so a code that raced two
  // requests cannot mint two sessions.
  await prisma.userLoginOtp.delete({ where: { userId: user.id } });
  return { ok: true, userId: user.id };
}
