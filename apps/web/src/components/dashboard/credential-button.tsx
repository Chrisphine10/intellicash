"use client";

import React from "react";
import { LockKeyhole, Smartphone } from "@/lib/theme-icons";

/**
 * The one button for every credential action in the console.
 *
 * These had drifted: "Send PIN" was primary with a key on one page and compact
 * on another, "Send OTP" was secondary with a phone icon on one page and primary
 * with a key on the next, and one page said "Send default PIN". A reader could
 * not tell from the button whether it would text somebody or change something
 * on the spot — which is the one thing they need to know before pressing it.
 *
 * The rule, enforced here rather than remembered:
 *
 * - `sms` — texts something to the person's phone (a PIN, an OTP, a reset
 *   code). Phone icon, "Send …" wording, "Sending" while busy.
 * - `password` — sets a password directly, now. Lock icon, "Saving" while busy.
 *
 * `emphasis` is the card's main action versus an alternative beside it.
 */
export function CredentialButton({
  kind,
  label,
  busy = false,
  disabled = false,
  emphasis = "primary",
  compact = false,
  type = "button",
  onClick
}: {
  kind: "sms" | "password";
  label: string;
  busy?: boolean;
  disabled?: boolean;
  emphasis?: "primary" | "secondary";
  compact?: boolean;
  type?: "button" | "submit";
  onClick?: () => void;
}) {
  const className = ["button", emphasis === "secondary" ? "secondary" : "", compact ? "compact" : ""]
    .filter(Boolean)
    .join(" ");
  const Icon = kind === "sms" ? Smartphone : LockKeyhole;

  return (
    <button className={className} disabled={disabled || busy} onClick={onClick} type={type}>
      <Icon size={16} />
      {busy ? (kind === "sms" ? "Sending" : "Saving") : label}
    </button>
  );
}
