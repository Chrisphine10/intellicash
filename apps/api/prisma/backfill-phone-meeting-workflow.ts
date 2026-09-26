/**
 * Mark the workflow of meetings held on a phone as done.
 *
 *   npx tsx prisma/backfill-phone-meeting-workflow.ts            # dry run, writes nothing
 *   npx tsx prisma/backfill-phone-meeting-workflow.ts --commit   # writes
 *
 * DRY RUN IS THE DEFAULT.
 *
 * Until phones sent their close with the meeting's details (26 Sep 2026), a
 * meeting closed on a phone reached the console as SEALED with every one of
 * the eight workflow steps still PENDING (or no step rows at all), and its
 * unlock "Pending" - so the meetings page read as if nothing had happened.
 * The phone ran its own steps; the eight console steps simply never applied.
 *
 * This records that, nothing more: for a SEALED meeting that no official
 * sealed on the console (sealedByMemberId is null), the missing step rows are
 * created and every step is marked COMPLETED at the meeting's close, and an
 * unlock still "PENDING" with no keys on record becomes NOT_RECORDED_ON_PHONE
 * (the phone's PIN holders were never sent, and inventing them would be
 * worse). No money moves; attendance and ledger are untouched.
 * Idempotent: a second run finds nothing to do.
 */
import { meetingStepLabels, meetingSteps, type MeetingStep } from "@intellicash/shared";
import { prisma } from "../src/lib/prisma";
import { appendAuditEvent } from "../src/services/audit-service";

const COMMIT = process.argv.includes("--commit");

async function main() {
  const candidates = await prisma.meeting.findMany({
    where: {
      status: "SEALED",
      sealedByMemberId: null,
      OR: [
        { steps: { some: { status: { not: "COMPLETED" } } } },
        { steps: { none: {} } },
        { unlockStatus: "PENDING", keySubmissions: { none: {} } }
      ]
    },
    select: {
      id: true,
      groupId: true,
      title: true,
      scheduledAt: true,
      closedAt: true,
      unlockStatus: true,
      _count: { select: { steps: true, keySubmissions: true } }
    },
    orderBy: { scheduledAt: "asc" }
  });

  console.log(`${COMMIT ? "COMMIT" : "DRY RUN"}: ${candidates.length} sealed phone meeting(s) with an unfinished workflow`);

  let done = 0;
  for (const meeting of candidates) {
    const at = meeting.closedAt ?? meeting.scheduledAt;
    console.log(
      `  ${meeting.scheduledAt.toISOString().slice(0, 10)}  ${meeting.title}  steps:${meeting._count.steps} keys:${meeting._count.keySubmissions} unlock:${meeting.unlockStatus}`
    );
    if (!COMMIT) continue;

    await prisma.$transaction(async (tx) => {
      for (const step of meetingSteps) {
        await tx.meetingStepRecord.upsert({
          where: { meetingId_step: { meetingId: meeting.id, step } },
          create: { meetingId: meeting.id, step, name: meetingStepLabels[step as MeetingStep], status: "COMPLETED", completedAt: at },
          update: {}
        });
      }
      await tx.meetingStepRecord.updateMany({
        where: { meetingId: meeting.id, status: { not: "COMPLETED" } },
        data: { status: "COMPLETED", completedAt: at }
      });
      if (meeting.unlockStatus === "PENDING" && meeting._count.keySubmissions === 0) {
        await tx.meeting.update({ where: { id: meeting.id }, data: { unlockStatus: "NOT_RECORDED_ON_PHONE" } });
      }
    });
    await appendAuditEvent({
      entityType: "MEETING",
      entityId: meeting.id,
      type: "MEETING_WORKFLOW_BACKFILLED",
      payload: { groupId: meeting.groupId, meetingId: meeting.id, at: at.toISOString(), backfill: true }
    });
    done += 1;
  }

  if (COMMIT) console.log(`completed ${done}`);
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(() => prisma.$disconnect());
