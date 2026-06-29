import { Router } from "express";
import { z } from "zod";
import { ApiHttpError, ok } from "../lib/http";
import { requireAuth } from "../middleware/auth";
import { createSmsBroadcast, listSmsBroadcasts } from "../services/admin-sms-service";

const router = Router();

const smsBroadcastSchema = z
  .object({
    targetType: z.enum(["GROUP", "MEMBER"]),
    groupId: z.string().optional(),
    memberId: z.string().optional(),
    message: z.string().trim().min(1).max(480),
    provider: z.enum(["BONGA_SMS", "AFRICAS_TALKING"]).optional()
  })
  .superRefine((body, context) => {
    if (body.targetType === "GROUP" && !body.groupId) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["groupId"],
        message: "Select a group."
      });
    }

    if (body.targetType === "MEMBER" && !body.memberId) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["memberId"],
        message: "Select a member."
      });
    }
  });

function assertAdmin(user: Express.Request["user"]) {
  if (user?.role !== "IWL_ADMIN") {
    throw new ApiHttpError(403, "ADMIN_REQUIRED", "Only IWL admins can send SMS broadcasts.");
  }
}

router.get("/sms/broadcasts", requireAuth("members:write"), async (req, res, next) => {
  try {
    assertAdmin(req.user);
    ok(res, await listSmsBroadcasts());
  } catch (error) {
    next(error);
  }
});

router.post("/sms/broadcasts", requireAuth("members:write"), async (req, res, next) => {
  try {
    assertAdmin(req.user);
    const payload = smsBroadcastSchema.parse(req.body);
    const broadcast = await createSmsBroadcast({
      ...payload,
      requestedByUserId: req.user?.id ?? null
    });

    ok(res.status(201), broadcast);
  } catch (error) {
    next(error);
  }
});

export { router as smsBroadcastsRouter };
