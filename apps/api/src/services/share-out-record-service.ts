/**
 * The closing act of a saving cycle: distributing the fund.
 *
 * Reconciles the phone's share-out with the server's ledger view, records the
 * result, and checks the arithmetic for discrepancies so a share-out that
 * does not balance is caught before it closes a cycle.
 */

import { z } from "zod";
import type { Prisma } from "@prisma/client";
import type { FundType, LedgerEntryType } from "@intellicash/shared";
import { AMOUNT_TOO_LARGE_MESSAGE, MAX_CENTS } from "../domain/money";
import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";
import { appendLedgerEntry, resolveFundAccount } from "../routes/groups";
import { closeCycleWithin, currentCycleSharesWhere, ensureActiveCycle } from "./cycle-service";

/**
 * A share-out that was done on a phone, sent to the online record.
 *
 * The phone works out who is owed what and the group counts the cash out at the
 * table: that has HAPPENED. So this records it, as it was, rather than working
 * it out again from the server's books (which is what the console's own
 * reviewed share-out does, from figures the server holds). Recomputing would
 * quietly change what members were actually paid whenever the two disagreed.
 *
 * What it refuses, and why:
 *  - a different cycle from the one the server has open. The console may have
 *    shared this cycle out already; recording the phone's payouts as well would
 *    pay the members twice.
 *  - a cycle whose share purchases the server does not hold in full. The
 *    payouts are worked out from them, so paying out against a partial record
 *    would leave the online books saying something the members were not told.
 *    A person may override this knowingly (`force`).
 *  - a loan fund that cannot cover it, or any figure that does not add up.
 *
 * Everything happens in ONE transaction: the loan settlements, the payouts, the
 * welfare split and the close of the cycle. It is all recorded or none of it is.
 */

const cents = (max = MAX_CENTS) => z.number().int().min(0).max(max, AMOUNT_TOO_LARGE_MESSAGE);

export const shareOutRecordSchema = z.object({
  /** The phone's own id for this share-out; what makes a retry recognisable. */
  shareOutId: z.string().trim().min(8).max(120),
  /** The cycle the phone believes it is ending. */
  cycleNumber: z.number().int().min(1),
  /** Record it even though the online share purchases differ from the phone's. */
  force: z.boolean().default(false),
  lines: z
    .array(
      z.object({
        memberId: z.string().min(1),
        shareCents: cents(),
        grossPayoutCents: cents(),
        welfarePayoutCents: cents(),
        loanOffsetCents: cents(),
        /** Negative when the member owes the group more than they are owed. */
        netPayoutCents: z.number().int().min(-MAX_CENTS).max(MAX_CENTS)
      })
    )
    .min(1)
    .max(500),
  notes: z.string().trim().max(300).optional()
});

export type ShareOutRecordInput = z.infer<typeof shareOutRecordSchema>;
type Line = ShareOutRecordInput["lines"][number];

const ledgerPlan: Record<
  "LOAN_REPAYMENT" | "SHARE_OUT_PAYOUT" | "WELFARE_SHARE_OUT",
  { fundType: FundType; direction: "CREDIT" | "DEBIT"; description: string }
> = {
  LOAN_REPAYMENT: { fundType: "INTERNAL_LOAN", direction: "CREDIT", description: "Loan settled from share-out" },
  SHARE_OUT_PAYOUT: { fundType: "INTERNAL_LOAN", direction: "DEBIT", description: "Share-out payout" },
  WELFARE_SHARE_OUT: { fundType: "SOCIAL", direction: "DEBIT", description: "Welfare fund shared out" }
};

function kes(centsValue: number) {
  return `KES ${(centsValue / 100).toLocaleString("en-KE", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/** net = gross + welfare - loan offset, for every line. A line that does not add up is refused. */
export function lineArithmeticProblems(lines: Line[]) {
  return lines.filter(
    (line) => line.grossPayoutCents + line.welfarePayoutCents - line.loanOffsetCents !== line.netPayoutCents
  );
}

export interface ShareDifference {
  memberId: string;
  phoneCents: number;
  onlineCents: number;
}

/**
 * Where the phone and the online record disagree about what each member put in
 * this cycle. Members with nothing on either side are not a difference.
 */
export function shareDifferences(phone: Map<string, number>, online: Map<string, number>): ShareDifference[] {
  const ids = new Set([...phone.keys(), ...online.keys()]);
  const differences: ShareDifference[] = [];
  for (const memberId of ids) {
    const phoneCents = phone.get(memberId) ?? 0;
    const onlineCents = online.get(memberId) ?? 0;
    if (phoneCents !== onlineCents) differences.push({ memberId, phoneCents, onlineCents });
  }
  return differences;
}

/**
 * The share purchases the console's own share-out would count for this cycle
 * (`currentCycleSharesWhere`). Deliberately the same definition, so the two
 * never disagree about which cycle a share belongs to. "Since the last payout"
 * alone counted the shares of a cycle closed WITHOUT a payout too, and refused
 * every phone share-out after one as out of step.
 */
async function onlineCycleShares(tx: Prisma.TransactionClient, groupId: string) {
  const rows = await tx.ledgerEntry.groupBy({
    by: ["memberId"],
    where: await currentCycleSharesWhere(tx, groupId),
    _sum: { amountCents: true }
  });
  const shares = new Map<string, number>();
  for (const row of rows) {
    if (row.memberId && (row._sum.amountCents ?? 0) > 0) shares.set(row.memberId, row._sum.amountCents ?? 0);
  }
  return shares;
}

export interface RecordedShareOut {
  /** True when this share-out had already been recorded and nothing was written now. */
  replayed: boolean;
  closed: { id: string; number: number };
  opened: { id: string; number: number } | null;
  entries: number;
  totalNetPaidCents: number;
  membersOwing: { memberId: string; owedCents: number }[];
}

export async function recordPhoneShareOut(
  actorUserId: string | null,
  groupId: string,
  input: ShareOutRecordInput
): Promise<RecordedShareOut> {
  return prisma.$transaction(
    async (tx) => {
      // A retry after a lost reply meets its own earlier write.
      const already = await tx.cycle.findFirst({
        where: { groupId, closedByShareOutId: input.shareOutId },
        select: { id: true, number: true }
      });
      if (already) {
        const next = await tx.cycle.findUnique({
          where: { groupId_number: { groupId, number: already.number + 1 } },
          select: { id: true, number: true }
        });
        const recorded = await tx.ledgerEntry.count({
          where: { groupId, clientRequestId: { startsWith: entryPrefix(input.shareOutId) } }
        });
        return {
          replayed: true,
          closed: { id: already.id, number: already.number },
          opened: next,
          entries: recorded,
          totalNetPaidCents: totalNetPaid(input.lines),
          membersOwing: owing(input.lines)
        };
      }

      const active = await ensureActiveCycle(tx, groupId);
      // Two different situations, told apart by code because the phone reacts to
      // them differently: a cycle the server has already closed is settled
      // ELSEWHERE (nothing more to send, and sending would pay twice), while a
      // cycle the server has not reached is merely early - an earlier share-out
      // has not arrived yet.
      if (active.number > input.cycleNumber) {
        throw new ApiHttpError(
          409,
          "SHARE_OUT_CYCLE_CLOSED",
          `The online record is already on Cycle ${active.number}, so Cycle ${input.cycleNumber} was closed there. To avoid paying members twice, this share-out was not recorded online.`,
          { serverCycleNumber: active.number, phoneCycleNumber: input.cycleNumber }
        );
      }
      if (active.number < input.cycleNumber) {
        throw new ApiHttpError(
          409,
          "SHARE_OUT_CYCLE_AHEAD",
          `The online record is still on Cycle ${active.number}, but this phone shared out Cycle ${input.cycleNumber}. Send the earlier cycle first.`,
          { serverCycleNumber: active.number, phoneCycleNumber: input.cycleNumber }
        );
      }

      const problems = lineArithmeticProblems(input.lines);
      if (problems.length > 0) {
        throw new ApiHttpError(
          400,
          "SHARE_OUT_ARITHMETIC",
          "A payout in this share-out does not add up (payout + welfare - loan settled must equal what is paid). Nothing was recorded."
        );
      }

      const memberIds = input.lines.map((line) => line.memberId);
      if (new Set(memberIds).size !== memberIds.length) {
        throw new ApiHttpError(400, "SHARE_OUT_DUPLICATE_MEMBER", "A member appears twice in this share-out. Nothing was recorded.");
      }
      const members = await tx.member.findMany({
        where: { groupId, id: { in: memberIds } },
        select: { id: true, fullName: true }
      });
      const names = new Map(members.map((member) => [member.id, member.fullName]));
      if (names.size !== memberIds.length) {
        throw new ApiHttpError(
          404,
          "MEMBER_NOT_FOUND",
          "A member in this share-out is not in the group online. Send the group's members first."
        );
      }

      if (!input.force) {
        const phoneShares = new Map(
          input.lines.filter((line) => line.shareCents > 0).map((line) => [line.memberId, line.shareCents])
        );
        const differences = shareDifferences(phoneShares, await onlineCycleShares(tx, groupId));
        if (differences.length > 0) {
          const named = await tx.member.findMany({
            where: { id: { in: differences.map((difference) => difference.memberId) } },
            select: { id: true, fullName: true }
          });
          const nameOf = new Map(named.map((member) => [member.id, member.fullName]));
          const shown = differences
            .slice(0, 3)
            .map(
              (difference) =>
                `${nameOf.get(difference.memberId) ?? "A member"}: phone ${kes(difference.phoneCents)}, online ${kes(difference.onlineCents)}`
            )
            .join("; ");
          throw new ApiHttpError(
            409,
            "SHARE_OUT_OUT_OF_STEP",
            `The online record does not hold the same share purchases as this phone for Cycle ${input.cycleNumber} (${shown}${
              differences.length > 3 ? `; and ${differences.length - 3} more` : ""
            }). Send all of the cycle's meetings first, or record it anyway if the difference is understood.`,
            { differences: differences.length }
          );
        }
      }

      // The anchor for the entries: the latest meeting of the cycle, so they
      // read on the console as the close of the meeting they followed. Optional.
      const anchor = await tx.meeting.findFirst({
        where: { groupId, cycleId: active.id },
        orderBy: { scheduledAt: "desc" },
        select: { id: true }
      });

      const prefix = entryPrefix(input.shareOutId);
      let written = 0;
      const post = async (
        type: keyof typeof ledgerPlan,
        line: Line,
        amountCents: number,
        key: string
      ) => {
        if (amountCents <= 0) return;
        const plan = ledgerPlan[type];
        const fund = await resolveFundAccount(tx, groupId, plan.fundType);
        await appendLedgerEntry(tx, {
          groupId,
          memberId: line.memberId,
          meetingId: anchor?.id ?? null,
          fundAccountId: fund.id,
          type: type as LedgerEntryType,
          amountCents,
          direction: plan.direction,
          description: input.notes ? `${plan.description} - ${input.notes}` : plan.description,
          clientRequestId: `${prefix}${key}-${line.memberId}`
        });
        written++;
      };

      try {
        // Money coming in first (loans settled out of the payouts), then money
        // going out, so the loan fund never has to be overdrawn part-way.
        for (const line of input.lines) await post("LOAN_REPAYMENT", line, line.loanOffsetCents, "settle");
        for (const line of input.lines) await post("SHARE_OUT_PAYOUT", line, line.grossPayoutCents, "payout");
        for (const line of input.lines) await post("WELFARE_SHARE_OUT", line, line.welfarePayoutCents, "welfare");
      } catch (error) {
        if (error instanceof ApiHttpError && error.code === "INSUFFICIENT_FUND_BALANCE") {
          throw new ApiHttpError(
            409,
            "SHARE_OUT_FUND_SHORT",
            "The online fund holds less than this share-out pays out, so nothing was recorded. Some of the cycle's records probably have not reached the online record yet: send the phone's meetings first, then try again.",
            error.details
          );
        }
        throw error;
      }

      const closed = await closeCycleWithin(tx, groupId, {
        closedByUserId: actorUserId,
        closedByShareOutId: input.shareOutId,
        notes: `Closed by a share-out done on the group's phone.${input.notes ? ` ${input.notes}` : ""}`
      });

      return {
        replayed: false,
        closed: closed.closed,
        opened: closed.opened,
        entries: written,
        totalNetPaidCents: totalNetPaid(input.lines),
        membersOwing: owing(input.lines)
      };
    },
    { timeout: 30_000, maxWait: 10_000 }
  );
}

/** Ledger entries carry `so-<share-out id>-<kind>-<member>` as their idempotency key. */
function entryPrefix(shareOutId: string) {
  return `so-${shareOutId}-`;
}

function totalNetPaid(lines: Line[]) {
  return lines.reduce((sum, line) => sum + Math.max(0, line.netPayoutCents), 0);
}

function owing(lines: Line[]) {
  return lines
    .filter((line) => line.netPayoutCents < 0)
    .map((line) => ({ memberId: line.memberId, owedCents: Math.abs(line.netPayoutCents) }));
}
