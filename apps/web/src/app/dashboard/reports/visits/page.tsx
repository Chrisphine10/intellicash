"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, BarChart3 } from "@/lib/theme-icons";
import { apiFetch, formatDate } from "../../../../lib/api";

/**
 * What the visits programme is actually doing.
 *
 * Coverage and staleness are two numbers on purpose. A group visited once, a
 * year ago, is not covered in any useful sense — but it is not unvisited
 * either, and one percentage cannot say both.
 */
interface GroupRef {
  id: string;
  name: string;
  code: string;
}

interface StaleGroup extends GroupRef {
  county?: string | null;
  lastVisitAt: string | null;
  daysSinceVisit: number | null;
}

interface VisitsReport {
  coverage: { groups: number; visited: number; neverVisited: number; percentVisited: number };
  bands: { band: string; count: number }[];
  actions: {
    total: number;
    open: number;
    overdue: number;
    dueSoon: number;
    done: number;
    worstDaysOverdue: number;
  };
  documents: {
    total: number;
    verified: number;
    missing: number;
    expired: number;
    needsAttention: number;
    percentVerified: number;
  };
  neverVisited: GroupRef[];
  staleGroups: StaleGroup[];
}

export default function VisitsReportPage() {
  const [data, setData] = useState<VisitsReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiFetch<VisitsReport>("/reports/visits")
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load the report."))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="loading-panel">Loading visit coverage…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!data) return <div className="empty-state">No report.</div>;

  const totalBanded = data.bands.reduce((sum, band) => sum + band.count, 0);

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href="/dashboard/reports">
            <ArrowLeft size={17} />
            <span>Reports</span>
          </Link>
          <h2>Field visits</h2>
          <p>
            Scoped to what you can see: an agent gets their own caseload, an admin the whole
            programme.
          </p>
        </div>
        <BarChart3 size={22} />
      </header>

      <section className="stat-grid">
        <article className="data-card">
          <span className="eyebrow">Groups visited</span>
          <strong>
            {data.coverage.visited} / {data.coverage.groups}
          </strong>
          <span className="eyebrow">{data.coverage.percentVisited}% have ever been visited</span>
        </article>
        <article className="data-card">
          <span className="eyebrow">Never visited</span>
          <strong>{data.coverage.neverVisited}</strong>
          <span className="eyebrow">No visit record at all</span>
        </article>
        <article className="data-card">
          <span className="eyebrow">Not seen in 90 days</span>
          <strong>{data.staleGroups.length}</strong>
          <span className="eyebrow">Visited once, but a long time ago</span>
        </article>
        <article className="data-card">
          <span className="eyebrow">Actions overdue</span>
          <strong>{data.actions.overdue}</strong>
          <span className="eyebrow">
            {data.actions.open} open
            {data.actions.worstDaysOverdue > 0
              ? ` · worst is ${data.actions.worstDaysOverdue} days late`
              : ""}
          </span>
        </article>
        <article className="data-card">
          <span className="eyebrow">Documents verified</span>
          <strong>{data.documents.percentVerified}%</strong>
          <span className="eyebrow">
            {data.documents.needsAttention} need attention · {data.documents.expired} expired
          </span>
        </article>
      </section>

      <section className="two-column">
        <article className="data-card">
          <header>
            <div>
              <h3>How groups scored</h3>
              <span>{totalBanded} scored assessment{totalBanded === 1 ? "" : "s"}</span>
            </div>
          </header>
          {data.bands.length === 0 ? (
            <div className="empty-state">No scorecard has been filled in yet.</div>
          ) : (
            <div className="list">
              {data.bands.map((band) => (
                <div className="list-row" key={band.band}>
                  <div>
                    <strong>{band.band}</strong>
                    <span>
                      {totalBanded === 0 ? 0 : Math.round((band.count / totalBanded) * 100)}% of
                      assessments
                    </span>
                  </div>
                  <span className="pill">{band.count}</span>
                </div>
              ))}
            </div>
          )}
        </article>

        <article className="data-card">
          <header>
            <div>
              <h3>Action plan</h3>
              <span>Overdue is worked out from the due date on every read, so it cannot go stale.</span>
            </div>
          </header>
          <div className="list">
            <div className="list-row">
              <div>
                <strong>Open</strong>
                <span>Agreed with a group and not yet closed</span>
              </div>
              <span className="pill">{data.actions.open}</span>
            </div>
            <div className="list-row">
              <div>
                <strong>Overdue</strong>
                <span>Past the date the group agreed</span>
              </div>
              <span className={data.actions.overdue > 0 ? "pill red" : "pill"}>
                {data.actions.overdue}
              </span>
            </div>
            <div className="list-row">
              <div>
                <strong>Due within a week</strong>
                <span>Still open, and the date is close</span>
              </div>
              <span className={data.actions.dueSoon > 0 ? "pill gold" : "pill"}>
                {data.actions.dueSoon}
              </span>
            </div>
            <div className="list-row">
              <div>
                <strong>Done</strong>
                <span>Closed at a later visit</span>
              </div>
              <span className="pill">{data.actions.done}</span>
            </div>
          </div>
        </article>
      </section>

      <article className="data-card">
        <header>
          <div>
            <h3>Never visited</h3>
            <span>These groups have no visit record at all.</span>
          </div>
        </header>
        {data.neverVisited.length === 0 ? (
          <div className="empty-state">Every group in scope has been visited at least once.</div>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>Group</th>
                <th>Code</th>
              </tr>
            </thead>
            <tbody>
              {data.neverVisited.map((group) => (
                <tr key={group.id}>
                  <td>
                    <Link href={`/dashboard/groups/${group.id}`}>{group.name}</Link>
                  </td>
                  <td>{group.code}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </article>

      <article className="data-card">
        <header>
          <div>
            <h3>Overdue a visit</h3>
            <span>Visited before, but not in the last 90 days. Longest gap first.</span>
          </div>
        </header>
        {data.staleGroups.length === 0 ? (
          <div className="empty-state">No group in scope has gone 90 days without a visit.</div>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>Group</th>
                <th>County</th>
                <th>Last visit</th>
                <th>Days ago</th>
              </tr>
            </thead>
            <tbody>
              {data.staleGroups.map((group) => (
                <tr key={group.id}>
                  <td>
                    <Link href={`/dashboard/groups/${group.id}`}>{group.name}</Link>
                  </td>
                  <td>{group.county ?? "—"}</td>
                  <td>
                    {formatDate(group.lastVisitAt)}
                  </td>
                  <td>
                    <span className="pill red">{group.daysSinceVisit}</span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </article>
    </section>
  );
}
