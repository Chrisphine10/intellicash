-- CreateTable
CREATE TABLE "Cycle" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "number" INTEGER NOT NULL,
    "startedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "closedAt" DATETIME,
    "status" TEXT NOT NULL DEFAULT 'ACTIVE',
    "closedByUserId" TEXT,
    "notes" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "Cycle_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- RedefineTables
PRAGMA defer_foreign_keys=ON;
PRAGMA foreign_keys=OFF;
CREATE TABLE "new_LedgerEntry" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "cycleId" TEXT,
    "memberId" TEXT,
    "meetingId" TEXT,
    "fundAccountId" TEXT,
    "type" TEXT NOT NULL,
    "amountCents" INTEGER NOT NULL,
    "currency" TEXT NOT NULL DEFAULT 'KES',
    "direction" TEXT NOT NULL,
    "description" TEXT NOT NULL,
    "signature" TEXT NOT NULL,
    "externalReference" TEXT,
    "clientRequestId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "LedgerEntry_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT "LedgerEntry_cycleId_fkey" FOREIGN KEY ("cycleId") REFERENCES "Cycle" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "LedgerEntry_memberId_fkey" FOREIGN KEY ("memberId") REFERENCES "Member" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "LedgerEntry_meetingId_fkey" FOREIGN KEY ("meetingId") REFERENCES "Meeting" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "LedgerEntry_fundAccountId_fkey" FOREIGN KEY ("fundAccountId") REFERENCES "FundAccount" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);
INSERT INTO "new_LedgerEntry" ("amountCents", "clientRequestId", "createdAt", "currency", "description", "direction", "externalReference", "fundAccountId", "groupId", "id", "meetingId", "memberId", "signature", "type") SELECT "amountCents", "clientRequestId", "createdAt", "currency", "description", "direction", "externalReference", "fundAccountId", "groupId", "id", "meetingId", "memberId", "signature", "type" FROM "LedgerEntry";
DROP TABLE "LedgerEntry";
ALTER TABLE "new_LedgerEntry" RENAME TO "LedgerEntry";
CREATE UNIQUE INDEX "LedgerEntry_clientRequestId_key" ON "LedgerEntry"("clientRequestId");
CREATE TABLE "new_Meeting" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "cycleId" TEXT,
    "title" TEXT NOT NULL,
    "status" TEXT NOT NULL,
    "scheduledAt" DATETIME NOT NULL,
    "openedAt" DATETIME,
    "closedAt" DATETIME,
    "unlockStatus" TEXT NOT NULL DEFAULT 'PENDING',
    "gpsCompliant" BOOLEAN NOT NULL DEFAULT false,
    "transactionTotal" INTEGER NOT NULL DEFAULT 0,
    "minutes" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "Meeting_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "Meeting_cycleId_fkey" FOREIGN KEY ("cycleId") REFERENCES "Cycle" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);
INSERT INTO "new_Meeting" ("closedAt", "createdAt", "gpsCompliant", "groupId", "id", "minutes", "openedAt", "scheduledAt", "status", "title", "transactionTotal", "unlockStatus", "updatedAt") SELECT "closedAt", "createdAt", "gpsCompliant", "groupId", "id", "minutes", "openedAt", "scheduledAt", "status", "title", "transactionTotal", "unlockStatus", "updatedAt" FROM "Meeting";
DROP TABLE "Meeting";
ALTER TABLE "new_Meeting" RENAME TO "Meeting";
PRAGMA foreign_keys=ON;
PRAGMA defer_foreign_keys=OFF;

-- CreateIndex
CREATE INDEX "Cycle_groupId_status_idx" ON "Cycle"("groupId", "status");

-- CreateIndex
CREATE UNIQUE INDEX "Cycle_groupId_number_key" ON "Cycle"("groupId", "number");


-- ---------------------------------------------------------------------------
-- Backfill. Every existing group gets one ACTIVE cycle carrying the number it
-- already had in Group.cycleNumber, and every existing meeting and ledger entry
-- is attached to it. Without this, all history would sit outside any cycle and
-- reports scoped by cycle would silently return nothing.
--
-- The id is derived rather than random so this is idempotent: re-running cannot
-- create a second cycle for the same group and number (also guarded by the
-- unique index on (groupId, number)).
--
-- Datetimes are copied from Group.createdAt rather than CURRENT_TIMESTAMP:
-- Prisma stores SQLite DateTime as INTEGER milliseconds, and CURRENT_TIMESTAMP
-- would write a TEXT value that Prisma then fails to read back.
-- ---------------------------------------------------------------------------
INSERT INTO "Cycle" ("id", "groupId", "number", "startedAt", "status", "createdAt", "updatedAt")
SELECT 'cyc_' || g."id" || '_' || g."cycleNumber",
       g."id",
       g."cycleNumber",
       g."createdAt",
       'ACTIVE',
       g."createdAt",
       g."createdAt"
FROM "Group" g;

UPDATE "Meeting"
SET "cycleId" = 'cyc_' || "groupId" || '_' ||
    (SELECT g."cycleNumber" FROM "Group" g WHERE g."id" = "Meeting"."groupId")
WHERE "cycleId" IS NULL;

UPDATE "LedgerEntry"
SET "cycleId" = 'cyc_' || "groupId" || '_' ||
    (SELECT g."cycleNumber" FROM "Group" g WHERE g."id" = "LedgerEntry"."groupId")
WHERE "cycleId" IS NULL;
