"use client";

import type { FormEvent } from "react";
import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, ShieldCheck } from "@/lib/theme-icons";
import { apiFetch } from "../../../../../lib/api";

/**
 * The 4-digit PIN a group uses to confirm a field visit really happened.
 *
 * The point of the mechanism is that the *visited party* holds the secret: an
 * agent who could set it could attest to a visit they never made. So this page
 * exists for the group's own account and for platform admins, and the API
 * refuses a field agent outright — on the role, not merely on the permission.
 *
 * The PIN is never shown back. There is no "reveal": once set, the only
 * operation is to set a new one.
 */
interface VisitPinResponse {
  group: { id: string; name: string; code: string };
  configured: boolean;
  setAt: string | null;
  locked: boolean;
  canSet: boolean;
}

export default function GroupVisitPinPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [data, setData] = useState<VisitPinResponse | null>(null);
  const [pin, setPin] = useState("");
  const [confirmPin, setConfirmPin] = useState("");
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  async function load() {
    setData(await apiFetch<VisitPinResponse>(`/groups/${id}/visit-pin`));
  }

  useEffect(() => {
    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load the visit PIN."))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  async function save(event: FormEvent) {
    event.preventDefault();
    // Checked here as well as on the server, so a mistyped confirmation is
    // caught before it becomes a PIN nobody in the group knows.
    if (pin !== confirmPin) {
      setMessage({ ok: false, text: "The two PINs do not match." });
      return;
    }
    setSaving(true);
    setMessage(null);
    try {
      await apiFetch(`/groups/${id}/visit-pin`, {
        method: "PUT",
        body: JSON.stringify({ pin })
      });
      setPin("");
      setConfirmPin("");
      await load();
      setMessage({
        ok: true,
        text: "Saved. Tell the group's officials — they enter it when an agent visits."
      });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not save the PIN." });
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <div className="loading-panel">Loading visit PIN…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!data) return <div className="empty-state">No visit PIN information.</div>;

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href={`/dashboard/groups/${id}`}>
            <ArrowLeft size={17} />
            <span>{data.group.name}</span>
          </Link>
          <h2>Visit PIN</h2>
          <p>
            {data.configured
              ? "This group has a visit PIN. An official enters it on the agent's phone at the start of a visit."
              : "This group has no visit PIN yet, so no field visit can be recorded for it."}
          </p>
        </div>
        <ShieldCheck size={22} />
      </header>

      {message ? (
        <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div>
      ) : null}

      {data.locked ? (
        <div className="dashboard-notice error">
          Too many wrong attempts, so the PIN is locked for a few minutes. Setting a new
          one below unlocks it immediately.
        </div>
      ) : null}

      {!data.canSet ? (
        <div className="dashboard-notice">
          You can see whether a PIN is set but not change it. A field agent can never set
          this PIN — it is what confirms their visit, so it belongs to the group.
        </div>
      ) : null}

      <article className="data-card">
        <form onSubmit={save}>
          <label>
            New 4-digit PIN
            <input
              inputMode="numeric"
              pattern="\d{4}"
              maxLength={4}
              value={pin}
              disabled={!data.canSet || saving}
              onChange={(event) => setPin(event.target.value.replace(/\D/g, ""))}
              placeholder="••••"
              required
            />
          </label>
          <label>
            Confirm PIN
            <input
              inputMode="numeric"
              pattern="\d{4}"
              maxLength={4}
              value={confirmPin}
              disabled={!data.canSet || saving}
              onChange={(event) => setConfirmPin(event.target.value.replace(/\D/g, ""))}
              placeholder="••••"
              required
            />
          </label>
          <p className="eyebrow">
            Avoid 1234 or four of the same digit — those are refused. The PIN is never
            shown again after saving; if it is forgotten, set a new one.
          </p>
          <button className="button" disabled={!data.canSet || saving} type="submit">
            {saving ? "Saving…" : data.configured ? "Replace PIN" : "Set PIN"}
          </button>
        </form>
      </article>

      {data.setAt ? (
        <p className="eyebrow">Last set {new Date(data.setAt).toLocaleString()}.</p>
      ) : null}
    </section>
  );
}
