import React from "react";
import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import {
  AssessmentRecord,
  type AssessmentRecordData
} from "../src/components/dashboard/assessment-record";

/**
 * The visit page shows what was collected, not only the total.
 *
 * A score of 46/92 tells a field officer nothing about what to do next. The
 * question answered "No", and the note the agent wrote beside it, is the
 * conversation to have at the following visit — and it was already being stored
 * and never shown.
 *
 * Prompts come from the breakdown the server froze at submission, so a visit
 * assessed on scorecard v1 still reads as v1 questions after v2 is published.
 */

function assessment(overrides: Partial<AssessmentRecordData> = {}): AssessmentRecordData {
  return {
    earnedPoints: 6,
    applicablePoints: 10,
    maxPoints: 12,
    percentage: 60,
    bandLabel: "Fair",
    complete: true,
    templateVersion: 1,
    breakdown: {
      unansweredKeys: [],
      sections: [
        {
          sectionKey: "records",
          title: "Record keeping",
          // Deliberately out of order in the array, to prove the render sorts
          // by position rather than trusting the order it was sent in.
          position: 2,
          earnedPoints: 4,
          applicablePoints: 6,
          percentage: 66.7,
          questions: [
            {
              questionKey: "passbooks",
              prompt: "Do members hold up-to-date passbooks?",
              weight: 2,
              choice: "PARTIAL",
              earnedPoints: 1,
              applicablePoints: 2,
              answered: true,
              excluded: false
            },
            {
              questionKey: "bank-recon",
              prompt: "Is the bank account reconciled monthly?",
              weight: 2,
              choice: "NOT_APPLICABLE",
              earnedPoints: 0,
              applicablePoints: 0,
              answered: true,
              excluded: true
            }
          ]
        },
        {
          sectionKey: "governance",
          title: "Governance",
          position: 1,
          earnedPoints: 2,
          applicablePoints: 4,
          percentage: 50,
          questions: [
            {
              questionKey: "constitution",
              prompt: "Does the group have a written constitution?",
              weight: 2,
              choice: "YES",
              earnedPoints: 2,
              applicablePoints: 2,
              answered: true,
              excluded: false
            },
            {
              questionKey: "elections",
              prompt: "Were officials elected in the last cycle?",
              weight: 2,
              choice: "NO",
              earnedPoints: 0,
              applicablePoints: 2,
              answered: true,
              excluded: false,
              note: "Chairlady has served three cycles unopposed"
            }
          ]
        }
      ]
    },
    ...overrides
  };
}

describe("the assessment record on a visit", () => {
  it("shows every question that was asked, not just the total", () => {
    render(<AssessmentRecord assessment={assessment()} />);

    expect(screen.getByText("Does the group have a written constitution?")).toBeInTheDocument();
    expect(screen.getByText("Were officials elected in the last cycle?")).toBeInTheDocument();
    expect(screen.getByText("Do members hold up-to-date passbooks?")).toBeInTheDocument();
  });

  it("shows the note the agent wrote beside a low answer", () => {
    // The most useful thing on the page, and previously stored and never
    // displayed: the reason behind a "No" is what the next visit acts on.
    render(<AssessmentRecord assessment={assessment()} />);

    expect(screen.getByText(/Chairlady has served three cycles unopposed/)).toBeInTheDocument();
  });

  it("groups questions under their section with the section's own score", () => {
    render(<AssessmentRecord assessment={assessment()} />);

    expect(screen.getByText("Governance")).toBeInTheDocument();
    expect(screen.getByText(/50% · 2 of 4 points/)).toBeInTheDocument();
  });

  it("orders sections by position, not by the order they arrived in", () => {
    render(<AssessmentRecord assessment={assessment()} />);

    const headings = screen.getAllByRole("heading", { level: 4 }).map((node) => node.textContent);
    expect(headings).toEqual(["Governance", "Record keeping"]);
  });

  it("distinguishes not-applicable from a failure", () => {
    render(<AssessmentRecord assessment={assessment()} />);

    // N/A left the denominator, so it is not a zero score and must not be
    // presented as one — neither in words nor in colour.
    const notApplicable = screen.getByText("Not applicable");
    expect(notApplicable).toBeInTheDocument();
    expect(notApplicable.className).not.toContain("red");

    expect(screen.getByText("No").className).toContain("red");
    expect(screen.getByText("Partly")).toBeInTheDocument();
  });

  it("shows a dash rather than zero points for a question that did not apply", () => {
    render(<AssessmentRecord assessment={assessment()} />);

    const row = screen.getByText("Is the bank account reconciled monthly?").closest("tr");
    expect(row?.textContent).toContain("—");
    expect(row?.textContent).not.toContain("0 / 2");
  });

  it("says a question was not answered rather than showing it as a No", () => {
    const partial = assessment();
    partial.breakdown.sections[1]!.questions[1] = {
      questionKey: "elections",
      prompt: "Were officials elected in the last cycle?",
      weight: 2,
      choice: null,
      earnedPoints: 0,
      applicablePoints: 2,
      answered: false,
      excluded: false
    };

    render(<AssessmentRecord assessment={partial} />);

    // Skipping a question is not the same as answering No, even though both
    // score zero. Showing it as No would put words in the agent's mouth.
    expect(screen.getByText("Not answered")).toBeInTheDocument();
  });

  it("warns that an incomplete assessment is not comparable", () => {
    render(
      <AssessmentRecord
        assessment={assessment({
          complete: false,
          breakdown: { ...assessment().breakdown, unansweredKeys: ["elections", "passbooks"] }
        })}
      />
    );

    // Unanswered scores zero but stays in the total, so the result reads lower
    // than a finished one. Showing the percentage without saying so invites a
    // false comparison against a complete assessment.
    expect(screen.getByText(/2 questions were left unanswered/)).toBeInTheDocument();
    expect(screen.getByText(/not comparable with a complete assessment/i)).toBeInTheDocument();
  });

  it("says nothing about completeness when the assessment is complete", () => {
    render(<AssessmentRecord assessment={assessment()} />);

    expect(screen.queryByText(/left unanswered/)).not.toBeInTheDocument();
  });

  it("handles a section where nothing applied", () => {
    render(
      <AssessmentRecord
        assessment={assessment({
          breakdown: {
            unansweredKeys: [],
            sections: [
              {
                sectionKey: "banking",
                title: "Banking",
                position: 1,
                earnedPoints: 0,
                applicablePoints: 0,
                percentage: null,
                questions: []
              }
            ]
          }
        })}
      />
    );

    // 0/0 rendered as "0%" would read as a total failure in a section that
    // simply did not apply to this group.
    expect(screen.getByText("Nothing in this section applied")).toBeInTheDocument();
    expect(screen.queryByText("0% · 0 of 0 points")).not.toBeInTheDocument();
  });
});
