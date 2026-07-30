/**
 * Loan arithmetic. Pure, integer cents, no database, no clock of its own —
 * every function takes `asOf` so results are reproducible in a test and in a
 * report run months apart.
 *
 * INTEREST MODEL (decided 30 Jul 2026): FLAT MONTHLY ON THE ORIGINAL PRINCIPAL.
 *
 *   interest = principal x (rateBps / 10_000) x elapsedMonths
 *
 * It does NOT reduce as the member repays. A member who borrows 10,000 at 10%
 * a month owes 1,000 in interest for month one whether they have repaid 9,000
 * of it or nothing — which is how most VSLA constitutions actually work, and
 * is simple enough to verify by hand in a meeting. A reducing-balance model
 * would give a different, smaller number; do not mix the two.
 */

export const MONTH_MS = 30 * 24 * 60 * 60 * 1000;

/**
 * Whole elapsed months, never negative.
 *
 * Deliberately floor(), not round(): a loan 29 days old has completed no month,
 * so charging a month's interest would overcharge the member. Interest starts
 * accruing at the end of each month, not partway through.
 */
export function elapsedMonths(disbursedAt: Date, asOf: Date): number {
  const ms = asOf.getTime() - disbursedAt.getTime();
  if (ms <= 0) return 0;
  return Math.floor(ms / MONTH_MS);
}

/**
 * Months interest is charged for.
 *
 * Capped at the agreed term. A group that lends for one month has agreed one
 * month's interest; letting it run indefinitely would turn a late repayment
 * into an unbounded debt, which no constitution here permits. Penalties for
 * lateness are FINES, a separate ledger type, decided by the group.
 */
export function chargeableMonths(
  disbursedAt: Date,
  termMonths: number,
  asOf: Date
): number {
  return Math.min(Math.max(0, termMonths), elapsedMonths(disbursedAt, asOf));
}

export function accruedInterestCents(input: {
  principalCents: number;
  interestRateBps: number;
  termMonths: number;
  disbursedAt: Date;
  asOf: Date;
}): number {
  const months = chargeableMonths(input.disbursedAt, input.termMonths, input.asOf);
  if (months === 0 || input.interestRateBps <= 0) return 0;

  // Integer cents throughout. Round once at the end rather than per month, so
  // twelve monthly roundings cannot drift away from the annual figure.
  return Math.round((input.principalCents * input.interestRateBps * months) / 10_000);
}

export interface LoanBalance {
  principalCents: number;
  interestCents: number;
  repaidCents: number;
  /** principal + interest - repaid. Never negative. */
  outstandingCents: number;
  /** Repaid beyond what was owed — refundable, not a negative balance. */
  overpaidCents: number;
  settled: boolean;
}

/**
 * The whole point of the projection: a balance derived from the ledger every
 * time it is asked for, so it cannot drift from the accounting record.
 */
export function loanBalance(input: {
  principalCents: number;
  interestRateBps: number;
  termMonths: number;
  disbursedAt: Date;
  repaidCents: number;
  asOf: Date;
}): LoanBalance {
  const interestCents = accruedInterestCents(input);
  const owed = input.principalCents + interestCents;
  const net = owed - input.repaidCents;

  return {
    principalCents: input.principalCents,
    interestCents,
    repaidCents: input.repaidCents,
    // Clamped: an overpayment is surfaced separately rather than as a negative
    // debt, which would quietly net off against another loan in a total.
    outstandingCents: Math.max(0, net),
    overpaidCents: Math.max(0, -net),
    settled: net <= 0
  };
}

/**
 * Can this loan be disbursed from the fund? (requirement #2)
 *
 * Separate from the fund's own overdraw guard, which is a backstop that fires
 * only once money has already been committed. This answers the question a UI
 * needs BEFORE offering an approve button.
 */
export function canDisburse(input: {
  requestedCents: number;
  loanFundBalanceCents: number;
}): { allowed: boolean; shortfallCents: number } {
  const shortfall = input.requestedCents - input.loanFundBalanceCents;
  return {
    allowed: input.requestedCents > 0 && shortfall <= 0,
    shortfallCents: Math.max(0, shortfall)
  };
}

/**
 * Attribute repayments to loans, oldest first.
 *
 * FIFO is standard VSLA practice and is what a treasurer does on paper: the
 * oldest debt clears first. Used by the backfill and by any repayment that
 * arrives without a loan reference.
 */
export function allocateFifo(
  loans: { id: string; owedCents: number }[],
  repaidCents: number
): { loanId: string; appliedCents: number }[] {
  let remaining = repaidCents;
  const applied: { loanId: string; appliedCents: number }[] = [];

  for (const loan of loans) {
    if (remaining <= 0) break;
    const take = Math.min(remaining, loan.owedCents);
    if (take > 0) {
      applied.push({ loanId: loan.id, appliedCents: take });
      remaining -= take;
    }
  }

  return applied;
}
