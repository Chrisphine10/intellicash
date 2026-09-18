"use client";

import React, { useEffect, useState } from "react";
import { ApiClientError, apiFetch } from "../../lib/api";
import { CredentialButton } from "./credential-button";

/**
 * An administrator resetting somebody else's password.
 *
 * Texting a reset code comes first, because it is the better of the two: the
 * person chooses their own password and the admin never learns it. Setting a
 * password directly is the fallback — for an account with no phone, or someone
 * standing in front of the admin — and it signs the account out everywhere.
 */
export function AdminPasswordReset({ userId, hasPhone }: { userId: string; hasPhone: boolean }) {
  const [isSelf, setIsSelf] = useState(false);
  const [newPassword, setNewPassword] = useState("");
  const [busy, setBusy] = useState<"code" | "set" | null>(null);
  const [result, setResult] = useState<{ ok: boolean; text: string } | null>(null);
  // The server refuses a second code within a minute. Counting down here means
  // the admin sees when they may send again instead of meeting that refusal.
  const [resendIn, setResendIn] = useState(0);

  useEffect(() => {
    if (resendIn <= 0) return;
    const timer = window.setTimeout(() => setResendIn((seconds) => seconds - 1), 1000);
    return () => window.clearTimeout(timer);
  }, [resendIn]);

  useEffect(() => {
    // Checked here so the page needs no extra state. The server refuses the
    // admin's own account anyway; this just says so before they try.
    let active = true;
    apiFetch<{ id: string }>("/auth/me")
      .then((me) => {
        if (active) setIsSelf(me.id === userId);
      })
      .catch(() => undefined);
    return () => {
      active = false;
    };
  }, [userId]);

  if (isSelf) {
    return (
      <section className="admin-password-reset">
        <h4>Password</h4>
        <p className="card-note">
          Change your own password from My account, where your current password is asked for.
        </p>
      </section>
    );
  }

  async function sendCode() {
    setBusy("code");
    setResult(null);
    try {
      const response = await apiFetch<{ sentTo: string | null; expiresInMinutes: number }>(
        `/users/${userId}/password`,
        { method: "POST", body: JSON.stringify({ mode: "SEND_CODE" }) }
      );
      setResult({
        ok: true,
        text: `Reset code texted to ${response.sentTo ?? "their phone"}. It works for ${response.expiresInMinutes} minutes; they choose the new password under "Forgot password?".`
      });
      setResendIn(60);
    } catch (error) {
      if (error instanceof ApiClientError && error.code === "CODE_RECENTLY_SENT") setResendIn(60);
      setResult({ ok: false, text: error instanceof Error ? error.message : "Could not send the code." });
    } finally {
      setBusy(null);
    }
  }

  async function setPassword() {
    setBusy("set");
    setResult(null);
    try {
      const response = await apiFetch<{ endedSessions: number; ownerNotified: boolean }>(
        `/users/${userId}/password`,
        { method: "POST", body: JSON.stringify({ mode: "SET", newPassword }) }
      );
      setNewPassword("");
      setResult({
        ok: true,
        text: `Password set. ${response.endedSessions} signed-in session${
          response.endedSessions === 1 ? " was" : "s were"
        } ended${response.ownerNotified ? ", and the owner was texted that it changed" : ""}. Give them the new password in person.`
      });
    } catch (error) {
      setResult({ ok: false, text: error instanceof Error ? error.message : "Could not set the password." });
    } finally {
      setBusy(null);
    }
  }

  return (
    <section className="admin-password-reset" aria-labelledby="admin-password-reset-title">
      <header>
        <h4 id="admin-password-reset-title">Password</h4>
        <span>Saved the moment you press a button — not with “Save access”.</span>
      </header>

      <div className="admin-password-option">
        <div className="admin-password-option-text">
          <strong>Text a reset code</strong>
          <span>
            {hasPhone
              ? "Recommended. They choose their own password and you never see it."
              : "This account has no phone number, so a code cannot be texted."}
          </span>
        </div>
        {hasPhone ? (
          <CredentialButton
            busy={busy === "code"}
            disabled={busy !== null || resendIn > 0}
            kind="sms"
            label={resendIn > 0 ? `Send again in ${resendIn}s` : "Send reset code"}
            onClick={() => void sendCode()}
          />
        ) : null}
      </div>

      {/* Not a <form>: Enter in this field must not submit anything by
          accident. Setting a password takes a deliberate click. */}
      <div className="admin-password-option">
        <div className="admin-password-option-text">
          <strong>Set a password now</strong>
          <span>For someone in front of you. Signs the account out on every device.</span>
        </div>
        <div className="admin-password-set">
          <label className="credential-field">
            <span className="sr-only">Or set a new password (at least 8 characters)</span>
            <input
              autoComplete="new-password"
              minLength={8}
              onChange={(event) => setNewPassword(event.target.value)}
              placeholder="New password, 8+ characters"
              type="password"
              value={newPassword}
            />
          </label>
          <CredentialButton
            busy={busy === "set"}
            disabled={busy !== null || newPassword.length < 8}
            emphasis="secondary"
            kind="password"
            label="Set password"
            onClick={() => void setPassword()}
          />
        </div>
      </div>

      {result ? <p className={result.ok ? "notice success" : "notice warning"}>{result.text}</p> : null}
    </section>
  );
}
