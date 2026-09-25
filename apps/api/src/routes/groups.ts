import { Router } from "express";
import { z } from "zod";
import bcrypt from "bcryptjs";
import type { Prisma } from "@prisma/client";
import {
  fundTypes,
  groupPhases,
  ledgerEntryTypes,
  meetingStepLabels,
  meetingSteps,
  memberRoles,
  resolutionTypes,
  type FundType,
  type LedgerEntryType,
  type MeetingStep
} from "@intellicash/shared";
import { assertMeetingStepOrder } from "../domain/meeting-workflow";
import { MEETING_FREQUENCIES, isMeetingTime, meetingDaysLabel, nairobiDayBounds } from "../domain/meeting-schedule";
import { assertAppendOnlyOperation, signLedgerEntry } from "../domain/ledger";
import {
  computeAndStoreCreditRating,
  computeCreditRating,
  latestCreditRating
} from "../services/credit-rating-service";
import { canDisburse, normaliseInterestType } from "../domain/loan-math";
import { AMOUNT_TOO_LARGE_MESSAGE, MAX_CENTS, MAX_CENTS_LABEL } from "../domain/money";
import { allocateLargestRemainder } from "../domain/share-out";
import { loadLoanPositions } from "../services/loan-position-service";
import {
  assertMeetingWritable,
  ensureActiveCycle
} from "../services/cycle-service";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { appendAuditEvent } from "../services/audit-service";
import { createNotifications } from "../services/notification-service";
import { dispatchAfterResponse } from "../services/outbound-sms-service";
import { notifySharePurchases, sendMeetingSummaries } from "../services/meeting-sms-service";
import {
  generateAndQueueMemberOtp,
  generateAndQueueMemberPin,
  serializeMemberPinDelivery,
  sendQueuedMemberPinDelivery,
  type MemberPinDeliveryPublic
} from "../services/member-pin-service";
import { buildMemberOverview, buildMemberPassbook } from "../services/member-passbook-service";
import {
  assertGroupAccess,
  ledgerScopeForUser,
  memberScopeForUser,
  scopeGroupWhere
} from "../services/account-scope";
import { ApiHttpError, ok } from "../lib/http";
import { decryptJson, derivePinVerifier, sha256 } from "../lib/crypto";
import { canViewMemberContact, maskPhone } from "../lib/privacy";
import { looksLikePhone, normalisePhone, phoneTail, samePhone } from "../lib/phone";
import { linkMembership, MemberAlreadyLinkedError, reconcileMembership } from "../services/membership-service";
import { prisma } from "../lib/prisma";
import { assertModuleEnabled, modulesForGroup } from "../services/module-service";
import { assertFollowsGroupRules, groupRules } from "../services/group-rules-service";
import { assertMayCreateMemberLogin, memberAccountsEnabledFor, visibleMembershipsFor } from "../services/member-accounts-service";

const router = Router();
const credentialTransactionOptions = { timeout: 15_000 };

function routeParam(value: string | string[] | undefined, name: string) {
  if (typeof value === "string" && value.trim()) return value;
  throw new ApiHttpError(400, "INVALID_ROUTE_PARAM", `Missing route parameter: ${name}.`);
}

// Optional text and GPS fields accept `null`, which is what the console sends
// for a box left empty — and what clears a value on update. These used to be
// `.optional()` only, so saving any group with one blank field (most imported
// groups have no GPS) failed validation, and a group's location could not be
// set at all.
const groupCreateSchema = z.object({
  name: z.string().trim().min(2).max(120),
  code: z.string().trim().min(2).max(40),
  county: z.string().trim().min(2).max(80),
  phase: z.enum(groupPhases).default("MOBILISATION"),
  subCounty: z.string().trim().max(80).nullish(),
  location: z.string().trim().max(200).nullish(),
  composition: z.string().trim().max(300).nullish(),
  objective: z.string().trim().max(1000).nullish(),
  contactPersonName: z.string().trim().max(120).nullish(),
  contactPhone: z.string().trim().max(30).nullish(),
  onboardingFeedback: z.string().trim().max(2000).nullish(),
  meetingDay: z.string().trim().max(30).nullish(),
  gpsLatitude: z.number().min(-90).max(90).nullish(),
  gpsLongitude: z.number().min(-180).max(180).nullish(),
  gpsRadiusMeters: z.number().int().min(1).optional(),
  shareValueCents: z.number().int().min(1).optional(),
  maxSharesPerMemberPerMeeting: z.number().int().min(1).max(100).optional(),
  constitutionVersion: z.string().trim().optional(),
  cycleNumber: z.number().int().min(1).optional(),
  programmeIds: z.array(z.string()).default([]),
  villageAgentId: z.string().nullish()
});

const groupUpdateSchema = groupCreateSchema.partial().extend({
  programmeIds: z.array(z.string()).optional()
});

const memberCreateSchema = z.object({
  fullName: z.string().trim().min(2).max(120),
  // Digits, not punctuation — the same test sign-up and joining apply. `min(7)`
  // let "12345" through, and a number nobody can dial is not an identity.
  phone: z.string().trim().max(30).refine(looksLikePhone, "Enter a valid phone number."),
  role: z.enum(memberRoles).default("MEMBER"),
  kycStatus: z.enum(["PENDING", "VERIFIED", "REJECTED"]).default("PENDING"),
  status: z.enum(["ACTIVE", "INACTIVE", "SUSPENDED"]).default("ACTIVE"),
  nationalIdHash: z.string().optional()
});

// An edit keeps the older, looser rule: a member imported with a short number
// must stay editable for their name without being forced to change the number.
const memberUpdateSchema = memberCreateSchema
  .extend({ phone: z.string().trim().min(7).max(30) })
  .partial();
const pinRequestSchema = z.object({}).strict();

const meetingCreateSchema = z.object({
  title: z.string().trim().min(2).max(200),
  scheduledAt: z.string().datetime(),
  gpsCompliant: z.boolean().default(false),
  /**
   * A phone that starts a meeting sends this: if the group already has a
   * scheduled meeting that day with nothing recorded in it, that meeting is
   * the one being held, so it is returned instead of a duplicate being made.
   */
  adoptScheduled: z.boolean().optional(),
  /** PHONE for a meeting a treasurer holds on the phone. */
  source: z.enum(["MANUAL", "PHONE"]).optional()
});

const meetingCancelSchema = z.object({
  reason: z.string().trim().min(3).max(300)
});

const meetingPhoneLifecycleSchema = z.object({
  /** What a person did on the phone. The server never infers either one. */
  event: z.enum(["STARTED", "CLOSED"]),
  /** When they did it, by the phone's clock. */
  at: z.string().datetime()
});

const meetingScheduleSchema = z.object({
  frequency: z.enum(MEETING_FREQUENCIES),
  days: z.array(z.number().int().min(1).max(7)).min(1).max(7),
  time: z.string().refine(isMeetingTime, "Use 24-hour HH:mm, for example 14:00."),
  remindersEnabled: z.boolean().optional()
});

const meetingUpdateSchema = z.object({
  title: z.string().trim().min(2).max(200).optional(),
  scheduledAt: z.string().datetime().optional(),
  gpsCompliant: z.boolean().optional()
});

const meetingKeySubmissionSchema = z.object({
  memberId: z.string().optional(),
  /*
   * Four OR six digits, deliberately.
   *
   * A meeting PIN is now four, chosen by the member. But this same field also
   * carries a six-digit one-time code (`CURRENT_OTP`), and every member who set
   * a PIN before the change is still holding six digits. Narrowing this to four
   * would reject both and lock existing groups out of their own meetings.
   */
  pin: z.string().regex(/^\d{4}$|^\d{6}$/, "PIN must be 4 digits, or a 6-digit code."),
  credentialType: z.enum(["DEFAULT_PIN", "CURRENT_OTP"]).optional(),
  deviceId: z.string().trim().min(2).max(120).optional(),
  capturedOfflineAt: z.string().datetime().optional()
});

const meetingKeySubmissionBatchSchema = z.object({
  submissions: z.array(meetingKeySubmissionSchema).min(1).max(12)
});

const meetingOpenSchema = z.object({
  gpsCompliant: z.boolean().default(false),
  keySubmissions: z.array(meetingKeySubmissionSchema).default([])
});

const attendanceSchema = z.object({
  memberId: z.string(),
  status: z.enum(["PRESENT", "ABSENT", "LATE", "EXCUSED"]).default("PRESENT")
});

const attendanceBatchItemSchema = attendanceSchema.extend({
  clientRequestId: z.string().trim().min(4).max(120).optional()
});

/**
 * Closing a meeting requires an official to prove it is them.
 *
 * Sealing freezes the record: after it, the money and the minutes cannot be
 * changed. Until 2 Aug 2026 anyone holding a `meetings:write` session could do
 * that with an empty body — no PIN, no named official, nothing tying the
 * closure to a person who was actually in the room.
 *
 * The key submission is REQUIRED. Opening already demands officials' PINs, so
 * accepting an open-time submission as proof of closing would make the rule
 * vacuous — the PIN has to be entered now, to close this meeting.
 */
const meetingSealSchema = z.object({
  minutes: z.string().optional(),
  keySubmission: meetingKeySubmissionSchema
});

const ledgerCreateSchema = z.object({
  memberId: z.string().optional(),
  meetingId: z.string().optional(),
  fundAccountId: z.string(),
  type: z.enum(ledgerEntryTypes),
  amountCents: z.number().int().min(1).max(MAX_CENTS, AMOUNT_TOO_LARGE_MESSAGE),
  direction: z.enum(["CREDIT", "DEBIT"]),
  description: z.string().trim().min(2).max(500),
  externalReference: z.string().max(120).optional(),
  clientRequestId: z.string().trim().min(4).max(120).optional()
});

const meetingLedgerEntryTypes = [
  "SHARE_PURCHASE",
  "LOAN_REPAYMENT",
  "INTERNAL_LOAN_DISBURSEMENT",
  "SOCIAL_CONTRIBUTION",
  "FINE_COLLECTION",
  "WELFARE_EXPENSE",
  "SHARE_OUT_PAYOUT",
  "WELFARE_SHARE_OUT"
] as const;

/**
 * The terms a loan was agreed at, sent by the phone with its disbursement so
 * the server records THAT loan, not the group's current default: a treasurer
 * may pick a longer due date for one loan, and a rate changed after the loan
 * was made must not re-price it. Absent (older phones, the console), the
 * group's policy applies as before.
 */
const loanTermsSchema = z.object({
  termMonths: z.number().int().min(1).max(60),
  interestRateBps: z.number().int().min(0).max(5000),
  interestType: z.enum(["FLAT", "REDUCING"])
});
type LoanTerms = z.infer<typeof loanTermsSchema>;

const meetingLedgerEntrySchema = z.object({
  memberId: z.string(),
  type: z.enum(meetingLedgerEntryTypes),
  amountCents: z.number().int().min(1).max(MAX_CENTS, AMOUNT_TOO_LARGE_MESSAGE),
  loan: loanTermsSchema.optional(),
  description: z.string().trim().max(500).optional(),
  externalReference: z.string().max(120).optional(),
  clientRequestId: z.string().trim().min(4).max(120).optional()
});

const meetingLedgerBatchSchema = z.object({
  entries: z.array(meetingLedgerEntrySchema).min(1).max(250),
  /**
   * WEB when typed in the console, which checks the group's own rules. Phones
   * never send it: they enforce the rules themselves and are the book of
   * record, so their syncs are never refused (a refused sync would strand a
   * meeting held offline).
   */
  source: z.enum(["PHONE", "WEB"]).optional()
});

const offlineDevicePrepareSchema = z.object({
  deviceId: z.string().trim().min(2).max(120),
  cacheTtlHours: z.number().int().min(1).max(168).default(72),
  memberPins: z
    .array(
      z.object({
        memberId: z.string(),
        // Same both-lengths reasoning as the submission schema above.
        pin: z.string().regex(/^\d{4}$|^\d{6}$/)
      })
    )
    .min(1)
    .max(200)
});

const offlineDeviceRefreshSchema = z.object({
  deviceId: z.string().trim().min(2).max(120),
  cacheTtlHours: z.number().int().min(1).max(168).default(72)
});

const offlineDeviceStatusSchema = z.object({
  status: z.enum(["ACTIVE", "REVOKED"])
});

const offlineSyncSchema = z.object({
  deviceId: z.string().trim().min(2).max(120),
  gpsCompliant: z.boolean().default(false),
  keySubmissions: z.array(meetingKeySubmissionSchema).default([]),
  attendance: z.array(attendanceBatchItemSchema).default([]),
  ledgerEntries: z.array(meetingLedgerEntrySchema).default([])
});

const shareOutPreviewSchema = z.object({
  poolAmountCents: z.number().int().min(1).max(MAX_CENTS, AMOUNT_TOO_LARGE_MESSAGE),
  /**
   * Whether what REMAINS in the welfare fund is shared out too.
   *
   * Defaults TRUE, per the rule the welfare module is built around: the fund
   * is spent down during the cycle and whatever is left is distributed. It
   * pays through `WELFARE_SHARE_OUT`, which debits the SOCIAL fund, so the
   * money comes from the pot it actually sits in.
   *
   * Split EQUALLY, not pro-rata by shares. Welfare is mutual insurance —
   * everyone contributes the same and is covered the same — so weighting the
   * remainder by savings would hand the largest savers a fund they have no
   * greater claim on. A group that keeps its welfare float across cycles
   * sets this false.
   */
  distributeWelfare: z.boolean().default(true)
});

const shareOutPostSchema = shareOutPreviewSchema.extend({
  clientRequestPrefix: z.string().trim().min(3).max(80).optional(),
  description: z.string().trim().optional()
});

const otpBatchSchema = z.object({
  memberIds: z.array(z.string()).min(1).max(12)
});

const voteCreateSchema = z.object({
  meetingId: z.string().optional(),
  resolutionType: z.enum(resolutionTypes),
  motion: z.string().trim().min(2),
  result: z.enum(["PASSED", "FAILED", "TIED", "DEFERRED"]),
  quorumRequired: z.number().int().min(0).max(100),
  yesCount: z.number().int().min(0),
  noCount: z.number().int().min(0),
  abstainCount: z.number().int().min(0).default(0),
  totalEligible: z.number().int().min(1)
});

const officialMemberRoles = new Set(["CHAIRPERSON", "SECRETARY", "TREASURER", "MONEY_COUNTER", "KEY_HOLDER"]);

/** Money in messages a treasurer reads, not raw cents. */
function formatKesFromCents(cents: number) {
  return `KES ${(cents / 100).toLocaleString("en-KE", { minimumFractionDigits: 2 })}`;
}

const meetingLedgerRules: Record<
  (typeof meetingLedgerEntryTypes)[number],
  { fundType: FundType; direction: "CREDIT" | "DEBIT"; label: string }
> = {
  SHARE_PURCHASE: { fundType: "INTERNAL_LOAN", direction: "CREDIT", label: "Share purchase" },
  LOAN_REPAYMENT: { fundType: "INTERNAL_LOAN", direction: "CREDIT", label: "Loan repayment" },
  INTERNAL_LOAN_DISBURSEMENT: {
    fundType: "INTERNAL_LOAN",
    direction: "DEBIT",
    label: "Loan disbursement"
  },
  SOCIAL_CONTRIBUTION: { fundType: "SOCIAL", direction: "CREDIT", label: "Social fund contribution" },
  FINE_COLLECTION: { fundType: "SOCIAL", direction: "CREDIT", label: "Fine collection" },
  // Welfare spending. DEBIT against SOCIAL, so appendLedgerEntry's existing
  // overdraw guard refuses an expense larger than the welfare fund holds —
  // a group cannot spend welfare money it does not have.
  WELFARE_EXPENSE: { fundType: "SOCIAL", direction: "DEBIT", label: "Welfare expense" },
  SHARE_OUT_PAYOUT: { fundType: "INTERNAL_LOAN", direction: "DEBIT", label: "Share-out payout" },
  // The welfare remainder, paid from the fund it actually sits in. The
  // overdraw guard in appendLedgerEntry then does the right thing for free:
  // a group cannot distribute welfare money it has already spent.
  WELFARE_SHARE_OUT: { fundType: "SOCIAL", direction: "DEBIT", label: "Welfare share-out" }
};

const memberSelect = {
  id: true,
  groupId: true,
  fullName: true,
  phone: true,
  role: true,
  kycStatus: true,
  status: true,
  joinedAt: true,
  createdAt: true,
  updatedAt: true,
  pinHash: true,
  pinSetAt: true,
  pinUpdatedAt: true,
  currentOtpHash: true,
  currentOtpIssuedAt: true,
  currentOtpExpiresAt: true
} satisfies Prisma.MemberSelect;

// Members embedded in meetings, attendance, ledger entries, and share-out
// previews are identified by name only — no phone. Contact details are served
// solely by the roster endpoint, masked by role. (DATA_PROTECTION.md §3.)
const nestedMemberSelect = {
  id: true,
  groupId: true,
  fullName: true,
  role: true,
  kycStatus: true,
  status: true
} satisfies Prisma.MemberSelect;

const groupInclude = {
  programme: { include: { partner: true } },
  programmeLinks: {
    include: {
      programme: {
        include: {
          partner: true,
          partnerLinks: { include: { partner: true } },
          _count: { select: { groups: true, villageAgentLinks: true, partnerLinks: true, groupLinks: true } }
        }
      }
    },
    orderBy: { createdAt: "asc" }
  },
  villageAgent: true,
  fundAccounts: { orderBy: { type: "asc" } },
  creditScores: { orderBy: { computedAt: "desc" }, take: 1 },
  _count: { select: { members: true, meetings: true, votes: true, ledgerEntries: true } }
} satisfies Prisma.GroupInclude;

function serializeMember<
  T extends {
    phone?: string | null;
    pinHash?: string | null;
    pinSetAt?: Date | null;
    currentOtpHash?: string | null;
    currentOtpIssuedAt?: Date | null;
    currentOtpExpiresAt?: Date | null;
  }
>(member: T, options?: { viewerRole?: string | null; delivery?: MemberPinDeliveryPublic | null }) {
  const { pinHash: _pinHash, currentOtpHash: _currentOtpHash, ...safeMember } = member;
  const phone =
    typeof member.phone === "string" && !canViewMemberContact(options?.viewerRole)
      ? maskPhone(member.phone)
      : member.phone;
  const serialized = {
    ...safeMember,
    ...(typeof member.phone === "string" ? { phone } : {}),
    pinSet: Boolean(_pinHash),
    defaultPinSet: Boolean(_pinHash),
    pinSetAt: member.pinSetAt ?? null,
    currentOtpSet: Boolean(_currentOtpHash && member.currentOtpExpiresAt && member.currentOtpExpiresAt > new Date()),
    currentOtpIssuedAt: member.currentOtpIssuedAt ?? null,
    currentOtpExpiresAt: member.currentOtpExpiresAt ?? null
  };

  return options?.delivery
    ? { ...serialized, pinDelivery: serializeMemberPinDelivery(options.delivery) }
    : serialized;
}

function meetingInclude(user?: AuthenticatedUser) {
  const memberDetailScope = user?.role === "MEMBER" ? { memberId: user.memberId ?? "__no_access__" } : undefined;
  // What the sitting committed the group to. A member sees only their own,
  // matching how attendance, key submissions and the ledger already scope.
  const commitmentScope =
    user?.role === "MEMBER" ? { requesterUserId: user.id ?? "__no_access__" } : undefined;

  return {
    group: {
      select: {
        id: true,
        name: true,
        code: true,
        county: true,
        gpsLatitude: true,
        gpsLongitude: true,
        gpsRadiusMeters: true,
        shareValueCents: true,
        maxSharesPerMemberPerMeeting: true
      }
    },
    steps: { orderBy: { createdAt: "asc" } },
    attendance: {
      where: memberDetailScope,
      include: { member: { select: nestedMemberSelect } }
    },
    keySubmissions: {
      where: memberDetailScope,
      orderBy: { verifiedAt: "asc" },
      select: {
        id: true,
        meetingId: true,
        memberId: true,
        deviceId: true,
        capturedOfflineAt: true,
        credentialType: true,
        verifiedAt: true,
        member: { select: nestedMemberSelect },
        capturedByUser: { select: { id: true, name: true, role: true } }
      }
    },
    externalLoanApplications: {
      where: commitmentScope,
      orderBy: { createdAt: "asc" },
      select: {
        id: true,
        amountCents: true,
        purpose: true,
        status: true,
        creditBand: true,
        createdAt: true,
        product: { select: { id: true, name: true, category: true } },
        requester: { select: { id: true, name: true } }
      }
    },
    storeCreditRequests: {
      where: commitmentScope,
      orderBy: { createdAt: "asc" },
      select: {
        id: true,
        quantity: true,
        requestedAmountCents: true,
        depositCents: true,
        status: true,
        creditBand: true,
        createdAt: true,
        product: { select: { id: true, name: true } },
        requester: { select: { id: true, name: true } }
      }
    }
  } satisfies Prisma.MeetingInclude;
}

function meetingKeyMemberId(
  user: AuthenticatedUser | undefined,
  submission: z.infer<typeof meetingKeySubmissionSchema>
) {
  if (submission.memberId) return submission.memberId;
  if (user?.role === "MEMBER" && user.memberId) return user.memberId;

  throw new ApiHttpError(400, "MEMBER_REQUIRED", "A meeting key submission requires a member.");
}

async function verifyMeetingCredential(
  submission: z.infer<typeof meetingKeySubmissionSchema>,
  member: {
    pinHash: string | null;
    currentOtpHash: string | null;
    currentOtpExpiresAt: Date | null;
  }
) {
  if (submission.capturedOfflineAt && submission.credentialType === "CURRENT_OTP") {
    throw new ApiHttpError(400, "OFFLINE_OTP_NOT_ALLOWED", "Offline meeting unlocks must use the saved default PIN.");
  }

  const allowDefaultPin = !submission.credentialType || submission.credentialType === "DEFAULT_PIN";
  const allowCurrentOtp =
    !submission.capturedOfflineAt && (!submission.credentialType || submission.credentialType === "CURRENT_OTP");

  if (allowDefaultPin && member.pinHash && (await bcrypt.compare(submission.pin, member.pinHash))) {
    return "DEFAULT_PIN";
  }

  if (
    allowCurrentOtp &&
    member.currentOtpHash &&
    member.currentOtpExpiresAt &&
    member.currentOtpExpiresAt > new Date() &&
    (await bcrypt.compare(submission.pin, member.currentOtpHash))
  ) {
    return "CURRENT_OTP";
  }

  throw new ApiHttpError(400, "INVALID_MEMBER_CREDENTIAL", "One or more meeting PINs or OTPs are invalid.");
}

async function recordMeetingKeySubmission(
  tx: Prisma.TransactionClient,
  user: AuthenticatedUser | undefined,
  groupId: string,
  meetingId: string,
  submission: z.infer<typeof meetingKeySubmissionSchema>
) {
  const memberId = meetingKeyMemberId(user, submission);

  if (user?.role === "MEMBER" && user.memberId !== memberId) {
    throw new ApiHttpError(403, "FORBIDDEN", "Members can only submit their own meeting key.");
  }

  const member = await tx.member.findFirst({
    where: { id: memberId, groupId, status: "ACTIVE" },
    select: {
      id: true,
      fullName: true,
      pinHash: true,
      currentOtpHash: true,
      currentOtpExpiresAt: true
    }
  });

  if (!member) {
    throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist or is outside this group.");
  }

  const credentialType = await verifyMeetingCredential(submission, member);
  const capturedOfflineAt = submission.capturedOfflineAt ? new Date(submission.capturedOfflineAt) : undefined;
  const verifiedAt = new Date();

  const keySubmission = await tx.meetingKeySubmission.upsert({
    where: { meetingId_memberId: { meetingId, memberId } },
    create: {
      meetingId,
      memberId,
      capturedByUserId: user?.id ?? null,
      deviceId: submission.deviceId,
      capturedOfflineAt,
      credentialType,
      verifiedAt
    },
    update: {
      capturedByUserId: user?.id ?? null,
      deviceId: submission.deviceId,
      capturedOfflineAt,
      credentialType,
      verifiedAt
    },
    include: {
      member: { select: nestedMemberSelect },
      capturedByUser: { select: { id: true, name: true, role: true } }
    }
  });

  if (credentialType === "CURRENT_OTP") {
    await tx.member.update({
      where: { id: member.id },
      data: { currentOtpHash: null, currentOtpIssuedAt: null, currentOtpExpiresAt: null },
      select: { id: true }
    });
  }

  return keySubmission;
}

async function evaluateMeetingUnlock(tx: Prisma.TransactionClient, meetingId: string) {
  const submissions = await tx.meetingKeySubmission.findMany({
    where: { meetingId },
    include: { member: { select: { id: true, role: true, status: true } } }
  });
  const activeSubmissions = submissions.filter((submission) => submission.member.status === "ACTIVE");
  const distinctMemberIds = new Set(activeSubmissions.map((submission) => submission.memberId));
  const distinctOfficialIds = new Set(
    activeSubmissions
      .filter((submission) => officialMemberRoles.has(submission.member.role))
      .map((submission) => submission.memberId)
  );
  const officialsVerified = distinctOfficialIds.size;
  const membersVerified = distinctMemberIds.size;
  const canOpen = officialsVerified >= 3 || membersVerified >= 5;
  const unlockStatus =
    officialsVerified >= 3 ? "OFFICIALS_VERIFIED" : membersVerified >= 5 ? "FIVE_MEMBERS_VERIFIED" : "PENDING";

  return {
    canOpen,
    unlockStatus,
    officialsVerified,
    membersVerified,
    requiredOfficials: 3,
    requiredMembers: 5,
    message: canOpen ? "Meeting unlock policy satisfied." : "Meeting requires 3 officials or 5 active members."
  };
}

async function assertMeetingInGroup(tx: Prisma.TransactionClient, groupId: string, meetingId: string) {
  const meeting = await tx.meeting.findFirst({ where: { id: meetingId, groupId } });
  if (!meeting) throw new ApiHttpError(404, "MEETING_NOT_FOUND", "Meeting does not exist or is outside this group.");
  return meeting;
}

async function createMeetingSteps(tx: Prisma.TransactionClient, meetingId: string) {
  for (const step of meetingSteps) {
    await tx.meetingStepRecord.upsert({
      where: { meetingId_step: { meetingId, step } },
      create: {
        meetingId,
        step,
        name: meetingStepLabels[step as MeetingStep],
        status: "PENDING"
      },
      update: {}
    });
  }
}

async function activateMeeting(
  tx: Prisma.TransactionClient,
  user: AuthenticatedUser | undefined,
  groupId: string,
  meetingId: string,
  gpsCompliant: boolean,
  unlockStatus: string
) {
  await createMeetingSteps(tx, meetingId);
  await tx.meetingStepRecord.updateMany({
    where: { meetingId },
    data: { status: "PENDING", completedAt: null }
  });
  await tx.meetingStepRecord.update({
    where: { meetingId_step: { meetingId, step: meetingSteps[0] } },
    data: { status: "ACTIVE", completedAt: null }
  });

  const meeting = await tx.meeting.update({
    where: { id: meetingId },
    data: {
      status: "IN_PROGRESS",
      openedAt: new Date(),
      gpsCompliant,
      unlockStatus
    },
    include: meetingInclude(user)
  });

  return meeting;
}

async function notifyMeetingActive(groupId: string, title: string) {
  const activeMemberUsers = await prisma.user.findMany({
    where: { groupId, role: { in: ["GROUP_ACCOUNT", "MEMBER"] }, status: "ACTIVE" },
    select: { id: true }
  });

  await createNotifications(
    activeMemberUsers.map((account) => ({
      userId: account.id,
      title: "Meeting is active",
      body: `${title} has started.`,
      type: "MEETING_ACTIVE",
      href: "/dashboard/meetings"
    }))
  );
}

// Exported so the welfare module can reuse the SAME money path — signing,
// cycle stamping and the overdraw guard — instead of writing its own.
export async function resolveFundAccount(tx: Prisma.TransactionClient, groupId: string, fundType: FundType) {
  const fundAccount = await tx.fundAccount.findUnique({
    where: { groupId_type: { groupId, type: fundType } }
  });

  if (!fundAccount) {
    throw new ApiHttpError(404, "FUND_ACCOUNT_NOT_FOUND", `No ${fundType} fund account exists for this group.`);
  }

  return fundAccount;
}

export async function appendLedgerEntry(
  tx: Prisma.TransactionClient,
  input: {
    groupId: string;
    memberId?: string | null;
    meetingId?: string | null;
    fundAccountId: string;
    type: LedgerEntryType;
    amountCents: number;
    direction: "CREDIT" | "DEBIT";
    description: string;
    externalReference?: string | null;
    clientRequestId?: string | null;
    /** For a disbursement: the terms this loan was agreed at. */
    loanTerms?: LoanTerms;
  }
) {
  if (input.clientRequestId) {
    const existing = await tx.ledgerEntry.findUnique({
      where: { clientRequestId: input.clientRequestId },
      include: {
        member: { select: nestedMemberSelect },
        meeting: { select: { id: true, title: true, status: true } },
        fundAccount: { select: { id: true, type: true, currency: true } }
      }
    });
    if (existing) return existing;
  }

  const fundAccount = await tx.fundAccount.findFirst({
    where: { id: input.fundAccountId, groupId: input.groupId }
  });
  if (!fundAccount) {
    throw new ApiHttpError(404, "FUND_ACCOUNT_NOT_FOUND", "Fund account does not exist or is outside this group.");
  }

  // A type has one meaning: which way the money moves and which fund it moves
  // in. The meeting route sets both from the type; this route lets the caller
  // choose them, so a share purchase could be booked as money OUT, a loan
  // "disbursement" as money IN (creating a loan the fund never paid), or a
  // social contribution into the loan fund. Enforced here so every caller is
  // held to it.
  const rule = (meetingLedgerRules as Partial<Record<string, { fundType: FundType; direction: "CREDIT" | "DEBIT"; label: string }>>)[input.type];
  if (rule && (input.direction !== rule.direction || fundAccount.type !== rule.fundType)) {
    throw new ApiHttpError(
      400,
      "LEDGER_ENTRY_MISMATCH",
      `${rule.label} must be recorded as money ${rule.direction === "CREDIT" ? "into" : "out of"} the ${rule.fundType === "SOCIAL" ? "social" : "loan"} fund.`
    );
  }

  if (input.memberId) {
    const member = await tx.member.findFirst({ where: { id: input.memberId, groupId: input.groupId }, select: { id: true } });
    if (!member) {
      throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist or is outside this group.");
    }
  }

  if (input.meetingId) await assertMeetingInGroup(tx, input.groupId, input.meetingId);

  // Cycle scoping. A meeting from a closed cycle refuses new money outright;
  // otherwise the entry is stamped with the group's active cycle so history
  // stays attributable. Enforced here rather than per-route so a new caller
  // cannot forget it.
  if (input.meetingId) await assertMeetingWritable(tx, input.meetingId);
  const activeCycle = await ensureActiveCycle(tx, input.groupId);

  const nextBalance =
    input.direction === "CREDIT"
      ? fundAccount.balanceCents + input.amountCents
      : fundAccount.balanceCents - input.amountCents;

  if (nextBalance < 0) {
    throw new ApiHttpError(400, "INSUFFICIENT_FUND_BALANCE", "This debit would make the fund balance negative.");
  }
  if (nextBalance > MAX_CENTS) {
    throw new ApiHttpError(
      400,
      "FUND_BALANCE_LIMIT",
      `This would take the fund above ${MAX_CENTS_LABEL}, the most it can hold.`
    );
  }

  const payload = {
    groupId: input.groupId,
    cycleId: activeCycle.id,
    memberId: input.memberId ?? null,
    meetingId: input.meetingId ?? null,
    fundAccountId: input.fundAccountId,
    type: input.type,
    amountCents: input.amountCents,
    currency: fundAccount.currency,
    direction: input.direction,
    description: input.description,
    externalReference: input.externalReference ?? null,
    clientRequestId: input.clientRequestId ?? null
  };

  await tx.fundAccount.update({
    where: { id: fundAccount.id },
    data: { balanceCents: nextBalance }
  });

  const ledgerEntry = await tx.ledgerEntry.create({
    data: {
      ...payload,
      signature: signLedgerEntry(payload)
    },
    include: {
      member: { select: nestedMemberSelect },
      meeting: { select: { id: true, title: true, status: true } },
      fundAccount: { select: { id: true, type: true, currency: true } }
    }
  });

  // Keep the Loan projection in step with the money, inside the SAME
  // transaction. Placed here rather than in a route for the same reason as the
  // cycle guard above: a new caller cannot forget it, and until 31 Jul 2026
  // NOTHING created a Loan row, so interest was never charged on anything the
  // app recorded.
  await projectLoanFromEntry(tx, ledgerEntry, input.loanTerms);

  if (input.meetingId) {
    const transactionTotal = await tx.ledgerEntry.count({ where: { meetingId: input.meetingId } });
    await tx.meeting.update({
      where: { id: input.meetingId },
      data: { transactionTotal }
    });
  }

  return ledgerEntry;
}

/**
 * Mirror a disbursement or a repayment into the `Loan` projection.
 *
 * The ledger stays the source of truth: this creates no money and moves none.
 * It records what a ledger line cannot — the term and rate a loan was agreed
 * at, so interest can be computed — and points repayments at the loan they
 * pay down.
 *
 * Same shape the backfill script produces, deliberately: `disbursementEntryId`
 * is UNIQUE, so a loan created here is one the backfill will skip, and the two
 * can never double-count a disbursement.
 */
async function projectLoanFromEntry(
  tx: Prisma.TransactionClient,
  entry: { id: string; groupId: string; memberId: string | null; cycleId: string | null; type: string; amountCents: number; createdAt: Date },
  terms?: LoanTerms
) {
  if (!entry.memberId) return;

  if (entry.type === "INTERNAL_LOAN_DISBURSEMENT") {
    // Read the policy through `tx`, not the global client: a read outside the
    // transaction could see a rate that the same transaction is changing.
    const policy = await tx.groupPolicy.findUnique({ where: { groupId: entry.groupId } });
    const termMonths = terms?.termMonths ?? policy?.defaultLoanTermMonths ?? 1;
    // The rate is COPIED onto the loan rather than looked up later, so a group
    // raising its rate next month cannot reprice money already lent.
    const interestRateBps = terms?.interestRateBps ?? policy?.loanInterestRateBps ?? 0;
    // Same for the interest type: reducing or flat, fixed at disbursement.
    const interestType = normaliseInterestType(terms?.interestType ?? policy?.interestType);

    const dueAt = new Date(entry.createdAt);
    dueAt.setMonth(dueAt.getMonth() + termMonths);

    await tx.loan.create({
      data: {
        groupId: entry.groupId,
        memberId: entry.memberId,
        cycleId: entry.cycleId,
        principalCents: entry.amountCents,
        interestRateBps,
        interestType,
        termMonths,
        disbursedAt: entry.createdAt,
        dueAt,
        status: "ACTIVE",
        disbursementEntryId: entry.id
      }
    });
    return;
  }

  if (entry.type !== "LOAN_REPAYMENT") return;

  // The member's loans are judged TOGETHER, oldest first, at what each owed on
  // the day of each repayment — the same replay the passbook and the share-out
  // use, so all of them report identical balances. Judging only the loan this
  // row points at dropped any surplus (a share-out netting two loans in one
  // row), and left the second loan owing money already paid.
  const position = (
    await loadLoanPositions(tx, { memberIds: [entry.memberId] }, entry.createdAt)
  ).get(entry.memberId);
  if (!position || position.loans.length === 0) return;

  // Back-link the row to the loan that took its first cent — or, when no loan
  // could take any (a pure overpayment), the newest loan that then existed.
  // Back-link only: the guard refuses this the moment it touches an amount, a
  // direction or a party. Declaring the change rather than trusting the line
  // below to keep being harmless.
  const firstSlice = position.allocations.find((slice) => slice.repaymentId === entry.id);
  const targetId =
    firstSlice?.loanId ??
    [...position.loans].reverse().find((loan) => loan.loan.disbursedAt <= entry.createdAt)?.id;
  if (targetId) {
    const backLink = { loanId: targetId };
    assertAppendOnlyOperation("update", Object.keys(backLink));
    await tx.ledgerEntry.update({ where: { id: entry.id }, data: backLink });
  }

  // Closing a loan stops interest accruing on a debt already settled. A large
  // repayment can close several loans at once.
  for (const settled of position.loans) {
    if (settled.settledAt && settled.loan.status === "ACTIVE") {
      await tx.loan.update({ where: { id: settled.id }, data: { status: "REPAID" } });
    }
  }
}

async function appendMeetingLedgerEntry(
  tx: Prisma.TransactionClient,
  groupId: string,
  meetingId: string,
  entry: z.infer<typeof meetingLedgerEntrySchema>
) {
  const rule = meetingLedgerRules[entry.type];
  const fundAccount = await resolveFundAccount(tx, groupId, rule.fundType);

  // Requirement #2: a loan may not exceed the money the group actually has to
  // lend. appendLedgerEntry's overdraw guard would also catch this, but only
  // as a generic "fund would go negative" once the disbursement is already
  // being written. A treasurer needs to be told, in the moment, that the loan
  // is too big and by how much.
  if (entry.type === "INTERNAL_LOAN_DISBURSEMENT") {
    const check = canDisburse({
      requestedCents: entry.amountCents,
      loanFundBalanceCents: fundAccount.balanceCents
    });
    if (!check.allowed) {
      throw new ApiHttpError(
        400,
        "INSUFFICIENT_LOAN_FUND",
        entry.amountCents <= 0
          ? "A loan must be for more than zero."
          : `This loan is larger than the loan fund. Available ${formatKesFromCents(
              fundAccount.balanceCents
            )}, requested ${formatKesFromCents(entry.amountCents)}, short by ${formatKesFromCents(
              check.shortfallCents
            )}.`,
        {
          requestedCents: entry.amountCents,
          availableCents: fundAccount.balanceCents,
          shortfallCents: check.shortfallCents
        }
      );
    }
  }

  return appendLedgerEntry(tx, {
    groupId,
    memberId: entry.memberId,
    meetingId,
    fundAccountId: fundAccount.id,
    type: entry.type,
    amountCents: entry.amountCents,
    direction: rule.direction,
    description: entry.description ?? rule.label,
    externalReference: entry.externalReference,
    clientRequestId: entry.clientRequestId,
    loanTerms: entry.type === "INTERNAL_LOAN_DISBURSEMENT" ? entry.loan : undefined
  });
}

/**
 * What each member actually walks away with at share-out.
 *
 * The pro-rata split is only the first line. The 30 Jul 2026 rules decide the
 * rest, and until 1 Aug 2026 NONE of them were applied here — the server paid
 * out gross while the phone's calculator netted, so the two disagreed about
 * real money:
 *
 *   pro-rata share of the pool
 *   + an equal share of what REMAINS in the welfare fund
 *   − outstanding loans, principal AND interest
 *   = net payout
 *
 * The two pots are paid by two entry types against two funds — the pro-rata
 * share debits INTERNAL_LOAN, the welfare remainder debits SOCIAL — so the
 * money always leaves the pot it was actually sitting in.
 *
 * Outstanding loans NET OFF and are never carried forward, and a member whose
 * debt exceeds their entitlement ends with a NEGATIVE net — a debt to the
 * group, never a bar on sharing out. `payoutCents` stays the gross pro-rata
 * figure so the existing invariant (gross sums to the pool) still holds;
 * `netPayoutCents` is what leaves the box.
 */
async function computeShareOutPreview(
  tx: Prisma.TransactionClient,
  groupId: string,
  poolAmountCents: number,
  options: { distributeWelfare?: boolean } = {}
) {
  const lastShareOut = await tx.ledgerEntry.findFirst({
    where: { groupId, type: "SHARE_OUT_PAYOUT" },
    orderBy: { createdAt: "desc" },
    select: { createdAt: true }
  });
  // This cycle's shares: stamped with the active cycle. Older rows carry no
  // stamp, and for those "since the last payout" is still the best evidence.
  // Going by the payout alone let a cycle closed WITHOUT a payout leak its
  // shares into the next share-out.
  const activeCycle = await tx.cycle.findFirst({
    where: { groupId, status: "ACTIVE" },
    orderBy: { number: "desc" },
    select: { id: true }
  });
  const unstampedSinceLastPayout: Prisma.LedgerEntryWhereInput = {
    cycleId: null,
    ...(lastShareOut ? { createdAt: { gt: lastShareOut.createdAt } } : {})
  };
  const cycleWhere: Prisma.LedgerEntryWhereInput = {
    groupId,
    type: "SHARE_PURCHASE",
    direction: "CREDIT",
    ...(activeCycle
      ? { OR: [{ cycleId: activeCycle.id }, unstampedSinceLastPayout] }
      : lastShareOut
        ? { createdAt: { gt: lastShareOut.createdAt } }
        : {})
  };
  const rows = await tx.ledgerEntry.groupBy({
    by: ["memberId"],
    where: cycleWhere,
    _sum: { amountCents: true }
  });
  const memberIds = rows.map((row) => row.memberId).filter((id): id is string => Boolean(id));
  const members = await tx.member.findMany({
    where: { id: { in: memberIds } },
    select: nestedMemberSelect
  });
  const membersById = new Map(members.map((member) => [member.id, member]));
  const totalShareCents = rows.reduce((sum, row) => sum + (row._sum.amountCents ?? 0), 0);

  // What each member still owes, interest included — the same figure the
  // passbook shows them, from the same maths, so share-out cannot quietly
  // forgive interest the member has been told they owe.
  const eligible = rows.filter((row) => row.memberId && (row._sum.amountCents ?? 0) > 0);
  const outstandingByMember = await shareOutLoanOffsets(
    tx,
    groupId,
    eligible.map((row) => row.memberId!)
  );

  // The welfare fund AS IT STANDS — already spent down by every welfare
  // expense recorded this cycle. Gross contributions would distribute money
  // the group has already paid to a hospital.
  const distributeWelfare = options.distributeWelfare ?? true;
  const welfareFund = await tx.fundAccount.findFirst({
    where: { groupId, type: "SOCIAL" },
    select: { balanceCents: true }
  });
  // Always REPORTED, even when not being distributed, so a group that keeps
  // its float can still see what it is carrying into the next cycle.
  const welfarePoolCents = Math.max(0, welfareFund?.balanceCents ?? 0);
  const welfareShares = distributeWelfare
    ? allocateEqually(welfarePoolCents, eligible.length)
    : new Array<number>(eligible.length).fill(0);

  // Largest remainder: the pool splits to the cent, and the leftover cents go
  // to whoever rounding cost most - not to whoever happens to be listed last.
  const payouts = allocateLargestRemainder(
    poolAmountCents,
    eligible.map((row) => row._sum.amountCents ?? 0)
  );
  const preview = eligible.map((row, index) => {
    const sharePurchaseCents = row._sum.amountCents ?? 0;
    const payoutCents = payouts[index] ?? 0;
    const member = membersById.get(row.memberId!);
    const welfareCents = welfareShares[index] ?? 0;
    const loanOffsetCents = outstandingByMember.get(row.memberId!) ?? 0;
    const netPayoutCents = payoutCents + welfareCents - loanOffsetCents;

    return {
      memberId: row.memberId!,
      member,
      sharePurchaseCents,
      shareCount: sharePurchaseCents,
      percentage: totalShareCents > 0 ? sharePurchaseCents / totalShareCents : 0,
      payoutCents,
      welfareCents,
      /** Principal AND interest, settled out of the payout. */
      loanOffsetCents,
      netPayoutCents,
      /** A debt to the group, not a bar on sharing out. */
      owesGroup: netPayoutCents < 0
    };
  });

  return {
    poolAmountCents,
    totalShareCents,
    distributeWelfare,
    welfarePoolCents,
    totalLoanOffsetCents: preview.reduce((sum, row) => sum + row.loanOffsetCents, 0),
    /** The cash that actually leaves the box. Members owing pay in instead. */
    totalNetPayoutCents: preview.reduce((sum, row) => sum + Math.max(0, row.netPayoutCents), 0),
    roundingDifferenceCents: poolAmountCents - preview.reduce((sum, row) => sum + row.payoutCents, 0),
    rows: preview
  };
}

/**
 * Split `total` equally, giving the remainder cents to the earliest members.
 *
 * Every cent is allocated. Dropping the remainder would leave money in a fund
 * that is supposed to be emptied, and a group counting cash at the table would
 * find a discrepancy nobody could explain.
 */
function allocateEqually(totalCents: number, count: number) {
  if (count <= 0 || totalCents <= 0) return new Array<number>(Math.max(0, count)).fill(0);
  const base = Math.floor(totalCents / count);
  const remainder = totalCents - base * count;
  return Array.from({ length: count }, (_, index) => base + (index < remainder ? 1 : 0));
}

/**
 * Interest-aware outstanding per member, from the Loan projection.
 *
 * Every loan the member has ever taken goes in, not only the ACTIVE ones: a
 * loan is settled by the replay, and the status column only follows it.
 */
async function shareOutLoanOffsets(
  tx: Prisma.TransactionClient,
  groupId: string,
  memberIds: string[]
) {
  const offsets = new Map<string, number>();
  if (memberIds.length === 0) return offsets;

  const positions = await loadLoanPositions(tx, { memberIds, groupIds: [groupId] }, new Date());
  for (const [memberId, position] of positions) {
    if (position.outstandingCents > 0) offsets.set(memberId, position.outstandingCents);
  }

  return offsets;
}

/*
 * A verifier is EMITTED to a device and checked there; nothing compares it
 * here. That is why switching the algorithm needs no migration — a device
 * refreshes its cache and gets the new format. Any consumer must parse the
 * prefix rather than assume a bare hex digest.
 */
function buildOfflineVerifier(deviceId: string, memberId: string, pin: string) {
  return derivePinVerifier(deviceId, memberId, pin);
}

function extractDefaultPinFromDelivery(ciphertext: string) {
  try {
    const payload = decryptJson<{ pin?: string; body?: string; purpose?: string }>(ciphertext);
    // Four or six: a generated PIN is now four digits, but a delivery sent
    // before this change — or a one-time code — carries six.
    const candidate =
      typeof payload.pin === "string"
        ? payload.pin
        : payload.body?.match(/\b\d{4}\b|\b\d{6}\b/)?.[0];
    return candidate && /^\d{4}$|^\d{6}$/.test(candidate) ? candidate : null;
  } catch {
    return null;
  }
}

async function buildAutomaticOfflineVerifiers(tx: Prisma.TransactionClient, groupId: string, deviceId: string) {
  const members = await tx.member.findMany({
    where: { groupId, status: "ACTIVE", pinHash: { not: null } },
    orderBy: { fullName: "asc" },
    select: {
      id: true,
      fullName: true,
      role: true,
      pinHash: true,
      pinUpdatedAt: true,
      pinDeliveries: {
        where: { purpose: "DEFAULT_PIN" },
        orderBy: { createdAt: "desc" },
        take: 5,
        select: { messageCiphertext: true, createdAt: true }
      }
    }
  });
  const verifiers = [];
  const skipped = [];

  for (const member of members) {
    let matchedPin: string | null = null;
    for (const delivery of member.pinDeliveries) {
      const pin = extractDefaultPinFromDelivery(delivery.messageCiphertext);
      if (pin && member.pinHash && (await bcrypt.compare(pin, member.pinHash))) {
        matchedPin = pin;
        break;
      }
    }

    if (!matchedPin) {
      skipped.push({
        memberId: member.id,
        fullName: member.fullName,
        reason: "PIN_DELIVERY_NOT_AVAILABLE"
      });
      continue;
    }

    verifiers.push({
      memberId: member.id,
      fullName: member.fullName,
      role: member.role,
      verifier: buildOfflineVerifier(deviceId, member.id, matchedPin),
      pinUpdatedAt: member.pinUpdatedAt
    });
  }

  return { verifiers, skipped };
}

router.get("/meetings", requireAuth("meetings:read"), async (req, res, next) => {
  try {
    const meetings = await prisma.meeting.findMany({
      where: { group: scopeGroupWhere(req.user) },
      orderBy: { scheduledAt: "desc" },
      include: meetingInclude(req.user)
    });
    ok(res, meetings);
  } catch (error) {
    next(error);
  }
});

/**
 * The credit score a group page shows must be the one the agent report and the
 * lender views use.
 *
 * Rows written before the rating contract (seed fixtures, the FTMA workbook
 * import) carry only a legacy weighted breakdown. `latestCreditRating` already
 * re-rates those against the current contract — the pages used to show the raw
 * stored number instead, so one group read "76" on its page and "not rated" in
 * its agent's caseload. A legacy row is now replaced by the current rating, or
 * dropped when the current contract cannot rate the group yet ("Pending").
 */
async function withCurrentRating<
  T extends { id: string; fundAccounts: Array<{ type: string; balanceCents: number }>; creditScores: Array<{ score: number; breakdownJson: string }> }
>(group: T): Promise<T & { totalSavingsCents: number; totalSocialFundCents: number }> {
  const latest = group.creditScores[0];
  
  // Savings = the shares members bought this cycle. NOT the loan fund's cash
  // balance, which is what this used to report: that falls every time a loan
  // goes out, so a group that lent its savings read as having saved nothing.
  // Unstamped rows are older than cycles and belong to the first one.
  const shares = await prisma.ledgerEntry.groupBy({
    by: ["direction"],
    where: {
      groupId: group.id,
      type: "SHARE_PURCHASE",
      OR: [{ cycle: { status: "ACTIVE" } }, { cycleId: null }]
    },
    _sum: { amountCents: true }
  });
  const totalSavingsCents = shares.reduce(
    (sum, row) => sum + (row._sum.amountCents ?? 0) * (row.direction === "DEBIT" ? -1 : 1),
    0
  );
  // The welfare fund as it stands: contributions and fines less welfare paid.
  const socialFund = group.fundAccounts.find((f) => f.type === "SOCIAL");
  const totalSocialFundCents = socialFund?.balanceCents ?? 0;

  if (!latest) return { ...group, totalSavingsCents, totalSocialFundCents };

  let legacy = true;
  try {
    const parsed = JSON.parse(latest.breakdownJson) as { band?: unknown; factors?: unknown };
    legacy = !(parsed.band && parsed.factors);
  } catch {
    legacy = true;
  }
  if (!legacy) return { ...group, totalSavingsCents, totalSocialFundCents };

  const rating = await latestCreditRating(group.id);
  if (!rating || !rating.rated) return { ...group, creditScores: [], totalSavingsCents, totalSocialFundCents };

  return {
    ...group,
    creditScores: [{ ...latest, score: rating.score }],
    totalSavingsCents,
    totalSocialFundCents
  };
}

router.get("/groups", requireAuth("groups:read"), async (req, res, next) => {
  try {
    const groups = await prisma.group.findMany({
      where: scopeGroupWhere(req.user),
      orderBy: { createdAt: "desc" },
      include: groupInclude
    });
    ok(res, await Promise.all(groups.map((group) => withCurrentRating(group))));
  } catch (error) {
    next(error);
  }
});

router.post("/groups", requireAuth("groups:write"), async (req, res, next) => {
  try {
    const payload = groupCreateSchema.parse(req.body);
    const group = await prisma.$transaction(async (tx) => {
      const created = await tx.group.create({
        data: {
          name: payload.name,
          code: payload.code,
          county: payload.county,
          phase: payload.phase,
          subCounty: payload.subCounty,
          location: payload.location,
          composition: payload.composition,
          objective: payload.objective,
          contactPersonName: payload.contactPersonName,
          contactPhone: payload.contactPhone,
          onboardingFeedback: payload.onboardingFeedback,
          meetingDay: payload.meetingDay,
          gpsLatitude: payload.gpsLatitude,
          gpsLongitude: payload.gpsLongitude,
          gpsRadiusMeters: payload.gpsRadiusMeters,
          shareValueCents: payload.shareValueCents,
          maxSharesPerMemberPerMeeting: payload.maxSharesPerMemberPerMeeting,
          constitutionVersion: payload.constitutionVersion,
          cycleNumber: payload.cycleNumber,
          villageAgentId: payload.villageAgentId,
          programmeId: payload.programmeIds[0] ?? undefined,
          fundAccounts: {
            create: fundTypes.map((type) => ({ type }))
          },
          programmeLinks: {
            create: payload.programmeIds.map((programmeId) => ({ programmeId }))
          }
        },
        include: groupInclude
      });

      return created;
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "GROUP",
      entityId: group.id,
      type: "GROUP_CREATED",
      payload: { groupId: group.id, code: group.code }
    });

    ok(res.status(201), group);
  } catch (error) {
    next(error);
  }
});

router.get("/groups/:id", requireAuth("groups:read"), async (req, res, next) => {
  try {
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const group = await prisma.group.findFirst({
      where: scopeGroupWhere(req.user, { id: routeParam(req.params.id, "id") }),
      include: groupInclude
    });
    if (!group) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside this account.");
    // Which optional modules this group's programmes have switched on, so the
    // phone and console show only what the group can use.
    ok(res, { ...(await withCurrentRating(group)), modules: await modulesForGroup(group.id) });
  } catch (error) {
    next(error);
  }
});

router.patch("/groups/:id", requireAuth("groups:write"), async (req, res, next) => {
  try {
    const payload = groupUpdateSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const group = await prisma.$transaction(async (tx) => {
      const updateData: Prisma.GroupUpdateInput = {
        ...(payload.name ? { name: payload.name } : {}),
        ...(payload.code ? { code: payload.code } : {}),
        ...(payload.county ? { county: payload.county } : {}),
        ...(payload.phase ? { phase: payload.phase } : {}),
        ...(payload.subCounty !== undefined ? { subCounty: payload.subCounty } : {}),
        ...(payload.location !== undefined ? { location: payload.location } : {}),
        ...(payload.composition !== undefined ? { composition: payload.composition } : {}),
        ...(payload.objective !== undefined ? { objective: payload.objective } : {}),
        ...(payload.contactPersonName !== undefined ? { contactPersonName: payload.contactPersonName } : {}),
        ...(payload.contactPhone !== undefined ? { contactPhone: payload.contactPhone } : {}),
        ...(payload.onboardingFeedback !== undefined ? { onboardingFeedback: payload.onboardingFeedback } : {}),
        ...(payload.meetingDay !== undefined ? { meetingDay: payload.meetingDay } : {}),
        ...(payload.gpsLatitude !== undefined ? { gpsLatitude: payload.gpsLatitude } : {}),
        ...(payload.gpsLongitude !== undefined ? { gpsLongitude: payload.gpsLongitude } : {}),
        ...(payload.gpsRadiusMeters !== undefined ? { gpsRadiusMeters: payload.gpsRadiusMeters } : {}),
        ...(payload.shareValueCents !== undefined ? { shareValueCents: payload.shareValueCents } : {}),
        ...(payload.maxSharesPerMemberPerMeeting !== undefined ? { maxSharesPerMemberPerMeeting: payload.maxSharesPerMemberPerMeeting } : {}),
        ...(payload.constitutionVersion !== undefined ? { constitutionVersion: payload.constitutionVersion } : {}),
        ...(payload.cycleNumber !== undefined ? { cycleNumber: payload.cycleNumber } : {}),
        ...(payload.villageAgentId !== undefined ? { villageAgent: payload.villageAgentId ? { connect: { id: payload.villageAgentId } } : { disconnect: true } } : {}),
        ...(payload.programmeIds ? { programme: payload.programmeIds[0] ? { connect: { id: payload.programmeIds[0] } } : { disconnect: true } } : {})
      };

      if (payload.programmeIds) {
        await tx.programmeGroup.deleteMany({ where: { groupId: routeParam(req.params.id, "id") } });
        await tx.programmeGroup.createMany({
          data: payload.programmeIds.map((programmeId) => ({ groupId: routeParam(req.params.id, "id"), programmeId }))
        });
      }

      return tx.group.update({
        where: { id: routeParam(req.params.id, "id") },
        data: updateData,
        include: groupInclude
      });
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "GROUP",
      entityId: group.id,
      type: "GROUP_UPDATED",
      payload: { groupId: group.id, code: group.code }
    });

    ok(res, group);
  } catch (error) {
    next(error);
  }
});

router.get("/groups/:id/members", requireAuth("members:read"), async (req, res, next) => {
  try {
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const members = await prisma.member.findMany({
      where: memberScopeForUser(req.user, { groupId: routeParam(req.params.id, "id") }),
      orderBy: { joinedAt: "asc" },
      select: memberSelect
    });
    ok(res, members.map((member) => serializeMember(member, { viewerRole: req.user?.role })));
  } catch (error) {
    next(error);
  }
});

/**
 * A member the phone knows about, sent up by the app's automatic sync.
 *
 * Members created on a handset were never sent to the server, so every one of
 * them became a "not linked to the backend" conflict and their attendance and
 * money stayed on the phone — the reason a group's records could be full on
 * the phone and empty in the console.
 *
 * Built to be retried by a phone with a bad signal: it finds the member it
 * already made (by phone, else by name within the group) rather than making a
 * second. A phone number is optional here, because groups set up on the phone
 * often enter members by name alone; such a member is stored with no number
 * and simply cannot receive SMS until one is added. No PIN is texted — these
 * people already use the phone, and a first sync must not send a burst of SMS.
 */
const memberSyncSchema = z.object({
  fullName: z.string().trim().min(1).max(120),
  phone: z.string().trim().max(32).nullish(),
  role: z.enum(memberRoles).optional()
});

router.post("/groups/:id/members/sync", requireAuth("members:write"), async (req, res, next) => {
  try {
    const payload = memberSyncSchema.parse(req.body);
    const groupId = routeParam(req.params.id, "id");
    await assertGroupAccess(req.user, groupId);

    // An unusable number ("12345") is not stored as though it identified
    // someone: the member arrives by name, as if no number had been given, and
    // the number stays on the phone that typed it. Storing it would let a
    // junk value be matched against — or collide with — a real member later.
    const phone = looksLikePhone(payload.phone) ? normalisePhone(payload.phone) : "";
    const wantedName = payload.fullName.trim().toLowerCase().replace(/\s+/g, " ");
    const roster = await prisma.member.findMany({
      where: { groupId },
      select: { id: true, fullName: true, phone: true }
    });
    const existing =
      (phone ? roster.find((member) => normalisePhone(member.phone) === phone) : undefined) ??
      roster.find((member) => member.fullName.trim().toLowerCase().replace(/\s+/g, " ") === wantedName);

    if (existing) {
      ok(res, { id: existing.id, matched: true });
      return;
    }

    const member = await prisma.member.create({
      data: {
        groupId,
        fullName: payload.fullName.trim(),
        phone,
        role: payload.role ?? "MEMBER",
        kycStatus: "PENDING",
        status: "ACTIVE"
      },
      select: { id: true }
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "MEMBER",
      entityId: member.id,
      type: "MEMBER_REGISTERED",
      payload: { groupId, memberId: member.id, source: "PHONE_SYNC" }
    });

    ok(res.status(201), { id: member.id, matched: false });
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/members", requireAuth("members:write"), async (req, res, next) => {
  try {
    const payload = memberCreateSchema.parse(req.body);
    const groupId = routeParam(req.params.id, "id");
    await assertGroupAccess(req.user, groupId);

    // Canonicalised on the way in, for the same reason it is on update: the
    // phone is how a person is recognised later, and guarding the edit path
    // while leaving the add path open would let duplicates in the front door.
    const phone = normalisePhone(payload.phone);
    if (!phone) {
      throw new ApiHttpError(400, "INVALID_PHONE", "That does not look like a usable phone number.");
    }
    const existing = await prisma.member.findFirst({
      where: { groupId, phone },
      select: { id: true, fullName: true }
    });
    if (existing) {
      throw new ApiHttpError(
        409,
        "PHONE_ALREADY_IN_GROUP",
        `${existing.fullName} already uses that number in this group. Adding a second member on one number is how savings end up in the wrong passbook.`,
        { memberId: existing.id }
      );
    }

    const result = await prisma.$transaction(async (tx) => {
      const member = await tx.member.create({
        data: { ...payload, phone, groupId },
        select: memberSelect
      });
      return generateAndQueueMemberPin(tx, member, {
        requestedByUserId: req.user?.id,
        select: memberSelect
      });
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "MEMBER",
      entityId: result.member.id,
      type: "MEMBER_REGISTERED",
      payload: { groupId: routeParam(req.params.id, "id"), memberId: result.member.id }
    });

    ok(res.status(201), serializeMember(result.member, { viewerRole: req.user?.role, delivery: result.delivery }));
  } catch (error) {
    next(error);
  }
});

/**
 * Correct a member's details — most often a name spelling or a mistyped phone.
 *
 * The phone is not just a contact detail: it is how a person is recognised
 * across the platform. A join request is matched to an existing member by
 * phone, and a member whose number fails to match is handed a fresh empty
 * passbook instead of the savings already recorded against her name. So an
 * edit here has to do three things the previous version did none of:
 * canonicalise the number, refuse one that already belongs to somebody else
 * in the group, and keep the member's sign-in account in step.
 */
router.patch("/groups/:id/members/:memberId", requireAuth("members:write"), async (req, res, next) => {
  try {
    const payload = memberUpdateSchema.parse(req.body);
    const groupId = routeParam(req.params.id, "id");
    const memberId = routeParam(req.params.memberId, "memberId");
    await assertGroupAccess(req.user, groupId);

    const member = await prisma.member.findFirst({
      where: { id: memberId, groupId },
      select: { id: true, phone: true, fullName: true }
    });
    if (!member) throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist or is outside this group.");

    const data: Record<string, unknown> = { ...payload };

    if (payload.phone !== undefined) {
      // Stored canonical. "0712345678" and "+254 712 345 678" are one person,
      // and storing them as typed is what produced duplicate accounts before.
      const canonical = normalisePhone(payload.phone);
      if (!canonical) {
        throw new ApiHttpError(400, "INVALID_PHONE", "That does not look like a usable phone number.");
      }
      data.phone = canonical;

      if (canonical !== normalisePhone(member.phone)) {
        const clash = await prisma.member.findFirst({
          where: { groupId, phone: canonical, id: { not: memberId } },
          select: { id: true, fullName: true }
        });
        if (clash) {
          throw new ApiHttpError(
            409,
            "PHONE_ALREADY_IN_GROUP",
            `${clash.fullName} already uses that number in this group. Two members cannot share one number — it is how the group tells them apart.`,
            { memberId: clash.id }
          );
        }
      }
    }

    const updated = await prisma.$transaction(async (tx) => {
      const result = await tx.member.update({ where: { id: memberId }, data, select: memberSelect });

      // A member with a sign-in account signs in WITH THIS NUMBER. Leaving the
      // account behind would lock them out of their own savings while the
      // roster showed the new number and looked correct.
      if (typeof data.phone === "string" && data.phone !== normalisePhone(member.phone)) {
        const account = await tx.user.findFirst({
          where: { memberId },
          select: { id: true, phone: true }
        });
        if (account) {
          const takenBy = await tx.user.findFirst({
            where: { phone: data.phone, id: { not: account.id } },
            select: { id: true }
          });
          if (takenBy) {
            throw new ApiHttpError(
              409,
              "PHONE_ALREADY_HAS_ACCOUNT",
              "Another sign-in account already uses that number. Change it there first, or the two accounts would collide.",
              { memberId }
            );
          }
          await tx.user.update({ where: { id: account.id }, data: { phone: data.phone } });
        }
      }

      return result;
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "MEMBER",
      entityId: memberId,
      type: "MEMBER_UPDATED",
      // The old value is the point: without it nobody can tell whether a
      // number was corrected or quietly pointed at a different person.
      payload: {
        groupId,
        changed: Object.keys(payload),
        ...(payload.phone !== undefined ? { previousPhone: member.phone } : {}),
        ...(payload.fullName !== undefined ? { previousName: member.fullName } : {})
      }
    });

    ok(res, serializeMember(updated, { viewerRole: req.user?.role }));
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/members/:memberId/pin", requireAuth("members:write"), async (req, res, next) => {
  try {
    pinRequestSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const existing = await prisma.member.findFirst({
      where: { id: routeParam(req.params.memberId, "memberId"), groupId: routeParam(req.params.id, "id") },
      select: memberSelect
    });
    if (!existing) throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist or is outside this group.");

    const result = await prisma.$transaction((tx) =>
      generateAndQueueMemberPin(tx, existing, {
        requestedByUserId: req.user?.id,
        select: memberSelect
      })
    );
    const delivery = await sendQueuedMemberPinDelivery(result.delivery.id);
    ok(res, serializeMember(result.member, { viewerRole: req.user?.role, delivery: delivery ?? result.delivery }));
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/members/:memberId/otp", requireAuth("meeting-keys:write"), async (req, res, next) => {
  try {
    pinRequestSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const existing = await prisma.member.findFirst({
      where: memberScopeForUser(req.user, { id: routeParam(req.params.memberId, "memberId"), groupId: routeParam(req.params.id, "id") }),
      select: memberSelect
    });
    if (!existing) throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist or is outside this group.");

    const result = await prisma.$transaction((tx) =>
      generateAndQueueMemberOtp(tx, existing, {
        requestedByUserId: req.user?.id,
        select: memberSelect
      })
    );
    const delivery = await sendQueuedMemberPinDelivery(result.delivery.id);
    ok(res, serializeMember(result.member, { viewerRole: req.user?.role, delivery: delivery ?? result.delivery }));
  } catch (error) {
    next(error);
  }
});

/**
 * Standalone credential check for the mobile 3-key unlock: when the phone is
 * online it verifies a member's one-time code (or saved PIN) against the
 * server before counting the key — no backend meeting required, so the
 * offline-first local meeting flow can still use it.
 */
const verifyCredentialSchema = z.object({
  secret: z.string().trim().regex(/^\d{4,8}$/, "Enter the code you received."),
  credentialType: z.enum(["DEFAULT_PIN", "CURRENT_OTP"]).optional()
});

router.post(
  "/groups/:id/members/:memberId/verify-credential",
  requireAuth("meeting-keys:write"),
  async (req, res, next) => {
    try {
      const body = verifyCredentialSchema.parse(req.body);
      await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
      const member = await prisma.member.findFirst({
        where: memberScopeForUser(req.user, {
          id: routeParam(req.params.memberId, "memberId"),
          groupId: routeParam(req.params.id, "id")
        }),
        select: { id: true, pinHash: true, currentOtpHash: true, currentOtpExpiresAt: true }
      });
      if (!member) throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist or is outside this group.");

      const allowDefaultPin = !body.credentialType || body.credentialType === "DEFAULT_PIN";
      const allowCurrentOtp = !body.credentialType || body.credentialType === "CURRENT_OTP";

      let credentialType: "DEFAULT_PIN" | "CURRENT_OTP" | null = null;
      if (allowDefaultPin && member.pinHash && (await bcrypt.compare(body.secret, member.pinHash))) {
        credentialType = "DEFAULT_PIN";
      } else if (
        allowCurrentOtp &&
        member.currentOtpHash &&
        member.currentOtpExpiresAt &&
        member.currentOtpExpiresAt > new Date() &&
        (await bcrypt.compare(body.secret, member.currentOtpHash))
      ) {
        credentialType = "CURRENT_OTP";
      }

      if (!credentialType) {
        throw new ApiHttpError(400, "INVALID_MEMBER_CREDENTIAL", "That code is wrong or has expired.");
      }

      ok(res, { valid: true, credentialType });
    } catch (error) {
      next(error);
    }
  }
);

/**
 * A group gives one of its members a sign-in, so the member can see their own
 * savings on their own phone.
 *
 * Only when the group has member accounts switched on (Edit group set-up on the
 * phone; `GroupPolicy.memberAccountsEnabled`). IWL admins are not bound by it.
 *
 * A person is one login however many groups they save with: the account is
 * found by the member's phone (compared in canonical form, so 0712… and
 * +254712… are the same person). A member who already signs in for another
 * group has this group LINKED to that login, not a second account made.
 */
const memberAccountSchema = z.object({
  password: z.string().min(6).max(100),
  email: z.string().trim().email().optional()
});

async function assertMemberAccountsOn(user: AuthenticatedUser | undefined, groupId: string) {
  if (user?.role === "IWL_ADMIN") return;
  if (!(await memberAccountsEnabledFor(groupId))) {
    throw new ApiHttpError(
      403,
      "MEMBER_ACCOUNTS_OFF",
      "Member sign-ins are switched off for this group. Turn them on in Edit group set-up first."
    );
  }
}

async function findLoginForPhone(phone: string) {
  const tail = phoneTail(phone);
  if (tail.length < 9) return null;
  const candidates = await prisma.user.findMany({
    where: { phone: { contains: tail } },
    select: { id: true, role: true, phone: true, memberId: true, name: true, email: true, groupId: true }
  });
  return candidates.find((candidate) => samePhone(candidate.phone, phone)) ?? null;
}

router.post(
  "/groups/:id/members/:memberId/account",
  requireAuth("members:write"),
  async (req, res, next) => {
    try {
      const body = memberAccountSchema.parse(req.body);
      const groupId = routeParam(req.params.id, "id");
      await assertGroupAccess(req.user, groupId);
      const member = await prisma.member.findFirst({
        where: memberScopeForUser(req.user, { id: routeParam(req.params.memberId, "memberId"), groupId }),
        select: { id: true, fullName: true, phone: true }
      });
      if (!member) throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist or is outside this group.");
      if (req.user?.role !== "IWL_ADMIN") await assertMayCreateMemberLogin(groupId, req.user?.id ?? null);

      const existing = await findLoginForPhone(member.phone);
      if (existing) {
        if (existing.role !== "MEMBER") {
          throw new ApiHttpError(
            409,
            "ACCOUNT_EXISTS",
            "This phone number already signs in to another kind of account, so it cannot be a member sign-in too."
          );
        }
        // Same person, another group: link this group to the login they have.
        try {
          await linkMembership(existing.id, member.id, groupId);
        } catch (error) {
          if (error instanceof MemberAlreadyLinkedError) {
            throw new ApiHttpError(409, "ACCOUNT_EXISTS", `${member.fullName} already has a sign-in account.`);
          }
          throw error;
        }
        await appendAuditEvent({
          actorUserId: req.user?.id,
          entityType: "USER",
          entityId: existing.id,
          type: "MEMBER_ACCOUNT_CREATED",
          payload: { memberId: member.id, groupId, createdBy: req.user?.id, linkedExistingLogin: true }
        });
        // Their password stays their own; this group's official does not set it.
        ok(res.status(200), {
          id: existing.id,
          name: existing.name,
          email: existing.email,
          phone: existing.phone,
          role: existing.role,
          groupId: existing.groupId,
          memberId: existing.memberId,
          linkedExistingLogin: true
        });
        return;
      }

      const email = body.email ?? `${member.phone.replace(/[^0-9]/g, "")}@accounts.intellicash.app`;
      const emailTaken = await prisma.user.findUnique({ where: { email }, select: { id: true } });
      if (emailTaken) {
        throw new ApiHttpError(409, "ACCOUNT_EXISTS", "An account with this email already exists.");
      }

      const passwordHash = await bcrypt.hash(body.password, 12);
      const user = await prisma.$transaction(async (tx) => {
        const created = await tx.user.create({
          data: {
            name: member.fullName,
            email,
            phone: member.phone,
            passwordHash,
            role: "MEMBER",
            groupId,
            memberId: member.id
          },
          select: { id: true, name: true, email: true, phone: true, role: true, groupId: true, memberId: true }
        });
        await linkMembership(created.id, member.id, groupId, tx);
        return created;
      });

      await appendAuditEvent({
        actorUserId: req.user?.id,
        entityType: "USER",
        entityId: user.id,
        type: "MEMBER_ACCOUNT_CREATED",
        payload: { memberId: member.id, groupId, createdBy: req.user?.id }
      });

      ok(res.status(201), { ...user, linkedExistingLogin: false });
    } catch (error) {
      next(error);
    }
  }
);

/**
 * Sets a new starting password for a member who has forgotten theirs. The
 * group's official hands it over in person, as with a new account.
 *
 * Only for a login that belongs to this group's member and to no other group:
 * a person who also saves elsewhere keeps control of their own password, and
 * one group's official must not be able to lock them out of another group.
 */
router.put(
  "/groups/:id/members/:memberId/account/password",
  requireAuth("members:write"),
  async (req, res, next) => {
    try {
      const body = z.object({ password: z.string().min(6).max(100) }).parse(req.body);
      const groupId = routeParam(req.params.id, "id");
      await assertGroupAccess(req.user, groupId);
      await assertMemberAccountsOn(req.user, groupId);
      const member = await prisma.member.findFirst({
        where: memberScopeForUser(req.user, { id: routeParam(req.params.memberId, "memberId"), groupId }),
        select: { id: true, fullName: true, phone: true }
      });
      if (!member) throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist or is outside this group.");

      const login = await findLoginForPhone(member.phone);
      if (!login || login.role !== "MEMBER") {
        throw new ApiHttpError(404, "ACCOUNT_NOT_FOUND", `${member.fullName} has no sign-in yet.`);
      }
      const otherGroups = await prisma.userMembership.count({
        where: { userId: login.id, NOT: { groupId } }
      });
      if (otherGroups > 0 && req.user?.role !== "IWL_ADMIN") {
        throw new ApiHttpError(
          409,
          "SHARED_LOGIN",
          `${member.fullName} also signs in for another group, so only they (or IWL support) can change the password.`
        );
      }

      await prisma.user.update({
        where: { id: login.id },
        data: { passwordHash: await bcrypt.hash(body.password, 12) }
      });
      await prisma.session.deleteMany({ where: { userId: login.id } });
      await appendAuditEvent({
        actorUserId: req.user?.id,
        entityType: "USER",
        entityId: login.id,
        type: "USER_PASSWORD_UPDATED",
        payload: { memberId: member.id, groupId, method: "GROUP_RESET" }
      });
      ok(res, { reset: true });
    } catch (error) {
      next(error);
    }
  }
);

/**
 * The signed-in member's own passbook — savings, loans, attendance and their
 * recent transactions, totalled by the server.
 *
 * Self-scoped by design: there is no id in the path, so a member can only
 * ever fetch themselves. Group officials and admins use
 * `GET /reports/member/:memberId` (scope-checked) to view someone else.
 */
router.get("/members/me", requireAuth("members:read"), async (req, res, next) => {
  try {
    // The group they were viewing may have removed them since this session
    // began. Repoint at a group they still belong to before concluding the
    // account has no member at all.
    let memberId = req.user?.memberId;
    if (!memberId && req.user?.id) {
      await reconcileMembership(req.user.id);
      const refreshed = await prisma.user.findUnique({
        where: { id: req.user.id },
        select: { memberId: true }
      });
      memberId = refreshed?.memberId ?? undefined;
    }
    if (!memberId) {
      throw new ApiHttpError(
        400,
        "NOT_A_MEMBER_ACCOUNT",
        "This account is not linked to a group member."
      );
    }
    // A member whose current group has switched sign-ins off is shown another
    // group they belong to that has not; with none left, they are told why.
    if (req.user?.role === "MEMBER" && req.user.id) {
      const open = await visibleMembershipsFor(req.user.id);
      if (!open.some((link) => link.memberId === memberId)) {
        if (open.length === 0) {
          throw new ApiHttpError(
            403,
            "MEMBER_ACCOUNTS_OFF",
            "Your group has switched member sign-ins off. Ask your group's officials if you need to see your savings."
          );
        }
        memberId = open[0]!.memberId;
      }
    }
    const passbook = await buildMemberPassbook(memberId, {
      cycleId: typeof req.query.cycleId === "string" ? req.query.cycleId : undefined
    });
    if (!passbook) {
      throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member record not found.");
    }
    ok(res, passbook);
  } catch (error) {
    next(error);
  }
});

/**
 * Everything this person has saved, across every group they belong to.
 *
 * Self-scoped like `/members/me` — there is no id in the path, so it can only
 * ever return the caller's own figures.
 */
router.get("/members/me/overview", requireAuth("members:read"), async (req, res, next) => {
  try {
    const userId = req.user?.id;
    if (!userId) throw new ApiHttpError(401, "UNAUTHENTICATED", "Please sign in to continue.");
    if (req.user?.role !== "MEMBER") {
      throw new ApiHttpError(
        400,
        "NOT_A_MEMBER_ACCOUNT",
        "This account is not linked to a group member."
      );
    }
    await reconcileMembership(userId);
    const open = new Set((await visibleMembershipsFor(userId)).map((link) => link.groupId));
    ok(res, await buildMemberOverview(userId, { includeGroup: (groupId) => open.has(groupId) }));
  } catch (error) {
    next(error);
  }
});

router.post("/members/me/pin", requireAuth("meeting-keys:write"), async (req, res, next) => {
  try {
    pinRequestSchema.parse(req.body);
    if (!req.user?.memberId || !req.user.groupId) {
      throw new ApiHttpError(400, "MEMBER_ACCOUNT_REQUIRED", "This action requires a member account.");
    }
    const existing = await prisma.member.findFirst({
      where: { id: req.user.memberId, groupId: req.user.groupId },
      select: memberSelect
    });
    if (!existing) throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member account was not found.");

    const result = await prisma.$transaction((tx) =>
      generateAndQueueMemberPin(tx, existing, {
        requestedByUserId: req.user?.id,
        select: memberSelect
      })
    );
    const delivery = await sendQueuedMemberPinDelivery(result.delivery.id);
    ok(res, serializeMember(result.member, { viewerRole: req.user?.role, delivery: delivery ?? result.delivery }));
  } catch (error) {
    next(error);
  }
});

router.post("/members/me/otp", requireAuth("meeting-keys:write"), async (req, res, next) => {
  try {
    pinRequestSchema.parse(req.body);
    if (!req.user?.memberId || !req.user.groupId) {
      throw new ApiHttpError(400, "MEMBER_ACCOUNT_REQUIRED", "This action requires a member account.");
    }
    const existing = await prisma.member.findFirst({
      where: { id: req.user.memberId, groupId: req.user.groupId },
      select: memberSelect
    });
    if (!existing) throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member account was not found.");

    const result = await prisma.$transaction((tx) =>
      generateAndQueueMemberOtp(tx, existing, {
        requestedByUserId: req.user?.id,
        select: memberSelect
      })
    );
    const delivery = await sendQueuedMemberPinDelivery(result.delivery.id);
    ok(res, serializeMember(result.member, { viewerRole: req.user?.role, delivery: delivery ?? result.delivery }));
  } catch (error) {
    next(error);
  }
});

router.get("/groups/:id/meetings", requireAuth("meetings:read"), async (req, res, next) => {
  try {
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const meetings = await prisma.meeting.findMany({
      where: { groupId: routeParam(req.params.id, "id") },
      orderBy: { scheduledAt: "desc" },
      include: meetingInclude(req.user)
    });
    ok(res, meetings);
  } catch (error) {
    next(error);
  }
});

router.get("/groups/:id/meetings/:meetingId", requireAuth("meetings:read"), async (req, res, next) => {
  try {
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const meeting = await prisma.meeting.findFirst({
      where: { id: routeParam(req.params.meetingId, "meetingId"), groupId: routeParam(req.params.id, "id") },
      include: meetingInclude(req.user)
    });
    if (!meeting) throw new ApiHttpError(404, "MEETING_NOT_FOUND", "Meeting does not exist or is outside this group.");
    ok(res, meeting);
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/meetings", requireAuth("meetings:write"), async (req, res, next) => {
  try {
    const payload = meetingCreateSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));

    if (payload.adoptScheduled) {
      const { start, end } = nairobiDayBounds(new Date(payload.scheduledAt));
      const planned = await prisma.meeting.findFirst({
        where: {
          groupId: routeParam(req.params.id, "id"),
          status: "SCHEDULED",
          scheduledAt: { gte: start, lt: end },
          attendance: { none: {} },
          ledgerEntries: { none: {} }
        },
        orderBy: { scheduledAt: "asc" },
        include: meetingInclude(req.user)
      });
      if (planned) {
        ok(res, planned);
        return;
      }
    }

    const meeting = await prisma.$transaction(async (tx) => {
      // Belongs to the cycle it is made in. Without this every meeting made
      // through this route had no cycle, so closing a cycle archived none of
      // them, the "closed cycle refuses new money" rule never applied to them,
      // and the cycles screen reported "0 meetings" for a group that had held
      // several.
      const cycle = await ensureActiveCycle(tx, routeParam(req.params.id, "id"));
      const created = await tx.meeting.create({
        data: {
          groupId: routeParam(req.params.id, "id"),
          cycleId: cycle.id,
          title: payload.title,
          status: "SCHEDULED",
          scheduledAt: new Date(payload.scheduledAt),
          gpsCompliant: payload.gpsCompliant,
          source: payload.source ?? "MANUAL"
        }
      });
      await createMeetingSteps(tx, created.id);
      return tx.meeting.findUniqueOrThrow({
        where: { id: created.id },
        include: meetingInclude(req.user)
      });
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "MEETING",
      entityId: meeting.id,
      type: "MEETING_SCHEDULED",
      payload: { groupId: routeParam(req.params.id, "id"), meetingId: meeting.id }
    });

    ok(res.status(201), meeting);
  } catch (error) {
    next(error);
  }
});

router.patch("/groups/:id/meetings/:meetingId", requireAuth("meetings:write"), async (req, res, next) => {
  try {
    const payload = meetingUpdateSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const existing = await prisma.meeting.findFirst({
      where: { id: routeParam(req.params.meetingId, "meetingId"), groupId: routeParam(req.params.id, "id") }
    });
    if (!existing) throw new ApiHttpError(404, "MEETING_NOT_FOUND", "Meeting does not exist or is outside this group.");
    if (!["SCHEDULED", "KEY_UNLOCK_PENDING"].includes(existing.status)) {
      throw new ApiHttpError(400, "MEETING_LOCKED", "Only scheduled meetings can be edited.");
    }

    const meeting = await prisma.meeting.update({
      where: { id: routeParam(req.params.meetingId, "meetingId") },
      data: {
        ...(payload.title ? { title: payload.title } : {}),
        ...(payload.scheduledAt ? { scheduledAt: new Date(payload.scheduledAt) } : {}),
        ...(payload.gpsCompliant !== undefined ? { gpsCompliant: payload.gpsCompliant } : {})
      },
      include: meetingInclude(req.user)
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "MEETING",
      entityId: meeting.id,
      type: "MEETING_UPDATED",
      payload: { groupId: routeParam(req.params.id, "id"), meetingId: meeting.id }
    });

    ok(res, meeting);
  } catch (error) {
    next(error);
  }
});

/**
 * Cancel a scheduled meeting that did not happen.
 *
 * Always a person's decision: nothing in the system cancels, starts or closes
 * a meeting because its time passed. Refused once anything was recorded in
 * it - a meeting with attendance or money happened, whatever its status says.
 */
router.post("/groups/:id/meetings/:meetingId/cancel", requireAuth("meetings:write"), async (req, res, next) => {
  try {
    const payload = meetingCancelSchema.parse(req.body);
    const groupId = routeParam(req.params.id, "id");
    const meetingId = routeParam(req.params.meetingId, "meetingId");
    await assertGroupAccess(req.user, groupId);

    const result = await prisma.$transaction(async (tx) => {
      const existing = await tx.meeting.findFirst({
        where: { id: meetingId, groupId },
        include: { _count: { select: { attendance: true, ledgerEntries: true } } }
      });
      if (!existing) throw new ApiHttpError(404, "MEETING_NOT_FOUND", "Meeting does not exist or is outside this group.");
      if (existing.status === "CANCELLED") {
        const meeting = await tx.meeting.findUniqueOrThrow({ where: { id: meetingId }, include: meetingInclude(req.user) });
        return { meeting, changed: false };
      }
      if (!["SCHEDULED", "KEY_UNLOCK_PENDING"].includes(existing.status)) {
        throw new ApiHttpError(409, "MEETING_NOT_CANCELLABLE", "Only a meeting that has not started can be cancelled.", {
          status: existing.status
        });
      }
      if (existing._count.attendance > 0 || existing._count.ledgerEntries > 0) {
        throw new ApiHttpError(
          409,
          "MEETING_HAS_RECORDS",
          "Attendance or money is already recorded in this meeting, so it took place and cannot be cancelled."
        );
      }
      const meeting = await tx.meeting.update({
        where: { id: meetingId },
        data: {
          status: "CANCELLED",
          cancelledAt: new Date(),
          cancelledByUserId: req.user?.id ?? null,
          cancelReason: payload.reason
        },
        include: meetingInclude(req.user)
      });
      return { meeting, changed: true };
    });

    if (result.changed) {
      await appendAuditEvent({
        actorUserId: req.user?.id,
        entityType: "MEETING",
        entityId: meetingId,
        type: "MEETING_CANCELLED",
        payload: { groupId, meetingId, reason: payload.reason }
      });
    }

    ok(res, result.meeting);
  } catch (error) {
    next(error);
  }
});

/**
 * A phone reports what its user did to a meeting held on the phone.
 *
 * The phone runs its own three-key unlock offline, so it cannot go through
 * /open (which checks the keys here). Without this a phone-held meeting stayed
 * SCHEDULED on the server forever. Each event is the record of a person's
 * action; the server never infers either one from the clock.
 *
 * Safe to resend: an event that already happened returns the meeting as it
 * is. A meeting never moves backwards, and a cancelled one is not revived.
 */
router.post("/groups/:id/meetings/:meetingId/phone-lifecycle", requireAuth("meetings:write"), async (req, res, next) => {
  try {
    const payload = meetingPhoneLifecycleSchema.parse(req.body);
    const groupId = routeParam(req.params.id, "id");
    const meetingId = routeParam(req.params.meetingId, "meetingId");
    await assertGroupAccess(req.user, groupId);

    // A phone clock running ahead must not date a meeting in the future.
    const now = new Date();
    const at = new Date(Math.min(new Date(payload.at).getTime(), now.getTime()));

    const result = await prisma.$transaction(async (tx) => {
      const existing = await assertMeetingInGroup(tx, groupId, meetingId);
      if (existing.status === "CANCELLED") {
        throw new ApiHttpError(409, "MEETING_CANCELLED", "This meeting was cancelled, so it cannot be started or closed.");
      }

      let data: Prisma.MeetingUpdateInput | null = null;
      if (payload.event === "STARTED" && ["SCHEDULED", "KEY_UNLOCK_PENDING"].includes(existing.status)) {
        data = { status: "IN_PROGRESS", openedAt: at };
      }
      if (payload.event === "CLOSED" && ["SCHEDULED", "KEY_UNLOCK_PENDING", "IN_PROGRESS"].includes(existing.status)) {
        data = { status: "SEALED", openedAt: existing.openedAt ?? at, closedAt: at };
      }

      const meeting = data
        ? await tx.meeting.update({ where: { id: meetingId }, data, include: meetingInclude(req.user) })
        : await tx.meeting.findUniqueOrThrow({ where: { id: meetingId }, include: meetingInclude(req.user) });
      return { meeting, changed: data !== null };
    });

    if (result.changed) {
      await appendAuditEvent({
        actorUserId: req.user?.id,
        entityType: "MEETING",
        entityId: meetingId,
        type: payload.event === "STARTED" ? "MEETING_STARTED_ON_PHONE" : "MEETING_CLOSED_ON_PHONE",
        payload: { groupId, meetingId, at: at.toISOString() }
      });
      // "Has started" is news only while it is true. A start that reaches the
      // server hours later, after the phone was offline, is not texted.
      if (payload.event === "STARTED" && now.getTime() - at.getTime() <= 2 * 60 * 60 * 1000) {
        await notifyMeetingActive(groupId, result.meeting.title);
      }
    }

    ok(res, result.meeting);
  } catch (error) {
    next(error);
  }
});

/**
 * The group's meeting days and time. Used only to remind members - the
 * reminder planner puts the next meeting on the calendar so the reminders
 * have something to be about. It never opens one.
 */
router.put("/groups/:id/meeting-schedule", requireAuth("meetings:write"), async (req, res, next) => {
  try {
    const payload = meetingScheduleSchema.parse(req.body);
    const groupId = routeParam(req.params.id, "id");
    await assertGroupAccess(req.user, groupId);

    const days = [...new Set(payload.days)].sort((a, b) => a - b);
    const before = await prisma.group.findUniqueOrThrow({
      where: { id: groupId },
      select: { meetingFrequency: true, meetingDays: true, meetingTime: true }
    });
    const scheduleChanged =
      before.meetingFrequency !== payload.frequency ||
      before.meetingDays !== JSON.stringify(days) ||
      before.meetingTime !== payload.time;
    const group = await prisma.group.update({
      where: { id: groupId },
      data: {
        meetingFrequency: payload.frequency,
        meetingDays: JSON.stringify(days),
        meetingTime: payload.time,
        meetingDay: meetingDaysLabel(days),
        ...(payload.remindersEnabled !== undefined ? { remindersEnabled: payload.remindersEnabled } : {})
      },
      select: {
        id: true,
        meetingFrequency: true,
        meetingTime: true,
        meetingDay: true,
        remindersEnabled: true
      }
    });

    // Plans the reminder planner made from the OLD days no longer match: their
    // reminders would send members to a meeting on a day the group does not
    // meet. Only the planner's own future plans with nothing recorded go; a
    // meeting a person scheduled, or one that happened, is never touched. The
    // planner puts the right day on the calendar on its next run.
    // Saving the same days again withdraws nothing: re-planning would send the
    // same reminder twice.
    const withdrawn = scheduleChanged
      ? await prisma.meeting.deleteMany({
          where: {
            groupId,
            source: "AUTO_SCHEDULE",
            status: "SCHEDULED",
            scheduledAt: { gt: new Date() },
            attendance: { none: {} },
            ledgerEntries: { none: {} }
          }
        })
      : { count: 0 };

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "GROUP",
      entityId: groupId,
      type: "MEETING_SCHEDULE_UPDATED",
      payload: { groupId, frequency: payload.frequency, days, time: payload.time, plansWithdrawn: withdrawn.count }
    });

    ok(res, { ...group, meetingDays: days });
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/meetings/:meetingId/otp-batch", requireAuth("meeting-keys:write"), async (req, res, next) => {
  try {
    const payload = otpBatchSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    await prisma.$transaction((tx) => assertMeetingInGroup(tx, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId")));
    const results = await prisma.$transaction(async (tx) => {
      const members = await tx.member.findMany({
        where: { id: { in: payload.memberIds }, groupId: routeParam(req.params.id, "id"), status: "ACTIVE" },
        select: memberSelect
      });
      if (members.length !== payload.memberIds.length) {
        throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "One or more selected members are outside this group.");
      }
      return Promise.all(
        members.map((member) =>
          generateAndQueueMemberOtp(tx, member, {
            requestedByUserId: req.user?.id,
            select: memberSelect
          })
        )
      );
    }, credentialTransactionOptions);
    const deliveredResults = await Promise.all(
      results.map(async (result) => ({
        member: result.member,
        delivery: (await sendQueuedMemberPinDelivery(result.delivery.id)) ?? result.delivery
      }))
    );
    ok(res, deliveredResults.map((result) => serializeMember(result.member, { viewerRole: req.user?.role, delivery: result.delivery })));
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/meetings/:meetingId/key-submissions", requireAuth("meeting-keys:write"), async (req, res, next) => {
  try {
    const payload = meetingKeySubmissionBatchSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const result = await prisma.$transaction(async (tx) => {
      await assertMeetingInGroup(tx, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"));
      const submissions = [];
      for (const submission of payload.submissions) {
        submissions.push(
          await recordMeetingKeySubmission(tx, req.user, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"), submission)
        );
      }
      const unlock = await evaluateMeetingUnlock(tx, routeParam(req.params.meetingId, "meetingId"));
      return { submissions, ...unlock };
    }, credentialTransactionOptions);

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "MEETING",
      entityId: routeParam(req.params.meetingId, "meetingId"),
      type: "MEETING_KEY_SUBMITTED",
      payload: { groupId: routeParam(req.params.id, "id"), meetingId: routeParam(req.params.meetingId, "meetingId"), count: payload.submissions.length }
    });

    ok(res, result);
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/meetings/:meetingId/open", requireAuth("meetings:write"), async (req, res, next) => {
  try {
    const payload = meetingOpenSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const meeting = await prisma.$transaction(async (tx) => {
      const existing = await assertMeetingInGroup(tx, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"));
      if (existing.status === "SEALED") throw new ApiHttpError(400, "MEETING_SEALED", "A sealed meeting cannot be reopened.");
      if (existing.status === "CANCELLED") {
        throw new ApiHttpError(400, "MEETING_CANCELLED", "This meeting was cancelled. Schedule a new one instead.");
      }
      for (const submission of payload.keySubmissions) {
        await recordMeetingKeySubmission(tx, req.user, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"), submission);
      }
      const unlock = await evaluateMeetingUnlock(tx, routeParam(req.params.meetingId, "meetingId"));
      if (!unlock.canOpen) {
        throw new ApiHttpError(400, "MEETING_UNLOCK_INCOMPLETE", unlock.message, unlock);
      }
      return activateMeeting(tx, req.user, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"), payload.gpsCompliant, unlock.unlockStatus);
    }, credentialTransactionOptions);

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "MEETING",
      entityId: meeting.id,
      type: "MEETING_OPENED",
      payload: { groupId: routeParam(req.params.id, "id"), meetingId: meeting.id, unlockStatus: meeting.unlockStatus }
    });
    await notifyMeetingActive(routeParam(req.params.id, "id"), meeting.title);

    ok(res, meeting);
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/meetings/:meetingId/attendance", requireAuth("meetings:write"), async (req, res, next) => {
  try {
    const payload = attendanceSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const attendance = await prisma.$transaction(async (tx) => {
      await assertMeetingInGroup(tx, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"));
      const member = await tx.member.findFirst({
        where: { id: payload.memberId, groupId: routeParam(req.params.id, "id") },
        select: { id: true }
      });
      if (!member) throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist or is outside this group.");
      return tx.attendance.upsert({
        where: { meetingId_memberId: { meetingId: routeParam(req.params.meetingId, "meetingId"), memberId: payload.memberId } },
        create: { meetingId: routeParam(req.params.meetingId, "meetingId"), memberId: payload.memberId, status: payload.status },
        update: { status: payload.status, recordedAt: new Date() },
        include: { member: { select: nestedMemberSelect } }
      });
    });
    ok(res, attendance);
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/meetings/:meetingId/steps/:step/complete", requireAuth("meetings:write"), async (req, res, next) => {
  try {
    const step = routeParam(req.params.step, "step") as MeetingStep;
    if (!meetingSteps.includes(step)) throw new ApiHttpError(400, "INVALID_MEETING_STEP", "Unknown meeting step.");
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const result = await prisma.$transaction(async (tx) => {
      const meeting = await assertMeetingInGroup(tx, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"));
      if (meeting.status !== "IN_PROGRESS") {
        throw new ApiHttpError(400, "MEETING_NOT_ACTIVE", "Meeting must be active before completing workflow steps.");
      }
      const completed = await tx.meetingStepRecord.findMany({
        where: { meetingId: routeParam(req.params.meetingId, "meetingId"), status: "COMPLETED" },
        orderBy: { createdAt: "asc" },
        select: { step: true }
      });
      assertMeetingStepOrder(completed.map((row) => row.step as MeetingStep), step);
      const updated = await tx.meetingStepRecord.update({
        where: { meetingId_step: { meetingId: routeParam(req.params.meetingId, "meetingId"), step } },
        data: { status: "COMPLETED", completedAt: new Date() }
      });
      const nextStep = meetingSteps[meetingSteps.indexOf(step) + 1];
      if (nextStep) {
        await tx.meetingStepRecord.update({
          where: { meetingId_step: { meetingId: routeParam(req.params.meetingId, "meetingId"), step: nextStep } },
          data: { status: "ACTIVE" }
        });
      }
      return updated;
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "MEETING",
      entityId: routeParam(req.params.meetingId, "meetingId"),
      type: "MEETING_STEP_COMPLETED",
      payload: { groupId: routeParam(req.params.id, "id"), meetingId: routeParam(req.params.meetingId, "meetingId"), step }
    });

    ok(res, result);
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/meetings/:meetingId/seal", requireAuth("meetings:write"), async (req, res, next) => {
  try {
    const payload = meetingSealSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const meeting = await prisma.$transaction(async (tx) => {
      const existing = await assertMeetingInGroup(tx, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"));
      if (existing.status !== "IN_PROGRESS") {
        throw new ApiHttpError(400, "MEETING_NOT_ACTIVE", "Only active meetings can be sealed.");
      }
      // Identity FIRST, before anything about the meeting's state is checked
      // or revealed. Someone who cannot prove they are an official of this
      // group has no business learning how far through its agenda it is.
      const sealingMemberId = meetingKeyMemberId(req.user, payload.keySubmission);
      const official = await tx.member.findFirst({
        where: { id: sealingMemberId, groupId: routeParam(req.params.id, "id"), status: "ACTIVE" },
        select: {
          id: true,
          fullName: true,
          role: true,
          pinHash: true,
          currentOtpHash: true,
          currentOtpExpiresAt: true
        }
      });
      if (!official) {
        throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist or is outside this group.");
      }
      if (!officialMemberRoles.has(official.role)) {
        throw new ApiHttpError(
          403,
          "OFFICIAL_REQUIRED",
          `${official.fullName} is not an official of this group. Closing a meeting needs a chairperson, secretary, treasurer, money counter or key holder.`,
          { memberId: official.id, role: official.role }
        );
      }
      if (req.user?.role === "MEMBER" && req.user.memberId !== official.id) {
        throw new ApiHttpError(403, "FORBIDDEN", "Members can only submit their own meeting key.");
      }
      await verifyMeetingCredential(payload.keySubmission, official);

      const completedCount = await tx.meetingStepRecord.count({
        where: { meetingId: routeParam(req.params.meetingId, "meetingId"), status: "COMPLETED" }
      });
      if (completedCount < meetingSteps.length) {
        throw new ApiHttpError(400, "MEETING_WORKFLOW_INCOMPLETE", "Complete every meeting step before sealing.");
      }

      const [ledgerCount, voteCount] = await Promise.all([
        tx.ledgerEntry.count({ where: { meetingId: routeParam(req.params.meetingId, "meetingId") } }),
        tx.vote.count({ where: { meetingId: routeParam(req.params.meetingId, "meetingId") } })
      ]);
      return tx.meeting.update({
        where: { id: routeParam(req.params.meetingId, "meetingId") },
        data: {
          status: "SEALED",
          closedAt: new Date(),
          minutes: payload.minutes,
          sealedByMemberId: official.id,
          transactionTotal: ledgerCount + voteCount
        },
        include: meetingInclude(req.user)
      });
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "MEETING",
      entityId: meeting.id,
      type: "MEETING_SEALED",
      payload: { groupId: routeParam(req.params.id, "id"), meetingId: meeting.id }
    });

    ok(res, meeting);

    // Every member gets their own transactions for the meeting just closed.
    // Thirty members is thirty sequential provider calls, so this runs after
    // the response: sealing is the operation being confirmed, not the texting.
    dispatchAfterResponse(() =>
      sendMeetingSummaries(meeting.id, { requestedByUserId: req.user?.id ?? null })
    );
  } catch (error) {
    next(error);
  }
});

router.get("/groups/:id/offline-devices", requireAuth("meetings:read"), async (req, res, next) => {
  try {
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const devices = await prisma.offlineDevice.findMany({
      where: { groupId: routeParam(req.params.id, "id") },
      orderBy: { updatedAt: "desc" }
    });
    ok(res, devices);
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/offline-devices/prepare", requireAuth("meeting-keys:write"), async (req, res, next) => {
  try {
    const payload = offlineDevicePrepareSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const cacheExpiresAt = new Date(Date.now() + payload.cacheTtlHours * 60 * 60 * 1000);
    const prepared = await prisma.$transaction(async (tx) => {
      const verifiers = [];
      for (const item of payload.memberPins) {
        const member = await tx.member.findFirst({
          where: { id: item.memberId, groupId: routeParam(req.params.id, "id"), status: "ACTIVE" },
          select: { id: true, fullName: true, phone: true, role: true, pinHash: true }
        });
        if (!member || !member.pinHash || !(await bcrypt.compare(item.pin, member.pinHash))) {
          throw new ApiHttpError(400, "INVALID_MEMBER_CREDENTIAL", "One or more default PINs are invalid.");
        }
        verifiers.push({
          memberId: member.id,
          fullName: member.fullName,
          role: member.role,
          verifier: buildOfflineVerifier(payload.deviceId, member.id, item.pin)
        });
      }

      const device = await tx.offlineDevice.upsert({
        where: { groupId_deviceId: { groupId: routeParam(req.params.id, "id"), deviceId: payload.deviceId } },
        create: {
          groupId: routeParam(req.params.id, "id"),
          userId: req.user?.id ?? null,
          deviceId: payload.deviceId,
          status: "ACTIVE",
          cacheExpiresAt,
          lastPreparedAt: new Date()
        },
        update: {
          userId: req.user?.id ?? null,
          status: "ACTIVE",
          cacheExpiresAt,
          lastPreparedAt: new Date()
        }
      });

      return { device, verifiers };
    }, credentialTransactionOptions);

    ok(res, {
      ...prepared,
      encryption: { algorithm: "SHA-256", deviceBound: true, expiresAt: cacheExpiresAt }
    });
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/offline-devices/refresh", requireAuth("meeting-keys:write"), async (req, res, next) => {
  try {
    const payload = offlineDeviceRefreshSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const cacheExpiresAt = new Date(Date.now() + payload.cacheTtlHours * 60 * 60 * 1000);
    const refreshed = await prisma.$transaction(async (tx) => {
      const { verifiers, skipped } = await buildAutomaticOfflineVerifiers(tx, routeParam(req.params.id, "id"), payload.deviceId);
      const device = await tx.offlineDevice.upsert({
        where: { groupId_deviceId: { groupId: routeParam(req.params.id, "id"), deviceId: payload.deviceId } },
        create: {
          groupId: routeParam(req.params.id, "id"),
          userId: req.user?.id ?? null,
          deviceId: payload.deviceId,
          status: "ACTIVE",
          cacheExpiresAt,
          lastPreparedAt: new Date()
        },
        update: {
          userId: req.user?.id ?? null,
          status: "ACTIVE",
          cacheExpiresAt,
          lastPreparedAt: new Date()
        }
      });

      return { device, verifiers, skipped };
    }, credentialTransactionOptions);

    ok(res, {
      ...refreshed,
      encryption: { algorithm: "SHA-256", deviceBound: true, expiresAt: cacheExpiresAt }
    });
  } catch (error) {
    next(error);
  }
});

router.patch("/groups/:id/offline-devices/:deviceId", requireAuth("groups:write"), async (req, res, next) => {
  try {
    const payload = offlineDeviceStatusSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const device = await prisma.offlineDevice.update({
      where: { groupId_deviceId: { groupId: routeParam(req.params.id, "id"), deviceId: routeParam(req.params.deviceId, "deviceId") } },
      data: { status: payload.status }
    });
    ok(res, device);
  } catch (error) {
    next(error);
  }
});

router.get("/groups/:id/ledger", requireAuth("ledger:read"), async (req, res, next) => {
  try {
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const meetingId = typeof req.query.meetingId === "string" ? req.query.meetingId : undefined;
    const ledger = await prisma.ledgerEntry.findMany({
      where: ledgerScopeForUser(req.user, { groupId: routeParam(req.params.id, "id"), ...(meetingId ? { meetingId } : {}) }),
      orderBy: { createdAt: "desc" },
      include: {
        group: { select: { id: true, name: true, code: true, county: true } },
        member: { select: nestedMemberSelect },
        meeting: { select: { id: true, title: true, status: true } },
        fundAccount: { select: { id: true, type: true, currency: true } }
      }
    });
    ok(res, ledger);
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/ledger", requireAuth("ledger:write"), async (req, res, next) => {
  try {
    const payload = ledgerCreateSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const ledgerEntry = await prisma.$transaction(async (tx) => {
      const shape = (meetingLedgerRules as Partial<Record<string, { direction: "CREDIT" | "DEBIT" }>>)[payload.type];
      // A mis-shaped entry is refused by appendLedgerEntry with its own reason;
      // the group's rules only speak to an entry that is otherwise valid.
      if (!shape || shape.direction === payload.direction) await assertFollowsGroupRules(tx, routeParam(req.params.id, "id"), {
        type: payload.type,
        amountCents: payload.amountCents,
        memberId: payload.memberId,
        meetingId: payload.meetingId
      });
      return appendLedgerEntry(tx, {
        groupId: routeParam(req.params.id, "id"),
        memberId: payload.memberId,
        meetingId: payload.meetingId,
        fundAccountId: payload.fundAccountId,
        type: payload.type,
        amountCents: payload.amountCents,
        direction: payload.direction,
        description: payload.description,
        externalReference: payload.externalReference,
        clientRequestId: payload.clientRequestId
      });
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "LEDGER_ENTRY",
      entityId: ledgerEntry.id,
      type: "LEDGER_ENTRY_APPENDED",
      payload: { groupId: routeParam(req.params.id, "id"), ledgerEntryId: ledgerEntry.id }
    });

    ok(res.status(201), ledgerEntry);

    dispatchAfterResponse(() =>
      notifySharePurchases([ledgerEntry], { requestedByUserId: req.user?.id ?? null })
    );
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/meetings/:meetingId/ledger/batch", requireAuth("ledger:write"), async (req, res, next) => {
  try {
    const payload = meetingLedgerBatchSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const entries = await prisma.$transaction(async (tx) => {
      await assertMeetingInGroup(tx, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"));
      const rules = payload.source === "WEB" ? await groupRules(tx, routeParam(req.params.id, "id")) : null;
      const created = [];
      for (const entry of payload.entries) {
        if (rules) {
          await assertFollowsGroupRules(
            tx,
            routeParam(req.params.id, "id"),
            { ...entry, meetingId: routeParam(req.params.meetingId, "meetingId") },
            rules
          );
        }
        created.push(await appendMeetingLedgerEntry(tx, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"), entry));
      }
      return created;
    });
    ok(res.status(201), entries);

    // Share-purchase confirmations. AFTER the response, because Bonga takes one
    // recipient per request and a treasurer should not wait on the network to
    // learn their entries were saved. The money is already committed; a failed
    // text is recorded, never thrown.
    dispatchAfterResponse(() =>
      notifySharePurchases(entries, { requestedByUserId: req.user?.id ?? null })
    );
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/meetings/:meetingId/offline-sync", requireAuth("ledger:write"), async (req, res, next) => {
  try {
    const payload = offlineSyncSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const result = await prisma.$transaction(async (tx) => {
      await assertMeetingInGroup(tx, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"));
      const device = await tx.offlineDevice.findUnique({
        where: { groupId_deviceId: { groupId: routeParam(req.params.id, "id"), deviceId: payload.deviceId } }
      });
      if (!device || device.status !== "ACTIVE" || device.cacheExpiresAt < new Date()) {
        throw new ApiHttpError(400, "OFFLINE_DEVICE_NOT_ACTIVE", "This offline device is not active for sync.");
      }

      const synced: Array<{ kind: string; id: string }> = [];
      const savedLedgerEntries: Awaited<ReturnType<typeof appendMeetingLedgerEntry>>[] = [];
      const conflicts: Array<{ kind: string; clientRequestId?: string | null; memberId?: string; code: string; message: string }> = [];

      for (const submission of payload.keySubmissions) {
        try {
          const saved = await recordMeetingKeySubmission(tx, req.user, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"), {
            ...submission,
            deviceId: submission.deviceId ?? payload.deviceId
          });
          synced.push({ kind: "keySubmission", id: saved.id });
        } catch (error) {
          conflicts.push({
            kind: "keySubmission",
            memberId: submission.memberId,
            code: error instanceof ApiHttpError ? error.code : "KEY_SYNC_FAILED",
            message: error instanceof Error ? error.message : "Meeting key could not be verified."
          });
        }
      }

      for (const item of payload.attendance) {
        try {
          const member = await tx.member.findFirst({
            where: { id: item.memberId, groupId: routeParam(req.params.id, "id") },
            select: { id: true }
          });
          if (!member) throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist or is outside this group.");
          const saved = await tx.attendance.upsert({
            where: { meetingId_memberId: { meetingId: routeParam(req.params.meetingId, "meetingId"), memberId: item.memberId } },
            create: { meetingId: routeParam(req.params.meetingId, "meetingId"), memberId: item.memberId, status: item.status },
            update: { status: item.status, recordedAt: new Date() }
          });
          synced.push({ kind: "attendance", id: saved.id });
        } catch (error) {
          conflicts.push({
            kind: "attendance",
            clientRequestId: item.clientRequestId,
            memberId: item.memberId,
            code: error instanceof ApiHttpError ? error.code : "ATTENDANCE_SYNC_FAILED",
            message: error instanceof Error ? error.message : "Attendance could not be synced."
          });
        }
      }

      for (const entry of payload.ledgerEntries) {
        try {
          if (entry.clientRequestId) {
            const existing = await tx.ledgerEntry.findUnique({
              where: { clientRequestId: entry.clientRequestId },
              select: { id: true }
            });
            if (existing) {
              // A replay after a lost response is an idempotent success, not
              // a conflict. The phone must be able to converge after retry.
              synced.push({ kind: "ledgerEntry", id: existing.id });
              continue;
            }
          }
          const saved = await appendMeetingLedgerEntry(tx, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"), entry);
          synced.push({ kind: "ledgerEntry", id: saved.id });
          // Kept whole, not just its id: a share purchase captured offline is
          // still a share purchase and the member is owed the same
          // confirmation as one entered while the phone had signal.
          savedLedgerEntries.push(saved);
        } catch (error) {
          conflicts.push({
            kind: "ledgerEntry",
            clientRequestId: entry.clientRequestId,
            memberId: entry.memberId,
            code: error instanceof ApiHttpError ? error.code : "LEDGER_SYNC_FAILED",
            message: error instanceof Error ? error.message : "Ledger entry could not be synced."
          });
        }
      }

      await tx.offlineDevice.update({
        where: { id: device.id },
        data: { lastSyncedAt: new Date() }
      });

      if (conflicts.length > 0) {
        await tx.meeting.update({
          where: { id: routeParam(req.params.meetingId, "meetingId") },
          data: { status: "SYNC_CONFLICT" }
        });
      }

      return { synced, conflicts, savedLedgerEntries };
    }, credentialTransactionOptions);

    ok(res, { synced: result.synced, conflicts: result.conflicts });

    dispatchAfterResponse(() =>
      notifySharePurchases(result.savedLedgerEntries, { requestedByUserId: req.user?.id ?? null })
    );
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/meetings/:meetingId/share-out/preview", requireAuth("ledger:read"), async (req, res, next) => {
  try {
    const payload = shareOutPreviewSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const preview = await prisma.$transaction(async (tx) => {
      await assertMeetingInGroup(tx, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"));
      return computeShareOutPreview(tx, routeParam(req.params.id, "id"), payload.poolAmountCents, {
        distributeWelfare: payload.distributeWelfare
      });
    });
    // Partners, lenders and read-only viewers see the split, not who gets it
    // (Kenya DPA 2019: minimisation). The group and its officials keep names.
    if (["PARTNER_OFFICER", "LENDER", "READ_ONLY"].includes(req.user?.role ?? "")) {
      ok(res, { ...preview, rows: preview.rows.map((row) => ({ ...row, memberId: null, member: null })) });
      return;
    }
    ok(res, preview);
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/meetings/:meetingId/share-out/post", requireAuth("ledger:write"), async (req, res, next) => {
  try {
    const payload = shareOutPostSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const result = await prisma.$transaction(async (tx) => {
      await assertMeetingInGroup(tx, routeParam(req.params.id, "id"), routeParam(req.params.meetingId, "meetingId"));
      const groupId = routeParam(req.params.id, "id");
      const meetingId = routeParam(req.params.meetingId, "meetingId");
      const preview = await computeShareOutPreview(tx, groupId, payload.poolAmountCents, {
        distributeWelfare: payload.distributeWelfare
      });
      const prefix = payload.clientRequestPrefix ?? `shareout-${meetingId}`;
      const entries = [];
      const settlements = [];

      for (const row of preview.rows) {
        // Three truthful entries, not one netted one.
        //
        // The member's WHOLE entitlement leaves the fund it sat in, and the
        // settled loan comes back as a real repayment. Writing the netted
        // figure as the payout instead would credit the loan fund the full
        // repayment while debiting it only the remainder, leaving the fund
        // richer than reality by exactly the amount settled.
        //
        //   entitlement 500 from the loan fund, loan owed 300:
        //     gross:  −500 payout +300 repayment = −200  ✓ cash handed over
        //     netted: −200 payout +300 repayment = +100  ✗ money invented
        //
        // The cash actually counted out at the table is netPayoutCents; the
        // ledger explains where it came from.
        if (row.loanOffsetCents > 0) {
          // Through the same path every other repayment takes, so it is
          // attributed to the loan and closes it. Merely paying less cash
          // would leave the loan ACTIVE and the member would owe it again
          // next cycle — "netted off, never carried forward" would be false.
          settlements.push(
            await appendMeetingLedgerEntry(tx, groupId, meetingId, {
              memberId: row.memberId,
              type: "LOAN_REPAYMENT",
              amountCents: row.loanOffsetCents,
              description: "Loan settled from share-out",
              clientRequestId: `${prefix}-settle-${row.memberId}`
            })
          );
        }

        if (row.payoutCents > 0) {
          entries.push(
            await appendMeetingLedgerEntry(tx, groupId, meetingId, {
              memberId: row.memberId,
              type: "SHARE_OUT_PAYOUT",
              amountCents: row.payoutCents,
              description: payload.description ?? "Reviewed share-out payout",
              clientRequestId: `${prefix}-${row.memberId}`
            })
          );
        }

        // The welfare remainder leaves the SOCIAL fund, never the loan fund.
        if (row.welfareCents > 0) {
          entries.push(
            await appendMeetingLedgerEntry(tx, groupId, meetingId, {
              memberId: row.memberId,
              type: "WELFARE_SHARE_OUT",
              amountCents: row.welfareCents,
              description: "Welfare fund shared out",
              clientRequestId: `${prefix}-welfare-${row.memberId}`
            })
          );
        }
      }

      return {
        preview,
        entries,
        settlements,
        membersOwing: preview.rows
          .filter((row) => row.owesGroup)
          .map((row) => ({
            memberId: row.memberId,
            owedCents: Math.abs(row.netPayoutCents)
          }))
      };
    });
    ok(res.status(201), result);
  } catch (error) {
    next(error);
  }
});

/**
 * The group's current credit rating under the rating contract. Returns the
 * latest stored rating, or computes a fresh one when the group has never been
 * scored (so a new group always gets an honest UNRATED answer rather than a
 * 404).
 */
router.get("/groups/:id/credit-score", requireAuth("groups:read"), async (req, res, next) => {
  try {
    const groupId = routeParam(req.params.id, "id");
    await assertGroupAccess(req.user, groupId);
    const stored = await latestCreditRating(groupId);
    ok(res, stored ?? (await computeCreditRating(groupId)));
  } catch (error) {
    next(error);
  }
});

/** Recomputes the rating from current data and stores it (audit-logged). */
router.post(
  "/groups/:id/credit-score/recompute",
  requireAuth("groups:write"),
  async (req, res, next) => {
    try {
      const groupId = routeParam(req.params.id, "id");
      await assertGroupAccess(req.user, groupId);
      const rating = await computeAndStoreCreditRating(groupId, req.user?.id);
      ok(res.status(201), rating);
    } catch (error) {
      next(error);
    }
  }
);

router.get("/groups/:id/votes", requireAuth("votes:read"), async (req, res, next) => {
  try {
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    const votes = await prisma.vote.findMany({
      where: { groupId: routeParam(req.params.id, "id") },
      orderBy: { createdAt: "desc" },
      include: { meeting: { select: { id: true, title: true, status: true } } }
    });
    ok(res, votes);
  } catch (error) {
    next(error);
  }
});

router.post("/groups/:id/votes", requireAuth("votes:write"), async (req, res, next) => {
  try {
    const payload = voteCreateSchema.parse(req.body);
    await assertGroupAccess(req.user, routeParam(req.params.id, "id"));
    await assertModuleEnabled(req.user, "voting", { groupId: routeParam(req.params.id, "id") });
    const vote = await prisma.$transaction(async (tx) => {
      if (payload.meetingId) await assertMeetingInGroup(tx, routeParam(req.params.id, "id"), payload.meetingId);
      const hashPayload = {
        groupId: routeParam(req.params.id, "id"),
        ...payload
      };
      return tx.vote.create({
        data: {
          groupId: routeParam(req.params.id, "id"),
          meetingId: payload.meetingId,
          resolutionType: payload.resolutionType,
          motion: payload.motion,
          result: payload.result,
          quorumRequired: payload.quorumRequired,
          yesCount: payload.yesCount,
          noCount: payload.noCount,
          abstainCount: payload.abstainCount,
          totalEligible: payload.totalEligible,
          hash: signLedgerEntry(hashPayload)
        },
        include: { meeting: { select: { id: true, title: true, status: true } } }
      });
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "VOTE",
      entityId: vote.id,
      type: "VOTE_RECORDED",
      payload: { groupId: routeParam(req.params.id, "id"), voteId: vote.id }
    });

    ok(res.status(201), vote);
  } catch (error) {
    next(error);
  }
});

export { router as groupsRouter };
