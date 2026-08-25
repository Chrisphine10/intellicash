/**
 * The MEAL contract: how field records become a defensible figure.
 *
 * Pure. No I/O, no Prisma, no clock of its own — every function takes what it
 * needs and returns a value, so a figure that reaches a partner report can be
 * reproduced from the same inputs a year later. Same split as
 * `credit-rating-contract.ts` and `visit-assessment-contract.ts`.
 *
 * ## Why this exists
 *
 * The platform already records activity well: visits with GPS, assessments with
 * every answer kept, mentorship sessions, action items. What it could not do is
 * answer "did any of it change anything" — the question a funder asks, and the
 * one a programme manager needs in order to move agents around.
 *
 * Turning records into that answer is where reporting usually goes wrong, so
 * these rules are enforced in code rather than left to whoever writes the query:
 *
 * 1. **Paired comparison.** Change is measured across units holding BOTH a
 *    baseline and a later reading. Comparing the mean of all groups this
 *    quarter against the mean of all groups last quarter measures the change in
 *    which groups are enrolled as much as any change in the groups themselves,
 *    and it moves most when a programme expands — precisely when somebody is
 *    hunting for good news. See `pairedChange`.
 *
 * 2. **The denominator travels with the number.** Every result carries how many
 *    units it covers and how many were eligible. "62% improved" is not a
 *    finding until it says 62% of what.
 *
 * 3. **Comparability is checked, not assumed.** Scores from two different
 *    scorecard versions are not the same measurement. Where the version moved
 *    underneath a group, that group is excluded from the change and counted in
 *    `excludedForComparability` rather than quietly averaged in.
 *
 * 4. **Median for money.** Enterprise revenue is skewed — one group with a
 *    milling machine drags a mean to a figure no group is actually at, which is
 *    then quoted as typical. Median leads; mean is available beside it.
 *
 * 5. **Small samples are labelled, not hidden.** Under `SMALL_SAMPLE_THRESHOLD`
 *    paired units a percentage is arithmetic rather than evidence, and says so.
 *    Suppressing it outright invites somebody to recompute it by hand without
 *    the caveat attached.
 *
 * 6. **Contribution, not attribution.** Nothing here claims the programme
 *    caused anything. There is no comparison group and no counterfactual, so
 *    these are changes observed among groups the programme worked with.
 *    `OBSERVED_CHANGE_CAVEAT` is part of the contract for that reason: a figure
 *    that ends up on a slide should carry its own limits with it.
 */

export const MEAL_CONTRACT_VERSION = "1.0.0";

/** Below this many paired units, a percentage is arithmetic, not evidence. */
export const SMALL_SAMPLE_THRESHOLD = 5;

export const OBSERVED_CHANGE_CAVEAT =
  "Observed change among groups the programme worked with. There is no comparison group, so this shows what changed, not what the programme caused.";

// ---------------------------------------------------------------------------
// Indicator definitions
// ---------------------------------------------------------------------------

/** Where a measurement sits on the results chain. */
export type ResultLevel = "ACTIVITY" | "OUTPUT" | "OUTCOME" | "DATA_QUALITY";

/** Which way is good. Needed before any figure can be coloured or ranked. */
export type Direction = "UP_IS_GOOD" | "DOWN_IS_GOOD" | "NEUTRAL";

export type IndicatorUnit = "COUNT" | "PERCENT" | "CENTS" | "SCORE" | "LADDER_STEP" | "DAYS";

export interface IndicatorDefinition {
  key: string;
  name: string;
  /** Precisely what is counted. Written to survive being read out of context. */
  definition: string;
  /** What the number is a share OF, in words. Empty for a plain count. */
  denominator: string;
  level: ResultLevel;
  unit: IndicatorUnit;
  direction: Direction;
}

/**
 * The indicator set.
 *
 * Keys are stable and are what a report joins on; names and wording may be
 * edited freely. Deliberately short — an indicator nobody acts on still costs
 * an agent time to collect, and a long list is how field staff end up filling
 * forms instead of coaching.
 */
export const MEAL_INDICATORS = {
  // -- Activity: what the programme did --------------------------------------
  "visits.completed": {
    key: "visits.completed",
    name: "Visits completed",
    definition: "Field visits submitted by an agent in the period.",
    denominator: "",
    level: "ACTIVITY",
    unit: "COUNT",
    direction: "UP_IS_GOOD"
  },
  "mentorship.sessions": {
    key: "mentorship.sessions",
    name: "Mentorship sessions delivered",
    definition: "Coaching recorded against a visit, counted once per topic per visit.",
    denominator: "",
    level: "ACTIVITY",
    unit: "COUNT",
    direction: "UP_IS_GOOD"
  },

  // -- Output: what that produced --------------------------------------------
  "groups.assessed": {
    key: "groups.assessed",
    name: "Groups assessed",
    definition: "Groups with at least one completed scorecard.",
    denominator: "groups in scope",
    level: "OUTPUT",
    unit: "PERCENT",
    direction: "UP_IS_GOOD"
  },
  "enterprises.profiled": {
    key: "enterprises.profiled",
    name: "Enterprises profiled",
    definition: "Group enterprises with a recorded business profile.",
    denominator: "",
    level: "OUTPUT",
    unit: "COUNT",
    direction: "UP_IS_GOOD"
  },
  "needs.raised": {
    key: "needs.raised",
    name: "Support needs recorded",
    definition: "Support needs named by groups against an enterprise.",
    denominator: "",
    level: "OUTPUT",
    unit: "COUNT",
    direction: "NEUTRAL"
  },

  // -- Outcome: what changed --------------------------------------------------
  "assessment.score": {
    key: "assessment.score",
    name: "Assessment score",
    definition:
      "Scorecard result as a percentage of applicable points, first reading against latest on the same group and the same scorecard version.",
    denominator: "groups with two or more comparable assessments",
    level: "OUTCOME",
    unit: "PERCENT",
    direction: "UP_IS_GOOD"
  },
  "assessment.improved": {
    key: "assessment.improved",
    name: "Groups whose score rose",
    definition: "Groups whose latest comparable score sits above their first.",
    denominator: "groups with two or more comparable assessments",
    level: "OUTCOME",
    unit: "PERCENT",
    direction: "UP_IS_GOOD"
  },
  "enterprise.revenue": {
    key: "enterprise.revenue",
    name: "Monthly enterprise revenue",
    definition: "Reported monthly revenue per enterprise, median, first reading against latest.",
    denominator: "enterprises with two or more readings",
    level: "OUTCOME",
    unit: "CENTS",
    direction: "UP_IS_GOOD"
  },
  "enterprise.margin": {
    key: "enterprise.margin",
    name: "Monthly enterprise margin",
    definition:
      "Reported monthly revenue less reported monthly costs, median. Computed, never stored, so it cannot disagree with the figures it comes from.",
    denominator: "enterprises reporting revenue and costs at both readings",
    level: "OUTCOME",
    unit: "CENTS",
    direction: "UP_IS_GOOD"
  },
  "enterprise.marketReach": {
    key: "enterprise.marketReach",
    name: "Market reach",
    definition:
      "How far the enterprise's output travels, on the ordered ladder from farm gate to export. A step up means the same business is selling into a wider market.",
    denominator: "enterprises with two or more readings",
    level: "OUTCOME",
    unit: "LADDER_STEP",
    direction: "UP_IS_GOOD"
  },
  "enterprise.buyers": {
    key: "enterprise.buyers",
    name: "Buyers per enterprise",
    definition:
      "Distinct buyers in the reporting month, median. Tracked because rising revenue against a single buyer is growth and concentration at the same time.",
    denominator: "enterprises with two or more readings",
    level: "OUTCOME",
    unit: "COUNT",
    direction: "UP_IS_GOOD"
  },
  "enterprise.formalAgreement": {
    key: "enterprise.formalAgreement",
    name: "Enterprises with a written buyer agreement",
    definition: "Enterprises reporting a written offtake agreement rather than an informal arrangement.",
    denominator: "enterprises asked",
    level: "OUTCOME",
    unit: "PERCENT",
    direction: "UP_IS_GOOD"
  },
  "needs.met": {
    key: "needs.met",
    name: "Support needs met",
    definition:
      "Recorded needs marked met. A measure of the PROGRAMME's responsiveness, not of the group's performance.",
    denominator: "support needs recorded",
    level: "OUTCOME",
    unit: "PERCENT",
    direction: "UP_IS_GOOD"
  },
  "needs.daysToMeet": {
    key: "needs.daysToMeet",
    name: "Days to meet a support need",
    definition: "Days between a need being raised and marked met, median.",
    denominator: "needs marked met",
    level: "OUTCOME",
    unit: "DAYS",
    direction: "DOWN_IS_GOOD"
  },
  "actions.closed": {
    key: "actions.closed",
    name: "Action items closed",
    definition: "Agreed actions marked done.",
    denominator: "action items raised",
    level: "OUTCOME",
    unit: "PERCENT",
    direction: "UP_IS_GOOD"
  },
  "mentorship.rating": {
    key: "mentorship.rating",
    name: "Mentorship rating from the group",
    definition:
      "Score out of 5 given by the group's representative. Ratings entered by the agent are excluded rather than averaged in — an agent scoring their own coaching tells you nothing.",
    denominator: "ratings given by a group representative",
    level: "OUTCOME",
    unit: "SCORE",
    direction: "UP_IS_GOOD"
  },

  // -- Data quality: how far the above can be trusted -------------------------
  "data.assessmentCoverage": {
    key: "data.assessmentCoverage",
    name: "Groups with a current assessment",
    definition: "Groups assessed within the freshness window.",
    denominator: "groups in scope",
    level: "DATA_QUALITY",
    unit: "PERCENT",
    direction: "UP_IS_GOOD"
  },
  "data.ratingProvenance": {
    key: "data.ratingProvenance",
    name: "Ratings given by the group",
    definition:
      "Mentorship ratings recorded as coming from the group representative rather than the agent. A low figure means the rating indicator is measuring agents' opinion of themselves.",
    denominator: "mentorship ratings recorded",
    level: "DATA_QUALITY",
    unit: "PERCENT",
    direction: "UP_IS_GOOD"
  }
} as const satisfies Record<string, IndicatorDefinition>;

export type IndicatorKey = keyof typeof MEAL_INDICATORS;

/** Every definition, ordered by results-chain level. For a methodology page. */
export function indicatorCatalogue(): IndicatorDefinition[] {
  const order: ResultLevel[] = ["ACTIVITY", "OUTPUT", "OUTCOME", "DATA_QUALITY"];
  return Object.values(MEAL_INDICATORS as Record<string, IndicatorDefinition>).sort(
    (a, b) => order.indexOf(a.level) - order.indexOf(b.level)
  );
}

// ---------------------------------------------------------------------------
// Market reach: an ordered ladder, not a label
// ---------------------------------------------------------------------------

/**
 * Ordered nearest to furthest. The ORDER is the point: it is what lets a move
 * from selling at the farm gate to selling into the county be measured as a
 * step rather than merely a changed string.
 *
 * Keys are stored; positions are derived here. Inserting a rung later shifts
 * every number, which is exactly why nothing persists the index.
 */
export const MARKET_REACH_LADDER = [
  { key: "WITHIN_GROUP", label: "Within the group" },
  { key: "VILLAGE", label: "Village" },
  { key: "WARD", label: "Ward" },
  { key: "SUB_COUNTY", label: "Sub-county" },
  { key: "COUNTY", label: "County" },
  { key: "REGIONAL", label: "Neighbouring counties" },
  { key: "NATIONAL", label: "National" },
  { key: "EXPORT", label: "Export" }
] as const;

export type MarketReachKey = (typeof MARKET_REACH_LADDER)[number]["key"];

/** Rung number, 1-based. Null for an absent or unrecognised value. */
export function marketReachStep(key: string | null | undefined): number | null {
  if (!key) return null;
  const index = MARKET_REACH_LADDER.findIndex((rung) => rung.key === key);
  return index === -1 ? null : index + 1;
}

export function marketReachLabel(key: string | null | undefined): string | null {
  return MARKET_REACH_LADDER.find((rung) => rung.key === key)?.label ?? null;
}

export function isMarketReachKey(value: string): value is MarketReachKey {
  return MARKET_REACH_LADDER.some((rung) => rung.key === value);
}

/** How an enterprise sells. Several at once is normal, not an error. */
export const MARKET_CHANNELS = [
  { key: "FARM_GATE", label: "At the farm gate" },
  { key: "LOCAL_MARKET", label: "Local market" },
  { key: "TRADER", label: "Trader or broker" },
  { key: "COOPERATIVE", label: "Cooperative or aggregator" },
  { key: "PROCESSOR", label: "Processor" },
  { key: "RETAILER", label: "Shop or retailer" },
  { key: "INSTITUTION", label: "School, hospital or institution" },
  { key: "ONLINE", label: "Online or phone orders" },
  { key: "EXPORT_AGENT", label: "Export agent" }
] as const;

export type MarketChannelKey = (typeof MARKET_CHANNELS)[number]["key"];

export function isMarketChannelKey(value: string): value is MarketChannelKey {
  return MARKET_CHANNELS.some((channel) => channel.key === value);
}

export function marketChannelLabel(key: string): string | null {
  return MARKET_CHANNELS.find((channel) => channel.key === key)?.label ?? null;
}

export const SUPPORT_NEED_CATEGORIES = [
  "FINANCE",
  "MARKET",
  "SKILLS",
  "INPUTS",
  "INFRASTRUCTURE",
  "GOVERNANCE",
  "TECHNOLOGY"
] as const;

export type SupportNeedCategory = (typeof SUPPORT_NEED_CATEGORIES)[number];

/**
 * The support-need vocabulary.
 *
 * Canonical here, and INSERTed by the migration that created the table, so a
 * production database has the rows the moment it deploys. Both copies must
 * agree — `support-need-taxonomy.test.ts` fails if they drift.
 *
 * Duplicated deliberately rather than generated: reference data that lives only
 * in a migration is absent from every environment built with `prisma db push`,
 * which is how CI builds its database and how the capture screen ended up with
 * an empty list there. Reference data that lives only in code is absent until
 * something boots. It needs both.
 */
export const SUPPORT_NEED_TYPES: ReadonlyArray<{
  key: string;
  title: string;
  category: SupportNeedCategory;
}> = [
  { key: "working-capital", title: "Working capital or stock finance", category: "FINANCE" },
  { key: "asset-finance", title: "Equipment or asset finance", category: "FINANCE" },
  { key: "insurance", title: "Insurance cover", category: "FINANCE" },
  { key: "buyer-linkage", title: "Linkage to a reliable buyer", category: "MARKET" },
  { key: "price-information", title: "Market price information", category: "MARKET" },
  { key: "aggregation", title: "Bulking and aggregation", category: "MARKET" },
  { key: "certification", title: "Certification or quality standards", category: "MARKET" },
  { key: "packaging-branding", title: "Packaging and branding", category: "MARKET" },
  { key: "business-training", title: "Business and enterprise training", category: "SKILLS" },
  { key: "record-keeping", title: "Record keeping", category: "SKILLS" },
  { key: "production-technique", title: "Production or agronomy technique", category: "SKILLS" },
  { key: "digital-skills", title: "Digital skills", category: "SKILLS" },
  { key: "seed-stock", title: "Seed, stock or breeding material", category: "INPUTS" },
  { key: "feed-fertiliser", title: "Feed or fertiliser", category: "INPUTS" },
  { key: "tools-equipment", title: "Tools and equipment", category: "INPUTS" },
  { key: "storage", title: "Storage", category: "INFRASTRUCTURE" },
  { key: "cold-chain", title: "Cold chain", category: "INFRASTRUCTURE" },
  { key: "water", title: "Water access", category: "INFRASTRUCTURE" },
  { key: "power", title: "Power access", category: "INFRASTRUCTURE" },
  { key: "transport", title: "Transport to market", category: "INFRASTRUCTURE" },
  { key: "registration", title: "Registration or licensing", category: "GOVERNANCE" },
  { key: "constitution", title: "Constitution and by-laws", category: "GOVERNANCE" },
  { key: "leadership", title: "Leadership and governance", category: "GOVERNANCE" },
  { key: "digital-records", title: "Digital record keeping", category: "TECHNOLOGY" },
  { key: "mobile-money", title: "Mobile money and digital payments", category: "TECHNOLOGY" }
];

export const SUPPORT_NEED_PRIORITIES = ["HIGH", "MEDIUM", "LOW"] as const;
export const SUPPORT_NEED_STATUSES = ["OPEN", "IN_PROGRESS", "MET", "DECLINED"] as const;
export const ENTERPRISE_STATUSES = ["ACTIVE", "DORMANT", "CLOSED"] as const;

// ---------------------------------------------------------------------------
// Statistics
// ---------------------------------------------------------------------------

export function mean(values: number[]): number | null {
  if (values.length === 0) return null;
  return values.reduce((sum, value) => sum + value, 0) / values.length;
}

/**
 * Median. Reported ahead of the mean for money.
 *
 * One group with a milling machine pulls a mean to a figure no group is
 * actually at — which is then quoted as typical.
 */
export function median(values: number[]): number | null {
  if (values.length === 0) return null;
  const sorted = [...values].sort((a, b) => a - b);
  const middle = Math.floor(sorted.length / 2);
  if (sorted.length % 2 === 1) return sorted[middle] as number;
  return ((sorted[middle - 1] as number) + (sorted[middle] as number)) / 2;
}

/** Rounds for presentation only. Never applied before a comparison. */
export function round(value: number | null, places = 1): number | null {
  if (value === null || !Number.isFinite(value)) return null;
  const factor = 10 ** places;
  return Math.round(value * factor) / factor;
}

// ---------------------------------------------------------------------------
// Paired change
// ---------------------------------------------------------------------------

/** One unit's first and last reading. A unit is a group or an enterprise. */
export interface PairedUnit {
  unitId: string;
  first: number | null;
  last: number | null;
  /** False where the two readings are not the same measurement. */
  comparable?: boolean;
}

export interface PairedChange {
  indicatorKey: string;
  aggregate: "MEDIAN" | "MEAN";
  baseline: number | null;
  latest: number | null;
  change: number | null;
  percentChange: number | null;
  /** Units counted: both readings present and comparable. */
  pairedUnits: number;
  /** Units with any reading at all. */
  observedUnits: number;
  /** Units that could have had one. */
  eligibleUnits: number;
  coveragePercent: number | null;
  improved: number;
  unchanged: number;
  declined: number;
  /** Had both readings, but the measurement changed underneath. */
  excludedForComparability: number;
  isSmallSample: boolean;
  notes: string[];
}

/**
 * Change measured on the same units at both ends.
 *
 * The alternative — averaging everyone at the start and everyone at the end —
 * is the most common way a programme report states something untrue. As new
 * groups join, the later average is dominated by units with no earlier reading,
 * so the figure moves without a single group having changed. It moves most
 * during expansion, which is exactly when somebody is looking for a result.
 *
 * A unit with one reading counts towards coverage and never towards change.
 */
export function pairedChange(
  indicatorKey: string,
  units: PairedUnit[],
  options: { aggregate?: "MEDIAN" | "MEAN"; eligibleUnits?: number } = {}
): PairedChange {
  const aggregate = options.aggregate ?? "MEDIAN";
  const combine = aggregate === "MEDIAN" ? median : mean;

  const observed = units.filter((unit) => unit.first !== null || unit.last !== null);
  const bothReadings = units.filter((unit) => unit.first !== null && unit.last !== null);
  const paired = bothReadings.filter((unit) => unit.comparable !== false);
  const excludedForComparability = bothReadings.length - paired.length;

  const baseline = combine(paired.map((unit) => unit.first as number));
  const latest = combine(paired.map((unit) => unit.last as number));

  let improved = 0;
  let unchanged = 0;
  let declined = 0;
  for (const unit of paired) {
    const delta = (unit.last as number) - (unit.first as number);
    if (delta > 0) improved += 1;
    else if (delta < 0) declined += 1;
    else unchanged += 1;
  }

  const change = baseline === null || latest === null ? null : latest - baseline;
  // A percentage change from a baseline of zero is undefined, not infinite.
  // Without this guard every such indicator reports "Infinity% growth".
  const percentChange =
    change === null || baseline === null || baseline === 0 ? null : (change / baseline) * 100;

  const eligibleUnits = options.eligibleUnits ?? units.length;
  const coveragePercent = eligibleUnits === 0 ? null : (observed.length / eligibleUnits) * 100;

  const notes: string[] = [];
  if (paired.length === 0) {
    notes.push(
      bothReadings.length === 0
        ? "No unit has two readings yet, so there is no baseline to compare against."
        : "Every unit with two readings was excluded because the measurement changed between them."
    );
  }
  if (excludedForComparability > 0 && paired.length > 0) {
    notes.push(
      `${excludedForComparability} excluded: the measurement changed between their two readings.`
    );
  }
  if (paired.length > 0 && paired.length < SMALL_SAMPLE_THRESHOLD) {
    notes.push(
      `Based on ${paired.length} ${paired.length === 1 ? "unit" : "units"} — too few to read as a trend.`
    );
  }
  if (coveragePercent !== null && coveragePercent < 50 && eligibleUnits > 0) {
    notes.push(
      `Only ${Math.round(coveragePercent)}% of eligible units have any reading, so this describes a minority.`
    );
  }

  return {
    indicatorKey,
    aggregate,
    baseline: round(baseline, 2),
    latest: round(latest, 2),
    change: round(change, 2),
    percentChange: round(percentChange, 1),
    pairedUnits: paired.length,
    observedUnits: observed.length,
    eligibleUnits,
    coveragePercent: round(coveragePercent, 1),
    improved,
    unchanged,
    declined,
    excludedForComparability,
    isSmallSample: paired.length > 0 && paired.length < SMALL_SAMPLE_THRESHOLD,
    notes
  };
}

// ---------------------------------------------------------------------------
// Shares
// ---------------------------------------------------------------------------

export interface Share {
  indicatorKey: string;
  numerator: number;
  denominator: number;
  percent: number | null;
  isSmallSample: boolean;
  notes: string[];
}

/**
 * A count over its denominator, with the denominator kept attached.
 *
 * Returns null rather than 0 when the denominator is empty. Zero out of zero
 * rendered as "0%" reads as failure when the truth is that nothing has been
 * measured — two states a dashboard must never merge.
 */
export function share(indicatorKey: string, numerator: number, denominator: number): Share {
  const notes: string[] = [];
  if (denominator === 0) notes.push("Nothing recorded yet, which is not the same as zero.");
  if (denominator > 0 && denominator < SMALL_SAMPLE_THRESHOLD) {
    notes.push(`Out of ${denominator} — too few for the percentage to carry much weight.`);
  }

  return {
    indicatorKey,
    numerator,
    denominator,
    percent: denominator === 0 ? null : round((numerator / denominator) * 100, 1),
    isSmallSample: denominator > 0 && denominator < SMALL_SAMPLE_THRESHOLD,
    notes
  };
}

// ---------------------------------------------------------------------------
// Direction
// ---------------------------------------------------------------------------

export type Movement = "IMPROVED" | "WORSENED" | "FLAT" | "UNKNOWN";

/**
 * Whether a change is good news, given the indicator's direction.
 *
 * Kept here rather than in a component so that "up is good" is decided once.
 * Days-to-meet falling is an improvement; a chart that colours every rise green
 * says the opposite of what the data does.
 */
export function movementOf(indicatorKey: string, change: number | null): Movement {
  if (change === null) return "UNKNOWN";
  if (change === 0) return "FLAT";

  const direction = (MEAL_INDICATORS as Record<string, IndicatorDefinition>)[indicatorKey]?.direction;
  if (!direction || direction === "NEUTRAL") return "UNKNOWN";
  const rising = change > 0;
  return direction === "UP_IS_GOOD"
    ? rising
      ? "IMPROVED"
      : "WORSENED"
    : rising
      ? "WORSENED"
      : "IMPROVED";
}
