import { Router } from "express";
import { z } from "zod";

import { ok } from "../lib/http";
import { requireAuth } from "../middleware/auth";
import { assertGroupAccess } from "../services/account-scope";
import { appendAuditEvent } from "../services/audit-service";
import { linkGroupChampion } from "../services/group-champion-service";
import { dispatchAfterResponse, dispatchSms } from "../services/outbound-sms-service";

export const groupChampionRouter = Router();

const championSchema = z.object({
  championName: z.string().trim().max(120).optional(),
  phone: z.string().min(6).max(32)
});

/**
 * Give a group's digital champion access to the group's existing account.
 *
 * `members:write`, which field agents already hold, plus the caseload check —
 * an agent can do this for their own groups and no others. Admins can do it for
 * any group.
 *
 * Handing a phone the keys to a group's book is a big deal, so three things
 * happen every time: it is audited with who did it, it is refused if the number
 * already opens someone else's account, and the champion is TEXTED — so a
 * number entered by mistake reaches a real person who can say "that isn't me".
 */
groupChampionRouter.put("/groups/:id/champion", requireAuth("members:write"), async (req, res, next) => {
  try {
    const groupId = String(req.params.id ?? "");
    await assertGroupAccess(req.user, groupId);
    const body = championSchema.parse(req.body);

    const result = await linkGroupChampion({ groupId, championName: body.championName, phone: body.phone });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "GROUP",
      entityId: groupId,
      type: "GROUP_CHAMPION_LINKED",
      payload: {
        outcome: result.outcome,
        userId: result.userId,
        phone: result.phone,
        championName: body.championName ?? null,
        linkedOrphanName: result.linkedOrphanName ?? null
      }
    });

    if (result.outcome !== "ALREADY_LINKED") {
      dispatchAfterResponse(() =>
        dispatchSms({
          kind: "SYSTEM_NOTIFICATION",
          label: "Champion access",
          requestedByUserId: req.user?.id,
          groupId,
          recipients: [
            {
              memberName: body.championName || result.groupName,
              phone: result.phone,
              message: `You can now open ${result.groupName} on Intelli-Cash with this number. On the sign-in screen choose "Sign in with a code". If this is not you, tell your field officer.`
            }
          ]
        })
      );
    }

    ok(res, {
      outcome: result.outcome,
      groupId: result.groupId,
      groupName: result.groupName,
      phone: result.phone,
      linkedOrphanName: result.linkedOrphanName ?? null
    });
  } catch (error) {
    next(error);
  }
});
