import { Router } from "express";
import { z } from "zod";
import { appendAuditEvent } from "../services/audit-service";
import { requireAuth } from "../middleware/auth";
import { scopeGroupWhere } from "../services/account-scope";
import {
  listMemberships,
  setActiveMembership,
  linkMembership,
  MemberAlreadyLinkedError
} from "../services/membership-service";
import { createNotification, createNotifications } from "../services/notification-service";
import { ApiHttpError, ok } from "../lib/http";
import { joinRequestRateLimit } from "../middleware/rate-limit";
import { prisma } from "../lib/prisma";

/**
 * Joining a group from a member account.
 *
 * A group's code is printed on its constitution, its passbooks and its
 * meeting register, so it is not a secret and cannot be the gate on its own.
 * Requesting to join therefore grants nothing at all: the row sits PENDING,
 * the account stays unlinked, and none of the group's money is visible until
 * an official of that group approves it.
 *
 * The member side is gated on `members:read` rather than a new permission —
 * asking is not a write against the group, and existing role rows in the
 * database never pick up newly-added permissions.
 */
const router = Router();

const requestSchema = z.object({
  groupCode: z.string().min(3).max(64),
  /// Optional: what the member wants the group to call them in the roster.
  name: z.string().min(2).max(120).optional()
});

const decisionSchema = z.object({
  decision: z.enum(["APPROVE", "REJECT"]),
  notes: z.string().max(500).optional(),
  /**
   * The existing member the approver was shown they would be attaching this
   * login to. Required whenever the phone matches someone on the roster, so
   * handing over a passbook is always a deliberate act.
   */
  confirmMemberId: z.string().min(1).optional()
});

const activeSchema = z.object({ groupId: z.string().min(1) });

/**
 * How long a refused applicant waits before asking that group again.
 *
 * Short on purpose. The point is to stop a refusal being answered with an
 * instant re-ask that re-notifies the officials who just said no — not to
 * lock anyone out. Someone told "come to a meeting first" who then does so
 * that afternoon must be able to ask again the same day.
 */
const REASK_COOLDOWN_MS = 60 * 60 * 1000;

/**
 * Turns a claimed-member collision into something an official can act on.
 * It can surface from a decision, and also from the lazy backfill when an
 * account's `User.memberId` points at a roster entry someone else holds.
 */
function asApiError(error: unknown) {
  if (error instanceof MemberAlreadyLinkedError) {
    return new ApiHttpError(
      409,
      "MEMBER_ALREADY_LINKED",
      "That member is already signed in on another account. Ask them to use " +
        "that one, or have an administrator unlink it first."
    );
  }
  return error;
}

/**
 * Deciding who joins a group is the group's own business.
 *
 * `members:write` alone is too coarse a gate: VILLAGE_AGENT carries it and
 * `groupScopeForUser` gives an agent every group on their caseload, so
 * permission-only checks would let one agent account attach logins to members'
 * savings across every group they support, with no official involved.
 */
function assertMayDecide(user: { role?: string } | undefined) {
  if (user?.role === "GROUP_ACCOUNT" || user?.role === "IWL_ADMIN") return;
  throw new ApiHttpError(
    403,
    "NOT_A_GROUP_OFFICIAL",
    "Only an official of this group can answer requests to join."
  );
}

function routeParam(value: string | string[] | undefined, name: string) {
  if (typeof value === "string" && value.trim()) return value;
  throw new ApiHttpError(400, "INVALID_ROUTE_PARAM", `Missing route parameter: ${name}.`);
}

/**
 * Only accounts allowed to answer a request may see one is waiting.
 */
async function notifyOfficials(groupId: string, requestedName: string, groupName: string) {
  const officials = await prisma.user.findMany({
    where: { groupId, role: "GROUP_ACCOUNT", status: "ACTIVE" },
    select: { id: true }
  });
  await createNotifications(
    officials.map((official) => ({
      userId: official.id,
      title: "Someone wants to join",
      body: `${requestedName} has asked to join ${groupName}.`,
      type: "GROUP_JOIN_REQUESTED",
      href: `/dashboard/groups/${groupId}/join-requests`
    }))
  );
}

/** Kenyan numbers are written 0712…, +254712… and 254712… interchangeably. */
function normalisePhone(phone: string) {
  const digits = phone.replace(/\D/g, "");
  if (digits.startsWith("254")) return digits;
  if (digits.startsWith("0")) return `254${digits.slice(1)}`;
  if (digits.length === 9) return `254${digits}`;
  return digits;
}

// --- Member side -----------------------------------------------------------

/** Every group this account belongs to, and which one is in view. */
router.get("/members/me/memberships", requireAuth("members:read"), async (req, res, next) => {
  try {
    const user = req.user;
    if (!user?.id) throw new ApiHttpError(401, "UNAUTHENTICATED", "Sign in first.");
    ok(res, await listMemberships(user.id));
  } catch (error) {
    next(asApiError(error));
  }
});

/** Switches which membership the rest of the API scopes to. */
router.post("/members/me/active-membership", requireAuth("members:read"), async (req, res, next) => {
  try {
    const user = req.user;
    if (!user?.id) throw new ApiHttpError(401, "UNAUTHENTICATED", "Sign in first.");
    const body = activeSchema.parse(req.body);
    const membership = await setActiveMembership(user.id, body.groupId);
    // Not one of theirs — refuse rather than reveal whether the group exists.
    if (!membership) {
      throw new ApiHttpError(404, "NOT_A_MEMBERSHIP", "You do not belong to that group.");
    }
    ok(res, membership);
  } catch (error) {
    next(asApiError(error));
  }
});

/** Asks a group to be added to its roster. Grants nothing until approved. */
router.post(
  "/members/me/join-requests",
  requireAuth("members:read"),
  joinRequestRateLimit,
  async (req, res, next) => {
  try {
    const user = req.user;
    if (!user?.id) throw new ApiHttpError(401, "UNAUTHENTICATED", "Sign in first.");
    const body = requestSchema.parse(req.body);

    const account = await prisma.user.findUnique({
      where: { id: user.id },
      select: { name: true, phone: true }
    });
    if (!account) throw new ApiHttpError(401, "UNAUTHENTICATED", "Sign in first.");

    const phone = normalisePhone(account.phone ?? "");
    // A roster entry cannot exist without a phone, and matching to existing
    // savings depends on it. Fail here, where we can say what to fix.
    if (!phone) {
      throw new ApiHttpError(
        400,
        "PHONE_REQUIRED",
        "Add a phone number to your account before joining a group."
      );
    }

    const code = body.groupCode.trim().toUpperCase();
    const group = await prisma.group.findFirst({
      where: { code },
      select: { id: true, name: true }
    });
    if (!group) {
      throw new ApiHttpError(
        404,
        "GROUP_NOT_FOUND",
        "No group has that code. Check it with your group's secretary."
      );
    }

    const already = await prisma.userMembership.findUnique({
      where: { userId_groupId: { userId: user.id, groupId: group.id } },
      select: { id: true }
    });
    if (already) {
      throw new ApiHttpError(409, "ALREADY_A_MEMBER", `You are already in ${group.name}.`);
    }

    const existing = await prisma.groupJoinRequest.findUnique({
      where: { groupId_userId: { groupId: group.id, userId: user.id } },
      select: { id: true, status: true, decidedAt: true }
    });
    if (existing?.status === "PENDING") {
      throw new ApiHttpError(
        409,
        "REQUEST_PENDING",
        `${group.name} has not answered your last request yet.`
      );
    }

    // A refusal is not permanent — circumstances change and the group decides
    // again — but asking again the same minute just re-notifies the officials
    // who have already said no. Make them wait a day.
    if (existing?.status === "REJECTED" && existing.decidedAt) {
      const waitedMs = Date.now() - existing.decidedAt.getTime();
      if (waitedMs < REASK_COOLDOWN_MS) {
        const minutes = Math.max(1, Math.ceil((REASK_COOLDOWN_MS - waitedMs) / 60_000));
        throw new ApiHttpError(
          429,
          "REASK_TOO_SOON",
          `${group.name} declined your last request. You can ask again in ` +
            `${minutes} minute${minutes === 1 ? "" : "s"}.`
        );
      }
    }

    const data = {
      requestedName: body.name?.trim() || account.name,
      phone,
      status: "PENDING",
      // A previous refusal must not lock someone out forever — circumstances
      // change, and the group decides again.
      memberId: null,
      reviewNotes: null,
      decidedByUserId: null,
      decidedAt: null
    };

    const request = existing
      ? await prisma.groupJoinRequest.update({ where: { id: existing.id }, data })
      : await prisma.groupJoinRequest.create({
          data: { ...data, groupId: group.id, userId: user.id }
        });

    await appendAuditEvent({
      actorUserId: user.id,
      entityType: "GroupJoinRequest",
      entityId: request.id,
      type: "GROUP_JOIN_REQUESTED",
      payload: { groupId: group.id, groupName: group.name }
    });

    // Without this the request sits unseen until an official happens to look.
    await notifyOfficials(group.id, data.requestedName, group.name);

    ok(res, {
      id: request.id,
      status: request.status,
      groupId: group.id,
      groupName: group.name
    });
  } catch (error) {
    next(asApiError(error));
  }
});

// --- Group side ------------------------------------------------------------

/** Requests waiting on this group. */
router.get("/groups/:groupId/join-requests", requireAuth("members:write"), async (req, res, next) => {
  try {
    assertMayDecide(req.user);
    const groupId = routeParam(req.params.groupId, "groupId");
    const visible = await prisma.group.findFirst({
      where: scopeGroupWhere(req.user, { id: groupId }),
      select: { id: true }
    });
    if (!visible) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group not found.");

    const status =
      typeof req.query.status === "string" ? req.query.status.toUpperCase() : "PENDING";
    const requests = await prisma.groupJoinRequest.findMany({
      where: { groupId, ...(status === "ALL" ? {} : { status }) },
      orderBy: { createdAt: "desc" },
      select: {
        id: true,
        requestedName: true,
        phone: true,
        status: true,
        memberId: true,
        reviewNotes: true,
        createdAt: true,
        decidedAt: true
      }
    });

    // A requester types their own phone at sign-up and nothing verifies it, so
    // a matching number is a claim, not proof. Say plainly whose records
    // accepting would hand over — the official cannot weigh that otherwise,
    // and the name alone looks entirely legitimate.
    const roster = await prisma.member.findMany({
      where: { groupId },
      select: { id: true, phone: true, fullName: true }
    });
    ok(
      res,
      requests.map((request) => {
        const match =
          request.status === "PENDING"
            ? roster.find((m) => m.phone && normalisePhone(m.phone) === request.phone)
            : undefined;
        return {
          ...request,
          willLinkToMemberId: match?.id ?? null,
          willLinkToMemberName: match?.fullName ?? null
        };
      })
    );
  } catch (error) {
    next(asApiError(error));
  }
});

/** Approve or refuse. Approving is what actually opens the books. */
router.post(
  "/groups/:groupId/join-requests/:requestId/decision",
  requireAuth("members:write"),
  async (req, res, next) => {
    try {
      const user = req.user;
      assertMayDecide(user);
      const groupId = routeParam(req.params.groupId, "groupId");
      const requestId = routeParam(req.params.requestId, "requestId");
      const body = decisionSchema.parse(req.body);

      const visible = await prisma.group.findFirst({
        where: scopeGroupWhere(user, { id: groupId }),
        select: { id: true, name: true }
      });
      if (!visible) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group not found.");

      const request = await prisma.groupJoinRequest.findFirst({
        where: { id: requestId, groupId },
        select: { id: true, userId: true, requestedName: true, phone: true, status: true }
      });
      if (!request) throw new ApiHttpError(404, "REQUEST_NOT_FOUND", "Request not found.");
      if (request.status !== "PENDING") {
        throw new ApiHttpError(409, "ALREADY_DECIDED", "That request has already been answered.");
      }

      // One transaction, opened by claiming the request itself: two officials
      // tapping Accept at the same moment would otherwise both pass the
      // PENDING check above and each create a roster row for the same person,
      // leaving a duplicate member that inflates counts and share-out
      // arithmetic. `updateMany` with a status guard lets exactly one win.
      const outcome = await prisma.$transaction(async (tx) => {
        const claim = await tx.groupJoinRequest.updateMany({
          where: { id: request.id, groupId, status: "PENDING" },
          data: {
            status: body.decision === "APPROVE" ? "APPROVED" : "REJECTED",
            reviewNotes: body.notes ?? null,
            decidedByUserId: user?.id ?? null,
            decidedAt: new Date()
          }
        });
        if (claim.count === 0) {
          throw new ApiHttpError(409, "ALREADY_DECIDED", "That request has already been answered.");
        }

        if (body.decision === "REJECT") {
          return { approved: false as const };
        }

        // Groups usually enter their roster during setup, long before those
        // people install the app. Match on phone so approving links the person
        // to the savings already recorded against their name, instead of
        // opening a second empty passbook for the same person.
        const roster = await tx.member.findMany({
          where: { groupId },
          select: { id: true, phone: true, fullName: true }
        });
        // Compare normalised: the roster may hold +254712… and the account 0712….
        const matched = roster.find((m) => m.phone && normalisePhone(m.phone) === request.phone);

        // Accepting a match hands over someone's savings history, so it only
        // proceeds if the approver confirmed the very member the list showed
        // them. A stale screen or a blind POST is refused rather than guessed.
        if (matched && body.confirmMemberId !== matched.id) {
          throw new ApiHttpError(
            409,
            "CONFIRM_EXISTING_MEMBER",
            `${request.requestedName} gave a phone number already on the roster for ` +
              `${matched.fullName}. Accepting attaches this login to ${matched.fullName}'s ` +
              "savings records. Confirm it is the same person before continuing."
          );
        }

        const member =
          matched ??
          (await tx.member.create({
            data: {
              groupId,
              fullName: request.requestedName,
              phone: request.phone,
              status: "ACTIVE"
            },
            select: { id: true, phone: true, fullName: true }
          }));

        // Throwing here rolls the claim back, so a request that could not be
        // linked returns to PENDING rather than being consumed.
        await linkMembership(request.userId, member.id, groupId, tx);

        await tx.groupJoinRequest.update({
          where: { id: request.id },
          data: { memberId: member.id }
        });

        return { approved: true as const, member, matched: Boolean(matched) };
      });

      if (!outcome.approved) {
        await appendAuditEvent({
          actorUserId: user?.id ?? null,
          entityType: "GroupJoinRequest",
          entityId: request.id,
          type: "GROUP_JOIN_REJECTED",
          payload: { groupId, requestedName: request.requestedName, notes: body.notes ?? null }
        });
        // They were told an official would decide; tell them it happened.
        await createNotification({
          userId: request.userId,
          title: "Your request was declined",
          type: "GROUP_JOIN_REJECTED",
          body: body.notes?.trim()
            ? `${visible.name} declined your request: ${body.notes.trim()}`
            : `${visible.name} declined your request to join.`
        });

        ok(res, { id: request.id, status: "REJECTED" });
        return;
      }

      const member = outcome.member;
      const matched = outcome.matched;

      await appendAuditEvent({
        actorUserId: user?.id ?? null,
        entityType: "GroupJoinRequest",
        entityId: request.id,
        type: "GROUP_JOIN_APPROVED",
        payload: {
          groupId,
          memberId: member.id,
          memberName: member.fullName,
          /// Whether this attached to savings the group had already recorded.
          matchedExisting: Boolean(matched)
        }
      });

      await createNotification({
        userId: request.userId,
        title: `You are now in ${visible.name}`,
        type: "GROUP_JOIN_APPROVED",
        body: matched
          ? "Your savings already recorded with the group are now in your passbook."
          : "You have been added to the group. Your savings will appear as they are recorded."
      });

      ok(res, {
        id: request.id,
        status: "APPROVED",
        memberId: member.id,
        /// True when this linked to savings the group had already recorded.
        matchedExistingMember: Boolean(matched)
      });
    } catch (error) {
      next(asApiError(error));
    }
  }
);

export { router as groupJoinRouter };
