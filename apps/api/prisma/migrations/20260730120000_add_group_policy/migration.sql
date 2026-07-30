-- CreateTable
CREATE TABLE "GroupPolicy" (
    "groupId" TEXT NOT NULL PRIMARY KEY,
    "defaultLoanTermMonths" INTEGER NOT NULL DEFAULT 1,
    "expenseFundType" TEXT NOT NULL DEFAULT 'SOCIAL',
    "updatedByUserId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "GroupPolicy_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

