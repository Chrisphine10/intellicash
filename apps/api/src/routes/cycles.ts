import { Router } from "express";
import { z } from "zod";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import { assertMayManageCycles, closeCycleAndOpenNext, listCycles } from "../services/cycle-service";

export const cyclesRouter = Router();

async function loadGroupInScope(user: AuthenticatedUser | undefined, groupId: string) {
  const group = await prisma.group.findFirst({
    where: { AND: [{ id: groupId }, scopeGroupWhere(user)] },
    select: { id: true, name: true, code: true, cycleNumber: true }
  });
  if (!group) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
  return group;
}

cyclesRouter.get("/groups/:groupId/cycles", requireAuth("groups:read"), async (req, res, next) => {
  try {
    const group = await loadGroupInScope(req.user, req.params.groupId as string);
    const cycles = await listCycles(group.id);

    ok(res, {
      group: { id: group.id, name: group.name, code: group.code },
      currentCycleNumber: group.cycleNumber,
      cycles,
      canManage:
        Boolean(req.user?.permissions.includes("groups:write")) ||
        (req.user?.role === "GROUP_ACCOUNT" && req.user?.groupId === group.id)
    });
  } catch (error) {
    next(error);
  }
});

const closeSchema = z.object({ notes: z.string().max(2000).optional() });

cyclesRouter.post(
  "/groups/:groupId/cycles/close",
  requireAuth("groups:read"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      assertMayManageCycles(req.user, group.id);

      const body = closeSchema.parse(req.body ?? {});
      const result = await closeCycleAndOpenNext(group.id, {
        closedByUserId: req.user?.id ?? null,
        notes: body.notes ?? null
      });

      ok(res, {
        ...result,
        message:
          `Cycle ${result.closed.number} is closed and its ${result.archivedMeetings} meeting(s) are now read-only. ` +
          `Cycle ${result.opened.number} is open — members, roles and balances carry over.`
      });
    } catch (error) {
      next(error);
    }
  }
);
