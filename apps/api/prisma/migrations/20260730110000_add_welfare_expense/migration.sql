-- CreateTable
CREATE TABLE "WelfareExpense" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "cycleId" TEXT,
    "meetingId" TEXT,
    "ledgerEntryId" TEXT NOT NULL,
    "category" TEXT NOT NULL,
    "payeeMemberId" TEXT,
    "payeeName" TEXT,
    "note" TEXT,
    "approvedByUserId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "WelfareExpense_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "WelfareExpense_cycleId_fkey" FOREIGN KEY ("cycleId") REFERENCES "Cycle" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "WelfareExpense_ledgerEntryId_fkey" FOREIGN KEY ("ledgerEntryId") REFERENCES "LedgerEntry" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "WelfareExpense_payeeMemberId_fkey" FOREIGN KEY ("payeeMemberId") REFERENCES "Member" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateIndex
CREATE UNIQUE INDEX "WelfareExpense_ledgerEntryId_key" ON "WelfareExpense"("ledgerEntryId");

-- CreateIndex
CREATE INDEX "WelfareExpense_groupId_createdAt_idx" ON "WelfareExpense"("groupId", "createdAt");

-- CreateIndex
CREATE INDEX "WelfareExpense_cycleId_idx" ON "WelfareExpense"("cycleId");

