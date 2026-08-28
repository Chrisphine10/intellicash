"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, BarChart3 } from "@/lib/theme-icons";
import { apiFetch, formatDateTime } from "../../../../lib/api";

/**
 * Monitoring, evaluation, accountability and learning.
 *
 * The field-visit report answers "how much did we do". This answers "did any of
 * it change anything" — the question a funder asks and the one a programme
 * manager needs before moving agents around.
 *
 * Three things this page does that a dashboard usually does not, and each is
 * here because leaving it out is how a report ends up stating something untrue:
 *
 * 1. **Every figure shows its denominator.** "62% improved" is not a finding
 *    until it says 62% of what.
 * 2. **Caveats sit inside the card, not in a footnote.** A small sample or a
 *    minority coverage warning must survive being screenshotted.
 * 3. **Movement is coloured by the indicator's own direction.** Days-to-meet
 *    falling is an improvement; a chart that paints every rise green says the
 *    opposite of what the data does.
 *
 * The server sends the definitions with the figures, so nothing here decides
 * what an indicator means.
 */
interface Definition {
  key: string;
  name: string;
  definition: string;
  denominator: string;
  level: "ACTIVITY" | "OUTPUT" | "OUTCOME" | "DATA_QUALITY";
  unit: "COUNT" | "PERCENT" | "CENTS" | "SCORE" | "LADDER_STEP" | "DAYS";
  direction: string;
}

interface Change {
  baseline: number | null;
  latest: number | null;
  change: number | null;
  percentChange: number | null;
  pairedUnits: number;
  observedUnits: number;
  eligibleUnits: number;
  coveragePercent: number | null;
  improved: number;
  unchanged: number;
  declined: number;
  excludedForComparability: number;
  isSmallSample: boolean;
  notes: string[];
}

interface Share {
  numerator: number;
  denominator: number;
  percent: number | null;
  isSmallSample: boolean;
  notes: string[];
}

interface Indicator {
  definition: Definition;
  change?: Change;
  share?: Share;
  value?: number | null;
  movement: "IMPROVED" | "WORSENED" | "FLAT" | "UNKNOWN";
}

interface MealReport {
  contractVersion: string;
  caveat: string;
  generatedAt: string;
  scope: { groups: number; freshnessDays: number };
  indicators: Indicator[];
  assessmentMovement: {
    improved: number;
    unchanged: number;
    declined: number;
    notComparable: number;
    noBaseline: number;
    neverAssessed: number;
  };
  supportNeeds: {
    total: number;
    met: number;
    open: number;
    medianDaysToMeet: number | null;
    ranked: { key: string; title: string; category: string; raised: number; met: number; high: number }[];
    byCategory: { category: string; raised: number; met: number }[];
  };
  mentorship: {
    sessions: number;
    topics: { key: string; title: string; sessions: number; minutes: number }[];
    ratingsTotal: number;
    ratingsFromGroup: number;
    averageFromGroup: number | null;
  };
  methodology: Definition[];
}

const LEVEL_TITLE: Record<Definition["level"], string> = {
  ACTIVITY: "What the programme did",
  OUTPUT: "What that produced",
  OUTCOME: "What changed",
  DATA_QUALITY: "How far these figures can be trusted"
};

const LEVEL_BLURB: Record<Definition["level"], string> = {
  ACTIVITY: "Effort. These rise with the size of the team, not with results.",
  OUTPUT: "Things now in place because of that effort.",
  OUTCOME:
    "Change measured on the same groups at both ends. A group with only one reading has no baseline and is counted in coverage, never in change.",
  DATA_QUALITY:
    "Read this before quoting anything above. A mentorship average built entirely from agents rating their own coaching looks identical to one given by groups."
};

export default function MealReportPage() {
  const [report, setReport] = useState<MealReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showMethod, setShowMethod] = useState(false);

  useEffect(() => {
    apiFetch<MealReport>("/reports/meal")
      .then(setReport)
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load the MEAL report."))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="loading-panel">Loading impact figures…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!report) return <div className="empty-state">No figures.</div>;

  const levels: Definition["level"][] = ["OUTCOME", "OUTPUT", "ACTIVITY", "DATA_QUALITY"];

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href="/dashboard/reports">
            <ArrowLeft size={15} />
            Back to reports
          </Link>
          <h2>
            <BarChart3 size={19} /> Impact
          </h2>
          <p className="eyebrow">
            {report.scope.groups} group{report.scope.groups === 1 ? "" : "s"} in view.
          </p>
        </div>
      </header>

      {/* The caveat leads rather than closes. Put at the bottom it gets cropped
          out of the screenshot that reaches the funder. */}
      <div className="notice">{report.caveat}</div>

      {/* Outcomes first: the average can hide a programme where half the groups
          are going backwards, so the direction of travel is shown beside it. */}
      <article className="data-card">
        <header>
          <div>
            <h3>Where the groups are heading</h3>
            <span>Direction of travel, which an average hides</span>
          </div>
        </header>
        <div className="card-body">
        <div className="fact-grid">
          <Movement label="Improved" value={report.assessmentMovement.improved} tone="improved" />
          <Movement label="No change" value={report.assessmentMovement.unchanged} tone="flat" />
          <Movement label="Declined" value={report.assessmentMovement.declined} tone="worsened" />
          <Movement
            label="Not comparable"
            value={report.assessmentMovement.notComparable}
            tone="unknown"
          />
          <Movement label="No baseline yet" value={report.assessmentMovement.noBaseline} tone="unknown" />
          <Movement
            label="Never assessed"
            value={report.assessmentMovement.neverAssessed}
            tone="unknown"
          />
        </div>
        <p className="card-note">
          &ldquo;Not comparable&rdquo; means the group was assessed on two different
          scorecard versions. Those two numbers are not the same measurement, so they are
          excluded rather than averaged — counting them would read a re-worded question as
          the group improving.
        </p>
        </div>
      </article>

      {levels.map((level) => {
        const rows = report.indicators.filter((row) => row.definition.level === level);
        if (rows.length === 0) return null;

        return (
          <article className="data-card" key={level}>
            <header>
              <div>
                <h3>{LEVEL_TITLE[level]}</h3>
                <span>{LEVEL_BLURB[level]}</span>
              </div>
              <span className="pill blue">{rows.length}</span>
            </header>
            <div className="card-body">
              <div className="meal-grid">
                {rows.map((row) => (
                  <IndicatorCard indicator={row} key={row.definition.key} />
                ))}
              </div>
            </div>
          </article>
        );
      })}

      <article className="data-card">
        <header>
          <div>
            <h3>What groups are asking for</h3>
            <span>Ranked by how often it is named</span>
          </div>
          <span className="pill">{report.supportNeeds.total}</span>
        </header>
        <p className="card-note" style={{ padding: "14px 20px 0" }}>
          This list is only possible because needs are
          recorded against a fixed list rather than typed as sentences — the same need
          written twelve ways cannot be counted.
        </p>
        {report.supportNeeds.total === 0 ? (
          <p className="empty-state">No support needs recorded yet.</p>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>Need</th>
                <th>Category</th>
                <th>Asked for</th>
                <th>High priority</th>
                <th>Met</th>
              </tr>
            </thead>
            <tbody>
              {report.supportNeeds.ranked.map((need) => (
                <tr key={need.key}>
                  <td>{need.title}</td>
                  <td>{need.category.toLowerCase()}</td>
                  <td>{need.raised}</td>
                  <td>{need.high}</td>
                  <td>
                    {need.met} of {need.raised}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </article>

      <article className="data-card">
        <header>
          <div>
            <h3>What agents coached on</h3>
            <span>Topics delivered, and who rated them</span>
          </div>
          <span className="pill blue">{report.mentorship.sessions}</span>
        </header>
        {report.mentorship.sessions === 0 ? (
          <p className="empty-state">No mentorship recorded yet.</p>
        ) : (
          <>
            <p className="card-note" style={{ padding: "14px 20px 0" }}>
              {report.mentorship.sessions} session
              {report.mentorship.sessions === 1 ? "" : "s"} ·{" "}
              {report.mentorship.ratingsFromGroup} of {report.mentorship.ratingsTotal} ratings
              came from the group itself.
            </p>
            <table className="data-table">
              <thead>
                <tr>
                  <th>Topic</th>
                  <th>Sessions</th>
                  <th>Time spent</th>
                </tr>
              </thead>
              <tbody>
                {report.mentorship.topics.map((topic) => (
                  <tr key={topic.key}>
                    <td>{topic.title}</td>
                    <td>{topic.sessions}</td>
                    <td>{topic.minutes === 0 ? "not recorded" : `${topic.minutes} min`}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </>
        )}
      </article>

      <article className="data-card">
        <header>
          <div>
            <h3>How these figures are worked out</h3>
            <span>Definitions travel with the figures</span>
          </div>
          <button
            className="button secondary"
            onClick={() => setShowMethod((current) => !current)}
            type="button"
          >
            {showMethod ? "Hide" : "Show"}
          </button>
        </header>
        <p className="card-note" style={{ padding: "14px 20px 0" }}>
          An indicator without a written definition gets redefined by whoever reads it
          next, and two people then quote the same name for two different things.
        </p>
        {showMethod ? (
          <table className="data-table">
            <thead>
              <tr>
                <th>Indicator</th>
                <th>What it counts</th>
                <th>Out of</th>
              </tr>
            </thead>
            <tbody>
              {report.methodology.map((definition) => (
                <tr key={definition.key}>
                  <td>{definition.name}</td>
                  <td>{definition.definition}</td>
                  <td>{definition.denominator || "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : null}
        <p className="meal-note">
          Method version {report.contractVersion} · figures as at{" "}
          {formatDateTime(report.generatedAt)} · an assessment counts as current
          for {report.scope.freshnessDays} days.
        </p>
      </article>
    </section>
  );
}

function Movement({ label, value, tone }: { label: string; value: number; tone: string }) {
  return (
    <div className="fact">
      <span className="label">{label}</span>
      <span className={`value meal-movement ${tone}`}>{value}</span>
    </div>
  );
}

function IndicatorCard({ indicator }: { indicator: Indicator }) {
  const { definition, change, share, movement } = indicator;

  return (
    <div className="meal-card">
      <h4>{definition.name}</h4>

      <span className="meal-value">{headline(indicator)}</span>

      {change && change.baseline !== null ? (
        <span className="meal-baseline">
          from {format(change.baseline, definition.unit)} at baseline
        </span>
      ) : null}

      {share ? (
        <span className="meal-baseline">
          {share.numerator} of {share.denominator} {definition.denominator}
        </span>
      ) : null}

      {change && change.change !== null ? (
        <span className={`meal-movement ${movement.toLowerCase()}`}>
          {change.change > 0 ? "▲" : change.change < 0 ? "▼" : "—"}{" "}
          {format(Math.abs(change.change), definition.unit)}
          {change.percentChange === null ? "" : ` (${Math.abs(change.percentChange)}%)`}
          {" · "}
          {change.pairedUnits} compared
        </span>
      ) : null}

      {/* Caveats live inside the card so they survive a screenshot. */}
      {(change?.notes ?? share?.notes ?? []).map((note) => (
        <p className="meal-note" key={note}>
          {note}
        </p>
      ))}

      <p className="definition">{definition.definition}</p>
    </div>
  );
}

function headline(indicator: Indicator): string {
  const { definition, change, share, value } = indicator;

  if (share) return share.percent === null ? "Not measured" : `${share.percent}%`;
  if (change) {
    return change.latest === null ? "No baseline yet" : format(change.latest, definition.unit);
  }
  if (value === null || value === undefined) return "Not measured";
  return format(value, definition.unit);
}

/**
 * Formats by the indicator's own unit.
 *
 * Cents become shillings here and nowhere else, matching the rest of the
 * console: amounts travel as cents so they cannot pick up a rounding error on
 * the way.
 */
function format(value: number, unit: Definition["unit"]): string {
  if (unit === "CENTS") {
    return `KSh ${(value / 100).toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
  }
  if (unit === "PERCENT") return `${value}%`;
  if (unit === "DAYS") return `${value} day${value === 1 ? "" : "s"}`;
  if (unit === "SCORE") return `${value} / 5`;
  if (unit === "LADDER_STEP") return `${value} step${Math.abs(value) === 1 ? "" : "s"}`;
  return value.toLocaleString();
}
