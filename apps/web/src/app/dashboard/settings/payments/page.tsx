"use client";

import React from "react";
import type { FormEvent } from "react";
import { useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, Banknote } from "@/lib/theme-icons";
import { apiFetch, formatDate } from "../../../../lib/api";
import { centsFromShillings, kesExact, PROVIDER_LABELS } from "../../../../features/payments/money";

/**
 * Payment fees.
 *
 * The member names what the GROUP receives; these fees go on top. Platform
 * bands are IWL's fee, chosen on the group amount. Provider rules are the
 * gateway's cost, chosen on the total and grossed up so the group is never
 * short. An edit makes a new version; payments keep the fees they were
 * quoted, so history never changes.
 */
interface FeeRule {
  id: string;
  kind: "PLATFORM" | "PROVIDER";
  provider: string | null;
  minCents: number;
  maxCents: number | null;
  fixedCents: number;
  percentBps: number;
  active: boolean;
  version: number;
  note: string | null;
  updatedAt: string;
}

interface Quote {
  groupAmountCents: number;
  platformFeeCents: number;
  providerFeeCents: number;
  totalCents: number;
}

const emptyDraft = { kind: "PLATFORM", provider: "", min: "", max: "", fixed: "", percent: "", note: "" };

function band(rule: FeeRule) {
  const low = kesExact(rule.minCents);
  return rule.maxCents == null ? `${low} and above` : `${low} – ${kesExact(rule.maxCents)}`;
}

function charge(rule: FeeRule) {
  const parts = [];
  if (rule.fixedCents > 0) parts.push(kesExact(rule.fixedCents));
  if (rule.percentBps > 0) parts.push(`${rule.percentBps / 100}%`);
  return parts.length > 0 ? parts.join(" + ") : "Free";
}

export default function PaymentFeesPage() {
  const [rules, setRules] = useState<FeeRule[]>([]);
  const [showRetired, setShowRetired] = useState(false);
  const [draft, setDraft] = useState(emptyDraft);
  const [editing, setEditing] = useState<string | null>(null);
  const [preview, setPreview] = useState({ provider: "MPESA_DARAJA", amount: "500" });
  const [quote, setQuote] = useState<Quote | null>(null);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  async function load(all = showRetired) {
    setRules(await apiFetch<FeeRule[]>(`/payment-admin/fee-rules${all ? "?all=1" : ""}`));
  }

  useEffect(() => {
    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load fee rules."))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function runPreview() {
    const cents = centsFromShillings(preview.amount);
    if (!cents) return setQuote(null);
    try {
      setQuote(
        await apiFetch<Quote>("/payment-admin/fee-preview", {
          method: "POST",
          body: JSON.stringify({ provider: preview.provider, groupAmountCents: cents })
        })
      );
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not preview." });
    }
  }

  useEffect(() => {
    runPreview();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [preview.provider, preview.amount, rules]);

  function edit(rule: FeeRule) {
    setEditing(rule.id);
    setDraft({
      kind: rule.kind,
      provider: rule.provider ?? "",
      min: String(rule.minCents / 100),
      max: rule.maxCents == null ? "" : String(rule.maxCents / 100),
      fixed: String(rule.fixedCents / 100),
      percent: String(rule.percentBps / 100),
      note: rule.note ?? ""
    });
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    setMessage(null);
    try {
      const body = {
        kind: draft.kind,
        provider: draft.kind === "PROVIDER" ? draft.provider || null : null,
        minCents: centsFromShillings(draft.min) ?? 0,
        maxCents: centsFromShillings(draft.max),
        fixedCents: centsFromShillings(draft.fixed) ?? 0,
        percentBps: Math.round(Number(draft.percent || "0") * 100),
        active: true,
        note: draft.note || undefined
      };
      await apiFetch(editing ? `/payment-admin/fee-rules/${editing}` : "/payment-admin/fee-rules", {
        method: editing ? "PUT" : "POST",
        body: JSON.stringify(body)
      });
      setDraft(emptyDraft);
      setEditing(null);
      await load();
      setMessage({ ok: true, text: editing ? "Saved as a new version. Earlier payments keep their fees." : "Fee rule added." });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not save." });
    } finally {
      setSaving(false);
    }
  }

  async function deactivate(rule: FeeRule) {
    if (!window.confirm(`Stop charging ${charge(rule)} for ${band(rule)}?`)) return;
    try {
      await apiFetch(`/payment-admin/fee-rules/${rule.id}/deactivate`, { method: "POST" });
      await load();
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not switch it off." });
    }
  }

  async function reactivate(rule: FeeRule) {
    try {
      await apiFetch(`/payment-admin/fee-rules/${rule.id}`, {
        method: "PUT",
        body: JSON.stringify({
          kind: rule.kind,
          provider: rule.provider,
          minCents: rule.minCents,
          maxCents: rule.maxCents,
          fixedCents: rule.fixedCents,
          percentBps: rule.percentBps,
          active: true,
          note: rule.note ?? undefined
        })
      });
      await load();
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not switch it on." });
    }
  }

  if (loading) return <div className="loading-panel">Loading fee rules…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;

  const activeCount = rules.filter((rule) => rule.active).length;
  const groups: Array<{ title: string; kind: "PLATFORM" | "PROVIDER"; note: string }> = [
    { title: "IWL platform fee", kind: "PLATFORM", note: "Chosen on what the group receives." },
    { title: "Payment provider charges", kind: "PROVIDER", note: "Chosen on the total charged, and grossed up so the group is never short." }
  ];

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href="/dashboard/settings">
            <ArrowLeft size={17} />
            <span>Settings</span>
          </Link>
          <h2>Payment fees</h2>
          <p>Added on top of what the member wants the group to receive. The group always receives the full amount.</p>
        </div>
        <Banknote size={22} />
      </header>

      {message ? <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div> : null}
      {activeCount === 0 ? (
        <div className="dashboard-notice">
          No fee is switched on, so members pay exactly the group amount. Once any fee is on, phones older than the
          fee-aware version are asked to update before they can pay online — they cannot show the charges.
        </div>
      ) : null}

      <article className="data-card">
        <header>
          <h3>What a member would pay</h3>
        </header>
        <div className="dashboard-filter-row">
          <label>
            Provider
            <select value={preview.provider} onChange={(event) => setPreview({ ...preview, provider: event.target.value })}>
              {Object.entries(PROVIDER_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <label>
            Group receives (KSh)
            <input min={1} type="number" value={preview.amount} onChange={(event) => setPreview({ ...preview, amount: event.target.value })} />
          </label>
        </div>
        {quote ? (
          <table className="fee-breakdown">
            <tbody>
              <tr>
                <td>To the group</td>
                <td>{kesExact(quote.groupAmountCents)}</td>
              </tr>
              <tr>
                <td>IWL platform fee</td>
                <td>{kesExact(quote.platformFeeCents)}</td>
              </tr>
              <tr>
                <td>Payment charges</td>
                <td>{kesExact(quote.providerFeeCents)}</td>
              </tr>
              <tr>
                <th>Member pays</th>
                <th>{kesExact(quote.totalCents)}</th>
              </tr>
            </tbody>
          </table>
        ) : null}
      </article>

      {groups.map((section) => (
        <article className="data-card" key={section.kind}>
          <header>
            <div>
              <h3>{section.title}</h3>
              <p>{section.note}</p>
            </div>
          </header>
          <table>
            <thead>
              <tr>
                {section.kind === "PROVIDER" ? <th>Provider</th> : null}
                <th>Band</th>
                <th>Charge</th>
                <th>Status</th>
                <th>Version</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {rules
                .filter((rule) => rule.kind === section.kind)
                .map((rule) => (
                  <tr key={rule.id}>
                    {section.kind === "PROVIDER" ? <td>{rule.provider ? PROVIDER_LABELS[rule.provider] : "Any"}</td> : null}
                    <td>{band(rule)}</td>
                    <td>{charge(rule)}</td>
                    <td>
                      <span className={`pill ${rule.active ? "" : "muted"}`}>{rule.active ? "On" : "Off"}</span>
                    </td>
                    <td>
                      v{rule.version} · {formatDate(rule.updatedAt)}
                    </td>
                    <td>
                      {rule.active ? (
                        <>
                          <button className="button secondary" type="button" onClick={() => edit(rule)}>
                            Change
                          </button>{" "}
                          <button className="button secondary" type="button" onClick={() => deactivate(rule)}>
                            Switch off
                          </button>
                        </>
                      ) : (
                        <button className="button secondary" type="button" onClick={() => reactivate(rule)}>
                          Switch on
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
            </tbody>
          </table>
        </article>
      ))}

      <label className="checkbox-field">
        <input
          checked={showRetired}
          type="checkbox"
          onChange={(event) => {
            setShowRetired(event.target.checked);
            load(event.target.checked).catch(() => undefined);
          }}
        />
        <span>Show switched-off and earlier versions</span>
      </label>

      <article className="data-card">
        <header>
          <h3>{editing ? "Change fee rule" : "Add a fee rule"}</h3>
        </header>
        <form className="stacked-form is-columns" onSubmit={save}>
          <label>
            Kind
            <select value={draft.kind} onChange={(event) => setDraft({ ...draft, kind: event.target.value })}>
              <option value="PLATFORM">IWL platform fee</option>
              <option value="PROVIDER">Payment provider charge</option>
            </select>
          </label>
          {draft.kind === "PROVIDER" ? (
            <label>
              Provider
              <select value={draft.provider} onChange={(event) => setDraft({ ...draft, provider: event.target.value })}>
                <option value="">Any provider</option>
                {Object.entries(PROVIDER_LABELS).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </label>
          ) : null}
          <label>
            From (KSh)
            <input min={0} required step="0.01" type="number" value={draft.min} onChange={(event) => setDraft({ ...draft, min: event.target.value })} />
          </label>
          <label>
            Up to (KSh, blank for no limit)
            <input min={0} step="0.01" type="number" value={draft.max} onChange={(event) => setDraft({ ...draft, max: event.target.value })} />
          </label>
          <label>
            Fixed charge (KSh)
            <input min={0} step="0.01" type="number" value={draft.fixed} onChange={(event) => setDraft({ ...draft, fixed: event.target.value })} />
          </label>
          <label>
            Percentage (%)
            <input max={49.99} min={0} step="0.01" type="number" value={draft.percent} onChange={(event) => setDraft({ ...draft, percent: event.target.value })} />
          </label>
          <label>
            Note
            <input maxLength={300} value={draft.note} onChange={(event) => setDraft({ ...draft, note: event.target.value })} />
          </label>
          <div className="form-actions">
            <button className="button" disabled={saving} type="submit">
              {saving ? "Saving…" : editing ? "Save new version" : "Add rule"}
            </button>
            {editing ? (
              <button
                className="button secondary"
                type="button"
                onClick={() => {
                  setEditing(null);
                  setDraft(emptyDraft);
                }}
              >
                Cancel
              </button>
            ) : null}
          </div>
        </form>
      </article>
    </section>
  );
}
