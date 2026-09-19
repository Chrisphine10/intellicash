/**
 * Limits on a single money figure.
 *
 * Amounts, fund balances and loan principals are stored as 32-bit integers of
 * cents, so the most one figure can hold is 2,147,483,647 cents — KES
 * 21,474,836.47. Beyond that the database refuses the write, and before this
 * guard existed the person saw "Something went wrong on our side" for an
 * amount they had typed, with no hint that the amount was the problem.
 */
export const MAX_CENTS = 2_147_483_647;
export const MAX_CENTS_LABEL = "KES 21,474,836.47";

export const AMOUNT_TOO_LARGE_MESSAGE = `That amount is too large to record. The most one figure can hold is ${MAX_CENTS_LABEL}.`;

/** The database's refusal to store an integer that does not fit its column. */
export function isIntegerOverflow(error: unknown): boolean {
  return error instanceof Error && /unable to fit integer value/i.test(error.message);
}
