/**
 * IntelliCash Group Credit Rating Contract
 * ========================================
 *
 * A **deterministic, versioned rating contract**: the published, immutable
 * rule-set that turns a group's recorded behaviour into a credit score.
 *
 * Contract properties (why this is a "contract" and not just a formula):
 *  - **Versioned** — every score is stamped with the contract version that
 *    produced it, so a historical score can always be re-derived and defended.
 *  - **Deterministic & pure** — same facts in, same score out. No clock, no
 *    randomness, no I/O. The database derivation lives in the service layer;
 *    the rules live here, alone.
 *  - **Transparent** — every factor publishes its weight, the evidence it was
 *    computed from, and a human-readable explanation. A group can be told
 *    exactly why it scored what it scored, and what to fix.
 *  - **Evidence-bound** — a factor with no supporting evidence scores its
 *    declared baseline (never a silent 0 or a free 100), and a group with too
 *    little history is `UNRATED` rather than mislabelled.
 *
 * The score answers one question: *how well does this group follow VSLA
 * methodology and run itself?* It is built on two pillars —
 *   GOVERNANCE STRUCTURE (40 pts) — is the group properly constituted and run?
 *   VSLA COMPLIANCE      (60 pts) — does it follow the savings/loan discipline?
 */

export const CREDIT_RATING_CONTRACT_VERSION = "1.0.0";

/** Below this many meetings a group has too little history to rate. */
export const MIN_MEETINGS_TO_RATE = 3;

export type CreditPillar = "GOVERNANCE" | "COMPLIANCE";

/** Bands are ordered best → worst. `UNRATED` = insufficient history. */
export type CreditBand = "A" | "B" | "C" | "D" | "UNRATED";

export type CreditFactorKey =
  // Governance structure (40)
  | "leadershipComplete"
  | "securityCompliance"
  | "constitutionAdopted"
  | "democraticGovernance"
  // VSLA compliance (60)
  | "repaymentRate"
  | "savingsConsistency"
  | "attendanceRate"
  | "meetingCompletion"
  | "socialFundHealth"
  | "cycleMaturity";

export interface CreditFactorDefinition {
  key: CreditFactorKey;
  pillar: CreditPillar;
  /** Points this factor contributes at a perfect raw score. Weights total 100. */
  weight: number;
  label: string;
  /** What the group must do to score well — shown back to the group. */
  guideline: string;
  /**
   * Raw score (0-100) used when there is no evidence yet. Never a free 100:
   * unproven is not the same as proven-good.
   */
  noEvidenceBaseline: number;
}

/**
 * The contract terms. Weights are fixed for this version — changing any weight
 * requires a version bump so historical scores stay reproducible.
 */
export const CREDIT_FACTORS: readonly CreditFactorDefinition[] = [
  // ---- Pillar 1: governance structure (40) -------------------------------
  {
    key: "leadershipComplete",
    pillar: "GOVERNANCE",
    weight: 15,
    label: "Leadership complete",
    guideline:
      "Elect and keep active a chairperson, secretary and treasurer; a money counter and key holder strengthen the committee.",
    noEvidenceBaseline: 0
  },
  {
    key: "securityCompliance",
    pillar: "GOVERNANCE",
    weight: 12,
    label: "3-key security",
    guideline:
      "Open every meeting with the 3-key unlock (three officials or five members verifying).",
    noEvidenceBaseline: 0
  },
  {
    key: "constitutionAdopted",
    pillar: "GOVERNANCE",
    weight: 6,
    label: "Constitution adopted",
    guideline: "Adopt and record a written group constitution.",
    noEvidenceBaseline: 0
  },
  {
    key: "democraticGovernance",
    pillar: "GOVERNANCE",
    weight: 7,
    label: "Decisions recorded",
    guideline:
      "Record resolutions and votes so decisions are made democratically and traceably.",
    noEvidenceBaseline: 0
  },
  // ---- Pillar 2: VSLA compliance (60) -----------------------------------
  {
    key: "repaymentRate",
    pillar: "COMPLIANCE",
    weight: 20,
    label: "Loan repayment",
    guideline: "Repay internal loans in full and on time.",
    // No lending history is unproven, not bad: a defined neutral.
    noEvidenceBaseline: 50
  },
  {
    key: "savingsConsistency",
    pillar: "COMPLIANCE",
    weight: 15,
    label: "Savings consistency",
    guideline: "Buy shares at every meeting.",
    noEvidenceBaseline: 0
  },
  {
    key: "attendanceRate",
    pillar: "COMPLIANCE",
    weight: 12,
    label: "Attendance",
    guideline: "Members attend every meeting.",
    noEvidenceBaseline: 0
  },
  {
    key: "meetingCompletion",
    pillar: "COMPLIANCE",
    weight: 6,
    label: "Meetings completed",
    guideline:
      "Run each meeting through every step and seal it, rather than abandoning it.",
    noEvidenceBaseline: 0
  },
  {
    key: "socialFundHealth",
    pillar: "COMPLIANCE",
    weight: 4,
    label: "Social fund kept up",
    guideline: "Collect the social fund at every meeting.",
    noEvidenceBaseline: 0
  },
  {
    key: "cycleMaturity",
    pillar: "COMPLIANCE",
    weight: 3,
    label: "Track record",
    guideline: "Complete savings cycles — a longer history earns more trust.",
    noEvidenceBaseline: 0
  }
] as const;

/** Cycles at which `cycleMaturity` reaches full marks. */
export const MATURE_CYCLE_COUNT = 3;

/** Band thresholds, inclusive lower bounds. Ordered best → worst. */
export const BAND_THRESHOLDS: readonly { band: Exclude<CreditBand, "UNRATED">; min: number }[] = [
  { band: "A", min: 80 },
  { band: "B", min: 65 },
  { band: "C", min: 50 },
  { band: "D", min: 0 }
];

export const BAND_LABELS: Record<CreditBand, string> = {
  A: "Excellent",
  B: "Good",
  C: "Fair",
  D: "Needs improvement",
  UNRATED: "Not yet rated"
};

/**
 * Credit terms the band unlocks in Intelli-Store. Better-run groups get a
 * lower deposit, cheaper credit and longer terms — this is the mechanism by
 * which product pricing follows the credit rating.
 *
 * `interestMultiplierBps` scales the programme's base flat interest
 * (10_000 = 1.0x).
 */
export interface CreditTerms {
  band: CreditBand;
  depositRateBps: number;
  interestMultiplierBps: number;
  maxInstallments: number;
  /** Whether the band may buy on credit at all. */
  creditEligible: boolean;
  summary: string;
}

const CREDIT_TERMS: Record<CreditBand, CreditTerms> = {
  A: {
    band: "A",
    depositRateBps: 1000, // 10%
    interestMultiplierBps: 8000, // 0.80x
    maxInstallments: 12,
    creditEligible: true,
    summary: "10% deposit, lowest interest, up to 12 instalments."
  },
  B: {
    band: "B",
    depositRateBps: 2000, // 20%
    interestMultiplierBps: 10000, // 1.00x
    maxInstallments: 9,
    creditEligible: true,
    summary: "20% deposit, standard interest, up to 9 instalments."
  },
  C: {
    band: "C",
    depositRateBps: 3500, // 35%
    interestMultiplierBps: 12500, // 1.25x
    maxInstallments: 6,
    creditEligible: true,
    summary: "35% deposit, higher interest, up to 6 instalments."
  },
  D: {
    band: "D",
    depositRateBps: 5000, // 50%
    interestMultiplierBps: 15000, // 1.50x
    maxInstallments: 3,
    creditEligible: true,
    summary: "50% deposit, highest interest, up to 3 instalments."
  },
  UNRATED: {
    band: "UNRATED",
    depositRateBps: 5000, // treat like D until proven
    interestMultiplierBps: 15000,
    maxInstallments: 3,
    creditEligible: true,
    summary:
      "Not yet rated — 50% deposit until the group builds a meeting history."
  }
};

export function creditTermsForBand(band: CreditBand): CreditTerms {
  return CREDIT_TERMS[band];
}

/**
 * The raw, observable facts the contract scores. Every field is a plain count
 * or amount taken straight from recorded data — nothing pre-scored, so the
 * contract remains the single source of the rules.
 */
export interface CreditRatingFacts {
  // Governance
  activeMembers: number;
  /** Distinct ACTIVE members holding each in-group role. */
  officialRoles: {
    chairperson: number;
    secretary: number;
    treasurer: number;
    moneyCounter: number;
    keyHolder: number;
  };
  hasConstitution: boolean;
  votesRecorded: number;

  // Meetings
  meetingsTotal: number;
  /** Meetings that reached at least IN_PROGRESS (i.e. were opened). */
  meetingsOpened: number;
  /** Opened meetings whose unlock satisfied the 3-key policy. */
  meetingsUnlockCompliant: number;
  meetingsSealed: number;
  meetingsWithSharePurchase: number;
  meetingsWithSocialContribution: number;

  // Attendance
  attendanceRecords: number;
  attendancePresent: number;

  // Lending (ledger-derived, in cents)
  loanDisbursedCents: number;
  loanRepaidCents: number;

  // Track record
  cycleNumber: number;
}

export interface CreditFactorResult {
  key: CreditFactorKey;
  pillar: CreditPillar;
  label: string;
  weight: number;
  /** 0-100 before weighting. */
  rawScore: number;
  /** Contribution to the final score (rawScore × weight / 100). */
  points: number;
  /** True when scored from the no-evidence baseline rather than real data. */
  fromBaseline: boolean;
  /** Human-readable evidence, e.g. "8 of 10 meetings". */
  evidence: string;
  guideline: string;
}

export interface CreditRating {
  contractVersion: string;
  /** 0-100. */
  score: number;
  band: CreditBand;
  bandLabel: string;
  /** False when the group has too little history for the band to be meaningful. */
  rated: boolean;
  pillars: { governance: number; compliance: number };
  factors: CreditFactorResult[];
  terms: CreditTerms;
  /** The biggest scoring gaps, best-first — what to fix to improve.  */
  recommendations: string[];
}

// ---------------------------------------------------------------------------
// Evaluation
// ---------------------------------------------------------------------------

/**
 * Evaluates the contract against a group's facts. Pure and total: any facts
 * (including all-zero) yield a defined rating.
 */
export function evaluateCreditRating(facts: CreditRatingFacts): CreditRating {
  const raw = rawScores(facts);

  const factors: CreditFactorResult[] = CREDIT_FACTORS.map((def) => {
    const measured = raw[def.key];
    const fromBaseline = measured.value === null;
    const rawScore = clamp(
      fromBaseline ? def.noEvidenceBaseline : (measured.value as number)
    );
    return {
      key: def.key,
      pillar: def.pillar,
      label: def.label,
      weight: def.weight,
      rawScore,
      points: round2((rawScore * def.weight) / 100),
      fromBaseline,
      evidence: measured.evidence,
      guideline: def.guideline
    };
  });

  const score = clamp(
    Math.round(factors.reduce((sum, f) => sum + f.points, 0))
  );
  const rated = facts.meetingsTotal >= MIN_MEETINGS_TO_RATE;
  const band: CreditBand = rated ? bandForScore(score) : "UNRATED";

  const pillarPoints = (pillar: CreditPillar) =>
    round2(
      factors
        .filter((f) => f.pillar === pillar)
        .reduce((sum, f) => sum + f.points, 0)
    );

  return {
    contractVersion: CREDIT_RATING_CONTRACT_VERSION,
    score,
    band,
    bandLabel: BAND_LABELS[band],
    rated,
    pillars: {
      governance: pillarPoints("GOVERNANCE"),
      compliance: pillarPoints("COMPLIANCE")
    },
    factors,
    terms: creditTermsForBand(band),
    recommendations: recommendationsFor(factors, rated, facts)
  };
}

export function bandForScore(score: number): Exclude<CreditBand, "UNRATED"> {
  for (const threshold of BAND_THRESHOLDS) {
    if (score >= threshold.min) return threshold.band;
  }
  return "D";
}

/**
 * Derives each factor's raw 0-100 score from the facts. `value: null` means
 * "no evidence" — the caller substitutes the factor's declared baseline.
 */
function rawScores(
  f: CreditRatingFacts
): Record<CreditFactorKey, { value: number | null; evidence: string }> {
  const core =
    Math.min(f.officialRoles.chairperson, 1) +
    Math.min(f.officialRoles.secretary, 1) +
    Math.min(f.officialRoles.treasurer, 1);
  const support =
    Math.min(f.officialRoles.moneyCounter, 1) + Math.min(f.officialRoles.keyHolder, 1);

  return {
    // The three core offices carry 80%; money counter + key holder add 20%.
    leadershipComplete: {
      value: (core / 3) * 80 + (support / 2) * 20,
      evidence: `${core}/3 core offices filled, ${support}/2 support roles`
    },
    securityCompliance: {
      value: ratio(f.meetingsUnlockCompliant, f.meetingsOpened),
      evidence: `${f.meetingsUnlockCompliant} of ${f.meetingsOpened} opened meetings used the 3-key unlock`
    },
    constitutionAdopted: {
      value: f.hasConstitution ? 100 : 0,
      evidence: f.hasConstitution ? "Constitution recorded" : "No constitution recorded"
    },
    democraticGovernance: {
      // One recorded resolution per sealed meeting earns full marks.
      value: ratio(f.votesRecorded, f.meetingsSealed),
      evidence: `${f.votesRecorded} resolution(s) across ${f.meetingsSealed} sealed meeting(s)`
    },
    repaymentRate: {
      value: ratio(f.loanRepaidCents, f.loanDisbursedCents),
      evidence:
        f.loanDisbursedCents > 0
          ? `${money(f.loanRepaidCents)} repaid of ${money(f.loanDisbursedCents)} lent`
          : "No loans issued yet"
    },
    savingsConsistency: {
      value: ratio(f.meetingsWithSharePurchase, f.meetingsTotal),
      evidence: `Shares bought in ${f.meetingsWithSharePurchase} of ${f.meetingsTotal} meeting(s)`
    },
    attendanceRate: {
      value: ratio(f.attendancePresent, f.attendanceRecords),
      evidence: `${f.attendancePresent} present of ${f.attendanceRecords} attendance record(s)`
    },
    meetingCompletion: {
      value: ratio(f.meetingsSealed, f.meetingsTotal),
      evidence: `${f.meetingsSealed} of ${f.meetingsTotal} meeting(s) sealed`
    },
    socialFundHealth: {
      value: ratio(f.meetingsWithSocialContribution, f.meetingsTotal),
      evidence: `Social fund collected in ${f.meetingsWithSocialContribution} of ${f.meetingsTotal} meeting(s)`
    },
    cycleMaturity: {
      value:
        f.cycleNumber <= 0
          ? null
          : (Math.min(f.cycleNumber, MATURE_CYCLE_COUNT) / MATURE_CYCLE_COUNT) * 100,
      evidence: f.cycleNumber > 0 ? `Cycle ${f.cycleNumber}` : "No cycle started"
    }
  };
}

/** Percentage of `part` over `whole`, or null when there is no evidence. */
function ratio(part: number, whole: number): number | null {
  if (whole <= 0) return null;
  return clamp((part / whole) * 100);
}

function clamp(value: number) {
  if (!Number.isFinite(value)) return 0;
  return Math.max(0, Math.min(100, value));
}

function round2(value: number) {
  return Math.round(value * 100) / 100;
}

function money(cents: number) {
  return `KSh ${(cents / 100).toLocaleString("en-KE")}`;
}

/**
 * The lowest-scoring factors by lost points — the most valuable things the
 * group could fix. Capped at three so the advice stays actionable.
 */
function recommendationsFor(
  factors: CreditFactorResult[],
  rated: boolean,
  facts: CreditRatingFacts
): string[] {
  const tips: string[] = [];
  if (!rated) {
    tips.push(
      `Hold and seal at least ${MIN_MEETINGS_TO_RATE} meetings to earn a credit rating (${facts.meetingsTotal} so far).`
    );
  }
  const gaps = factors
    .map((f) => ({ f, lost: f.weight - f.points }))
    .filter((g) => g.lost > 0.5)
    .sort((a, b) => b.lost - a.lost)
    .slice(0, 3);
  for (const gap of gaps) {
    tips.push(`${gap.f.label}: ${gap.f.guideline}`);
  }
  return tips;
}
