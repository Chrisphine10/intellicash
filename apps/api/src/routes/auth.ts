import { Router } from "express";
import bcrypt from "bcryptjs";
import { z } from "zod";
import { languagePreferences } from "@intellicash/shared";
import { appendAuditEvent } from "../services/audit-service";
import {
  createSession,
  requireAuth,
  resolveUserFromRequest,
  serializeExpiredSessionCookie,
  serializeSessionCookie
} from "../middleware/auth";
import { permissionsForRoleFromStore } from "../services/role-permission-service";
import { ApiHttpError, ok } from "../lib/http";
import { requestLoginOtp, verifyLoginOtp } from "../services/login-otp-service";
import { looksLikePhone, normalisePhone, phoneTail, samePhone } from "../lib/phone";
import { loginRateLimit, otpRequestRateLimit, otpVerifyRateLimit, registerRateLimit } from "../middleware/rate-limit";
import { prisma } from "../lib/prisma";

const router = Router();

const otpRequestSchema = z.object({ phone: z.string().min(6).max(32) });
const passwordResetSchema = z.object({
  phone: z.string().min(6).max(32),
  code: z.string().trim().length(6),
  // The same rule as changing a password while signed in. A reset must not be
  // the easy way round the password policy.
  newPassword: z.string().min(8).max(128)
});

const otpVerifySchema = z.object({
  phone: z.string().min(6).max(32),
  code: z.string().trim().length(6)
});

const loginSchema = z.object({
  email: z.string().optional(),
  phone: z.string().optional(),
  password: z.string().min(1)
}).refine((data) => data.email || data.phone, {
  message: "Either email or phone is required."
});

/**
 * Self-service account creation from the mobile app. Everyone starts with an
 * account: a group (its record book), a member, or a village agent. Email is
 * optional for field users — a stable placeholder is derived from the phone
 * so the unique-email constraint holds.
 */
const registerSchema = z.object({
  accountType: z.enum(["GROUP", "MEMBER", "AGENT"]),
  name: z.string().trim().min(2).max(120),
  phone: z
    .string()
    .trim()
    .min(9)
    .max(24)
    // Checked on the digits alone — the value is canonicalised before it is
    // stored or compared, so punctuation is not the user's problem.
    .refine(looksLikePhone, "Enter a valid phone number."),
  email: z.string().trim().email().optional(),
  password: z.string().min(6).max(100),
  county: z.string().trim().max(60).optional()
});

const registerRoleByType = {
  GROUP: "GROUP_ACCOUNT",
  MEMBER: "MEMBER",
  AGENT: "VILLAGE_AGENT"
} as const;

function placeholderEmail(phone: string) {
  // Built from the canonical form, so the same line always yields the same
  // address however the person typed it.
  return `${normalisePhone(phone)}@accounts.intellicash.app`;
}

const profileUpdateSchema = z
  .object({
    name: z.string().min(2).max(120).optional(),
    avatarUrl: z.string().url().nullable().optional(),
    languagePreference: z.enum(languagePreferences).optional()
  })
  .refine((body) => body.name !== undefined || body.avatarUrl !== undefined || body.languagePreference !== undefined, {
    message: "No profile fields provided."
  });

const passwordUpdateSchema = z.object({
  currentPassword: z.string().min(1),
  newPassword: z.string().min(8).max(128)
});

async function serializeUser(userId: string) {
  const user = await prisma.user.findUniqueOrThrow({
    where: { id: userId },
    include: {
      partner: { select: { id: true, name: true } },
      group: { select: { id: true, name: true, code: true } },
      member: { select: { id: true, fullName: true, phone: true } }
    }
  });
  const permissions = await permissionsForRoleFromStore(user.role);

  return {
    id: user.id,
    name: user.name,
    email: user.email,
    phone: user.phone,
    role: user.role,
    status: user.status,
    permissions,
    avatarUrl: user.avatarUrl,
    languagePreference: user.languagePreference,
    partnerId: user.partnerId,
    groupId: user.groupId,
    memberId: user.memberId,
    partner: user.partner,
    group: user.group,
    member: user.member,
    createdAt: user.createdAt
  };
}

router.post("/login", loginRateLimit, async (req, res, next) => {
  try {
    const body = loginSchema.parse(req.body);
    const user = body.email
      ? await prisma.user.findUnique({ where: { email: body.email } })
      : body.phone && phoneTail(body.phone).length >= 9
        ? // Someone who signed up as 0712… must still get in typing +254712….
          // Guarded on a full-length tail: `contains: ""` would otherwise
          // hydrate every user row, password hashes included, for anyone who
          // posts a junk number.
          await prisma.user
            .findMany({
              where: { phone: { contains: phoneTail(body.phone) } }
            })
            .then((candidates) =>
              candidates.find((candidate) => samePhone(candidate.phone, body.phone)) ?? null
            )
        : null;

    if (!user || user.status !== "ACTIVE") {
      throw new ApiHttpError(401, "INVALID_CREDENTIALS", "Invalid credentials.");
    }

    const valid = await bcrypt.compare(body.password, user.passwordHash);
    if (!valid) {
      throw new ApiHttpError(401, "INVALID_CREDENTIALS", "Invalid credentials.");
    }

    const session = await createSession(user.id);
    const permissions = await permissionsForRoleFromStore(user.role);
    res.setHeader("Set-Cookie", serializeSessionCookie(session));

    await appendAuditEvent({
      actorUserId: user.id,
      entityType: "USER",
      entityId: user.id,
      type: "AUTH_LOGIN",
      payload: { email: user.email, phone: user.phone, role: user.role }
    });

    ok(res, {
      id: user.id,
      name: user.name,
      email: user.email,
      phone: user.phone,
      role: user.role,
      permissions,
      avatarUrl: user.avatarUrl,
      languagePreference: user.languagePreference,
      partnerId: user.partnerId,
      groupId: user.groupId,
      memberId: user.memberId,
      villageAgentId: user.villageAgentId
    });
  } catch (error) {
    next(error);
  }
});

router.post("/register", registerRateLimit, async (req, res, next) => {
  try {
    const body = registerSchema.parse(req.body);
    const email = body.email ?? placeholderEmail(body.phone);
    const role = registerRoleByType[body.accountType];
    const phone = normalisePhone(body.phone);

    // Existing rows hold whichever format was entered at the time, so filter
    // on the nine digits that never change, then compare canonical forms. A
    // plain `phone: body.phone` match lets one person register twice by
    // writing their number a different way.
    const sameLine = await prisma.user.findMany({
      where: { phone: { contains: phoneTail(body.phone) } },
      select: { id: true, phone: true }
    });
    const existing =
      sameLine.find((candidate) => samePhone(candidate.phone, body.phone)) ??
      (await prisma.user.findFirst({ where: { email }, select: { id: true, phone: true } }));
    if (existing) {
      throw new ApiHttpError(
        409,
        "ACCOUNT_EXISTS",
        "This number already has an account. Sign in with a code sent to it, or reset the password.",
        // The field team was stuck here: the account existed and the only way
        // forward offered was a password nobody had. The client uses this to
        // go straight to the code screen with the number already filled in.
        { canSignInWithCode: true, canResetPassword: true }
      );
    }

    const passwordHash = await bcrypt.hash(body.password, 12);

    const user = await prisma.$transaction(async (tx) => {
      // A village agent login must be bound to an agent profile — create one
      // alongside the account (a programme can adopt it later).
      const villageAgent =
        body.accountType === "AGENT"
          ? await tx.villageAgent.create({
              data: {
                name: body.name,
                phone,
                email: body.email,
                county: body.county,
                sourceSystem: "MOBILE_SELF_SIGNUP"
              },
              select: { id: true }
            })
          : null;

      return tx.user.create({
        data: {
          name: body.name,
          email,
          phone,
          passwordHash,
          role,
          villageAgentId: villageAgent?.id
        }
      });
    });

    const session = await createSession(user.id);
    const permissions = await permissionsForRoleFromStore(user.role);
    res.setHeader("Set-Cookie", serializeSessionCookie(session));

    await appendAuditEvent({
      actorUserId: user.id,
      entityType: "USER",
      entityId: user.id,
      type: "AUTH_REGISTERED",
      payload: { accountType: body.accountType, email: user.email, phone: user.phone, role: user.role }
    });

    ok(res.status(201), {
      id: user.id,
      name: user.name,
      email: user.email,
      phone: user.phone,
      role: user.role,
      permissions,
      avatarUrl: user.avatarUrl,
      languagePreference: user.languagePreference,
      partnerId: user.partnerId,
      groupId: user.groupId,
      memberId: user.memberId,
      villageAgentId: user.villageAgentId
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Ask for a sign-in code.
 *
 * **Always answers 200**, whatever happened. Whether a number has an account is
 * not something a stranger gets to find out by posting numbers one at a time —
 * and here the answer would be "this savings group banks with you", which is
 * worth more to somebody choosing a target than an ordinary account check.
 *
 * So the body never varies. What did happen is in the audit trail.
 */
router.post("/otp/request", otpRequestRateLimit, async (req, res, next) => {
  try {
    const body = otpRequestSchema.parse(req.body);
    const result = await requestLoginOtp(body.phone);

    await appendAuditEvent({
      entityType: "USER",
      entityId: result.maskedPhone ?? "unknown",
      type: "AUTH_OTP_REQUESTED",
      // Masked, never the number itself: this table is read by more people
      // than the user table is.
      payload: { outcome: result.reason, phone: result.maskedPhone ?? null }
    });

    ok(res, {
      // Deliberately incurious. The client shows "if that number has an
      // account, a code is on its way" and moves to the code screen either way.
      requested: true,
      expiresInMinutes: 10,
      // Only ever present with SMS network calls switched off, which is when
      // there is no handset to read it from anyway. It is what makes an
      // end-to-end test of this flow possible at all.
      ...(result.devCode ? { devCode: result.devCode } : {})
    });
  } catch (error) {
    next(error);
  }
});

/** Exchange the code for a session. */
router.post("/otp/verify", otpVerifyRateLimit, async (req, res, next) => {
  try {
    const body = otpVerifySchema.parse(req.body);
    const result = await verifyLoginOtp(body.phone, body.code);

    if (!result.ok) {
      // One message for every failure. Saying "that code expired" rather than
      // "wrong code" tells somebody guessing that they had the right number and
      // should ask for a fresh one.
      throw new ApiHttpError(401, "INVALID_CODE", "That code is wrong or has expired.");
    }

    const user = await prisma.user.findUniqueOrThrow({ where: { id: result.userId } });
    const session = await createSession(user.id);
    const permissions = await permissionsForRoleFromStore(user.role);
    res.setHeader("Set-Cookie", serializeSessionCookie(session));

    await appendAuditEvent({
      actorUserId: user.id,
      entityType: "USER",
      entityId: user.id,
      type: "AUTH_LOGIN",
      payload: { phone: user.phone, role: user.role, method: "OTP" }
    });

    ok(res, {
      id: user.id,
      name: user.name,
      email: user.email,
      phone: user.phone,
      role: user.role,
      permissions,
      avatarUrl: user.avatarUrl,
      languagePreference: user.languagePreference,
      partnerId: user.partnerId,
      groupId: user.groupId,
      memberId: user.memberId,
      villageAgentId: user.villageAgentId
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Forgotten password, step one: text a code to the account's own phone.
 *
 * Same answer whatever happened, for the same reason as the sign-in code — this
 * must not become a way to learn which numbers hold accounts.
 */
router.post("/password/reset/request", otpRequestRateLimit, async (req, res, next) => {
  try {
    const body = otpRequestSchema.parse(req.body);
    const result = await requestLoginOtp(body.phone, { purpose: "PASSWORD_RESET" });

    await appendAuditEvent({
      entityType: "USER",
      entityId: result.maskedPhone ?? "unknown",
      type: "AUTH_OTP_REQUESTED",
      payload: { purpose: "PASSWORD_RESET", outcome: result.reason, phone: result.maskedPhone ?? null }
    });

    ok(res, {
      requested: true,
      expiresInMinutes: 10,
      ...(result.devCode ? { devCode: result.devCode } : {})
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Forgotten password, step two: the code proves the phone, and sets a new
 * password.
 *
 * Every existing session is ended. A reset is often the response to "somebody
 * else has been using our account", and leaving their session alive would make
 * the new password decorative.
 *
 * Signs the person in afterwards. Making them type the password they chose ten
 * seconds ago is friction, not security — the code has already proved the
 * phone.
 */
router.post("/password/reset", otpVerifyRateLimit, async (req, res, next) => {
  try {
    const body = passwordResetSchema.parse(req.body);
    const result = await verifyLoginOtp(body.phone, body.code);
    if (!result.ok) {
      throw new ApiHttpError(401, "INVALID_CODE", "That code is wrong or has expired.");
    }

    const passwordHash = await bcrypt.hash(body.newPassword, 12);
    const user = await prisma.$transaction(async (tx) => {
      await tx.session.deleteMany({ where: { userId: result.userId } });
      return tx.user.update({ where: { id: result.userId }, data: { passwordHash } });
    });

    await appendAuditEvent({
      actorUserId: user.id,
      entityType: "USER",
      entityId: user.id,
      type: "USER_PASSWORD_UPDATED",
      payload: { method: "OTP_RESET", role: user.role }
    });

    const session = await createSession(user.id);
    const permissions = await permissionsForRoleFromStore(user.role);
    res.setHeader("Set-Cookie", serializeSessionCookie(session));

    ok(res, {
      id: user.id,
      name: user.name,
      email: user.email,
      phone: user.phone,
      role: user.role,
      permissions,
      avatarUrl: user.avatarUrl,
      languagePreference: user.languagePreference,
      partnerId: user.partnerId,
      groupId: user.groupId,
      memberId: user.memberId,
      villageAgentId: user.villageAgentId
    });
  } catch (error) {
    next(error);
  }
});

router.post("/logout", async (req, res, next) => {
  try {
    // Logout is idempotent and best-effort: resolve the current session if one
    // exists, but always clear the cookie and return success even when the
    // caller is already unauthenticated (expired/cleared session). This avoids
    // a spurious 401 on a fire-and-forget client logout.
    const user = await resolveUserFromRequest(req);

    if (req.sessionTokenHash) {
      await prisma.session.deleteMany({ where: { tokenHash: req.sessionTokenHash } });
    }

    if (user) {
      await appendAuditEvent({
        actorUserId: user.id,
        entityType: "USER",
        entityId: user.id,
        type: "AUTH_LOGOUT",
        payload: { email: user.email }
      });
    }

    res.setHeader("Set-Cookie", serializeExpiredSessionCookie());
    ok(res, { loggedOut: true });
  } catch (error) {
    next(error);
  }
});

router.get("/me", requireAuth(), async (req, res) => {
  ok(res, req.user);
});

router.patch("/me", requireAuth(), async (req, res, next) => {
  try {
    const body = profileUpdateSchema.parse(req.body);

    const user = await prisma.user.update({
      where: { id: req.user!.id },
      data: {
        name: body.name,
        avatarUrl: body.avatarUrl === undefined ? undefined : body.avatarUrl || null,
        languagePreference: body.languagePreference
      },
      select: { id: true, name: true, email: true, avatarUrl: true, languagePreference: true, role: true }
    });

    await appendAuditEvent({
      actorUserId: req.user!.id,
      entityType: "USER",
      entityId: user.id,
      type: "USER_PROFILE_UPDATED",
      payload: {
        email: user.email,
        role: user.role,
        name: user.name,
        avatarUrlSet: Boolean(user.avatarUrl),
        languagePreference: user.languagePreference
      }
    });

    ok(res, await serializeUser(user.id));
  } catch (error) {
    next(error);
  }
});

router.post("/me/password", requireAuth(), async (req, res, next) => {
  try {
    const body = passwordUpdateSchema.parse(req.body);
    const user = await prisma.user.findUnique({
      where: { id: req.user!.id },
      select: { id: true, email: true, passwordHash: true, role: true }
    });

    if (!user) {
      throw new ApiHttpError(404, "USER_NOT_FOUND", "Signed-in account could not be found.");
    }

    const valid = await bcrypt.compare(body.currentPassword, user.passwordHash);
    if (!valid) {
      throw new ApiHttpError(400, "CURRENT_PASSWORD_INVALID", "Current password is incorrect.");
    }

    await prisma.user.update({
      where: { id: user.id },
      data: { passwordHash: await bcrypt.hash(body.newPassword, 12) },
      select: { id: true }
    });

    await appendAuditEvent({
      actorUserId: user.id,
      entityType: "USER",
      entityId: user.id,
      type: "USER_PASSWORD_UPDATED",
      payload: { email: user.email, role: user.role }
    });

    ok(res, { updated: true });
  } catch (error) {
    next(error);
  }
});

export { router as authRouter };
