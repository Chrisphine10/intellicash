import { env } from "./config/env";
import { createApp } from "./app";
import { prisma } from "./lib/prisma";
import { assertDurableDatabase } from "./lib/storage-safety";
import { startMeetingReminderLoop } from "./services/meeting-reminder-service";

// Before accepting a single savings entry, make sure the database will still
// be here after the next deploy.
assertDurableDatabase();

const app = createApp();

const server = app.listen(env.API_PORT, () => {
  console.log(`Intellicash API listening on http://localhost:${env.API_PORT}`);
});

// Plans upcoming meetings from each group's schedule and sends their
// reminders. It never starts a meeting.
const stopMeetingReminders = startMeetingReminderLoop();

async function shutdown() {
  stopMeetingReminders();
  server.close(async () => {
    await prisma.$disconnect();
    process.exit(0);
  });
}

process.on("SIGINT", shutdown);
process.on("SIGTERM", shutdown);
