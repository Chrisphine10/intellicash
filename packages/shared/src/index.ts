export const roles = [
  "IWL_ADMIN",
  "PARTNER_OFFICER",
  "GROUP_ACCOUNT",
  "MEMBER",
  "LENDER",
  /** Village Agent / Community-Based Trainer: supports a caseload of groups. */
  "VILLAGE_AGENT",
  "READ_ONLY"
] as const;

export type Role = (typeof roles)[number];

export const demoPassword = "IntellicashDemo#2026";

export const languagePreferences = [
  "ENGLISH",
  "KISWAHILI",
  "GIKUYU",
  "LUO",
  "KIEMBU"
] as const;
export type LanguagePreference = (typeof languagePreferences)[number];

export const languagePreferenceLabels: Record<LanguagePreference, string> = {
  ENGLISH: "English",
  KISWAHILI: "Kiswahili (Swahili)",
  GIKUYU: "Gikuyu (Kikuyu)",
  LUO: "Dholuo (Luo)",
  KIEMBU: "Kiembu (Embu)"
};

export const demoAccounts = [
  {
    name: "IWL Platform Admin",
    email: "admin@intellicash.co.ke",
    phone: "+254700000001",
    role: "IWL_ADMIN",
    scope: "Full platform access"
  },
  {
    name: "Partner Portfolio Officer",
    email: "partner@intellicash.co.ke",
    phone: "+254700000002",
    role: "PARTNER_OFFICER",
    scope: "Programme and partner workspace"
  },
  {
    name: "Lender Credit Analyst",
    email: "lender@intellicash.co.ke",
    phone: "+254700000003",
    role: "LENDER",
    scope: "Credit, portfolio, and audit review"
  },
  {
    name: "Tujijenge Group Account",
    email: "group@intellicash.co.ke",
    phone: "+254700000005",
    role: "GROUP_ACCOUNT",
    scope: "Group operations and meetings"
  },
  {
    name: "Mary Njeri",
    email: "member@intellicash.co.ke",
    phone: "+254700000006",
    role: "MEMBER",
    scope: "Member dashboard scope"
  },
  {
    // Grace is the agent the seed assigns groups to; without a login of her
    // own the whole agent experience — caseload, visits, agent report — could
    // not be reached at all.
    name: "Grace Wanjiku",
    email: "agent@intellicash.co.ke",
    phone: "+254700000007",
    role: "VILLAGE_AGENT",
    scope: "Field caseload and group support"
  },
  {
    name: "Read Only Auditor",
    email: "readonly@intellicash.co.ke",
    phone: "+254700000004",
    role: "READ_ONLY",
    scope: "Read-only oversight"
  }
] as const satisfies ReadonlyArray<{
  name: string;
  email: string;
  phone: string;
  role: Role;
  scope: string;
}>;

export const permissions = [
  "users:read",
  "users:write",
  "partners:read",
  "partners:write",
  "programmes:read",
  "programmes:write",
  "village-agents:read",
  "village-agents:write",
  "groups:read",
  "groups:write",
  "members:read",
  "members:write",
  "meetings:read",
  "meetings:write",
  "meeting-keys:write",
  "ledger:read",
  "ledger:write",
  "payments:read",
  "payments:write",
  "payments:approve",
  "store:read",
  "store:write",
  "signup-requests:read",
  "signup-requests:approve",
  "votes:read",
  "votes:write",
  "analytics:read",
  "audit:read",
  "intelliaudit:read",
  "intelliaudit:write",
  "intelliaudit:approve",
  "evidence:write",
  "connectors:sync",
  "reports:approve",
  "integrations:read",
  "integrations:write",
  "integrations:test",
  "api-keys:read",
  "api-keys:write",
  "webhooks:write"
] as const;

export type Permission = (typeof permissions)[number];

export const rolePermissions: Record<Role, Permission[]> = {
  IWL_ADMIN: [...permissions],
  PARTNER_OFFICER: [
    "partners:read",
    "programmes:read",
    "village-agents:read",
    "groups:read",
    "members:read",
    "meetings:read",
    "ledger:read",
    "payments:read",
    "payments:write",
    "store:read",
    "store:write",
    "votes:read",
    "analytics:read"
  ],
  GROUP_ACCOUNT: [
    "programmes:read",
    "groups:read",
    "members:read",
    "members:write",
    "meetings:read",
    "meetings:write",
    "meeting-keys:write",
    "ledger:read",
    "ledger:write",
    "store:read",
    "store:write",
    "votes:read",
    "votes:write",
    "analytics:read"
  ],
  MEMBER: [
    "programmes:read",
    "groups:read",
    "members:read",
    "meetings:read",
    "meeting-keys:write",
    "ledger:read",
    // A member casts their own ballot in group polls — reading and writing a
    // vote is theirs to do. It does NOT let them move money.
    "votes:read",
    "votes:write",
    "store:read",
    "store:write",
    "analytics:read"
  ],
  LENDER: [
    "programmes:read",
    "groups:read",
    "members:read",
    "ledger:read",
    "payments:read",
    "payments:write",
    "store:read",
    "store:write",
    "votes:read",
    "analytics:read"
  ],
  /**
   * A Village Agent / CBT supports and monitors their caseload of groups, and
   * distributes Intelli-Store products to them. Deliberately **read-only over
   * money**: no `ledger:write`, no `meetings:write`, no `meeting-keys:write` —
   * a group's own elected officials move its funds and hold its keys. The
   * agent onboards members and handles store distribution.
   */
  VILLAGE_AGENT: [
    "programmes:read",
    "village-agents:read",
    "groups:read",
    "members:read",
    "members:write",
    "meetings:read",
    "ledger:read",
    "votes:read",
    "store:read",
    "store:write",
    "analytics:read"
  ],
  READ_ONLY: [
    "partners:read",
    "programmes:read",
    "village-agents:read",
    "groups:read",
    "members:read",
    "meetings:read",
    "ledger:read",
    "votes:read",
    "analytics:read",
    "store:read"
  ],
};

export const groupPhases = [
  "MOBILISATION",
  "INTENSIVE",
  "DEVELOPMENT",
  "MATURITY",
  "POST_GRADUATION"
] as const;
export type GroupPhase = (typeof groupPhases)[number];

export const meetingStatuses = [
  "SCHEDULED",
  "KEY_UNLOCK_PENDING",
  "IN_PROGRESS",
  "SEALED",
  "SYNC_CONFLICT"
] as const;
export type MeetingStatus = (typeof meetingStatuses)[number];

export const meetingSteps = [
  "OPENING_AND_3_KEY_SECURITY",
  "MINUTES_REVIEW",
  "SOCIAL_FUND_ROUND",
  "LOAN_REPAYMENTS",
  "SHARE_PURCHASE",
  "LOAN_APPLICATIONS",
  "RESOLUTIONS_AND_GENERAL_VOTES",
  "CLOSING"
] as const;
export type MeetingStep = (typeof meetingSteps)[number];

export const meetingStepLabels: Record<MeetingStep, string> = {
  OPENING_AND_3_KEY_SECURITY: "Opening & 3-Key Security",
  MINUTES_REVIEW: "Minutes Review",
  SOCIAL_FUND_ROUND: "Social Fund Round",
  LOAN_REPAYMENTS: "Loan Repayments",
  SHARE_PURCHASE: "Share Purchase",
  LOAN_APPLICATIONS: "Loan Applications",
  RESOLUTIONS_AND_GENERAL_VOTES: "Resolutions & General Votes",
  CLOSING: "Closing"
};

export const fundTypes = [
  "INTERNAL_LOAN",
  "SOCIAL",
  "EXTERNAL_LOAN",
  "GRANT",
  "VSLF"
] as const;
export type FundType = (typeof fundTypes)[number];

export const ledgerEntryTypes = [
  "FINE_COLLECTION",
  "SOCIAL_CONTRIBUTION",
  "SOCIAL_GRANT",
  // Money spent OUT of the welfare fund during a cycle. What remains after
  // these is what gets shared out.
  "WELFARE_EXPENSE",
  "LOAN_REPAYMENT",
  "SHARE_PURCHASE",
  "INTERNAL_LOAN_DISBURSEMENT",
  "EXTERNAL_LOAN_RECEIPT",
  "EXTERNAL_LOAN_REPAYMENT",
  "GRANT_RECEIPT",
  "SHARE_OUT_PAYOUT",
  "VSLF_DEPOSIT"
] as const;
export type LedgerEntryType = (typeof ledgerEntryTypes)[number];

export const resolutionTypes = [
  "INTERNAL_LOAN_APPROVAL",
  "EXTERNAL_GROUP_LOAN_APPROVAL",
  "GRANT_APPLICATION_APPROVAL",
  "SOCIAL_FUND_GRANT",
  "OFFICER_ELECTION",
  "CONSTITUTION_AMENDMENT",
  "MEMBER_EXPULSION",
  "VSLF_FEDERATION_MEMBERSHIP",
  "FINE_WAIVER_REQUEST",
  "MINUTES_APPROVAL"
] as const;
export type ResolutionType = (typeof resolutionTypes)[number];

export const integrationProviders = [
  "MPESA_DARAJA",
  "AFRICAS_TALKING",
  "BONGA_SMS",
  "IPRS",
  "KCB_BUNI",
  "PAYSTACK",
  "TRANSUNION_CRB",
  "MFARM",
  "GOOGLE_MAPS"
] as const;
export type IntegrationProvider = (typeof integrationProviders)[number];

export const intelliAuditEvidenceSourceTypes = [
  "DATABASE",
  "MYSQL",
  "CSV",
  "EXCEL",
  "PDF",
  "BANK_STATEMENT",
  "MPESA_STATEMENT",
  "ACCOUNTING_SYSTEM",
  "ERP",
  "PAYROLL",
  "REST_API",
  "OPENAPI",
  "WEBHOOK",
  "MANUAL"
] as const;
export type IntelliAuditEvidenceSourceType = (typeof intelliAuditEvidenceSourceTypes)[number];

export const intelliAuditScopeTypes = ["GLOBAL", "PARTNER", "GROUP", "PROGRAMME"] as const;
export type IntelliAuditScopeType = (typeof intelliAuditScopeTypes)[number];

export const intelliAuditReportStandards = [
  "IFRS",
  "ISA",
  "IPSAS",
  "SACCO",
  "NGO_DONOR",
  "WORLD_BANK",
  "CGAP",
  "VSLA",
  "CUSTOM"
] as const;
export type IntelliAuditReportStandard = (typeof intelliAuditReportStandards)[number];

export const auditEventTypes = [
  "AUTH_LOGIN",
  "AUTH_REGISTERED",
  "MEMBER_ACCOUNT_CREATED",
  "AUTH_LOGOUT",
  "USER_CREATED",
  "USER_UPDATED",
  "USER_PROFILE_UPDATED",
  "USER_PASSWORD_UPDATED",
  "ROLE_PERMISSIONS_UPDATED",
  "API_KEY_CREATED",
  "API_KEY_REVOKED",
  "PARTNER_SIGNUP_REQUESTED",
  "PARTNER_SIGNUP_AGENT_ASSIGNED",
  "PARTNER_SIGNUP_FIELD_VISIT_RECORDED",
  "PARTNER_SIGNUP_APPROVED",
  "PARTNER_SIGNUP_REJECTED",
  "PARTNER_CREATED",
  "PARTNER_UPDATED",
  "PROGRAMME_CREATED",
  "PROGRAMME_UPDATED",
  "PROGRAMME_ASSET_CREATED",
  "PROGRAMME_ASSET_UPDATED",
  "PAYMENT_INITIATED",
  "PAYMENT_COMPLETED",
  "PAYMENT_FAILED",
  "STORE_PRODUCT_CREATED",
  "STORE_PRODUCT_UPDATED",
  "STORE_SUPPLIER_CREATED",
  "STORE_SUPPLIER_UPDATED",
  "STORE_CREDIT_REQUESTED",
  "STORE_CREDIT_REQUEST_UPDATED",
  "STORE_CREDIT_SCHEDULE_GENERATED",
  "STORE_CREDIT_REPAYMENT_POSTED",
  "AGENT_BOOKING_REQUESTED",
  "AGENT_BOOKING_REQUEST_UPDATED",
  "WITHDRAWAL_REQUESTED",
  "WITHDRAWAL_APPROVED",
  "WITHDRAWAL_REJECTED",
  "VA_CREATED",
  "GROUP_UPDATED",
  "GROUP_CREATED",
  "MEMBER_REGISTERED",
  "MEMBER_PIN_UPDATED",
  "MEMBER_PIN_DELIVERY_QUEUED",
  "MEMBER_OTP_UPDATED",
  "MEMBER_OTP_DELIVERY_QUEUED",
  "MEETING_OPENED",
  "MEETING_KEY_SUBMITTED",
  "MEETING_STEP_COMPLETED",
  "MEETING_SEALED",
  "LEDGER_ENTRY_APPENDED",
  "VOTE_RECORDED",
  "CREDIT_SCORE_COMPUTED",
  "GROUP_JOIN_REQUESTED",
  "GROUP_JOIN_APPROVED",
  "GROUP_JOIN_REJECTED",
  "GROUP_PAYMENT_INITIATED",
  "GROUP_PAYMENT_COMPLETED",
  "GROUP_PAYMENT_FAILED",
  "POLL_CREATED",
  "POLL_VOTE_CAST",
  "POLL_CLOSED",
  "EXTERNAL_LOAN_PRODUCT_CREATED",
  "EXTERNAL_LOAN_PRODUCT_UPDATED",
  "EXTERNAL_LOAN_APPLICATION_CREATED",
  "EXTERNAL_LOAN_APPLICATION_DECIDED",
  "EXTERNAL_LOAN_DISBURSED",
  "INTEGRATION_HEALTH_CHECKED",
  "INTEGRATION_CREDENTIALS_UPDATED",
  "WEBHOOK_SUBSCRIBED",
  "VA_UPDATED",
  "MEMBER_UPDATED",
  "MEETING_SCHEDULED",
  "MEETING_UPDATED",
  "ATTENDANCE_RECORDED",
  "INTELLIAUDIT_EVIDENCE_UPLOADED",
  "INTELLIAUDIT_EVIDENCE_EXTRACTED",
  "INTELLIAUDIT_CONNECTOR_SYNCED",
  "INTELLIAUDIT_AI_RESPONDED",
  "INTELLIAUDIT_RECOMMENDATION_UPDATED",
  "INTELLIAUDIT_REPORT_DRAFTED",
  "INTELLIAUDIT_REPORT_APPROVED",
  "INTELLIAUDIT_REPORT_EXPORTED",
  "INTELLIAUDIT_REQUEST_REJECTED",
  "INTELLIAUDIT_RECONCILIATION_STAGED",
  "INTELLIAUDIT_RECONCILIATION_APPROVED"
] as const;
export type AuditEventType = (typeof auditEventTypes)[number];

export const memberRoles = [
  "MEMBER",
  "CHAIRPERSON",
  "SECRETARY",
  "TREASURER",
  "MONEY_COUNTER",
  "KEY_HOLDER",
  "VILLAGE_AGENT"
] as const;
export type MemberRole = (typeof memberRoles)[number];

export interface ApiEnvelope<T> {
  data: T;
  meta?: Record<string, unknown>;
}

export interface ApiError {
  error: {
    code: string;
    message: string;
    details?: unknown;
    traceId?: string;
  };
}

export interface PortfolioSummary {
  groups: number;
  members: number;
  activeMeetings: number;
  totalSavingsCents: number;
  repaymentRate: number;
  averageCreditScore: number;
  phaseDistribution: Record<GroupPhase, number>;
  integrationConfigured: number;
  integrationTotal: number;
}
