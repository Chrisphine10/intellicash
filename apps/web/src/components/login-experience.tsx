"use client";

import type { FormEvent } from "react";
import React, { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { LogIn, Smartphone } from "@/lib/theme-icons";
import { demoAccounts, demoPassword } from "@intellicash/shared";

/**
 * One-click demo sign-in, and the credentials it prefills, are a DEVELOPMENT
 * convenience. Shipped to production they published a working password on the
 * public login page - `IntellicashDemo#2026` was readable in the page source
 * of intellicash.co.ke/login. Opt in explicitly; the default is off, so a
 * production build never renders it.
 */
const DEMO_LOGIN_ENABLED = process.env.NEXT_PUBLIC_ENABLE_DEMO_LOGIN === "true";
import type { Role } from "@intellicash/shared";
import { apiFetch, humanizeEnum } from "../lib/api";
import { refreshOfflinePinCache } from "../lib/offline-pin-cache";

interface LoginExperienceProps {
  ariaLabel?: string;
  copyTitle: string;
  copyText: string;
  demoRoles?: readonly Role[];
  formTitle?: string;
}

export function LoginExperience({
  ariaLabel = "Intelli Cash platform",
  copyTitle,
  copyText,
  demoRoles,
  formTitle = "Sign in"
}: LoginExperienceProps) {
  const router = useRouter();
  const visibleDemoAccounts = useMemo(
    () =>
      DEMO_LOGIN_ENABLED
        ? demoAccounts.filter((account) => !demoRoles || demoRoles.includes(account.role))
        : [],
    [demoRoles]
  );
  // Empty unless demo login is explicitly enabled: a real sign-in page must
  // not arrive with someone else's credentials already typed in.
  const [phone, setPhone] = useState<string>(
    DEMO_LOGIN_ENABLED ? (visibleDemoAccounts[0] ?? demoAccounts[0]).phone : ""
  );
  const [password, setPassword] = useState<string>(DEMO_LOGIN_ENABLED ? demoPassword : "");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [activeDemoPhone, setActiveDemoPhone] = useState<string | null>(null);

  /**
   * Three ways in. A group's champion usually has no password worth the name —
   * the account was created for them — so a texted code is the ordinary path,
   * and a reset is how they set a password if they want one.
   */
  const [mode, setMode] = useState<"password" | "code" | "reset">("password");
  const [codeSent, setCodeSent] = useState(false);
  const [code, setCode] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [notice, setNotice] = useState<string | null>(null);

  function switchMode(next: "password" | "code" | "reset") {
    setMode(next);
    setCodeSent(false);
    setCode("");
    setNewPassword("");
    setError(null);
    setNotice(null);
  }

  async function afterSignIn(signedInUser: { role: Role; groupId?: string | null }) {
    if (signedInUser.role === "GROUP_ACCOUNT" && signedInUser.groupId) {
      void refreshOfflinePinCache(signedInUser.groupId).catch(() => undefined);
    }
    router.push("/dashboard");
  }

  async function requestCode() {
    setError(null);
    setNotice(null);
    setLoading(true);
    try {
      await apiFetch(mode === "reset" ? "/auth/password/reset/request" : "/auth/otp/request", {
        method: "POST",
        body: JSON.stringify({ phone })
      });
      setCodeSent(true);
      // Worded as a possibility on purpose: the server does not say whether the
      // number has an account, and this screen must not guess it either.
      setNotice("If that number has an account, a 6-digit code is on its way by SMS. It expires in 10 minutes.");
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Could not send a code.");
    } finally {
      setLoading(false);
    }
  }

  async function submitCode() {
    setError(null);
    setLoading(true);
    try {
      const body =
        mode === "reset" ? { phone, code: code.trim(), newPassword } : { phone, code: code.trim() };
      const signedInUser = await apiFetch<{ role: Role; groupId?: string | null }>(
        mode === "reset" ? "/auth/password/reset" : "/auth/otp/verify",
        { method: "POST", body: JSON.stringify(body) }
      );
      await afterSignIn(signedInUser);
    } catch (codeError) {
      setError(codeError instanceof Error ? codeError.message : "That code did not work.");
    } finally {
      setLoading(false);
    }
  }

  async function signIn(nextPhone: string = phone, nextPassword: string = password) {
    setError(null);
    setLoading(true);

    try {
      // Groups without a phone on record sign in with their group email, so the
      // one box takes either. Sending an email as `phone` failed for exactly
      // those groups.
      const identifier = nextPhone.trim();
      const credentials = isEmail(identifier)
        ? { email: identifier, password: nextPassword }
        : { phone: identifier, password: nextPassword };
      const signedInUser = await apiFetch<{ role: Role; groupId?: string | null; phone: string }>("/auth/login", {
        method: "POST",
        body: JSON.stringify(credentials)
      });
      await afterSignIn(signedInUser);
    } catch (loginError) {
      setError(loginError instanceof Error ? loginError.message : "Login failed");
    } finally {
      setLoading(false);
    }
  }

  function isPhone(value: string): boolean {
    return /^[\d\+\-\(\)\s]{7,20}$/.test(value);
  }

  function isEmail(value: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (mode === "password") {
      await signIn();
    } else if (!codeSent) {
      await requestCode();
    } else {
      await submitCode();
    }
  }

  function submitLabel() {
    if (mode === "password") return loading ? "Signing in" : "Sign in";
    if (!codeSent) return loading ? "Sending code" : "Send me a code";
    if (mode === "reset") return loading ? "Saving" : "Set password and sign in";
    return loading ? "Checking" : "Sign in";
  }

  async function signInAsDemo(account: (typeof demoAccounts)[number]) {
    setPhone(account.phone);
    setPassword(demoPassword);
    setActiveDemoPhone(account.phone);
    await signIn(account.phone, demoPassword);
    setActiveDemoPhone(null);
  }

  return (
    <main className="login-screen">
      <section className="login-copy" aria-label={ariaLabel}>
        <div className="logo-panel">
          <img
            alt="Intelli Cash - Trusted Financial Partner"
            className="brand-logo login-logo"
            src="/brand/intelli-cash-logo.png"
          />
        </div>
        <div>
          <h1>{copyTitle}</h1>
          <p>{copyText}</p>
        </div>
      </section>
      <section className="login-panel">
        <form className="login-form" onSubmit={onSubmit}>
          <h2>{mode === "reset" ? "Reset your password" : formTitle}</h2>
          <label>
            {/* One inline unit: the label lays its children out as rows, which
                left the icon alone on a line above the text. */}
            <span className="login-label">
              <Smartphone size={14} /> {mode === "password" ? "Phone number or group email" : "Phone number"}
            </span>
            <input
              autoComplete={mode === "password" ? "username" : "tel"}
              disabled={codeSent}
              onChange={(event) => setPhone(event.target.value)}
              required
              type={mode === "password" ? "text" : "tel"}
              placeholder="0712 345 678"
              value={phone}
            />
          </label>

          {mode === "password" ? (
            <label>
              Password
              <input
                autoComplete="current-password"
                onChange={(event) => setPassword(event.target.value)}
                required
                type="password"
                value={password}
              />
            </label>
          ) : null}

          {mode !== "password" && codeSent ? (
            <label>
              6-digit code from the SMS
              <input
                autoComplete="one-time-code"
                inputMode="numeric"
                maxLength={6}
                onChange={(event) => setCode(event.target.value.replace(/[^0-9]/g, ""))}
                required
                value={code}
              />
            </label>
          ) : null}

          {mode === "reset" && codeSent ? (
            <label>
              New password (at least 8 characters)
              <input
                autoComplete="new-password"
                minLength={8}
                onChange={(event) => setNewPassword(event.target.value)}
                required
                type="password"
                value={newPassword}
              />
            </label>
          ) : null}

          {notice ? <div className="notice">{notice}</div> : null}
          {error ? <div className="error">{error}</div> : null}

          <button className="button" disabled={loading} type="submit">
            <LogIn size={18} />
            {submitLabel()}
          </button>

          <div className="login-alternatives">
            {mode !== "code" ? (
              <button className="link-button" onClick={() => switchMode("code")} type="button">
                Sign in with a code sent to my phone
              </button>
            ) : null}
            {mode !== "reset" ? (
              <button className="link-button" onClick={() => switchMode("reset")} type="button">
                Forgot password?
              </button>
            ) : null}
            {mode !== "password" ? (
              <button className="link-button" onClick={() => switchMode("password")} type="button">
                Sign in with a password
              </button>
            ) : null}
            {mode !== "password" && codeSent ? (
              <button className="link-button" disabled={loading} onClick={() => void requestCode()} type="button">
                Send the code again
              </button>
            ) : null}
          </div>
        </form>
        {visibleDemoAccounts.length > 0 ? (
          <section className="demo-login">
            <header>
              <h3>Demo accounts</h3>
              <span>One-click access</span>
            </header>
            <div className="demo-account-list">
              {visibleDemoAccounts.map((account) => (
                <button
                  className="demo-account-button"
                  disabled={loading}
                  key={account.phone}
                  onClick={() => void signInAsDemo(account)}
                  type="button"
                >
                  <span>
                    <strong>{humanizeEnum(account.role)}</strong>
                    <small>{account.scope}</small>
                  </span>
                  <em>{activeDemoPhone === account.phone ? "Signing in" : "Open"}</em>
                </button>
              ))}
            </div>
          </section>
        ) : null}
      </section>
    </main>
  );
}
