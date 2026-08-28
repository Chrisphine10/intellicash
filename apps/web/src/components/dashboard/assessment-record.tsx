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

/**
 * Weakest first, and a section where nothing applied goes last.
 *
 * The template's own order is the order an agent answers in, which is right on
 * the phone and wrong here: a reviewer is looking for what went badly, and
 * putting Governance first every time makes them hunt for it.
 */
function bySeverityThenOrder(left: SectionResult, right: SectionResult): number {
  if (left.percentage === null && right.percentage === null) {
    return left.position - right.position;
  }
  if (left.percentage === null) return 1;
  if (right.percentage === null) return -1;
  if (left.percentage !== right.percentage) return left.percentage - right.percentage;
  return left.position - right.position;
}

/** Bar colour, on the same thresholds the scorecard's own bands use. */
function sectionTone(percentage: number): string {
  if (percentage >= 80) return "score-bar-good";
  if (percentage >= 60) return "score-bar-fair";
  if (percentage >= 40) return "score-bar-warn";
  return "score-bar-poor";
}

/** How many answers in a section are worth talking about at the next visit. */
function countConcerns(section: SectionResult): number {
  return section.questions.filter(
    (question) => question.choice === "NO" || question.choice === "PARTIAL"
  ).length;
}

export function AssessmentRecord({ assessment }: { assessment: AssessmentRecordData }) {
  const unanswered = assessment.breakdown.unansweredKeys.length;

  return (
    <article className="data-card">
      <header>
        <div>
          <h3>Assessment</h3>
          <span>Scorecard v{assessment.templateVersion}, as it was worded then</span>
        </div>
        {assessment.bandLabel ? (
          <span className={assessment.complete ? "pill" : "pill gold"}>
            {assessment.bandLabel}
          </span>
        ) : null}
      </header>

      <div className="card-body">
        <div className="fact-grid">
          <div className="fact">
            <span className="label">Score</span>
            <span className="value">
              <span className="metric-value small">{assessment.percentage}%</span>
            </span>
          </div>
          <div className="fact">
            <span className="label">Points</span>
            <span className="value">
              {assessment.earnedPoints} of {assessment.applicablePoints} that applied
            </span>
          </div>
          <div className="fact">
            <span className="label">Full scale</span>
            <span className="value">{assessment.maxPoints}</span>
          </div>
        </div>

        <span
          aria-hidden="true"
          className={`score-bar ${sectionTone(assessment.percentage)}`}
        >
          <span
            className="score-bar-fill"
            style={{ width: `${Math.max(0, Math.min(100, assessment.percentage))}%` }}
          />
        </span>

        {assessment.complete ? null : (
          <p className="notice warning">
            {unanswered} question{unanswered === 1 ? " was" : "s were"} left unanswered. Those
            score zero and stay in the total, so this result reads lower than a finished one —
            it is not comparable with a complete assessment.
          </p>
        )}

        {assessment.bandGuidance ? (
          <p className="card-note">{assessment.bandGuidance}</p>
        ) : null}
      </div>

      {/* Section by section, then question by question.

          The section that scored 40% is the conversation to have next time, and
          a single total hides it. But seven sections at six to eight questions
          each is 46 rows of table, which buried everything below this card and
          made the weak section just as hard to find as it was in the total.

          So the sections lead with a bar, WEAKEST FIRST, and the questions sit
          behind a disclosure. Nothing is removed — a reader who wants the
          wording of every question still has it, one click away. */}
      <div className="assessment-sections">
        <p className="card-note">
          Weakest section first. Open one to read the questions as they were worded
          at the time.
        </p>
        {[...assessment.breakdown.sections]
          .sort(bySeverityThenOrder)
          .map((section) => (
            <section className="assessment-section" key={section.sectionKey}>
              <header className="assessment-section-head">
                <h4>{section.title}</h4>
                {/* Not `.eyebrow`: that is a bright green uppercase label, which
                    is the wrong voice for a score and the wrong colour for a
                    low one. */}
                <span className="section-score">
                  {section.percentage === null
                    ? "Nothing in this section applied"
                    : `${Math.round(section.percentage)}% · ${section.earnedPoints} of ${section.applicablePoints} points`}
                </span>
              </header>

              {section.percentage === null ? null : (
                <span
                  aria-hidden="true"
                  className={`score-bar ${sectionTone(section.percentage)}`}
                >
                  <span
                    className="score-bar-fill"
                    style={{ width: `${Math.max(0, Math.min(100, section.percentage))}%` }}
                  />
                </span>
              )}

              <details className="assessment-questions">
                <summary>
                  {section.questions.length} question
                  {section.questions.length === 1 ? "" : "s"}
                  {countConcerns(section) > 0
                    ? ` · ${countConcerns(section)} answered No or Partly`
                    : ""}
                </summary>

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
                            <p className="card-note">Agent&apos;s note: {question.note}</p>
                          ) : null}
                        </td>
                        <td>
                          {question.answered ? (
                            <span className={choicePill(question.choice)}>
                              {CHOICE_LABEL[question.choice ?? ""] ?? "—"}
                            </span>
                          ) : (
                            <span className="unanswered-marker">Not answered</span>
                          )}
                        </td>
                        <td>
                          {question.excluded
                            ? "—"
                            : `${question.earnedPoints} / ${question.weight}`}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </details>
            </section>
          ))}
      </div>
    </article>
  );
}
