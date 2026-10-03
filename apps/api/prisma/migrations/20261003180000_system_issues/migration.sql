-- CreateTable
CREATE TABLE "SystemIssue" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "fingerprint" TEXT NOT NULL,
    "source" TEXT NOT NULL,
    "severity" TEXT NOT NULL,
    "category" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "detail" TEXT,
    "entityType" TEXT,
    "entityId" TEXT,
    "groupId" TEXT,
    "contextJson" TEXT NOT NULL DEFAULT '{}',
    "status" TEXT NOT NULL DEFAULT 'OPEN',
    "occurrences" INTEGER NOT NULL DEFAULT 1,
    "firstSeenAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "lastSeenAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "lastTraceId" TEXT,
    "resolvedAt" DATETIME,
    "resolvedById" TEXT,
    "resolutionNote" TEXT
);

-- CreateIndex
CREATE UNIQUE INDEX "SystemIssue_fingerprint_key" ON "SystemIssue"("fingerprint");

-- CreateIndex
CREATE INDEX "SystemIssue_status_lastSeenAt_idx" ON "SystemIssue"("status", "lastSeenAt");

-- CreateIndex
CREATE INDEX "SystemIssue_source_category_idx" ON "SystemIssue"("source", "category");

-- CreateIndex
CREATE INDEX "SystemIssue_groupId_idx" ON "SystemIssue"("groupId");

