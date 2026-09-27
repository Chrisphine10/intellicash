"use client";

import React from "react";
import type { FormEvent } from "react";
import { useCallback, useEffect, useState } from "react";
import { apiFetch, formatDate, formatDateTime } from "../../lib/api";
import { DESTINATION_LABELS, kesExact, SETTLEMENT_LABELS } from "./money";
import { NoteAction } from "./note-action";

/**
 * A group's payment setup, in the pieces the Payments page lays out:
 * a status strip, the payment options (which providers are on, where money
 * is collected, passbook payments) and the settlement account.
 *
 * A settlement account is proposed here and approved by a DIFFERENT IWL
 * admin; nothing is paid to it for a cooling-off period after approval.
 */
export interface Destination {
  id: string;
  type: string;
  provider: string;
  accountNumber: string;
  accountName: string;
  accountReference: string | null;
  bankCode: string | null;
  status: string;
  proposedById: string | null;
  verifiedAt: string | null;
  retiredAt: string | null;
  note: string | null;
  createdAt: string;
}

interface Settlement {
  id: string;
  amountCents: number;
  status: string;
  internalReference: string;
  providerReceipt: string | null;
  createdAt: string;
  settledAt: string | null;
  failureReason: string | null;
}

export interface SettingsResponse {
  settings: {
    collectionMode: "SYSTEM" | "OWN_ACCOUNT" | null;
    explicit: boolean;
    enabledProviders: string[];
    memberSelfPayEnabled: boolean;
    ownCredentialProviders: string[];
  };
  destinations: Destination[];
  activeDestinationReady: boolean;
  activeDestinationNote: string | null;
  coolOffUntil: string | null;
  settlements: Settlement[];
  automatedSettlement: boolean;
}

type Message = { ok: boolean; text: string } | null;

/** Loads a group's payment settings and runs actions against them. */
export function useGroupPaymentSettings(groupId: string) {
  const [data, setData] = useState<SettingsResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<Message>(null);
  const [busy, setBusy] = useState(false);
  const [isPlatformAdmin, setIsPlatformAdmin] = useState(false);

  const reload = useCallback(async () => {
    setData(await apiFetch<SettingsResponse>(`/groups/${groupId}/payment-settings`));
  }, [groupId]);

  useEffect(() => {
    apiFetch<{ role: string }>("/auth/me")
      .then((me) => setIsPlatformAdmin(me.role === "IWL_ADMIN"))
      .catch(() => undefined);
    reload().catch((e) => setError(e instanceof Error ? e.message : "Unable to load payment settings."));
  }, [reload]);

  const act = useCallback(
    async (work: () => Promise<unknown>, success: string) => {
      setBusy(true);
      setMessage(null);
      try {
        await work();
        await reload();
        setMessage({ ok: true, text: success });
      } catch (e) {
        setMessage({ ok: false, text: e instanceof Error ? e.message : "That did not work." });
      } finally {
        setBusy(false);
      }
    },
    [reload]
  );

  return { data, error, message, busy, isPlatformAdmin, act, reload };
}

export type PaymentSettingsState = ReturnType<typeof useGroupPaymentSettings>;

function effectiveMode(data: SettingsResponse) {
  return data.settings.collectionMode ?? (data.settings.ownCredentialProviders.length > 0 ? "OWN_ACCOUNT" : "SYSTEM");
}

/** One line of status pills: what a treasurer needs to know at a glance. */
export function PaymentStatusStrip({ data }: { data: SettingsResponse }) {
  const on = (provider: string) => data.settings.enabledProviders.includes(provider);
  const active = data.destinations.find((row) => row.status === "ACTIVE");
  const waiting = data.destinations.some((row) => row.status === "PROPOSED");
  const mode = effectiveMode(data);
  return (
    <div className="payment-status-strip" aria-label="Payment status">
      <span className={`status-pill ${on("MPESA_DARAJA") ? "is-on" : "is-off"}`}>
        <img alt="" height={16} src="/brand/payments/mpesa.png" /> M-Pesa {on("MPESA_DARAJA") ? "on" : "off"}
      </span>
      <span className={`status-pill ${on("PAYSTACK") ? "is-on" : "is-off"}`}>
        <img alt="" height={16} src="/brand/payments/paystack.png" /> Paystack {on("PAYSTACK") ? "on" : "off"}
      </span>
      <span className="status-pill">{mode === "OWN_ACCOUNT" ? "Collected into the group's own account" : "Collected by Intelli-Cash"}</span>
      {mode === "SYSTEM" ? (
        <span className={`status-pill ${active ? (data.activeDestinationReady ? "is-on" : "is-wait") : "is-off"}`}>
          {active
            ? data.activeDestinationReady
              ? "Settlement account approved"
              : "Settlement account cooling off"
            : waiting
              ? "Settlement account waiting for approval"
              : "No settlement account"}
        </span>
      ) : null}
      <span className={`status-pill ${data.settings.memberSelfPayEnabled ? "is-on" : "is-off"}`}>
        Passbook payments {data.settings.memberSelfPayEnabled ? "on" : "off"}
      </span>
    </div>
  );
}

const PROVIDER_ROWS = [
  { value: "MPESA_DARAJA", label: "M-Pesa", logo: "/brand/payments/mpesa.png", note: "A prompt on the member's phone. No code to type." },
  { value: "PAYSTACK", label: "Paystack", logo: "/brand/payments/paystack.png", note: "Card or mobile money, by link." }
];

/** Which providers are on, where money is collected, and passbook payments. */
export function PaymentOptionsCard({
  groupId,
  state,
  canConfigure
}: {
  groupId: string;
  state: PaymentSettingsState;
  canConfigure: boolean;
}) {
  const { data, busy, act } = state;
  const [mode, setMode] = useState<"SYSTEM" | "OWN_ACCOUNT">("SYSTEM");
  const [providers, setProviders] = useState<string[]>([]);
  const [selfPay, setSelfPay] = useState(false);

  useEffect(() => {
    if (!data) return;
    setMode(effectiveMode(data));
    setProviders(data.settings.enabledProviders);
    setSelfPay(data.settings.memberSelfPayEnabled);
  }, [data]);

  if (!data) return null;
  const dirty =
    mode !== effectiveMode(data) ||
    selfPay !== data.settings.memberSelfPayEnabled ||
    [...providers].sort().join() !== [...data.settings.enabledProviders].sort().join();

  function save(event: FormEvent) {
    event.preventDefault();
    act(
      () =>
        apiFetch(`/groups/${groupId}/payment-settings`, {
          method: "PUT",
          body: JSON.stringify({ collectionMode: mode, enabledProviders: providers, memberSelfPayEnabled: selfPay })
        }),
      providers.length === 0
        ? "Saved. Online payments are off; cash and M-Pesa Classic still work."
        : "Payment options saved."
    );
  }

  return (
    <form className="payment-options" onSubmit={save}>
      <article className="data-card">
        <header>
          <div>
            <h3>Online payments</h3>
            <p>
              Switch each provider on or off for this group. Cash and M-Pesa Classic (the member pays the Paybill and the
              treasurer types the code) always work.
            </p>
          </div>
        </header>
        <div className="provider-switches">
          {PROVIDER_ROWS.map((row) => {
            const on = providers.includes(row.value);
            const missingOwn = mode === "OWN_ACCOUNT" && !data.settings.ownCredentialProviders.includes(row.value);
            return (
              <label className={`provider-switch ${on ? "is-on" : ""}`} key={row.value}>
                <img alt={row.label} className="provider-switch-logo" src={row.logo} />
                <span className="provider-switch-text">
                  <strong>{row.label}</strong>
                  <small>{missingOwn && on ? "Add the group's own details under Own provider accounts." : row.note}</small>
                </span>
                <input
                  aria-label={`${row.label} ${on ? "on" : "off"}`}
                  checked={on}
                  className="toggle"
                  disabled={!canConfigure || busy}
                  role="switch"
                  type="checkbox"
                  onChange={(event) =>
                    setProviders(
                      event.target.checked ? [...providers, row.value] : providers.filter((item) => item !== row.value)
                    )
                  }
                />
              </label>
            );
          })}
        </div>
      </article>

      <article className="data-card">
        <header>
          <div>
            <h3>Where the money goes</h3>
          </div>
        </header>
        <div className="choice-cards">
          <label className={`choice-card ${mode === "SYSTEM" ? "is-selected" : ""}`}>
            <input
              checked={mode === "SYSTEM"}
              disabled={!canConfigure || busy}
              name="mode"
              type="radio"
              onChange={() => setMode("SYSTEM")}
            />
            <span>
              <strong>Intelli-Cash collects, then pays the group</strong>
              <small>Members pay into IWL&apos;s account; the group&apos;s money is paid to its settlement account.</small>
            </span>
          </label>
          <label className={`choice-card ${mode === "OWN_ACCOUNT" ? "is-selected" : ""}`}>
            <input
              checked={mode === "OWN_ACCOUNT"}
              disabled={!canConfigure || busy}
              name="mode"
              type="radio"
              onChange={() => setMode("OWN_ACCOUNT")}
            />
            <span>
              <strong>Straight into the group&apos;s own account</strong>
              <small>
                Uses the group&apos;s own M-Pesa / Paystack details. The IWL fee inside these payments is then owed by the
                group.
              </small>
            </span>
          </label>
        </div>
      </article>

      <article className="data-card">
        <label className={`provider-switch ${selfPay ? "is-on" : ""}`}>
          <span className="provider-switch-text">
            <strong>Members may pay from their own passbook</strong>
            <small>Shares, welfare, fines and loan repayments, by M-Pesa or Paystack. Posted to the books once confirmed.</small>
          </span>
          <input
            aria-label={`Passbook payments ${selfPay ? "on" : "off"}`}
            checked={selfPay}
            className="toggle"
            disabled={!canConfigure || busy}
            role="switch"
            type="checkbox"
            onChange={(event) => setSelfPay(event.target.checked)}
          />
        </label>
      </article>

      {canConfigure ? (
        <div className="form-actions">
          <button className="button" disabled={busy || !dirty} type="submit">
            {busy ? "Saving…" : "Save payment options"}
          </button>
          {dirty ? <span className="form-actions-note">You have unsaved changes.</span> : null}
        </div>
      ) : (
        <div className="dashboard-notice">Only an IWL admin or the group&apos;s own account can change these.</div>
      )}
    </form>
  );
}

const emptyDestination = { type: "MPESA_PAYBILL", accountNumber: "", accountName: "", accountReference: "", bankCode: "", note: "" };

/** The account IWL pays the group's money into, and the payouts made. */
export function SettlementAccountCard({
  groupId,
  state,
  canConfigure
}: {
  groupId: string;
  state: PaymentSettingsState;
  canConfigure: boolean;
}) {
  const { data, busy, act, isPlatformAdmin } = state;
  const [destination, setDestination] = useState(emptyDestination);
  const [proposing, setProposing] = useState(false);
  if (!data) return null;

  const active = data.destinations.find((row) => row.status === "ACTIVE") ?? null;
  const proposed = data.destinations.filter((row) => row.status === "PROPOSED");
  const history = data.destinations.filter((row) => row.status !== "ACTIVE" && row.status !== "PROPOSED");

  function propose(event: FormEvent) {
    event.preventDefault();
    act(async () => {
      await apiFetch(`/groups/${groupId}/settlement-destinations`, {
        method: "POST",
        body: JSON.stringify({
          type: destination.type,
          accountNumber: destination.accountNumber,
          accountName: destination.accountName,
          accountReference: destination.accountReference || undefined,
          bankCode: destination.bankCode || undefined,
          note: destination.note || undefined
        })
      });
      setDestination(emptyDestination);
      setProposing(false);
    }, "Proposed. Another IWL administrator must approve it before any money is paid there.");
  }

  const describe = (row: Destination) =>
    `${DESTINATION_LABELS[row.type] ?? row.type} · ${row.accountName} · ${row.accountNumber}${
      row.accountReference ? ` (account ${row.accountReference})` : ""
    }`;

  return (
    <>
      {effectiveMode(data) === "OWN_ACCOUNT" ? (
        <div className="dashboard-notice">
          This group collects into its own account, so nothing is settled. A settlement account is only used when
          Intelli-Cash collects.
        </div>
      ) : null}
      <article className="data-card">
        <header>
          <div>
            <h3>Settlement account</h3>
            <p>
              Where IWL pays this group&apos;s money.{" "}
              {data.automatedSettlement ? "Payouts run automatically." : "Automatic payouts are switched off platform-wide."}
            </p>
          </div>
        </header>

        {active ? (
          <div className="account-summary">
            <div>
              <strong>{describe(active)}</strong>
              <small>
                Approved {formatDate(active.verifiedAt)}
                {!data.activeDestinationReady && data.coolOffUntil
                  ? ` · no payouts until ${formatDateTime(data.coolOffUntil)} (cooling off after a change)`
                  : ""}
              </small>
            </div>
            {canConfigure ? (
              <button
                className="button secondary"
                disabled={busy}
                type="button"
                onClick={() => {
                  if (window.confirm("Stop all payouts to this account? A new one must then be proposed and approved.")) {
                    act(() => apiFetch(`/settlement-destinations/${active.id}/retire`, { method: "POST" }), "Account retired.");
                  }
                }}
              >
                Retire
              </button>
            ) : null}
          </div>
        ) : (
          <div className="empty-state">No approved settlement account. Collected money waits until one is approved.</div>
        )}

        {proposed.map((row) => (
          <div className="account-summary is-waiting" key={row.id}>
            <div>
              <strong>Waiting for approval: {describe(row)}</strong>
              <small>
                Proposed {formatDate(row.createdAt)}
                {row.note ? ` · ${row.note}` : ""}
              </small>
            </div>
            {isPlatformAdmin ? (
              <div className="form-actions">
                <button
                  className="button"
                  disabled={busy}
                  type="button"
                  onClick={() =>
                    act(
                      () => apiFetch(`/settlement-destinations/${row.id}/approve`, { method: "POST" }),
                      "Approved. Payouts start after the cooling-off period."
                    )
                  }
                >
                  Approve
                </button>
                <NoteAction
                  disabled={busy}
                  label="Reject"
                  placeholder="e.g. the name does not match the group"
                  onSubmit={(note) =>
                    act(
                      () =>
                        apiFetch(`/settlement-destinations/${row.id}/reject`, { method: "POST", body: JSON.stringify({ note }) }),
                      "Rejected."
                    )
                  }
                />
              </div>
            ) : null}
          </div>
        ))}

        {canConfigure && !proposing ? (
          <div className="form-actions">
            <button className="button secondary" type="button" onClick={() => setProposing(true)}>
              {active ? "Propose a different account" : "Propose a settlement account"}
            </button>
          </div>
        ) : null}

        {canConfigure && proposing ? (
          <form className="stacked-form" onSubmit={propose}>
            <label>
              Account type
              <select value={destination.type} onChange={(event) => setDestination({ ...destination, type: event.target.value })}>
                {Object.entries(DESTINATION_LABELS).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </label>
            <label>
              {destination.type === "MPESA_PHONE"
                ? "Phone number"
                : destination.type === "MPESA_PAYBILL"
                  ? "Paybill number"
                  : destination.type === "MPESA_TILL"
                    ? "Till number"
                    : "Account number"}
              <input
                required
                value={destination.accountNumber}
                onChange={(event) => setDestination({ ...destination, accountNumber: event.target.value })}
              />
            </label>
            {destination.type === "MPESA_PAYBILL" ? (
              <label>
                Account number at the paybill
                <input
                  required
                  value={destination.accountReference}
                  onChange={(event) => setDestination({ ...destination, accountReference: event.target.value })}
                />
              </label>
            ) : null}
            {destination.type.startsWith("PAYSTACK_") ? (
              <label>
                Bank or operator code (from Paystack)
                <input
                  required
                  value={destination.bankCode}
                  onChange={(event) => setDestination({ ...destination, bankCode: event.target.value })}
                />
              </label>
            ) : null}
            <label>
              Account name, exactly as the bank or M-Pesa shows it
              <input
                required
                value={destination.accountName}
                onChange={(event) => setDestination({ ...destination, accountName: event.target.value })}
              />
            </label>
            <label>
              Note for the approver
              <input maxLength={300} value={destination.note} onChange={(event) => setDestination({ ...destination, note: event.target.value })} />
            </label>
            <div className="form-actions">
              <button className="button" disabled={busy} type="submit">
                Send for approval
              </button>
              <button className="button secondary" type="button" onClick={() => setProposing(false)}>
                Cancel
              </button>
            </div>
          </form>
        ) : null}

        {history.length > 0 ? (
          <details className="quiet-details">
            <summary>Earlier accounts ({history.length})</summary>
            <ul>
              {history.map((row) => (
                <li key={row.id}>
                  {describe(row)} · {row.status.toLowerCase()}
                  {row.retiredAt ? ` ${formatDate(row.retiredAt)}` : ""}
                  {row.note ? ` — ${row.note}` : ""}
                </li>
              ))}
            </ul>
          </details>
        ) : null}
      </article>

      <article className="data-card">
        <header>
          <h3>Payouts to this group</h3>
        </header>
        {data.settlements.length === 0 ? (
          <div className="empty-state">No payouts yet.</div>
        ) : (
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Receipt</th>
              </tr>
            </thead>
            <tbody>
              {data.settlements.map((row) => (
                <tr key={row.id}>
                  <td>{formatDate(row.settledAt ?? row.createdAt)}</td>
                  <td>{kesExact(row.amountCents)}</td>
                  <td>
                    {SETTLEMENT_LABELS[row.status] ?? row.status}
                    {row.failureReason ? ` — ${row.failureReason}` : ""}
                  </td>
                  <td>{row.providerReceipt ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </article>
    </>
  );
}
