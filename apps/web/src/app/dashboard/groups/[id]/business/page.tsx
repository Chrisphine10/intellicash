"use client";

import type { FormEvent } from "react";
import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, Building2 } from "@/lib/theme-icons";
import { apiFetch } from "../../../../../lib/api";

/**
 * The businesses a group runs.
 *
 * A group may run several — a poultry unit and a cereal store have different
 * margins, different buyers and different needs, and a single profile forced
 * whoever captured the second to overwrite the first.
 *
 * Each enterprise keeps its current figures on top and one snapshot per visit
 * underneath. That split is the point: editing the revenue figure would
 * otherwise destroy the previous one, and "did this business grow between
 * visits" is the only question the history exists to answer.
 *
 * Amounts are held in cents on the wire, matching the ledger. This page is the
 * only place they become shillings, and only for the eye.
 */
interface SupportNeed {
  id: string;
  needKeySnapshot: string;
  needTitleSnapshot: string;
  needCategorySnapshot: string;
  priority: string;
  status: string;
  detail: string | null;
  raisedAt: string;
  metAt: string | null;
}

interface EnterpriseFigures {
  id: string;
  name: string;
  enterpriseType: string | null;
  description: string | null;
  monthlyRevenueCents: number | null;
  monthlyCostsCents: number | null;
  monthlyMarginCents: number | null;
  employsPeople: number | null;
  marketReach: string | null;
  marketReachLabel: string | null;
  marketReachStep: number | null;
  buyerCount: number | null;
  marketChannels: { key: string; label: string | null }[];
  hasFormalBuyerAgreement: boolean | null;
  salesMonths: number[];
  mainChallenge: string | null;
  supportNeeded: string | null;
  status: string;
}

interface HistoryRow extends EnterpriseFigures {
  visitId: string | null;
  recordedAt: string;
}

interface Enterprise extends EnterpriseFigures {
  supportNeeds: SupportNeed[];
  history: HistoryRow[];
}

interface EnterprisesResponse {
  group: { id: string; name: string; code: string };
  enterprises: Enterprise[];
  recorded: boolean;
}

interface Reference {
  marketReach: { key: string; label: string; step: number }[];
  marketChannels: { key: string; label: string }[];
  supportNeedTypes: { key: string; title: string; category: string }[];
  priorities: string[];
  needStatuses: string[];
}

const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

const BLANK = {
  name: "",
  enterpriseType: "",
  revenue: "",
  costs: "",
  employs: "",
  marketReach: "",
  buyerCount: "",
  channels: [] as string[],
  formalAgreement: "" as "" | "yes" | "no",
  salesMonths: [] as number[],
  mainChallenge: "",
  supportNeeded: ""
};

export default function GroupEnterprisesPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [data, setData] = useState<EnterprisesResponse | null>(null);
  const [reference, setReference] = useState<Reference | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);

  /** Null when the form is closed; an id when editing; "new" when adding. */
  const [editing, setEditing] = useState<string | null>(null);
  const [form, setForm] = useState({ ...BLANK });

  async function load() {
    const [enterprises, ref] = await Promise.all([
      apiFetch<EnterprisesResponse>(`/groups/${id}/enterprises`),
      apiFetch<Reference>("/enterprise-reference")
    ]);
    setData(enterprises);
    setReference(ref);
  }

  useEffect(() => {
    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load the enterprises."))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  function openNew() {
    setForm({ ...BLANK });
    setEditing("new");
    setMessage(null);
  }

  function openEdit(enterprise: Enterprise) {
    setForm({
      name: enterprise.name,
      enterpriseType: enterprise.enterpriseType ?? "",
      revenue: toShillings(enterprise.monthlyRevenueCents),
      costs: toShillings(enterprise.monthlyCostsCents),
      employs: enterprise.employsPeople === null ? "" : String(enterprise.employsPeople),
      marketReach: enterprise.marketReach ?? "",
      buyerCount: enterprise.buyerCount === null ? "" : String(enterprise.buyerCount),
      channels: enterprise.marketChannels.map((channel) => channel.key),
      formalAgreement:
        enterprise.hasFormalBuyerAgreement === null
          ? ""
          : enterprise.hasFormalBuyerAgreement
            ? "yes"
            : "no",
      salesMonths: enterprise.salesMonths,
      mainChallenge: enterprise.mainChallenge ?? "",
      supportNeeded: enterprise.supportNeeded ?? ""
    });
    setEditing(enterprise.id);
    setMessage(null);
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    setMessage(null);
    try {
      const body = JSON.stringify({
        name: form.name.trim(),
        enterpriseType: form.enterpriseType.trim() || null,
        monthlyRevenueCents: toCents(form.revenue),
        monthlyCostsCents: toCents(form.costs),
        employsPeople: form.employs.trim() === "" ? null : Number(form.employs),
        marketReach: form.marketReach || null,
        buyerCount: form.buyerCount.trim() === "" ? null : Number(form.buyerCount),
        marketChannels: form.channels,
        // Empty stays null: "not asked" is a different fact from "no
        // agreement", and collapsing them would report every unasked
        // enterprise as informal.
        hasFormalBuyerAgreement: form.formalAgreement === "" ? null : form.formalAgreement === "yes",
        salesMonths: form.salesMonths,
        mainChallenge: form.mainChallenge.trim() || null,
        supportNeeded: form.supportNeeded.trim() || null
        // No visitId from the console: an office edit is a correction to the
        // current figures, not a new observation made on an occasion. Only the
        // agent standing with the group creates a snapshot.
      });

      if (editing === "new") {
        await apiFetch(`/groups/${id}/enterprises`, { method: "POST", body });
      } else {
        await apiFetch(`/enterprises/${editing}`, { method: "PATCH", body });
      }

      await load();
      setEditing(null);
      setMessage({ ok: true, text: "Saved." });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not save." });
    } finally {
      setSaving(false);
    }
  }

  async function addNeed(enterpriseId: string, needKey: string, priority: string) {
    if (!needKey) return;
    try {
      await apiFetch(`/enterprises/${enterpriseId}/support-needs`, {
        method: "POST",
        body: JSON.stringify({ needKey, priority })
      });
      await load();
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not add the need." });
    }
  }

  async function setNeedStatus(needId: string, status: string) {
    try {
      await apiFetch(`/support-needs/${needId}`, {
        method: "PATCH",
        body: JSON.stringify({ status })
      });
      await load();
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not update the need." });
    }
  }

  function toggle(list: string[], value: string) {
    return list.includes(value) ? list.filter((entry) => entry !== value) : [...list, value];
  }

  if (loading) return <div className="loading-panel">Loading enterprises…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!data) return <div className="empty-state">No enterprises.</div>;

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href={`/dashboard/groups/${id}`}>
            <ArrowLeft size={15} />
            Back to {data.group.name}
          </Link>
          <h2>
            <Building2 size={19} /> Enterprises
          </h2>
          <p className="eyebrow">
            The businesses this group runs together. Each one keeps its own figures and its
            own history, because a poultry unit and a cereal store do not share a margin.
          </p>
        </div>
        <button className="button" onClick={openNew} type="button">
          Add an enterprise
        </button>
      </header>

      {message ? (
        <div className={message.ok ? "notice success" : "notice warning"}>{message.text}</div>
      ) : null}

      {editing ? (
        <form className="data-card" onSubmit={save}>
          <h3>{editing === "new" ? "New enterprise" : "Edit enterprise"}</h3>

          <label>
            What is it called
            <input
              onChange={(event) => setForm({ ...form, name: event.target.value })}
              placeholder="Poultry unit"
              required
              value={form.name}
            />
          </label>

          <div className="form-grid">
            <label>
              Kind of business
              <input
                onChange={(event) => setForm({ ...form, enterpriseType: event.target.value })}
                placeholder="Poultry"
                value={form.enterpriseType}
              />
            </label>
            <label>
              People employed
              <input
                min={0}
                onChange={(event) => setForm({ ...form, employs: event.target.value })}
                type="number"
                value={form.employs}
              />
            </label>
            <label>
              Monthly revenue (KSh)
              <input
                min={0}
                onChange={(event) => setForm({ ...form, revenue: event.target.value })}
                type="number"
                value={form.revenue}
              />
            </label>
            <label>
              Monthly costs (KSh)
              <input
                min={0}
                onChange={(event) => setForm({ ...form, costs: event.target.value })}
                type="number"
                value={form.costs}
              />
            </label>
          </div>

          <h4>Market coverage</h4>
          <p className="eyebrow">
            How far what they produce actually travels, and to how many buyers. Revenue
            rising against a single buyer is growth and concentration at the same time, and
            only one of those is good news.
          </p>

          <div className="form-grid">
            <label>
              How far it reaches
              <select
                onChange={(event) => setForm({ ...form, marketReach: event.target.value })}
                value={form.marketReach}
              >
                <option value="">Not asked</option>
                {reference?.marketReach.map((rung) => (
                  <option key={rung.key} value={rung.key}>
                    {rung.label}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Buyers last month
              <input
                min={0}
                onChange={(event) => setForm({ ...form, buyerCount: event.target.value })}
                type="number"
                value={form.buyerCount}
              />
            </label>
            <label>
              Written buyer agreement
              <select
                onChange={(event) =>
                  setForm({ ...form, formalAgreement: event.target.value as "" | "yes" | "no" })
                }
                value={form.formalAgreement}
              >
                {/* Left blank on purpose until somebody asks. Recording "no"
                    for an enterprise nobody asked would report a gap that has
                    not been measured. */}
                <option value="">Not asked</option>
                <option value="yes">Yes, in writing</option>
                <option value="no">No, informal</option>
              </select>
            </label>
          </div>

          <fieldset className="chip-fieldset">
            <legend>How they sell</legend>
            <div className="chip-row">
              {reference?.marketChannels.map((channel) => (
                <label className="chip-check" key={channel.key}>
                  <input
                    checked={form.channels.includes(channel.key)}
                    onChange={() => setForm({ ...form, channels: toggle(form.channels, channel.key) })}
                    type="checkbox"
                  />
                  {channel.label}
                </label>
              ))}
            </div>
          </fieldset>

          <fieldset className="chip-fieldset">
            <legend>Months they sell in</legend>
            {/* A seasonal business compared month-on-month reads as collapsing.
                Recording the season is what stops a quiet month being mistaken
                for a failing enterprise. */}
            <div className="chip-row">
              {MONTHS.map((label, index) => {
                const month = index + 1;
                return (
                  <label className="chip-check" key={label}>
                    <input
                      checked={form.salesMonths.includes(month)}
                      onChange={() =>
                        setForm({
                          ...form,
                          salesMonths: form.salesMonths.includes(month)
                            ? form.salesMonths.filter((entry) => entry !== month)
                            : [...form.salesMonths, month]
                        })
                      }
                      type="checkbox"
                    />
                    {label}
                  </label>
                );
              })}
            </div>
          </fieldset>

          <label>
            Biggest challenge
            <textarea
              onChange={(event) => setForm({ ...form, mainChallenge: event.target.value })}
              rows={2}
              value={form.mainChallenge}
            />
          </label>
          <label>
            Anything else they asked for
            <textarea
              onChange={(event) => setForm({ ...form, supportNeeded: event.target.value })}
              rows={2}
              value={form.supportNeeded}
            />
            <span className="eyebrow">
              Use the support-need list below for anything that should be counted across the
              programme. This box is for what the list could not hold.
            </span>
          </label>

          <div className="form-actions">
            <button className="button" disabled={saving} type="submit">
              {saving ? "Saving…" : "Save"}
            </button>
            <button
              className="button secondary"
              onClick={() => setEditing(null)}
              type="button"
            >
              Cancel
            </button>
          </div>
        </form>
      ) : null}

      {data.enterprises.length === 0 && !editing ? (
        <div className="empty-state">
          This group has not been asked about its businesses yet. That is not the same as
          having none.
        </div>
      ) : null}

      {data.enterprises.map((enterprise) => (
        <EnterpriseCard
          enterprise={enterprise}
          key={enterprise.id}
          onAddNeed={addNeed}
          onEdit={() => openEdit(enterprise)}
          onNeedStatus={setNeedStatus}
          reference={reference}
        />
      ))}
    </section>
  );
}

function EnterpriseCard({
  enterprise,
  onAddNeed,
  onEdit,
  onNeedStatus,
  reference
}: {
  enterprise: Enterprise;
  onAddNeed: (enterpriseId: string, needKey: string, priority: string) => void;
  onEdit: () => void;
  onNeedStatus: (needId: string, status: string) => void;
  reference: Reference | null;
}) {
  const [needKey, setNeedKey] = useState("");
  const [priority, setPriority] = useState("MEDIUM");

  // Oldest to newest, so growth reads left to right the way a series should.
  const chronological = [...enterprise.history].sort(
    (a, b) => new Date(a.recordedAt).getTime() - new Date(b.recordedAt).getTime()
  );
  const growth = revenueGrowth(chronological);

  return (
    <article
      className={`enterprise-card ${enterprise.status === "CLOSED" ? "is-closed" : ""}`}
    >
      <header className="enterprise-head">
        <div>
          <h3>{enterprise.name}</h3>
          <p className="eyebrow">
            {enterprise.enterpriseType ?? "Kind not recorded"}
            {enterprise.status === "ACTIVE" ? "" : ` · ${enterprise.status.toLowerCase()}`}
          </p>
        </div>
        <button className="button secondary" onClick={onEdit} type="button">
          Edit
        </button>
      </header>

      <div className="enterprise-figures">
        <div className="enterprise-figure">
          <span className="label">Monthly revenue</span>
          <span className="value">{money(enterprise.monthlyRevenueCents)}</span>
        </div>
        <div className="enterprise-figure">
          <span className="label">Monthly costs</span>
          <span className="value">{money(enterprise.monthlyCostsCents)}</span>
        </div>
        <div className="enterprise-figure">
          <span className="label">Margin</span>
          <span className="value">{money(enterprise.monthlyMarginCents)}</span>
        </div>
        <div className="enterprise-figure">
          <span className="label">Employs</span>
          <span className="value">{enterprise.employsPeople ?? "—"}</span>
        </div>
        <div className="enterprise-figure">
          <span className="label">Reaches</span>
          <span className="value">{enterprise.marketReachLabel ?? "Not asked"}</span>
        </div>
        <div className="enterprise-figure">
          <span className="label">Buyers</span>
          <span className="value">{enterprise.buyerCount ?? "—"}</span>
        </div>
        <div className="enterprise-figure">
          <span className="label">Buyer agreement</span>
          <span className="value">
            {enterprise.hasFormalBuyerAgreement === null
              ? "Not asked"
              : enterprise.hasFormalBuyerAgreement
                ? "In writing"
                : "Informal"}
          </span>
        </div>
        <div className="enterprise-figure">
          <span className="label">Revenue since first visit</span>
          <span className="value">
            {growth === null ? "No baseline yet" : money(growth)}
          </span>
        </div>
      </div>

      {enterprise.marketChannels.length > 0 ? (
        <div className="chip-row">
          {enterprise.marketChannels.map((channel) => (
            <span className="pill blue" key={channel.key}>
              {channel.label ?? channel.key}
            </span>
          ))}
        </div>
      ) : null}

      {enterprise.salesMonths.length > 0 && enterprise.salesMonths.length < 12 ? (
        <p className="eyebrow">
          Sells in {enterprise.salesMonths.map((month) => MONTHS[month - 1]).join(", ")}. A
          quiet month outside that season is not a fall.
        </p>
      ) : null}

      {enterprise.mainChallenge ? (
        <p className="eyebrow">Biggest challenge: {enterprise.mainChallenge}</p>
      ) : null}

      <h4>Support needed</h4>
      {enterprise.supportNeeds.length === 0 ? (
        <p className="eyebrow">Nothing recorded against this enterprise yet.</p>
      ) : (
        <table className="data-table">
          <thead>
            <tr>
              <th>Need</th>
              <th>Category</th>
              <th>Priority</th>
              <th>Status</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {enterprise.supportNeeds.map((need) => (
              <tr key={need.id}>
                {/* The snapshotted title, so the row still reads if the type is
                    later retired from the list. */}
                <td>
                  {need.needTitleSnapshot}
                  {need.detail ? <p className="eyebrow">{need.detail}</p> : null}
                </td>
                <td>{need.needCategorySnapshot.toLowerCase()}</td>
                <td>
                  <span className={need.priority === "HIGH" ? "pill red" : "pill"}>
                    {need.priority.toLowerCase()}
                  </span>
                </td>
                <td>{need.status.replace("_", " ").toLowerCase()}</td>
                <td>
                  {need.status === "MET" ? (
                    <button
                      className="link-button"
                      onClick={() => onNeedStatus(need.id, "OPEN")}
                      type="button"
                    >
                      Reopen
                    </button>
                  ) : (
                    <button
                      className="link-button"
                      onClick={() => onNeedStatus(need.id, "MET")}
                      type="button"
                    >
                      Mark met
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      <div className="form-actions">
        <select onChange={(event) => setNeedKey(event.target.value)} value={needKey}>
          <option value="">Add a support need…</option>
          {reference?.supportNeedTypes.map((type) => (
            <option key={type.key} value={type.key}>
              {type.category.toLowerCase()} — {type.title}
            </option>
          ))}
        </select>
        <select onChange={(event) => setPriority(event.target.value)} value={priority}>
          {reference?.priorities.map((entry) => (
            <option key={entry} value={entry}>
              {entry.toLowerCase()}
            </option>
          ))}
        </select>
        <button
          className="button secondary"
          disabled={!needKey}
          onClick={() => {
            onAddNeed(enterprise.id, needKey, priority);
            setNeedKey("");
          }}
          type="button"
        >
          Add
        </button>
      </div>

      {chronological.length > 0 ? (
        <>
          <h4>What was recorded at each visit</h4>
          <table className="data-table">
            <thead>
              <tr>
                <th>When</th>
                <th>Revenue</th>
                <th>Costs</th>
                <th>Reaches</th>
                <th>Buyers</th>
              </tr>
            </thead>
            <tbody>
              {chronological.map((row) => (
                <tr key={row.visitId ?? row.recordedAt}>
                  <td>{new Date(row.recordedAt).toLocaleDateString()}</td>
                  <td>{money(row.monthlyRevenueCents)}</td>
                  <td>{money(row.monthlyCostsCents)}</td>
                  <td>{row.marketReachLabel ?? "—"}</td>
                  <td>{row.buyerCount ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </>
      ) : null}
    </article>
  );
}

/**
 * Change in revenue from the first dated reading to the latest.
 *
 * Null with fewer than two readings — there is no baseline, which is a
 * different fact from no growth and must not be shown as zero.
 */
function revenueGrowth(rows: HistoryRow[]): number | null {
  const withRevenue = rows.filter((row) => typeof row.monthlyRevenueCents === "number");
  if (withRevenue.length < 2) return null;
  return (
    withRevenue[withRevenue.length - 1]!.monthlyRevenueCents! - withRevenue[0]!.monthlyRevenueCents!
  );
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
