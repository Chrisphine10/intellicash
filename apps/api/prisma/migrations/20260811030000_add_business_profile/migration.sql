-- CreateTable
CREATE TABLE "GroupBusinessProfile" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "enterpriseType" TEXT,
    "description" TEXT,
    "monthlyRevenueCents" INTEGER,
    "monthlyCostsCents" INTEGER,
    "employsPeople" INTEGER,
    "startedOn" DATETIME,
    "mainChallenge" TEXT,
    "supportNeeded" TEXT,
    "updatedAt" DATETIME NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "GroupBusinessProfile_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "GroupBusinessProfileVersion" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "profileId" TEXT NOT NULL,
    "groupId" TEXT NOT NULL,
    "visitId" TEXT,
    "enterpriseType" TEXT,
    "description" TEXT,
    "monthlyRevenueCents" INTEGER,
    "monthlyCostsCents" INTEGER,
    "employsPeople" INTEGER,
    "mainChallenge" TEXT,
    "supportNeeded" TEXT,
    "recordedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "GroupBusinessProfileVersion_profileId_fkey" FOREIGN KEY ("profileId") REFERENCES "GroupBusinessProfile" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateIndex
CREATE UNIQUE INDEX "GroupBusinessProfile_groupId_key" ON "GroupBusinessProfile"("groupId");

-- CreateIndex
CREATE INDEX "GroupBusinessProfileVersion_groupId_recordedAt_idx" ON "GroupBusinessProfileVersion"("groupId", "recordedAt");

-- CreateIndex
CREATE UNIQUE INDEX "GroupBusinessProfileVersion_profileId_visitId_key" ON "GroupBusinessProfileVersion"("profileId", "visitId");

