import { Router } from "express";
import { requireAuth } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { assertGroupAccess } from "../services/account-scope";
import { buildRestoreBundle } from "../services/restore-bundle-service";

export const restoreBundleRouter = Router();

/**
 * A group's whole record book, for loading onto a new phone.
 *
 * It holds every member's money, so it is limited to the people who may already
 * see all of it: a platform admin, or the group's own account. A member, or an
 * agent with read access to the group, has the ordinary routes for what they are
 * meant to see - and no reason to rebuild the book on a handset.
 */
restoreBundleRouter.get("/groups/:groupId/restore-bundle", requireAuth("ledger:read"), async (req, res, next) => {
  try {
    const groupId = req.params.groupId as string;
    await assertGroupAccess(req.user, groupId);

    const user = req.user;
    const allowed =
      Boolean(user?.permissions.includes("groups:write")) ||
      (user?.role === "GROUP_ACCOUNT" && user.groupId === groupId);
    if (!allowed) {
      throw new ApiHttpError(
        403,
        "FORBIDDEN",
        "Only a platform admin or the group's own account can load its records onto a phone."
      );
    }

    ok(res, await buildRestoreBundle(groupId));
  } catch (error) {
    next(error);
  }
});
