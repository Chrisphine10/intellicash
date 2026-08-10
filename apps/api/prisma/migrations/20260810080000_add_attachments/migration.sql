-- CreateTable
CREATE TABLE "Attachment" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "kind" TEXT NOT NULL,
    "groupId" TEXT NOT NULL,
    "visitId" TEXT,
    "sectionKey" TEXT,
    "questionKey" TEXT,
    "villageAgentId" TEXT,
    "uploadedByUserId" TEXT,
    "storagePath" TEXT NOT NULL,
    "fileName" TEXT NOT NULL,
    "mimeType" TEXT NOT NULL,
    "sizeBytes" INTEGER NOT NULL,
    "sha256" TEXT NOT NULL,
    "capturedAt" DATETIME,
    "caption" TEXT,
    "clientRequestId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "Attachment_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "Attachment_visitId_fkey" FOREIGN KEY ("visitId") REFERENCES "GroupVisit" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "Attachment_villageAgentId_fkey" FOREIGN KEY ("villageAgentId") REFERENCES "VillageAgent" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "Attachment_uploadedByUserId_fkey" FOREIGN KEY ("uploadedByUserId") REFERENCES "User" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "GroupDocument" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "documentType" TEXT NOT NULL,
    "presence" TEXT NOT NULL DEFAULT 'MISSING',
    "verification" TEXT NOT NULL DEFAULT 'UNVERIFIED',
    "expiresOn" DATETIME,
    "attachmentId" TEXT,
    "notes" TEXT,
    "verifiedByUserId" TEXT,
    "verifiedAt" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "GroupDocument_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "GroupDocument_verifiedByUserId_fkey" FOREIGN KEY ("verifiedByUserId") REFERENCES "User" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateIndex
CREATE UNIQUE INDEX "Attachment_clientRequestId_key" ON "Attachment"("clientRequestId");

-- CreateIndex
CREATE INDEX "Attachment_groupId_createdAt_idx" ON "Attachment"("groupId", "createdAt");

-- CreateIndex
CREATE INDEX "Attachment_visitId_idx" ON "Attachment"("visitId");

-- CreateIndex
CREATE UNIQUE INDEX "Attachment_sha256_visitId_key" ON "Attachment"("sha256", "visitId");

-- CreateIndex
CREATE UNIQUE INDEX "GroupDocument_groupId_documentType_key" ON "GroupDocument"("groupId", "documentType");

