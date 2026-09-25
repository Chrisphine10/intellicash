/**
 * Where every "what does this member owe?" question is answered.
 *
 * The passbook, the SMS a member is texted after a meeting, the share-out that
 * nets debts against payouts, and the partner report all come through here:
 * the loans and the member's repayments are loaded once and handed to the pure
 * `memberLoanPosition` arithmetic, so they can never disagree.
 */

import type { Loan, Prisma, PrismaClient } from "@prisma/client";
import { memberLoanPosition, type MemberLoanPosition } from "../domain/loan-math";

/**
 * Where every "what does this member owe?" question is answered.
 *
 * The passbook, the SMS a member is texted after a meeting, the share-out that
 * nets debts against payouts, and the partner report all used to work a loan
 * out on its own and add the results up. Each did it slightly differently, so
 * a member could be told three different balances on the same day. They all
 * come through here now: the loans and the member's repayments are loaded once
 * and handed to the pure `memberLoanPosition`, which replays them in order.
 */
type Db = Prisma.TransactionClient | PrismaClient;

export interface LoanPositionScope {
  memberIds?: string[];
  groupIds?: string[];
}

/**
 * Positions keyed by member id. A member with no loans is absent from the map.
 * An empty scope returns nothing rather than every loan in the database.
 */
export async function loadLoanPositions(
  db: Db,
  scope: LoanPositionScope,
  asOf: Date
): Promise<Map<string, MemberLoanPosition<Loan>>> {
  const positions = new Map<string, MemberLoanPosition<Loan>>();
  const { memberIds, groupIds } = scope;
  if (!memberIds && !groupIds) return positions;
  if ((memberIds && memberIds.length === 0) || (groupIds && groupIds.length === 0)) return positions;

  const loans = await db.loan.findMany({
    where: {
      ...(memberIds ? { memberId: { in: memberIds } } : {}),
      ...(groupIds ? { groupId: { in: groupIds } } : {})
    }
  });
  if (loans.length === 0) return positions;

  const loansByMember = new Map<string, Loan[]>();
  for (const loan of loans) {
    const list = loansByMember.get(loan.memberId) ?? [];
    list.push(loan);
    loansByMember.set(loan.memberId, list);
  }

  // Every repayment the member made — not only the ones the ledger has pointed
  // at a loan. Which loan a row points at is a back-link for display; the
  // replay decides where the money actually went.
  const rows = await db.ledgerEntry.findMany({
    where: { type: "LOAN_REPAYMENT", memberId: { in: [...loansByMember.keys()] } },
    select: { id: true, memberId: true, amountCents: true, createdAt: true, meeting: { select: { closedAt: true } } },
    orderBy: [{ createdAt: "asc" }, { id: "asc" }]
  });
  const repaymentsByMember = new Map<string, Array<{ id: string; at: Date; amountCents: number }>>();
  for (const row of rows) {
    if (!row.memberId) continue;
    const list = repaymentsByMember.get(row.memberId) ?? [];
    list.push({ id: row.id, at: occurredAt(row.createdAt, row.meeting?.closedAt), amountCents: row.amountCents });
    repaymentsByMember.set(row.memberId, list);
  }

  // A loan given out in a meeting held offline reaches the server when the
  // phone next has signal; its row is dated then. The meeting's own close time
  // (reported by the phone) is when the money actually moved.
  const disbursementIds = loans.map((loan) => loan.disbursementEntryId).filter((id): id is string => Boolean(id));
  if (disbursementIds.length > 0) {
    const disbursements = await db.ledgerEntry.findMany({
      where: { id: { in: disbursementIds } },
      select: { id: true, meeting: { select: { closedAt: true } } }
    });
    const closedAtById = new Map(disbursements.map((row) => [row.id, row.meeting?.closedAt ?? null]));
    for (const loan of loans) {
      if (!loan.disbursementEntryId) continue;
      loan.disbursedAt = occurredAt(loan.disbursedAt, closedAtById.get(loan.disbursementEntryId));
    }
  }

  for (const [memberId, memberLoans] of loansByMember) {
    positions.set(memberId, memberLoanPosition(memberLoans, repaymentsByMember.get(memberId) ?? [], asOf));
  }
  return positions;
}

/**
 * When money recorded in a meeting actually moved: the row's own time, or the
 * meeting's close time if that is earlier (a meeting held offline and synced
 * later). Month-by-month interest depends on it; the sync time would charge a
 * member for days the money was already back in the box.
 */
export function occurredAt(recordedAt: Date, meetingClosedAt: Date | null | undefined): Date {
  if (!meetingClosedAt) return recordedAt;
  return meetingClosedAt.getTime() < recordedAt.getTime() ? meetingClosedAt : recordedAt;
}
