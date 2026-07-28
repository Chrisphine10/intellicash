import { prisma } from "../lib/prisma";
import {
  CREDIT_RATING_CONTRACT_VERSION,
  evaluateCreditRating,
  type CreditRating,
  type CreditRatingFacts
} from "../domain/credit-rating-contract";
import { appendAuditEvent } from "./audit-service";

/**
 * Derives a group's credit-rating facts from recorded data and runs them
 * through the rating contract.
 *
 * The split is deliberate: **this file only gathers evidence**, the contract
 * (`domain/credit-rating-contract.ts`) owns every rule. That keeps the rules
 * pure/testable and means a score can always be re-derived from the stored
 * facts + contract version.
 */

/** Meeting statuses that mean the meeting was actually opened. */
const OPENED_STATUSES = ["IN_PROGRESS", "SEALED", "SYNC_CONFLICT"];
/** Unlock statuses that satisfy the 3-key policy. */
const COMPLIANT_UNLOCK = ["OFFICIALS_VERIFIED", "FIVE_MEMBERS_VERIFIED"];
/** Attendance statuses that count as having shown up. */
const PRESENT_STATUSES = ["PRESENT", "LATE"];

export async function gatherCreditFacts(groupId: string): Promise<CreditRatingFacts> {
  const group = await prisma.group.findUnique({
    where: { id: groupId },
    select: { id: true, constitutionVersion: true, cycleNumber: true }
  });
  if (!group) {
    throw new Error(`Group ${groupId} not found`);
  }

  const [members, meetings, votesRecorded, ledger] = await Promise.all([
    prisma.member.findMany({
      where: { groupId, status: "ACTIVE" },
      select: { id: true, role: true }
    }),
    prisma.meeting.findMany({
      where: { groupId },
      select: { id: true, status: true, unlockStatus: true }
    }),
    prisma.vote.count({ where: { groupId } }),
    prisma.ledgerEntry.findMany({
      where: { groupId },
      select: { meetingId: true, type: true, amountCents: true }
    })
  ]);

  const meetingIds = meetings.map((m) => m.id);
  const attendance = meetingIds.length
    ? await prisma.attendance.findMany({
        where: { meetingId: { in: meetingIds } },
        select: { status: true }
      })
    : [];

  const roleCount = (role: string) => members.filter((m) => m.role === role).length;

  // Distinct meetings that saw each activity — "did they do it at this
  // meeting", not "how much", which is what the consistency factors ask.
  const meetingsWith = (type: string) =>
    new Set(
      ledger
        .filter((e) => e.type === type && e.meetingId)
        .map((e) => e.meetingId as string)
    ).size;

  const sumOf = (type: string) =>
    ledger.filter((e) => e.type === type).reduce((sum, e) => sum + e.amountCents, 0);

  const opened = meetings.filter((m) => OPENED_STATUSES.includes(m.status));

  return {
    activeMembers: members.length,
    officialRoles: {
      chairperson: roleCount("CHAIRPERSON"),
      secretary: roleCount("SECRETARY"),
      treasurer: roleCount("TREASURER"),
      moneyCounter: roleCount("MONEY_COUNTER"),
      keyHolder: roleCount("KEY_HOLDER")
    },
    hasConstitution: Boolean(group.constitutionVersion),
    votesRecorded,

    meetingsTotal: meetings.length,
    meetingsOpened: opened.length,
    meetingsUnlockCompliant: opened.filter((m) =>
      COMPLIANT_UNLOCK.includes(m.unlockStatus)
    ).length,
    meetingsSealed: meetings.filter((m) => m.status === "SEALED").length,
    meetingsWithSharePurchase: meetingsWith("SHARE_PURCHASE"),
    meetingsWithSocialContribution: meetingsWith("SOCIAL_CONTRIBUTION"),

    attendanceRecords: attendance.length,
    attendancePresent: attendance.filter((a) => PRESENT_STATUSES.includes(a.status)).length,

    loanDisbursedCents: sumOf("INTERNAL_LOAN_DISBURSEMENT"),
    loanRepaidCents: sumOf("LOAN_REPAYMENT"),

    cycleNumber: group.cycleNumber
  };
}

/** Computes a group's rating without persisting it (preview / dry-run). */
export async function computeCreditRating(groupId: string): Promise<CreditRating> {
  return evaluateCreditRating(await gatherCreditFacts(groupId));
}

/**
 * Computes and stores a group's rating, appending an audit event. The stored
 * `breakdownJson` carries the full factor breakdown, the raw facts and the
 * contract version, so any historical score can be explained and re-derived.
 */
export async function computeAndStoreCreditRating(
  groupId: string,
  actorUserId?: string
): Promise<CreditRating> {
  const facts = await gatherCreditFacts(groupId);
  const rating = evaluateCreditRating(facts);

  const stored = await prisma.creditScore.create({
    data: {
      groupId,
      score: rating.score,
      breakdownJson: JSON.stringify({
        contractVersion: rating.contractVersion,
        band: rating.band,
        rated: rating.rated,
        pillars: rating.pillars,
        factors: rating.factors,
        terms: rating.terms,
        recommendations: rating.recommendations,
        facts
      })
    },
    select: { id: true }
  });

  await appendAuditEvent({
    actorUserId,
    entityType: "GROUP",
    entityId: groupId,
    type: "CREDIT_SCORE_COMPUTED",
    payload: {
      groupId,
      creditScoreId: stored.id,
      score: rating.score,
      band: rating.band,
      contractVersion: rating.contractVersion
    }
  });

  return rating;
}

/**
 * The group's latest stored rating, rehydrated from `breakdownJson`. Returns
 * null when the group has never been scored. Scores written by an older
 * contract version are returned as-is (with their original version stamp) —
 * callers that need current-contract terms should recompute.
 */
export async function latestCreditRating(
  groupId: string
): Promise<(CreditRating & { computedAt: Date }) | null> {
  const row = await prisma.creditScore.findFirst({
    where: { groupId },
    orderBy: { computedAt: "desc" }
  });
  if (!row) return null;

  try {
    const parsed = JSON.parse(row.breakdownJson) as Partial<CreditRating> & {
      facts?: CreditRatingFacts;
    };
    // Rows written before this contract (e.g. seed fixtures) only carry the
    // legacy weighted breakdown — recompute from facts when we have them.
    if (parsed.factors && parsed.band) {
      return {
        contractVersion: parsed.contractVersion ?? "legacy",
        score: row.score,
        band: parsed.band,
        bandLabel: parsed.bandLabel ?? parsed.band,
        rated: parsed.rated ?? true,
        pillars: parsed.pillars ?? { governance: 0, compliance: 0 },
        factors: parsed.factors,
        terms: parsed.terms!,
        recommendations: parsed.recommendations ?? [],
        computedAt: row.computedAt
      };
    }
  } catch {
    // fall through to a live recompute below
  }

  // Legacy or unparsable row: recompute against the current contract so
  // callers always get a usable, current-version rating.
  const rating = await computeCreditRating(groupId);
  return { ...rating, computedAt: row.computedAt };
}

export { CREDIT_RATING_CONTRACT_VERSION };
