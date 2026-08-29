import { randomBytes } from "node:crypto";
import { Router } from "express";
import bcrypt from "bcryptjs";
import { z } from "zod";
import { ApiHttpError, ok } from "../lib/http";
import { normalisePhone, looksLikePhone, phoneTail, samePhone } from "../lib/phone";
import { prisma } from "../lib/prisma";
import { createSession, requireAuth, serializeSessionCookie } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { joinRequestRateLimit, registerRateLimit } from "../middleware/rate-limit";
import { appendAuditEvent } from "../services/audit-service";
import { permissionsForRoleFromStore } from "../services/role-permission-service";
import { scopeGroupWhere } from "../services/account-scope";

/**
 * A group's shareable invite link, and the public page behind it.
 *
 * The problem it solves: joining used to require already having an account AND
 * knowing the group code, typed by hand. A secretary at a meeting could not
 * simply hold up a phone. Now she shares a link or a printed QR, and the person
 * on the other end fills one form.
 *
 * What the link deliberately does NOT do is let anybody in. It creates their
 * account and files a PENDING request; an official still approves it, on the
 * screen that names whose savings an approval would hand over. A public link
 * that granted membership would hand the group's whole book to anyone who
 * forwarded it on WhatsApp.
 */

export const publicJoinRouter = Router();

/**
 * 128 bits of URL-safe randomness.
 *
 * Not the group code. `IWL-KBU-0001` can be counted upwards, so a link built on
 * one would let anybody enumerate every group on the platform, learn its name
 * and county, and file requests at all of them.
 */
function newJoinToken() {
  return randomBytes(16).toString("base64url");
}

function joinUrlFor(token: string) {
  const base = (process.env.API_PUBLIC_URL || "http://localhost:4000").replace(/\/+$/, "");
  return `${base}/join/${token}`;
}

async function loadGroupInScope(user: AuthenticatedUser | undefined, groupId: string) {
  const group = await prisma.group.findFirst({
    where: { AND: [{ id: groupId }, scopeGroupWhere(user)] },
    select: { id: true, name: true, joinToken: true, joinTokenIssuedAt: true }
  });
  if (!group) {
    throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
  }
  return group;
}

/** A platform admin, or the group's own account. Same rule as group policy. */
function assertMayShare(user: AuthenticatedUser | undefined, groupId: string) {
  if (!user) throw new ApiHttpError(401, "UNAUTHENTICATED", "Authentication is required.");
  if (user.permissions.includes("groups:write")) return;
  if (user.role === "GROUP_ACCOUNT" && user.groupId === groupId) return;

  throw new ApiHttpError(
    403,
    "FORBIDDEN",
    "Only a platform admin or the group's own account may share its invite link."
  );
}

/**
 * The link to share, minting one on first use.
 *
 * Issued lazily rather than at group creation: a group that never invites
 * anybody should have no public surface at all, and 40 imported groups should
 * not each acquire a live public URL the moment they land.
 */
publicJoinRouter.get(
  "/groups/:groupId/join-link",
  requireAuth("groups:read"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      assertMayShare(req.user, group.id);

      let token = group.joinToken;
      if (!token) {
        token = newJoinToken();
        await prisma.group.update({
          where: { id: group.id },
          data: { joinToken: token, joinTokenIssuedAt: new Date() }
        });
      }

      ok(res, {
        group: { id: group.id, name: group.name },
        token,
        url: joinUrlFor(token),
        issuedAt: (group.joinTokenIssuedAt ?? new Date()).toISOString()
      });
    } catch (error) {
      next(error);
    }
  }
);

/** Revoke a link that has ended up somewhere it should not have. */
publicJoinRouter.post(
  "/groups/:groupId/join-link/rotate",
  requireAuth("groups:read"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      assertMayShare(req.user, group.id);

      const token = newJoinToken();
      await prisma.group.update({
        where: { id: group.id },
        data: { joinToken: token, joinTokenIssuedAt: new Date() }
      });

      await appendAuditEvent({
        actorUserId: req.user?.id,
        entityType: "GROUP",
        entityId: group.id,
        type: "GROUP_JOIN_LINK_ROTATED",
        payload: { groupId: group.id }
      });

      ok(res, {
        group: { id: group.id, name: group.name },
        token,
        url: joinUrlFor(token),
        issuedAt: new Date().toISOString(),
        message: "The old link and QR code no longer work. Share the new one."
      });
    } catch (error) {
      next(error);
    }
  }
);

async function groupForToken(token: string) {
  const group = await prisma.group.findFirst({
    where: { joinToken: token },
    // Only what belongs on a poster. No member count, no balances, no roster:
    // an invite link is public, and everything returned here is public too.
    select: { id: true, name: true, county: true, subCounty: true }
  });
  if (!group) {
    throw new ApiHttpError(
      404,
      "INVITE_NOT_FOUND",
      "This invite link is not valid any more. Ask the group for a new one."
    );
  }
  return group;
}

/** What the landing page shows before anybody types anything. */
publicJoinRouter.get("/public/join/:token", joinRequestRateLimit, async (req, res, next) => {
  try {
    const group = await groupForToken(z.string().min(8).parse(req.params.token));
    ok(res, { group });
  } catch (error) {
    next(error);
  }
});

const publicJoinSchema = z.object({
  name: z.string().trim().min(2).max(120),
  phone: z
    .string()
    .trim()
    .min(9)
    .max(24)
    .refine(looksLikePhone, "Enter a valid phone number."),
  password: z.string().min(6).max(100)
});

/**
 * Sign up and ask to join, in one transaction.
 *
 * One step on purpose. Registering and then filing a request are two calls, and
 * a person whose signal dropped between them would end up with an account, no
 * request, and no idea which half had worked.
 */
publicJoinRouter.post("/public/join/:token", registerRateLimit, async (req, res, next) => {
  try {
    const token = z.string().min(8).parse(req.params.token);
    const body = publicJoinSchema.parse(req.body);
    const group = await groupForToken(token);
    const phone = normalisePhone(body.phone);

    // Existing rows hold whichever format was typed at the time, so filter on
    // the nine digits that never change and then compare canonical forms.
    // Matching on the raw string lets one person register twice by writing
    // their number a different way.
    const sameLine = await prisma.user.findMany({
      where: { phone: { contains: phoneTail(body.phone) } },
      select: { id: true, phone: true }
    });
    if (sameLine.some((candidate) => samePhone(candidate.phone, body.phone))) {
      throw new ApiHttpError(
        409,
        "ACCOUNT_EXISTS",
        "You already have an Intelli-Cash account on this number. Sign in, then ask to join."
      );
    }

    const email = `${phone}@accounts.intellicash.app`;
    const passwordHash = await bcrypt.hash(body.password, 12);

    const { user } = await prisma.$transaction(async (tx) => {
      const created = await tx.user.create({
        data: { name: body.name, email, phone, passwordHash, role: "MEMBER" }
      });

      await tx.groupJoinRequest.create({
        data: {
          groupId: group.id,
          userId: created.id,
          requestedName: body.name,
          phone,
          status: "PENDING"
        }
      });

      return { user: created };
    });

    await appendAuditEvent({
      actorUserId: user.id,
      entityType: "GroupJoinRequest",
      entityId: user.id,
      type: "GROUP_JOIN_REQUESTED_VIA_LINK",
      payload: { groupId: group.id, requestedName: body.name }
    });

    const session = await createSession(user.id);
    res.setHeader("Set-Cookie", serializeSessionCookie(session));
    const permissions = await permissionsForRoleFromStore(user.role);

    ok(res.status(201), {
      group: { id: group.id, name: group.name },
      // Said plainly, because the single commonest misreading of a join link is
      // "I am in now".
      status: "PENDING",
      message:
        `Your request has been sent to ${group.name}. ` +
        "An official of the group will decide, and you will be told either way.",
      user: {
        id: user.id,
        name: user.name,
        email: user.email,
        phone: user.phone,
        role: user.role,
        permissions
      }
    });
  } catch (error) {
    next(error);
  }
});
