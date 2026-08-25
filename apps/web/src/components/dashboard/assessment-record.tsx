"use client";

import React from "react";

/**
 * What an assessment actually recorded, question by question.
 *
 * A score of 46/92 tells a field officer nothing about what to do next. The
 * question answered "No", and the note the agent wrote beside it, is the
 * conversation to have at the following visit — and it was already being stored
 * and never shown.
 *
 * Everything here comes from the breakdown the server froze at submission, so a
 * visit assessed on scorecard v1 still reads as v1 questions after v2 is
 * published. Rendering from the live template instead would silently re-word
 * historical records, which is the failure the stored breakdown exists to
 * prevent.
 *
 * A component rather than inline JSX because the page it sits on reads its route
 * params with React's `use`, and a suspending component cannot be rendered in a
 * test without the assertions turning into a fight with the framework.
 */
export interface QuestionResult {
  questionKey: string;
  prompt: string;
  weight: number;
  choice: "YES" | "PARTIAL" | "NO" | "NOT_APPLICABLE" | null;
  earnedPoints: number;
  applicablePoints: number;
  answered: boolean;
  excluded: boolean;
  note?: string;
}

export interface SectionResult {
  sectionKey: string;
  title: string;
  position: number;
  earnedPoints: number;
  applicablePoints: number;
  /** Null when every question in the section was marked not applicable. */
  percentage: number | null;
  questions: QuestionResult[];
}

export interface AssessmentRecordData {
  earnedPoints: number;
  applicablePoints: number;
  maxPoints: number;
  percentage: number;
  bandLabel: string | null;
  bandGuidance?: string;
  complete: boolean;
  templateVersion: number;
  breakdown: {
    sections: SectionResult[];
    unansweredKeys: string[];
  };
}

const CHOICE_LABEL: Record<string, string> = {
  YES: "Yes",
  PARTIAL: "Partly",
  NO: "No",
  NOT_APPLICABLE: "Not applicable"
};

/**
 * Colour carries meaning, so it is decided once.
 *
 * "Not applicable" is deliberately neutral rather than red: it removed the
 * question from the denominator, so it is not a failure and must not read as
 * one.
 */
function choicePill(choice: QuestionResult["choice"]): string {
  if (choice === "YES") return "pill";
  if (choice === "PARTIAL") return "pill gold";
  if (choice === "NO") return "pill red";
  return "pill blue";
}

export function AssessmentRecord({ assessment }: { assessment: AssessmentRecordData }) {
  const unanswered = assessment.breakdown.unansweredKeys.length;

  return (
    <article className="data-card">
      <h3>Assessment</h3>
      <p className="metric-value">
        {assessment.earnedPoints} / {assessment.maxPoints}
        {assessment.bandLabel ? ` · ${assessment.bandLabel}` : ""}
      </p>
      <p className="eyebrow">
        {assessment.percentage}% · scorecard v{assessment.templateVersion}
      </p>

      {assessment.complete ? null : (
        <p className="notice warning">
          {unanswered} question{unanswered === 1 ? " was" : "s were"} left unanswered. Those
          score zero and stay in the total, so this result reads lower than a finished one —
          it is not comparable with a complete assessment.
        </p>
      )}

      {assessment.bandGuidance ? <p className="eyebrow">{assessment.bandGuidance}</p> : null}

      {/* Section by section, then question by question. The section that scored
          40% is the conversation to have next time; a single total hides it. */}
      {[...assessment.breakdown.sections]
        .sort((left, right) => left.position - right.position)
        .map((section) => (
          <section className="assessment-section" key={section.sectionKey}>
            <header className="assessment-section-head">
              <h4>{section.title}</h4>
              <span className="eyebrow">
                {section.percentage === null
                  ? "Nothing in this section applied"
                  : `${Math.round(section.percentage)}% · ${section.earnedPoints} of ${section.applicablePoints} points`}
              </span>
            </header>

            <table className="data-table">
              <thead>
                <tr>
                  <th>Question</th>
                  <th>Answer</th>
                  <th>Points</th>
                </tr>
              </thead>
              <tbody>
                {section.questions.map((question) => (
                  <tr key={question.questionKey}>
                    <td>
                      {/* The prompt as it was worded at the time. */}
                      {question.prompt}
                      {question.note ? (
                        <p className="eyebrow">Agent&apos;s note: {question.note}</p>
                      ) : null}
                    </td>
                    <td>
                      {question.answered ? (
                        <span className={choicePill(question.choice)}>
                          {CHOICE_LABEL[question.choice ?? ""] ?? "—"}
                        </span>
                      ) : (
                        <span className="eyebrow">Not answered</span>
                      )}
                    </td>
                    <td>
                      {question.excluded ? "—" : `${question.earnedPoints} / ${question.weight}`}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>
        ))}
    </article>
  );
}
