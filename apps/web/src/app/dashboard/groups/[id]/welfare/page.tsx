"use client";

import React from "react";
import type { FormEvent } from "react";
import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, HeartHandshake } from "@/lib/theme-icons";
import { apiFetch, formatKes } from "../../../../../lib/api";

/**
 * Welfare spending.
 *
 * The number that matters on this page is the REMAINING balance, because that
 * is what share-out distributes. Every expense recorded here reduces what each
 * member eventually receives, so the page shows the consequence rather than
 * just a list.
 */
interface WelfareExpense {
  id: string;
  category: string;
  note: string | null;
  payeeName: string | null;
  payeeMember: { id: string; fullName: string } | null;
  ledgerEntry: { amountCents: number; createdAt: string };
}

interface WelfareResponse {
  group: { id: string; name: string; code: string };
  expenses: WelfareExpense[];
  spentCents: number;
  welfareBalanceCents: number;
}

interface Member {
  id: string;
  fullName: string;
}

/** Welfare is paid out during a meeting, so the page needs the open ones. */
interface Meeting {
  id: string;
  title: string;
  status: string;
  scheduledAt: string;
}

const CATEGORIES = ["MEDICAL", "BEREAVEMENT", "EDUCATION", "EMERGENCY", "OTHER"];

export default function GroupWelfarePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [data, setData] = useState<WelfareResponse | null>(null);
  const [members, setMembers] = useState<Member[]>([]);
  const [openMeetings, setOpenMeetings] = useState<Meeting[]>([]);
  const [meetingId, setMeetingId] = useState("");
  const [amount, setAmount] = useState("");
  const [category, setCategory] = useState(CATEGORIES[0]);
  const [payeeMemberId, setPayeeMemberId] = useState("");
  const [payeeName, setPayeeName] = useState("");
  const [note, setNote] = useState("");
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  async function load() {
    const [welfare, memberList, meetingList] = await Promise.all([
      apiFetch<WelfareResponse>(`/groups/${id}/welfare-expenses`),
      apiFetch<Member[]>(`/groups/${id}/members`),
      apiFetch<Meeting[]>(`/groups/${id}/meetings`)
    ]);
    const open = meetingList.filter((meeting) => meeting.status === "IN_PROGRESS");
    setOpenMeetings(open);
    // One open meeting is the normal case; preselect it rather than making an
    // official pick from a list of one.
    setMeetingId((current) => (open.some((m) => m.id === current) ? current : open[0]?.id ?? ""));
    setData(welfare);
    setMembers(memberList);
  }

  useEffect(() => {
    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load welfare expenses."))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const amountCents = Math.round(Number(amount || 0) * 100);
  const available = data?.welfareBalanceCents ?? 0;
  const exceedsFund = amountCents > available;

  async function record(event: FormEvent) {
    event.preventDefault();
    // A payee is required by the API: welfare is paid to someone, and an
    // anonymous debit is not an auditable welfare record.
    if (!payeeMemberId && !payeeName.trim()) {
      return setMessage({ ok: false, text: "Record who received the money — a member, or a name." });
    }
    if (!meetingId) {
      return setMessage({
        ok: false,
        text: "Welfare is paid out during a meeting. Open a meeting first, then record it there."
      });
    }
    setSaving(true);
    setMessage(null);
    try {
      await apiFetch(`/groups/${id}/welfare-expenses`, {
        method: "POST",
        body: JSON.stringify({
          amountCents,
          category,
          payeeMemberId: payeeMemberId || undefined,
          payeeName: payeeName.trim() || undefined,
          note: note.trim() || undefined,
          meetingId
        })
      });
      setAmount("");
      setPayeeName("");
      setPayeeMemberId("");
      setNote("");
      await load();
      setMessage({ ok: true, text: "Recorded. The welfare balance shared out at cycle end is now lower." });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not record the expense." });
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <div className="loading-panel">Loading welfare…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!data) return <div className="empty-state">No welfare information.</div>;

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href={`/dashboard/groups/${id}`}>
            <ArrowLeft size={17} />
            <span>{data.group.name}</span>
          </Link>
          <h2>Welfare</h2>
          <p>
            Remaining <strong>{formatKes(data.welfareBalanceCents)}</strong> · spent{" "}
            {formatKes(data.spentCents)}
          </p>
        </div>
        <HeartHandshake size={22} />
      </header>

      {message ? (
        <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div>
      ) : null}

      <div className="dashboard-notice">
        Welfare is spent during the cycle, and <strong>what remains is what gets shared out</strong>.
        Every expense here reduces what each member receives.
      </div>

      <article className="data-card">
        <header>
          <h3>Record a payment</h3>
        </header>
        <form onSubmit={record}>
          <label>
            Amount (KES)
            <input
              min="0"
              onChange={(event) => setAmount(event.target.value)}
              step="0.01"
              type="number"
              value={amount}
            />
          </label>
          {/* Which meeting this payment is being made in. Welfare leaves the
              fund in front of the members it belongs to, so there is no way to
              record one without naming the meeting. */}
          {openMeetings.length === 0 ? (
            <p className="dashboard-notice error">
              No meeting is open. Welfare is paid out during a meeting, in front of the members —
              open one first, then record the payment there.
            </p>
          ) : (
            <label>
              Recorded in meeting
              <select onChange={(event) => setMeetingId(event.target.value)} value={meetingId}>
                {openMeetings.map((meeting) => (
                  <option key={meeting.id} value={meeting.id}>
                    {meeting.title} — {new Date(meeting.scheduledAt).toLocaleDateString()}
                  </option>
                ))}
              </select>
            </label>
          )}

          <label>
            What for
            <select onChange={(event) => setCategory(event.target.value)} value={category}>
              {CATEGORIES.map((value) => (
                <option key={value} value={value}>
                  {value.charAt(0) + value.slice(1).toLowerCase()}
                </option>
              ))}
            </select>
          </label>
          <label>
            Paid to a member
            <select onChange={(event) => setPayeeMemberId(event.target.value)} value={payeeMemberId}>
              <option value="">— not a member —</option>
              {members.map((member) => (
                <option key={member.id} value={member.id}>
                  {member.fullName}
                </option>
              ))}
            </select>
          </label>
          <label>
            …or a name
            <input
              onChange={(event) => setPayeeName(event.target.value)}
              placeholder="Family, hospital, school"
              type="text"
              value={payeeName}
            />
          </label>
          <label>
            Note
            <input onChange={(event) => setNote(event.target.value)} type="text" value={note} />
          </label>

          {exceedsFund && amountCents > 0 ? (
            <p className="dashboard-notice error">
              The welfare fund holds {formatKes(available)} — short by{" "}
              {formatKes(amountCents - available)}. A group cannot spend welfare money it does not
              have.
            </p>
          ) : null}

          <div className="form-actions">
            <button className="button" disabled={saving || exceedsFund || amountCents <= 0} type="submit">
              {saving ? "Recording…" : "Record expense"}
            </button>
          </div>
        </form>
      </article>

      <article className="data-card">
        <header>
          <h3>Spending this cycle</h3>
        </header>
        {data.expenses.length === 0 ? (
          <p className="empty-state">Nothing spent yet — the whole welfare fund will be shared out.</p>
        ) : (
          <ul>
            {data.expenses.map((expense) => (
              <li key={expense.id}>
                <strong>{formatKes(expense.ledgerEntry.amountCents)}</strong> ·{" "}
                {expense.category.charAt(0) + expense.category.slice(1).toLowerCase()} ·{" "}
                {expense.payeeMember?.fullName ?? expense.payeeName ?? "unrecorded payee"} ·{" "}
                {new Date(expense.ledgerEntry.createdAt).toLocaleDateString()}
                {expense.note ? ` — ${expense.note}` : ""}
              </li>
            ))}
          </ul>
        )}
      </article>
    </section>
  );
}
