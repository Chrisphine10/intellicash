-- CreateTable
CREATE TABLE "User" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "name" TEXT NOT NULL,
    "email" TEXT NOT NULL,
    "phone" TEXT,
    "passwordHash" TEXT NOT NULL,
    "role" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'ACTIVE',
    "avatarUrl" TEXT,
    "languagePreference" TEXT NOT NULL DEFAULT 'ENGLISH',
    "partnerId" TEXT,
    "groupId" TEXT,
    "memberId" TEXT,
    "villageAgentId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "User_partnerId_fkey" FOREIGN KEY ("partnerId") REFERENCES "Partner" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "User_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "User_memberId_fkey" FOREIGN KEY ("memberId") REFERENCES "Member" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "User_villageAgentId_fkey" FOREIGN KEY ("villageAgentId") REFERENCES "VillageAgent" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "Notification" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "userId" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "body" TEXT NOT NULL,
    "type" TEXT NOT NULL DEFAULT 'INFO',
    "href" TEXT,
    "readAt" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "Notification_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "Session" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "userId" TEXT NOT NULL,
    "tokenHash" TEXT NOT NULL,
    "expiresAt" DATETIME NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "lastUsedAt" DATETIME,
    CONSTRAINT "Session_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "ApiKey" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "userId" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "tokenHash" TEXT NOT NULL,
    "scopesJson" TEXT NOT NULL,
    "lastUsedAt" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "revokedAt" DATETIME,
    CONSTRAINT "ApiKey_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "RolePermissionTemplate" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "role" TEXT NOT NULL,
    "permissionsJson" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "Partner" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "name" TEXT NOT NULL,
    "type" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'ACTIVE',
    "apiScope" TEXT NOT NULL DEFAULT 'PROGRAMME',
    "county" TEXT,
    "contactName" TEXT,
    "contactPhone" TEXT,
    "valueProposition" TEXT,
    "capacity" TEXT,
    "linkageType" TEXT,
    "sourceSystem" TEXT,
    "sourceReference" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "Programme" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "partnerId" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "country" TEXT NOT NULL DEFAULT 'Kenya',
    "county" TEXT,
    "description" TEXT,
    "coverImageUrl" TEXT,
    "sourceSystem" TEXT,
    "sourceReference" TEXT,
    "publicSlug" TEXT,
    "publicStatus" TEXT NOT NULL DEFAULT 'DRAFT',
    "fundingGoalCents" INTEGER NOT NULL DEFAULT 0,
    "fundingSummary" TEXT,
    "impactSummary" TEXT,
    "fundingDeadline" DATETIME,
    "allowInvestments" BOOLEAN NOT NULL DEFAULT true,
    "allowDonations" BOOLEAN NOT NULL DEFAULT true,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "Programme_partnerId_fkey" FOREIGN KEY ("partnerId") REFERENCES "Partner" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "ProgrammeAsset" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "programmeId" TEXT NOT NULL,
    "type" TEXT NOT NULL,
    "visibility" TEXT NOT NULL DEFAULT 'PRIVATE',
    "title" TEXT NOT NULL,
    "description" TEXT,
    "url" TEXT NOT NULL,
    "fileName" TEXT,
    "mimeType" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "ProgrammeAsset_programmeId_fkey" FOREIGN KEY ("programmeId") REFERENCES "Programme" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "PartnerSignupRequest" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "organizationName" TEXT NOT NULL,
    "organizationType" TEXT NOT NULL,
    "requestedRole" TEXT NOT NULL DEFAULT 'PARTNER_OFFICER',
    "requestedPartnerType" TEXT NOT NULL DEFAULT 'NGO',
    "contactName" TEXT NOT NULL,
    "contactEmail" TEXT NOT NULL,
    "contactPhone" TEXT,
    "county" TEXT,
    "groupSubCounty" TEXT,
    "groupLocation" TEXT,
    "groupMeetingDay" TEXT,
    "groupObjective" TEXT,
    "estimatedMembers" INTEGER,
    "championRole" TEXT,
    "assignedVillageAgentId" TEXT,
    "fieldVisitStatus" TEXT NOT NULL DEFAULT 'NOT_REQUIRED',
    "fieldVisitNotes" TEXT,
    "fieldVisitScheduledAt" DATETIME,
    "fieldVisitedAt" DATETIME,
    "fieldVisitReviewedByUserId" TEXT,
    "valueProposition" TEXT,
    "status" TEXT NOT NULL DEFAULT 'PENDING',
    "reviewNotes" TEXT,
    "reviewedByUserId" TEXT,
    "reviewedAt" DATETIME,
    "createdPartnerId" TEXT,
    "createdGroupId" TEXT,
    "createdMemberId" TEXT,
    "createdUserId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "PartnerSignupRequest_createdPartnerId_fkey" FOREIGN KEY ("createdPartnerId") REFERENCES "Partner" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "PartnerSignupRequest_assignedVillageAgentId_fkey" FOREIGN KEY ("assignedVillageAgentId") REFERENCES "VillageAgent" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "PartnerWallet" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "partnerId" TEXT NOT NULL,
    "balanceCents" INTEGER NOT NULL DEFAULT 0,
    "heldCents" INTEGER NOT NULL DEFAULT 0,
    "currency" TEXT NOT NULL DEFAULT 'KES',
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "PartnerWallet_partnerId_fkey" FOREIGN KEY ("partnerId") REFERENCES "Partner" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "PartnerWalletTransaction" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "walletId" TEXT,
    "partnerId" TEXT,
    "programmeId" TEXT,
    "actorUserId" TEXT,
    "approvedByUserId" TEXT,
    "type" TEXT NOT NULL,
    "provider" TEXT NOT NULL,
    "source" TEXT NOT NULL DEFAULT 'WALLET',
    "status" TEXT NOT NULL DEFAULT 'PENDING',
    "amountCents" INTEGER NOT NULL,
    "currency" TEXT NOT NULL DEFAULT 'KES',
    "description" TEXT,
    "customerName" TEXT,
    "customerEmail" TEXT,
    "phoneNumber" TEXT,
    "payoutPhoneNumber" TEXT,
    "payoutRecipientCode" TEXT,
    "providerReference" TEXT,
    "internalReference" TEXT NOT NULL,
    "providerCheckoutUrl" TEXT,
    "providerAccessCode" TEXT,
    "providerTransactionId" TEXT,
    "providerMetadataJson" TEXT,
    "failureReason" TEXT,
    "approvedAt" DATETIME,
    "completedAt" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "PartnerWalletTransaction_walletId_fkey" FOREIGN KEY ("walletId") REFERENCES "PartnerWallet" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "PartnerWalletTransaction_partnerId_fkey" FOREIGN KEY ("partnerId") REFERENCES "Partner" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "PartnerWalletTransaction_programmeId_fkey" FOREIGN KEY ("programmeId") REFERENCES "Programme" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "PaymentWebhookEvent" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "provider" TEXT NOT NULL,
    "eventId" TEXT,
    "reference" TEXT,
    "signatureValid" BOOLEAN NOT NULL DEFAULT false,
    "payloadJson" TEXT NOT NULL,
    "processed" BOOLEAN NOT NULL DEFAULT false,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- CreateTable
CREATE TABLE "ProgrammePartner" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "programmeId" TEXT NOT NULL,
    "partnerId" TEXT NOT NULL,
    "role" TEXT NOT NULL DEFAULT 'PARTNER',
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "ProgrammePartner_programmeId_fkey" FOREIGN KEY ("programmeId") REFERENCES "Programme" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "ProgrammePartner_partnerId_fkey" FOREIGN KEY ("partnerId") REFERENCES "Partner" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "ProgrammeGroup" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "programmeId" TEXT NOT NULL,
    "groupId" TEXT NOT NULL,
    "role" TEXT NOT NULL DEFAULT 'PRIMARY',
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "ProgrammeGroup_programmeId_fkey" FOREIGN KEY ("programmeId") REFERENCES "Programme" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "ProgrammeGroup_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "VillageAgent" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "programmeId" TEXT,
    "name" TEXT NOT NULL,
    "phone" TEXT NOT NULL,
    "email" TEXT,
    "gender" TEXT,
    "projectOfficer" TEXT,
    "county" TEXT,
    "location" TEXT,
    "feedback" TEXT,
    "sourceSystem" TEXT,
    "sourceReference" TEXT,
    "status" TEXT NOT NULL DEFAULT 'ACTIVE',
    "digitalLiteracyScore" INTEGER NOT NULL DEFAULT 80,
    "caseloadLimit" INTEGER NOT NULL DEFAULT 25,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "VillageAgent_programmeId_fkey" FOREIGN KEY ("programmeId") REFERENCES "Programme" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "StoreSupplier" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "name" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'ACTIVE',
    "contactName" TEXT,
    "contactPhone" TEXT,
    "contactEmail" TEXT,
    "county" TEXT,
    "location" TEXT,
    "notes" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "StoreProduct" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "name" TEXT NOT NULL,
    "slug" TEXT NOT NULL,
    "category" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'ACTIVE',
    "supplierId" TEXT,
    "description" TEXT NOT NULL,
    "imageUrl" TEXT,
    "sellerName" TEXT,
    "priceCents" INTEGER NOT NULL,
    "depositCents" INTEGER NOT NULL DEFAULT 0,
    "currency" TEXT NOT NULL DEFAULT 'KES',
    "creditSummary" TEXT,
    "fulfilmentSummary" TEXT,
    "inventoryCount" INTEGER,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "StoreProduct_supplierId_fkey" FOREIGN KEY ("supplierId") REFERENCES "StoreSupplier" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "StoreProductProgramme" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "productId" TEXT NOT NULL,
    "programmeId" TEXT NOT NULL,
    "creditTerms" TEXT,
    "depositRateBps" INTEGER NOT NULL DEFAULT 1000,
    "installmentCount" INTEGER NOT NULL DEFAULT 6,
    "installmentFrequency" TEXT NOT NULL DEFAULT 'MONTHLY',
    "flatInterestRateBps" INTEGER NOT NULL DEFAULT 0,
    "gracePeriodDays" INTEGER NOT NULL DEFAULT 30,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "StoreProductProgramme_productId_fkey" FOREIGN KEY ("productId") REFERENCES "StoreProduct" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "StoreProductProgramme_programmeId_fkey" FOREIGN KEY ("programmeId") REFERENCES "Programme" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "StoreProductProgrammeAgent" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "productProgrammeId" TEXT NOT NULL,
    "villageAgentId" TEXT NOT NULL,
    "isPrimary" BOOLEAN NOT NULL DEFAULT false,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "StoreProductProgrammeAgent_productProgrammeId_fkey" FOREIGN KEY ("productProgrammeId") REFERENCES "StoreProductProgramme" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "StoreProductProgrammeAgent_villageAgentId_fkey" FOREIGN KEY ("villageAgentId") REFERENCES "VillageAgent" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "StoreCreditRequest" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "productId" TEXT NOT NULL,
    "programmeId" TEXT NOT NULL,
    "requesterUserId" TEXT,
    "distributionAgentId" TEXT,
    "financierPartnerId" TEXT,
    "customerName" TEXT NOT NULL,
    "customerEmail" TEXT NOT NULL,
    "phoneNumber" TEXT NOT NULL,
    "county" TEXT,
    "groupName" TEXT,
    "groupId" TEXT,
    "meetingId" TEXT,
    "creditBand" TEXT,
    "quantity" INTEGER NOT NULL DEFAULT 1,
    "requestedAmountCents" INTEGER NOT NULL,
    "depositCents" INTEGER NOT NULL DEFAULT 0,
    "financedAmountCents" INTEGER NOT NULL DEFAULT 0,
    "commissionRateBps" INTEGER NOT NULL DEFAULT 500,
    "commissionCents" INTEGER NOT NULL DEFAULT 0,
    "repaymentStatus" TEXT NOT NULL DEFAULT 'NOT_FINANCED',
    "status" TEXT NOT NULL DEFAULT 'PENDING',
    "notes" TEXT,
    "reviewNotes" TEXT,
    "financedAt" DATETIME,
    "fulfilledAt" DATETIME,
    "paidAt" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "StoreCreditRequest_productId_fkey" FOREIGN KEY ("productId") REFERENCES "StoreProduct" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "StoreCreditRequest_programmeId_fkey" FOREIGN KEY ("programmeId") REFERENCES "Programme" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "StoreCreditRequest_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "StoreCreditRequest_meetingId_fkey" FOREIGN KEY ("meetingId") REFERENCES "Meeting" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "StoreCreditRequest_requesterUserId_fkey" FOREIGN KEY ("requesterUserId") REFERENCES "User" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "StoreCreditRequest_distributionAgentId_fkey" FOREIGN KEY ("distributionAgentId") REFERENCES "VillageAgent" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "StoreCreditRequest_financierPartnerId_fkey" FOREIGN KEY ("financierPartnerId") REFERENCES "Partner" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "StoreCreditInstallment" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "requestId" TEXT NOT NULL,
    "sequence" INTEGER NOT NULL,
    "dueDate" DATETIME NOT NULL,
    "principalCents" INTEGER NOT NULL,
    "interestCents" INTEGER NOT NULL,
    "totalDueCents" INTEGER NOT NULL,
    "paidCents" INTEGER NOT NULL DEFAULT 0,
    "status" TEXT NOT NULL DEFAULT 'PENDING',
    "paidAt" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "StoreCreditInstallment_requestId_fkey" FOREIGN KEY ("requestId") REFERENCES "StoreCreditRequest" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "StoreCreditRepayment" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "requestId" TEXT NOT NULL,
    "installmentId" TEXT,
    "amountCents" INTEGER NOT NULL,
    "source" TEXT NOT NULL DEFAULT 'MANUAL',
    "provider" TEXT,
    "providerReference" TEXT,
    "recordedByUserId" TEXT,
    "notes" TEXT,
    "receivedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "StoreCreditRepayment_requestId_fkey" FOREIGN KEY ("requestId") REFERENCES "StoreCreditRequest" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "StoreCreditRepayment_installmentId_fkey" FOREIGN KEY ("installmentId") REFERENCES "StoreCreditInstallment" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "AgentBookingRequest" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "villageAgentId" TEXT,
    "programmeId" TEXT,
    "serviceType" TEXT NOT NULL,
    "preferredDate" DATETIME,
    "customerName" TEXT NOT NULL,
    "customerEmail" TEXT NOT NULL,
    "phoneNumber" TEXT NOT NULL,
    "county" TEXT,
    "groupName" TEXT,
    "notes" TEXT,
    "status" TEXT NOT NULL DEFAULT 'PENDING',
    "reviewNotes" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "AgentBookingRequest_villageAgentId_fkey" FOREIGN KEY ("villageAgentId") REFERENCES "VillageAgent" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "AgentBookingRequest_programmeId_fkey" FOREIGN KEY ("programmeId") REFERENCES "Programme" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "Group" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "programmeId" TEXT,
    "villageAgentId" TEXT,
    "name" TEXT NOT NULL,
    "code" TEXT NOT NULL,
    "phase" TEXT NOT NULL,
    "county" TEXT NOT NULL,
    "subCounty" TEXT,
    "location" TEXT,
    "composition" TEXT,
    "objective" TEXT,
    "contactPersonName" TEXT,
    "contactPhone" TEXT,
    "onboardingFeedback" TEXT,
    "sourceSystem" TEXT,
    "sourceReference" TEXT,
    "meetingDay" TEXT,
    "gpsLatitude" REAL,
    "gpsLongitude" REAL,
    "gpsRadiusMeters" INTEGER NOT NULL DEFAULT 50,
    "shareValueCents" INTEGER NOT NULL DEFAULT 50000,
    "maxSharesPerMemberPerMeeting" INTEGER NOT NULL DEFAULT 10,
    "constitutionVersion" TEXT NOT NULL DEFAULT 'IWLSGS-1.0',
    "cycleNumber" INTEGER NOT NULL DEFAULT 1,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "Group_programmeId_fkey" FOREIGN KEY ("programmeId") REFERENCES "Programme" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "Group_villageAgentId_fkey" FOREIGN KEY ("villageAgentId") REFERENCES "VillageAgent" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "Member" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "fullName" TEXT NOT NULL,
    "nationalIdHash" TEXT,
    "phone" TEXT NOT NULL,
    "role" TEXT NOT NULL DEFAULT 'MEMBER',
    "kycStatus" TEXT NOT NULL DEFAULT 'PENDING',
    "status" TEXT NOT NULL DEFAULT 'ACTIVE',
    "pinHash" TEXT,
    "pinSetAt" DATETIME,
    "pinUpdatedAt" DATETIME,
    "currentOtpHash" TEXT,
    "currentOtpIssuedAt" DATETIME,
    "currentOtpExpiresAt" DATETIME,
    "joinedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "Member_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "Meeting" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "status" TEXT NOT NULL,
    "scheduledAt" DATETIME NOT NULL,
    "openedAt" DATETIME,
    "closedAt" DATETIME,
    "unlockStatus" TEXT NOT NULL DEFAULT 'PENDING',
    "gpsCompliant" BOOLEAN NOT NULL DEFAULT false,
    "transactionTotal" INTEGER NOT NULL DEFAULT 0,
    "minutes" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "Meeting_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "MeetingKeySubmission" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "meetingId" TEXT NOT NULL,
    "memberId" TEXT NOT NULL,
    "capturedByUserId" TEXT,
    "deviceId" TEXT,
    "capturedOfflineAt" DATETIME,
    "credentialType" TEXT NOT NULL DEFAULT 'DEFAULT_PIN',
    "verifiedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "MeetingKeySubmission_meetingId_fkey" FOREIGN KEY ("meetingId") REFERENCES "Meeting" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "MeetingKeySubmission_memberId_fkey" FOREIGN KEY ("memberId") REFERENCES "Member" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "MeetingKeySubmission_capturedByUserId_fkey" FOREIGN KEY ("capturedByUserId") REFERENCES "User" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "MemberPinDelivery" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "memberId" TEXT NOT NULL,
    "requestedByUserId" TEXT,
    "provider" TEXT NOT NULL DEFAULT 'AFRICAS_TALKING',
    "channel" TEXT NOT NULL DEFAULT 'SMS',
    "purpose" TEXT NOT NULL DEFAULT 'DEFAULT_PIN',
    "phone" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'QUEUED',
    "messagePreview" TEXT NOT NULL,
    "messageCiphertext" TEXT NOT NULL,
    "sentAt" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "MemberPinDelivery_memberId_fkey" FOREIGN KEY ("memberId") REFERENCES "Member" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "SmsBroadcast" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "requestedByUserId" TEXT,
    "targetType" TEXT NOT NULL,
    "targetGroupId" TEXT,
    "targetMemberId" TEXT,
    "provider" TEXT NOT NULL,
    "message" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'QUEUED',
    "recipientCount" INTEGER NOT NULL DEFAULT 0,
    "queuedCount" INTEGER NOT NULL DEFAULT 0,
    "sentCount" INTEGER NOT NULL DEFAULT 0,
    "failedCount" INTEGER NOT NULL DEFAULT 0,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "SmsBroadcastRecipient" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "broadcastId" TEXT NOT NULL,
    "memberId" TEXT,
    "groupId" TEXT,
    "memberName" TEXT NOT NULL,
    "phone" TEXT NOT NULL,
    "provider" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'QUEUED',
    "providerReference" TEXT,
    "providerStatus" TEXT,
    "providerMessage" TEXT,
    "sentAt" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "SmsBroadcastRecipient_broadcastId_fkey" FOREIGN KEY ("broadcastId") REFERENCES "SmsBroadcast" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "MeetingStepRecord" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "meetingId" TEXT NOT NULL,
    "step" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'PENDING',
    "completedAt" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "MeetingStepRecord_meetingId_fkey" FOREIGN KEY ("meetingId") REFERENCES "Meeting" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "Attendance" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "meetingId" TEXT NOT NULL,
    "memberId" TEXT NOT NULL,
    "status" TEXT NOT NULL,
    "recordedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "Attendance_meetingId_fkey" FOREIGN KEY ("meetingId") REFERENCES "Meeting" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "Attendance_memberId_fkey" FOREIGN KEY ("memberId") REFERENCES "Member" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "FundAccount" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "type" TEXT NOT NULL,
    "balanceCents" INTEGER NOT NULL DEFAULT 0,
    "currency" TEXT NOT NULL DEFAULT 'KES',
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "FundAccount_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "LedgerEntry" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "memberId" TEXT,
    "meetingId" TEXT,
    "fundAccountId" TEXT,
    "type" TEXT NOT NULL,
    "amountCents" INTEGER NOT NULL,
    "currency" TEXT NOT NULL DEFAULT 'KES',
    "direction" TEXT NOT NULL,
    "description" TEXT NOT NULL,
    "signature" TEXT NOT NULL,
    "externalReference" TEXT,
    "clientRequestId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "LedgerEntry_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT "LedgerEntry_memberId_fkey" FOREIGN KEY ("memberId") REFERENCES "Member" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "LedgerEntry_meetingId_fkey" FOREIGN KEY ("meetingId") REFERENCES "Meeting" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "LedgerEntry_fundAccountId_fkey" FOREIGN KEY ("fundAccountId") REFERENCES "FundAccount" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "OfflineDevice" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "userId" TEXT,
    "deviceId" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'ACTIVE',
    "cacheExpiresAt" DATETIME NOT NULL,
    "lastPreparedAt" DATETIME,
    "lastSyncedAt" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "OfflineDevice_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "OfflineDevice_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "Vote" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "meetingId" TEXT,
    "resolutionType" TEXT NOT NULL,
    "motion" TEXT NOT NULL,
    "result" TEXT NOT NULL,
    "quorumRequired" INTEGER NOT NULL,
    "yesCount" INTEGER NOT NULL,
    "noCount" INTEGER NOT NULL,
    "abstainCount" INTEGER NOT NULL,
    "totalEligible" INTEGER NOT NULL,
    "hash" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "Vote_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "Vote_meetingId_fkey" FOREIGN KEY ("meetingId") REFERENCES "Meeting" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "CreditScore" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "score" INTEGER NOT NULL,
    "breakdownJson" TEXT NOT NULL,
    "computedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "CreditScore_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "AuditEvent" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "actorUserId" TEXT,
    "entityType" TEXT NOT NULL,
    "entityId" TEXT NOT NULL,
    "type" TEXT NOT NULL,
    "payloadJson" TEXT NOT NULL,
    "hash" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "AuditEvent_actorUserId_fkey" FOREIGN KEY ("actorUserId") REFERENCES "User" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "IntegrationConfig" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "provider" TEXT NOT NULL,
    "displayName" TEXT NOT NULL,
    "mode" TEXT NOT NULL DEFAULT 'SANDBOX',
    "enabled" BOOLEAN NOT NULL DEFAULT true,
    "requiredEnvJson" TEXT NOT NULL,
    "credentialsJson" TEXT,
    "credentialsUpdatedAt" DATETIME,
    "callbackUrl" TEXT,
    "lastCheckedAt" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "IntelliAuditStandardReference" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "key" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "category" TEXT NOT NULL,
    "jurisdiction" TEXT,
    "sourceUrl" TEXT NOT NULL,
    "summary" TEXT NOT NULL,
    "effectiveDate" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "IntelliAuditEvidenceSource" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "name" TEXT NOT NULL,
    "sourceType" TEXT NOT NULL,
    "provider" TEXT,
    "status" TEXT NOT NULL DEFAULT 'ACTIVE',
    "scopeType" TEXT NOT NULL DEFAULT 'GLOBAL',
    "scopeId" TEXT,
    "connectorConfigJson" TEXT,
    "lastSyncedAt" DATETIME,
    "createdByUserId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "IntelliAuditSourceDocument" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "sourceId" TEXT NOT NULL,
    "scopeType" TEXT NOT NULL DEFAULT 'GLOBAL',
    "scopeId" TEXT,
    "title" TEXT NOT NULL,
    "fileName" TEXT,
    "mimeType" TEXT,
    "sourceUri" TEXT,
    "contentHash" TEXT NOT NULL,
    "extractionStatus" TEXT NOT NULL DEFAULT 'PENDING',
    "rawMetadataJson" TEXT NOT NULL,
    "uploadedByUserId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "IntelliAuditSourceDocument_sourceId_fkey" FOREIGN KEY ("sourceId") REFERENCES "IntelliAuditEvidenceSource" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "IntelliAuditExtractedRecord" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "sourceId" TEXT,
    "documentId" TEXT,
    "recordType" TEXT NOT NULL,
    "occurredAt" DATETIME,
    "amountCents" INTEGER,
    "currency" TEXT NOT NULL DEFAULT 'KES',
    "direction" TEXT,
    "counterparty" TEXT,
    "reference" TEXT,
    "description" TEXT,
    "normalizedJson" TEXT NOT NULL,
    "hash" TEXT NOT NULL,
    "confidence" REAL NOT NULL DEFAULT 0.8,
    "status" TEXT NOT NULL DEFAULT 'STAGED',
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "IntelliAuditExtractedRecord_sourceId_fkey" FOREIGN KEY ("sourceId") REFERENCES "IntelliAuditEvidenceSource" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "IntelliAuditExtractedRecord_documentId_fkey" FOREIGN KEY ("documentId") REFERENCES "IntelliAuditSourceDocument" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "IntelliAuditConnectorSyncRun" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "sourceId" TEXT NOT NULL,
    "provider" TEXT NOT NULL,
    "status" TEXT NOT NULL,
    "startedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "completedAt" DATETIME,
    "importedRecordCount" INTEGER NOT NULL DEFAULT 0,
    "exceptionCount" INTEGER NOT NULL DEFAULT 0,
    "summaryJson" TEXT NOT NULL,
    "actorUserId" TEXT,
    CONSTRAINT "IntelliAuditConnectorSyncRun_sourceId_fkey" FOREIGN KEY ("sourceId") REFERENCES "IntelliAuditEvidenceSource" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "IntelliAuditReconciliationBatch" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "scopeType" TEXT NOT NULL DEFAULT 'GLOBAL',
    "scopeId" TEXT,
    "title" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'STAGED',
    "recordCount" INTEGER NOT NULL DEFAULT 0,
    "exceptionCount" INTEGER NOT NULL DEFAULT 0,
    "totalDebitCents" INTEGER NOT NULL DEFAULT 0,
    "totalCreditCents" INTEGER NOT NULL DEFAULT 0,
    "createdByUserId" TEXT,
    "approvedByUserId" TEXT,
    "approvedAt" DATETIME,
    "reviewerNotes" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "IntelliAuditReconciliationItem" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "batchId" TEXT NOT NULL,
    "extractedRecordId" TEXT,
    "ledgerEntryId" TEXT,
    "matchStatus" TEXT NOT NULL,
    "confidence" REAL NOT NULL DEFAULT 0.7,
    "exceptionJson" TEXT,
    "reviewerNotes" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "IntelliAuditReconciliationItem_batchId_fkey" FOREIGN KEY ("batchId") REFERENCES "IntelliAuditReconciliationBatch" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "IntelliAuditReconciliationItem_extractedRecordId_fkey" FOREIGN KEY ("extractedRecordId") REFERENCES "IntelliAuditExtractedRecord" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "IntelliAuditConversation" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "scopeType" TEXT NOT NULL DEFAULT 'GLOBAL',
    "scopeId" TEXT,
    "title" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'ACTIVE',
    "createdByUserId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "IntelliAuditMessage" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "conversationId" TEXT NOT NULL,
    "role" TEXT NOT NULL,
    "content" TEXT NOT NULL,
    "factualDataJson" TEXT NOT NULL,
    "assumptionsJson" TEXT NOT NULL,
    "observationsJson" TEXT NOT NULL,
    "recommendationsJson" TEXT NOT NULL,
    "evidenceRefsJson" TEXT NOT NULL,
    "unsupportedClaimsJson" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "IntelliAuditMessage_conversationId_fkey" FOREIGN KEY ("conversationId") REFERENCES "IntelliAuditConversation" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "IntelliAuditFinding" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "scopeType" TEXT NOT NULL DEFAULT 'GLOBAL',
    "scopeId" TEXT,
    "severity" TEXT NOT NULL,
    "category" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "observation" TEXT NOT NULL,
    "recommendation" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'OPEN',
    "evidenceRefsJson" TEXT NOT NULL,
    "sourceIdsJson" TEXT NOT NULL,
    "createdByUserId" TEXT,
    "reportDraftId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "IntelliAuditRecommendation" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "findingId" TEXT,
    "scopeType" TEXT NOT NULL DEFAULT 'GLOBAL',
    "scopeId" TEXT,
    "title" TEXT NOT NULL,
    "action" TEXT NOT NULL,
    "priority" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'OPEN',
    "dueDate" DATETIME,
    "ownerUserId" TEXT,
    "createdByUserId" TEXT,
    "approvedByUserId" TEXT,
    "approvedAt" DATETIME,
    "evidenceRefsJson" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "IntelliAuditReportDraft" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "scopeType" TEXT NOT NULL DEFAULT 'GLOBAL',
    "scopeId" TEXT,
    "templateKey" TEXT NOT NULL,
    "standard" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'DRAFT',
    "periodStart" DATETIME,
    "periodEnd" DATETIME,
    "generatedByUserId" TEXT,
    "approvedByUserId" TEXT,
    "approvedAt" DATETIME,
    "approvalNotes" TEXT,
    "contentJson" TEXT NOT NULL,
    "auditTrailRefsJson" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "IntelliAuditReportApproval" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "reportDraftId" TEXT NOT NULL,
    "actorUserId" TEXT NOT NULL,
    "action" TEXT NOT NULL,
    "notes" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "IntelliAuditReportApproval_reportDraftId_fkey" FOREIGN KEY ("reportDraftId") REFERENCES "IntelliAuditReportDraft" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "IntelliAuditReportAuditReference" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "reportDraftId" TEXT NOT NULL,
    "entityType" TEXT NOT NULL,
    "entityId" TEXT NOT NULL,
    "auditEventId" TEXT,
    "evidenceDocumentId" TEXT,
    "extractedRecordId" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "IntelliAuditReportAuditReference_reportDraftId_fkey" FOREIGN KEY ("reportDraftId") REFERENCES "IntelliAuditReportDraft" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "IntelliAuditReportAuditReference_evidenceDocumentId_fkey" FOREIGN KEY ("evidenceDocumentId") REFERENCES "IntelliAuditSourceDocument" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "IntelliAuditReportAuditReference_extractedRecordId_fkey" FOREIGN KEY ("extractedRecordId") REFERENCES "IntelliAuditExtractedRecord" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "IntelliAuditOfflineAction" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "clientActionId" TEXT NOT NULL,
    "userId" TEXT,
    "actionType" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'QUEUED',
    "payloadJson" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "syncedAt" DATETIME
);

-- CreateTable
CREATE TABLE "WebhookSubscription" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "partnerId" TEXT,
    "url" TEXT NOT NULL,
    "eventTypesJson" TEXT NOT NULL,
    "secretHash" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'ACTIVE',
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "WebhookSubscription_partnerId_fkey" FOREIGN KEY ("partnerId") REFERENCES "Partner" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "FtmaImportBatch" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "sourceFile" TEXT NOT NULL,
    "importedAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "summaryJson" TEXT NOT NULL
);

-- CreateTable
CREATE TABLE "FtmaCountyVslaKpi" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "county" TEXT NOT NULL,
    "metricDate" DATETIME,
    "vslaGroupCount" INTEGER NOT NULL,
    "membershipCount" INTEGER NOT NULL,
    "nhifUptakeRate" REAL,
    "externalLoanUptakeRate" REAL,
    "actionableMarketingPlanRate" REAL,
    "savingsCents" BIGINT NOT NULL,
    "outstandingLoanCents" BIGINT NOT NULL,
    "socialFundCents" BIGINT NOT NULL,
    "sourceRowJson" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- CreateTable
CREATE TABLE "FtmaCountyVslaTrainingMetric" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "county" TEXT NOT NULL,
    "assessedVslaCount" INTEGER NOT NULL,
    "newGroupsCount" INTEGER NOT NULL,
    "bdsModulesCount" INTEGER NOT NULL,
    "nhifSensitizedCount" INTEGER NOT NULL,
    "linkedToMarketCount" INTEGER NOT NULL,
    "linkedToFinanceCount" INTEGER NOT NULL,
    "marketLinkageCount" INTEGER NOT NULL,
    "inputDistributorLinkageCount" INTEGER NOT NULL,
    "valueAdditionTrainingCount" INTEGER NOT NULL,
    "sourceRowJson" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- CreateTable
CREATE TABLE "FtmaCountyFscKpi" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "county" TEXT NOT NULL,
    "fscBdsModulesCount" INTEGER NOT NULL,
    "actionableBusinessPlanRate" REAL,
    "nhifMembershipRate" REAL,
    "financialInstitutionLinkages" INTEGER NOT NULL,
    "marketLinkages" INTEGER NOT NULL,
    "inputDistributorLinkages" INTEGER NOT NULL,
    "otherTrainings" INTEGER NOT NULL,
    "sourceRowJson" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- CreateTable
CREATE TABLE "FtmaPartnerLinkage" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "rowNumber" INTEGER NOT NULL,
    "dateText" TEXT,
    "projectOfficer" TEXT,
    "institutionName" TEXT NOT NULL,
    "county" TEXT,
    "constituency" TEXT,
    "valueProposition" TEXT,
    "capacity" TEXT,
    "linkageType" TEXT,
    "contactName" TEXT,
    "contactPhone" TEXT,
    "sourceRowJson" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- CreateTable
CREATE TABLE "ExternalLoanProduct" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "partnerId" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "slug" TEXT NOT NULL,
    "category" TEXT NOT NULL DEFAULT 'WORKING_CAPITAL',
    "description" TEXT NOT NULL,
    "minAmountCents" INTEGER NOT NULL,
    "maxAmountCents" INTEGER NOT NULL,
    "interestRateBps" INTEGER NOT NULL,
    "interestPeriod" TEXT NOT NULL DEFAULT 'PER_YEAR',
    "termMonths" INTEGER NOT NULL,
    "repaymentFrequency" TEXT NOT NULL DEFAULT 'MONTHLY',
    "minCreditBand" TEXT,
    "requirements" TEXT,
    "status" TEXT NOT NULL DEFAULT 'ACTIVE',
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "ExternalLoanProduct_partnerId_fkey" FOREIGN KEY ("partnerId") REFERENCES "Partner" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "ExternalLoanApplication" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "productId" TEXT NOT NULL,
    "groupId" TEXT NOT NULL,
    "meetingId" TEXT,
    "requesterUserId" TEXT,
    "amountCents" INTEGER NOT NULL,
    "purpose" TEXT NOT NULL,
    "creditBand" TEXT,
    "status" TEXT NOT NULL DEFAULT 'PENDING',
    "reviewNotes" TEXT,
    "decidedAt" DATETIME,
    "disbursedAt" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "ExternalLoanApplication_productId_fkey" FOREIGN KEY ("productId") REFERENCES "ExternalLoanProduct" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "ExternalLoanApplication_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "ExternalLoanApplication_meetingId_fkey" FOREIGN KEY ("meetingId") REFERENCES "Meeting" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "ExternalLoanApplication_requesterUserId_fkey" FOREIGN KEY ("requesterUserId") REFERENCES "User" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "GroupPayment" (
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
    CONSTRAINT "GroupPayment_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "GroupPayment_memberId_fkey" FOREIGN KEY ("memberId") REFERENCES "Member" ("id") ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT "GroupPayment_meetingId_fkey" FOREIGN KEY ("meetingId") REFERENCES "Meeting" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "Poll" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "meetingId" TEXT,
    "type" TEXT NOT NULL DEFAULT 'DECISION',
    "title" TEXT NOT NULL,
    "description" TEXT,
    "targetRole" TEXT,
    "status" TEXT NOT NULL DEFAULT 'OPEN',
    "secretBallot" BOOLEAN NOT NULL DEFAULT false,
    "createdByUserId" TEXT,
    "closesAt" DATETIME,
    "closedAt" DATETIME,
    "resultSummary" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "Poll_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "Poll_meetingId_fkey" FOREIGN KEY ("meetingId") REFERENCES "Meeting" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "PollOption" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "pollId" TEXT NOT NULL,
    "label" TEXT NOT NULL,
    "memberId" TEXT,
    "position" INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT "PollOption_pollId_fkey" FOREIGN KEY ("pollId") REFERENCES "Poll" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "PollOption_memberId_fkey" FOREIGN KEY ("memberId") REFERENCES "Member" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "PollVote" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "pollId" TEXT NOT NULL,
    "optionId" TEXT NOT NULL,
    "memberId" TEXT NOT NULL,
    "castAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "PollVote_pollId_fkey" FOREIGN KEY ("pollId") REFERENCES "Poll" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "PollVote_optionId_fkey" FOREIGN KEY ("optionId") REFERENCES "PollOption" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "PollVote_memberId_fkey" FOREIGN KEY ("memberId") REFERENCES "Member" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "GroupJoinRequest" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "groupId" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "requestedName" TEXT NOT NULL,
    "phone" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'PENDING',
    "memberId" TEXT,
    "reviewNotes" TEXT,
    "decidedByUserId" TEXT,
    "decidedAt" DATETIME,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL,
    CONSTRAINT "GroupJoinRequest_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "GroupJoinRequest_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "UserMembership" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "userId" TEXT NOT NULL,
    "memberId" TEXT NOT NULL,
    "groupId" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "UserMembership_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "UserMembership_memberId_fkey" FOREIGN KEY ("memberId") REFERENCES "Member" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "UserMembership_groupId_fkey" FOREIGN KEY ("groupId") REFERENCES "Group" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateIndex
CREATE UNIQUE INDEX "User_email_key" ON "User"("email");

-- CreateIndex
CREATE UNIQUE INDEX "User_phone_key" ON "User"("phone");

-- CreateIndex
CREATE UNIQUE INDEX "User_memberId_key" ON "User"("memberId");

-- CreateIndex
CREATE INDEX "Notification_userId_readAt_createdAt_idx" ON "Notification"("userId", "readAt", "createdAt");

-- CreateIndex
CREATE UNIQUE INDEX "Session_tokenHash_key" ON "Session"("tokenHash");

-- CreateIndex
CREATE UNIQUE INDEX "ApiKey_tokenHash_key" ON "ApiKey"("tokenHash");

-- CreateIndex
CREATE UNIQUE INDEX "RolePermissionTemplate_role_key" ON "RolePermissionTemplate"("role");

-- CreateIndex
CREATE UNIQUE INDEX "Programme_publicSlug_key" ON "Programme"("publicSlug");

-- CreateIndex
CREATE UNIQUE INDEX "PartnerWallet_partnerId_key" ON "PartnerWallet"("partnerId");

-- CreateIndex
CREATE UNIQUE INDEX "PartnerWalletTransaction_providerReference_key" ON "PartnerWalletTransaction"("providerReference");

-- CreateIndex
CREATE UNIQUE INDEX "PartnerWalletTransaction_internalReference_key" ON "PartnerWalletTransaction"("internalReference");

-- CreateIndex
CREATE UNIQUE INDEX "PaymentWebhookEvent_eventId_key" ON "PaymentWebhookEvent"("eventId");

-- CreateIndex
CREATE UNIQUE INDEX "ProgrammePartner_programmeId_partnerId_role_key" ON "ProgrammePartner"("programmeId", "partnerId", "role");

-- CreateIndex
CREATE UNIQUE INDEX "ProgrammeGroup_programmeId_groupId_key" ON "ProgrammeGroup"("programmeId", "groupId");

-- CreateIndex
CREATE UNIQUE INDEX "StoreProduct_slug_key" ON "StoreProduct"("slug");

-- CreateIndex
CREATE UNIQUE INDEX "StoreProductProgramme_productId_programmeId_key" ON "StoreProductProgramme"("productId", "programmeId");

-- CreateIndex
CREATE UNIQUE INDEX "StoreProductProgrammeAgent_productProgrammeId_villageAgentId_key" ON "StoreProductProgrammeAgent"("productProgrammeId", "villageAgentId");

-- CreateIndex
CREATE INDEX "StoreCreditInstallment_dueDate_status_idx" ON "StoreCreditInstallment"("dueDate", "status");

-- CreateIndex
CREATE UNIQUE INDEX "StoreCreditInstallment_requestId_sequence_key" ON "StoreCreditInstallment"("requestId", "sequence");

-- CreateIndex
CREATE UNIQUE INDEX "Group_code_key" ON "Group"("code");

-- CreateIndex
CREATE UNIQUE INDEX "MeetingKeySubmission_meetingId_memberId_key" ON "MeetingKeySubmission"("meetingId", "memberId");

-- CreateIndex
CREATE INDEX "MemberPinDelivery_memberId_createdAt_idx" ON "MemberPinDelivery"("memberId", "createdAt");

-- CreateIndex
CREATE INDEX "MemberPinDelivery_status_createdAt_idx" ON "MemberPinDelivery"("status", "createdAt");

-- CreateIndex
CREATE INDEX "SmsBroadcast_createdAt_idx" ON "SmsBroadcast"("createdAt");

-- CreateIndex
CREATE INDEX "SmsBroadcast_targetType_createdAt_idx" ON "SmsBroadcast"("targetType", "createdAt");

-- CreateIndex
CREATE INDEX "SmsBroadcast_targetGroupId_createdAt_idx" ON "SmsBroadcast"("targetGroupId", "createdAt");

-- CreateIndex
CREATE INDEX "SmsBroadcast_targetMemberId_createdAt_idx" ON "SmsBroadcast"("targetMemberId", "createdAt");

-- CreateIndex
CREATE INDEX "SmsBroadcastRecipient_broadcastId_idx" ON "SmsBroadcastRecipient"("broadcastId");

-- CreateIndex
CREATE INDEX "SmsBroadcastRecipient_memberId_createdAt_idx" ON "SmsBroadcastRecipient"("memberId", "createdAt");

-- CreateIndex
CREATE INDEX "SmsBroadcastRecipient_groupId_createdAt_idx" ON "SmsBroadcastRecipient"("groupId", "createdAt");

-- CreateIndex
CREATE INDEX "SmsBroadcastRecipient_status_createdAt_idx" ON "SmsBroadcastRecipient"("status", "createdAt");

-- CreateIndex
CREATE UNIQUE INDEX "MeetingStepRecord_meetingId_step_key" ON "MeetingStepRecord"("meetingId", "step");

-- CreateIndex
CREATE UNIQUE INDEX "Attendance_meetingId_memberId_key" ON "Attendance"("meetingId", "memberId");

-- CreateIndex
CREATE UNIQUE INDEX "FundAccount_groupId_type_key" ON "FundAccount"("groupId", "type");

-- CreateIndex
CREATE UNIQUE INDEX "LedgerEntry_clientRequestId_key" ON "LedgerEntry"("clientRequestId");

-- CreateIndex
CREATE INDEX "OfflineDevice_status_cacheExpiresAt_idx" ON "OfflineDevice"("status", "cacheExpiresAt");

-- CreateIndex
CREATE UNIQUE INDEX "OfflineDevice_groupId_deviceId_key" ON "OfflineDevice"("groupId", "deviceId");

-- CreateIndex
CREATE UNIQUE INDEX "IntegrationConfig_provider_key" ON "IntegrationConfig"("provider");

-- CreateIndex
CREATE UNIQUE INDEX "IntelliAuditStandardReference_key_key" ON "IntelliAuditStandardReference"("key");

-- CreateIndex
CREATE UNIQUE INDEX "IntelliAuditOfflineAction_clientActionId_key" ON "IntelliAuditOfflineAction"("clientActionId");

-- CreateIndex
CREATE UNIQUE INDEX "FtmaCountyVslaKpi_county_metricDate_key" ON "FtmaCountyVslaKpi"("county", "metricDate");

-- CreateIndex
CREATE UNIQUE INDEX "FtmaCountyVslaTrainingMetric_county_key" ON "FtmaCountyVslaTrainingMetric"("county");

-- CreateIndex
CREATE UNIQUE INDEX "FtmaCountyFscKpi_county_key" ON "FtmaCountyFscKpi"("county");

-- CreateIndex
CREATE UNIQUE INDEX "FtmaPartnerLinkage_rowNumber_institutionName_key" ON "FtmaPartnerLinkage"("rowNumber", "institutionName");

-- CreateIndex
CREATE UNIQUE INDEX "ExternalLoanProduct_slug_key" ON "ExternalLoanProduct"("slug");

-- CreateIndex
CREATE INDEX "ExternalLoanApplication_groupId_status_idx" ON "ExternalLoanApplication"("groupId", "status");

-- CreateIndex
CREATE INDEX "ExternalLoanApplication_productId_status_idx" ON "ExternalLoanApplication"("productId", "status");

-- CreateIndex
CREATE UNIQUE INDEX "GroupPayment_internalReference_key" ON "GroupPayment"("internalReference");

-- CreateIndex
CREATE UNIQUE INDEX "GroupPayment_clientRequestId_key" ON "GroupPayment"("clientRequestId");

-- CreateIndex
CREATE INDEX "GroupPayment_groupId_status_idx" ON "GroupPayment"("groupId", "status");

-- CreateIndex
CREATE INDEX "GroupPayment_providerReference_idx" ON "GroupPayment"("providerReference");

-- CreateIndex
CREATE INDEX "Poll_groupId_status_idx" ON "Poll"("groupId", "status");

-- CreateIndex
CREATE INDEX "PollOption_pollId_idx" ON "PollOption"("pollId");

-- CreateIndex
CREATE INDEX "PollVote_optionId_idx" ON "PollVote"("optionId");

-- CreateIndex
CREATE UNIQUE INDEX "PollVote_pollId_memberId_key" ON "PollVote"("pollId", "memberId");

-- CreateIndex
CREATE INDEX "GroupJoinRequest_groupId_status_idx" ON "GroupJoinRequest"("groupId", "status");

-- CreateIndex
CREATE UNIQUE INDEX "GroupJoinRequest_groupId_userId_key" ON "GroupJoinRequest"("groupId", "userId");

-- CreateIndex
CREATE UNIQUE INDEX "UserMembership_memberId_key" ON "UserMembership"("memberId");

-- CreateIndex
CREATE INDEX "UserMembership_userId_idx" ON "UserMembership"("userId");

-- CreateIndex
CREATE UNIQUE INDEX "UserMembership_userId_groupId_key" ON "UserMembership"("userId", "groupId");

