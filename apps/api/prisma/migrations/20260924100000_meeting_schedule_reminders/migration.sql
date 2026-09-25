-- Meetings start only when a person starts them. A scheduled meeting exists so
-- members can be reminded (SMS + notification) - nothing here opens anything.
--
-- Meeting.source says where a meeting came from; existing rows are MANUAL.
-- A scheduled meeting that did not happen can now be cancelled by an official.
ALTER TABLE "Meeting" ADD COLUMN "source" TEXT NOT NULL DEFAULT 'MANUAL';
ALTER TABLE "Meeting" ADD COLUMN "cancelledAt" DATETIME;
ALTER TABLE "Meeting" ADD COLUMN "cancelledByUserId" TEXT;
ALTER TABLE "Meeting" ADD COLUMN "cancelReason" TEXT;
CREATE INDEX "Meeting_status_scheduledAt_idx" ON "Meeting"("status", "scheduledAt");

-- A structured schedule for the reminder planner. All nullable: a group with
-- no schedule simply gets no planned meetings.
ALTER TABLE "Group" ADD COLUMN "meetingFrequency" TEXT;
ALTER TABLE "Group" ADD COLUMN "meetingDays" TEXT;
ALTER TABLE "Group" ADD COLUMN "meetingTime" TEXT;
ALTER TABLE "Group" ADD COLUMN "remindersEnabled" BOOLEAN NOT NULL DEFAULT true;

-- One row per reminder; the unique key is what stops a reminder going twice.
CREATE TABLE "MeetingReminder" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "meetingId" TEXT NOT NULL,
    "kind" TEXT NOT NULL,
    "scheduledFor" DATETIME NOT NULL,
    "sentAt" DATETIME,
    "recipients" INTEGER NOT NULL DEFAULT 0,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "MeetingReminder_meetingId_fkey" FOREIGN KEY ("meetingId") REFERENCES "Meeting" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE UNIQUE INDEX "MeetingReminder_meetingId_kind_scheduledFor_key" ON "MeetingReminder"("meetingId", "kind", "scheduledFor");
