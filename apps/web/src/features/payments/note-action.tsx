"use client";

import React from "react";
import type { FormEvent } from "react";
import { useState } from "react";

/**
 * A money action that needs a written reason (release a held payment, reject
 * an account, resolve a payout by hand). The reason is part of the audit
 * trail, so it is asked for in the page rather than a browser pop-up that is
 * easy to dismiss with nothing in it.
 */
export function NoteAction({
  label,
  placeholder,
  confirmLabel,
  onSubmit,
  disabled,
  primary = false,
  extraField
}: {
  label: string;
  placeholder: string;
  confirmLabel?: string;
  onSubmit: (note: string, extra: string) => Promise<unknown> | void;
  disabled?: boolean;
  primary?: boolean;
  /** An optional second input, e.g. the provider's receipt. */
  extraField?: { label: string; placeholder?: string };
}) {
  const [open, setOpen] = useState(false);
  const [note, setNote] = useState("");
  const [extra, setExtra] = useState("");

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (note.trim().length < 3) return;
    await onSubmit(note.trim(), extra.trim());
    setOpen(false);
    setNote("");
    setExtra("");
  }

  if (!open) {
    return (
      <button className={`button${primary ? "" : " secondary"}`} disabled={disabled} type="button" onClick={() => setOpen(true)}>
        {label}
      </button>
    );
  }

  return (
    <form className="note-action" onSubmit={submit}>
      {extraField ? (
        <label>
          {extraField.label}
          <input value={extra} placeholder={extraField.placeholder} onChange={(event) => setExtra(event.target.value)} />
        </label>
      ) : null}
      <label>
        Reason (kept in the audit trail)
        <input autoFocus minLength={3} required value={note} placeholder={placeholder} onChange={(event) => setNote(event.target.value)} />
      </label>
      <div className="form-actions">
        <button className="button" disabled={disabled || note.trim().length < 3} type="submit">
          {confirmLabel ?? label}
        </button>
        <button className="button secondary" type="button" onClick={() => setOpen(false)}>
          Cancel
        </button>
      </div>
    </form>
  );
}
