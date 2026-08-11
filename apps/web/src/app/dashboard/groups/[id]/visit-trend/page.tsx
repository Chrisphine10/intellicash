"use client";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, TrendingUp } from "@/lib/theme-icons";
import { apiFetch, humanizeEnum } from "../../../../../lib/api";

/**
 * How a group has scored across visits.
 *
 * Sections line up on their KEY, never their title or position — that is the
 * only thing guaranteed stable when the scorecard is re-versioned. Where the
 * template changed between two visits the row carries a marker, because a step
 * in the line at that point may be the form moving rather than the group.
 */
interface OverallPoint {
  visitedAt: string | null;
  percentage: number | null;
  bandLabel: string | null;
  templateVersion: number;
  templateChanged: boolean;
}

interface SectionSeries {
  sectionKey: string;
  series: { visitedAt: string | null; percentage: number | null }[];
  change: number | null;
}

interface TrendResponse {
  group: { id: string; name: string; code: string };
  visits: number;
  overall: OverallPoint[];
  sections: SectionSeries[];
}

export default function GroupVisitTrendPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [data, setData] = useState<TrendResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiFetch<TrendResponse>(`/groups/${id}/visit-trend`)
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load the trend."))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) return <div className="loading-panel">Loading trend…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!data) return <div className="empty-state">No trend.</div>;

  const scored = data.overall.filter((point) => point.percentage !== null);
  const latest = scored.at(-1) ?? null;
  const first = scored[0] ?? null;
  const swing =
    scored.length >= 2 ? Math.round((latest!.percentage! - first!.percentage!) * 10) / 10 : null;
  const versionsSeen = new Set(data.overall.map((point) => point.templateVersion));

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href={`/dashboard/groups/${id}`}>
            <ArrowLeft size={17} />
            <span>{data.group.name}</span>
          </Link>
          <h2>Assessment trend</h2>
          <p>
            {data.visits === 0
              ? "No scorecard has been filled in for this group yet."
              : `${data.visits} scored visit${data.visits === 1 ? "" : "s"}.`}
          </p>
        </div>
        <TrendingUp size={22} />
      </header>

      {data.visits === 0 ? (
        <div className="empty-state">
          Nothing to chart. An agent scores the group on the phone during a visit; the first
          score appears here as soon as it syncs.
        </div>
      ) : (
        <>
          <section className="stat-grid">
            <article className="data-card">
              <span className="eyebrow">Latest score</span>
              <strong>{latest?.percentage === null || latest === null ? "—" : `${latest.percentage}%`}</strong>
              <span className="eyebrow">{latest?.bandLabel ?? "Not banded"}</span>
            </article>
            <article className="data-card">
              <span className="eyebrow">Change since first visit</span>
              <strong>{swing === null ? "—" : `${swing > 0 ? "+" : ""}${swing} pts`}</strong>
              <span className="eyebrow">
                {swing === null ? "Needs two scored visits" : "Percentage points, first to latest"}
              </span>
            </article>
            <article className="data-card">
              <span className="eyebrow">Scorecard versions</span>
              <strong>{versionsSeen.size}</strong>
              <span className="eyebrow">
                {versionsSeen.size > 1
                  ? "The questions changed during this history — compare with care."
                  : "The same questions throughout."}
              </span>
            </article>
          </section>

          <article className="data-card">
            <header>
              <h3>Visit by visit</h3>
            </header>
            <table className="data-table">
              <thead>
                <tr>
                  <th>Visit</th>
                  <th>Score</th>
                  <th>Band</th>
                  <th>Scorecard</th>
                </tr>
              </thead>
              <tbody>
                {[...data.overall].reverse().map((point, index) => (
                  <tr key={`${point.visitedAt ?? "unknown"}-${index}`}>
                    <td>
                      {point.visitedAt ? new Date(point.visitedAt).toLocaleDateString() : "Undated"}
                    </td>
                    <td>{point.percentage === null ? "—" : `${point.percentage}%`}</td>
                    <td>
                      {point.bandLabel ? (
                        <span className="pill">{point.bandLabel}</span>
                      ) : (
                        <span className="eyebrow">Not banded</span>
                      )}
                    </td>
                    <td>
                      v{point.templateVersion}
                      {point.templateChanged ? (
                        <div className="eyebrow">Questions changed at this visit</div>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </article>

          <article className="data-card">
            <header>
              <div>
                <h3>By section</h3>
                <span>
                  Sections are matched on their key, so one that only appears in some versions
                  still lines up on the visits where it was asked.
                </span>
              </div>
            </header>
            <table className="data-table">
              <thead>
                <tr>
                  <th>Section</th>
                  <th>Visits asked</th>
                  <th>First</th>
                  <th>Latest</th>
                  <th>Change</th>
                </tr>
              </thead>
              <tbody>
                {data.sections.map((section) => {
                  const scoredPoints = section.series.filter((point) => point.percentage !== null);
                  return (
                    <tr key={section.sectionKey}>
                      <td>{humanizeEnum(section.sectionKey.toUpperCase())}</td>
                      <td>{section.series.length}</td>
                      <td>
                        {scoredPoints[0]?.percentage === undefined
                          ? "—"
                          : `${scoredPoints[0]!.percentage}%`}
                      </td>
                      <td>
                        {scoredPoints.at(-1)?.percentage === undefined
                          ? "—"
                          : `${scoredPoints.at(-1)!.percentage}%`}
                      </td>
                      <td>
                        {section.change === null ? (
                          <span className="eyebrow">—</span>
                        ) : (
                          <span className={section.change < 0 ? "pill red" : "pill"}>
                            {section.change > 0 ? "+" : ""}
                            {section.change} pts
                          </span>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </article>
        </>
      )}
    </section>
  );
}
