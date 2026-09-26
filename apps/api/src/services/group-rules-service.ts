/**
 * A group's own money rules, and whether an entry keeps to them.
 *
 * The rules are the group's (share value, most shares a member may buy at one
 * meeting, the social-fund amount, how much a member may borrow against their
 * savings). They are set on the group's phone and pushed to GroupPolicy, and
 * ONLY GroupPolicy counts. The group row's share settings are schema defaults
 * (KSh 500, 10 shares) that every imported or console-made group carries
 * whether or not anyone chose them — the production rehearsal of 25 Sep 2026
 * found a live group saving KSh 50 shares that the default would have refused.
 *
 * Who is checked (decided 24 Sep 2026): entries typed on the web are refused
 * when they break a rule. Entries synced from a phone are NEVER refused — the
 * phone already enforces the same rules and is the group's book of record, so
 * refusing would strand a meeting held offline. Anything that slips through is
 * listed by the consistency report instead.
 */

import type { Prisma, PrismaClient } from "@prisma/client";
import { ApiHttpError } from "../lib/http";
import { loadLoanPositions } from "./loan-position-service";

type Db = Prisma.TransactionClient | PrismaClient;

export interface GroupRules {
  shareValueCents: number | null;
  maxSharesPerMeeting: number | null;
  socialFundCents: number | null;
  loanMultiplierBps: number | null;
}

export async function groupRules(db: Db, groupId: string): Promise<GroupRules> {
  const policy = await db.groupPolicy.findUnique({
    where: { groupId },
    select: { shareValueCents: true, maxSharesPerMeeting: true, socialFundCents: true, loanMultiplierBps: true }
  });
  return {
    shareValueCents: policy?.shareValueCents ?? null,
    maxSharesPerMeeting: policy?.maxSharesPerMeeting ?? null,
    socialFundCents: policy?.socialFundCents ?? null,
    loanMultiplierBps: policy?.loanMultiplierBps ?? null
  };
}

export interface RuleCheckEntry {
  type: string;
  amountCents: number;
  memberId?: string | null;
  meetingId?: string | null;
}

const kes = (cents: number) => `KSh ${(cents / 100).toLocaleString("en-KE", { maximumFractionDigits: 2 })}`;

/**
 * Why [entry] breaks the group's rules, or null when it keeps to them. Looks
 * at what is already recorded, so it must run BEFORE the entry is written.
 */
export async function ruleViolation(
  db: Db,
  groupId: string,
  entry: RuleCheckEntry,
  rules?: GroupRules
): Promise<string | null> {
  const r = rules ?? (await groupRules(db, groupId));

  if (entry.type === "SHARE_PURCHASE" && r.shareValueCents && r.shareValueCents > 0) {
    if (entry.amountCents % r.shareValueCents !== 0) {
      return `A share purchase must be a whole number of shares of ${kes(r.shareValueCents)}.`;
    }
    if (r.maxSharesPerMeeting && entry.memberId && entry.meetingId) {
      const bought = await db.ledgerEntry.aggregate({
        where: { groupId, meetingId: entry.meetingId, memberId: entry.memberId, type: "SHARE_PURCHASE" },
        _sum: { amountCents: true }
      });
      const shares = ((bought._sum.amountCents ?? 0) + entry.amountCents) / r.shareValueCents;
      if (shares > r.maxSharesPerMeeting) {
        return `A member may buy at most ${r.maxSharesPerMeeting} share(s) at one meeting; this would make ${shares}.`;
      }
    }
  }

  if (entry.type === "SOCIAL_CONTRIBUTION" && r.socialFundCents && r.socialFundCents > 0) {
    if (entry.amountCents !== r.socialFundCents) {
      return `The social fund contribution is ${kes(r.socialFundCents)} per member.`;
    }
  }

  if (entry.type === "INTERNAL_LOAN_DISBURSEMENT" && r.loanMultiplierBps !== null && entry.memberId) {
    const cycle = await db.cycle.findFirst({
      where: { groupId, status: "ACTIVE" },
      orderBy: { number: "desc" },
      select: { id: true }
    });
    const saved = await db.ledgerEntry.aggregate({
      where: {
        groupId,
        memberId: entry.memberId,
        type: "SHARE_PURCHASE",
        ...(cycle ? { cycleId: cycle.id } : {})
      },
      _sum: { amountCents: true }
    });
    const savings = saved._sum.amountCents ?? 0;
    const owed = (await loadLoanPositions(db, { memberIds: [entry.memberId] }, new Date())).get(entry.memberId)
      ?.outstandingCents ?? 0;
    const limit = Math.max(0, Math.floor((savings * r.loanMultiplierBps) / 10_000) - owed);
    if (entry.amountCents > limit) {
      return (
        `This member may borrow up to ${kes(limit)}: ${r.loanMultiplierBps / 10_000}x their savings this cycle ` +
        `(${kes(savings)}) less ${kes(owed)} still owed.`
      );
    }
  }

  return null;
}

export async function assertFollowsGroupRules(db: Db, groupId: string, entry: RuleCheckEntry, rules?: GroupRules) {
  const reason = await ruleViolation(db, groupId, entry, rules);
  if (reason) throw new ApiHttpError(422, "GROUP_RULE_BROKEN", reason, { type: entry.type, memberId: entry.memberId });
}

/**
 * Does the group's record hold together? Read-only.
 *
 * - every fund's balance equals the sum of its ledger (credits less debits);
 * - every loan disbursement has its loan record, at the same principal, and
 *   every loan record points at a disbursement that exists;
 * - share purchases and social-fund contributions keep to the group's rules
 *   (phone entries are recorded regardless, so this is where a slip shows).
 */
export async function groupConsistency(db: Db, groupId: string) {
  const [funds, entries, loans, rules] = await Promise.all([
    db.fundAccount.findMany({ where: { groupId }, select: { id: true, type: true, balanceCents: true } }),
    db.ledgerEntry.findMany({
      where: { groupId },
      orderBy: [{ createdAt: "asc" }, { id: "asc" }],
      select: {
        id: true,
        type: true,
        amountCents: true,
        direction: true,
        fundAccountId: true,
        memberId: true,
        meetingId: true
      }
    }),
    db.loan.findMany({
      where: { groupId },
      select: { id: true, principalCents: true, disbursementEntryId: true, memberId: true }
    }),
    groupRules(db, groupId)
  ]);

  const net = new Map<string, number>();
  for (const entry of entries) {
    if (!entry.fundAccountId) continue;
    const signed = entry.direction === "CREDIT" ? entry.amountCents : -entry.amountCents;
    net.set(entry.fundAccountId, (net.get(entry.fundAccountId) ?? 0) + signed);
  }
  const fundChecks = funds.map((fund) => {
    const ledgerNetCents = net.get(fund.id) ?? 0;
    return { fundAccountId: fund.id, type: fund.type, balanceCents: fund.balanceCents, ledgerNetCents, ok: fund.balanceCents === ledgerNetCents };
  });

  const loanByDisbursement = new Map(loans.filter((loan) => loan.disbursementEntryId).map((loan) => [loan.disbursementEntryId!, loan]));
  const entryById = new Map(entries.map((entry) => [entry.id, entry]));
  const disbursementsWithoutLoan: string[] = [];
  const principalMismatches: Array<{ loanId: string; principalCents: number; disbursedCents: number }> = [];
  for (const entry of entries) {
    if (entry.type !== "INTERNAL_LOAN_DISBURSEMENT") continue;
    const loan = loanByDisbursement.get(entry.id);
    if (!loan) disbursementsWithoutLoan.push(entry.id);
    else if (loan.principalCents !== entry.amountCents) {
      principalMismatches.push({ loanId: loan.id, principalCents: loan.principalCents, disbursedCents: entry.amountCents });
    }
  }
  const loansWithoutDisbursement = loans
    .filter((loan) => loan.disbursementEntryId && !entryById.has(loan.disbursementEntryId))
    .map((loan) => loan.id);

  // Rules, replayed in order: shares bought so far at each meeting count
  // towards the per-meeting limit exactly as they did when recorded.
  const violations: Array<{ entryId: string; type: string; memberId: string | null; meetingId: string | null; amountCents: number; reason: string }> = [];
  const sharesAtMeeting = new Map<string, number>();
  for (const entry of entries) {
    let reason: string | null = null;
    if (entry.type === "SHARE_PURCHASE" && rules.shareValueCents && rules.shareValueCents > 0) {
      if (entry.amountCents % rules.shareValueCents !== 0) {
        reason = `Not a whole number of shares of ${kes(rules.shareValueCents)}.`;
      } else if (rules.maxSharesPerMeeting && entry.memberId && entry.meetingId) {
        const key = `${entry.meetingId}:${entry.memberId}`;
        const shares = (sharesAtMeeting.get(key) ?? 0) + entry.amountCents / rules.shareValueCents;
        sharesAtMeeting.set(key, shares);
        if (shares > rules.maxSharesPerMeeting) {
          reason = `${shares} shares at one meeting; the limit is ${rules.maxSharesPerMeeting}.`;
        }
      }
    }
    if (entry.type === "SOCIAL_CONTRIBUTION" && rules.socialFundCents && rules.socialFundCents > 0) {
      if (entry.amountCents !== rules.socialFundCents) {
        reason = `Social fund contribution of ${kes(entry.amountCents)}; the group's amount is ${kes(rules.socialFundCents)}.`;
      }
    }
    if (reason) {
      violations.push({
        entryId: entry.id,
        type: entry.type,
        memberId: entry.memberId,
        meetingId: entry.meetingId,
        amountCents: entry.amountCents,
        reason
      });
    }
  }

  const fundsOk = fundChecks.every((fund) => fund.ok);
  const loansOk =
    disbursementsWithoutLoan.length === 0 && loansWithoutDisbursement.length === 0 && principalMismatches.length === 0;
  return {
    groupId,
    checkedAt: new Date().toISOString(),
    // The ledger and the balances/loan records agree: the part that must never fail.
    ok: fundsOk && loansOk,
    funds: fundChecks,
    loans: { disbursementsWithoutLoan, loansWithoutDisbursement, principalMismatches },
    rules: { applied: rules, violations }
  };
}
