"use client";

import React from "react";
import { useEffect, useState } from "react";
import Link from "next/link";
import { AlertTriangle } from "@/lib/theme-icons";
import { apiFetch, formatDateTime } from "../../../lib/api";

/**
 * The development team's issue log: server errors, errors the console and
 * phone hit, and data that does not add up (a needs assessment whose loans
 * are more than ten times its savings). Repeats fold into one row with a
 * count. IWL admins only — groups and partners never see it.
 */
interface Issue {
  id: string;
  source: string;
  severity: string;
  category: string;
  title: string;
  detail: string | null;
  entityType: string | null;
  entityId: string | null;
  groupId: string | null;
  group: { id: string; code: string; name: string } | null;
  context: Record<string, unknown>;
  status: string;
  occurrences: number;
  firstSeenAt: string;
  lastSeenAt: string;
  lastTraceId: string | null;
  resolvedAt: string | null;
  resolutionNote: string | null;
}

const SOURCE_LABELS: Record<string, string> = {
  API: "Server",
  WEB: "Web console",
  MOBILE: "Phone app",
  DATA_QUALITY: "Data quality",
  JOB: "Background job"
};
const STATUS_LABELS: Record<string, string> = {
  OPEN: "Open",
  ACKNOWLEDGED: "Being worked on",
  RESOLVED: "Resolved",
  IGNORED: "Ignored"
};

export default function SystemIssuesPage() {
  const [status, setStatus] = useState("");
  const [source, setSource] = useState("");
  const [rows, setRows] = useState<Issue[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  async function load() {
    const query = new URLSearchParams();
    if (status) query.set("status", status);
    if (source) query.set("source", source);
    const data = await apiFetch<Issue[]>(`/system-issues${query.size ? `?${query}` : ""}`);
    setRows(Array.isArray(data) ? data : []);
  }

  useEffect(() => {
    setError(null);
    load().catch((e) => setError(e instanceof Error ? e.message : "Unable to load the issue log."));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [status, source]);

  async function update(issue: Issue, next: string) {
    let note: string | undefined;
    if (next === "RESOLVED") {
      const answer = window.prompt("What fixed it? (kept with the issue)");
      if (!answer?.trim()) return;
      note = answer.trim();
    }
    setBusy(issue.id);
    setMessage(null);
    try {
      await apiFetch(`/system-issues/${issue.id}`, { method: "PATCH", body: JSON.stringify({ status: next, ...(note ? { note } : {}) }) });
      await load();
      setMessage({ ok: true, text: `Marked "${STATUS_LABELS[next]}".` });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "That did not work." });
    } finally {
      setBusy(null);
    }
  }

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <h2>System issues</h2>
          <p>Errors and data problems for the development team to sort out. Groups and partners never see this list.</p>
        </div>
        <AlertTriangle size={22} />
      </header>

      {message ? <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div> : null}
      {error ? <div className="dashboard-notice error">{error}</div> : null}

      <div className="dashboard-filter-row">
        <label>
          Status
          <select value={status} onChange={(event) => setStatus(event.target.value)}>
            <option value="">Open and being worked on</option>
            {Object.entries(STATUS_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <label>
          Source
          <select value={source} onChange={(event) => setSource(event.target.value)}>
            <option value="">All sources</option>
            {Object.entries(SOURCE_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </label>
      </div>

      <article className="data-card">
        <header>
          <div>
            <h3>{rows ? `${rows.length} issue${rows.length === 1 ? "" : "s"}` : "Issues"}</h3>
            <p>Newest first. A problem that happens again adds to its count, and a resolved one that comes back reopens.</p>
          </div>
        </header>
        {!rows && !error ? <div className="loading-panel">Loading issues…</div> : null}
        {rows && rows.length === 0 ? <div className="empty-state">Nothing here.</div> : null}
        {rows && rows.length > 0 ? (
          <ul className="issue-list">
            {rows.map((issue) => (
              <li key={issue.id} className={`issue-row severity-${issue.severity.toLowerCase()}`}>
                <div className="issue-main">
                  <div className="issue-tags">
                    <span className="pill">{SOURCE_LABELS[issue.source] ?? issue.source}</span>
                    <span className="pill">{issue.severity.toLowerCase()}</span>
                    <span className="pill">{STATUS_LABELS[issue.status] ?? issue.status}</span>
                    {issue.occurrences > 1 ? <span className="pill">×{issue.occurrences}</span> : null}
                  </div>
                  <strong className="issue-title">{issue.title}</strong>
                  <span className="issue-meta">
                    {issue.category} · last {formatDateTime(issue.lastSeenAt)}
                    {issue.occurrences > 1 ? ` · first ${formatDateTime(issue.firstSeenAt)}` : ""}
                    {issue.lastTraceId ? ` · ref ${issue.lastTraceId.slice(0, 8)}` : ""}
                    {issue.group ? (
                      <>
                        {" · "}
                        <Link href={`/dashboard/groups/${issue.group.id}`}>
                          {issue.group.code} {issue.group.name}
                        </Link>
                      </>
                    ) : null}
                  </span>
                  {issue.resolutionNote ? <span className="issue-meta">Resolution: {issue.resolutionNote}</span> : null}
                  {issue.detail || Object.keys(issue.context ?? {}).length ? (
                    <details className="quiet-details">
                      <summary>Details</summary>
                      {issue.detail ? <pre className="issue-detail">{issue.detail}</pre> : null}
                      {Object.keys(issue.context ?? {}).length ? (
                        <pre className="issue-detail">{JSON.stringify(issue.context, null, 2)}</pre>
                      ) : null}
                    </details>
                  ) : null}
                </div>
                <div className="issue-actions">
                  {issue.status === "OPEN" ? (
                    <button type="button" className="button secondary" disabled={busy === issue.id} onClick={() => update(issue, "ACKNOWLEDGED")}>
                      Working on it
                    </button>
                  ) : null}
                  {issue.status !== "RESOLVED" ? (
                    <button type="button" className="button" disabled={busy === issue.id} onClick={() => update(issue, "RESOLVED")}>
                      Resolved
                    </button>
                  ) : null}
                  {issue.status !== "IGNORED" && issue.status !== "RESOLVED" ? (
                    <button type="button" className="button secondary" disabled={busy === issue.id} onClick={() => update(issue, "IGNORED")}>
                      Ignore
                    </button>
                  ) : null}
                  {issue.status === "RESOLVED" || issue.status === "IGNORED" ? (
                    <button type="button" className="button secondary" disabled={busy === issue.id} onClick={() => update(issue, "OPEN")}>
                      Reopen
                    </button>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        ) : null}
      </article>
    </section>
  );
}
