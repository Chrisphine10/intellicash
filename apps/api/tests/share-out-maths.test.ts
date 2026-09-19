import { describe, expect, it } from "vitest";
import { proRataShareCents } from "../src/domain/share-out";

describe("pro-rata share of the pool", () => {
  it("is floor(pool x part / total) for ordinary sums", () => {
    expect(proRataShareCents(1_000_000, 500_000, 1_500_000)).toBe(333_333);
    expect(proRataShareCents(1_000_000, 1, 3)).toBe(333_333);
    expect(proRataShareCents(10, 1, 3)).toBe(3);
  });

  it("is exact where floating point is not", () => {
    // 2,000,000.00 pool x 999,999.99 of shares: the product is ~2e16, past
    // the 9e15 a double holds exactly.
    const pool = 200_000_000;
    const part = 99_999_999;
    const total = 300_000_001;
    const naive = Math.floor((pool * part) / total);
    const exact = Number((BigInt(pool) * BigInt(part)) / BigInt(total));
    expect(proRataShareCents(pool, part, total)).toBe(exact);
    // The two are allowed to differ — this documents that they can.
    expect(Math.abs(naive - exact)).toBeLessThanOrEqual(1);
  });

  it("gives the whole pool to a sole saver", () => {
    expect(proRataShareCents(2_147_483_647, 2_147_483_647, 2_147_483_647)).toBe(2_147_483_647);
  });

  it("never exceeds the pool", () => {
    expect(proRataShareCents(999, 3, 3)).toBe(999);
    expect(proRataShareCents(999, 2, 3)).toBe(666);
  });

  it("is zero for nothing to share, nothing bought, or no shares at all", () => {
    expect(proRataShareCents(0, 5, 10)).toBe(0);
    expect(proRataShareCents(100, 0, 10)).toBe(0);
    expect(proRataShareCents(100, 5, 0)).toBe(0);
  });
});
