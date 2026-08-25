-- A group may run several enterprises, and what a group needs is now countable.
--
-- Three changes in one migration because they are one change. `GroupBusinessProfile`
-- had `groupId` UNIQUE, so a group running a poultry unit AND a cereal store had to
-- overwrite one to record the other. Lifting that constraint means rebuilding the
-- table, and the new market-coverage and support-need columns belong on the table
-- being rebuilt rather than in a second pass over the same rows.
--
-- ORDER MATTERS AND IS NOT PRISMA'S. `prisma migrate diff` emits both DROP TABLEs
-- FIRST, which would destroy every business profile ever recorded before the tables
-- that receive them exist. The DDL below is Prisma's own, unedited, with the drops
-- moved to the end -- after everything has been read.
--
-- The pragma pairing is the one from the agent-programme migration: `PRAGMA
-- foreign_keys` is a no-op inside a transaction, and `defer_foreign_keys` defers
-- FK *checks* but not ON DELETE *actions*, so a DROP TABLE can still fire a cascade
-- and empty a table a later statement was about to read. Both are needed.

PRAGMA defer_foreign_keys=ON;
PRAGMA foreign_keys=OFF;

-- CreateTable
CREATE TABLE "GroupEnterprise" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "enterpriseType" TEXT,
    "description" TEXT,
    "monthlyRevenueCents" INTEGER,
    "monthlyCostsCents" INTEGER,
    "employsPeople" INTEGER,
    "startedOn" DATETIME,
    "marketReach" TEXT,
    "buyerCount" INTEGER,
    "marketChannelsJson" TEXT NOT NULL DEFAULT '[]',
    "hasFormalBuyerAgreement" BOOLEAN,
    "salesMonthsJson" TEXT NOT NULL DEFAULT '[]',
    "mainChallenge" TEXT,
    "supportNeeded" TEXT,
    "status" TEXT NOT NULL DEFAULT 'ACTIVE',
    "updatedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "GroupEnterprise_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "GroupEnterpriseVersion" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "enterpriseId" TEXT NOT NULL,
    "groupId" TEXT NOT NULL,
    "visitId" TEXT,
    "name" TEXT,
    "enterpriseType" TEXT,
    "description" TEXT,
    "monthlyRevenueCents" INTEGER,
    "monthlyCostsCents" INTEGER,
    "employsPeople" INTEGER,
    "marketReach" TEXT,
    "buyerCount" INTEGER,
    "marketChannelsJson" TEXT NOT NULL DEFAULT '[]',
    "hasFormalBuyerAgreement" BOOLEAN,
    "salesMonthsJson" TEXT NOT NULL DEFAULT '[]',
    "mainChallenge" TEXT,
    "supportNeeded" TEXT,
    "status" TEXT,
    "recordedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "GroupEnterpriseVersion_enterpriseId_fkey" FOREIGN KEY ("enterpriseId") REFERENCES "GroupEnterprise" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "SupportNeedType" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "key" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "category" TEXT NOT NULL,
    "description" TEXT,
    "position" INTEGER NOT NULL DEFAULT 0,
    "isActive" BOOLEAN NOT NULL DEFAULT true,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- CreateTable
CREATE TABLE "GroupEnterpriseSupportNeed" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "enterpriseId" TEXT NOT NULL,
    "groupId" TEXT NOT NULL,
    "typeId" TEXT,
    "needKeySnapshot" TEXT NOT NULL,
    "needTitleSnapshot" TEXT NOT NULL,
    "needCategorySnapshot" TEXT NOT NULL,
    "priority" TEXT NOT NULL DEFAULT 'MEDIUM',
    "status" TEXT NOT NULL DEFAULT 'OPEN',
    "detail" TEXT,
    "raisedAtVisitId" TEXT,
    "metAtVisitId" TEXT,
    "raisedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "metAt" DATETIME,
    "updatedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "GroupEnterpriseSupportNeed_enterpriseId_fkey" FOREIGN KEY ("enterpriseId") REFERENCES "GroupEnterprise" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "GroupEnterpriseSupportNeed_typeId_fkey" FOREIGN KEY ("typeId") REFERENCES "SupportNeedType" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateIndex
CREATE INDEX "GroupEnterprise_groupId_status_idx" ON "GroupEnterprise"("groupId", "status");

-- CreateIndex
CREATE INDEX "GroupEnterpriseVersion_groupId_recordedAt_idx" ON "GroupEnterpriseVersion"("groupId", "recordedAt");

-- CreateIndex
CREATE INDEX "GroupEnterpriseVersion_enterpriseId_recordedAt_idx" ON "GroupEnterpriseVersion"("enterpriseId", "recordedAt");

-- CreateIndex
CREATE UNIQUE INDEX "GroupEnterpriseVersion_enterpriseId_visitId_key" ON "GroupEnterpriseVersion"("enterpriseId", "visitId");

-- CreateIndex
CREATE UNIQUE INDEX "SupportNeedType_key_key" ON "SupportNeedType"("key");

-- CreateIndex
CREATE INDEX "GroupEnterpriseSupportNeed_groupId_status_idx" ON "GroupEnterpriseSupportNeed"("groupId", "status");

-- CreateIndex
CREATE INDEX "GroupEnterpriseSupportNeed_needKeySnapshot_status_idx" ON "GroupEnterpriseSupportNeed"("needKeySnapshot", "status");

-- CreateIndex
CREATE INDEX "GroupEnterpriseSupportNeed_enterpriseId_status_idx" ON "GroupEnterpriseSupportNeed"("enterpriseId", "status");

-- ---------------------------------------------------------------------------
-- Carry the existing profiles across: one enterprise per group, as before.
-- ---------------------------------------------------------------------------
--
-- `name` is NOT NULL and had no predecessor. `enterpriseType` is the closest the
-- old row comes to a name ("Poultry", "Cereal store"), so it is used where it was
-- filled in and a plain label stands in where it was not. "Enterprise 1" would be
-- worse than useless on a field officer's screen.

INSERT INTO "GroupEnterprise" (
  "id", "groupId", "name", "enterpriseType", "description",
  "monthlyRevenueCents", "monthlyCostsCents", "employsPeople", "startedOn",
  "mainChallenge", "supportNeeded", "status", "createdAt", "updatedAt"
)
SELECT
  "id",
  "groupId",
  COALESCE(NULLIF(TRIM("enterpriseType"), ''), 'Group enterprise'),
  "enterpriseType",
  "description",
  "monthlyRevenueCents",
  "monthlyCostsCents",
  "employsPeople",
  "startedOn",
  "mainChallenge",
  "supportNeeded",
  'ACTIVE',
  "createdAt",
  "updatedAt"
FROM "GroupBusinessProfile";

-- The per-visit history moves with it. Dropping this would leave the current
-- figures with nothing to compare against, which is the single question the
-- snapshots exist to answer.
INSERT INTO "GroupEnterpriseVersion" (
  "id", "enterpriseId", "groupId", "visitId", "name", "enterpriseType",
  "description", "monthlyRevenueCents", "monthlyCostsCents", "employsPeople",
  "mainChallenge", "supportNeeded", "status", "recordedAt"
)
SELECT
  v."id",
  v."profileId",
  v."groupId",
  v."visitId",
  COALESCE(NULLIF(TRIM(v."enterpriseType"), ''), 'Group enterprise'),
  v."enterpriseType",
  v."description",
  v."monthlyRevenueCents",
  v."monthlyCostsCents",
  v."employsPeople",
  v."mainChallenge",
  v."supportNeeded",
  'ACTIVE',
  v."recordedAt"
FROM "GroupBusinessProfileVersion" v
-- An orphaned version whose profile is already gone would violate the new foreign
-- key and abort the entire migration. Skip it rather than take the site down.
WHERE EXISTS (SELECT 1 FROM "GroupEnterprise" e WHERE e."id" = v."profileId");

-- ---------------------------------------------------------------------------
-- The support-need vocabulary.
-- ---------------------------------------------------------------------------
--
-- Reference data, so it ships in the migration and not the seed: the seed does not
-- run on production, and without these rows the capture screen offers an empty list
-- and the whole feature is inert on the one environment that matters.
--
-- Deterministic ids (`snt-<key>`) rather than generated ones, so replaying this
-- against a database that already has them is a no-op instead of quietly creating
-- a second copy of the entire taxonomy.

INSERT OR IGNORE INTO "SupportNeedType" ("id", "key", "title", "category", "position")
VALUES
  ('snt-working-capital', 'working-capital', 'Working capital or stock finance', 'FINANCE', 10),
  ('snt-asset-finance', 'asset-finance', 'Equipment or asset finance', 'FINANCE', 20),
  ('snt-insurance', 'insurance', 'Insurance cover', 'FINANCE', 30),
  ('snt-buyer-linkage', 'buyer-linkage', 'Linkage to a reliable buyer', 'MARKET', 40),
  ('snt-price-information', 'price-information', 'Market price information', 'MARKET', 50),
  ('snt-aggregation', 'aggregation', 'Bulking and aggregation', 'MARKET', 60),
  ('snt-certification', 'certification', 'Certification or quality standards', 'MARKET', 70),
  ('snt-packaging-branding', 'packaging-branding', 'Packaging and branding', 'MARKET', 80),
  ('snt-business-training', 'business-training', 'Business and enterprise training', 'SKILLS', 90),
  ('snt-record-keeping', 'record-keeping', 'Record keeping', 'SKILLS', 100),
  ('snt-production-technique', 'production-technique', 'Production or agronomy technique', 'SKILLS', 110),
  ('snt-digital-skills', 'digital-skills', 'Digital skills', 'SKILLS', 120),
  ('snt-seed-stock', 'seed-stock', 'Seed, stock or breeding material', 'INPUTS', 130),
  ('snt-feed-fertiliser', 'feed-fertiliser', 'Feed or fertiliser', 'INPUTS', 140),
  ('snt-tools-equipment', 'tools-equipment', 'Tools and equipment', 'INPUTS', 150),
  ('snt-storage', 'storage', 'Storage', 'INFRASTRUCTURE', 160),
  ('snt-cold-chain', 'cold-chain', 'Cold chain', 'INFRASTRUCTURE', 170),
  ('snt-water', 'water', 'Water access', 'INFRASTRUCTURE', 180),
  ('snt-power', 'power', 'Power access', 'INFRASTRUCTURE', 190),
  ('snt-transport', 'transport', 'Transport to market', 'INFRASTRUCTURE', 200),
  ('snt-registration', 'registration', 'Registration or licensing', 'GOVERNANCE', 210),
  ('snt-constitution', 'constitution', 'Constitution and by-laws', 'GOVERNANCE', 220),
  ('snt-leadership', 'leadership', 'Leadership and governance', 'GOVERNANCE', 230),
  ('snt-digital-records', 'digital-records', 'Digital record keeping', 'TECHNOLOGY', 240),
  ('snt-mobile-money', 'mobile-money', 'Mobile money and digital payments', 'TECHNOLOGY', 250);

-- ---------------------------------------------------------------------------
-- Only now that everything has been read, drop the old tables.
-- ---------------------------------------------------------------------------
--
-- Version table first: its rows cascade from the profile, so dropping the parent
-- while children remain is the exact pattern that silently emptied a table during
-- the agent-programme migration.

DROP TABLE "GroupBusinessProfileVersion";
DROP TABLE "GroupBusinessProfile";

PRAGMA foreign_keys=ON;
PRAGMA defer_foreign_keys=OFF;
