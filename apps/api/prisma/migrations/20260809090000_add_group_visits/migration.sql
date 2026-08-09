-- Field-agent group visits.
--
-- Three new tables and no change to any existing one, so nothing is rebuilt
-- and no current row is rewritten. Safe to apply to a live database.

-- The 4-digit PIN a group uses to attest that a visit happened on its premises.
--
-- Its own table rather than a column on Group so the hash cannot leak through
-- any of the places a Group is serialized — the agent's own caseload list among
-- them, which is read by exactly the people this PIN keeps honest.
CREATE TABLE "GroupVisitPin" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "pinHash" TEXT NOT NULL,
    "setByUserId" TEXT,
    "setAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    "failedCount" INTEGER NOT NULL DEFAULT 0,
    "lockedUntil" DATETIME,
    "lastFailedAt" DATETIME,
    CONSTRAINT "GroupVisitPin_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "GroupVisitPin_setByUserId_fkey" FOREIGN KEY ("setByUserId") REFERENCES "User" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE UNIQUE INDEX "GroupVisitPin_groupId_key" ON "GroupVisitPin"("groupId");

-- One visit: one agent, one group, one occasion.
CREATE TABLE "GroupVisit" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "villageAgentId" TEXT,
    "submittedByUserId" TEXT,
    -- Minted on the phone when the opening PIN passes and never regenerated.
    -- The UNIQUE index below is what makes a retried submit return the existing
    -- visit instead of creating a second one.
    "clientRequestId" TEXT NOT NULL,
    "visitType" TEXT NOT NULL DEFAULT 'FOLLOW_UP',
    "status" TEXT NOT NULL DEFAULT 'SUBMITTED',
    "startedAt" DATETIME NOT NULL,
    "completedAt" DATETIME,
    "submittedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "deviceLatitude" REAL,
    "deviceLongitude" REAL,
    "locationAccuracyM" REAL,
    "locationCapturedAt" DATETIME,
    -- Server-computed. The client's own assertion is never stored here.
    "distanceFromGroupM" REAL,
    "locationOutcome" TEXT NOT NULL DEFAULT 'NO_DEVICE_FIX',
    "withinGeofence" BOOLEAN NOT NULL DEFAULT false,
    "locationNote" TEXT,
    "authenticityFlagsJson" TEXT NOT NULL DEFAULT '[]',
    "deviceId" TEXT,
    "notes" TEXT,
    "revision" INTEGER NOT NULL DEFAULT 1,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "GroupVisit_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "GroupVisit_villageAgentId_fkey" FOREIGN KEY ("villageAgentId") REFERENCES "VillageAgent" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "GroupVisit_submittedByUserId_fkey" FOREIGN KEY ("submittedByUserId") REFERENCES "User" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE UNIQUE INDEX "GroupVisit_clientRequestId_key" ON "GroupVisit"("clientRequestId");
CREATE INDEX "GroupVisit_groupId_startedAt_idx" ON "GroupVisit"("groupId", "startedAt");
CREATE INDEX "GroupVisit_villageAgentId_startedAt_idx" ON "GroupVisit"("villageAgentId", "startedAt");

-- The state of a visit before an amendment. Append-only: a submitted visit is
-- never rewritten in place, so what was reported at the time survives the
-- correction.
CREATE TABLE "GroupVisitRevision" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "visitId" TEXT NOT NULL,
    "revision" INTEGER NOT NULL,
    "snapshotJson" TEXT NOT NULL,
    "reason" TEXT,
    "amendedByUserId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "GroupVisitRevision_visitId_fkey" FOREIGN KEY ("visitId") REFERENCES "GroupVisit" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "GroupVisitRevision_amendedByUserId_fkey" FOREIGN KEY ("amendedByUserId") REFERENCES "User" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE UNIQUE INDEX "GroupVisitRevision_visitId_revision_key" ON "GroupVisitRevision"("visitId", "revision");
