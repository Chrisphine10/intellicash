"use client";

import React from "react";
import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, Activity } from "@/lib/theme-icons";
import { apiFetch, formatDate, formatKes } from "../../../../../lib/api";

/**
 * Share cycles.
 *
 * A closed cycle is READ-ONLY, not deleted — its meetings and ledger stay fully
 * visible in history and reports. The page states that plainly, because
 * "archived" is easily read as "gone".
 */
interface Cycle {
  id: string;
  number: number;
  status: string;
  startedAt: string;
  closedAt: string | null;
  meetings: number;
  ledgerEntries: number;
  editable: boolean;
  notes: string | null;
}

interface CyclesResponse {
  group: { id: string; name: string; code: string };
  currentCycleNumber: number;
  cycles: Cycle[];
  canManage: boolean;
  /** Members bought shares this cycle and they have not been shared out yet. */
  closeNeedsShareOut?: boolean;
}

export default function GroupCyclesPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [data, setData] = useState<CyclesResponse | null>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  async function load() {
    setData(await apiFetch<CyclesResponse>(`/groups/${id}/cycles`));
  }

  useEffect(() => {
    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load cycles."))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  async function closeCycle() {
    const current = data?.cycles.find((cycle) => cycle.editable);
    const confirmed = window.confirm(
      `Close cycle ${current?.number ?? ""} and start the next?\n\n` +
        `No shares were bought in it, so there is nothing to share out. Its ${current?.meetings ?? 0} ` +
        `meeting(s) become read-only; members and roles carry over. This cannot be undone.`
    );
    if (!confirmed) return;

    setBusy(true);
    setMessage(null);
    try {
      const result = await apiFetch<{ message: string }>(`/groups/${id}/cycles/close`, {
        method: "POST",
        body: JSON.stringify({})
      });
      await load();
      setMessage({ ok: true, text: result.message });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not close the cycle." });
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <div className="loading-panel">Loading cycles…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!data) return <div className="empty-state">No cycle information.</div>;

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href={`/dashboard/groups/${id}`}>
            <ArrowLeft size={17} />
            <span>{data.group.name}</span>
          </Link>
          <h2>Share cycles</h2>
          <p>Currently on cycle {data.currentCycleNumber}.</p>
        </div>
        <Activity size={22} />
      </header>

      {message ? (
        <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div>
      ) : null}

      <div className="dashboard-notice">
        Closing a cycle makes its records <strong>read-only, not deleted</strong>. Every meeting and
        ledger entry stays visible in history and reports.
      </div>

      {data.canManage && data.closeNeedsShareOut ? (
        <div className="dashboard-notice">
          Members bought shares in cycle {data.currentCycleNumber}, so this cycle ends with its{" "}
          <strong>share-out</strong>, not with a plain close. Run it from the phone (More, Share-Out) or
          in the cycle&apos;s last meeting here (<Link href="/dashboard/meetings">Meetings</Link>, then Entry):
          it pays every member and closes the cycle in one step.
        </div>
      ) : data.canManage ? (
        <div className="form-actions">
          <button className="button" disabled={busy} onClick={closeCycle} type="button">
            {busy ? "Closing…" : "Close cycle and start the next"}
          </button>
        </div>
      ) : (
        <div className="dashboard-notice">
          You can see this group&apos;s cycles but not close one. Only a platform admin or the
          group&apos;s own account may.
        </div>
      )}

      <div className="dashboard-grid">
        {data.cycles.map((cycle) => (
          <article className="data-card" key={cycle.id}>
            <header>
              <div>
                <h3>Cycle {cycle.number}</h3>
                <p>
                  Started {formatDate(cycle.startedAt)}
                  {cycle.closedAt ? ` · closed ${formatDate(cycle.closedAt)}` : ""}
                </p>
              </div>
              <span className={`pill ${cycle.editable ? "" : "muted"}`}>
                {cycle.editable ? "Open" : "Archived — read only"}
              </span>
            </header>
            <div className="card-body">
              <p>
                {cycle.meetings} {cycle.meetings === 1 ? "meeting" : "meetings"} · {cycle.ledgerEntries}{" "}
                {cycle.ledgerEntries === 1 ? "ledger entry" : "ledger entries"}
              </p>
              {cycle.notes ? <p>{cycle.notes}</p> : null}
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}
