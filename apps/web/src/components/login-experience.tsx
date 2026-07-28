"use client";

import type { FormEvent } from "react";
import React, { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { LogIn, Smartphone } from "@/lib/theme-icons";
import { demoAccounts, demoPassword } from "@intellicash/shared";
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
    () => demoAccounts.filter((account) => !demoRoles || demoRoles.includes(account.role)),
    [demoRoles]
  );
  const initialAccount = visibleDemoAccounts[0] ?? demoAccounts[0];
  const [phone, setPhone] = useState<string>(initialAccount.phone);
  const [password, setPassword] = useState<string>(demoPassword);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [activeDemoPhone, setActiveDemoPhone] = useState<string | null>(null);

  async function signIn(nextPhone: string = phone, nextPassword: string = password) {
    setError(null);
    setLoading(true);

    try {
      const signedInUser = await apiFetch<{ role: Role; groupId?: string | null; phone: string }>("/auth/login", {
        method: "POST",
        body: JSON.stringify({ phone: nextPhone, password: nextPassword })
      });
      if (signedInUser.role === "GROUP_ACCOUNT" && signedInUser.groupId) {
        void refreshOfflinePinCache(signedInUser.groupId).catch(() => undefined);
      }
      router.push("/dashboard");
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
    await signIn();
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
          <h2>{formTitle}</h2>
          <label>
            <Smartphone size={16} /> Phone Number
            <input
              autoComplete="tel"
              onChange={(event) => setPhone(event.target.value)}
              required
              type="tel"
              placeholder="+254700000001"
              value={phone}
            />
          </label>
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
          {error ? <div className="error">{error}</div> : null}
          <button className="button" disabled={loading} type="submit">
            <LogIn size={18} />
            {loading ? "Signing in" : "Sign in"}
          </button>
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
