/**
 * IntelliCash Visit Assessment Contract
 * =====================================
 *
 * The rules for scoring the human-filled assessment an agent completes during a
 * field visit. Same shape as `credit-rating-contract.ts` — versioned,
 * deterministic, pure, no I/O — but one crucial difference:
 *
 *   The credit rating's *questions* are hard-coded here in the contract.
 *   The assessment's questions are **data**, authored and versioned by IWL.
 *
 * So this module scores answers against a **snapshot** handed to it, and never
 * reads a template. That is what makes a historical assessment reproducible: the
 * snapshot is frozen at publish, the visit stores which snapshot it used, and
 * re-scoring it years later cannot be disturbed by anyone editing the live
 * template in between.
 *
 * There is no 92 in this file, and there must never be one. The 92-point form is
 * seed content for template v1; `maxPoints` is computed from whatever questions
 * a template actually carries. If the seeded questions don't total 92, the seed
 * is wrong — not the engine.
 *
 * Two implementations of these rules exist: this one, and its Dart mirror in the
 * phone (`lib/core/utils/visit_assessment_scoring.dart`) so an agent sees a band
 * before syncing. A shared JSON fixture is asserted by both suites. When they
 * disagree, **the server wins** and the phone's figure was provisional.
 */

export const VISIT_ASSESSMENT_CONTRACT_VERSION = "1.0.0";

/**
 * Keys are the stable join across template versions — a cross-visit trend for
 * "governance" only means something if v1 and v4 both call it `governance`.
 * Restricted so they can be used unescaped in JSON, URLs and Dart maps alike.
 */
export const ASSESSMENT_KEY_PATTERN = /^[a-z0-9][a-z0-9_]{0,62}$/;

/**
 * What an agent can answer. Fixed by the contract rather than per-template: a
 * uniform answer set is what lets one scoring rule serve every template, and
 * three-state Yes/Partial/No is the instrument IWL actually uses in the field.
 *
 * `credit` is the fraction of the question's weight earned, so a 2-point
 * question scores 2 / 1 / 0 without the weight being mentioned here.
 */
export type AssessmentChoice = "YES" | "PARTIAL" | "NO" | "NOT_APPLICABLE";

export interface AssessmentChoiceDefinition {
  key: AssessmentChoice;
  label: string;
  /** Fraction of the question's weight earned. Null = excluded from scoring. */
  credit: number | null;
}

export const ASSESSMENT_CHOICES: readonly AssessmentChoiceDefinition[] = [
  { key: "YES", label: "Yes", credit: 1 },
  { key: "PARTIAL", label: "Partial", credit: 0.5 },
  { key: "NO", label: "No", credit: 0 },
  // Genuinely inapplicable — a question about loan books for a group that has
  // never lent. Removed from the denominator rather than scored 0, so a group
  // is not marked down for a practice that cannot apply to it.
  { key: "NOT_APPLICABLE", label: "Not applicable", credit: null }
];

const CHOICE_CREDIT = new Map<string, number | null>(
  ASSESSMENT_CHOICES.map((choice) => [choice.key, choice.credit])
);

export function isAssessmentChoice(value: string): value is AssessmentChoice {
  return CHOICE_CREDIT.has(value);
}

// ---------------------------------------------------------------------------
// The snapshot: a frozen, self-contained template
// ---------------------------------------------------------------------------

export interface AssessmentQuestionSnapshot {
  key: string;
  prompt: string;
  /** Points at full credit. Positive; not required to be a whole number. */
  weight: number;
  /** What "Yes" looks like — shown to the agent to keep scoring consistent. */
  guidance?: string;
  /** Order within the section. */
  position: number;
  /** When true the agent must justify anything below full credit. */
  requiresNote?: boolean;
}

export interface AssessmentSectionSnapshot {
  key: string;
  title: string;
  description?: string;
  position: number;
  questions: AssessmentQuestionSnapshot[];
}

export interface AssessmentBandSnapshot {
  key: string;
  label: string;
  /** Inclusive lower bound, in points. */
  minPoints: number;
  /** Inclusive upper bound, in points. */
  maxPoints: number;
  /** What this band means and what the group should do about it. */
  guidance?: string;
}

/**
 * Everything needed to render and score an assessment, with no reference to any
 * database row. This is what gets frozen at publish, cached on the phone, and
 * stored against each completed assessment.
 */
export interface AssessmentTemplateSnapshot {
  templateId: string;
  version: number;
  title: string;
  description?: string;
  sections: AssessmentSectionSnapshot[];
  bands: AssessmentBandSnapshot[];
  /** Sum of every question weight. Computed at publish, never assumed. */
  maxPoints: number;
  /** The contract version that validated it — and that must re-score it. */
  scoringContractVersion: string;
}

// ---------------------------------------------------------------------------
// Validation — run at publish, when a template stops being editable
// ---------------------------------------------------------------------------

export interface AssessmentValidationIssue {
  /** Dotted path to the offending element, e.g. `sections.1.questions.3.weight`. */
  path: string;
  message: string;
}

export type AssessmentValidationResult =
  | { ok: true; maxPoints: number }
  | { ok: false; issues: AssessmentValidationIssue[] };

/** A template as it stands mid-authoring, before `maxPoints` is known. */
export interface AssessmentTemplateDraft {
  title: string;
  description?: string;
  sections: AssessmentSectionSnapshot[];
  bands: AssessmentBandSnapshot[];
}

/**
 * Checks a draft is publishable.
 *
 * Reports **every** problem rather than stopping at the first: this drives an
 * authoring screen, and fixing one issue only to be told about the next is a
 * miserable way to build a 46-question form.
 */
export function validateAssessmentTemplate(
  draft: AssessmentTemplateDraft
): AssessmentValidationResult {
  const issues: AssessmentValidationIssue[] = [];
  const add = (path: string, message: string) => issues.push({ path, message });

  if (!draft.title?.trim()) add("title", "The template needs a title.");

  if (!draft.sections?.length) {
    add("sections", "A template needs at least one section.");
  }

  const seenSectionKeys = new Set<string>();
  // Question keys are unique across the WHOLE template, not per section. They
  // are what answers, evidence and trends join on, so a duplicate would make an
  // answer ambiguous even if the two lived in different sections.
  const seenQuestionKeys = new Set<string>();
  let maxPoints = 0;

  (draft.sections ?? []).forEach((section, sectionIndex) => {
    const at = `sections.${sectionIndex}`;

    if (!ASSESSMENT_KEY_PATTERN.test(section.key ?? "")) {
      add(
        `${at}.key`,
        `"${section.key}" is not a valid key. Use lower-case letters, digits and underscores.`
      );
    } else if (seenSectionKeys.has(section.key)) {
      add(`${at}.key`, `Duplicate section key "${section.key}".`);
    } else {
      seenSectionKeys.add(section.key);
    }

    if (!section.title?.trim()) add(`${at}.title`, "The section needs a title.");

    if (!section.questions?.length) {
      add(`${at}.questions`, `Section "${section.key}" has no questions.`);
    }

    (section.questions ?? []).forEach((question, questionIndex) => {
      const qAt = `${at}.questions.${questionIndex}`;

      if (!ASSESSMENT_KEY_PATTERN.test(question.key ?? "")) {
        add(
          `${qAt}.key`,
          `"${question.key}" is not a valid key. Use lower-case letters, digits and underscores.`
        );
      } else if (seenQuestionKeys.has(question.key)) {
        add(`${qAt}.key`, `Duplicate question key "${question.key}".`);
      } else {
        seenQuestionKeys.add(question.key);
      }

      if (!question.prompt?.trim()) add(`${qAt}.prompt`, "The question needs a prompt.");

      if (!Number.isFinite(question.weight) || question.weight <= 0) {
        add(
          `${qAt}.weight`,
          `Weight must be a positive number, got ${String(question.weight)}.`
        );
      } else {
        maxPoints += question.weight;
      }
    });
  });

  maxPoints = round2(maxPoints);

  if (maxPoints <= 0) {
    add("sections", "The questions carry no points, so nothing could be scored.");
  }

  validateBands(draft.bands ?? [], maxPoints, add);

  if (issues.length) return { ok: false, issues };
  return { ok: true, maxPoints };
}

/**
 * Bands must tile `[0, maxPoints]` exactly — contiguous, non-overlapping, no
 * gaps at either end. A gap means some achievable score has no band, and the
 * first time anyone notices is when a real assessment lands in the hole.
 */
function validateBands(
  bands: AssessmentBandSnapshot[],
  maxPoints: number,
  add: (path: string, message: string) => void
) {
  if (!bands.length) {
    add("bands", "A template needs at least one band.");
    return;
  }

  const seenKeys = new Set<string>();
  bands.forEach((band, index) => {
    const at = `bands.${index}`;
    if (!ASSESSMENT_KEY_PATTERN.test(band.key ?? "")) {
      add(`${at}.key`, `"${band.key}" is not a valid band key.`);
    } else if (seenKeys.has(band.key)) {
      add(`${at}.key`, `Duplicate band key "${band.key}".`);
    } else {
      seenKeys.add(band.key);
    }

    if (!band.label?.trim()) add(`${at}.label`, "The band needs a label.");

    if (!Number.isFinite(band.minPoints) || !Number.isFinite(band.maxPoints)) {
      add(`${at}`, "Band bounds must be numbers.");
      return;
    }
    if (band.minPoints > band.maxPoints) {
      add(`${at}`, `Band "${band.key}" ends (${band.maxPoints}) before it starts (${band.minPoints}).`);
    }
  });

  // Coverage is only meaningful once the bounds are numbers and maxPoints is
  // real; checking it against a broken draft produces noise on top of noise.
  if (maxPoints <= 0) return;
  if (bands.some((b) => !Number.isFinite(b.minPoints) || !Number.isFinite(b.maxPoints))) return;

  const sorted = [...bands].sort((a, b) => a.minPoints - b.minPoints);
  const lowest = sorted[0];
  const top = sorted[sorted.length - 1];
  if (!lowest || !top) return;

  if (lowest.minPoints !== 0) {
    add("bands", `The lowest band starts at ${lowest.minPoints}, so a score of 0 has no band.`);
  }

  if (round2(top.maxPoints) !== maxPoints) {
    add(
      "bands",
      `The highest band ends at ${top.maxPoints} but the questions total ${maxPoints} points.`
    );
  }

  for (let i = 1; i < sorted.length; i += 1) {
    const previous = sorted[i - 1];
    const current = sorted[i];
    if (!previous || !current) continue;
    const expected = round2(previous.maxPoints + 1);

    if (current.minPoints <= previous.maxPoints) {
      add(
        "bands",
        `Bands "${previous.key}" and "${current.key}" overlap between ${current.minPoints} and ${previous.maxPoints}.`
      );
    } else if (round2(current.minPoints) !== expected) {
      add(
        "bands",
        `Nothing covers ${expected}–${round2(current.minPoints - 1)}, between "${previous.key}" and "${current.key}".`
      );
    }
  }
}

/**
 * Turns a validated draft into the immutable snapshot that gets frozen.
 * Sections and questions are ordered here, once, so every later reader —
 * server, web, phone — sees the same order without re-sorting.
 */
export function buildAssessmentSnapshot(
  draft: AssessmentTemplateDraft,
  identity: { templateId: string; version: number; maxPoints: number }
): AssessmentTemplateSnapshot {
  return {
    templateId: identity.templateId,
    version: identity.version,
    title: draft.title.trim(),
    ...(draft.description?.trim() ? { description: draft.description.trim() } : {}),
    sections: [...draft.sections]
      .sort(byPosition)
      .map((section, sectionIndex) => ({
        key: section.key,
        title: section.title.trim(),
        ...(section.description?.trim() ? { description: section.description.trim() } : {}),
        position: sectionIndex,
        questions: [...section.questions].sort(byPosition).map((question, questionIndex) => ({
          key: question.key,
          prompt: question.prompt.trim(),
          weight: round2(question.weight),
          ...(question.guidance?.trim() ? { guidance: question.guidance.trim() } : {}),
          position: questionIndex,
          ...(question.requiresNote ? { requiresNote: true } : {})
        }))
      })),
    bands: [...draft.bands]
      .sort((a, b) => b.minPoints - a.minPoints) // best band first
      .map((band) => ({
        key: band.key,
        label: band.label.trim(),
        minPoints: round2(band.minPoints),
        maxPoints: round2(band.maxPoints),
        ...(band.guidance?.trim() ? { guidance: band.guidance.trim() } : {})
      })),
    maxPoints: identity.maxPoints,
    scoringContractVersion: VISIT_ASSESSMENT_CONTRACT_VERSION
  };
}

/**
 * A deterministic string form of a snapshot, for checksumming.
 *
 * `JSON.stringify` is not enough: its output follows key insertion order, so two
 * structurally identical snapshots built by different code paths can serialize
 * differently and appear to be different templates. This walks the value and
 * emits object keys sorted.
 *
 * Hashing lives in the service — the contract stays free of imports so the Dart
 * mirror has nothing to reproduce but arithmetic.
 */
export function canonicalizeSnapshot(value: unknown): string {
  if (value === null || typeof value !== "object") return JSON.stringify(value) ?? "null";
  if (Array.isArray(value)) return `[${value.map(canonicalizeSnapshot).join(",")}]`;

  const entries = Object.entries(value as Record<string, unknown>)
    .filter(([, v]) => v !== undefined)
    .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0));

  return `{${entries
    .map(([key, v]) => `${JSON.stringify(key)}:${canonicalizeSnapshot(v)}`)
    .join(",")}}`;
}

// ---------------------------------------------------------------------------
// Scoring
// ---------------------------------------------------------------------------

export interface AssessmentAnswerInput {
  questionKey: string;
  choice: string;
  note?: string;
}

export interface AssessmentQuestionResult {
  questionKey: string;
  sectionKey: string;
  prompt: string;
  weight: number;
  choice: AssessmentChoice | null;
  /** Points earned. 0 for an unanswered question. */
  earnedPoints: number;
  /** Points this question contributed to the denominator — 0 when N/A. */
  applicablePoints: number;
  answered: boolean;
  excluded: boolean;
  note?: string;
}

export interface AssessmentSectionResult {
  sectionKey: string;
  title: string;
  position: number;
  earnedPoints: number;
  applicablePoints: number;
  /** 0-100 within this section, or null when every question was N/A. */
  percentage: number | null;
  questions: AssessmentQuestionResult[];
}

export interface AssessmentScore {
  scoringContractVersion: string;
  templateId: string;
  templateVersion: number;
  /** Raw points earned across applicable questions. */
  earnedPoints: number;
  /** Points that were actually in play — `maxPoints` minus anything N/A. */
  applicablePoints: number;
  /** The template's full scale, for context. */
  maxPoints: number;
  /**
   * `earnedPoints` rescaled onto the full `maxPoints` scale, which is what the
   * band is resolved against. Identical to `earnedPoints` when nothing was
   * marked N/A — the ordinary case.
   */
  scaledPoints: number;
  /** 0-100. The comparable figure across templates and versions. */
  percentage: number;
  bandKey: string | null;
  bandLabel: string | null;
  bandGuidance?: string;
  sections: AssessmentSectionResult[];
  /** Questions in the snapshot that nobody answered. */
  unansweredKeys: string[];
  /** Answers whose question is not in this snapshot — kept, never scored. */
  unknownAnswerKeys: string[];
  complete: boolean;
}

/**
 * Scores answers against a snapshot. Pure and total: any inputs, including
 * none, produce a defined score.
 *
 * Two deliberate asymmetries:
 *
 * - **Unanswered scores 0 but stays in the denominator.** Skipping a question
 *   is not the same as it not applying, and must not quietly raise the
 *   percentage. `complete` and `unansweredKeys` say so out loud.
 * - **N/A leaves the denominator.** The remaining points are then rescaled onto
 *   the full range before banding, so a group with three inapplicable questions
 *   is still judged on the same 0-100 as everyone else.
 */
export function scoreAssessment(
  snapshot: AssessmentTemplateSnapshot,
  answers: readonly AssessmentAnswerInput[]
): AssessmentScore {
  const byKey = new Map<string, AssessmentAnswerInput>();
  for (const answer of answers) {
    // Last write wins: a resent visit payload may carry a corrected answer, and
    // throwing here would reject the whole document over a duplicate.
    byKey.set(answer.questionKey, answer);
  }

  const knownKeys = new Set<string>();
  const unansweredKeys: string[] = [];
  const sections: AssessmentSectionResult[] = [];

  let earnedPoints = 0;
  let applicablePoints = 0;

  for (const section of [...snapshot.sections].sort(byPosition)) {
    const questions: AssessmentQuestionResult[] = [];
    let sectionEarned = 0;
    let sectionApplicable = 0;

    for (const question of [...section.questions].sort(byPosition)) {
      knownKeys.add(question.key);
      const answer = byKey.get(question.key);
      const choice =
        answer && isAssessmentChoice(answer.choice) ? answer.choice : null;
      const credit = choice ? CHOICE_CREDIT.get(choice) ?? null : null;
      const excluded = choice === "NOT_APPLICABLE";

      if (!choice) unansweredKeys.push(question.key);

      const questionApplicable = excluded ? 0 : question.weight;
      const questionEarned =
        credit === null ? 0 : round2(question.weight * credit);

      sectionEarned += questionEarned;
      sectionApplicable += questionApplicable;

      questions.push({
        questionKey: question.key,
        sectionKey: section.key,
        prompt: question.prompt,
        weight: question.weight,
        choice,
        earnedPoints: questionEarned,
        applicablePoints: questionApplicable,
        answered: choice !== null,
        excluded,
        ...(answer?.note ? { note: answer.note } : {})
      });
    }

    sectionEarned = round2(sectionEarned);
    sectionApplicable = round2(sectionApplicable);
    earnedPoints += sectionEarned;
    applicablePoints += sectionApplicable;

    sections.push({
      sectionKey: section.key,
      title: section.title,
      position: section.position,
      earnedPoints: sectionEarned,
      applicablePoints: sectionApplicable,
      percentage:
        sectionApplicable > 0
          ? round2((sectionEarned / sectionApplicable) * 100)
          : null,
      questions
    });
  }

  earnedPoints = round2(earnedPoints);
  applicablePoints = round2(applicablePoints);

  const percentage =
    applicablePoints > 0 ? round2((earnedPoints / applicablePoints) * 100) : 0;
  const scaledPoints = round2((percentage / 100) * snapshot.maxPoints);
  const band = bandForPoints(snapshot, scaledPoints);

  const unknownAnswerKeys = [...byKey.keys()].filter((key) => !knownKeys.has(key));

  return {
    scoringContractVersion: VISIT_ASSESSMENT_CONTRACT_VERSION,
    templateId: snapshot.templateId,
    templateVersion: snapshot.version,
    earnedPoints,
    applicablePoints,
    maxPoints: snapshot.maxPoints,
    scaledPoints,
    percentage,
    bandKey: band?.key ?? null,
    bandLabel: band?.label ?? null,
    ...(band?.guidance ? { bandGuidance: band.guidance } : {}),
    sections,
    unansweredKeys,
    unknownAnswerKeys,
    complete: unansweredKeys.length === 0
  };
}

/**
 * The band a point total falls in: the highest band the score reaches.
 *
 * Matched on `minPoints` alone, deliberately. Bands are authored with whole-
 * number bounds (0–36, 37–55, …) but a score is frequently fractional: PARTIAL
 * earns half a question's weight, and rescaling around a NOT_APPLICABLE
 * question divides. Testing `points <= band.maxPoints` as well left every
 * fraction between two bands unbanded — a score of 7.78 against bands 0–7 and
 * 8–13 matched neither, and came back null.
 *
 * Treating each band as running from its own `minPoints` up to the next band's
 * closes those gaps by construction. `maxPoints` is still validated and still
 * displayed; it just is not what the lookup turns on.
 */
export function bandForPoints(
  snapshot: AssessmentTemplateSnapshot,
  points: number
): AssessmentBandSnapshot | null {
  let best: AssessmentBandSnapshot | null = null;
  for (const band of snapshot.bands) {
    if (points >= band.minPoints && (!best || band.minPoints > best.minPoints)) {
      best = band;
    }
  }
  // Below every band's floor — only reachable on a snapshot whose lowest band
  // does not start at 0, which validation refuses at publish.
  return best;
}

function byPosition(a: { position: number }, b: { position: number }) {
  return a.position - b.position;
}

function round2(value: number) {
  return Math.round(value * 100) / 100;
}
