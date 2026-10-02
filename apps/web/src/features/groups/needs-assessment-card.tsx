"use client";

import { useEffect, useState } from "react";
import { apiFetch, formatDate, formatKes } from "../../lib/api";

interface NeedsAssessment {
  id: string;
  baseline: boolean;
  assessedOn: string;
  fieldOfficer: string | null;
  totalMembers: number | null;
  womenMembers: number | null;
  youthMembers: number | null;
  membersWithDisability: number | null;
  completedCycles: number | null;
  currentCycle: number | null;
  shareValueCents: number | null;
  totalSavingsCents: number | null;
  loanPortfolioCents: number | null;
  arrearsCents: number | null;
  par30Cents: number | null;
  welfareBalanceCents: number | null;
  smartphoneMembers: number | null;
  literacyPct: number | null;
  digitalChampion: boolean | null;
  answers: Record<string, Record<string, string>>;
  qualityFlags: string[];
  scorecard: { percentage: number; band: string | null; asked: number; total: number } | null;
}

const SECTION_TITLES: Record<string, string> = {
  profile: "Profile",
  leadership: "Leadership",
  governance: "Governance",
  finances: "Reported finances",
  markets: "Markets and value chains",
  records: "Records inspection",
  training: "Training needs (H = high)",
  linkages: "Financial and other linkages",
  digital: "Digital readiness",
  observations: "Observations",
  signOff: "Sign-off"
};

const money = (cents: number | null) => (cents === null ? null : formatKes(cents));
const count = (value: number | null) => (value === null ? null : String(value));

/**
 * The group's needs assessments — the first is its baseline. Everything here
 * is as the group reported it on the day, which the card says, and checks the
 * enumerator's figures failed are listed rather than hidden.
 */
export function NeedsAssessmentCard({ groupId }: { groupId: string }) {
  const [rows, setRows] = useState<NeedsAssessment[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let live = true;
    apiFetch<NeedsAssessment[]>(`/groups/${groupId}/needs-assessments`)
      .then((data) => live && setRows(data))
      .catch((e) => live && setError(e instanceof Error ? e.message : "Could not load the needs assessment."));
    return () => {
      live = false;
    };
  }, [groupId]);

  if (error) return null;
  if (!Array.isArray(rows) || rows.length === 0) return null;
  const baseline = rows[0]!;

  const facts: Array<[string, string | null]> = [
    ["Assessed", formatDate(baseline.assessedOn)],
    ["Field officer", baseline.fieldOfficer],
    ["Members", count(baseline.totalMembers)],
    ["Women", count(baseline.womenMembers)],
    ["Youth", count(baseline.youthMembers)],
    ["With a disability", count(baseline.membersWithDisability)],
    ["Current cycle", count(baseline.currentCycle)],
    ["Share value", money(baseline.shareValueCents)],
    ["Total savings", money(baseline.totalSavingsCents)],
    ["Loans out", money(baseline.loanPortfolioCents)],
    ["In arrears", money(baseline.arrearsCents)],
    ["At risk > 30 days", money(baseline.par30Cents)],
    ["Welfare fund", money(baseline.welfareBalanceCents)],
    ["Members with smartphones", count(baseline.smartphoneMembers)],
    ["Literacy (estimated)", baseline.literacyPct === null ? null : `${baseline.literacyPct}%`],
    ["Digital champion", baseline.digitalChampion === null ? null : baseline.digitalChampion ? "Yes" : "No"]
  ];
  const highNeeds = Object.entries(baseline.answers.training ?? {})
    .filter(([, value]) => value.toUpperCase() === "H")
    .map(([label]) => label);

  return (
    <section className="data-card needs-assessment">
      <header>
        <div>
          <h3>Baseline needs assessment</h3>
          <p>
            {formatDate(baseline.assessedOn)} · as the group reported it on the day, not verified
            {rows.length > 1 ? ` · ${rows.length - 1} later assessment${rows.length > 2 ? "s" : ""}` : ""}
          </p>
        </div>
        {baseline.scorecard ? (
          <span className="pill" title={`Scored on ${baseline.scorecard.asked} of ${baseline.scorecard.total} scorecard questions`}>
            {Math.round(baseline.scorecard.percentage)}% · {baseline.scorecard.asked} of {baseline.scorecard.total} questions
          </span>
        ) : null}
      </header>

      {baseline.qualityFlags.length > 0 ? (
        <div className="dashboard-notice">
          <strong>Check with the field team:</strong> {baseline.qualityFlags.join("; ")}.
        </div>
      ) : null}

      <div className="card-body fact-grid">
        {facts
          .filter(([, value]) => value !== null && value !== "")
          .map(([label, value]) => (
            <div className="fact" key={label}>
              <span className="label">{label}</span>
              <span className="value">{value}</span>
            </div>
          ))}
      </div>

      {highNeeds.length > 0 ? (
        <p className="card-note">
          <strong>High training needs:</strong> {highNeeds.join(", ")}
        </p>
      ) : null}

      <details className="quiet-details">
        <summary>Every answer</summary>
        {Object.entries(baseline.answers).map(([section, answers]) => (
          <div className="needs-assessment-section" key={section}>
            <h4>{SECTION_TITLES[section] ?? section}</h4>
            <dl>
              {Object.entries(answers).map(([label, value]) => (
                <div key={label}>
                  <dt>{label}</dt>
                  <dd>{value}</dd>
                </div>
              ))}
            </dl>
          </div>
        ))}
      </details>
    </section>
  );
}
