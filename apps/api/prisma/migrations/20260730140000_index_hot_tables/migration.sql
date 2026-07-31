-- CreateIndex
CREATE INDEX "LedgerEntry_groupId_createdAt_idx" ON "LedgerEntry"("groupId", "createdAt");

-- CreateIndex
CREATE INDEX "LedgerEntry_memberId_createdAt_idx" ON "LedgerEntry"("memberId", "createdAt");

-- CreateIndex
CREATE INDEX "LedgerEntry_meetingId_idx" ON "LedgerEntry"("meetingId");

-- CreateIndex
CREATE INDEX "Meeting_groupId_scheduledAt_idx" ON "Meeting"("groupId", "scheduledAt");

-- CreateIndex
CREATE INDEX "Member_groupId_status_idx" ON "Member"("groupId", "status");

