-- Which official closed the meeting.
--
-- A plain ADD COLUMN on a nullable field: SQLite does this in place, so the
-- Meeting table is NOT rebuilt and no existing meeting is rewritten.
--
-- Nullable on purpose. Meetings sealed before an official PIN was required
-- genuinely have no such proof; back-filling one would fabricate an approval
-- that never happened, which is exactly what this column exists to prevent.
ALTER TABLE "Meeting" ADD COLUMN "sealedByMemberId" TEXT;
