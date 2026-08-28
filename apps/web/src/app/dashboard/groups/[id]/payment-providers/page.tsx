"use client";

import React from "react";
import type { FormEvent } from "react";
import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, Banknote } from "@/lib/theme-icons";
import { apiFetch, formatDate, humanizeEnum } from "../../../../../lib/api";

/**
 * Where a group's collections land.
 *
 * A group that has its own M-Pesa till or Paystack account configures it here.
 * Leaving a provider unconfigured is a valid, common choice: the group simply
 * uses the platform's account. The page says so explicitly rather than showing
 * an empty form that looks broken.
 *
 * Secrets are never sent back by the API — they read as "Saved" — so the
 * inputs start blank and only the fields actually typed into are submitted.
 */
interface ProviderConfig {
  provider: string;
  configured: boolean;
  enabled: boolean;
  mode: string;
  credentialsUpdatedAt: string | null;
  values: Record<string, string | null>;
  missingKeys: string[];
  /** Where money will ACTUALLY go, derived from the credentials themselves. */
  effective: { environment: string; host: string; note: string };
}

interface ProvidersResponse {
  group: { id: string; name: string; code: string };
  providers: ProviderConfig[];
  fallback: string;
  canConfigure: boolean;
}

const SECRET_PLACEHOLDER = "__set__";

const FIELD_LABELS: Record<string, string> = {
  MPESA_CONSUMER_KEY: "Consumer key",
  MPESA_CONSUMER_SECRET: "Consumer secret",
  MPESA_SHORTCODE: "Shortcode / till number",
  MPESA_PASSKEY: "Passkey",
  MPESA_INITIATOR_NAME: "Initiator name",
  MPESA_SECURITY_CREDENTIAL: "Security credential",
  MPESA_ENVIRONMENT: "Safaricom environment",
  PAYSTACK_SECRET_KEY: "Secret key",
  PAYSTACK_PUBLIC_KEY: "Public key"
};

const PROVIDER_LABELS: Record<string, string> = {
  MPESA_DARAJA: "M-Pesa (Daraja API)",
  PAYSTACK: "Paystack"
};

function isSecretField(key: string) {
  return /SECRET|PASSKEY|CREDENTIAL/.test(key);
}

export default function GroupPaymentProvidersPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [data, setData] = useState<ProvidersResponse | null>(null);
  const [drafts, setDrafts] = useState<Record<string, Record<string, string>>>({});
  const [saving, setSaving] = useState<string | null>(null);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  async function loadPage() {
    const response = await apiFetch<ProvidersResponse>(`/groups/${id}/payment-providers`);
    setData(response);
  }

  useEffect(() => {
    loadPage()
      .catch((loadError) =>
        setError(loadError instanceof Error ? loadError.message : "Unable to load payment providers.")
      )
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  function setDraft(provider: string, key: string, value: string) {
    setDrafts((current) => ({ ...current, [provider]: { ...current[provider], [key]: value } }));
  }

  async function save(event: FormEvent, config: ProviderConfig) {
    event.preventDefault();
    setSaving(config.provider);
    setMessage(null);

    try {
      // Only send what was typed. Sending blanks would otherwise look like an
      // instruction to clear a credential the operator never touched.
      const entered = Object.fromEntries(
        Object.entries(drafts[config.provider] ?? {}).filter(([, value]) => value.trim().length > 0)
      );

      await apiFetch(`/groups/${id}/payment-providers/${config.provider}`, {
        method: "PUT",
        body: JSON.stringify({ credentials: entered, enabled: true })
      });

      setDrafts((current) => ({ ...current, [config.provider]: {} }));
      await loadPage();
      setMessage({
        ok: true,
        text: `${PROVIDER_LABELS[config.provider] ?? config.provider} updated. Collections for this group now use its own account.`
      });
    } catch (saveError) {
      setMessage({
        ok: false,
        text: saveError instanceof Error ? saveError.message : "Could not save the provider."
      });
    } finally {
      setSaving(null);
    }
  }

  async function revert(config: ProviderConfig) {
    // Money routing — make the person say yes before it moves.
    const confirmed = window.confirm(
      `Remove this group's own ${PROVIDER_LABELS[config.provider] ?? config.provider} credentials?\n\n` +
        `Its collections will go back to the platform's account.`
    );
    if (!confirmed) return;

    setSaving(config.provider);
    setMessage(null);
    try {
      await apiFetch(`/groups/${id}/payment-providers/${config.provider}`, { method: "DELETE" });
      await loadPage();
      setMessage({ ok: true, text: "This group now uses the platform's payment account." });
    } catch (revertError) {
      setMessage({
        ok: false,
        text: revertError instanceof Error ? revertError.message : "Could not revert the provider."
      });
    } finally {
      setSaving(null);
    }
  }

  if (loading) return <div className="loading-panel">Loading payment providers…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!data) return <div className="empty-state">No payment provider information.</div>;

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href={`/dashboard/groups/${id}`}>
            <ArrowLeft size={17} />
            <span>{data.group.name}</span>
          </Link>
          <h2>Payment providers</h2>
          <p>
            Where this group&apos;s collections are paid. {data.fallback}
          </p>
        </div>
        <Banknote size={22} />
      </header>

      {message ? (
        <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div>
      ) : null}

      {!data.canConfigure ? (
        <div className="dashboard-notice">
          You can see this group&apos;s payment setup but not change it. Only a platform admin or the
          group&apos;s own account may move where its money is collected.
        </div>
      ) : null}

      <div className="dashboard-notice">
        <strong>M-Pesa Classic</strong> needs nothing here — the member reads the transaction code off
        their phone and types it in.
      </div>

      <div className="dashboard-grid">
        {data.providers.map((config) => (
          <article className="data-card" key={config.provider}>
            <header>
              <div>
                <h3>{PROVIDER_LABELS[config.provider] ?? humanizeEnum(config.provider)}</h3>
                <p>
                  {config.configured
                    ? `Using this group's own account${
                        config.credentialsUpdatedAt
                          ? ` · updated ${formatDate(config.credentialsUpdatedAt)}`
                          : ""
                      }`
                    : "Using the platform's account"}
                </p>
              </div>
              <span className={`pill ${config.configured ? "" : "muted"}`}>
                {config.configured ? (config.enabled ? "Active" : "Disabled") : "Platform default"}
              </span>
            </header>

            <form onSubmit={(event) => save(event, config)}>
              {Object.keys(config.values).map((key) => {
                const saved = config.values[key];
                const isSecret = isSecretField(key);
                const savedNonSecret = saved && saved !== SECRET_PLACEHOLDER ? saved : "";

                // A choice, not free text: typing "lve" here is the difference
                // between reaching a real till and silently staying on test.
                if (key === "MPESA_ENVIRONMENT") {
                  return (
                    <label key={key}>
                      {FIELD_LABELS[key]}
                      <select
                        disabled={!data.canConfigure || saving === config.provider}
                        onChange={(event) => setDraft(config.provider, key, event.target.value)}
                        value={drafts[config.provider]?.[key] ?? (savedNonSecret || "SANDBOX")}
                      >
                        <option value="SANDBOX">Sandbox — testing, no real money</option>
                        <option value="LIVE">Live — real money to this group&apos;s till</option>
                      </select>
                    </label>
                  );
                }

                return (
                  <label key={key}>
                    {FIELD_LABELS[key] ?? key}
                    <input
                      autoComplete="off"
                      disabled={!data.canConfigure || saving === config.provider}
                      onChange={(event) => setDraft(config.provider, key, event.target.value)}
                      placeholder={
                        saved === SECRET_PLACEHOLDER
                          ? "Saved — type to replace"
                          : savedNonSecret || "Not set"
                      }
                      type={isSecret ? "password" : "text"}
                      value={drafts[config.provider]?.[key] ?? ""}
                    />
                  </label>
                );
              })}

              {/* The one line that matters before anyone takes a payment. It is
                  derived from the credentials, so it cannot flatter a group that
                  has pasted a test key while believing it is live. */}
              {config.configured ? (
                <p className={`dashboard-notice ${config.effective.environment === "LIVE" ? "" : "error"}`}>
                  <strong>
                    {config.effective.environment === "LIVE" ? "Live" : "Test mode"}
                  </strong>{" "}
                  — {config.effective.note}
                </p>
              ) : null}

              {config.missingKeys.length > 0 && config.configured ? (
                <p className="dashboard-notice error">
                  Incomplete — still needed:{" "}
                  {config.missingKeys.map((key) => FIELD_LABELS[key] ?? key).join(", ")}. Until every
                  field is set this group keeps using the platform&apos;s account.
                </p>
              ) : null}

              {data.canConfigure ? (
                <div className="form-actions">
                  <button className="button" disabled={saving === config.provider} type="submit">
                    {saving === config.provider ? "Saving…" : "Save credentials"}
                  </button>
                  {config.configured ? (
                    <button
                      className="button secondary"
                      disabled={saving === config.provider}
                      onClick={() => void revert(config)}
                      type="button"
                    >
                      Use platform account
                    </button>
                  ) : null}
                </div>
              ) : null}
            </form>
          </article>
        ))}
      </div>
    </section>
  );
}
