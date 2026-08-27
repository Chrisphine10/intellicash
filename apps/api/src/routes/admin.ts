import { Router } from "express";
import bcrypt from "bcryptjs";
import { z } from "zod";
import type { Prisma } from "@prisma/client";
import { languagePreferences, permissions, roles, type Role } from "@intellicash/shared";
import { requireAuth } from "../middleware/auth";
import { appendAuditEvent } from "../services/audit-service";
import { setAgentProgrammes } from "../services/village-agent-service";
import {
  partnerScopeForUser,
  programmeScopeForUser,
  scopeGroupWhere,
  villageAgentScopeForUser
} from "../services/account-scope";
import {
  getRolePermissionMap,
  normalizePermissionList,
  updateRolePermissionTemplate
} from "../services/role-permission-service";
import {
  generateAndQueueMemberPin,
  serializeMemberPinDelivery
} from "../services/member-pin-service";
import { ApiHttpError, ok } from "../lib/http";
import { linkMembership, MemberAlreadyLinkedError } from "../services/membership-service";
import { prisma } from "../lib/prisma";
import { normalisePhone } from "../lib/phone";
import { planUserAccountClosure } from "../domain/data-subject";

const router = Router();

const userSelect = {
  id: true,
  name: true,
  email: true,
  // The sign-in identifier, and the one most likely to carry a typo that locks
  // somebody out. It was not selected, so the console could not show it and an
  // admin could not tell a wrong number from a missing one.
  phone: true,
  role: true,
  status: true,
  avatarUrl: true,
  languagePreference: true,
  partnerId: true,
  groupId: true,
  memberId: true,
  // Without this the console cannot show which agent an account belongs to,
  // and cannot tell a correctly bound agent from one that will see nothing.
  villageAgentId: true,
  partner: { select: { id: true, name: true } },
  group: { select: { id: true, name: true, code: true } },
  member: { select: { id: true, fullName: true, phone: true } },
  createdAt: true
};

const accountProfiles: Record<
  Role,
  {
    accountType: string;
    requiredBinding: "GROUP" | "MEMBER" | "NONE" | "PARTNER" | "LENDER" | "VILLAGE_AGENT";
    dashboard: string;
    dataScope: string;
  }
> = {
  IWL_ADMIN: {
    accountType: "Admin",
    requiredBinding: "NONE",
    dashboard: "Full platform operations",
    dataScope: "All partners, programmes, groups, members, integrations, users, and audit events"
  },
  PARTNER_OFFICER: {
    accountType: "Partner",
    requiredBinding: "PARTNER",
    dashboard: "Partner portfolio dashboard",
    dataScope: "Programmes, groups, members, meetings, ledger entries, and reports linked to the partner"
  },
  GROUP_ACCOUNT: {
    accountType: "Group",
    requiredBinding: "GROUP",
    dashboard: "Group account dashboard",
    dataScope: "One assigned group, its members, meetings, ledger entries, votes, score, and store product requests"
  },
  MEMBER: {
    accountType: "Member",
    requiredBinding: "MEMBER",
    dashboard: "Member account dashboard",
    dataScope: "One member profile and that member's scoped group, ledger, meetings, votes, score, and store requests"
  },
  LENDER: {
    accountType: "Lender",
    requiredBinding: "LENDER",
    dashboard: "Lender portfolio dashboard",
    dataScope: "Programmes, groups, credit-readiness, ledger visibility, and store requests linked for financing"
  },
  VILLAGE_AGENT: {
    accountType: "Village agent / CBT",
    requiredBinding: "VILLAGE_AGENT",
    dashboard: "Agent caseload dashboard",
    dataScope:
      "Only the groups assigned to this agent — their members, meetings, ledger, scores, and store distribution"
  },
  READ_ONLY: {
    accountType: "Read only",
    requiredBinding: "NONE",
    dashboard: "Read-only oversight dashboard",
    dataScope: "Platform-wide read views without write operations"
  }
};

async function accessControlPayload() {
  const effectiveRolePermissions = await getRolePermissionMap();

  return {
    roles,
    permissions,
    rolePermissions: effectiveRolePermissions,
    accountProfiles: roles.map((role) => ({
      role,
      permissionCount: effectiveRolePermissions[role].length,
      ...accountProfiles[role]
    }))
  };
}

/**
 * An admin binding an account to a member who already has one is a mistake
 * worth naming, not a 500 — the alternative would be silently detaching the
 * existing account from that person's savings.
 */
function asBindingError(error: unknown) {
  if (error instanceof MemberAlreadyLinkedError) {
    return new ApiHttpError(
      409,
      "MEMBER_ALREADY_LINKED",
      "That member already has a sign-in account. Remove it before binding another."
    );
  }
  return error;
}

async function normalizeUserBinding(input: {
  /** Excluded from the "already claimed" check when editing an account. */
  currentUserId?: string;
  role: Role;
  partnerId?: string | null;
  groupId?: string | null;
  memberId?: string | null;
  villageAgentId?: string | null;
}) {
  if (input.role === "IWL_ADMIN" || input.role === "READ_ONLY") {
    return { partnerId: null, groupId: null, memberId: null, villageAgentId: null };
  }

  /*
   * A village agent account, which had no branch at all and fell through to the
   * member one below.
   *
   * The consequences were both halves of the same fault: the console demanded a
   * MEMBER for an agent account, so an agent could not be created here — and
   * `villageAgentId` was never set, so anything that did get through was a
   * village agent bound to no village agent. `scopeGroupWhere` returns an
   * impossible filter for exactly that, meaning every group 404s and the app
   * reads as broken rather than as a mis-configured account.
   */
  if (input.role === "VILLAGE_AGENT") {
    if (!input.villageAgentId) {
      throw new ApiHttpError(
        400,
        "VILLAGE_AGENT_REQUIRED",
        "Agent accounts must be linked to a VA / CBT record, or they sign in to an empty caseload."
      );
    }

    const agent = await prisma.villageAgent.findUnique({
      where: { id: input.villageAgentId },
      select: { id: true }
    });
    if (!agent) {
      throw new ApiHttpError(404, "VILLAGE_AGENT_NOT_FOUND", "Selected VA / CBT record does not exist.");
    }

    return { partnerId: null, groupId: null, memberId: null, villageAgentId: agent.id };
  }

  if (input.role === "PARTNER_OFFICER" || input.role === "LENDER") {
    if (!input.partnerId) {
      throw new ApiHttpError(400, "PARTNER_REQUIRED", "Partner and lender accounts require a partner/lender.");
    }

    const partner = await prisma.partner.findUnique({
      where: { id: input.partnerId },
      select: { id: true, type: true }
    });

    if (!partner) {
      throw new ApiHttpError(404, "PARTNER_NOT_FOUND", "Selected partner/lender does not exist.");
    }

    if (input.role === "LENDER" && partner.type !== "LENDER") {
      throw new ApiHttpError(400, "LENDER_REQUIRED", "Lender accounts must be bound to a lender partner.");
    }

    if (input.role === "PARTNER_OFFICER" && partner.type === "LENDER") {
      throw new ApiHttpError(400, "PARTNER_REQUIRED", "Partner officer accounts must be bound to a non-lender partner.");
    }

    return { partnerId: partner.id, groupId: null, memberId: null, villageAgentId: null };
  }

  if (input.role === "GROUP_ACCOUNT") {
    if (!input.groupId) {
      throw new ApiHttpError(400, "GROUP_REQUIRED", "Group accounts require a group.");
    }

    const group = await prisma.group.findUnique({
      where: { id: input.groupId },
      select: { id: true }
    });

    if (!group) {
      throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Selected group does not exist.");
    }

    return { partnerId: null, groupId: group.id, memberId: null, villageAgentId: null };
  }

  if (!input.memberId) {
    throw new ApiHttpError(400, "MEMBER_REQUIRED", "Member accounts require a member.");
  }

  const member = await prisma.member.findUnique({
    where: { id: input.memberId },
    select: { id: true, groupId: true }
  });

  if (!member) {
    throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Selected member does not exist.");
  }

  if (input.groupId && input.groupId !== member.groupId) {
    throw new ApiHttpError(400, "MEMBER_GROUP_MISMATCH", "Selected member does not belong to the selected group.");
  }

  // Refuse here rather than letting the unique constraint on User.memberId
  // surface as a 500 from the insert. One roster entry is one person, so
  // handing it to a second login would detach the first from their savings.
  const heldBy = await prisma.user.findFirst({
    where: { memberId: member.id, ...(input.currentUserId ? { NOT: { id: input.currentUserId } } : {}) },
    select: { id: true, email: true }
  });
  if (heldBy) {
    throw new ApiHttpError(
      409,
      "MEMBER_ALREADY_LINKED",
      `That member already signs in as ${heldBy.email}. Remove that account before binding another.`
    );
  }

  // Cleared like the others: a member account carrying a stale agent link is a
  // caseload nobody is watching.
  return { partnerId: null, groupId: member.groupId, memberId: member.id, villageAgentId: null };
}

async function queueMemberAccountPin(
  tx: Prisma.TransactionClient,
  memberId: string,
  actorUserId?: string | null
) {
  const member = await tx.member.findUnique({
    where: { id: memberId },
    select: { id: true, fullName: true, phone: true, pinSetAt: true }
  });

  if (!member) return null;

  const { delivery } = await generateAndQueueMemberPin(tx, member, {
    requestedByUserId: actorUserId,
    select: { id: true }
  });

  return delivery;
}

router.get("/users", requireAuth("users:read"), async (_req, res, next) => {
  try {
    const users = await prisma.user.findMany({
      orderBy: { createdAt: "desc" },
      select: userSelect
    });

    ok(res, users);
  } catch (error) {
    next(error);
  }
});

router.get("/access-control", requireAuth("users:read"), async (_req, res, next) => {
  try {
    ok(res, await accessControlPayload());
  } catch (error) {
    next(error);
  }
});

const rolePermissionUpdateSchema = z.object({
  permissions: z.array(z.enum(permissions))
});

router.patch("/access-control/roles/:role/permissions", requireAuth("users:write"), async (req, res, next) => {
  try {
    const role = String(req.params.role ?? "");
    if (!roles.includes(role as Role)) {
      throw new ApiHttpError(404, "ROLE_NOT_FOUND", "Role does not exist.");
    }

    const body = rolePermissionUpdateSchema.parse(req.body);
    const before = await getRolePermissionMap();

    try {
      await updateRolePermissionTemplate(role as Role, normalizePermissionList(body.permissions));
    } catch (error) {
      throw new ApiHttpError(
        400,
        "ROLE_PERMISSION_GUARD",
        error instanceof Error ? error.message : "Role permission update is not allowed."
      );
    }

    const after = await getRolePermissionMap();

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "ROLE",
      entityId: role,
      type: "ROLE_PERMISSIONS_UPDATED",
      payload: {
        role,
        before: before[role as Role],
        after: after[role as Role]
      }
    });

    ok(res, await accessControlPayload());
  } catch (error) {
    next(error);
  }
});

const userCreateSchema = z.object({
  name: z.string().min(2),
  email: z.string().email(),
  /** Optional, but an account without one can only ever sign in by email. */
  phone: z.string().min(6).max(32).optional(),
  password: z.string().min(12),
  role: z.enum(roles),
  avatarUrl: z.string().url().optional(),
  languagePreference: z.enum(languagePreferences).optional(),
  partnerId: z.string().optional(),
  groupId: z.string().optional(),
  memberId: z.string().optional(),
  villageAgentId: z.string().optional()
});

router.post("/users", requireAuth("users:write"), async (req, res, next) => {
  try {
    const body = userCreateSchema.parse(req.body);
    const { password, ...userInput } = body;
    const passwordHash = await bcrypt.hash(password, 12);
    const binding = await normalizeUserBinding(userInput);

    const { user, pinDelivery } = await prisma.$transaction(async (tx) => {
      const createdUser = await tx.user.create({
        data: {
          name: userInput.name,
          email: userInput.email,
          phone: userInput.phone ? normalisePhone(userInput.phone) : null,
          role: userInput.role,
          avatarUrl: userInput.avatarUrl,
          languagePreference: userInput.languagePreference,
          ...binding,
          passwordHash
        },
        select: userSelect
      });

      // Record the membership too. `User.memberId` alone is the shape that
      // used to leave an account showing no groups at all until something
      // repaired it, and it is the source of truth nothing else can see.
      if (binding.memberId && binding.groupId) {
        await linkMembership(createdUser.id, binding.memberId, binding.groupId, tx);
      }

      const delivery =
        userInput.role === "MEMBER" && binding.memberId
          ? await queueMemberAccountPin(tx, binding.memberId, req.user?.id)
          : null;

      return { user: createdUser, pinDelivery: delivery };
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "USER",
      entityId: user.id,
      type: "USER_CREATED",
      payload: user
    });
    if (pinDelivery) {
      await appendAuditEvent({
        actorUserId: req.user?.id,
        entityType: "MEMBER",
        entityId: pinDelivery.memberId,
        type: "MEMBER_PIN_DELIVERY_QUEUED",
        payload: {
          memberId: pinDelivery.memberId,
          reason: "MEMBER_ACCOUNT_CREATED",
          delivery: serializeMemberPinDelivery(pinDelivery)
        }
      });
    }

    ok(res.status(201), user);
  } catch (error) {
    next(asBindingError(error));
  }
});

const userUpdateSchema = z.object({
  /**
   * Name, email and phone were not editable at all, which meant a mistyped
   * number — the thing people actually sign in with — could only be fixed by
   * creating a second account for the same person.
   */
  name: z.string().trim().min(2).max(200).optional(),
  email: z.string().email().optional(),
  phone: z.string().min(6).max(32).nullable().optional(),
  role: z.enum(roles).optional(),
  status: z.enum(["ACTIVE", "SUSPENDED"]).optional(),
  avatarUrl: z.string().url().nullable().optional(),
  languagePreference: z.enum(languagePreferences).optional(),
  partnerId: z.string().nullable().optional(),
  groupId: z.string().nullable().optional(),
  memberId: z.string().nullable().optional(),
  villageAgentId: z.string().nullable().optional()
});

router.patch("/users/:id", requireAuth("users:write"), async (req, res, next) => {
  try {
    const body = userUpdateSchema.parse(req.body);
    const userId = String(req.params.id ?? "");
    const existing = await prisma.user.findUnique({
      where: { id: userId },
      select: {
        id: true,
        role: true,
        status: true,
        partnerId: true,
        groupId: true,
        memberId: true,
        villageAgentId: true
      }
    });

    if (!existing) {
      throw new ApiHttpError(404, "USER_NOT_FOUND", "User account does not exist.");
    }

    // A closed account has had its identity stripped on purpose. Editing it
    // back into service would produce an account with no working credential
    // and no owner, sitting inside whatever group it was re-bound to.
    if (existing.status === "CLOSED") {
      throw new ApiHttpError(
        409,
        "USER_ACCOUNT_CLOSED",
        "That account has been closed. Create a new account instead of reopening it."
      );
    }

    const identity = await resolveIdentityEdits(existing.id, body);

    const role = (body.role ?? existing.role) as Role;
    const status = body.status ?? existing.status;
    const binding = await normalizeUserBinding({
      currentUserId: existing.id,
      role,
      partnerId: body.partnerId === undefined ? existing.partnerId : body.partnerId,
      groupId: body.groupId === undefined ? existing.groupId : body.groupId,
      memberId: body.memberId === undefined ? existing.memberId : body.memberId,
      // Carried through, so correcting an agent's phone does not silently
      // detach them from their caseload.
      villageAgentId:
        body.villageAgentId === undefined ? existing.villageAgentId : body.villageAgentId
    });

    if ((existing.role === "IWL_ADMIN" || role === "IWL_ADMIN") && (role !== "IWL_ADMIN" || status !== "ACTIVE")) {
      const activeAdminCount = await prisma.user.count({
        where: {
          id: { not: existing.id },
          role: "IWL_ADMIN",
          status: "ACTIVE"
        }
      });

      if (activeAdminCount === 0) {
        throw new ApiHttpError(400, "LAST_ADMIN", "At least one active IWL admin account must remain.");
      }
    }

    const shouldQueueMemberPin =
      role === "MEMBER" &&
      Boolean(binding.memberId) &&
      (existing.role !== "MEMBER" || existing.memberId !== binding.memberId);

    const { user, pinDelivery } = await prisma.$transaction(async (tx) => {
      const updatedUser = await tx.user.update({
        where: { id: existing.id },
        data: {
          ...identity,
          role,
          status,
          avatarUrl: body.avatarUrl === undefined ? undefined : body.avatarUrl,
          languagePreference: body.languagePreference,
          ...binding
        },
        select: userSelect
      });

      // Re-binding normally only changes which membership is IN VIEW, and must
      // NOT delete the others: a person can save with several VSLAs, and
      // `User.memberId` names the group they are looking at, not the only one
      // they belong to. Removing rows here took a real group away from someone,
      // and with it sight of the savings held in it.
      //
      // The one exception is re-binding within the SAME group, which means an
      // admin correcting an account pointed at the wrong person. Nobody holds
      // two places on one roster, so the mistaken row goes.
      if (binding.memberId && binding.groupId) {
        await tx.userMembership.deleteMany({
          where: {
            userId: existing.id,
            groupId: binding.groupId,
            memberId: { not: binding.memberId }
          }
        });
      }
      if (binding.memberId && binding.groupId) {
        await linkMembership(existing.id, binding.memberId, binding.groupId, tx);
      }

      const delivery =
        shouldQueueMemberPin && binding.memberId
          ? await queueMemberAccountPin(tx, binding.memberId, req.user?.id)
          : null;

      return { user: updatedUser, pinDelivery: delivery };
    });

    if (status !== "ACTIVE") {
      await prisma.session.deleteMany({ where: { userId: user.id } });
    }

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "USER",
      entityId: user.id,
      type: "USER_UPDATED",
      payload: {
        before: existing,
        after: user
      }
    });
    if (pinDelivery) {
      await appendAuditEvent({
        actorUserId: req.user?.id,
        entityType: "MEMBER",
        entityId: pinDelivery.memberId,
        type: "MEMBER_PIN_DELIVERY_QUEUED",
        payload: {
          memberId: pinDelivery.memberId,
          reason: "MEMBER_ACCOUNT_ASSIGNED",
          delivery: serializeMemberPinDelivery(pinDelivery)
        }
      });
    }

    ok(res, user);
  } catch (error) {
    next(asBindingError(error));
  }
});

/**
 * Validates a change to the fields somebody signs in with.
 *
 * Both are UNIQUE, so a collision is a 500 from the database unless it is
 * caught here — and the message an admin needs is "that number already belongs
 * to another account", not "Unique constraint failed".
 *
 * The phone is canonicalised before both the check and the write. Kenyan
 * numbers are written six different ways by the same person on different days;
 * comparing raw strings is how one human ends up as two accounts.
 */
async function resolveIdentityEdits(
  userId: string,
  body: { name?: string; email?: string; phone?: string | null }
) {
  const edits: { name?: string; email?: string; phone?: string | null } = {};

  if (body.name !== undefined) edits.name = body.name.trim();

  if (body.email !== undefined) {
    const email = body.email.trim().toLowerCase();
    const clash = await prisma.user.findFirst({
      where: { email, id: { not: userId } },
      select: { id: true }
    });
    if (clash) {
      throw new ApiHttpError(409, "EMAIL_TAKEN", "Another account already uses that email address.");
    }
    edits.email = email;
  }

  if (body.phone !== undefined) {
    if (body.phone === null || body.phone.trim() === "") {
      edits.phone = null;
    } else {
      const phone = normalisePhone(body.phone);
      if (!phone) {
        throw new ApiHttpError(400, "PHONE_INVALID", "That does not look like a phone number.");
      }
      const clash = await prisma.user.findFirst({
        where: { phone, id: { not: userId } },
        select: { id: true }
      });
      if (clash) {
        throw new ApiHttpError(409, "PHONE_TAKEN", "Another account already uses that phone number.");
      }
      edits.phone = phone;
    }
  }

  return edits;
}

const userCloseSchema = z.object({
  /** The account's current email, typed back by whoever is closing it. */
  confirmEmail: z.string().min(1),
  reason: z.string().trim().min(3).max(500)
});

/**
 * Closes an account: strips the identity, keeps the trail.
 *
 * DELETE, because that is what an administrator means and what the button says.
 * What it must NOT do is `DELETE FROM User`. Every relation pointing at User is
 * `onDelete: SetNull` — `AuditEvent.actor` included — so a real delete would
 * silently blank the actor on every audit record that account ever produced.
 * The trail would remain, readable, and no longer able to say who did any of
 * it. In a system holding other people's savings, a routine admin action must
 * not be able to do that.
 *
 * So the row stays as a pseudonymous key and everything identifying is removed
 * from it, per `planUserAccountClosure`. Afterwards nothing in the record says
 * who the person was or how to reach them, and every historical action is still
 * attributable to a row.
 *
 * Not reversible, and deliberately not: an "undo" would have to restore the
 * identity, which means keeping a copy of exactly what was supposed to be gone.
 */
router.delete("/users/:id", requireAuth("users:write"), async (req, res, next) => {
  try {
    const body = userCloseSchema.parse(req.body ?? {});
    const userId = String(req.params.id ?? "");

    const existing = await prisma.user.findUnique({
      where: { id: userId },
      select: { id: true, email: true, role: true, status: true }
    });
    if (!existing) {
      throw new ApiHttpError(404, "USER_NOT_FOUND", "User account does not exist.");
    }
    if (existing.status === "CLOSED") {
      throw new ApiHttpError(409, "USER_ALREADY_CLOSED", "That account is already closed.");
    }

    // Closing the account you are signed in with ends the request doing it and
    // leaves nobody holding the session that could undo a mistake.
    if (req.user?.id === existing.id) {
      throw new ApiHttpError(
        400,
        "CANNOT_CLOSE_OWN_ACCOUNT",
        "You cannot close the account you are signed in with. Ask another admin to do it."
      );
    }

    // Typed back rather than clicked once. The id in the URL is invisible to
    // whoever pressed the button, and the row above the one they meant looks
    // exactly the same.
    if (body.confirmEmail.trim().toLowerCase() !== existing.email.trim().toLowerCase()) {
      throw new ApiHttpError(
        400,
        "CONFIRMATION_MISMATCH",
        "Type the account's email address exactly as it appears to confirm."
      );
    }

    if (existing.role === "IWL_ADMIN") {
      const remaining = await prisma.user.count({
        where: { id: { not: existing.id }, role: "IWL_ADMIN", status: "ACTIVE" }
      });
      if (remaining === 0) {
        throw new ApiHttpError(400, "LAST_ADMIN", "At least one active IWL admin account must remain.");
      }
    }

    const plan = planUserAccountClosure(existing.id);
    const replacement = (field: string) =>
      plan.erase.find((entry) => entry.field === field)?.replacement ?? null;

    const closed = await prisma.$transaction(async (tx) => {
      await tx.session.deleteMany({ where: { userId: existing.id } });

      // The login's links to groups. The Member rows they point at — and every
      // shilling recorded against them — belong to the group and stay put.
      // Closing a login is not removing somebody from a roster.
      await tx.userMembership.deleteMany({ where: { userId: existing.id } });

      return tx.user.update({
        where: { id: existing.id },
        data: {
          status: "CLOSED",
          name: replacement("name") ?? "Closed account",
          email: replacement("email") ?? `closed-${existing.id}@account.invalid`,
          phone: null,
          avatarUrl: null,
          // Cleared rather than left. Status gates sign-in today, but a closed
          // account whose credential still verifies is one mistaken status flip
          // away from being live — and nobody would be watching that account.
          passwordHash: "",
          partnerId: null,
          groupId: null,
          memberId: null,
          villageAgentId: null
        },
        select: userSelect
      });
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "USER",
      entityId: existing.id,
      type: "USER_ACCOUNT_CLOSED",
      payload: {
        // Deliberately NOT the name, email or phone. Removing those is the
        // point of the action; writing them into the audit payload would put
        // them straight back, in a table nobody thinks to look in. The entity
        // id identifies the row for anyone who has to follow it.
        role: existing.role,
        previousStatus: existing.status,
        reason: body.reason,
        retained: plan.retain.map((entry) => entry.entity)
      }
    });

    ok(res, { user: closed, closed: true, retained: plan.retain });
  } catch (error) {
    next(error);
  }
});

router.get("/partners", requireAuth("partners:read"), async (req, res, next) => {
  try {
    const partners = await prisma.partner.findMany({
      where: partnerScopeForUser(req.user),
      orderBy: { createdAt: "desc" },
      include: {
        _count: {
          select: {
            programmes: true,
            programmeLinks: true,
            users: true,
            webhookSubscriptions: true
          }
        }
      }
    });

    ok(res, partners);
  } catch (error) {
    next(error);
  }
});

const partnerSchema = z.object({
  name: z.string().min(2),
  type: z.string().min(2),
  status: z.string().default("ACTIVE"),
  apiScope: z.string().default("PROGRAMME"),
  county: z.string().nullable().optional(),
  contactName: z.string().nullable().optional(),
  contactPhone: z.string().nullable().optional(),
  valueProposition: z.string().nullable().optional(),
  capacity: z.string().nullable().optional(),
  linkageType: z.string().nullable().optional()
});

const partnerUpdateSchema = z.object({
  name: z.string().min(2).optional(),
  type: z.string().min(2).optional(),
  status: z.string().min(2).optional(),
  apiScope: z.string().min(2).optional(),
  county: z.string().nullable().optional(),
  contactName: z.string().nullable().optional(),
  contactPhone: z.string().nullable().optional(),
  valueProposition: z.string().nullable().optional(),
  capacity: z.string().nullable().optional(),
  linkageType: z.string().nullable().optional()
});

router.post("/partners", requireAuth("partners:write"), async (req, res, next) => {
  try {
    const body = partnerSchema.parse(req.body);
    const partner = await prisma.partner.create({ data: body });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "PARTNER",
      entityId: partner.id,
      type: "PARTNER_CREATED",
      payload: partner
    });

    ok(res.status(201), partner);
  } catch (error) {
    next(error);
  }
});

router.patch("/partners/:id", requireAuth("partners:write"), async (req, res, next) => {
  try {
    const partnerId = z.string().parse(req.params.id);
    const body = partnerUpdateSchema.parse(req.body);
    const existing = await prisma.partner.findFirst({
      where: {
        AND: [{ id: partnerId }, partnerScopeForUser(req.user)]
      },
      include: {
        _count: {
          select: {
            programmes: true,
            programmeLinks: true,
            users: true,
            webhookSubscriptions: true
          }
        }
      }
    });

    if (!existing) {
      throw new ApiHttpError(404, "PARTNER_NOT_FOUND", "Partner does not exist or is outside this account.");
    }

    const partner = await prisma.partner.update({
      where: { id: existing.id },
      data: body,
      include: {
        _count: {
          select: {
            programmes: true,
            programmeLinks: true,
            users: true,
            webhookSubscriptions: true
          }
        }
      }
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "PARTNER",
      entityId: partner.id,
      type: "PARTNER_UPDATED",
      payload: {
        before: existing,
        after: partner
      }
    });

    ok(res, partner);
  } catch (error) {
    next(error);
  }
});

const programmeInclude = {
  partner: true,
  partnerLinks: {
    include: {
      partner: true
    },
    orderBy: { role: "asc" }
  },
  assets: {
    orderBy: [{ type: "asc" }, { createdAt: "desc" }]
  },
  groupLinks: {
    include: {
      group: {
        select: {
          id: true,
          name: true,
          code: true,
          county: true,
          phase: true
        }
      }
    },
    orderBy: { createdAt: "asc" }
  },
  _count: {
    select: {
      groups: true,
      villageAgentLinks: true,
      partnerLinks: true,
      groupLinks: true
    }
  }
} satisfies Prisma.ProgrammeInclude;

router.get("/programmes", requireAuth("programmes:read"), async (req, res, next) => {
  try {
    const programmes = await prisma.programme.findMany({
      where: programmeScopeForUser(req.user),
      orderBy: { createdAt: "desc" },
      include: programmeInclude
    });

    ok(res, programmes);
  } catch (error) {
    next(error);
  }
});

const publicProgrammeStatuses = ["DRAFT", "ONGOING", "PAUSED", "CLOSED"] as const;

const programmeSchema = z.object({
  partnerId: z.string().optional(),
  partnerIds: z.array(z.string()).optional(),
  lenderPartnerIds: z.array(z.string()).optional(),
  name: z.string().min(2),
  country: z.string().default("Kenya"),
  county: z.string().optional(),
  description: z.string().optional(),
  coverImageUrl: z.string().url().optional(),
  publicSlug: z
    .string()
    .regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, "Public slug must use lowercase letters, numbers, and hyphens.")
    .nullable()
    .optional(),
  publicStatus: z.enum(publicProgrammeStatuses).default("DRAFT"),
  fundingGoalCents: z.number().int().min(0).default(0),
  fundingSummary: z.string().nullable().optional(),
  impactSummary: z.string().nullable().optional(),
  fundingDeadline: z.string().datetime().nullable().optional(),
  allowInvestments: z.boolean().default(true),
  allowDonations: z.boolean().default(true)
});

const programmeUpdateSchema = z.object({
  partnerId: z.string().nullable().optional(),
  partnerIds: z.array(z.string()).optional(),
  lenderPartnerIds: z.array(z.string()).optional(),
  name: z.string().min(2).optional(),
  country: z.string().min(2).optional(),
  county: z.string().nullable().optional(),
  description: z.string().nullable().optional(),
  coverImageUrl: z.string().url().nullable().optional(),
  publicSlug: z
    .string()
    .regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, "Public slug must use lowercase letters, numbers, and hyphens.")
    .nullable()
    .optional(),
  publicStatus: z.enum(publicProgrammeStatuses).optional(),
  fundingGoalCents: z.number().int().min(0).optional(),
  fundingSummary: z.string().nullable().optional(),
  impactSummary: z.string().nullable().optional(),
  fundingDeadline: z.string().datetime().nullable().optional(),
  allowInvestments: z.boolean().optional(),
  allowDonations: z.boolean().optional()
});

function programmeLinkData(partnerIds: string[], lenderPartnerIds: string[]) {
  return [
    ...partnerIds.map((partnerId, index) => ({
      partnerId,
      role: index === 0 ? "IMPLEMENTING_PARTNER" : "PARTNER"
    })),
    ...lenderPartnerIds
      .filter((partnerId) => !partnerIds.includes(partnerId))
      .map((partnerId) => ({
        partnerId,
        role: "LENDER"
      }))
  ];
}

async function validatePartnerLinksForUser(user: Express.Request["user"], partnerIds: string[]) {
  const uniquePartnerIds = Array.from(new Set(partnerIds));
  if (uniquePartnerIds.length === 0) return uniquePartnerIds;

  const partners = await prisma.partner.findMany({
    where: {
      AND: [{ id: { in: uniquePartnerIds } }, partnerScopeForUser(user)]
    },
    select: { id: true }
  });

  if (partners.length !== uniquePartnerIds.length) {
    throw new ApiHttpError(404, "PARTNER_NOT_FOUND", "One or more selected partners/lenders do not exist or are outside this account.");
  }

  return uniquePartnerIds;
}

router.post("/programmes", requireAuth("programmes:write"), async (req, res, next) => {
  try {
    const body = programmeSchema.parse(req.body);
    const partnerIds = Array.from(new Set([body.partnerId, ...(body.partnerIds ?? [])].filter(Boolean))) as string[];
    const lenderPartnerIds = Array.from(new Set(body.lenderPartnerIds ?? []));
    const primaryPartnerId = partnerIds[0] ?? lenderPartnerIds[0];

    if (!primaryPartnerId) {
      throw new ApiHttpError(400, "PARTNER_REQUIRED", "A program requires at least one partner or lender.");
    }

    const allPartnerIds = Array.from(new Set([...partnerIds, ...lenderPartnerIds]));
    await validatePartnerLinksForUser(req.user, allPartnerIds);

    if (body.publicSlug) {
      const slugOwner = await prisma.programme.findUnique({
        where: { publicSlug: body.publicSlug },
        select: { id: true }
      });
      if (slugOwner) {
        throw new ApiHttpError(400, "PUBLIC_SLUG_TAKEN", "Public slug is already used by another program.");
      }
    }

    const programme = await prisma.programme.create({
      data: {
        name: body.name,
        country: body.country,
        county: body.county,
        description: body.description,
        coverImageUrl: body.coverImageUrl,
        publicSlug: body.publicSlug,
        publicStatus: body.publicStatus,
        fundingGoalCents: body.fundingGoalCents,
        fundingSummary: body.fundingSummary,
        impactSummary: body.impactSummary,
        fundingDeadline: body.fundingDeadline ? new Date(body.fundingDeadline) : null,
        allowInvestments: body.allowInvestments,
        allowDonations: body.allowDonations,
        partnerId: primaryPartnerId,
        partnerLinks: {
          create: programmeLinkData(partnerIds, lenderPartnerIds)
        }
      },
      include: programmeInclude
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "PROGRAMME",
      entityId: programme.id,
      type: "PROGRAMME_CREATED",
      payload: programme
    });

    ok(res.status(201), programme);
  } catch (error) {
    next(error);
  }
});

router.patch("/programmes/:id", requireAuth("programmes:write"), async (req, res, next) => {
  try {
    const programmeId = z.string().parse(req.params.id);
    const body = programmeUpdateSchema.parse(req.body);
    const existing = await prisma.programme.findFirst({
      where: {
        AND: [{ id: programmeId }, programmeScopeForUser(req.user)]
      },
      include: programmeInclude
    });

    if (!existing) {
      throw new ApiHttpError(404, "PROGRAMME_NOT_FOUND", "Program does not exist or is outside this account.");
    }

    if (body.publicSlug) {
      const slugOwner = await prisma.programme.findUnique({
        where: { publicSlug: body.publicSlug },
        select: { id: true }
      });
      if (slugOwner && slugOwner.id !== existing.id) {
        throw new ApiHttpError(400, "PUBLIC_SLUG_TAKEN", "Public slug is already used by another program.");
      }
    }

    const linksRequested =
      body.partnerId !== undefined ||
      body.partnerIds !== undefined ||
      body.lenderPartnerIds !== undefined;
    const currentPartnerIds =
      existing.partnerLinks
        ?.filter((link) => link.role !== "LENDER")
        .map((link) => link.partnerId) ?? [existing.partnerId];
    const currentLenderPartnerIds =
      existing.partnerLinks
        ?.filter((link) => link.role === "LENDER")
        .map((link) => link.partnerId) ?? [];
    const partnerIds = linksRequested
      ? Array.from(new Set([body.partnerId, ...(body.partnerIds ?? [])].filter(Boolean))) as string[]
      : currentPartnerIds;
    const lenderPartnerIds = linksRequested
      ? Array.from(new Set(body.lenderPartnerIds ?? currentLenderPartnerIds))
      : currentLenderPartnerIds;
    const primaryPartnerId = partnerIds[0] ?? lenderPartnerIds[0];

    if (linksRequested) {
      if (!primaryPartnerId) {
        throw new ApiHttpError(400, "PARTNER_REQUIRED", "A program requires at least one partner or lender.");
      }
      await validatePartnerLinksForUser(req.user, [...partnerIds, ...lenderPartnerIds]);
    }

    const programme = await prisma.$transaction(async (tx) => {
      await tx.programme.update({
        where: { id: existing.id },
        data: {
          name: body.name,
          country: body.country,
          county: body.county === undefined ? undefined : body.county,
          description: body.description === undefined ? undefined : body.description,
          coverImageUrl: body.coverImageUrl === undefined ? undefined : body.coverImageUrl,
          publicSlug: body.publicSlug === undefined ? undefined : body.publicSlug,
          publicStatus: body.publicStatus,
          fundingGoalCents: body.fundingGoalCents,
          fundingSummary: body.fundingSummary === undefined ? undefined : body.fundingSummary,
          impactSummary: body.impactSummary === undefined ? undefined : body.impactSummary,
          fundingDeadline:
            body.fundingDeadline === undefined
              ? undefined
              : body.fundingDeadline
                ? new Date(body.fundingDeadline)
                : null,
          allowInvestments: body.allowInvestments,
          allowDonations: body.allowDonations,
          partnerId: linksRequested ? primaryPartnerId : undefined
        }
      });

      if (linksRequested) {
        await tx.programmePartner.deleteMany({ where: { programmeId: existing.id } });
        await tx.programmePartner.createMany({
          data: programmeLinkData(partnerIds, lenderPartnerIds).map((link) => ({
            programmeId: existing.id,
            ...link
          }))
        });
      }

      return tx.programme.findUniqueOrThrow({
        where: { id: existing.id },
        include: programmeInclude
      });
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "PROGRAMME",
      entityId: programme.id,
      type: "PROGRAMME_UPDATED",
      payload: {
        before: existing,
        after: programme
      }
    });

    ok(res, programme);
  } catch (error) {
    next(error);
  }
});

const programmeAssetSchema = z.object({
  type: z.enum(["IMAGE", "FILE"]),
  visibility: z.enum(["PUBLIC", "PRIVATE"]).default("PRIVATE"),
  title: z.string().min(2),
  description: z.string().optional(),
  url: z.string().url(),
  fileName: z.string().optional(),
  mimeType: z.string().optional()
});

const programmeAssetUpdateSchema = z.object({
  type: z.enum(["IMAGE", "FILE"]).optional(),
  visibility: z.enum(["PUBLIC", "PRIVATE"]).optional(),
  title: z.string().min(2).optional(),
  description: z.string().nullable().optional(),
  url: z.string().url().optional(),
  fileName: z.string().nullable().optional(),
  mimeType: z.string().nullable().optional()
});

router.get("/programmes/:id/assets", requireAuth("programmes:read"), async (req, res, next) => {
  try {
    const programmeId = z.string().parse(req.params.id);
    const programme = await prisma.programme.findFirst({
      where: {
        AND: [{ id: programmeId }, programmeScopeForUser(req.user)]
      },
      select: { id: true }
    });

    if (!programme) {
      throw new ApiHttpError(404, "PROGRAMME_NOT_FOUND", "Program does not exist or is outside this account.");
    }

    const assets = await prisma.programmeAsset.findMany({
      where: { programmeId },
      orderBy: [{ type: "asc" }, { createdAt: "desc" }]
    });

    ok(res, assets);
  } catch (error) {
    next(error);
  }
});

router.post("/programmes/:id/assets", requireAuth("programmes:write"), async (req, res, next) => {
  try {
    const programmeId = z.string().parse(req.params.id);
    const body = programmeAssetSchema.parse(req.body);
    const programme = await prisma.programme.findFirst({
      where: {
        AND: [{ id: programmeId }, programmeScopeForUser(req.user)]
      },
      select: { id: true }
    });

    if (!programme) {
      throw new ApiHttpError(404, "PROGRAMME_NOT_FOUND", "Program does not exist or is outside this account.");
    }

    const asset = await prisma.programmeAsset.create({
      data: {
        programmeId,
        ...body
      }
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "PROGRAMME",
      entityId: programmeId,
      type: "PROGRAMME_ASSET_CREATED",
      payload: asset
    });

    ok(res.status(201), asset);
  } catch (error) {
    next(error);
  }
});

router.patch("/programmes/:id/assets/:assetId", requireAuth("programmes:write"), async (req, res, next) => {
  try {
    const programmeId = z.string().parse(req.params.id);
    const assetId = z.string().parse(req.params.assetId);
    const body = programmeAssetUpdateSchema.parse(req.body);
    const programme = await prisma.programme.findFirst({
      where: {
        AND: [{ id: programmeId }, programmeScopeForUser(req.user)]
      },
      select: { id: true }
    });

    if (!programme) {
      throw new ApiHttpError(404, "PROGRAMME_NOT_FOUND", "Program does not exist or is outside this account.");
    }

    const existing = await prisma.programmeAsset.findFirst({
      where: { id: assetId, programmeId }
    });

    if (!existing) {
      throw new ApiHttpError(404, "PROGRAMME_ASSET_NOT_FOUND", "Program asset does not exist.");
    }

    const asset = await prisma.programmeAsset.update({
      where: { id: existing.id },
      data: {
        type: body.type,
        visibility: body.visibility,
        title: body.title,
        description: body.description === undefined ? undefined : body.description,
        url: body.url,
        fileName: body.fileName === undefined ? undefined : body.fileName,
        mimeType: body.mimeType === undefined ? undefined : body.mimeType
      }
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "PROGRAMME",
      entityId: programmeId,
      type: "PROGRAMME_ASSET_UPDATED",
      payload: {
        before: existing,
        after: asset
      }
    });

    ok(res, asset);
  } catch (error) {
    next(error);
  }
});

router.get(
  "/village-agents",
  requireAuth("village-agents:read"),
  async (req, res, next) => {
    try {
      const agents = await prisma.villageAgent.findMany({
        where: villageAgentScopeForUser(req.user),
        orderBy: { createdAt: "desc" },
        include: {
          partner: true,
          programmeLinks: {
            include: { programme: { include: { partner: true } } },
            orderBy: { createdAt: "asc" }
          },
          groups: {
            select: {
              id: true,
              name: true,
              code: true,
              county: true,
              phase: true
            },
            orderBy: { name: "asc" }
          },
          _count: {
            select: { groups: true }
          }
        }
      });

      ok(res, agents);
    } catch (error) {
      next(error);
    }
  }
);

const villageAgentSchema = z.object({
  /**
   * The programmes this agent serves. `programmeId` is still accepted so an
   * existing caller is not broken by the change; it is folded into the list.
   */
  programmeIds: z.array(z.string()).optional(),
  programmeId: z.string().optional(),
  name: z.string().min(2),
  phone: z.string().min(7),
  email: z.string().email().optional(),
  gender: z.string().optional(),
  projectOfficer: z.string().optional(),
  county: z.string().optional(),
  location: z.string().optional(),
  feedback: z.string().optional(),
  digitalLiteracyScore: z.number().int().min(0).max(100).default(80),
  caseloadLimit: z.number().int().min(1).max(100).default(25),
  groupIds: z.array(z.string()).default([])
});

const villageAgentUpdateSchema = z.object({
  programmeIds: z.array(z.string()).optional(),
  programmeId: z.string().nullable().optional(),
  name: z.string().min(2).optional(),
  phone: z.string().min(7).optional(),
  email: z.string().email().nullable().optional(),
  gender: z.string().nullable().optional(),
  projectOfficer: z.string().nullable().optional(),
  county: z.string().nullable().optional(),
  location: z.string().nullable().optional(),
  feedback: z.string().nullable().optional(),
  status: z.enum(["ACTIVE", "INACTIVE", "SUSPENDED"]).optional(),
  digitalLiteracyScore: z.number().int().min(0).max(100).optional(),
  caseloadLimit: z.number().int().min(1).max(100).optional(),
  groupIds: z.array(z.string()).optional()
});

const villageAgentInclude = {
  partner: true,
  programmeLinks: {
    include: {
      programme: {
        include: {
          partner: true
        }
      }
    },
    orderBy: { createdAt: "asc" }
  },
  groups: {
    select: {
      id: true,
      name: true,
      code: true,
      county: true,
      phase: true
    },
    orderBy: { name: "asc" }
  },
  _count: {
    select: { groups: true }
  }
} satisfies Prisma.VillageAgentInclude;

async function assertProgrammeWriteScope(user: Express.Request["user"], programmeId?: string | null) {
  if (!programmeId) return null;

  const programme = await prisma.programme.findFirst({
    where: {
      AND: [{ id: programmeId }, programmeScopeForUser(user)]
    },
    select: { id: true }
  });

  if (!programme) {
    throw new ApiHttpError(404, "PROGRAMME_NOT_FOUND", "Selected program does not exist or is outside this account.");
  }

  return programme.id;
}

async function validateAgentGroupAssignment(input: {
  user: Express.Request["user"];
  groupIds: string[];
  caseloadLimit: number;
}) {
  const groupIds = Array.from(new Set(input.groupIds));

  if (groupIds.length > input.caseloadLimit) {
    throw new ApiHttpError(400, "CASELOAD_LIMIT_EXCEEDED", "Assigned groups exceed this VA / CBT caseload limit.");
  }

  if (groupIds.length === 0) return groupIds;

  const groups = await prisma.group.findMany({
    where: scopeGroupWhere(input.user, { id: { in: groupIds } }),
    select: { id: true }
  });

  if (groups.length !== groupIds.length) {
    throw new ApiHttpError(404, "GROUP_NOT_FOUND", "One or more selected groups do not exist or are outside this account.");
  }

  return groupIds;
}

async function setAgentGroups(
  tx: Prisma.TransactionClient,
  agentId: string,
  groupIds: string[]
) {
  await tx.group.updateMany({
    where: {
      villageAgentId: agentId,
      id: { notIn: groupIds.length > 0 ? groupIds : ["__no_selected_groups__"] }
    },
    data: { villageAgentId: null }
  });

  if (groupIds.length > 0) {
    await tx.group.updateMany({
      where: { id: { in: groupIds } },
      data: { villageAgentId: agentId }
    });
  }
}

router.post(
  "/village-agents",
  requireAuth("village-agents:write"),
  async (req, res, next) => {
    try {
      const body = villageAgentSchema.parse(req.body);
      const { groupIds, programmeId, programmeIds, ...agentInput } = body;
      // Both spellings land in one list, so an older caller sending a single
      // `programmeId` keeps working while the console sends the set.
      const wantedProgrammes = [...new Set([...(programmeIds ?? []), ...(programmeId ? [programmeId] : [])])];
      for (const id of wantedProgrammes) {
        await assertProgrammeWriteScope(req.user, id);
      }
      const assignmentIds = await validateAgentGroupAssignment({
        user: req.user,
        groupIds,
        caseloadLimit: agentInput.caseloadLimit
      });

      const agent = await prisma.$transaction(async (tx) => {
        const created = await tx.villageAgent.create({ data: agentInput });

        // The partner comes back from the assignment rather than being sent:
        // it is whichever partner owns the programmes, and the service refuses
        // a set that spans more than one.
        const { partnerId } = await setAgentProgrammes(tx, {
          agentId: created.id,
          programmeIds: wantedProgrammes
        });
        if (partnerId) {
          await tx.villageAgent.update({ where: { id: created.id }, data: { partnerId } });
        }

        await setAgentGroups(tx, created.id, assignmentIds);

        return tx.villageAgent.findUniqueOrThrow({
          where: { id: created.id },
          include: villageAgentInclude
        });
      });

      await appendAuditEvent({
        actorUserId: req.user?.id,
        entityType: "VILLAGE_AGENT",
        entityId: agent.id,
        type: "VA_CREATED",
        payload: agent
      });

      ok(res.status(201), agent);
    } catch (error) {
      next(error);
    }
  }
);

router.patch(
  "/village-agents/:id",
  requireAuth("village-agents:write"),
  async (req, res, next) => {
    try {
      const agentId = z.string().parse(req.params.id);
      const body = villageAgentUpdateSchema.parse(req.body);
      const existing = await prisma.villageAgent.findFirst({
        where: {
          AND: [{ id: agentId }, villageAgentScopeForUser(req.user)]
        },
        select: {
          id: true,
          caseloadLimit: true
        }
      });

      if (!existing) {
        throw new ApiHttpError(404, "VILLAGE_AGENT_NOT_FOUND", "VA / CBT does not exist or is outside this account.");
      }

      // `undefined` means "leave the programmes alone"; a list (including an
      // empty one) replaces them wholesale.
      const wantedProgrammes =
        body.programmeIds !== undefined
          ? body.programmeIds
          : body.programmeId !== undefined
            ? body.programmeId
              ? [body.programmeId]
              : []
            : undefined;
      for (const id of wantedProgrammes ?? []) {
        await assertProgrammeWriteScope(req.user, id);
      }
      const nextCaseloadLimit = body.caseloadLimit ?? existing.caseloadLimit;
      const assignmentIds =
        body.groupIds === undefined
          ? undefined
          : await validateAgentGroupAssignment({
              user: req.user,
              groupIds: body.groupIds,
              caseloadLimit: nextCaseloadLimit
            });

      const updated = await prisma.$transaction(async (tx) => {
        await tx.villageAgent.update({
          where: { id: existing.id },
          data: {
            name: body.name,
            phone: body.phone,
            email: body.email === undefined ? undefined : body.email,
            gender: body.gender === undefined ? undefined : body.gender,
            projectOfficer: body.projectOfficer === undefined ? undefined : body.projectOfficer,
            county: body.county === undefined ? undefined : body.county,
            location: body.location === undefined ? undefined : body.location,
            feedback: body.feedback === undefined ? undefined : body.feedback,
            status: body.status,
            digitalLiteracyScore: body.digitalLiteracyScore,
            caseloadLimit: body.caseloadLimit
          }
        });

        if (wantedProgrammes !== undefined) {
          const { partnerId } = await setAgentProgrammes(tx, {
            agentId: existing.id,
            programmeIds: wantedProgrammes
          });
          await tx.villageAgent.update({
            where: { id: existing.id },
            // Detaching every programme leaves the partner in place: an agent
            // between assignments still works for whoever engaged them.
            data: { partnerId: partnerId ?? undefined }
          });
        }

        if (assignmentIds) {
          await setAgentGroups(tx, existing.id, assignmentIds);
        }

        return tx.villageAgent.findUniqueOrThrow({
          where: { id: existing.id },
          include: villageAgentInclude
        });
      });

      await appendAuditEvent({
        actorUserId: req.user?.id,
        entityType: "VILLAGE_AGENT",
        entityId: updated.id,
        type: "VA_UPDATED",
        payload: {
          action: "UPDATED",
          agentId: updated.id,
          groupIds: updated.groups.map((group) => group.id)
        }
      });

      ok(res, updated);
    } catch (error) {
      next(error);
    }
  }
);

export { router as adminRouter };
