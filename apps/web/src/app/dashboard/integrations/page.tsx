"use client";

import React from "react";
import type { FormEvent } from "react";
import { useEffect, useState } from "react";
import { CheckCircle2, FlaskConical, KeyRound, LockKeyhole, PlugZap, Trash2 } from "@/lib/theme-icons";
import { apiFetch, humanizeEnum } from "../../../lib/api";
import { StatCard } from "../../../components/dashboard/stat-card";
import type { IntegrationHealth, IntegrationStatus, User } from "../../../components/dashboard/types";

interface TestResult {
  ok: boolean;
  message: string;
  status?: IntegrationStatus;
}

interface NotificationSmsSetting {
  type: string;
  label: string;
  audience: string;
  volume: string;
  smsEnabled: boolean;
  /** False means it is running on the default, which nobody chose. */
  configured: boolean;
}

interface CredentialValuesIndexResponse {
  providers: Array<{
    provider: string;
    displayName: string;
    credentialsUpdatedAt?: string | null;
    credentials: Record<string, string>;
  }>;
}

const smsProviders = ["AFRICAS_TALKING", "BONGA_SMS"];

/**
 * "Ready" used to mean nothing more than "the keys are filled in". Bonga was
 * configured correctly while `ENABLE_SMS_NETWORK_CALLS` was off, so every send
 * short-circuited to QUEUED and the console showed a healthy provider. Readiness
 * is credentials AND delivery; anything less gets its own label.
 */
function readinessLabel(status: IntegrationStatus) {
  if (!status.configured) return { tone: "gold", text: "Gated" };
  if (!status.deliveryEnabled) return { tone: "gold", text: "Not sending" };
  return { tone: "blue", text: "Ready" };
}

function inputTypeForKey(key: string) {
  if (key.includes("URL") || key.includes("ENDPOINT")) return "url";
  return "text";
}

function isTemplateKey(key: string) {
  return key.includes("TEMPLATE");
}

function visibleCredentialValues(values: Record<string, string>) {
  return Object.fromEntries(
    Object.entries(values)
      .map(([key, value]) => [key, value.trim()])
      .filter((entry): entry is [string, string] => Boolean(entry[1]))
  );
}

function credentialValueIndex(response: CredentialValuesIndexResponse) {
  return Object.fromEntries(
    response.providers.map((provider) => [provider.provider, provider.credentials ?? {}])
  );
}

function valuesForKeys(keys: string[], values: Record<string, string>) {
  return Object.fromEntries(keys.map((key) => [key, values[key]?.trim() ?? ""]));
}

export default function IntegrationsPage() {
  const [health, setHealth] = useState<IntegrationHealth | null>(null);
  const [testingProvider, setTestingProvider] = useState<string | null>(null);
  const [testResults, setTestResults] = useState<Record<string, TestResult>>({});
  const [selectedProvider, setSelectedProvider] = useState<string | null>(null);
  const [smsSettings, setSmsSettings] = useState<NotificationSmsSetting[]>([]);
  const [savingSmsType, setSavingSmsType] = useState<string | null>(null);
  const [credentialValuesByProvider, setCredentialValuesByProvider] = useState<Record<string, Record<string, string>>>({});
  const [credentialValues, setCredentialValues] = useState<Record<string, string>>({});
  const [loadingCredentials, setLoadingCredentials] = useState(false);
  const [savingCredentials, setSavingCredentials] = useState(false);
  const [credentialMessage, setCredentialMessage] = useState<TestResult | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadHealth();
  }, []);

  useEffect(() => {
    if (!selectedProvider) {
      setCredentialValues({});
      return;
    }

    setCredentialValues(credentialValuesByProvider[selectedProvider] ?? {});
  }, [credentialValuesByProvider, selectedProvider]);

  /**
   * Turn one category of system notification on or off for SMS.
   *
   * Optimistic, then reconciled from the server: an admin scanning a list of
   * eight switches should not wait on a round trip between each one, and a
   * failure puts the switch back where it was rather than leaving the screen
   * claiming something the platform will not do.
   */
  async function setNotificationSms(type: string, smsEnabled: boolean) {
    const previous = smsSettings;
    setSavingSmsType(type);
    setSmsSettings((current) =>
      current.map((row) => (row.type === type ? { ...row, smsEnabled, configured: true } : row))
    );

    try {
      await apiFetch("/notifications/sms-settings", {
        method: "PUT",
        body: JSON.stringify({ type, smsEnabled })
      });
      const refreshed = await apiFetch<{ settings: NotificationSmsSetting[] }>(
        "/notifications/sms-settings"
      );
      setSmsSettings(refreshed.settings);
    } catch (saveError) {
      setSmsSettings(previous);
      setError(saveError instanceof Error ? saveError.message : "Could not save that setting.");
    } finally {
      setSavingSmsType(null);
    }
  }

  async function loadHealth() {
    setLoading(true);
    try {
      const me = await apiFetch<User>("/auth/me");
      if (me.role !== "IWL_ADMIN") {
        throw new Error("Only IWL admins can manage integrations.");
      }

      setLoadingCredentials(true);
      const [response, credentialIndex, notificationSms] = await Promise.all([
        apiFetch<IntegrationHealth>("/integrations/health"),
        apiFetch<CredentialValuesIndexResponse>("/integrations/credentials"),
        apiFetch<{ settings: NotificationSmsSetting[] }>("/notifications/sms-settings")
      ]);
      setSmsSettings(notificationSms.settings);
      const preferredProvider =
        response.statuses.find((status) => !smsProviders.includes(status.provider))?.provider ??
        response.statuses[0]?.provider ??
        null;

      setHealth(response);
      setCredentialValuesByProvider(credentialValueIndex(credentialIndex));
      setSelectedProvider((current) =>
        current && response.statuses.some((status) => status.provider === current) ? current : preferredProvider
      );
      setError(null);
    } catch (integrationError) {
      setError(integrationError instanceof Error ? integrationError.message : "Integrations failed");
    } finally {
      setLoading(false);
      setLoadingCredentials(false);
    }
  }

  function updateProviderStatus(status: IntegrationStatus) {
    setHealth((current) => {
      if (!current) return current;

      const statuses = current.statuses.map((candidate) =>
        candidate.provider === status.provider ? status : candidate
      );

      return {
        ...current,
        configured: statuses.filter((candidate) => candidate.configured).length,
        statuses
      };
    });
  }

  function selectProvider(status: IntegrationStatus) {
    setSelectedProvider(status.provider);
    setCredentialValues(credentialValuesByProvider[status.provider] ?? {});
    setCredentialMessage(null);
  }

  async function testProvider(provider: string) {
    setTestingProvider(provider);
    try {
      const result = await apiFetch<TestResult>(`/integrations/${provider}/test`, {
        method: "POST"
      });
      if (result.status) updateProviderStatus(result.status);
      setTestResults((current) => ({ ...current, [provider]: result }));
    } catch (testError) {
      setTestResults((current) => ({
        ...current,
        [provider]: {
          ok: false,
          message: testError instanceof Error ? testError.message : "Test failed"
        }
      }));
    } finally {
      setTestingProvider(null);
    }
  }

  async function saveCredentials(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!selectedProvider) return;

    const credentials = visibleCredentialValues(credentialValues);
    if (Object.keys(credentials).length === 0) {
      setCredentialMessage({ ok: false, message: "Enter at least one credential value before saving." });
      return;
    }

    setSavingCredentials(true);
    setCredentialMessage(null);

    try {
      const status = await apiFetch<IntegrationStatus>(`/integrations/${selectedProvider}/credentials`, {
        method: "PUT",
        body: JSON.stringify({ credentials })
      });
      updateProviderStatus(status);
      const nextValues = valuesForKeys(
        status.requiredEnv,
        {
          ...(credentialValuesByProvider[selectedProvider] ?? {}),
          ...credentials
        }
      );
      setCredentialValuesByProvider((current) => ({
        ...current,
        [selectedProvider]: nextValues
      }));
      setCredentialValues(nextValues);
      setCredentialMessage({
        ok: true,
        message: `${status.displayName} credentials saved.`
      });
    } catch (saveError) {
      setCredentialMessage({
        ok: false,
        message: saveError instanceof Error ? saveError.message : "Credentials failed to save"
      });
    } finally {
      setSavingCredentials(false);
    }
  }

  async function clearCredentials() {
    if (!selectedProvider) return;

    setSavingCredentials(true);
    setCredentialMessage(null);

    try {
      const status = await apiFetch<IntegrationStatus>(`/integrations/${selectedProvider}/credentials`, {
        method: "DELETE"
      });
      updateProviderStatus(status);
      setCredentialValuesByProvider((current) => ({
        ...current,
        [selectedProvider]: valuesForKeys(status.requiredEnv, {})
      }));
      setCredentialValues({});
      setCredentialMessage({
        ok: true,
        message: `${status.displayName} stored credentials cleared.`
      });
    } catch (clearError) {
      setCredentialMessage({
        ok: false,
        message: clearError instanceof Error ? clearError.message : "Credentials failed to clear"
      });
    } finally {
      setSavingCredentials(false);
    }
  }

  if (loading && !health) return <div className="loading-panel">Loading...</div>;
  if (error) return <div className="error">{error}</div>;

  const statuses = health?.statuses ?? [];
  const configured = health?.configured ?? 0;
  const total = health?.total ?? 0;
  const missingTotal = statuses.reduce((sum, status) => sum + status.missingEnv.length, 0);
  const selectedStatus = statuses.find((status) => status.provider === selectedProvider) ?? statuses[0] ?? null;
  const selectedTestResult = selectedStatus ? testResults[selectedStatus.provider] : null;

  return (
    <>
      <section className="page-heading">
        <div>
          <p className="eyebrow">Sandbox Integrations</p>
          <h2
            aria-label="Integrations"
            className="has-hint"
            data-hint="Configure sandbox providers used by payments, maps, SMS notifications, KYC, banking, credit bureau, and market-data features."
            tabIndex={0}
          >
            Integrations
          </h2>
        </div>
        <button className="button secondary" onClick={loadHealth} type="button">
          Refresh
        </button>
      </section>

      <section className="stat-grid">
        <StatCard icon={<PlugZap size={20} />} label="Providers" note="Configured adapters" value={total.toString()} />
        <StatCard icon={<CheckCircle2 size={20} />} label="Ready" note="All credentials present" value={configured.toString()} />
        <StatCard icon={<LockKeyhole size={20} />} label="Missing fields" note="Across providers" value={missingTotal.toString()} />
        <StatCard icon={<FlaskConical size={20} />} label="Network tests" note="Sandbox probes" value={statuses[0]?.networkTestsAllowed ? "On" : "Off"} />
      </section>

      <section className="two-column">
        <div className="data-card">
          <header>
            <div>
              <h3>Providers</h3>
              <span>Review saved values for each third party, then select one row to edit.</span>
            </div>
            <span className="pill">{statuses.length} listed</span>
          </header>

          <div className="list">
            {statuses.map((status) => {
              const result = testResults[status.provider];
              const providerValues = credentialValuesByProvider[status.provider] ?? {};
              const selected = selectedStatus?.provider === status.provider;

              return (
                <div className={`list-row integration-provider-row ${selected ? "is-selected" : ""}`} key={status.provider}>
                  <div className="integration-provider-summary">
                    <strong>{status.displayName}</strong>
                    <span>
                      {humanizeEnum(status.provider)} - {status.storedCredentialKeys.length} stored, {status.missingEnv.length} missing
                    </span>
                    {result ? <em className={result.ok ? "" : "warning"}>{result.message}</em> : null}
                    <div className="integration-value-preview" aria-label={`${status.displayName} current values`}>
                      {status.requiredEnv.map((key) => {
                        const value = providerValues[key]?.trim() ?? "";
                        const fromEnv = status.envCredentialKeys.includes(key);
                        const missing = status.missingEnv.includes(key);
                        const stateLabel = value ? value : fromEnv ? "Set in environment" : "Not set";

                        return (
                          <div
                            className={`integration-value-item ${value ? "" : fromEnv ? "is-env" : "is-missing"}`}
                            key={key}
                          >
                            <span>{key}</span>
                            <code title={stateLabel}>{missing && !value ? "Not set" : stateLabel}</code>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                  <div className="modal-header-actions">
                    <span className={`pill ${readinessLabel(status).tone}`}>
                      {readinessLabel(status).text}
                    </span>
                    <button
                      aria-pressed={selected}
                      className="button secondary"
                      onClick={() => selectProvider(status)}
                      type="button"
                    >
                      {selected ? "Selected" : "Edit"}
                    </button>
                  </div>
                </div>
              );
            })}
            {statuses.length === 0 ? <div className="empty-state">No providers</div> : null}
          </div>
        </div>

        <section className="data-card credential-panel">
          <header>
            <div>
              <h3>{selectedStatus ? `${selectedStatus.displayName} Setup` : "Provider Setup"}</h3>
              <span>Stored values are visible to admins so setup can be reviewed without guessing.</span>
            </div>
            {selectedStatus ? (
              <span className={`pill ${readinessLabel(selectedStatus).tone}`}>
                {loadingCredentials
                  ? "Loading values"
                  : selectedStatus.configured
                    ? readinessLabel(selectedStatus).text
                    : `${selectedStatus.missingEnv.length} missing`}
              </span>
            ) : null}
          </header>

          {selectedStatus ? (
            <form className="credential-form" onSubmit={saveCredentials}>
              {selectedStatus.configured && !selectedStatus.deliveryEnabled ? (
                <p className="dashboard-notice warning">
                  {selectedStatus.deliveryNote ??
                    "Delivery is switched off for this provider."}{" "}
                  Saving credentials here will not change that — it is a server
                  environment variable and takes a restart.
                </p>
              ) : null}
              <div className="credential-grid">
                <label className="credential-field">
                  <span>Provider</span>
                  <select
                    onChange={(event) => {
                      const status = statuses.find((candidate) => candidate.provider === event.target.value);
                      if (status) selectProvider(status);
                    }}
                    value={selectedStatus.provider}
                  >
                    {statuses.map((status) => (
                      <option key={status.provider} value={status.provider}>
                        {status.displayName}
                      </option>
                    ))}
                  </select>
                </label>

                {selectedStatus.requiredEnv.map((key) => {
                  const stored = selectedStatus.storedCredentialKeys.includes(key);
                  const fromEnv = selectedStatus.envCredentialKeys.includes(key);
                  const missing = selectedStatus.missingEnv.includes(key);

                  return (
                    <label className="credential-field" key={key}>
                      <span>
                        {key}
                        {fromEnv ? <em>from .env</em> : null}
                        {stored ? <em>stored</em> : null}
                        {missing ? <em className="warning">missing</em> : null}
                      </span>
                      {isTemplateKey(key) ? (
                        <textarea
                          onChange={(event) =>
                            setCredentialValues((current) => ({
                              ...current,
                              [key]: event.target.value
                            }))
                          }
                          placeholder={
                            stored || fromEnv
                              ? "Current SMS text"
                              : "Use placeholders like {otp}, {pin}, and {ttlMinutes}"
                          }
                          rows={3}
                          value={credentialValues[key] ?? ""}
                        />
                      ) : (
                        <input
                          autoComplete="off"
                          onChange={(event) =>
                            setCredentialValues((current) => ({
                              ...current,
                              [key]: event.target.value
                            }))
                          }
                          placeholder={stored || fromEnv ? "Current value" : "Enter value"}
                          type={inputTypeForKey(key)}
                          value={credentialValues[key] ?? ""}
                        />
                      )}
                    </label>
                  );
                })}
              </div>

              {selectedTestResult ? (
                <div className={selectedTestResult.ok ? "notice success" : "notice warning"}>
                  {selectedTestResult.message}
                </div>
              ) : null}
              {credentialMessage ? (
                <div className={credentialMessage.ok ? "notice success" : "notice warning"}>
                  {credentialMessage.message}
                </div>
              ) : null}

              <div className="credential-actions">
                <button className="button" disabled={savingCredentials || loadingCredentials} type="submit">
                  <KeyRound size={16} />
                  {savingCredentials ? "Saving" : "Save"}
                </button>
                <button
                  className="button secondary"
                  disabled={testingProvider === selectedStatus.provider}
                  onClick={() => testProvider(selectedStatus.provider)}
                  type="button"
                >
                  <FlaskConical size={16} />
                  {testingProvider === selectedStatus.provider ? "Testing" : "Test"}
                </button>
                <button
                  className="button secondary"
                  disabled={
                    savingCredentials || loadingCredentials || selectedStatus.storedCredentialKeys.length === 0
                  }
                  onClick={clearCredentials}
                  type="button"
                >
                  <Trash2 size={16} />
                  Clear
                </button>
              </div>
            </form>
          ) : (
            <div className="empty-state">No providers</div>
          )}
        </section>
      </section>

      <section className="data-card notification-sms">
        <header>
          <div>
            <h3>System notifications by SMS</h3>
            <span>
              Everything the console shows in the bell is also texted, because the person who needs
              to act on it is usually not at a screen. Switch off the ones that are not worth the
              credits.
            </span>
          </div>
          <span className="pill">
            {smsSettings.filter((row) => row.smsEnabled).length} of {smsSettings.length} on
          </span>
        </header>

        <div className="notification-sms-list">
          {smsSettings.map((row) => (
            <label className="checkbox-field" key={row.type}>
              <input
                checked={row.smsEnabled}
                disabled={savingSmsType === row.type}
                onChange={(event) => setNotificationSms(row.type, event.target.checked)}
                type="checkbox"
              />
              <span>
                <strong>{row.label}</strong>
                <small>
                  {row.audience}. {row.volume}
                  {row.configured ? "" : " Running on the default."}
                </small>
              </span>
            </label>
          ))}
          {smsSettings.length === 0 ? (
            <div className="empty-state">No notification types</div>
          ) : null}
        </div>

        <p className="dashboard-notice">
          These are delivered by whichever SMS provider above is configured, and appear on the SMS
          page alongside manual broadcasts. A recipient with no phone number on record is listed
          there as failed rather than silently skipped.
        </p>
      </section>
    </>
  );
}
