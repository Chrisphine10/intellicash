-- Meetings made through POST /groups/:id/meetings were never given a cycle, so
-- closing a cycle archived none of them and the closed-cycle guard did not
-- apply to them. New meetings are stamped in code; this stamps the ones that
-- already exist.
--
-- Each goes to the cycle that was current on the day it was scheduled: the
-- latest cycle of its group that had started by then. A meeting scheduled
-- before its group's first cycle began goes to the earliest cycle. Idempotent:
-- it only touches rows that still have no cycle, and does nothing for a group
-- with no cycle row at all (one is created just below).
-- A group that has never written money has no cycle row yet (the running code
-- creates it on first use). Create it now, with the same derived id the code
-- and the original cycle migration use, so no competing row can appear.
INSERT INTO "Cycle" ("id", "groupId", "number", "startedAt", "status", "createdAt", "updatedAt")
SELECT 'cyc_' || g."id" || '_' || g."cycleNumber",
       g."id",
       g."cycleNumber",
       g."createdAt",
       'ACTIVE',
       g."createdAt",
       g."createdAt"
FROM "Group" g
WHERE NOT EXISTS (SELECT 1 FROM "Cycle" c WHERE c."groupId" = g."id");

UPDATE "Meeting"
SET "cycleId" = (
  SELECT c."id" FROM "Cycle" c
  WHERE c."groupId" = "Meeting"."groupId"
    AND c."startedAt" <= "Meeting"."scheduledAt"
  ORDER BY c."number" DESC
  LIMIT 1
)
WHERE "cycleId" IS NULL
  AND EXISTS (
    SELECT 1 FROM "Cycle" c
    WHERE c."groupId" = "Meeting"."groupId" AND c."startedAt" <= "Meeting"."scheduledAt"
  );

UPDATE "Meeting"
SET "cycleId" = (
  SELECT c."id" FROM "Cycle" c
  WHERE c."groupId" = "Meeting"."groupId"
  ORDER BY c."number" ASC
  LIMIT 1
)
WHERE "cycleId" IS NULL
  AND EXISTS (SELECT 1 FROM "Cycle" c WHERE c."groupId" = "Meeting"."groupId");
