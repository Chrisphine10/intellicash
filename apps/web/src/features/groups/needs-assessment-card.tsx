"use client";

import { useEffect, useState } from "react";
import { seesFieldComments } from "@intellicash/shared";
import { apiFetch, formatDate, formatKes } from "../../lib/api";
import { useCurrentUser } from "../../lib/current-user";

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
const yes = (value: string | undefined) => /^yes$/i.test((value ?? "").trim());

type Row = [string, string | null];

/** "7 of 10" over a section's Yes/No answers, and which ones were No. */
function inPlace(answers: Record<string, string> | undefined, pick: (label: string) => boolean = () => true) {
  const entries = Object.entries(answers ?? {}).filter(([label, value]) => pick(label) && /^(yes|no)$/i.test(value.trim()));
  return { have: entries.filter(([, value]) => yes(value)).length, total: entries.length, missing: entries.filter(([, value]) => !yes(value)).map(([label]) => label) };
}

const stripAvailable = (label: string) => label.replace(/\s+Available$/i, "");

/**
 * The group's baseline needs assessment, in the same groups as the programme's
 * baseline report: membership, savings and loans, governance, records,
 * linkages, training priorities and digital readiness. Figures are as the
 * group reported them on the day, which the card says.
 *
 * The field team's comments and the automatic quality checks are not shown
 * here: checks go to the System issues log for the team to verify, and the
 * full questionnaire is for IWL staff and the CBT who visited.
 */
export function NeedsAssessmentCard({ groupId }: { groupId: string }) {
  const user = useCurrentUser();
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
  const answers = baseline.answers ?? {};

  const governance = inPlace(answers.governance);
  const registers = inPlace(answers.records, (label) => /available$/i.test(label));
  const linked = Object.entries(answers.linkages ?? {})
    .filter(([, value]) => yes(value))
    .map(([label]) => label.replace(/^Linked to (an? )?/i, ""));
  const highNeeds = Object.entries(answers.training ?? {})
    .filter(([, value]) => value.trim().toUpperCase() === "H")
    .map(([label]) => label);

  const sections: Array<{ title: string; rows: Row[] }> = [
    {
      title: "Membership",
      rows: [
        ["Members", count(baseline.totalMembers)],
        ["Women", count(baseline.womenMembers)],
        ["Youth", count(baseline.youthMembers)],
        ["With a disability", count(baseline.membersWithDisability)],
        ["Cycles completed", count(baseline.completedCycles)]
      ]
    },
    {
      title: "Savings and loans (as reported)",
      rows: [
        ["Share value", money(baseline.shareValueCents)],
        ["Total savings", money(baseline.totalSavingsCents)],
        ["Loans out", money(baseline.loanPortfolioCents)],
        ["In arrears", money(baseline.arrearsCents)],
        ["Welfare fund", money(baseline.welfareBalanceCents)]
      ]
    },
    {
      title: "Governance and records",
      rows: [
        ["Governance practices", governance.total ? `${governance.have} of ${governance.total}` : null],
        ["Registers available", registers.total ? `${registers.have} of ${registers.total}` : null],
        ["Financial linkages", linked.length ? String(linked.length) : answers.linkages ? "None" : null]
      ]
    },
    {
      title: "Digital readiness",
      rows: [
        ["Smartphones", count(baseline.smartphoneMembers)],
        ["Literacy (estimated)", baseline.literacyPct === null ? null : `${baseline.literacyPct}%`],
        ["Digital champion", baseline.digitalChampion === null ? null : baseline.digitalChampion ? "Yes" : "No"]
      ]
    }
  ];
  const groups = sections
    .map((group) => ({ ...group, rows: group.rows.filter(([, value]) => value !== null && value !== "") }))
    .filter((group) => group.rows.length > 0);

  const gaps = [
    governance.missing.length ? `Governance: ${governance.missing.join(", ")}` : null,
    registers.missing.length ? `Registers: ${registers.missing.map(stripAvailable).join(", ")}` : null
  ].filter(Boolean);

  return (
    <section className="data-card needs-assessment">
      <header>
        <div>
          <h3>Baseline needs assessment</h3>
          <p>
            {formatDate(baseline.assessedOn)} · as the group reported it, not verified
            {rows.length > 1 ? ` · ${rows.length - 1} later assessment${rows.length > 2 ? "s" : ""}` : ""}
          </p>
        </div>
        {baseline.scorecard ? (
          <span className="pill" title={`Scored on ${baseline.scorecard.asked} of ${baseline.scorecard.total} scorecard questions`}>
            Scorecard {Math.round(baseline.scorecard.percentage)}% · {baseline.scorecard.asked} of {baseline.scorecard.total} asked
          </span>
        ) : null}
      </header>

      <div className="needs-groups">
        {groups.map((group) => (
          <div className="needs-group" key={group.title}>
            <h4>{group.title}</h4>
            {group.rows.map(([label, value]) => (
              <div className="row" key={label}>
                <span>{label}</span>
                <span>{value}</span>
              </div>
            ))}
          </div>
        ))}
      </div>

      {gaps.length > 0 ? (
        <p className="card-note">
          <strong>Not yet in place:</strong> {gaps.join(" · ")}
        </p>
      ) : null}
      {highNeeds.length > 0 ? (
        <p className="card-note">
          <strong>Training priorities:</strong> {highNeeds.join(", ")}
        </p>
      ) : null}

      {seesFieldComments(user?.role) ? (
        <details className="quiet-details">
          <summary>Full questionnaire{baseline.fieldOfficer ? ` · by ${baseline.fieldOfficer}` : ""}</summary>
          {Object.entries(answers).map(([section, list]) => (
            <div className="needs-assessment-section" key={section}>
              <h4>{SECTION_TITLES[section] ?? section}</h4>
              <dl>
                {Object.entries(list).map(([label, value]) => (
                  <div key={label}>
                    <dt>{label}</dt>
                    <dd>{value}</dd>
                  </div>
                ))}
              </dl>
            </div>
          ))}
        </details>
      ) : null}
    </section>
  );
}
