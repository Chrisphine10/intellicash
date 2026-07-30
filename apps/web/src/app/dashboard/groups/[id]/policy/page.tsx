"use client";

import React from "react";
import type { FormEvent } from "react";
import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, Settings } from "@/lib/theme-icons";
import { apiFetch } from "../../../../../lib/api";

/**
 * A group's own rules.
 *
 * Only two things are configurable, and that is deliberate: fines and welfare
 * net off a payout rather than barring share-out, and loans always net off at
 * share-out, so neither an eligibility gate nor a loan strategy exists to set.
 * The page says so, because an operator looking for those switches should learn
 * they are not missing — they are decided.
 */
interface Policy {
  defaultLoanTermMonths: number;
  expenseFundType: string;
  configured: boolean;
  updatedAt: string | null;
}

interface PolicyResponse {
  group: { id: string; name: string; code: string };
  policy: Policy;
  defaults: { defaultLoanTermMonths: number; expenseFundType: string };
  canConfigure: boolean;
}

const FUND_LABELS: Record<string, string> = {
  SOCIAL: "Welfare (social) fund",
  SAVINGS: "Savings fund",
  INTERNAL_LOAN: "Loan fund"
};

export default function GroupPolicyPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [data, setData] = useState<PolicyResponse | null>(null);
  const [term, setTerm] = useState("");
  const [fund, setFund] = useState("");
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  async function load() {
    const response = await apiFetch<PolicyResponse>(`/groups/${id}/policy`);
    setData(response);
    setTerm(String(response.policy.defaultLoanTermMonths));
    setFund(response.policy.expenseFundType);
  }

  useEffect(() => {
    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load the policy."))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  async function save(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    setMessage(null);
    try {
      const response = await apiFetch<{ message: string }>(`/groups/${id}/policy`, {
        method: "PUT",
        body: JSON.stringify({ defaultLoanTermMonths: Number(term), expenseFundType: fund })
      });
      await load();
      setMessage({ ok: true, text: response.message });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not save." });
    } finally {
      setSaving(false);
    }
  }

  async function revert() {
    if (!window.confirm("Put this group back on the platform defaults?")) return;
    setSaving(true);
    try {
      await apiFetch(`/groups/${id}/policy`, { method: "DELETE" });
      await load();
      setMessage({ ok: true, text: "This group is back on the platform defaults." });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not revert." });
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <div className="loading-panel">Loading policy…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!data) return <div className="empty-state">No policy information.</div>;

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href={`/dashboard/groups/${id}`}>
            <ArrowLeft size={17} />
            <span>{data.group.name}</span>
          </Link>
          <h2>Group policy</h2>
          <p>
            {data.policy.configured
              ? "This group uses its own settings."
              : "This group uses the platform defaults."}
          </p>
        </div>
        <Settings size={22} />
      </header>

      {message ? (
        <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div>
      ) : null}

      {!data.canConfigure ? (
        <div className="dashboard-notice">
          You can see these settings but not change them. Only a platform admin or the group&apos;s
          own account may edit them.
        </div>
      ) : null}

      <article className="data-card">
        <form onSubmit={save}>
          <label>
            Default loan term (months)
            <input
              disabled={!data.canConfigure || saving}
              max={60}
              min={1}
              onChange={(event) => setTerm(event.target.value)}
              type="number"
              value={term}
            />
          </label>
          <p className="dashboard-notice">
            Applies to <strong>new</strong> loans. Existing loans keep the term they were agreed
            with — changing this never reprices money already lent.
          </p>

          <label>
            Expenses are paid from
            <select
              disabled={!data.canConfigure || saving}
              onChange={(event) => setFund(event.target.value)}
              value={fund}
            >
              {Object.entries(FUND_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>

          {data.canConfigure ? (
            <div className="form-actions">
              <button className="button" disabled={saving} type="submit">
                {saving ? "Saving…" : "Save policy"}
              </button>
              {data.policy.configured ? (
                <button className="button secondary" disabled={saving} onClick={revert} type="button">
                  Use platform defaults
                </button>
              ) : null}
            </div>
          ) : null}
        </form>
      </article>

      <article className="data-card">
        <header>
          <h3>Settings this group does not have</h3>
        </header>
        <p>
          <strong>Share-out eligibility.</strong> Unpaid fines and welfare are deducted from a
          member&apos;s payout, never used to bar them from share-out — so there is nothing to
          configure. A member can end with a negative payout, which is a debt to the group.
        </p>
        <p>
          <strong>Outstanding loans.</strong> Always netted off at share-out and never carried into
          the next cycle, so there is no strategy to choose.
        </p>
      </article>
    </section>
  );
}
