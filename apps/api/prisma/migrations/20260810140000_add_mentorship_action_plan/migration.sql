-- CreateTable
CREATE TABLE "MentorshipTopic" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "key" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "description" TEXT,
    "position" INTEGER NOT NULL DEFAULT 0,
    "isActive" BOOLEAN NOT NULL DEFAULT true,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "VisitMentorshipSession" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "visitId" TEXT NOT NULL,
    "topicId" TEXT,
    "topicKeySnapshot" TEXT NOT NULL,
    "topicTitleSnapshot" TEXT NOT NULL,
    "notes" TEXT,
    "durationMinutes" INTEGER,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "VisitMentorshipSession_visitId_fkey" FOREIGN KEY ("visitId") REFERENCES "GroupVisit" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "VisitMentorshipSession_topicId_fkey" FOREIGN KEY ("topicId") REFERENCES "MentorshipTopic" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "MentorshipRatingDimension" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "key" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "position" INTEGER NOT NULL DEFAULT 0,
    "isActive" BOOLEAN NOT NULL DEFAULT true
);

-- CreateTable
CREATE TABLE "VisitMentorshipRating" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "visitId" TEXT NOT NULL,
    "dimensionId" TEXT,
    "dimensionKeySnapshot" TEXT NOT NULL,
    "score" INTEGER NOT NULL,
    "ratedByRole" TEXT NOT NULL DEFAULT 'GROUP_REPRESENTATIVE',
    "comment" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "VisitMentorshipRating_visitId_fkey" FOREIGN KEY ("visitId") REFERENCES "GroupVisit" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "VisitMentorshipRating_dimensionId_fkey" FOREIGN KEY ("dimensionId") REFERENCES "MentorshipRatingDimension" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "VisitActionItem" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "visitId" TEXT NOT NULL,
    "groupId" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "detail" TEXT,
    "owner" TEXT,
    "dueDate" DATETIME,
    "status" TEXT NOT NULL DEFAULT 'OPEN',
    "closedAtVisitId" TEXT,
    "closedAt" DATETIME,
    "closingNote" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "VisitActionItem_visitId_fkey" FOREIGN KEY ("visitId") REFERENCES "GroupVisit" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "VisitActionItem_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateIndex
CREATE UNIQUE INDEX "MentorshipTopic_key_key" ON "MentorshipTopic"("key");

-- CreateIndex
CREATE INDEX "VisitMentorshipSession_topicKeySnapshot_idx" ON "VisitMentorshipSession"("topicKeySnapshot");

-- CreateIndex
CREATE UNIQUE INDEX "VisitMentorshipSession_visitId_topicKeySnapshot_key" ON "VisitMentorshipSession"("visitId", "topicKeySnapshot");

-- CreateIndex
CREATE UNIQUE INDEX "MentorshipRatingDimension_key_key" ON "MentorshipRatingDimension"("key");

-- CreateIndex
CREATE UNIQUE INDEX "VisitMentorshipRating_visitId_dimensionKeySnapshot_key" ON "VisitMentorshipRating"("visitId", "dimensionKeySnapshot");

-- CreateIndex
CREATE INDEX "VisitActionItem_groupId_status_idx" ON "VisitActionItem"("groupId", "status");

-- CreateIndex
CREATE INDEX "VisitActionItem_status_dueDate_idx" ON "VisitActionItem"("status", "dueDate");

