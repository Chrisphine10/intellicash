// Scenario 6 - meetings start only when a person starts them; the schedule is
// for reminders.
//
// Seeds a group with a login, three members and a weekly schedule, and four
// meetings that between them cover every case the phone and the console must
// handle without ever opening a meeting on their own:
//
//   Held on a phone      45 days ago, SCHEDULED with records (an old phone never
//                        reported its start) - must come back CLOSED on a phone.
//   Sealed               20 days ago, SEALED with records.
//   Missed               2 days ago, SCHEDULED, nothing recorded - "Didn't happen?"
//   Planned for today    later today - an official starting a meeting today on
//                        the phone must take THIS one over, not make a second.
//
// Usage:  node qa/06-meeting-schedule.mjs seed    -> prints the phone login
//         node qa/06-meeting-schedule.mjs check   -> meeting statuses + reminders on the server

import { api, daysAgo, db, DEMO_PASSWORD, login, requestId, uniq, world, saveWorld } from "./lib.mjs";

const mode = process.argv[2] ?? "seed";

if (mode === "seed") {
  const run = uniq();
  const admin = await login("admin@intellicash.co.ke", DEMO_PASSWORD);
  const programmeId = (await api(admin.cookie, "GET", "/programmes")).data[0].id;

  const phone = `07${String(run).padStart(5, "0").slice(-5)}600`;
  const password = "QaMeetings#2026-x";
  const group = (
    await api(admin.cookie, "POST", "/groups", {
      name: `Upendo Meeting Circle ${run}`, code: `QA-M-${run}`, county: "Kisumu", phase: "INTENSIVE", programmeIds: [programmeId]
    })
  ).data;
  await api(admin.cookie, "POST", "/users", { name: `Upendo Login ${run}`, email: `qa.meet.${run}@intellicash.test`, phone, password, role: "GROUP_ACCOUNT", groupId: group.id });
  const { cookie } = await login(phone, password);

  const members = {};
  for (const [i, name] of ["Akinyi Otieno", "Wafula Simiyu", "Njeri Kamau"].entries()) {
    const r = await api(cookie, "POST", `/groups/${group.id}/members`, { fullName: name, phone: `07${String(run).padStart(5, "0").slice(-5)}${String(610 + i).padStart(3, "0")}` });
    members[name.split(" ")[0]] = r.data.id;
  }

  // Meets every week on today's weekday and the day after, at 15:00.
  const nairobiNow = new Date(Date.now() + 3 * 3600_000);
  const isoToday = ((nairobiNow.getUTCDay() + 6) % 7) + 1;
  const days = [isoToday, (isoToday % 7) + 1];
  const schedule = await api(cookie, "PUT", `/groups/${group.id}/meeting-schedule`, { frequency: "WEEKLY", days, time: "15:00" });

  const meeting = async (title, at) => (await api(cookie, "POST", `/groups/${group.id}/meetings`, { title, scheduledAt: at.toISOString() })).data.id;
  const post = (meetingId, memberName, type, amountCents, label) =>
    api(cookie, "POST", `/groups/${group.id}/meetings/${meetingId}/ledger/batch`, {
      entries: [{ memberId: members[memberName], type, amountCents, clientRequestId: requestId(`${label}-${memberName}`) }]
    });

  const heldOnPhone = await meeting("Held on an old phone", daysAgo(45));
  await post(heldOnPhone, "Akinyi", "SHARE_PURCHASE", 200_000, "a1");
  await post(heldOnPhone, "Wafula", "SHARE_PURCHASE", 100_000, "a2");

  const sealed = await meeting("Sealed meeting", daysAgo(20));
  await post(sealed, "Njeri", "SHARE_PURCHASE", 150_000, "b1");
  await db.meeting.update({ where: { id: sealed }, data: { status: "SEALED", openedAt: daysAgo(20), closedAt: daysAgo(20) } });

  const missed = await meeting("Missed meeting", daysAgo(2));

  // Later today, but never in the past: at least 20 minutes from now.
  const todayAt = new Date(Math.max(Date.now() + 20 * 60_000, Date.now()));
  const plannedToday = await meeting("Planned for today", todayAt);

  await db.cycle.updateMany({ where: { groupId: group.id }, data: { startedAt: daysAgo(60) } });

  Object.assign(world, { meetingGroup: { id: group.id, name: group.name, phone, password, members, heldOnPhone, sealed, missed, plannedToday } });
  saveWorld();
  console.log(JSON.stringify({ group: group.name, login: phone, password, groupId: group.id, schedule: schedule.data, plannedTodayAt: todayAt.toISOString() }, null, 2));
} else {
  const { meetingGroup } = world;
  const meetings = await db.meeting.findMany({
    where: { groupId: meetingGroup.id },
    orderBy: { scheduledAt: "asc" },
    select: { id: true, title: true, status: true, source: true, scheduledAt: true, openedAt: true, closedAt: true, cancelReason: true, _count: { select: { attendance: true, ledgerEntries: true, reminders: true } } }
  });
  const group = await db.group.findUniqueOrThrow({ where: { id: meetingGroup.id }, select: { meetingFrequency: true, meetingDays: true, meetingTime: true, meetingDay: true, remindersEnabled: true } });
  console.log("schedule", JSON.stringify(group));
  for (const m of meetings) {
    console.log(`  ${m.scheduledAt.toISOString()}  ${m.status.padEnd(12)} ${m.source.padEnd(13)} ${m.title}  att=${m._count.attendance} entries=${m._count.ledgerEntries} reminders=${m._count.reminders}${m.cancelReason ? `  cancelled: ${m.cancelReason}` : ""}${m.id === meetingGroup.plannedToday ? "  <- planned today" : ""}`);
  }
  const reminders = await db.meetingReminder.findMany({ where: { meeting: { groupId: meetingGroup.id } }, select: { kind: true, sentAt: true, recipients: true, meeting: { select: { title: true } } } });
  console.log("reminders", JSON.stringify(reminders));
  const sms = await db.smsBroadcast.findMany({ where: { kind: "MEETING_REMINDER", targetGroupId: meetingGroup.id }, select: { status: true, recipientCount: true, message: true } });
  console.log("reminder sms", JSON.stringify(sms));
}
await db.$disconnect();
