"use client";

import type { FormEvent } from "react";
import React, { useState } from "react";
import { apiFetch } from "../../lib/api";

/**
 * Gives a group's digital champion access to this group's EXISTING account.
 *
 * The field team kept signing champions up as new groups, which made a second
 * login attached to no group. This is the way back: enter the champion's
 * number. If that number already has one of those empty sign-ups, it is joined
 * to this group — nothing is deleted, and the password the champion already
 * knows now opens the real book. Otherwise the number goes on this group's
 * login and the champion signs in with a texted code.
 */

type Outcome = "PHONE_ATTACHED" | "LOGIN_CREATED" | "EXISTING_LOGIN_LINKED" | "ALREADY_LINKED";

const OUTCOME_TEXT: Record<Outcome, string> = {
  PHONE_ATTACHED: "Done. The champion can now sign in with a code sent to this number.",
  LOGIN_CREATED: "Done. This group had no login, so one was created — the champion signs in with a code sent to this number.",
  EXISTING_LOGIN_LINKED:
    "Done. That number already had a sign-up with no group; it is now joined to this group. The champion's existing password opens it, or they can sign in with a code.",
  ALREADY_LINKED: "That number already opens this group. Nothing needed changing."
};

export function ChampionAccessCard({
  groupId,
  championName,
  championPhone,
  onLinked
}: {
  groupId: string;
  championName?: string | null;
  championPhone?: string | null;
  onLinked?: () => void;
}) {
  const [name, setName] = useState(championName ?? "");
  const [phone, setPhone] = useState(championPhone ?? "");
  const [saving, setSaving] = useState(false);
  const [result, setResult] = useState<{ ok: boolean; text: string } | null>(null);

  async function submit(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    setResult(null);
    try {
      const response = await apiFetch<{ outcome: Outcome }>(`/groups/${groupId}/champion`, {
        method: "PUT",
        body: JSON.stringify({ championName: name.trim() || undefined, phone })
      });
      setResult({ ok: true, text: OUTCOME_TEXT[response.outcome] });
      onLinked?.();
    } catch (error) {
      setResult({ ok: false, text: error instanceof Error ? error.message : "Could not link that number." });
    } finally {
      setSaving(false);
    }
  }

  return (
    <article className="data-card">
      <header>
        <div>
          <h3>Digital champion access</h3>
          <span>Let the champion into this group&apos;s existing account — never sign them up as a new group</span>
        </div>
        <span className={championPhone ? "pill" : "pill gold"}>{championPhone ? "Phone on record" : "No phone yet"}</span>
      </header>
      <form className="card-body" onSubmit={submit}>
        <div className="fact-grid">
          <label className="credential-field">
            <span>Champion&apos;s name</span>
            <input onChange={(event) => setName(event.target.value)} value={name} />
          </label>
          <label className="credential-field">
            <span>Champion&apos;s phone</span>
            <input
              inputMode="tel"
              onChange={(event) => setPhone(event.target.value)}
              placeholder="0712 345 678"
              required
              value={phone}
            />
          </label>
        </div>
        <p className="card-note">
          The champion is texted to say they now have access, so a mistyped number reaches a real person who
          can say it isn&apos;t them.
        </p>
        {result ? <p className={result.ok ? "notice success" : "notice warning"}>{result.text}</p> : null}
        <div className="form-actions">
          <button className="button" disabled={saving} type="submit">
            {saving ? "Linking" : "Give the champion access"}
          </button>
        </div>
      </form>
    </article>
  );
}
