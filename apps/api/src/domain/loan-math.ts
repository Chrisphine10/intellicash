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

export interface MemberLoanInput {
  id: string;
  principalCents: number;
  interestRateBps: number;
  termMonths: number;
  disbursedAt: Date;
}

/** One repayment by the member, whichever loan the ledger row happens to point at. */
export interface MemberRepayment {
  id?: string;
  at: Date;
  amountCents: number;
}

/** Which loan a slice of a repayment went to. */
export interface LoanAllocation {
  repaymentId: string | null;
  loanId: string;
  cents: number;
}

export interface MemberLoanPositionEntry<L extends MemberLoanInput> extends LoanBalance {
  id: string;
  loan: L;
  /**
   * The moment the loan was paid off, or null while it still owes something.
   * Interest is charged up to this moment and no further.
   */
  settledAt: Date | null;
}

export interface MemberLoanPosition<L extends MemberLoanInput> {
  /** Oldest first. `repaidCents` is what was APPLIED to that loan. */
  loans: MemberLoanPositionEntry<L>[];
  outstandingCents: number;
  /** Paid beyond everything owed at the time — refundable, not a debt. */
  overpaidCents: number;
  /** Every slice of every repayment, in the order it was applied. */
  allocations: LoanAllocation[];
}

/**
 * One member's loans, taken TOGETHER, with time respected.
 *
 * Two mistakes are easy to make here and both were made once:
 *
 * 1. Judging each loan on its own. A repayment row points at a single loan —
 *    the oldest that still owed something. When a repayment is bigger than that
 *    loan (paying two loans at once, or a share-out netting a whole debt) the
 *    surplus cannot be split across rows, because the ledger is append-only, so
 *    per-loan arithmetic clamped it at zero and the surplus vanished: a member
 *    who had paid 550.00 against 550.00 of debt was still shown owing 300.00
 *    on the newer loan, and would have been charged it again at the next
 *    share-out.
 *
 * 2. Charging interest up to today on a loan that was paid off long ago. Flat
 *    monthly interest is worked out from the date, so a loan settled in month
 *    two and looked at in month three came back owing a third month.
 *
 * So the member's repayments are replayed in the order they were made. Each one
 * clears the oldest loan that existed at that moment and still owed something,
 * at what that loan owed ON THAT DAY; the surplus rolls on to the next loan. A
 * loan that is covered is SETTLED on that day and its interest stops there.
 * Whatever no loan could take (nothing owed, or no loan yet) is an overpayment,
 * held against the newest loan that existed so per-loan figures stay honest. A
 * repayment never pays a loan that had not been taken yet.
 *
 * `asOf` also bounds the replay: repayments after it are ignored, so the same
 * function answers "what was owed at share-out" months later.
 */
export function memberLoanPosition<L extends MemberLoanInput>(
  loans: L[],
  repayments: MemberRepayment[],
  asOf: Date
): MemberLoanPosition<L> {
  const ordered = [...loans].sort(
    (a, b) => a.disbursedAt.getTime() - b.disbursedAt.getTime() || a.id.localeCompare(b.id)
  );
  const events = repayments
    .map((repayment, order) => ({ repayment, order }))
    .filter(({ repayment }) => repayment.amountCents > 0 && repayment.at.getTime() <= asOf.getTime())
    .sort((a, b) => a.repayment.at.getTime() - b.repayment.at.getTime() || a.order - b.order)
    .map(({ repayment }) => repayment);

  const state = new Map(
    ordered.map((loan) => [loan.id, { applied: 0, surplus: 0, settledAt: null as Date | null }])
  );
  const allocations: LoanAllocation[] = [];

  for (const repayment of events) {
    let remaining = repayment.amountCents;
    let newestExisting: L | undefined;

    for (const loan of ordered) {
      if (loan.disbursedAt.getTime() > repayment.at.getTime()) break;
      newestExisting = loan;
      const position = state.get(loan.id)!;
      if (position.settledAt || remaining <= 0) continue;

      const owed =
        loan.principalCents + accruedInterestCents({ ...loan, asOf: repayment.at }) - position.applied;
      if (owed <= 0) {
        position.settledAt = repayment.at;
        continue;
      }
      const take = Math.min(remaining, owed);
      position.applied += take;
      remaining -= take;
      allocations.push({ repaymentId: repayment.id ?? null, loanId: loan.id, cents: take });
      if (take === owed) position.settledAt = repayment.at;
    }

    if (remaining > 0 && newestExisting) state.get(newestExisting.id)!.surplus += remaining;
  }

  const entries = ordered.map((loan): MemberLoanPositionEntry<L> => {
    const position = state.get(loan.id)!;
    const balance = loanBalance({
      ...loan,
      repaidCents: position.applied,
      asOf: position.settledAt ?? asOf
    });
    return {
      ...balance,
      id: loan.id,
      loan,
      settledAt: position.settledAt,
      repaidCents: balance.repaidCents + position.surplus,
      overpaidCents: balance.overpaidCents + position.surplus
    };
  });

  return {
    loans: entries,
    outstandingCents: entries.reduce((sum, entry) => sum + entry.outstandingCents, 0),
    overpaidCents: entries.reduce((sum, entry) => sum + entry.overpaidCents, 0),
    allocations
  };
}

/**
 * Repayment rate across a set of loans, as a whole percent — or null when there
 * is nothing to measure.
 *
 * Of everything owed (principal + interest to date) on loans that have FALLEN
 * DUE or been settled, the share that has been paid. A loan still inside its
 * term is left out: it has not had the chance to be repaid, and counting it
 * would make every freshly lent shilling look like a default. Overpayments do
 * not count as extra collected.
 *
 * Null, not zero, when no loan qualifies: "0% repaid" and "nothing has fallen
 * due yet" are different statements and a partner reads them differently.
 */
export function repaymentRatePercent(
  loans: Array<
    Pick<LoanBalance, "principalCents" | "interestCents" | "outstandingCents" | "settled"> & { dueAt: Date }
  >,
  asOf: Date
): number | null {
  let owed = 0;
  let collected = 0;
  for (const loan of loans) {
    if (!loan.settled && loan.dueAt.getTime() > asOf.getTime()) continue;
    const total = loan.principalCents + loan.interestCents;
    owed += total;
    collected += total - loan.outstandingCents;
  }
  return owed === 0 ? null : Math.round((collected / owed) * 100);
}
