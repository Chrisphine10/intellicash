import { describe, expect, it } from "vitest";
import {
  MONTH_MS,
  accruedInterestCents,
  allocateFifo,
  canDisburse,
  chargeableMonths,
  elapsedMonths,
  loanBalance,
  memberLoanPosition,
  repaymentRatePercent
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

describe("a member's loans taken together", () => {
  const asOf = new Date("2026-09-19T00:00:00Z");
  const daysBefore = (days: number) => new Date(asOf.getTime() - days * 24 * 60 * 60 * 1000);
  const pay = (amountCents: number, daysAgo: number, id?: string) => ({ id, at: daysBefore(daysAgo), amountCents });
  const older = { id: "a", principalCents: 50000, interestRateBps: 1000, termMonths: 3, disbursedAt: daysBefore(200) };
  const newer = { id: "b", principalCents: 30000, interestRateBps: 1000, termMonths: 3, disbursedAt: daysBefore(10) };

  it("applies a repayment bigger than the oldest loan to the next one (the QA share-out case)", () => {
    // Older owes 500 + 3 months' interest (150) = 650.00; newer owes 300.00.
    // A share-out nets the whole 550.00 still owing by paying it in one row.
    const position = memberLoanPosition([older, newer], [pay(40000, 5), pay(55000, 0)], asOf);
    expect(position.outstandingCents).toBe(0);
    expect(position.overpaidCents).toBe(0);
    expect(position.loans.map((loan) => loan.settled)).toEqual([true, true]);
    expect(position.allocations.map((a) => [a.loanId, a.cents])).toEqual([
      ["a", 40000],
      ["a", 25000],
      ["b", 30000]
    ]);
  });

  it("pays the oldest loan first and leaves the newer one owing", () => {
    const position = memberLoanPosition([older, newer], [pay(40000, 5)], asOf);
    expect(position.loans.map((l) => [l.id, l.repaidCents, l.outstandingCents])).toEqual([
      ["a", 40000, 25000],
      ["b", 0, 30000]
    ]);
    expect(position.outstandingCents).toBe(55000);
  });

  it("holds a true overpayment on the newest loan and reports it once", () => {
    const position = memberLoanPosition([older, newer], [pay(100000, 0)], asOf);
    expect(position.outstandingCents).toBe(0);
    expect(position.overpaidCents).toBe(5000); // 1,000.00 paid against 950.00 owed
    expect(position.loans[1]!.overpaidCents).toBe(5000);
  });

  it("gives the same answer however the repayments were split up", () => {
    const oneRow = memberLoanPosition([older, newer], [pay(70000, 0)], asOf);
    const manyRows = memberLoanPosition([older, newer], [pay(10000, 4), pay(30000, 3), pay(30000, 1)], asOf);
    expect(manyRows.outstandingCents).toBe(oneRow.outstandingCents);
    expect(oneRow.outstandingCents).toBe(25000);
  });

  it("does not depend on the order the rows arrive in", () => {
    const forward = memberLoanPosition([older, newer], [pay(40000, 5), pay(55000, 0)], asOf);
    const shuffled = memberLoanPosition([newer, older], [pay(55000, 0), pay(40000, 5)], asOf);
    expect(shuffled.loans.map((l) => [l.id, l.repaidCents, l.outstandingCents])).toEqual(
      forward.loans.map((l) => [l.id, l.repaidCents, l.outstandingCents])
    );
  });

  it("is empty for a member with no loans", () => {
    expect(memberLoanPosition([], [pay(1000, 0)], asOf)).toEqual({
      loans: [],
      outstandingCents: 0,
      overpaidCents: 0,
      allocations: []
    });
  });

  it("never changes what an unsettled loan owes: interest ignores repayments", () => {
    const none = memberLoanPosition([older], [], asOf);
    const some = memberLoanPosition([older], [pay(30000, 0)], asOf);
    expect(none.loans[0]!.interestCents).toBe(some.loans[0]!.interestCents);
  });

  describe("a settled loan stops accruing", () => {
    // Borrowed 1,000.00 at 10% a month for 3 months, 95 days ago. Thirty days
    // ago it was 65 days old — two whole months — and owed 1,200.00.
    const loan = { id: "s", principalCents: 100000, interestRateBps: 1000, termMonths: 3, disbursedAt: daysBefore(95) };

    it("stays settled a month after it was paid off", () => {
      const position = memberLoanPosition([loan], [pay(120000, 30)], asOf);
      expect(position.outstandingCents).toBe(0);
      expect(position.loans[0]!.interestCents).toBe(20000); // two months, not three
      expect(position.loans[0]!.settled).toBe(true);
      expect(position.loans[0]!.settledAt).toEqual(daysBefore(30));
    });

    it("but one cent short is still owing, and the third month is charged", () => {
      const position = memberLoanPosition([loan], [pay(119999, 30)], asOf);
      expect(position.loans[0]!.settled).toBe(false);
      expect(position.loans[0]!.settledAt).toBeNull();
      expect(position.loans[0]!.interestCents).toBe(30000);
      expect(position.outstandingCents).toBe(10001);
    });

    it("one cent over the amount owed leaves a one-cent overpayment", () => {
      const position = memberLoanPosition([loan], [pay(120001, 30)], asOf);
      expect(position.outstandingCents).toBe(0);
      expect(position.overpaidCents).toBe(1);
    });

    it("settles an interest-free loan at its principal", () => {
      const free = { ...loan, interestRateBps: 0 };
      const position = memberLoanPosition([free], [pay(100000, 30)], asOf);
      expect(position.loans[0]!.settled).toBe(true);
      expect(position.outstandingCents).toBe(0);
    });
  });

  describe("time is respected", () => {
    it("does not let a repayment pay a loan that had not been taken yet", () => {
      // 1,000.00 paid 20 days ago, ten days BEFORE the newer loan existed. The
      // older loan owed 650.00, so 350.00 is an overpayment on it — not credit
      // against a loan nobody had borrowed.
      const position = memberLoanPosition([older, newer], [pay(100000, 20)], asOf);
      expect(position.loans.map((l) => [l.id, l.outstandingCents])).toEqual([
        ["a", 0],
        ["b", 30000]
      ]);
      expect(position.loans[0]!.overpaidCents).toBe(35000);
      expect(position.outstandingCents).toBe(30000);
    });

    it("ignores repayments made after the date asked about", () => {
      const before = memberLoanPosition([older, newer], [pay(40000, 5), pay(55000, 0)], daysBefore(2));
      expect(before.outstandingCents).toBe(55000);
      expect(before.allocations).toHaveLength(1);
    });

    it("counts a repayment on the very day it is asked about", () => {
      const position = memberLoanPosition([older], [pay(65000, 0)], asOf);
      expect(position.outstandingCents).toBe(0);
    });
  });
});

describe("repayment rate across loans", () => {
  const asOf = new Date("2026-09-19T00:00:00Z");
  const past = new Date("2026-08-01T00:00:00Z");
  const future = new Date("2026-12-01T00:00:00Z");
  const loan = (over: Partial<Parameters<typeof repaymentRatePercent>[0][number]>) => ({
    principalCents: 100000,
    interestCents: 0,
    outstandingCents: 0,
    settled: false,
    dueAt: past,
    ...over
  });

  it("is null, not zero, when nothing has fallen due", () => {
    expect(repaymentRatePercent([], asOf)).toBeNull();
    expect(repaymentRatePercent([loan({ dueAt: future, outstandingCents: 100000 })], asOf)).toBeNull();
  });

  it("is the share of what has fallen due that has been paid", () => {
    // 1,000.00 + 200.00 owed, 300.00 still outstanding: 900 of 1,200 = 75%.
    expect(repaymentRatePercent([loan({ interestCents: 20000, outstandingCents: 30000 })], asOf)).toBe(75);
  });

  it("pools loans by money, not by count", () => {
    const rate = repaymentRatePercent(
      [loan({ principalCents: 900000, outstandingCents: 0, settled: true }), loan({ principalCents: 100000, outstandingCents: 100000 })],
      asOf
    );
    expect(rate).toBe(90);
  });

  it("counts a settled loan even though its due date is still ahead", () => {
    expect(repaymentRatePercent([loan({ dueAt: future, settled: true })], asOf)).toBe(100);
  });

  it("leaves loans still inside their term out of it", () => {
    const rate = repaymentRatePercent(
      [loan({ outstandingCents: 50000 }), loan({ dueAt: future, outstandingCents: 100000 })],
      asOf
    );
    expect(rate).toBe(50);
  });
});
