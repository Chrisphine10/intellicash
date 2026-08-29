"use client";

import React from "react";
import type { FormEvent } from "react";
import { use, useEffect, useState } from "react";
import Link from "next/link";
import { BadgeCheck, MapPinned, ShieldCheck, UserPlus } from "@/lib/theme-icons";
import { apiFetch } from "../../../lib/api";
import { PublicSiteFooter } from "../../../components/public-site-footer";
import { PublicSiteHeader } from "../../../components/public-site-header";

const playStoreUrl = "https://play.google.com/store/apps/details?id=com.intellicash.app";

/**
 * The page behind a group's invite link and QR code.
 *
 * One form, for somebody who has never used Intelli-Cash: their name, their
 * phone, a password. It creates the account and files the request in a single
 * call, so a dropped signal cannot leave them with an account and no request.
 *
 * It says three times, in three ways, that this is a REQUEST. The commonest
 * misreading of an invite link is "I am in now", and a person who believes they
 * are a member will go looking for savings that do not exist yet.
 */

interface JoinGroup {
  id: string;
  name: string;
  county?: string | null;
  subCounty?: string | null;
}

interface JoinResult {
  group: { id: string; name: string };
  status: string;
  message: string;
}

export default function JoinByLinkPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = use(params);

  const [group, setGroup] = useState<JoinGroup | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [done, setDone] = useState<JoinResult | null>(null);

  useEffect(() => {
    apiFetch<{ group: JoinGroup }>(`/public/join/${token}`)
      .then((response) => setGroup(response.group))
      .catch((error) =>
        setLoadError(
          error instanceof Error
            ? error.message
            : "This invite link is not valid any more."
        )
      )
      .finally(() => setLoading(false));
  }, [token]);

  async function submit(event: FormEvent) {
    event.preventDefault();
    setSubmitting(true);
    setSubmitError(null);
    try {
      const response = await apiFetch<JoinResult>(`/public/join/${token}`, {
        method: "POST",
        body: JSON.stringify({ name, phone, password })
      });
      setDone(response);
    } catch (error) {
      setSubmitError(error instanceof Error ? error.message : "Could not send your request.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <>
      <PublicSiteHeader playStoreUrl={playStoreUrl} />
      <main className="join-page">
        <div className="join-card">
          {loading ? (
            <p className="join-loading">Checking this invite…</p>
          ) : loadError || !group ? (
            <div className="join-state join-state-error">
              <h1>This link no longer works</h1>
              <p>
                {loadError ?? "This invite link is not valid any more."} Ask the group for a
                fresh link or QR code — they can issue a new one from the app at any time.
              </p>
              <Link className="join-button secondary" href="/">
                Go to Intelli-Cash
              </Link>
            </div>
          ) : done ? (
            <div className="join-state join-state-done">
              <span className="join-tick" aria-hidden="true">
                <BadgeCheck size={30} />
              </span>
              <h1>Request sent</h1>
              <p>{done.message}</p>
              <p className="join-fineprint">
                You are <strong>not a member yet</strong>. Nothing of the group&apos;s money is
                visible to you until an official approves. If they do, your savings appear in the
                app under your own name.
              </p>
              <Link className="join-button" href="/login">
                Sign in to Intelli-Cash
              </Link>
            </div>
          ) : (
            <>
              <header className="join-head">
                <span className="join-badge" aria-hidden="true">
                  <UserPlus size={22} />
                </span>
                <p className="join-kicker">You have been invited to join</p>
                <h1>{group.name}</h1>
                {group.county ? (
                  <p className="join-place">
                    <MapPinned size={15} />
                    {[group.subCounty, group.county].filter(Boolean).join(", ")}
                  </p>
                ) : null}
              </header>

              <p className="join-explainer">
                Fill this in once. It creates your Intelli-Cash account and asks {group.name} to
                add you to their register.
              </p>

              <form className="join-form" onSubmit={submit}>
                <label className="join-field">
                  <span>Your full name</span>
                  <input
                    autoComplete="name"
                    disabled={submitting}
                    minLength={2}
                    onChange={(event) => setName(event.target.value)}
                    placeholder="As the group knows you"
                    required
                    value={name}
                  />
                </label>

                <label className="join-field">
                  <span>Your phone number</span>
                  <input
                    autoComplete="tel"
                    disabled={submitting}
                    inputMode="tel"
                    onChange={(event) => setPhone(event.target.value)}
                    placeholder="07XX XXX XXX"
                    required
                    type="tel"
                    value={phone}
                  />
                  <small>
                    Use the number the group already has for you. It is how your savings are
                    matched to you.
                  </small>
                </label>

                <label className="join-field">
                  <span>Choose a password</span>
                  <input
                    autoComplete="new-password"
                    disabled={submitting}
                    minLength={6}
                    onChange={(event) => setPassword(event.target.value)}
                    placeholder="At least 6 characters"
                    required
                    type="password"
                    value={password}
                  />
                </label>

                {submitError ? <p className="join-error">{submitError}</p> : null}

                <button className="join-button" disabled={submitting} type="submit">
                  {submitting ? "Sending your request…" : "Ask to join"}
                </button>
              </form>

              <p className="join-guard">
                <ShieldCheck size={15} />
                <span>
                  This sends a request. An official of {group.name} decides whether you are
                  added — sharing this link does not let anyone into the group&apos;s records.
                </span>
              </p>

              <p className="join-fineprint">
                Already have an account? <Link href="/login">Sign in</Link> and ask to join from
                there. By continuing you agree to our{" "}
                <Link href="/privacy">privacy notice</Link>.
              </p>
            </>
          )}
        </div>
      </main>
      <PublicSiteFooter playStoreUrl={playStoreUrl} />
    </>
  );
}
