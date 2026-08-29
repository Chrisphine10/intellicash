/**
 * Every system notification the platform raises, and what it costs to text.
 *
 * A named list rather than free-form strings, for two reasons: the settings
 * page needs something to render, and a notification whose type nobody
 * declared would otherwise appear in no list and be impossible to switch off.
 *
 * Pure - no Prisma, no clock. The wording of an individual message is composed
 * from the title and body the caller already wrote for the console bell, so
 * there is exactly one copy of each sentence.
 */

export const NOTIFICATION_TYPES = [
  "GROUP_JOIN_REQUESTED",
  "GROUP_JOIN_APPROVED",
  "GROUP_JOIN_REJECTED",
  "MEETING_ACTIVE",
  "STORE_REQUEST_SUBMITTED",
  "STORE_REQUEST_UPDATED",
  "STORE_REPAYMENT_POSTED",
  /** Anything raised without a declared type. Kept so nothing is unlistable. */
  "INFO"
] as const;

export type NotificationType = (typeof NOTIFICATION_TYPES)[number];

export interface NotificationTypeInfo {
  type: NotificationType;
  label: string;
  /** Who receives it, in the words an operator would use. */
  audience: string;
  /** How often it fires, so the cost of leaving it on is legible. */
  volume: string;
}

export const NOTIFICATION_CATALOGUE: NotificationTypeInfo[] = [
  {
    type: "GROUP_JOIN_REQUESTED",
    label: "Someone asks to join a group",
    audience: "The group's own account",
    volume: "One text per request, to each group login."
  },
  {
    type: "GROUP_JOIN_APPROVED",
    label: "A join request is approved",
    audience: "The person who asked",
    volume: "One text per approval."
  },
  {
    type: "GROUP_JOIN_REJECTED",
    label: "A join request is declined",
    audience: "The person who asked",
    volume: "One text per rejection."
  },
  {
    type: "MEETING_ACTIVE",
    label: "A meeting opens",
    audience: "Every member and group login in that group",
    volume: "The heaviest one: a 30-member group is 30 texts every meeting."
  },
  {
    type: "STORE_REQUEST_SUBMITTED",
    label: "A store request is submitted",
    audience: "Whoever submitted it",
    volume: "One text per request. They are usually still looking at the screen."
  },
  {
    type: "STORE_REQUEST_UPDATED",
    label: "A store request changes status",
    audience: "Whoever submitted it",
    volume: "One text per status, financier or repayment change."
  },
  {
    type: "STORE_REPAYMENT_POSTED",
    label: "A store repayment is recorded",
    audience: "Whoever submitted the request",
    volume: "One text per repayment."
  },
  {
    type: "INFO",
    label: "Anything else",
    audience: "Whoever the notification names",
    volume: "Notifications raised without a declared type."
  }
];

export function isNotificationType(value: string): value is NotificationType {
  return (NOTIFICATION_TYPES as readonly string[]).includes(value);
}

/**
 * The text of a system notification, from the title and body the console bell
 * already shows.
 *
 * Both halves, because a body is not always self-contained: "Your savings are
 * now in your passbook" says nothing about which group without its title. The
 * title is dropped only when the body already opens with it, which would
 * otherwise read as a stutter.
 *
 * No link and no "click here": an SMS has nowhere to click, and the message has
 * to stand on its own.
 */
export function buildSystemNotificationSms(title: string, body: string) {
  const head = title.trim().replace(/[\s.:!]+$/, "");
  const tail = body.trim();

  if (!head) return tail;
  if (!tail) return `${head}.`;
  if (tail.toLowerCase().startsWith(head.toLowerCase())) return tail;

  return `${head}: ${tail}`;
}
