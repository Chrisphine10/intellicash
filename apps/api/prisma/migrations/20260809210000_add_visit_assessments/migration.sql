-- CreateTable
CREATE TABLE "AssessmentTemplate" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "familyKey" TEXT NOT NULL,
    "version" INTEGER NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'DRAFT',
    "title" TEXT NOT NULL,
    "description" TEXT,
    "maxPoints" REAL,
    "bandsJson" TEXT NOT NULL DEFAULT '[]',
    "publishedAt" DATETIME,
    "publishedByUserId" TEXT,
    "createdByUserId" TEXT,
    "clonedFromId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "AssessmentTemplate_publishedByUserId_fkey" FOREIGN KEY ("publishedByUserId") REFERENCES "User" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "AssessmentTemplate_createdByUserId_fkey" FOREIGN KEY ("createdByUserId") REFERENCES "User" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "AssessmentTemplate_clonedFromId_fkey" FOREIGN KEY ("clonedFromId") REFERENCES "AssessmentTemplate" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "AssessmentSection" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "templateId" TEXT NOT NULL,
    "key" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "description" TEXT,
    "position" INTEGER NOT NULL,
    CONSTRAINT "AssessmentSection_templateId_fkey" FOREIGN KEY ("templateId") REFERENCES "AssessmentTemplate" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "AssessmentQuestion" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "sectionId" TEXT NOT NULL,
    "key" TEXT NOT NULL,
    "prompt" TEXT NOT NULL,
    "guidance" TEXT,
    "weight" REAL NOT NULL,
    "position" INTEGER NOT NULL,
    "requiresNote" BOOLEAN NOT NULL DEFAULT false,
    CONSTRAINT "AssessmentQuestion_sectionId_fkey" FOREIGN KEY ("sectionId") REFERENCES "AssessmentSection" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "AssessmentTemplateSnapshot" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "templateId" TEXT NOT NULL,
    "version" INTEGER NOT NULL,
    "snapshotJson" TEXT NOT NULL,
    "checksum" TEXT NOT NULL,
    "maxPoints" REAL NOT NULL,
    "scoringContractVersion" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "AssessmentTemplateSnapshot_templateId_fkey" FOREIGN KEY ("templateId") REFERENCES "AssessmentTemplate" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "GroupVisitAssessment" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "visitId" TEXT NOT NULL,
    "templateSnapshotId" TEXT NOT NULL,
    "templateId" TEXT NOT NULL,
    "templateVersion" INTEGER NOT NULL,
    "scoringContractVersion" TEXT NOT NULL,
    "earnedPoints" REAL NOT NULL,
    "applicablePoints" REAL NOT NULL,
    "maxPoints" REAL NOT NULL,
    "scaledPoints" REAL NOT NULL,
    "percentage" REAL NOT NULL,
    "bandKey" TEXT,
    "bandLabel" TEXT,
    "complete" BOOLEAN NOT NULL DEFAULT false,
    "breakdownJson" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "GroupVisitAssessment_visitId_fkey" FOREIGN KEY ("visitId") REFERENCES "GroupVisit" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "GroupVisitAssessment_templateSnapshotId_fkey" FOREIGN KEY ("templateSnapshotId") REFERENCES "AssessmentTemplateSnapshot" ("id") ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT "GroupVisitAssessment_templateId_fkey" FOREIGN KEY ("templateId") REFERENCES "AssessmentTemplate" ("id") ON DELETE RESTRICT ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "GroupVisitAnswer" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "assessmentId" TEXT NOT NULL,
    "sectionKey" TEXT NOT NULL,
    "questionKey" TEXT NOT NULL,
    "choice" TEXT NOT NULL,
    "note" TEXT,
    CONSTRAINT "GroupVisitAnswer_assessmentId_fkey" FOREIGN KEY ("assessmentId") REFERENCES "GroupVisitAssessment" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateIndex
CREATE INDEX "AssessmentTemplate_status_idx" ON "AssessmentTemplate"("status");

-- CreateIndex
CREATE UNIQUE INDEX "AssessmentTemplate_familyKey_version_key" ON "AssessmentTemplate"("familyKey", "version");

-- CreateIndex
CREATE UNIQUE INDEX "AssessmentSection_templateId_key_key" ON "AssessmentSection"("templateId", "key");

-- CreateIndex
CREATE UNIQUE INDEX "AssessmentQuestion_sectionId_key_key" ON "AssessmentQuestion"("sectionId", "key");

-- CreateIndex
CREATE UNIQUE INDEX "AssessmentTemplateSnapshot_templateId_key" ON "AssessmentTemplateSnapshot"("templateId");

-- CreateIndex
CREATE INDEX "AssessmentTemplateSnapshot_checksum_idx" ON "AssessmentTemplateSnapshot"("checksum");

-- CreateIndex
CREATE UNIQUE INDEX "GroupVisitAssessment_visitId_key" ON "GroupVisitAssessment"("visitId");

-- CreateIndex
CREATE INDEX "GroupVisitAssessment_templateId_createdAt_idx" ON "GroupVisitAssessment"("templateId", "createdAt");

-- CreateIndex
CREATE INDEX "GroupVisitAnswer_questionKey_idx" ON "GroupVisitAnswer"("questionKey");

-- CreateIndex
CREATE UNIQUE INDEX "GroupVisitAnswer_assessmentId_questionKey_key" ON "GroupVisitAnswer"("assessmentId", "questionKey");

