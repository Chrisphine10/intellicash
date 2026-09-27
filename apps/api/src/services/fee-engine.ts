/**
 * The fee engine. Pure: no database, no clock, no network.
 *
 * The member names what the GROUP should receive. Everything else is added on
 * top of that figure, never taken out of it:
 *
 *   total = groupAmount + platformFee + providerFee
 *
 * - The platform fee (IWL's) is banded on the group amount.
 * - The provider fee is a gateway's cost: `fixedCents + percentBps` of the
 *   TOTAL charged. Because the percentage applies to a total that includes
 *   the fee itself, the total is grossed up:
 *
 *     total = ceil((groupAmount + platformFee + fixed) / (1 - pct))
 *
 *   The provider band is chosen on the total, and the total depends on the
 *   band, so the calculation repeats until the band stops changing.
 * - M-Pesa charges whole shillings, so an M-Pesa total rounds UP to the next
 *   shilling. The rounding difference is booked as provider fee: the group
 *   never receives a cent more or less than it was promised.
 *
 * No active rules means no fees: exactly how payments behaved before fees.
 */

export type FeeRuleKind = "PLATFORM" | "PROVIDER";

export interface FeeRuleInput {
  id: string;
  kind: FeeRuleKind | string;
  provider?: string | null;
  minCents: number;
  maxCents?: number | null;
  fixedCents: number;
  percentBps: number;
  version: number;
}

export interface FeeQuoteInput {
  groupAmountCents: number;
  provider: string;
  rules: FeeRuleInput[];
}

export interface FeeSnapshot {
  platformRule: { id: string; version: number; fixedCents: number; percentBps: number } | null;
  providerRule: { id: string; version: number; fixedCents: number; percentBps: number } | null;
  wholeShillings: boolean;
  passes: number;
}

export interface FeeQuote {
  groupAmountCents: number;
  platformFeeCents: number;
  providerFeeCents: number;
  totalCents: number;
  snapshot: FeeSnapshot;
}

const MAX_PASSES = 5;
/** A percentage of 100% or more can never be grossed up. */
const MAX_PERCENT_BPS = 5000;

/** Providers that can only charge whole shillings. */
export function chargesWholeShillings(provider: string) {
  return provider === "MPESA_DARAJA";
}

function inBand(rule: FeeRuleInput, amountCents: number) {
  return amountCents >= rule.minCents && (rule.maxCents == null || amountCents <= rule.maxCents);
}

function ruleSummary(rule: FeeRuleInput | null) {
  return rule
    ? { id: rule.id, version: rule.version, fixedCents: rule.fixedCents, percentBps: rule.percentBps }
    : null;
}

/**
 * The rule that applies to an amount. When bands overlap (a configuration
 * slip), a provider-specific rule beats an any-provider rule, then the
 * narrowest band wins — deterministic, so the same inputs always quote the
 * same fee.
 */
export function pickRule(
  rules: FeeRuleInput[],
  kind: FeeRuleKind,
  provider: string,
  amountCents: number
): FeeRuleInput | null {
  const candidates = rules.filter(
    (rule) =>
      rule.kind === kind &&
      (rule.provider == null || rule.provider === provider) &&
      inBand(rule, amountCents)
  );
  if (candidates.length === 0) return null;
  candidates.sort((a, b) => {
    const specific = Number(b.provider != null) - Number(a.provider != null);
    if (specific !== 0) return specific;
    const width = (rule: FeeRuleInput) => (rule.maxCents ?? Number.MAX_SAFE_INTEGER) - rule.minCents;
    const narrower = width(a) - width(b);
    if (narrower !== 0) return narrower;
    return a.id < b.id ? -1 : 1;
  });
  return candidates[0] ?? null;
}

/** A band's own fee on an amount: fixed plus a percentage, rounded up to a cent. */
function flatFee(rule: FeeRuleInput | null, amountCents: number) {
  if (!rule) return 0;
  return rule.fixedCents + Math.ceil((amountCents * rule.percentBps) / 10_000);
}

export class FeeConfigurationError extends Error {}

export function quoteFees(input: FeeQuoteInput): FeeQuote {
  const { groupAmountCents, provider, rules } = input;
  if (!Number.isInteger(groupAmountCents) || groupAmountCents <= 0) {
    throw new FeeConfigurationError("The amount for the group must be a positive whole number of cents.");
  }

  const platformRule = pickRule(rules, "PLATFORM", provider, groupAmountCents);
  const platformFeeCents = flatFee(platformRule, groupAmountCents);
  const net = groupAmountCents + platformFeeCents;
  const wholeShillings = chargesWholeShillings(provider);

  const grossUp = (rule: FeeRuleInput | null) => {
    let total = net;
    if (rule) {
      if (rule.percentBps < 0 || rule.percentBps >= MAX_PERCENT_BPS || rule.fixedCents < 0) {
        throw new FeeConfigurationError(`Provider fee rule ${rule.id} is not a usable percentage.`);
      }
      total = Math.ceil(((net + rule.fixedCents) * 10_000) / (10_000 - rule.percentBps));
    }
    if (wholeShillings) total = Math.ceil(total / 100) * 100;
    return total;
  };

  // Choose the band on the total; recompute until the band is stable.
  let providerRule = pickRule(rules, "PROVIDER", provider, grossUp(null));
  let totalCents = grossUp(providerRule);
  let passes = 1;
  while (passes < MAX_PASSES) {
    const next = pickRule(rules, "PROVIDER", provider, totalCents);
    if ((next?.id ?? null) === (providerRule?.id ?? null)) break;
    providerRule = next;
    totalCents = grossUp(providerRule);
    passes += 1;
  }

  const providerFeeCents = totalCents - net;
  return {
    groupAmountCents,
    platformFeeCents,
    providerFeeCents,
    totalCents,
    snapshot: {
      platformRule: ruleSummary(platformRule),
      providerRule: ruleSummary(providerRule),
      wholeShillings,
      passes
    }
  };
}

/** The invariant every stored payment must satisfy. */
export function feesBalance(payment: {
  amountCents: number;
  groupAmountCents: number;
  platformFeeCents: number;
  providerFeeCents: number;
}) {
  return (
    payment.amountCents ===
    payment.groupAmountCents + payment.platformFeeCents + payment.providerFeeCents
  );
}

/** Default platform bands (seeded INACTIVE; an admin switches them on). */
export const defaultPlatformBands: Array<Pick<FeeRuleInput, "minCents" | "maxCents" | "fixedCents" | "percentBps">> = [
  { minCents: 100, maxCents: 10_000, fixedCents: 100, percentBps: 0 },
  { minCents: 10_001, maxCents: 100_000, fixedCents: 500, percentBps: 0 },
  { minCents: 100_001, maxCents: null, fixedCents: 2_000, percentBps: 0 }
];
