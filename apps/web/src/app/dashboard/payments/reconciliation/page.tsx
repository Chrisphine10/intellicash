"use client";

import React from "react";
import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { AlertTriangle, ArrowLeft, Banknote, CheckCircle2, WalletCards } from "@/lib/theme-icons";
import { apiFetch, formatDateTime } from "../../../../lib/api";
import { StatCard } from "../../../../components/dashboard/stat-card";
import { DataTable, type DataTableColumn } from "../../../../components/dashboard/data-table";
import { DESTINATION_LABELS, kesExact, PROVIDER_LABELS, SETTLEMENT_LABELS, STATE_LABELS } from "../../../../features/payments/money";
import { NoteAction } from "../../../../features/payments/note-action";

/**
 * Online payments into groups, reconciled.
 *
 * Every figure is derived from the payment rows themselves. Collected money
 * splits exactly into the groups' money, IWL's fee and the provider's
 * charges; a row where it does not is listed as an exception. So is anything
 * that needs a person: a held payment, a verified one not yet in the books, a
 * payout whose outcome is unknown.
 */
interface PaymentRow {
  id: string;
  createdAt: string;
  groupId: string;
  group: { name: string; code: string };
  member: { fullName: string } | null;
  purpose: string;
  provider: string;
  collectionMode: string;
  amountCents: number;
  groupAmountCents: number;
  platformFeeCents: number;
  providerFeeCents: number;
  platformFeeStatus: string;
  state: string;
  settlementStatus: string;
  ledgerEntryId: string | null;
  internalReference: string;
  providerTransactionId: string | null;
  failureReason: string | null;
}

interface SettlementRow {
  id: string;
  createdAt: string;
  amountCents: number;
  status: string;
  provider: string;
  internalReference: string;
  providerReceipt: string | null;
  failureReason: string | null;
  group: { name: string; code: string };
  destination: { type: string; accountName: string; accountNumber: string };
  _count: { payments: number };
}

interface Report {
  range: { from: string; to: string };
  totals: {
    count: number;
    collectedCents: number;
    groupFundsCents: number;
    platformFeesCollectedCents: number;
    platformFeesReceivableCents: number;
    providerChargesCents: number;
    unsettledCents: number;
    settledCents: number;
  };
  payments: PaymentRow[];
  exceptions: Array<{ paymentId: string; reasons: string[] }>;
  settlements: SettlementRow[];
  automatedSettlement: boolean;
  autoApproveLimitCents: number;
}

const REASON_LABELS: Record<string, string> = {
  HELD: "Held — amounts did not agree",
  VERIFIED_NOT_POSTED: "Verified but not in the books",
  OPEN_TOO_LONG: "Waiting over 30 minutes",
  SETTLEMENT_UNKNOWN: "Payout outcome unknown",
  SETTLEMENT_FAILED: "Payout failed",
  NOT_SETTLED_48H: "Not paid to group after 48 h",
  IN_BOOKS_BUT_NOT_PAID: "In the books, but the payment did not go through",
  FEES_DO_NOT_ADD_UP: "Fees do not add up"
};

/**
 * The LOCAL calendar day. `toISOString()` gives the UTC day, which in Nairobi
 * is still "yesterday" until 03:00 — so the default range ended before
 * tonight's payments and the page showed nothing.
 */
function isoDay(date: Date) {
  const pad = (value: number) => String(value).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export default function PaymentReconciliationPage() {
  const [from, setFrom] = useState(isoDay(new Date(Date.now() - 30 * 86_400_000)));
  const [to, setTo] = useState(isoDay(new Date()));
  const [report, setReport] = useState<Report | null>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function load() {
    const end = new Date(`${to}T23:59:59`);
    setReport(
      await apiFetch<Report>(
        `/payment-admin/reconciliation?from=${encodeURIComponent(new Date(`${from}T00:00:00`).toISOString())}&to=${encodeURIComponent(end.toISOString())}`
      )
    );
  }

  useEffect(() => {
    setError(null);
    load().catch((e) => setError(e instanceof Error ? e.message : "Unable to load reconciliation."));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [from, to]);

  async function act(work: () => Promise<unknown>, success: string) {
    setBusy(true);
    setMessage(null);
    try {
      await work();
      await load();
      setMessage({ ok: true, text: success });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "That did not work." });
    } finally {
      setBusy(false);
    }
  }

  const byId = useMemo(() => new Map((report?.payments ?? []).map((row) => [row.id, row])), [report]);

  const columns: Array<DataTableColumn<PaymentRow>> = [
    { key: "date", header: "Date", value: (row) => row.createdAt, cell: (row) => formatDateTime(row.createdAt), sortable: true },
    { key: "group", header: "Group", value: (row) => row.group.name, searchable: true, sortable: true },
    { key: "member", header: "Member", value: (row) => row.member?.fullName ?? "—", searchable: true },
    { key: "provider", header: "Provider", value: (row) => PROVIDER_LABELS[row.provider] ?? row.provider },
    { key: "groupAmount", header: "To group", value: (row) => row.groupAmountCents, cell: (row) => kesExact(row.groupAmountCents), sortable: true },
    {
      key: "fees",
      header: "Fees",
      value: (row) => row.platformFeeCents + row.providerFeeCents,
      cell: (row) => `${kesExact(row.platformFeeCents)} + ${kesExact(row.providerFeeCents)}`
    },
    { key: "total", header: "Charged", value: (row) => row.amountCents, cell: (row) => kesExact(row.amountCents), sortable: true },
    { key: "state", header: "Payment", value: (row) => STATE_LABELS[row.state] ?? row.state },
    {
      key: "settlement",
      header: "Settlement",
      value: (row) => (row.collectionMode === "OWN_ACCOUNT" ? "Own account" : SETTLEMENT_LABELS[row.settlementStatus] ?? row.settlementStatus)
    },
    { key: "reference", header: "Reference", value: (row) => row.providerTransactionId ?? row.internalReference, searchable: true }
  ];

  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!report) return <div className="loading-panel">Loading reconciliation…</div>;

  const totals = report.totals;

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href="/dashboard/payments">
            <ArrowLeft size={17} />
            <span>Payments</span>
          </Link>
          <h2>Online payments reconciliation</h2>
          <p>
            Payments into groups through M-Pesa and Paystack.{" "}
            <Link href="/dashboard/settings/payments">Payment fees</Link>
          </p>
        </div>
        <WalletCards size={22} />
      </header>

      {message ? <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div> : null}
      {!report.automatedSettlement ? (
        <div className="dashboard-notice">
          Automatic payouts are switched off (ENABLE_AUTOMATED_SETTLEMENT). Settlement batches are still built so they can be
          reviewed here.
        </div>
      ) : null}

      <div className="dashboard-filter-row">
        <label>
          From
          <input type="date" value={from} onChange={(event) => setFrom(event.target.value)} />
        </label>
        <label>
          To
          <input type="date" value={to} onChange={(event) => setTo(event.target.value)} />
        </label>
      </div>

      <section className="stat-grid payments-stats">
        <StatCard icon={<WalletCards size={20} />} label="Collected" note={`${totals.count} payments`} value={kesExact(totals.collectedCents)} />
        <StatCard icon={<Banknote size={20} />} label="Group money" note="What groups receive" value={kesExact(totals.groupFundsCents)} />
        <StatCard
          icon={<CheckCircle2 size={20} />}
          label="IWL fees"
          note={`${kesExact(totals.platformFeesReceivableCents)} owed by own-account groups`}
          value={kesExact(totals.platformFeesCollectedCents)}
        />
        <StatCard icon={<Banknote size={20} />} label="Provider charges" value={kesExact(totals.providerChargesCents)} />
        <StatCard
          icon={<AlertTriangle size={20} />}
          label="Not yet paid to groups"
          note={`${kesExact(totals.settledCents)} paid out`}
          value={kesExact(totals.unsettledCents)}
        />
      </section>

      <article className="data-card">
        <header>
          <div>
            <h3>Needs attention ({report.exceptions.length})</h3>
            <p>Nothing here means every payment in the range reconciles.</p>
          </div>
        </header>
        {report.exceptions.length === 0 ? (
          <div className="empty-state">All clear.</div>
        ) : (
          <table>
            <thead>
              <tr>
                <th>Payment</th>
                <th>Why</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {report.exceptions.map((row) => {
                const payment = byId.get(row.paymentId);
                return (
                  <tr key={row.paymentId}>
                    <td>
                      {payment ? `${payment.group.name} · ${payment.member?.fullName ?? "—"} · ${kesExact(payment.amountCents)}` : row.paymentId}
                      {payment?.failureReason ? <small> — {payment.failureReason}</small> : null}
                    </td>
                    <td>{row.reasons.map((reason) => REASON_LABELS[reason] ?? reason).join("; ")}</td>
                    <td>
                      {row.reasons.includes("HELD") ? (
                        <>
                          <NoteAction
                            disabled={busy}
                            label="Release"
                            placeholder="e.g. seen on the M-Pesa statement"
                            primary
                            onSubmit={(note) =>
                              act(
                                () => apiFetch(`/payment-admin/payments/${row.paymentId}/release`, { method: "POST", body: JSON.stringify({ note }) }),
                                "Released and posted to the group's books."
                              )
                            }
                          />{" "}
                          <NoteAction
                            disabled={busy}
                            label="Reversed"
                            placeholder="e.g. the provider reversed it"
                            onSubmit={(note) =>
                              act(
                                () => apiFetch(`/payment-admin/payments/${row.paymentId}/reverse`, { method: "POST", body: JSON.stringify({ note }) }),
                                "Marked reversed."
                              )
                            }
                          />
                        </>
                      ) : null}
                      {row.reasons.includes("VERIFIED_NOT_POSTED") ? (
                        <button
                          className="button"
                          disabled={busy}
                          type="button"
                          onClick={() => act(() => apiFetch(`/payment-admin/payments/${row.paymentId}/repost`, { method: "POST" }), "Posting tried again.")}
                        >
                          Post again
                        </button>
                      ) : null}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </article>

      <article className="data-card">
        <header>
          <div>
            <h3>Payouts to groups</h3>
            <p>
              Batches above {kesExact(report.autoApproveLimitCents)} need an approval. A payout whose outcome is unknown is never
              sent again automatically — check it with the provider or resolve it with the receipt.
            </p>
          </div>
          <button
            className="button secondary"
            disabled={busy}
            type="button"
            onClick={() =>
              act(async () => {
                const result = await apiFetch<{ created: unknown[]; skipped: unknown[] }>("/payment-admin/settlements/build", { method: "POST" });
                return result;
              }, "Settlement batches rebuilt.")
            }
          >
            Build batches now
          </button>
        </header>
        {report.settlements.length === 0 ? (
          <div className="empty-state">No payouts in this range.</div>
        ) : (
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Group</th>
                <th>To</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Receipt</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {report.settlements.map((row) => (
                <tr key={row.id}>
                  <td>{formatDateTime(row.createdAt)}</td>
                  <td>{row.group.name}</td>
                  <td>
                    {DESTINATION_LABELS[row.destination.type] ?? row.destination.type} · {row.destination.accountName} ·{" "}
                    {row.destination.accountNumber}
                  </td>
                  <td>
                    {kesExact(row.amountCents)} <small>({row._count.payments} payments)</small>
                  </td>
                  <td>
                    {SETTLEMENT_LABELS[row.status] ?? row.status}
                    {row.failureReason ? <small> — {row.failureReason}</small> : null}
                  </td>
                  <td>{row.providerReceipt ?? "—"}</td>
                  <td>
                    {row.status === "AWAITING_APPROVAL" ? (
                      <button
                        className="button"
                        disabled={busy}
                        type="button"
                        onClick={() => act(() => apiFetch(`/payment-admin/settlements/${row.id}/approve`, { method: "POST" }), "Approved for payout.")}
                      >
                        Approve
                      </button>
                    ) : null}
                    {row.status === "QUEUED" && report.automatedSettlement ? (
                      <button
                        className="button"
                        disabled={busy}
                        type="button"
                        onClick={() => {
                          if (window.confirm(`Pay ${kesExact(row.amountCents)} to ${row.group.name} now?`)) {
                            act(() => apiFetch(`/payment-admin/settlements/${row.id}/pay`, { method: "POST" }), "Payout sent.");
                          }
                        }}
                      >
                        Pay now
                      </button>
                    ) : null}
                    {row.status === "UNKNOWN" || row.status === "PROCESSING" ? (
                      <>
                        <button
                          className="button secondary"
                          disabled={busy}
                          type="button"
                          onClick={() => act(() => apiFetch(`/payment-admin/settlements/${row.id}/check`, { method: "POST" }), "Asked the provider.")}
                        >
                          Check with provider
                        </button>{" "}
                        <NoteAction
                          disabled={busy}
                          extraField={{ label: "Provider receipt if the group WAS paid (leave blank if not)", placeholder: "e.g. SLK4H2X9Y1" }}
                          label="Resolve by hand"
                          placeholder="How did you confirm it?"
                          onSubmit={(note, receipt) =>
                            act(
                              () =>
                                apiFetch(`/payment-admin/settlements/${row.id}/resolve`, {
                                  method: "POST",
                                  body: JSON.stringify({
                                    outcome: receipt ? "SETTLED" : "FAILED",
                                    providerReceipt: receipt || undefined,
                                    note
                                  })
                                }),
                              receipt ? "Recorded as paid." : "Recorded as not paid; it can be sent again."
                            )
                          }
                        />
                      </>
                    ) : null}
                    {row.status === "FAILED" ? (
                      <button
                        className="button secondary"
                        disabled={busy}
                        type="button"
                        onClick={() => act(() => apiFetch(`/payment-admin/settlements/${row.id}/requeue`, { method: "POST" }), "Back in the queue.")}
                      >
                        Send again
                      </button>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </article>

      <DataTable
        columns={columns}
        defaultSort={{ key: "date", direction: "desc" }}
        exportName="online-payments"
        getRowKey={(row) => row.id}
        rows={report.payments}
        title="All online payments"
      />
    </section>
  );
}
