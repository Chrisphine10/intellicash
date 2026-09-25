/**
 * Close the meetings that were held on a phone but never left SCHEDULED.
 *
 *   npx tsx prisma/backfill-phone-meeting-status.ts            # dry run, writes nothing
 *   npx tsx prisma/backfill-phone-meeting-status.ts --commit   # writes
 *
 * DRY RUN IS THE DEFAULT.
 *
 * Before phones reported a meeting's start and close (phone-lifecycle), every
 * meeting a treasurer held on a phone reached the server as SCHEDULED and
 * stayed that way, with its attendance and money inside it. Under the rule
 * that a scheduled meeting is only a plan, those now read as "not started" -
 * and an official would be asked to cancel a meeting that happened.
 *
 * This records what already happened, nothing more: a SCHEDULED meeting whose
 * date is more than 36 hours past AND which holds attendance or ledger entries
 * was held, so it is marked SEALED, dated by its own records, source PHONE.
 * No money moves. A meeting with no records is left alone - whether it
 * happened is an official's call (start or cancel), not this script's.
 *
 * The 36 hours keep away from a meeting a phone may still be holding today.
 * Idempotent: a second run finds nothing to do.
 */
import { prisma } from "../src/lib/prisma";
import { appendAuditEvent } from "../src/services/audit-service";

const COMMIT = process.argv.includes("--commit");
const HELD_BEFORE = new Date(Date.now() - 36 * 60 * 60 * 1000);

async function main() {
  const candidates = await prisma.meeting.findMany({
    where: {
      status: "SCHEDULED",
      scheduledAt: { lt: HELD_BEFORE },
      OR: [{ attendance: { some: {} } }, { ledgerEntries: { some: {} } }]
    },
    select: {
      id: true,
      groupId: true,
      title: true,
      scheduledAt: true,
      attendance: { select: { recordedAt: true }, orderBy: { recordedAt: "desc" }, take: 1 },
      ledgerEntries: { select: { createdAt: true }, orderBy: { createdAt: "desc" }, take: 1 }
    }
  });

  console.log(`${COMMIT ? "COMMIT" : "DRY RUN"}: ${candidates.length} held meeting(s) still marked SCHEDULED`);

  let closed = 0;
  for (const meeting of candidates) {
    // Closed on the meeting's own day. A phone sends its records when it next
    // has signal - days or months later - so a record's time is when it
    // SYNCED, not when the meeting ended. Only a record from the meeting day
    // itself is taken as the close.
    const lastRecord = [meeting.attendance[0]?.recordedAt, meeting.ledgerEntries[0]?.createdAt]
      .filter((d): d is Date => Boolean(d))
      .sort((a, b) => b.getTime() - a.getTime())[0];
    const sameDay =
      lastRecord &&
      lastRecord > meeting.scheduledAt &&
      lastRecord.getTime() - meeting.scheduledAt.getTime() < 24 * 60 * 60 * 1000;
    const closedAt = sameDay ? lastRecord : meeting.scheduledAt;

    console.log(`  ${meeting.scheduledAt.toISOString().slice(0, 10)}  ${meeting.title}  -> SEALED (closed ${closedAt.toISOString()})`);
    if (!COMMIT) continue;

    await prisma.meeting.update({
      where: { id: meeting.id },
      data: { status: "SEALED", source: "PHONE", openedAt: meeting.scheduledAt, closedAt }
    });
    await appendAuditEvent({
      entityType: "MEETING",
      entityId: meeting.id,
      type: "MEETING_CLOSED_ON_PHONE",
      payload: { groupId: meeting.groupId, meetingId: meeting.id, at: closedAt.toISOString(), backfill: true }
    });
    closed += 1;
  }

  if (COMMIT) console.log(`closed ${closed}`);
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(() => prisma.$disconnect());
