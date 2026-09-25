import fs from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";
import { memberLoanPosition } from "../src/domain/loan-math";

/**
 * The phone and the server must owe the same member the same money.
 *
 * These cases are worked by hand and shared with the phone
 * (intellicash_mobile/test/fixtures/loan-accrual-cases.json, a copy of this
 * file). The phone's tests run the same cases through its own implementation,
 * so a rule changed on one side and not the other fails a test on that side.
 */
type Fixture = {
  cases: Array<{
    name: string;
    loan: { principalCents: number; interestRateBps: number; termMonths: number; interestType: string };
    repayments: Array<{ day: number; cents: number }>;
    asOfDay: number;
    expect: { interestCents: number; repaidCents: number; outstandingCents: number; overpaidCents: number; settled: boolean };
  }>;
};

const fixture = JSON.parse(
  fs.readFileSync(path.resolve(__dirname, "../../../qa/fixtures/loan-accrual-cases.json"), "utf8")
) as Fixture;

const DAY = 24 * 60 * 60 * 1000;
const base = Date.UTC(2026, 0, 1);
const day = (n: number) => new Date(base + n * DAY);

describe("loan accrual - shared phone/server cases", () => {
  it("has the cases", () => {
    expect(fixture.cases.length).toBeGreaterThanOrEqual(12);
  });

  it.each(fixture.cases.map((entry) => [entry.name, entry] as const))("%s", (_name, entry) => {
    const position = memberLoanPosition(
      [{ id: "loan", ...entry.loan, disbursedAt: day(0) }],
      entry.repayments.map((repayment, index) => ({
        id: `r${index}`,
        at: day(repayment.day),
        amountCents: repayment.cents
      })),
      day(entry.asOfDay)
    );
    const loan = position.loans[0]!;
    expect({
      interestCents: loan.interestCents,
      repaidCents: loan.repaidCents,
      outstandingCents: loan.outstandingCents,
      overpaidCents: loan.overpaidCents,
      settled: loan.settled
    }).toEqual(entry.expect);
  });
});
