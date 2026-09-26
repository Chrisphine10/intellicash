-- Many village agents / CBTs per group.
CREATE TABLE "GroupAgent" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "villageAgentId" TEXT NOT NULL,
    "isLead" BOOLEAN NOT NULL DEFAULT false,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "GroupAgent_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "GroupAgent_villageAgentId_fkey" FOREIGN KEY ("villageAgentId") REFERENCES "VillageAgent" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE UNIQUE INDEX "GroupAgent_groupId_villageAgentId_key" ON "GroupAgent"("groupId", "villageAgentId");
CREATE INDEX "GroupAgent_villageAgentId_idx" ON "GroupAgent"("villageAgentId");

-- Every group's existing agent becomes its lead link.
INSERT INTO "GroupAgent" ("id", "groupId", "villageAgentId", "isLead", "createdAt")
SELECT 'ga_' || "id", "id", "villageAgentId", true, CURRENT_TIMESTAMP
FROM "Group"
WHERE "villageAgentId" IS NOT NULL;
