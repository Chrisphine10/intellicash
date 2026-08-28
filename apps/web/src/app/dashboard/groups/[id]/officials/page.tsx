"use client";

import React from "react";
import type { FormEvent } from "react";
import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, UserCog } from "@/lib/theme-icons";
import { apiFetch, formatDate } from "../../../../../lib/api";

/**
 * Who holds which office, and who held it before.
 *
 * The page is built around one rule: a handover ENDS the previous term, it does
 * not erase it. Officials read "replaced" as "deleted", so the wording says so
 * before they click, and past terms are shown as their own list rather than
 * disappearing from the screen.
 */
interface Assignment {
  id: string;
  role: string;
  memberId: string;
  startedAt: string;
  endedAt: string | null;
  note: string | null;
  member?: { id: string; fullName: string };
}

interface AssignmentsResponse {
  group: { id: string; name: string; code: string };
  current: Assignment[];
  history: Assignment[];
  canAssign: boolean;
}

interface Member {
  id: string;
  fullName: string;
  status: string;
}

/**
 * MEMBER is deliberately absent: it is not an office. Standing a member down is
 * "end term", which keeps the history, rather than an assignment to "MEMBER"
 * that would read as a fresh appointment in the record.
 */
const OFFICES: Record<string, string> = {
  CHAIRPERSON: "Chairperson",
  SECRETARY: "Secretary",
  TREASURER: "Treasurer",
  MONEY_COUNTER: "Money counter",
  KEY_HOLDER: "Key holder",
  VILLAGE_AGENT: "Village agent"
};

/** Only these may be held by one member at a time — the API enforces it too. */
const SINGLETON_OFFICES = new Set(["CHAIRPERSON", "SECRETARY", "TREASURER"]);

function officeLabel(role: string) {
  return OFFICES[role] ?? role;
}

export default function GroupOfficialsPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [data, setData] = useState<AssignmentsResponse | null>(null);
  const [members, setMembers] = useState<Member[]>([]);
  const [memberId, setMemberId] = useState("");
  const [role, setRole] = useState("SECRETARY");
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  async function load() {
    const [assignments, roster] = await Promise.all([
      apiFetch<AssignmentsResponse>(`/groups/${id}/role-assignments`),
      apiFetch<Member[]>(`/groups/${id}/members`)
    ]);
    setData(assignments);
    setMembers(roster);
  }

  useEffect(() => {
    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load officials."))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const incumbent = data?.current.find((assignment) => assignment.role === role) ?? null;

  async function assign(event: FormEvent) {
    event.preventDefault();
    if (!memberId) {
      setMessage({ ok: false, text: "Choose the member who takes the office." });
      return;
    }

    // Naming the outgoing holder before the click is the point: the operator
    // should know a handover is happening, not discover it afterwards.
    if (incumbent) {
      const outgoing = incumbent.member?.fullName ?? "the current holder";
      const confirmed = window.confirm(
        `${outgoing} currently holds ${officeLabel(role).toLowerCase()}.\n\n` +
          `Their term will be recorded as ended today. It is kept in history, not deleted.`
      );
      if (!confirmed) return;
    }

    setBusy(true);
    setMessage(null);
    try {
      const response = await apiFetch<{ message: string }>(`/groups/${id}/role-assignments`, {
        method: "POST",
        body: JSON.stringify({ memberId, role, ...(note.trim() ? { note: note.trim() } : {}) })
      });
      await load();
      setMemberId("");
      setNote("");
      setMessage({ ok: true, text: response.message });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not assign the office." });
    } finally {
      setBusy(false);
    }
  }

  async function endTerm(assignment: Assignment) {
    const holder = assignment.member?.fullName ?? "this member";
    const confirmed = window.confirm(
      `End ${holder}'s term as ${officeLabel(assignment.role).toLowerCase()}?\n\n` +
        `The office is left vacant. The record of who held it stays in history.`
    );
    if (!confirmed) return;

    setBusy(true);
    setMessage(null);
    try {
      const response = await apiFetch<{ message: string }>(
        `/groups/${id}/role-assignments/${assignment.id}/end`,
        { method: "POST", body: JSON.stringify({}) }
      );
      await load();
      setMessage({ ok: true, text: response.message });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not end the term." });
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <div className="loading-panel">Loading officials…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!data) return <div className="empty-state">No officials information.</div>;

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href={`/dashboard/groups/${id}`}>
            <ArrowLeft size={17} />
            <span>{data.group.name}</span>
          </Link>
          <h2>Officials</h2>
          <p>Who holds each office today, and who held it before.</p>
        </div>
        <UserCog size={22} />
      </header>

      {message ? (
        <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div>
      ) : null}

      <div className="dashboard-notice">
        Handing an office over <strong>ends the previous term, it does not delete it</strong>. A
        meeting minuted last year still shows the secretary who actually took it.
      </div>

      {data.canAssign ? (
        <article className="data-card">
          <header>
            <h3>Appoint an official</h3>
          </header>
          <form onSubmit={assign}>
            <label>
              Office
              <select
                disabled={busy}
                onChange={(event) => setRole(event.target.value)}
                value={role}
              >
                {Object.entries(OFFICES).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </label>

            <label>
              Member
              <select
                disabled={busy}
                onChange={(event) => setMemberId(event.target.value)}
                value={memberId}
              >
                <option value="">Choose a member…</option>
                {members.map((member) => (
                  <option key={member.id} value={member.id}>
                    {member.fullName}
                    {member.status && member.status !== "ACTIVE" ? ` (${member.status.toLowerCase()})` : ""}
                  </option>
                ))}
              </select>
            </label>

            <label>
              Note (optional)
              <input
                disabled={busy}
                maxLength={500}
                onChange={(event) => setNote(event.target.value)}
                placeholder="e.g. elected at the AGM"
                type="text"
                value={note}
              />
            </label>

            {incumbent ? (
              <p className="dashboard-notice">
                {incumbent.member?.fullName ?? "Someone"} currently holds{" "}
                {officeLabel(role).toLowerCase()}
                {SINGLETON_OFFICES.has(role)
                  ? ". Appointing someone else ends their term today."
                  : "."}
              </p>
            ) : null}

            <div className="form-actions">
              <button className="button" disabled={busy} type="submit">
                {busy ? "Saving…" : "Appoint"}
              </button>
            </div>
          </form>
        </article>
      ) : (
        <div className="dashboard-notice">
          You can see this group&apos;s officials but not change them. Only a platform admin or the
          group&apos;s own account may.
        </div>
      )}

      <article className="data-card">
        <header>
          <h3>In office now</h3>
        </header>
        {data.current.length === 0 ? (
          <p className="empty-state">No offices are filled yet.</p>
        ) : (
          <ul>
            {data.current.map((assignment) => (
              <li key={assignment.id}>
                <strong>{officeLabel(assignment.role)}</strong> ·{" "}
                {assignment.member?.fullName ?? "Unknown member"} · since{" "}
                {formatDate(assignment.startedAt)}
                {assignment.note ? ` — ${assignment.note}` : ""}
                {data.canAssign ? (
                  <>
                    {" "}
                    <button
                      className="button secondary"
                      disabled={busy}
                      onClick={() => endTerm(assignment)}
                      type="button"
                    >
                      End term
                    </button>
                  </>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </article>

      <article className="data-card">
        <header>
          <h3>Past terms</h3>
        </header>
        {data.history.length === 0 ? (
          <p className="empty-state">No one has stood down yet.</p>
        ) : (
          <ul>
            {data.history.map((assignment) => (
              <li key={assignment.id}>
                <strong>{officeLabel(assignment.role)}</strong> ·{" "}
                {assignment.member?.fullName ?? "Unknown member"} ·{" "}
                {formatDate(assignment.startedAt)} to{" "}
                {assignment.endedAt ? formatDate(assignment.endedAt) : "—"}
                {assignment.note ? ` — ${assignment.note}` : ""}
              </li>
            ))}
          </ul>
        )}
      </article>
    </section>
  );
}
