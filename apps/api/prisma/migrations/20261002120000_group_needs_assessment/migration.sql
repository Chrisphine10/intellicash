-- CreateTable
CREATE TABLE "GroupNeedsAssessment" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "visitId" TEXT NOT NULL,
    "sourceSystem" TEXT NOT NULL,
    "sourceReference" TEXT NOT NULL,
    "formVersion" TEXT,
    "assessedOn" DATETIME NOT NULL,
    "fieldOfficer" TEXT,
    "enumerator" TEXT,
    "totalMembers" INTEGER,
    "womenMembers" INTEGER,
    "youthMembers" INTEGER,
    "membersWithDisability" INTEGER,
    "dateFormed" DATETIME,
    "completedCycles" INTEGER,
    "currentCycle" INTEGER,
    "shareValueCents" INTEGER,
    "totalSavingsCents" INTEGER,
    "loanPortfolioCents" INTEGER,
    "arrearsCents" INTEGER,
    "par30Cents" INTEGER,
    "welfareBalanceCents" INTEGER,
    "interestCents" INTEGER,
    "smartphoneMembers" INTEGER,
    "basicPhoneMembers" INTEGER,
    "literacyPct" INTEGER,
    "digitalChampion" BOOLEAN,
    "gpsLatitude" REAL,
    "gpsLongitude" REAL,
    "gpsPrecisionM" REAL,
    "answersJson" TEXT NOT NULL,
    "qualityFlagsJson" TEXT NOT NULL DEFAULT '[]',
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "GroupNeedsAssessment_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "GroupNeedsAssessment_visitId_fkey" FOREIGN KEY ("visitId") REFERENCES "GroupVisit" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateIndex
CREATE UNIQUE INDEX "GroupNeedsAssessment_visitId_key" ON "GroupNeedsAssessment"("visitId");

-- CreateIndex
CREATE UNIQUE INDEX "GroupNeedsAssessment_sourceReference_key" ON "GroupNeedsAssessment"("sourceReference");

-- CreateIndex
CREATE INDEX "GroupNeedsAssessment_groupId_assessedOn_idx" ON "GroupNeedsAssessment"("groupId", "assessedOn");

