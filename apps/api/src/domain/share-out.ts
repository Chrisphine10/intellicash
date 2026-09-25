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

/**
 * Split `poolCents` across members in proportion to `parts`, to the cent.
 *
 * Largest remainder: everyone gets the floor of their exact share, and the
 * cents left over go one each to the members whose exact share lost the most
 * in rounding (ties to the earlier member). The result always adds up to the
 * pool exactly, and no member's figure depends on where they sit in a list -
 * the older rule gave every leftover cent to whoever happened to be last.
 * The phone does the same (share_out_calculator.dart).
 */
export function allocateLargestRemainder(poolCents: number, parts: number[]): number[] {
  const total = parts.reduce((sum, part) => sum + Math.max(0, part), 0);
  if (poolCents <= 0 || total <= 0) return parts.map(() => 0);
  const pool = BigInt(poolCents);
  const whole = BigInt(total);
  const floors = parts.map((part) => (part > 0 ? Number((pool * BigInt(part)) / whole) : 0));
  const remainders = parts.map((part, index) => ({
    index,
    rest: part > 0 ? (pool * BigInt(part)) % whole : -1n
  }));
  let left = poolCents - floors.reduce((sum, value) => sum + value, 0);
  remainders.sort((a, b) => (a.rest === b.rest ? a.index - b.index : a.rest > b.rest ? -1 : 1));
  for (const { index, rest } of remainders) {
    if (left <= 0) break;
    if (rest < 0n) continue;
    floors[index] = (floors[index] ?? 0) + 1;
    left -= 1;
  }
  return floors;
}
