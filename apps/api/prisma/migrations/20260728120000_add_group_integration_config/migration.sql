
-- CreateTable
CREATE TABLE "GroupIntegrationConfig" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "provider" TEXT NOT NULL,
    "credentialsJson" TEXT,
    "enabled" BOOLEAN NOT NULL DEFAULT true,
    "mode" TEXT NOT NULL DEFAULT 'SANDBOX',
    "credentialsUpdatedAt" DATETIME,
    "updatedByUserId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "GroupIntegrationConfig_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateIndex
CREATE INDEX "GroupIntegrationConfig_groupId_idx" ON "GroupIntegrationConfig"("groupId");

-- CreateIndex
CREATE UNIQUE INDEX "GroupIntegrationConfig_groupId_provider_key" ON "GroupIntegrationConfig"("groupId", "provider");

