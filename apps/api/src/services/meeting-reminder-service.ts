import { Prisma } from "@prisma/client";
import {
  PLANNING_HORIZON_DAYS,
  dueReminder,
  meetingReminderText,
  nairobiDayBounds,
  nextMeetingSlot,
  readMeetingSchedule
} from "../domain/meeting-schedule";
import { env } from "../config/env";
import { prisma } from "../lib/prisma";
import { ensureActiveCycle } from "./cycle-service";
import { createNotifications, notificationSmsEnabled } from "./notification-service";
import { dispatchSms, type OutboundSmsRecipient } from "./outbound-sms-service";
import { normalizeSmsPhone } from "./sms-service";

/**
 * Meeting reminders: the only thing a meeting schedule is for.
 *
 * Two jobs, both run by a timer, and neither ever changes a meeting's status:
 *
 * - planUpcomingMeetings puts a group's next meeting day on the calendar as a
 *   SCHEDULED meeting (source AUTO_SCHEDULE), so there is something to remind
 *   people about. It only creates. Starting the meeting is an official's job.
 * - sendDueReminders texts and notifies members the day before and two hours
 *   before each scheduled meeting, each reminder once.
 *
 * A scheduled meeting whose time passes stays SCHEDULED. The console and the
 * phone show it as "not started" and ask an official to start or cancel it.
 */

const DAY_MS = 24 * 60 * 60 * 1000;

interface Dependencies {
  fetch?: typeof fetch;
  networkEnabled?: boolean;
}

/** Creates the next scheduled meeting for each group that has a schedule. Returns how many were made. */
export async function planUpcomingMeetings(now: Date = new Date()): Promise<number> {
  const groups = await prisma.group.findMany({
    where: {
      remindersEnabled: true,
      isDemo: false,
      meetingFrequency: { not: null },
      meetingDays: { not: null },
      meetingTime: { not: null }
    },
    select: { id: true, meetingFrequency: true, meetingDays: true, meetingTime: true }
  });

  let created = 0;
  for (const group of groups) {
    const schedule = readMeetingSchedule(group);
    if (!schedule) continue;

    // Fortnightly groups count from the last meeting they actually had.
    const last = await prisma.meeting.findFirst({
      where: { groupId: group.id, status: { not: "CANCELLED" }, scheduledAt: { lte: now } },
      orderBy: { scheduledAt: "desc" },
      select: { scheduledAt: true }
    });
    // The first meeting day in the window that nothing covers yet. Any meeting
    // on a day - scheduled by a person, already held, or cancelled by an
    // official - means the day is spoken for; a cancelled day in particular
    // must not come back as a fresh reminder. A day that is taken does not
    // stop the search: the next meeting day still needs its reminders.
    let slot: Date | null = null;
    let cursor = now;
    while (true) {
      const candidate = nextMeetingSlot(schedule, cursor, last?.scheduledAt ?? null);
      if (!candidate || candidate.getTime() - now.getTime() > PLANNING_HORIZON_DAYS * DAY_MS) break;
      const { start, end } = nairobiDayBounds(candidate);
      const taken = await prisma.meeting.count({
        where: { groupId: group.id, scheduledAt: { gte: start, lt: end } }
      });
      if (taken === 0) {
        slot = candidate;
        break;
      }
      cursor = end;
    }
    if (!slot) continue;
    const plannedAt = slot;

    await prisma.$transaction(async (tx) => {
      const cycle = await ensureActiveCycle(tx, group.id);
      await tx.meeting.create({
        data: {
          groupId: group.id,
          cycleId: cycle.id,
          title: `Group meeting ${plannedAt.toLocaleDateString("en-KE", {
            weekday: "long",
            day: "numeric",
            month: "short",
            timeZone: "Africa/Nairobi"
          })}`,
          status: "SCHEDULED",
          source: "AUTO_SCHEDULE",
          scheduledAt: plannedAt
        }
      });
    });
    created += 1;
  }
  return created;
}

export interface ReminderRunResult {
  reminders: number;
  notifications: number;
  sms: number;
}

/** Sends every reminder that is due and has not been sent. */
export async function sendDueReminders(
  now: Date = new Date(),
  dependencies: Dependencies = {}
): Promise<ReminderRunResult> {
  const result: ReminderRunResult = { reminders: 0, notifications: 0, sms: 0 };

  const meetings = await prisma.meeting.findMany({
    where: {
      status: "SCHEDULED",
      scheduledAt: { gt: now, lte: new Date(now.getTime() + DAY_MS) },
      group: { remindersEnabled: true, isDemo: false }
    },
    select: {
      id: true,
      scheduledAt: true,
      group: { select: { id: true, name: true, location: true } }
    }
  });

  const smsOn = await notificationSmsEnabled("MEETING_REMINDER");

  for (const meeting of meetings) {
    const kind = dueReminder(meeting.scheduledAt, now);
    if (!kind) continue;

    // Claim first. The unique key means a second claim - a second process, a
    // restart mid-run - fails here and sends nothing.
    let reminderId: string;
    try {
      const claimed = await prisma.meetingReminder.create({
        data: { meetingId: meeting.id, kind, scheduledFor: meeting.scheduledAt }
      });
      reminderId = claimed.id;
    } catch (error) {
      if (error instanceof Prisma.PrismaClientKnownRequestError && error.code === "P2002") continue;
      throw error;
    }

    const text = meetingReminderText({
      groupName: meeting.group.name,
      scheduledAt: meeting.scheduledAt,
      kind,
      location: meeting.group.location
    });

    const users = await prisma.user.findMany({
      where: { groupId: meeting.group.id, role: { in: ["GROUP_ACCOUNT", "MEMBER"] }, status: "ACTIVE" },
      select: { id: true, name: true, phone: true, member: { select: { id: true, phone: true } } }
    });
    // The bell only. The text goes out below, once per handset, to members
    // with a login and members without one alike.
    await createNotifications(
      users.map((user) => ({
        userId: user.id,
        title: kind === "H2" ? "Meeting today" : "Meeting tomorrow",
        body: text,
        type: "MEETING_REMINDER",
        href: "/dashboard/meetings",
        sms: false
      }))
    );
    result.notifications += users.length;

    let texted = 0;
    if (smsOn) {
      const members = await prisma.member.findMany({
        where: { groupId: meeting.group.id, status: "ACTIVE" },
        select: { id: true, fullName: true, phone: true }
      });
      const seen = new Set<string>();
      const recipients: OutboundSmsRecipient[] = [];
      const add = (memberId: string | null, name: string, phone: string | null | undefined) => {
        const raw = phone?.trim() ?? "";
        const key = raw ? normalizeSmsPhone(raw) : "";
        if (!key || seen.has(key)) return;
        seen.add(key);
        recipients.push({ memberId, memberName: name, phone: raw, message: text });
      };
      for (const member of members) add(member.id, member.fullName, member.phone);
      for (const user of users) add(user.member?.id ?? null, user.name, user.phone || user.member?.phone);

      if (recipients.length > 0) {
        const sent = await dispatchSms(
          {
            kind: "MEETING_REMINDER",
            groupId: meeting.group.id,
            meetingId: meeting.id,
            label: `meeting reminder (${kind === "H2" ? "2 hours" : "1 day"}) - ${recipients.length} recipient(s)`,
            recipients
          },
          dependencies
        );
        texted = sent.attempted;
      }
    }
    result.sms += texted;

    await prisma.meetingReminder.update({
      where: { id: reminderId },
      data: { sentAt: new Date(), recipients: texted }
    });
    result.reminders += 1;
  }

  return result;
}

export async function runMeetingReminders(now: Date = new Date(), dependencies: Dependencies = {}) {
  const planned = await planUpcomingMeetings(now);
  const sent = await sendDueReminders(now, dependencies);
  return { planned, ...sent };
}

const INTERVAL_MS = 5 * 60 * 1000;

/**
 * Starts the reminder loop. Returns a function that stops it.
 *
 * One API process runs on the server, so one loop runs; the reminder rows
 * would still stop a double send if that ever changed. The timer is unref'd
 * so it never holds the process open on shutdown.
 */
export function startMeetingReminderLoop(): () => void {
  if (!env.ENABLE_MEETING_REMINDERS) return () => undefined;

  let running = false;
  const tick = async () => {
    if (running) return;
    running = true;
    try {
      const outcome = await runMeetingReminders();
      if (outcome.planned > 0 || outcome.reminders > 0) {
        console.log(
          `Meeting reminders: planned ${outcome.planned} meeting(s), sent ${outcome.reminders} reminder(s), ${outcome.sms} text(s).`
        );
      }
    } catch (error) {
      console.error("Meeting reminder run failed", error);
    } finally {
      running = false;
    }
  };

  const first = setTimeout(tick, 30_000);
  const timer = setInterval(tick, INTERVAL_MS);
  first.unref();
  timer.unref();
  return () => {
    clearTimeout(first);
    clearInterval(timer);
  };
}
