import { Router } from "express";
import { requireAuth } from "../middleware/auth";
import { ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { assertGroupAccess } from "../services/account-scope";
import { groupConsistency } from "../services/group-rules-service";

export const consistencyRouter = Router();

/**
 * GET /groups/:groupId/consistency — does this group's record hold together?
 *
 * Read-only. Fund balances against their ledger, loan records against their
 * disbursements, and entries that break the group's own rules (phone syncs
 * are never refused, so this is where such a slip shows up). Used by the QA
 * scripts before and after a release, and by anyone who can read the ledger.
 */
consistencyRouter.get("/groups/:groupId/consistency", requireAuth("ledger:read"), async (req, res, next) => {
  try {
    const groupId = String(req.params.groupId);
    await assertGroupAccess(req.user, groupId);
    ok(res, await groupConsistency(prisma, groupId));
  } catch (error) {
    next(error);
  }
});
