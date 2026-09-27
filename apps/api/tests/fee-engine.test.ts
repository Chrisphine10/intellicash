import { describe, expect, it } from "vitest";
import { defaultPlatformBands, feesBalance, pickRule, quoteFees, type FeeRuleInput } from "../src/services/fee-engine";

const platform: FeeRuleInput[] = defaultPlatformBands.map((band, index) => ({
  id: `p${index}`,
  kind: "PLATFORM",
  provider: null,
  minCents: band.minCents,
  maxCents: band.maxCents ?? null,
  fixedCents: band.fixedCents,
  percentBps: band.percentBps,
  version: 1
}));

const paystack: FeeRuleInput = {
  id: "ps",
  kind: "PROVIDER",
  provider: "PAYSTACK",
  minCents: 0,
  maxCents: null,
  fixedCents: 0,
  percentBps: 150,
  version: 1
};

/**
 * The member names what the group receives; everything else is added on top.
 * These are the properties the ledger relies on.
 */
describe("fee engine", () => {
  it("adds nothing when no rules are active (behaviour before fees)", () => {
    const quote = quoteFees({ groupAmountCents: 50_000, provider: "MPESA_DARAJA", rules: [] });
    expect(quote).toMatchObject({ groupAmountCents: 50_000, platformFeeCents: 0, providerFeeCents: 0, totalCents: 50_000 });
  });

  it("applies the platform band on the group amount", () => {
    expect(quoteFees({ groupAmountCents: 10_000, provider: "PAYSTACK", rules: platform }).platformFeeCents).toBe(100);
    expect(quoteFees({ groupAmountCents: 50_000, provider: "PAYSTACK", rules: platform }).platformFeeCents).toBe(500);
    expect(quoteFees({ groupAmountCents: 100_001, provider: "PAYSTACK", rules: platform }).platformFeeCents).toBe(2_000);
  });

  it("grosses up a percentage fee so the group still receives exactly its amount", () => {
    const quote = quoteFees({ groupAmountCents: 50_000, provider: "PAYSTACK", rules: [...platform, paystack] });
    // KSh 500 + KSh 5 IWL = 50,500; grossed up at 1.5%: ceil(50,500 / 0.985) = ceil(51,269.04) = 51,270.
    expect(quote.totalCents).toBe(51_270);
    expect(quote.providerFeeCents).toBe(770);
    // What the provider keeps (1.5% of the total, rounded down) never eats into the group's share.
    const providerTakes = Math.floor((quote.totalCents * 150) / 10_000);
    expect(quote.totalCents - providerTakes).toBeGreaterThanOrEqual(50_000 + 500);
  });

  it("charges M-Pesa in whole shillings, rounding up into the provider fee", () => {
    const mpesa: FeeRuleInput = { ...paystack, id: "mp", provider: "MPESA_DARAJA", percentBps: 55 };
    const quote = quoteFees({ groupAmountCents: 50_000, provider: "MPESA_DARAJA", rules: [...platform, mpesa] });
    expect(quote.totalCents % 100).toBe(0);
    expect(quote.groupAmountCents).toBe(50_000);
    expect(quote.snapshot.wholeShillings).toBe(true);
  });

  it("always balances: total = group + platform fee + provider fee", () => {
    const tiered: FeeRuleInput[] = [
      { id: "t1", kind: "PROVIDER", provider: "MPESA_DARAJA", minCents: 0, maxCents: 10_000, fixedCents: 700, percentBps: 0, version: 1 },
      { id: "t2", kind: "PROVIDER", provider: "MPESA_DARAJA", minCents: 10_001, maxCents: 100_000, fixedCents: 1_300, percentBps: 0, version: 1 },
      { id: "t3", kind: "PROVIDER", provider: "MPESA_DARAJA", minCents: 100_001, maxCents: null, fixedCents: 2_300, percentBps: 100, version: 1 }
    ];
    for (const amount of [100, 9_300, 9_999, 10_000, 99_000, 250_000, 1_000_000]) {
      const quote = quoteFees({ groupAmountCents: amount, provider: "MPESA_DARAJA", rules: [...platform, ...tiered] });
      expect(
        feesBalance({
          amountCents: quote.totalCents,
          groupAmountCents: quote.groupAmountCents,
          platformFeeCents: quote.platformFeeCents,
          providerFeeCents: quote.providerFeeCents
        })
      ).toBe(true);
      expect(quote.groupAmountCents).toBe(amount);
      // The chosen provider band contains the total it was chosen for.
      const band = tiered.find((rule) => rule.id === quote.snapshot.providerRule?.id);
      if (band) {
        expect(quote.totalCents).toBeGreaterThanOrEqual(band.minCents);
        if (band.maxCents != null) expect(quote.totalCents).toBeLessThanOrEqual(band.maxCents + 100);
      }
    }
  });

  it("prefers a provider-specific rule, then the narrowest band", () => {
    const any: FeeRuleInput = { ...paystack, id: "any", provider: null, percentBps: 300 };
    expect(pickRule([any, paystack], "PROVIDER", "PAYSTACK", 10_000)?.id).toBe("ps");
    expect(pickRule([any, paystack], "PROVIDER", "MPESA_DARAJA", 10_000)?.id).toBe("any");
  });

  it("refuses a percentage that cannot be grossed up", () => {
    const broken: FeeRuleInput = { ...paystack, percentBps: 6_000 };
    expect(() => quoteFees({ groupAmountCents: 1_000, provider: "PAYSTACK", rules: [broken] })).toThrow();
  });
});
