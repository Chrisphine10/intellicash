import { describe, expect, it } from "vitest";
import {
  MONTH_MS,
  accruedInterestCents,
  allocateFifo,
  canDisburse,
  chargeableMonths,
  elapsedMonths,
  loanBalance
} from "../src/domain/loan-math";

const start = new Date("2026-01-01T00:00:00.000Z");
const after = (months: number) => new Date(start.getTime() + months * MONTH_MS);

describe("loan interest — flat monthly on the original principal", () => {
  it("charges nothing before a whole month has elapsed", () => {
    // 29 days is not a month. Charging for it would overcharge the member.
    expect(elapsedMonths(start, new Date(start.getTime() + 29 * 24 * 3600 * 1000))).toBe(0);
    expect(
      accruedInterestCents({
        principalCents: 1_000_000,
        interestRateBps: 1000,
        termMonths: 3,
        disbursedAt: start,
        asOf: new Date(start.getTime() + 29 * 24 * 3600 * 1000)
      })
    ).toBe(0);
  });

  it("charges 10% of principal per month, flat", () => {
    // 10,000.00 at 10%/month => 1,000.00 per month.
    const oneMonth = accruedInterestCents({
      principalCents: 1_000_000,
      interestRateBps: 1000,
      termMonths: 6,
      disbursedAt: start,
      asOf: after(1)
    });
    expect(oneMonth).toBe(100_000);

    // Three months is exactly 3x one month — flat, not compounding.
    expect(
      accruedInterestCents({
        principalCents: 1_000_000,
        interestRateBps: 1000,
        termMonths: 6,
        disbursedAt: start,
        asOf: after(3)
      })
    ).toBe(300_000);
  });

  it("does NOT reduce as the member repays", () => {
    // The distinguishing property of this model. Same interest whether the
    // member has repaid most of it or none.
    const common = {
      principalCents: 1_000_000,
      interestRateBps: 1000,
      termMonths: 6,
      disbursedAt: start,
      asOf: after(2)
    };
    const paidNothing = loanBalance({ ...common, repaidCents: 0 });
    const paidMost = loanBalance({ ...common, repaidCents: 900_000 });

    expect(paidNothing.interestCents).toBe(200_000);
    expect(paidMost.interestCents).toBe(200_000);
  });

  it("stops charging at the agreed term", () => {
    // A one-month loan left unpaid for a year owes ONE month of interest.
    // Lateness is punished with fines, which the group decides — not with
    // unbounded interest.
    expect(chargeableMonths(start, 1, after(12))).toBe(1);
    expect(
      accruedInterestCents({
        principalCents: 1_000_000,
        interestRateBps: 1000,
        termMonths: 1,
        disbursedAt: start,
        asOf: after(12)
      })
    ).toBe(100_000);
  });

  it("charges nothing on an interest-free loan", () => {
    expect(
      accruedInterestCents({
        principalCents: 500_000,
        interestRateBps: 0,
        termMonths: 3,
        disbursedAt: start,
        asOf: after(3)
      })
    ).toBe(0);
  });
});

describe("loan balance", () => {
  it("is principal plus interest minus repayments", () => {
    const balance = loanBalance({
      principalCents: 1_000_000,
      interestRateBps: 1000,
      termMonths: 3,
      disbursedAt: start,
      repaidCents: 400_000,
      asOf: after(1)
    });
    expect(balance.interestCents).toBe(100_000);
    expect(balance.outstandingCents).toBe(700_000); // 1,000,000 + 100,000 - 400,000
    expect(balance.settled).toBe(false);
  });

  it("reports an overpayment separately instead of a negative debt", () => {
    // A negative outstanding would silently net off another loan in a total.
    const balance = loanBalance({
      principalCents: 100_000,
      interestRateBps: 0,
      termMonths: 1,
      disbursedAt: start,
      repaidCents: 150_000,
      asOf: after(1)
    });
    expect(balance.outstandingCents).toBe(0);
    expect(balance.overpaidCents).toBe(50_000);
    expect(balance.settled).toBe(true);
  });
});

describe("loan fund validation (#2)", () => {
  it("refuses a loan larger than the fund and names the shortfall", () => {
    const result = canDisburse({ requestedCents: 500_000, loanFundBalanceCents: 300_000 });
    expect(result.allowed).toBe(false);
    expect(result.shortfallCents).toBe(200_000);
  });

  it("allows a loan exactly equal to the fund", () => {
    // The requirement is "loan <= available", so the boundary must pass.
    expect(canDisburse({ requestedCents: 300_000, loanFundBalanceCents: 300_000 })).toEqual({
      allowed: true,
      shortfallCents: 0
    });
  });

  it("refuses a zero or negative request", () => {
    expect(canDisburse({ requestedCents: 0, loanFundBalanceCents: 999 }).allowed).toBe(false);
  });
});

describe("FIFO repayment allocation", () => {
  it("clears the oldest debt first", () => {
    const applied = allocateFifo(
      [
        { id: "old", owedCents: 300_000 },
        { id: "new", owedCents: 500_000 }
      ],
      400_000
    );
    expect(applied).toEqual([
      { loanId: "old", appliedCents: 300_000 },
      { loanId: "new", appliedCents: 100_000 }
    ]);
  });

  it("never allocates more than is owed", () => {
    const applied = allocateFifo([{ id: "only", owedCents: 100_000 }], 250_000);
    expect(applied).toEqual([{ loanId: "only", appliedCents: 100_000 }]);
  });
});
