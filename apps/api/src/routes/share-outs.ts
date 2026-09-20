import { Router } from "express";
import { requireAuth } from "../middleware/auth";
import { ok } from "../lib/http";
import { assertGroupAccess } from "../services/account-scope";
import { assertMayManageCycles } from "../services/cycle-service";
import { recordPhoneShareOut, shareOutRecordSchema } from "../services/share-out-record-service";

export const shareOutsRouter = Router();

/**
 * Records a share-out that was done on the group's phone, and closes the cycle
 * it ended - both or neither. See `recordPhoneShareOut` for what it refuses.
 *
 * Needs the same standing as closing a cycle from the console (a platform admin,
 * or the group's own account) on top of being allowed to write to the ledger: a
 * share-out ends a cycle, and a village agent may read a group but not end it.
 */
shareOutsRouter.post("/groups/:groupId/share-outs", requireAuth("ledger:write"), async (req, res, next) => {
  try {
    const groupId = req.params.groupId as string;
    await assertGroupAccess(req.user, groupId);
    assertMayManageCycles(req.user, groupId);

    const payload = shareOutRecordSchema.parse(req.body);
    const result = await recordPhoneShareOut(req.user?.id ?? null, groupId, payload);

    // 200 for a replay (nothing new was written), 201 for the first recording.
    ok(res.status(result.replayed ? 200 : 201), result);
  } catch (error) {
    next(error);
  }
});
