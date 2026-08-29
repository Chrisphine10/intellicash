import { Router } from "express";
import { z } from "zod";
import { requireAuth } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import {
  NOTIFICATION_CATALOGUE,
  isNotificationType
} from "../domain/notification-catalogue";

const router = Router();

const notificationSelect = {
  id: true,
  title: true,
  body: true,
  type: true,
  href: true,
  readAt: true,
  createdAt: true
};

router.get("/notifications", requireAuth(), async (req, res, next) => {
  try {
    const notifications = await prisma.notification.findMany({
      where: { userId: req.user!.id },
      orderBy: { createdAt: "desc" },
      take: 40,
      select: notificationSelect
    });

    ok(res, notifications, {
      unreadCount: notifications.filter((notification) => !notification.readAt).length
    });
  } catch (error) {
    next(error);
  }
});

router.post("/notifications/read-all", requireAuth(), async (req, res, next) => {
  try {
    const result = await prisma.notification.updateMany({
      where: { userId: req.user!.id, readAt: null },
      data: { readAt: new Date() }
    });

    ok(res, { updated: result.count });
  } catch (error) {
    next(error);
  }
});

router.post("/notifications/:notificationId/read", requireAuth(), async (req, res, next) => {
  try {
    const notificationId = z.string().min(1).parse(req.params.notificationId);
    const notification = await prisma.notification.findFirst({
      where: { id: notificationId, userId: req.user!.id },
      select: { id: true }
    });

    if (!notification) {
      throw new ApiHttpError(404, "NOTIFICATION_NOT_FOUND", "Notification not found.");
    }

    const updated = await prisma.notification.update({
      where: { id: notification.id },
      data: { readAt: new Date() },
      select: notificationSelect
    });

    ok(res, updated);
  } catch (error) {
    next(error);
  }
});

/**
 * Which system notifications are also texted.
 *
 * Guarded by the integration permissions that already exist rather than a new
 * `notifications:*` pair. `ensureRolePermissionTemplates` upserts with
 * `update: {}`, so a newly invented permission never reaches the role rows
 * production already has and would 403 the whole page on deploy - the same
 * reason the group-policy route uses a role check instead of a new string.
 *
 * A category with no stored row is enabled, so the list is always complete
 * whether or not anyone has touched it.
 */
router.get("/notifications/sms-settings", requireAuth("integrations:read"), async (_req, res, next) => {
  try {
    const stored = await prisma.notificationSmsSetting.findMany();
    const byType = new Map(stored.map((row) => [row.type, row]));

    ok(res, {
      settings: NOTIFICATION_CATALOGUE.map((entry) => ({
        ...entry,
        smsEnabled: byType.get(entry.type)?.smsEnabled ?? true,
        // False means "running on the default", which a UI should say rather
        // than implying somebody chose it.
        configured: byType.has(entry.type),
        updatedAt: byType.get(entry.type)?.updatedAt?.toISOString() ?? null
      }))
    });
  } catch (error) {
    next(error);
  }
});

const smsSettingSchema = z.object({
  type: z.string().min(1),
  smsEnabled: z.boolean()
});

router.put("/notifications/sms-settings", requireAuth("integrations:write"), async (req, res, next) => {
  try {
    const payload = smsSettingSchema.parse(req.body);
    if (!isNotificationType(payload.type)) {
      throw new ApiHttpError(
        400,
        "UNKNOWN_NOTIFICATION_TYPE",
        "That is not a notification this platform raises."
      );
    }

    await prisma.notificationSmsSetting.upsert({
      where: { type: payload.type },
      create: {
        type: payload.type,
        smsEnabled: payload.smsEnabled,
        updatedByUserId: req.user?.id ?? null
      },
      update: { smsEnabled: payload.smsEnabled, updatedByUserId: req.user?.id ?? null }
    });

    ok(res, {
      type: payload.type,
      smsEnabled: payload.smsEnabled,
      message: payload.smsEnabled
        ? "This notification will be texted as well as shown in the console."
        : "This notification will now appear in the console only."
    });
  } catch (error) {
    next(error);
  }
});

export { router as notificationsRouter };
