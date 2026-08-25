-- A village agent serves many programmes, not one.
--
-- `VillageAgent.programmeId` held a single optional programme. In practice an
-- agent works across several of a partner's programmes, so whoever set them up
-- had to pick one and the rest of their work had no home. This replaces that
-- column with a join table, and records the partner on the agent so that
-- "programmes of the same partner" is a rule the service can check.
--
-- Order matters and is the whole safety story:
--   1. create the new shapes
--   2. copy every existing link into them
--   3. only then rebuild VillageAgent without the old column
--
-- Nothing is dropped before its contents have been copied, so a failure at any
-- step leaves the old column intact and the migration rolls back whole.

-- 1. The join table, in the shape ProgrammePartner already uses.
CREATE TABLE "VillageAgentProgramme" (
    "id"             TEXT NOT NULL PRIMARY KEY,
    "villageAgentId" TEXT NOT NULL,
    "programmeId"    TEXT NOT NULL,
    "createdAt"      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "VillageAgentProgramme_villageAgentId_fkey"
        FOREIGN KEY ("villageAgentId") REFERENCES "VillageAgent" ("id")
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "VillageAgentProgramme_programmeId_fkey"
        FOREIGN KEY ("programmeId") REFERENCES "Programme" ("id")
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE UNIQUE INDEX "VillageAgentProgramme_villageAgentId_programmeId_key"
    ON "VillageAgentProgramme" ("villageAgentId", "programmeId");
CREATE INDEX "VillageAgentProgramme_programmeId_idx"
    ON "VillageAgentProgramme" ("programmeId");

-- 2a. Record the partner on the agent, derived from the programme it was on.
ALTER TABLE "VillageAgent" ADD COLUMN "partnerId" TEXT;

UPDATE "VillageAgent"
   SET "partnerId" = (
       SELECT "Programme"."partnerId"
         FROM "Programme"
        WHERE "Programme"."id" = "VillageAgent"."programmeId"
   )
 WHERE "programmeId" IS NOT NULL;

-- 2b. Copy every existing agent-to-programme link into the join table.
--     `id` is generated here rather than by the application because this runs
--     before any client sees the table; the value only has to be unique.
INSERT INTO "VillageAgentProgramme" ("id", "villageAgentId", "programmeId", "createdAt")
SELECT
    'vap_' || "VillageAgent"."id" || '_' || "VillageAgent"."programmeId",
    "VillageAgent"."id",
    "VillageAgent"."programmeId",
    CURRENT_TIMESTAMP
  FROM "VillageAgent"
 WHERE "VillageAgent"."programmeId" IS NOT NULL
   AND EXISTS (
       SELECT 1 FROM "Programme" WHERE "Programme"."id" = "VillageAgent"."programmeId"
   );

-- The EXISTS above is not belt-and-braces. The old column had a foreign key,
-- but SQLite only enforces one when `PRAGMA foreign_keys` is on, and it is off
-- by default — so a database that has ever been written by a tool that did not
-- turn it on can hold an agent pointing at a programme that no longer exists.
-- The new table DOES enforce it, so copying such a row blind would turn a
-- dormant inconsistency into a migration that fails on somebody's production
-- data. Found by running this against a database seeded exactly that way.

-- 3. Rebuild VillageAgent without `programmeId`.
--
--    SQLite cannot drop a column that carries a foreign key, so the table is
--    recreated and copied — the standard rebuild, and what Prisma generates
--    for this itself. Foreign keys are already deferred inside a migration
--    transaction, so the referencing tables (Group, User, GroupVisit,
--    Attachment and the rest) survive the swap by name.
PRAGMA foreign_keys=OFF;

CREATE TABLE "new_VillageAgent" (
    "id"                   TEXT NOT NULL PRIMARY KEY,
    "partnerId"            TEXT,
    "name"                 TEXT NOT NULL,
    "phone"                TEXT NOT NULL,
    "email"                TEXT,
    "gender"               TEXT,
    "projectOfficer"       TEXT,
    "county"               TEXT,
    "location"             TEXT,
    "feedback"             TEXT,
    "sourceSystem"         TEXT,
    "sourceReference"      TEXT,
    "status"               TEXT NOT NULL DEFAULT 'ACTIVE',
    "digitalLiteracyScore" INTEGER NOT NULL DEFAULT 80,
    "caseloadLimit"        INTEGER NOT NULL DEFAULT 25,
    "createdAt"            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt"            DATETIME NOT NULL,
    CONSTRAINT "VillageAgent_partnerId_fkey"
        FOREIGN KEY ("partnerId") REFERENCES "Partner" ("id")
        ON DELETE SET NULL ON UPDATE CASCADE
);

INSERT INTO "new_VillageAgent" (
    "id", "partnerId", "name", "phone", "email", "gender", "projectOfficer",
    "county", "location", "feedback", "sourceSystem", "sourceReference",
    "status", "digitalLiteracyScore", "caseloadLimit", "createdAt", "updatedAt"
)
SELECT
    "id", "partnerId", "name", "phone", "email", "gender", "projectOfficer",
    "county", "location", "feedback", "sourceSystem", "sourceReference",
    "status", "digitalLiteracyScore", "caseloadLimit", "createdAt", "updatedAt"
  FROM "VillageAgent";

DROP TABLE "VillageAgent";
ALTER TABLE "new_VillageAgent" RENAME TO "VillageAgent";

PRAGMA foreign_keys=ON;
