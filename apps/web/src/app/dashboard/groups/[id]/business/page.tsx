"use client";

import type { FormEvent } from "react";
import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, Building2 } from "@/lib/theme-icons";
import { apiFetch } from "../../../../../lib/api";

/**
 * The group's collective enterprise.
 *
 * The current figures sit on top; underneath is one snapshot per visit. That
 * split is the whole point — editing the revenue figure would otherwise destroy
 * the previous one, and "did this group grow between visits" is the only
 * question the profile exists to answer.
 *
 * Amounts are held in cents on the wire, matching the ledger. This page is the
 * only place they become shillings, and only for the eye.
 */
interface ProfileFigures {
  enterpriseType: string | null;
  description: string | null;
  monthlyRevenueCents: number | null;
  monthlyCostsCents: number | null;
  monthlyMarginCents: number | null;
  employsPeople: number | null;
  mainChallenge: string | null;
  supportNeeded: string | null;
}

interface HistoryRow extends ProfileFigures {
  visitId: string | null;
  recordedAt: string;
}

interface ProfileResponse {
  group: { id: string; name: string; code: string };
  profile: ProfileFigures | null;
  recorded: boolean;
  history?: HistoryRow[];
}

export default function GroupBusinessPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [data, setData] = useState<ProfileResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);

  const [enterpriseType, setEnterpriseType] = useState("");
  const [revenue, setRevenue] = useState("");
  const [costs, setCosts] = useState("");
  const [employs, setEmploys] = useState("");
  const [mainChallenge, setMainChallenge] = useState("");
  const [supportNeeded, setSupportNeeded] = useState("");

  async function load() {
    const response = await apiFetch<ProfileResponse>(`/groups/${id}/business-profile`);
    setData(response);
    const profile = response.profile;
    setEnterpriseType(profile?.enterpriseType ?? "");
    setRevenue(toShillings(profile?.monthlyRevenueCents));
    setCosts(toShillings(profile?.monthlyCostsCents));
    setEmploys(profile?.employsPeople === null || profile?.employsPeople === undefined ? "" : String(profile.employsPeople));
    setMainChallenge(profile?.mainChallenge ?? "");
    setSupportNeeded(profile?.supportNeeded ?? "");
  }

  useEffect(() => {
    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load the business profile."))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  async function save(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    setMessage(null);
    try {
      // No visitId from the console: an office edit is a correction to the
      // current figures, not a new observation made on an occasion. Only the
      // agent standing with the group creates a snapshot.
      await apiFetch(`/groups/${id}/business-profile`, {
        method: "PUT",
        body: JSON.stringify({
          enterpriseType: enterpriseType.trim() || null,
          monthlyRevenueCents: toCents(revenue),
          monthlyCostsCents: toCents(costs),
          employsPeople: employs.trim() === "" ? null : Number(employs),
          mainChallenge: mainChallenge.trim() || null,
          supportNeeded: supportNeeded.trim() || null
        })
      });
      await load();
      setMessage({ ok: true, text: "Saved." });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not save the profile." });
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <div className="loading-panel">Loading business profile…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!data) return <div className="empty-state">No business profile.</div>;

  const history = data.history ?? [];
  // Oldest to newest, so growth reads left to right the way a series should.
  const chronological = [...history].sort(
    (a, b) => new Date(a.recordedAt).getTime() - new Date(b.recordedAt).getTime()
  );
  const growth = revenueGrowth(chronological);

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href={`/dashboard/groups/${id}`}>
            <ArrowLeft size={17} />
            <span>{data.group.name}</span>
          </Link>
          <h2>Group business</h2>
          <p>
            {data.recorded
              ? "The enterprise the group runs together. Figures are per month."
              : "This group has not been asked about a collective enterprise yet — which is not the same as having none."}
          </p>
        </div>
        <Building2 size={22} />
      </header>

      {message ? (
        <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div>
      ) : null}

      {data.profile ? (
        <section className="stat-grid">
          <article className="data-card">
            <span className="eyebrow">Money in</span>
            <strong>{money(data.profile.monthlyRevenueCents)}</strong>
          </article>
          <article className="data-card">
            <span className="eyebrow">Costs</span>
            <strong>{money(data.profile.monthlyCostsCents)}</strong>
          </article>
          <article className="data-card">
            <span className="eyebrow">Margin</span>
            <strong>{money(data.profile.monthlyMarginCents)}</strong>
            <span className="eyebrow">
              Worked out on read, never stored — so it cannot disagree with the two figures above.
            </span>
          </article>
          <article className="data-card">
            <span className="eyebrow">People employed</span>
            <strong>{data.profile.employsPeople ?? "—"}</strong>
          </article>
        </section>
      ) : null}

      <article className="data-card">
        <header>
          <h3>Current figures</h3>
        </header>
        <form onSubmit={save}>
          <label>
            Type of business
            <input
              maxLength={200}
              value={enterpriseType}
              disabled={saving}
              onChange={(event) => setEnterpriseType(event.target.value)}
              placeholder="e.g. poultry, cereal buying"
            />
          </label>
          <label>
            Money in each month (KES)
            <input
              inputMode="numeric"
              value={revenue}
              disabled={saving}
              onChange={(event) => setRevenue(event.target.value.replace(/[^\d.]/g, ""))}
            />
          </label>
          <label>
            Costs each month (KES)
            <input
              inputMode="numeric"
              value={costs}
              disabled={saving}
              onChange={(event) => setCosts(event.target.value.replace(/[^\d.]/g, ""))}
            />
          </label>
          <label>
            People it employs
            <input
              inputMode="numeric"
              value={employs}
              disabled={saving}
              onChange={(event) => setEmploys(event.target.value.replace(/\D/g, ""))}
            />
          </label>
          <label>
            Biggest problem they face
            <textarea
              maxLength={2000}
              rows={3}
              value={mainChallenge}
              disabled={saving}
              onChange={(event) => setMainChallenge(event.target.value)}
            />
          </label>
          <label>
            Support needed
            <textarea
              maxLength={2000}
              rows={3}
              value={supportNeeded}
              disabled={saving}
              onChange={(event) => setSupportNeeded(event.target.value)}
            />
          </label>
          <p className="eyebrow">
            Saving here corrects the current figures. It does not add a snapshot — only a
            visit does that, because a snapshot needs an occasion to belong to.
          </p>
          <button className="button" disabled={saving} type="submit">
            {saving ? "Saving…" : "Save"}
          </button>
        </form>
      </article>

      <article className="data-card">
        <header>
          <div>
            <h3>Recorded at each visit</h3>
            <span>
              {growth === null
                ? "Two visits with a revenue figure are needed before growth means anything."
                : `Revenue ${growth >= 0 ? "up" : "down"} ${formatKes(Math.abs(growth) / 100)} since the first recorded visit.`}
            </span>
          </div>
        </header>
        {chronological.length === 0 ? (
          <div className="empty-state">
            Nothing recorded against a visit yet. An agent fills this in on the phone during a
            visit, which is what gives each set of figures a date and an occasion.
          </div>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>Recorded</th>
                <th>Business</th>
                <th>Money in</th>
                <th>Costs</th>
                <th>Margin</th>
                <th>Employs</th>
              </tr>
            </thead>
            <tbody>
              {[...chronological].reverse().map((row) => (
                <tr key={`${row.visitId ?? "none"}-${row.recordedAt}`}>
                  <td>{new Date(row.recordedAt).toLocaleDateString()}</td>
                  <td>{row.enterpriseType ?? "—"}</td>
                  <td>{money(row.monthlyRevenueCents)}</td>
                  <td>{money(row.monthlyCostsCents)}</td>
                  <td>{money(row.monthlyMarginCents)}</td>
                  <td>{row.employsPeople ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </article>
    </section>
  );
}

/**
 * First to last recorded revenue, in cents, or null when fewer than two visits
 * carry a figure.
 *
 * Deliberately not "latest minus current profile": the profile can be edited
 * from this page without a visit, and comparing an office edit against a field
 * observation would report growth that nobody observed.
 */
function revenueGrowth(rows: HistoryRow[]): number | null {
  const withRevenue = rows.filter((row) => typeof row.monthlyRevenueCents === "number");
  if (withRevenue.length < 2) return null;
  return withRevenue[withRevenue.length - 1]!.monthlyRevenueCents! - withRevenue[0]!.monthlyRevenueCents!;
}

function toShillings(cents: number | null | undefined) {
  if (typeof cents !== "number") return "";
  return String(Math.round(cents / 100));
}

function toCents(value: string) {
  const shillings = Number.parseFloat(value.trim());
  return Number.isFinite(shillings) ? Math.round(shillings * 100) : null;
}

function money(cents: number | null | undefined) {
  if (typeof cents !== "number") return "—";
  return formatKes(cents / 100);
}

function formatKes(shillings: number) {
  return `KSh ${shillings.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
}
