/**
 * Share-out arithmetic. Pure, integer cents.
 */

/**
 * `floor(poolCents x partCents / totalCents)`, exact.
 *
 * The product of a pool and a member's shares does not fit in a JavaScript
 * number once both run into the hundreds of millions of cents (a 1.5m pool and
 * a 1m member already reach 1.5e16, past the 9e15 the language can hold
 * exactly), so the multiplication and division are done in BigInt. A floating
 * point answer could land a cent either side of the true one on the boundary,
 * and a group counting cash at the table would find a cent nobody could
 * explain.
 */
export function proRataShareCents(poolCents: number, partCents: number, totalCents: number): number {
  if (poolCents <= 0 || partCents <= 0 || totalCents <= 0) return 0;
  return Number((BigInt(poolCents) * BigInt(partCents)) / BigInt(totalCents));
}
