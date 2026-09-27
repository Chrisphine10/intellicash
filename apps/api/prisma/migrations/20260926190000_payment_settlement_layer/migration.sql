-- Payment & settlement layer: fees on top of what the group receives,
-- server-side posting on verification, settlement to a verified destination.

-- CreateTable
CREATE TABLE "FeeRule" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "kind" TEXT NOT NULL,
    "provider" TEXT,
    "minCents" INTEGER NOT NULL DEFAULT 0,
    "maxCents" INTEGER,
    "fixedCents" INTEGER NOT NULL DEFAULT 0,
    "percentBps" INTEGER NOT NULL DEFAULT 0,
    "active" BOOLEAN NOT NULL DEFAULT true,
    "version" INTEGER NOT NULL DEFAULT 1,
    "replacesId" TEXT,
    "note" TEXT,
    "createdById" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "GroupPaymentSettings" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "collectionMode" TEXT NOT NULL DEFAULT 'SYSTEM',
    "enabledProvidersJson" TEXT NOT NULL DEFAULT '[]',
    "memberSelfPayEnabled" BOOLEAN NOT NULL DEFAULT false,
    "updatedById" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "GroupPaymentSettings_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "SettlementDestination" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "type" TEXT NOT NULL,
    "provider" TEXT NOT NULL,
    "accountNumber" TEXT NOT NULL,
    "accountName" TEXT NOT NULL,
    "bankCode" TEXT,
    "accountReference" TEXT,
    "recipientCode" TEXT,
    "currency" TEXT NOT NULL DEFAULT 'KES',
    "status" TEXT NOT NULL DEFAULT 'PROPOSED',
    "proposedById" TEXT,
    "verifiedById" TEXT,
    "verifiedAt" DATETIME,
    "retiredAt" DATETIME,
    "note" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "SettlementDestination_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "Settlement" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "destinationId" TEXT NOT NULL,
    "amountCents" INTEGER NOT NULL,
    "currency" TEXT NOT NULL DEFAULT 'KES',
    "status" TEXT NOT NULL DEFAULT 'QUEUED',
    "provider" TEXT NOT NULL,
    "internalReference" TEXT NOT NULL,
    "providerReference" TEXT,
    "providerReceipt" TEXT,
    "approvedById" TEXT,
    "approvedAt" DATETIME,
    "settledAt" DATETIME,
    "failureReason" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "Settlement_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT "Settlement_destinationId_fkey" FOREIGN KEY ("destinationId") REFERENCES "SettlementDestination" ("id") ON DELETE RESTRICT ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "SettlementAttempt" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "settlementId" TEXT NOT NULL,
    "provider" TEXT NOT NULL,
    "result" TEXT NOT NULL,
    "requestJson" TEXT,
    "responseJson" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "SettlementAttempt_settlementId_fkey" FOREIGN KEY ("settlementId") REFERENCES "Settlement" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- RedefineTables
PRAGMA defer_foreign_keys=ON;
PRAGMA foreign_keys=OFF;
CREATE TABLE "new_GroupPayment" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "memberId" TEXT,
    "meetingId" TEXT,
    "purpose" TEXT NOT NULL DEFAULT 'SHARE_PURCHASE',
    "provider" TEXT NOT NULL,
    "amountCents" INTEGER NOT NULL,
    "currency" TEXT NOT NULL DEFAULT 'KES',
    "phoneNumber" TEXT,
    "customerEmail" TEXT,
    "status" TEXT NOT NULL DEFAULT 'PENDING',
    "internalReference" TEXT NOT NULL,
    "providerReference" TEXT,
    "providerTransactionId" TEXT,
    "checkoutUrl" TEXT,
    "failureReason" TEXT,
    "metadataJson" TEXT,
    "clientRequestId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "completedAt" DATETIME,
    "updatedAt" DATETIME NOT NULL,
    "groupAmountCents" INTEGER NOT NULL DEFAULT 0,
    "platformFeeCents" INTEGER NOT NULL DEFAULT 0,
    "providerFeeCents" INTEGER NOT NULL DEFAULT 0,
    "feeSnapshotJson" TEXT,
    "collectionMode" TEXT NOT NULL DEFAULT 'SYSTEM',
    "state" TEXT NOT NULL DEFAULT 'INITIATED',
    "platformFeeStatus" TEXT NOT NULL DEFAULT 'NONE',
    "verifiedAt" DATETIME,
    "verifiedAmountCents" INTEGER,
    "ledgerEntryId" TEXT,
    "settlementStatus" TEXT NOT NULL DEFAULT 'NOT_REQUIRED',
    "settlementId" TEXT,
    CONSTRAINT "GroupPayment_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "GroupPayment_memberId_fkey" FOREIGN KEY ("memberId") REFERENCES "Member" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "GroupPayment_meetingId_fkey" FOREIGN KEY ("meetingId") REFERENCES "Meeting" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "GroupPayment_settlementId_fkey" FOREIGN KEY ("settlementId") REFERENCES "Settlement" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);
INSERT INTO "new_GroupPayment" ("amountCents", "checkoutUrl", "clientRequestId", "completedAt", "createdAt", "currency", "customerEmail", "failureReason", "groupId", "id", "internalReference", "meetingId", "memberId", "metadataJson", "phoneNumber", "provider", "providerReference", "providerTransactionId", "purpose", "status", "updatedAt") SELECT "amountCents", "checkoutUrl", "clientRequestId", "completedAt", "createdAt", "currency", "customerEmail", "failureReason", "groupId", "id", "internalReference", "meetingId", "memberId", "metadataJson", "phoneNumber", "provider", "providerReference", "providerTransactionId", "purpose", "status", "updatedAt" FROM "GroupPayment";
DROP TABLE "GroupPayment";
ALTER TABLE "new_GroupPayment" RENAME TO "GroupPayment";
CREATE UNIQUE INDEX "GroupPayment_internalReference_key" ON "GroupPayment"("internalReference");
CREATE UNIQUE INDEX "GroupPayment_clientRequestId_key" ON "GroupPayment"("clientRequestId");
CREATE UNIQUE INDEX "GroupPayment_ledgerEntryId_key" ON "GroupPayment"("ledgerEntryId");
CREATE INDEX "GroupPayment_groupId_status_idx" ON "GroupPayment"("groupId", "status");
CREATE INDEX "GroupPayment_providerReference_idx" ON "GroupPayment"("providerReference");
CREATE INDEX "GroupPayment_providerTransactionId_idx" ON "GroupPayment"("providerTransactionId");
CREATE INDEX "GroupPayment_state_idx" ON "GroupPayment"("state");
CREATE INDEX "GroupPayment_settlementStatus_groupId_idx" ON "GroupPayment"("settlementStatus", "groupId");
PRAGMA foreign_keys=ON;
PRAGMA defer_foreign_keys=OFF;

-- CreateIndex
CREATE INDEX "FeeRule_kind_active_idx" ON "FeeRule"("kind", "active");

-- CreateIndex
CREATE UNIQUE INDEX "GroupPaymentSettings_groupId_key" ON "GroupPaymentSettings"("groupId");

-- CreateIndex
CREATE INDEX "SettlementDestination_groupId_status_idx" ON "SettlementDestination"("groupId", "status");

-- CreateIndex
CREATE UNIQUE INDEX "Settlement_internalReference_key" ON "Settlement"("internalReference");

-- CreateIndex
CREATE INDEX "Settlement_groupId_status_idx" ON "Settlement"("groupId", "status");

-- CreateIndex
CREATE INDEX "Settlement_status_idx" ON "Settlement"("status");

-- CreateIndex
CREATE INDEX "Settlement_providerReference_idx" ON "Settlement"("providerReference");

-- CreateIndex
CREATE INDEX "SettlementAttempt_settlementId_idx" ON "SettlementAttempt"("settlementId");


-- Backfill: payments made before this layer carried no fees, so the group
-- received everything that was charged. Their ledger entries came from the
-- phone's meeting sync, so they are marked COMPLETED_LEGACY (posted by the
-- phone, not this server) and never settled by it.
UPDATE "GroupPayment" SET "groupAmountCents" = "amountCents";
UPDATE "GroupPayment" SET "state" = CASE "status"
  WHEN 'COMPLETED' THEN 'COMPLETED_LEGACY'
  WHEN 'FAILED' THEN 'FAILED'
  WHEN 'CANCELLED' THEN 'CANCELLED'
  ELSE 'PROCESSING' END;
UPDATE "GroupPayment" SET "collectionMode" = 'OWN_ACCOUNT'
WHERE EXISTS (
  SELECT 1 FROM "GroupIntegrationConfig" c
  WHERE c."groupId" = "GroupPayment"."groupId" AND c."provider" = "GroupPayment"."provider" AND c."enabled" = 1
);

-- Every group holds all five standard funds. Groups imported by the FLOURISH
-- onboarding script were created without any, so the first money written for
-- them (a meeting sync or an online payment) failed with FUND_ACCOUNT_NOT_FOUND.
-- Idempotent: only missing (group, type) pairs are added, at a zero balance,
-- which is exactly what an account with no ledger rows holds.
INSERT INTO "FundAccount" ("id", "groupId", "type", "balanceCents", "currency", "createdAt", "updatedAt")
SELECT 'fa_' || g."id" || '_' || t."type", g."id", t."type", 0, 'KES', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM "Group" g
CROSS JOIN (
  SELECT 'INTERNAL_LOAN' AS "type" UNION ALL SELECT 'SOCIAL' UNION ALL SELECT 'EXTERNAL_LOAN'
  UNION ALL SELECT 'GRANT' UNION ALL SELECT 'VSLF'
) t
WHERE NOT EXISTS (
  SELECT 1 FROM "FundAccount" f WHERE f."groupId" = g."id" AND f."type" = t."type"
);
