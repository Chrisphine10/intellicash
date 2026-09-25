/**
 * Server Services — Public API
 *
 * This module re-exports every service in the IntelliCash API. Import from
 * here rather than reaching into individual service files, so callers get a
 * single stable surface to depend on and the internal layout can change
 * without touching every import.
 *
 * @example
 * ```ts
 * import { accountScope, creditRating, wallet } from "./server";
 * ```
 */

// ---------------------------------------------------------------------------
// Account scoping & authorization
// ---------------------------------------------------------------------------
export {
  groupScopeForUser,
  programmeScopeForUser,
  partnerScopeForUser,
  villageAgentScopeForUser,
  memberScopeForUser,
  ledgerScopeForUser,
  scopeGroupWhere,
  assertGroupAccess,
  callerIsDemo,
  demoExclusionForUser
} from "../services/account-scope";

// ---------------------------------------------------------------------------
// Credit rating
// ---------------------------------------------------------------------------
export {
  gatherCreditFacts,
  computeCreditRating,
  computeAndStoreCreditRating,
  latestCreditRating
} from "../services/credit-rating-service";

// ---------------------------------------------------------------------------
// Cycles
// ---------------------------------------------------------------------------
export {
  ensureActiveCycle,
  assertCycleWritable,
  assertMeetingWritable,
  closeCycleAndOpenNext,
  closeCycleWithin,
  assertMayManageCycles,
  listCycles,
  CYCLE_ACTIVE,
  CYCLE_CLOSED
} from "../services/cycle-service";

// ---------------------------------------------------------------------------
// Groups & members
// ---------------------------------------------------------------------------
export {
  ensureGroupForLogin,
  normaliseGroupName,
  AUTO_GROUP_SOURCE,
  type GroupLinkOutcome
} from "../services/group-login-link";

export {
  linkGroupChampion,
  type ChampionLinkOutcome
} from "../services/group-champion-service";

export {
  completeGroupPayment,
  failGroupPayment
} from "../services/group-payment-service";

export {
  generateGroupCode,
  countyCode
} from "../services/group-code";

// ---------------------------------------------------------------------------
// Loans & wallets
// ---------------------------------------------------------------------------
export {
  loadLoanPositions,
  type LoanPositionScope
} from "../services/loan-position-service";

export {
  availableCents,
  ensureWallet,
  holdFunds,
  releaseHold,
  settleHeldDebit,
  debitAvailable,
  creditBalance,
  recordWalletTransaction
} from "../services/wallet-service";

// ---------------------------------------------------------------------------
// Payments
// ---------------------------------------------------------------------------
export {
  initiateIncomingPayment,
  initiatePayout,
  completeIncomingTransaction,
  failIncomingTransaction,
  completeWithdrawal,
  failWithdrawal,
  rejectWithdrawal,
  verifyPaystackSignature,
  type PaymentProvider,
  type PaymentTransactionType
} from "../services/payment-service";

// ---------------------------------------------------------------------------
// SMS & notifications
// ---------------------------------------------------------------------------
export {
  dispatchAfterResponse,
  dispatchSms,
  type OutboundSmsKind
} from "../services/outbound-sms-service";

export {
  createNotification,
  createNotifications,
  notificationSmsEnabled,
  sendNotificationSms,
  type NotificationInput
} from "../services/notification-service";

export {
  notifySharePurchases,
  sendMeetingSummaries
} from "../services/meeting-sms-service";

export {
  credentialsFor,
  isSmsProvider,
  requiredCredentialKeys,
  smsProviders,
  findSmsIntegration,
  resolveSmsProvider
} from "../services/sms-provider";

export {
  sendSms,
  isSendableSmsPhone,
  renderSmsTemplate,
  normalizeSmsPhone,
  type SmsProvider
} from "../services/sms-service";

// ---------------------------------------------------------------------------
// Visits & assessments
// ---------------------------------------------------------------------------
export {
  submitGroupVisit,
  amendGroupVisit,
  serializeVisit,
  type SubmitVisitInput
} from "../services/visit-service";

export {
  submitVisitAssessment,
  readVisitAssessment,
  previewTemplate,
  publishTemplate,
  cloneTemplate,
  currentSnapshot,
  checksumSnapshot,
  draftFromTemplate
} from "../services/visit-assessment-service";

// ---------------------------------------------------------------------------
// IntelliAudit
// ---------------------------------------------------------------------------
export {
  generateIntelliAuditLlmResponse,
  type IntelliAuditLlmInput
} from "../services/intelliaudit-llm";

// ---------------------------------------------------------------------------
// Members & PINs
// ---------------------------------------------------------------------------
export {
  buildMemberPassbook,
  buildMemberOverview,
  type MemberPassbook
} from "../services/member-passbook-service";

export {
  generateMemberPin,
  generateMemberOtp,
  otpExpiresAt,
  sendQueuedMemberPinDelivery,
  generateAndQueueMemberPin,
  generateAndQueueMemberOtp,
  serializeMemberPinDelivery
} from "../services/member-pin-service";

export {
  requestLoginOtp,
  verifyLoginOtp,
  type LoginOtpPurpose,
  type VerifyLoginOtpResult
} from "../services/login-otp-service";

export {
  reconcileMembership,
  listMemberships,
  setActiveMembership,
  linkMembership,
  type MembershipSummary
} from "../services/membership-service";

// ---------------------------------------------------------------------------
// Village agents & programmes
// ---------------------------------------------------------------------------
export {
  setAgentProgrammes,
  agentProgrammeIds,
  resolveAgentProgramme,
  type ProgrammeAssignment
} from "../services/village-agent-service";

// ---------------------------------------------------------------------------
// Reports
// ---------------------------------------------------------------------------
export {
  buildMealReport,
  buildGroupMeal,
  ASSESSMENT_FRESHNESS_DAYS
} from "../services/meal-report";

export {
  buildProgrammePerformanceReport,
  SMALL_GROUP_THRESHOLD
} from "../services/programme-performance-report";

// ---------------------------------------------------------------------------
// Roles & permissions
// ---------------------------------------------------------------------------
export {
  getRolePermissionMap,
  permissionsForRoleFromStore,
  hasStoredPermission,
  updateRolePermissionTemplate,
  ensureRolePermissionTemplates,
  normalizePermissionList,
  validateRolePermissionUpdate
} from "../services/role-permission-service";

// ---------------------------------------------------------------------------
// Attachments & storage
// ---------------------------------------------------------------------------
export {
  attachmentDirectory,
  attachmentStoragePath,
  resolveAttachmentPath,
  attachmentUrl,
  hashFile,
  removeAttachmentFile
} from "../services/attachment-storage";

// ---------------------------------------------------------------------------
// Integrations & credentials
// ---------------------------------------------------------------------------
export {
  encryptCredentials,
  decryptCredentials,
  sanitizeCredentials,
  getStoredCredentialContext,
  type CredentialMap
} from "../services/integration-credentials";

// ---------------------------------------------------------------------------
// Support & needs
// ---------------------------------------------------------------------------
export {
  ensureSupportNeedTypes
} from "../services/support-need-service";

// ---------------------------------------------------------------------------
// Share-out
// ---------------------------------------------------------------------------
export {
  recordPhoneShareOut,
  lineArithmeticProblems,
  shareDifferences,
  type ShareOutRecordInput
} from "../services/share-out-record-service";

// ---------------------------------------------------------------------------
// Audit
// ---------------------------------------------------------------------------
export {
  appendAuditEvent
} from "../services/audit-service";

// ---------------------------------------------------------------------------
// Restore bundles
// ---------------------------------------------------------------------------
export {
  buildRestoreBundle
} from "../services/restore-bundle-service";

// ---------------------------------------------------------------------------
// Storage safety
// ---------------------------------------------------------------------------
export {
  judgeStorage,
  checkUploadStorage,
  STORAGE_LOW_FRACTION,
  STORAGE_CRITICAL_FRACTION,
  type StorageLevel
} from "../services/storage-guard";

// ---------------------------------------------------------------------------
// Assessment templates
// ---------------------------------------------------------------------------
export {
  ensureAssessmentTemplate
} from "../services/assessment-template-bootstrap";

// ---------------------------------------------------------------------------
// Admin SMS broadcasts
// ---------------------------------------------------------------------------
export {
  listSmsBroadcasts,
  createSmsBroadcast,
  serializeSmsBroadcast,
  type SmsBroadcastWithRecipients
} from "../services/admin-sms-service";
