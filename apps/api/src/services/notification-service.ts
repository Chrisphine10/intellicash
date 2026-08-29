import { buildSystemNotificationSms } from "../domain/notification-catalogue";
import { prisma } from "../lib/prisma";
import { normalizeSmsPhone } from "./sms-service";
import { dispatchAfterResponse, dispatchSms } from "./outbound-sms-service";

/**
 * System notifications.
 *
 * Every notification that appears in the console bell is also texted, because
 * the people who most need to act on one are not sitting in front of the
 * console: a secretary approving a join request, a member whose meeting has
 * just opened, a shopkeeper whose credit was approved.
 *
 * This is the only seam. Wiring SMS in here rather than at the seven call sites
 * means a notification added later is texted without anybody remembering to,
 * which is the failure this codebase has repeatedly produced - capability
 * written, tested, and reachable from nothing.
 */

export interface NotificationInput {
  userId?: string | null;
  title: string;
  body: string;
  type?: string;
  href?: string | null;
  createdAt?: Date;
  /**
   * Set false to keep something in the bell only.
   *
   * Nothing uses it yet. It exists so a genuinely console-only notice does not
   * have to be smuggled past the seam by not going through this service.
   */
  sms?: boolean;
}

function normalizeNotification(input: NotificationInput & { userId: string }) {
  return {
    userId: input.userId,
    title: input.title,
    body: input.body,
    type: input.type ?? "INFO",
    href: input.href ?? null,
    createdAt: input.createdAt
  };
}

/** Whether a category is texted. A missing row means yes. */
export async function notificationSmsEnabled(type: string) {
  const setting = await prisma.notificationSmsSetting.findUnique({
    where: { type },
    select: { smsEnabled: true }
  });
  return setting?.smsEnabled ?? true;
}

interface Dependencies {
  fetch?: typeof fetch;
  networkEnabled?: boolean;
}

/**
 * Text the people a batch of notifications was addressed to.
 *
 * Exported for the tests, which need to await it. Production goes through
 * `dispatchAfterResponse` instead: a join approval must not wait on Bonga, and
 * opening a meeting in a 30-member group is 30 sequential provider calls.
 */
export async function sendNotificationSms(
  inputs: Array<NotificationInput & { userId: string }>,
  dependencies: Dependencies = {}
) {
  const wanted = inputs.filter((input) => input.sms !== false);
  if (wanted.length === 0) return null;

  const byType = new Map<string, typeof wanted>();
  for (const input of wanted) {
    const type = input.type ?? "INFO";
    byType.set(type, [...(byType.get(type) ?? []), input]);
  }

  const results = [];
  for (const [type, group] of byType) {
    if (!(await notificationSmsEnabled(type))) continue;

    const users = await prisma.user.findMany({
      where: { id: { in: [...new Set(group.map((input) => input.userId))] } },
      select: {
        id: true,
        name: true,
        phone: true,
        // A member login often has no phone of its own; the member row does.
        member: { select: { id: true, phone: true } }
      }
    });
    const userById = new Map(users.map((user) => [user.id, user]));

    // One handset, one message. Households share phones, and a person who
    // holds both a member login and the group login would otherwise be texted
    // the same meeting notice twice.
    const seenPhones = new Set<string>();
    const recipients = [];

    for (const input of group) {
      const user = userById.get(input.userId);
      if (!user) continue;

      const phone = user.phone?.trim() || user.member?.phone?.trim() || "";
      const key = phone ? normalizeSmsPhone(phone) : "";
      if (key && seenPhones.has(key)) continue;
      if (key) seenPhones.add(key);

      recipients.push({
        memberId: user.member?.id ?? null,
        memberName: user.name,
        phone,
        message: buildSystemNotificationSms(input.title, input.body)
      });
    }

    if (recipients.length === 0) continue;

    results.push(
      await dispatchSms(
        {
          kind: "SYSTEM_NOTIFICATION",
          label: `${type.replace(/_/g, " ").toLowerCase()} - ${recipients.length} recipient(s)`,
          recipients
        },
        dependencies
      )
    );
  }

  return results;
}

export async function createNotification(input: NotificationInput) {
  if (!input.userId) return null;

  const notification = await prisma.notification.create({
    data: normalizeNotification({ ...input, userId: input.userId })
  });

  dispatchAfterResponse(() => sendNotificationSms([{ ...input, userId: input.userId as string }]));

  return notification;
}

export async function createNotifications(inputs: NotificationInput[]) {
  const addressed = inputs.filter(
    (input): input is NotificationInput & { userId: string } => Boolean(input.userId)
  );

  if (addressed.length === 0) return { count: 0 };

  const result = await prisma.notification.createMany({
    data: addressed.map(normalizeNotification)
  });

  dispatchAfterResponse(() => sendNotificationSms(addressed));

  return result;
}
