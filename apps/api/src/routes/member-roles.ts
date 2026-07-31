import { Router } from "express";
import { z } from "zod";
import { memberRoles } from "@intellicash/shared";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import { ensureActiveCycle } from "../services/cycle-service";

export const memberRolesRouter = Router();

/**
 * Who holds which office, and who held it before.
 *
 * `Member.role` answers "now". Reassigning used to overwrite it, which quietly
 * rewrote the past — a meeting minuted last year would appear to have been
 * taken by whoever is secretary today. Assignments are ENDED, never edited, so
 * the record of who was responsible survives every reshuffle.
 */

/** Offices where only one member may hold the post at a time. */
const SINGLETON_ROLES = new Set(["CHAIRPERSON", "SECRETARY", "TREASURER"]);

async function loadGroupInScope(user: AuthenticatedUser | undefined, groupId: string) {
  const group = await prisma.group.findFirst({
    where: { AND: [{ id: groupId }, scopeGroupWhere(user)] },
    select: { id: true, name: true, code: true }
  });
  if (!group) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
  return group;
}

function assertMayAssign(user: AuthenticatedUser | undefined, groupId: string) {
  if (!user) throw new ApiHttpError(401, "UNAUTHENTICATED", "Authentication is required.");
  if (user.permissions.includes("groups:write")) return;
  if (user.role === "GROUP_ACCOUNT" && user.groupId === groupId) return;

  throw new ApiHttpError(
    403,
    "FORBIDDEN",
    "Only a platform admin or the group's own account may change officials."
  );
}

memberRolesRouter.get(
  "/groups/:groupId/role-assignments",
  requireAuth("members:read"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      const cycleId = typeof req.query.cycleId === "string" ? req.query.cycleId : undefined;

      const assignments = await prisma.memberRoleAssignment.findMany({
        where: { groupId: group.id, ...(cycleId ? { cycleId } : {}) },
        orderBy: [{ endedAt: "asc" }, { startedAt: "desc" }],
        include: { member: { select: { id: true, fullName: true } } }
      });

      ok(res, {
        group,
        // Split so a caller does not have to know that endedAt === null means
        // "in office" — the distinction decides who may sign a meeting.
        current: assignments
          .filter((a) => a.endedAt === null)
          .map((a) => ({ ...a, member: a.member })),
        history: assignments.filter((a) => a.endedAt !== null),
        canAssign:
          Boolean(req.user?.permissions.includes("groups:write")) ||
          (req.user?.role === "GROUP_ACCOUNT" && req.user?.groupId === group.id)
      });
    } catch (error) {
      next(error);
    }
  }
);

const assignSchema = z.object({
  memberId: z.string(),
  role: z.enum(memberRoles as unknown as [string, ...string[]]),
  note: z.string().trim().max(500).optional()
});

memberRolesRouter.post(
  "/groups/:groupId/role-assignments",
  requireAuth("members:read"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      assertMayAssign(req.user, group.id);
      const body = assignSchema.parse(req.body ?? {});

      const result = await prisma.$transaction(async (tx) => {
        const member = await tx.member.findFirst({
          where: { id: body.memberId, groupId: group.id },
          select: { id: true, fullName: true, role: true }
        });
        if (!member) {
          throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member is not in this group.");
        }

        const cycle = await ensureActiveCycle(tx, group.id);
        const now = new Date();
        let replaced: { fullName: string } | null = null;

        if (SINGLETON_ROLES.has(body.role)) {
          // End the incumbent rather than deleting them: the group needs to be
          // able to say who was secretary last March.
          const holders = await tx.memberRoleAssignment.findMany({
            where: { groupId: group.id, role: body.role, endedAt: null },
            include: { member: { select: { fullName: true } } }
          });
          for (const holder of holders) {
            if (holder.memberId === member.id) {
              throw new ApiHttpError(
                409,
                "ALREADY_HOLDS_ROLE",
                `${member.fullName} already holds this office.`
              );
            }
            await tx.memberRoleAssignment.update({
              where: { id: holder.id },
              data: { endedAt: now }
            });
            replaced = { fullName: holder.member.fullName };
          }

          // Keep Member.role in step for everything that still reads it.
          await tx.member.updateMany({
            where: { groupId: group.id, role: body.role, id: { not: member.id } },
            data: { role: "MEMBER" }
          });
        }

        const assignment = await tx.memberRoleAssignment.create({
          data: {
            groupId: group.id,
            memberId: member.id,
            cycleId: cycle.id,
            role: body.role,
            startedAt: now,
            assignedByUserId: req.user?.id ?? null,
            note: body.note ?? null
          }
        });

        await tx.member.update({ where: { id: member.id }, data: { role: body.role } });
        return { assignment, member, replaced };
      });

      ok(res.status(201), {
        assignment: result.assignment,
        message: result.replaced
          ? `${result.member.fullName} is now ${result.assignment.role.toLowerCase()}. ` +
            `${result.replaced.fullName}'s term is recorded as ended, not deleted.`
          : `${result.member.fullName} is now ${result.assignment.role.toLowerCase()}.`
      });
    } catch (error) {
      next(error);
    }
  }
);

memberRolesRouter.post(
  "/groups/:groupId/role-assignments/:assignmentId/end",
  requireAuth("members:read"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      assertMayAssign(req.user, group.id);

      const assignment = await prisma.memberRoleAssignment.findFirst({
        where: { id: req.params.assignmentId as string, groupId: group.id }
      });
      if (!assignment) {
        throw new ApiHttpError(404, "ASSIGNMENT_NOT_FOUND", "No such role assignment in this group.");
      }
      if (assignment.endedAt) {
        // Ending twice would move the date and misstate when the term closed.
        throw new ApiHttpError(409, "ALREADY_ENDED", "That term has already ended.");
      }

      await prisma.$transaction(async (tx) => {
        await tx.memberRoleAssignment.update({
          where: { id: assignment.id },
          data: { endedAt: new Date() }
        });
        await tx.member.update({ where: { id: assignment.memberId }, data: { role: "MEMBER" } });
      });

      ok(res, { message: "Term ended. The record of who held it stays in history." });
    } catch (error) {
      next(error);
    }
  }
);
