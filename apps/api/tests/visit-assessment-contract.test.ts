import { describe, expect, it } from "vitest";
import {
  ASSESSMENT_CHOICES,
  VISIT_ASSESSMENT_CONTRACT_VERSION,
  bandForPoints,
  buildAssessmentSnapshot,
  canonicalizeSnapshot,
  scoreAssessment,
  validateAssessmentTemplate,
  type AssessmentAnswerInput,
  type AssessmentTemplateDraft,
  type AssessmentTemplateSnapshot
} from "../src/domain/visit-assessment-contract";

/**
 * A small, valid template: 3 questions worth 2 points each, 6 points total.
 * Small enough to reason about by hand, which is the point — the arithmetic
 * being checked is the same at 6 points as at 92.
 */
function draft(): AssessmentTemplateDraft {
  return {
    title: "Field assessment",
    sections: [
      {
        key: "governance",
        title: "Governance",
        position: 0,
        questions: [
          { key: "constitution", prompt: "Is there a constitution?", weight: 2, position: 0 },
          { key: "committee", prompt: "Is the committee complete?", weight: 2, position: 1 }
        ]
      },
      {
        key: "records",
        title: "Record keeping",
        position: 1,
        questions: [
          { key: "passbooks", prompt: "Are passbooks current?", weight: 2, position: 0 }
        ]
      }
    ],
    bands: [
      { key: "weak", label: "Weak", minPoints: 0, maxPoints: 2 },
      { key: "fair", label: "Fair", minPoints: 3, maxPoints: 4 },
      { key: "strong", label: "Strong", minPoints: 5, maxPoints: 6, guidance: "Keep it up." }
    ]
  };
}

function publish(source: AssessmentTemplateDraft = draft()): AssessmentTemplateSnapshot {
  const result = validateAssessmentTemplate(source);
  if (!result.ok) {
    throw new Error(`fixture is invalid: ${JSON.stringify(result.issues)}`);
  }
  return buildAssessmentSnapshot(source, {
    templateId: "tmpl_1",
    version: 1,
    maxPoints: result.maxPoints
  });
}

function answers(...pairs: [string, string][]): AssessmentAnswerInput[] {
  return pairs.map(([questionKey, choice]) => ({ questionKey, choice }));
}

/**
 * Look fixture parts up by key rather than by index. Keys are what the contract
 * itself joins on, so a test that says `questionOf(source, "passbooks")` stays
 * readable — and stays correct — when the fixture is reordered.
 */
function sectionOf(source: { sections: { key: string }[] }, key: string) {
  const section = source.sections.find((candidate) => candidate.key === key);
  if (!section) throw new Error(`fixture has no section "${key}"`);
  return section as AssessmentTemplateDraft["sections"][number];
}

function questionOf(source: AssessmentTemplateDraft, key: string) {
  for (const section of source.sections) {
    const question = section.questions.find((candidate) => candidate.key === key);
    if (question) return question;
  }
  throw new Error(`fixture has no question "${key}"`);
}

function bandOf(source: AssessmentTemplateDraft, key: string) {
  const band = source.bands.find((candidate) => candidate.key === key);
  if (!band) throw new Error(`fixture has no band "${key}"`);
  return band;
}

describe("validateAssessmentTemplate", () => {
  it("computes maxPoints from the questions rather than assuming a total", () => {
    const result = validateAssessmentTemplate(draft());
    expect(result).toEqual({ ok: true, maxPoints: 6 });
  });

  it("adding a question moves maxPoints, with no constant to update", () => {
    const source = draft();
    sectionOf(source, "governance").questions.push({
      key: "elections",
      prompt: "Were elections held?",
      weight: 3,
      position: 2
    });
    bandOf(source, "strong").maxPoints = 9;

    const result = validateAssessmentTemplate(source);
    expect(result).toEqual({ ok: true, maxPoints: 9 });
  });

  it("rejects a duplicate question key even across different sections", () => {
    const source = draft();
    questionOf(source, "passbooks").key = "constitution";

    const result = validateAssessmentTemplate(source);
    expect(result.ok).toBe(false);
    if (result.ok) return;
    expect(result.issues.some((i) => /Duplicate question key/.test(i.message))).toBe(true);
  });

  it("rejects a zero or negative weight", () => {
    const source = draft();
    questionOf(source, "constitution").weight = 0;
    questionOf(source, "committee").weight = -2;

    const result = validateAssessmentTemplate(source);
    expect(result.ok).toBe(false);
    if (result.ok) return;
    expect(result.issues.filter((i) => /Weight must be positive|Weight must be a positive/.test(i.message))).toHaveLength(2);
  });

  it("reports every problem at once, because this drives an authoring screen", () => {
    const result = validateAssessmentTemplate({
      title: "",
      sections: [{ key: "Bad Key", title: "", position: 0, questions: [] }],
      bands: []
    });

    expect(result.ok).toBe(false);
    if (result.ok) return;
    const paths = result.issues.map((i) => i.path);
    expect(paths).toContain("title");
    expect(paths).toContain("sections.0.key");
    expect(paths).toContain("sections.0.title");
    expect(paths).toContain("sections.0.questions");
    expect(paths).toContain("bands");
  });

  describe("band coverage", () => {
    it("rejects a gap between two bands", () => {
      const source = draft();
      bandOf(source, "fair").maxPoints = 3; // leaves 4 uncovered

      const result = validateAssessmentTemplate(source);
      expect(result.ok).toBe(false);
      if (result.ok) return;
      expect(result.issues.some((i) => /Nothing covers 4/.test(i.message))).toBe(true);
    });

    it("rejects overlapping bands", () => {
      const source = draft();
      bandOf(source, "fair").maxPoints = 5; // now overlaps "strong"

      const result = validateAssessmentTemplate(source);
      expect(result.ok).toBe(false);
      if (result.ok) return;
      expect(result.issues.some((i) => /overlap/.test(i.message))).toBe(true);
    });

    it("rejects bands that do not start at zero", () => {
      const source = draft();
      bandOf(source, "weak").minPoints = 1;

      const result = validateAssessmentTemplate(source);
      expect(result.ok).toBe(false);
      if (result.ok) return;
      expect(result.issues.some((i) => /score of 0 has no band/.test(i.message))).toBe(true);
    });

    it("rejects bands that stop short of maxPoints", () => {
      const source = draft();
      bandOf(source, "strong").maxPoints = 5; // questions total 6

      const result = validateAssessmentTemplate(source);
      expect(result.ok).toBe(false);
      if (result.ok) return;
      expect(
        result.issues.some((i) => /highest band ends at 5 but the questions total 6/.test(i.message))
      ).toBe(true);
    });
  });
});

describe("scoreAssessment", () => {
  it("scores Yes / Partial / No at full, half and no credit", () => {
    const score = scoreAssessment(
      publish(),
      answers(["constitution", "YES"], ["committee", "PARTIAL"], ["passbooks", "NO"])
    );

    expect(score.earnedPoints).toBe(3);
    expect(score.applicablePoints).toBe(6);
    expect(score.percentage).toBe(50);
    expect(score.bandKey).toBe("fair");
    expect(score.complete).toBe(true);
  });

  it("bands a perfect score at the top and an empty one at the bottom", () => {
    const snapshot = publish();

    const perfect = scoreAssessment(
      snapshot,
      answers(["constitution", "YES"], ["committee", "YES"], ["passbooks", "YES"])
    );
    expect(perfect.earnedPoints).toBe(6);
    expect(perfect.percentage).toBe(100);
    expect(perfect.bandKey).toBe("strong");
    expect(perfect.bandGuidance).toBe("Keep it up.");

    const nothing = scoreAssessment(
      snapshot,
      answers(["constitution", "NO"], ["committee", "NO"], ["passbooks", "NO"])
    );
    expect(nothing.earnedPoints).toBe(0);
    expect(nothing.bandKey).toBe("weak");
  });

  it("is total: no answers at all still produces a defined score", () => {
    const score = scoreAssessment(publish(), []);

    expect(score.earnedPoints).toBe(0);
    expect(score.percentage).toBe(0);
    expect(score.bandKey).toBe("weak");
    expect(score.complete).toBe(false);
    expect(score.unansweredKeys).toEqual(["constitution", "committee", "passbooks"]);
  });

  it("scores an unanswered question 0 but keeps it in the denominator", () => {
    // Skipping must not flatter the group. Two Yes out of three questions is
    // 4/6, not 4/4 — the contrast with NOT_APPLICABLE below is the whole point.
    const score = scoreAssessment(
      publish(),
      answers(["constitution", "YES"], ["committee", "YES"])
    );

    expect(score.earnedPoints).toBe(4);
    expect(score.applicablePoints).toBe(6);
    expect(score.percentage).toBe(66.67);
    expect(score.complete).toBe(false);
    expect(score.unansweredKeys).toEqual(["passbooks"]);
  });

  it("removes a NOT_APPLICABLE question from the denominator and rescales", () => {
    const score = scoreAssessment(
      publish(),
      answers(["constitution", "YES"], ["committee", "YES"], ["passbooks", "NOT_APPLICABLE"])
    );

    expect(score.earnedPoints).toBe(4);
    expect(score.applicablePoints).toBe(4);
    expect(score.percentage).toBe(100);
    // Rescaled onto the full 6-point range, so the band stays comparable with
    // a group that had every question in play.
    expect(score.scaledPoints).toBe(6);
    expect(score.bandKey).toBe("strong");
    expect(score.complete).toBe(true);
  });

  it("bands a fractional score that falls between two whole-number bands", () => {
    // Bands are authored as 0-2 / 3-4 / 5-6, but rescaling around a
    // NOT_APPLICABLE question produces fractions. Scoring 1 of 2 applicable
    // points here rescales to 3.0 on the 6-point range; a half-credit answer
    // lands between the bounds outright. Neither may come back unbanded.
    const score = scoreAssessment(
      publish(),
      answers(
        ["constitution", "PARTIAL"],
        ["committee", "NOT_APPLICABLE"],
        ["passbooks", "NOT_APPLICABLE"]
      )
    );

    expect(score.earnedPoints).toBe(1);
    expect(score.applicablePoints).toBe(2);
    expect(score.scaledPoints).toBe(3);
    expect(score.bandKey).toBe("fair");
  });

  it("never leaves a score unbanded, at any fraction across the range", () => {
    // The bug this guards: matching on both bounds left every value between a
    // band's top and the next band's floor with no band at all.
    const snapshot = publish();
    for (let points = 0; points <= snapshot.maxPoints * 10; points += 1) {
      const band = bandForPoints(snapshot, points / 10);
      expect(band, `no band for ${points / 10} points`).not.toBeNull();
    }
  });

  it("handles every question being NOT_APPLICABLE without dividing by zero", () => {
    const score = scoreAssessment(
      publish(),
      answers(
        ["constitution", "NOT_APPLICABLE"],
        ["committee", "NOT_APPLICABLE"],
        ["passbooks", "NOT_APPLICABLE"]
      )
    );

    expect(score.applicablePoints).toBe(0);
    expect(score.percentage).toBe(0);
    expect(Number.isNaN(score.scaledPoints)).toBe(false);
  });

  it("keeps answers to unknown questions instead of throwing", () => {
    // A phone holding a cached older snapshot can send a key this template no
    // longer has. Rejecting the document would lose a whole field visit.
    const score = scoreAssessment(
      publish(),
      answers(["constitution", "YES"], ["retired_question", "YES"])
    );

    expect(score.unknownAnswerKeys).toEqual(["retired_question"]);
    expect(score.earnedPoints).toBe(2);
  });

  it("treats an unrecognised choice as unanswered rather than crediting it", () => {
    const score = scoreAssessment(publish(), answers(["constitution", "MAYBE"]));

    expect(score.earnedPoints).toBe(0);
    expect(score.unansweredKeys).toContain("constitution");
  });

  it("reports per-section subtotals joined on the section key", () => {
    const score = scoreAssessment(
      publish(),
      answers(["constitution", "YES"], ["committee", "NO"], ["passbooks", "YES"])
    );

    expect(score.sections.map((s) => [s.sectionKey, s.earnedPoints, s.percentage])).toEqual([
      ["governance", 2, 50],
      ["records", 2, 100]
    ]);
  });

  it("stamps the contract version onto every score", () => {
    const score = scoreAssessment(publish(), []);
    expect(score.scoringContractVersion).toBe(VISIT_ASSESSMENT_CONTRACT_VERSION);
    expect(score.templateVersion).toBe(1);
  });
});

describe("reproducibility", () => {
  it("re-scores a snapshot identically after the live template has moved on", () => {
    // The scenario the whole design exists for: an assessment scored under v1,
    // then IWL edits the form. The old visit must not change.
    const original = publish();
    const historical = scoreAssessment(
      original,
      answers(["constitution", "YES"], ["committee", "PARTIAL"], ["passbooks", "NO"])
    );

    // v2: a section is dropped, a question reweighted, bands redrawn.
    const v2 = draft();
    v2.sections.pop();
    questionOf(v2, "constitution").weight = 10;
    v2.bands = [
      { key: "weak", label: "Weak", minPoints: 0, maxPoints: 5 },
      { key: "strong", label: "Strong", minPoints: 6, maxPoints: 12 }
    ];
    const v2Result = validateAssessmentTemplate(v2);
    expect(v2Result.ok).toBe(true);

    const rescored = scoreAssessment(
      original,
      answers(["constitution", "YES"], ["committee", "PARTIAL"], ["passbooks", "NO"])
    );
    expect(rescored).toEqual(historical);
  });

  it("canonicalizes key order, so an identical template checksums identically", () => {
    const a = { version: 1, title: "x", sections: [{ key: "g", position: 0 }] };
    const b = { sections: [{ position: 0, key: "g" }], title: "x", version: 1 };

    expect(canonicalizeSnapshot(a)).toBe(canonicalizeSnapshot(b));
  });

  it("changes the canonical form when anything meaningful changes", () => {
    const snapshot = publish();
    const edited = publish();
    sectionOf(edited, "governance").questions[0]!.weight = 3;

    expect(canonicalizeSnapshot(edited)).not.toBe(canonicalizeSnapshot(snapshot));
  });

  it("orders sections and questions at publish so every reader agrees", () => {
    const source = draft();
    source.sections.reverse();
    sectionOf(source, "governance").questions.reverse();

    const snapshot = buildAssessmentSnapshot(source, {
      templateId: "tmpl_1",
      version: 1,
      maxPoints: 6
    });

    expect(snapshot.sections.map((s) => s.key)).toEqual(["governance", "records"]);
    expect(sectionOf(snapshot, "governance").questions.map((q) => q.key)).toEqual([
      "constitution",
      "committee"
    ]);
    expect(sectionOf(snapshot, "governance").questions.map((q) => q.position)).toEqual([0, 1]);
  });
});

describe("choices", () => {
  it("offers exactly the four field choices, with N/A excluded from scoring", () => {
    expect(ASSESSMENT_CHOICES.map((c) => c.key)).toEqual([
      "YES",
      "PARTIAL",
      "NO",
      "NOT_APPLICABLE"
    ]);
    expect(ASSESSMENT_CHOICES.find((c) => c.key === "NOT_APPLICABLE")?.credit).toBeNull();
  });
});
