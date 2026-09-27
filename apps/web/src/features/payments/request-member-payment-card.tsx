"use client";

import React from "react";
import type { FormEvent } from "react";
import { useEffect, useRef, useState } from "react";
import { apiFetch } from "../../lib/api";
import { kesExact, PROVIDER_LABELS, STATE_LABELS } from "./money";

/**
 * The group asks a member to pay — shares, welfare, a fine or a loan
 * repayment. The member gets an M-Pesa prompt (or a Paystack link); the
 * server confirms it with the provider and books it into the right fund:
 * shares and repayments into the loan fund, welfare and fines into the
 * social fund. The member sees the full charge before anything is sent.
 */
const PURPOSES: Array<{ value: string; label: string }> = [
  { value: "SHARE_PURCHASE", label: "Shares" },
  { value: "SOCIAL_FUND", label: "Welfare (social fund)" },
  { value: "FINE", label: "Fine" },
  { value: "LOAN_REPAYMENT", label: "Loan repayment" }
];

interface MemberRow {
  id: string;
  fullName: string;
  phone?: string | null;
  status: string;
}

interface MemberContext {
  shareValueCents: number | null;
  socialFundCents: number | null;
  loanOutstandingCents: number;
  providers: string[];
}

interface Quote {
  quoteId: string;
  groupAmountCents: number;
  platformFeeCents: number;
  providerFeeCents: number;
  totalCents: number;
}

interface Payment {
  id: string;
  state: string;
  status: string;
  amountCents: number;
  checkoutUrl: string | null;
  providerTransactionId: string | null;
  failureReason: string | null;
}

export function RequestMemberPaymentCard({ groupId }: { groupId: string }) {
  const [members, setMembers] = useState<MemberRow[]>([]);
  const [memberId, setMemberId] = useState("");
  const [context, setContext] = useState<MemberContext | null>(null);
  const [purpose, setPurpose] = useState("SHARE_PURCHASE");
  const [provider, setProvider] = useState("MPESA_DARAJA");
  const [amount, setAmount] = useState("");
  const [contact, setContact] = useState("");
  const [quote, setQuote] = useState<Quote | null>(null);
  const [payment, setPayment] = useState<Payment | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const poll = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    apiFetch<MemberRow[]>(`/groups/${groupId}/members`)
      .then((rows) => setMembers(rows.filter((row) => row.status === "ACTIVE")))
      .catch((e) => setError(e instanceof Error ? e.message : "Could not load members."));
    return () => {
      if (poll.current) clearInterval(poll.current);
    };
  }, [groupId]);

  useEffect(() => {
    setQuote(null);
    setContext(null);
    if (!memberId) return;
    const member = members.find((row) => row.id === memberId);
    setContact(member?.phone ?? "");
    apiFetch<MemberContext>(`/groups/${groupId}/payments/member-context/${memberId}`)
      .then(setContext)
      .catch((e) => setError(e instanceof Error ? e.message : "Could not load the member's figures."));
  }, [memberId, members, groupId]);

  // Fixed amounts come from the group's own rules.
  useEffect(() => {
    setQuote(null);
    if (purpose === "SOCIAL_FUND" && context?.socialFundCents) setAmount(String(context.socialFundCents / 100));
    if (purpose === "SHARE_PURCHASE" && context?.shareValueCents && !amount) setAmount(String(context.shareValueCents / 100));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [purpose, context]);

  const amountCents = Math.round(Number(amount || "0") * 100);

  async function getQuote(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setBusy(true);
    try {
      setQuote(
        await apiFetch<Quote>(`/groups/${groupId}/payments/quote`, {
          method: "POST",
          body: JSON.stringify({ provider, purpose, groupAmountCents: amountCents, memberId })
        })
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not work out the charges.");
    } finally {
      setBusy(false);
    }
  }

  async function send() {
    if (!quote) return;
    setError(null);
    setBusy(true);
    try {
      const started = await apiFetch<Payment>(`/groups/${groupId}/payments`, {
        method: "POST",
        body: JSON.stringify({
          provider,
          purpose,
          quoteId: quote.quoteId,
          memberId,
          clientRequestId: `web-${quote.quoteId.slice(-16)}`,
          ...(provider === "MPESA_DARAJA" ? { phoneNumber: contact } : { customerEmail: contact })
        })
      });
      setPayment(started);
      if (poll.current) clearInterval(poll.current);
      let ticks = 0;
      poll.current = setInterval(async () => {
        ticks += 1;
        try {
          const latest = await apiFetch<Payment>(`/groups/${groupId}/payments/${started.id}`);
          setPayment(latest);
          if (latest.status !== "PENDING" || ticks > 200) {
            if (poll.current) clearInterval(poll.current);
          }
        } catch {
          // A dropped poll is retried on the next tick.
        }
      }, 3000);
    } catch (e) {
      setError(e instanceof Error ? e.message : "The request could not be sent.");
    } finally {
      setBusy(false);
    }
  }

  function reset() {
    if (poll.current) clearInterval(poll.current);
    setQuote(null);
    setPayment(null);
    setError(null);
  }

  const owes = context?.loanOutstandingCents ?? 0;
  const repaymentProblem =
    purpose === "LOAN_REPAYMENT" && memberId && context
      ? owes <= 0
        ? "This member has no loan to repay."
        : amountCents > owes
          ? `That is more than the ${kesExact(owes)} owed.`
          : null
      : null;

  return (
    <article className="data-card">
      <header>
        <div>
          <h3>Request a payment from a member</h3>
          <p>The member gets an M-Pesa prompt (or a Paystack link). It is booked into the group&apos;s books once confirmed.</p>
        </div>
      </header>
      {error ? <div className="dashboard-notice error">{error}</div> : null}

      {payment ? (
        <div className="dashboard-notice">
          <strong>{STATE_LABELS[payment.state] ?? payment.state}</strong> · {kesExact(payment.amountCents)}
          {payment.providerTransactionId ? ` · receipt ${payment.providerTransactionId}` : ""}
          {payment.failureReason ? ` — ${payment.failureReason}` : ""}
          {payment.checkoutUrl ? (
            <>
              {" "}
              · <a href={payment.checkoutUrl} rel="noreferrer" target="_blank">Paystack link for the member</a>
            </>
          ) : null}
          {payment.status === "PENDING" ? <p>Waiting for the member to approve…</p> : null}
          <div className="form-actions">
            <button className="button secondary" type="button" onClick={reset}>
              New request
            </button>
          </div>
        </div>
      ) : (
        <form className="stacked-form" onSubmit={getQuote}>
          <label>
            Member
            <select required value={memberId} onChange={(event) => setMemberId(event.target.value)}>
              <option value="">Choose a member</option>
              {members.map((member) => (
                <option key={member.id} value={member.id}>
                  {member.fullName}
                </option>
              ))}
            </select>
          </label>
          <label>
            Paying for
            <select value={purpose} onChange={(event) => setPurpose(event.target.value)}>
              {PURPOSES.map((item) => (
                <option key={item.value} value={item.value}>
                  {item.label}
                </option>
              ))}
            </select>
          </label>
          <label>
            Amount for the group (KSh)
            <input
              min={1}
              readOnly={purpose === "SOCIAL_FUND" && Boolean(context?.socialFundCents)}
              required
              step="0.01"
              type="number"
              value={amount}
              onChange={(event) => {
                setAmount(event.target.value);
                setQuote(null);
              }}
            />
          </label>
          {purpose === "LOAN_REPAYMENT" && context ? <p className="dashboard-notice">Owes {kesExact(owes)}.</p> : null}
          {repaymentProblem ? <div className="dashboard-notice error">{repaymentProblem}</div> : null}
          <label>
            Provider
            <select value={provider} onChange={(event) => setProvider(event.target.value)}>
              {(context?.providers ?? ["MPESA_DARAJA", "PAYSTACK"]).map((value) => (
                <option key={value} value={value}>
                  {PROVIDER_LABELS[value] ?? value}
                </option>
              ))}
            </select>
          </label>
          <label>
            {provider === "MPESA_DARAJA" ? "Member's M-Pesa phone" : "Member's email for the receipt"}
            <input required value={contact} onChange={(event) => setContact(event.target.value)} />
          </label>
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
          <div className="form-actions">
            {quote ? (
              <button className="button" disabled={busy} type="button" onClick={send}>
                {provider === "MPESA_DARAJA" ? `Send ${kesExact(quote.totalCents)} prompt` : `Create ${kesExact(quote.totalCents)} link`}
              </button>
            ) : (
              <button className="button" disabled={busy || !memberId || amountCents <= 0 || Boolean(repaymentProblem)} type="submit">
                Show the charges
              </button>
            )}
          </div>
        </form>
      )}
    </article>
  );
}
