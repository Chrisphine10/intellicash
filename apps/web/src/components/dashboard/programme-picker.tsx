"use client";

import React from "react";
import { useMemo } from "react";

import type { ProgrammeRow } from "./types";

/**
 * Chooses the programmes a village agent serves.
 *
 * An agent may serve several programmes, but all of them must belong to one
 * partner — an agent who straddled two partners would carry another partner's
 * groups, ratings and visit notes inside their caseload.
 *
 * The rule is shown rather than enforced after the fact. As soon as one
 * programme is ticked, everything belonging to a different partner is disabled
 * and says why. Submitting and being told "these belong to two partners" would
 * be a correct error message and a bad interface: by then the person has
 * already made the choice and has to work out which of their ticks was wrong.
 *
 * Grouped by partner because that is the shape of the decision. A flat list of
 * thirty programmes makes the constraint invisible until it is violated.
 */
export function ProgrammePicker({
  programmes,
  selectedIds,
  onChange,
  disabled = false
}: {
  programmes: ProgrammeRow[];
  selectedIds: string[];
  onChange: (next: string[]) => void;
  disabled?: boolean;
}) {
  const selected = useMemo(() => new Set(selectedIds), [selectedIds]);

  /** The partner the current selection locks us to, if any. */
  const lockedPartnerId = useMemo(() => {
    const first = programmes.find((programme) => selected.has(programme.id));
    return first?.partner?.id ?? null;
  }, [programmes, selected]);

  const byPartner = useMemo(() => {
    const groups = new Map<string, { name: string; items: ProgrammeRow[] }>();
    for (const programme of programmes) {
      const id = programme.partner?.id ?? "__none__";
      const name = programme.partner?.name ?? "No partner";
      const bucket = groups.get(id) ?? { name, items: [] };
      bucket.items.push(programme);
      groups.set(id, bucket);
    }
    // The locked partner first: it is the only one that can be acted on.
    return [...groups.entries()].sort(([a], [b]) =>
      a === lockedPartnerId ? -1 : b === lockedPartnerId ? 1 : 0
    );
  }, [programmes, lockedPartnerId]);

  function toggle(programme: ProgrammeRow) {
    const next = new Set(selected);
    if (next.has(programme.id)) {
      next.delete(programme.id);
    } else {
      next.add(programme.id);
    }
    onChange([...next]);
  }

  if (programmes.length === 0) {
    return (
      <p className="programme-picker-empty">
        No programmes yet. Create one first, then come back to assign this agent.
      </p>
    );
  }

  return (
    <div className="programme-picker">
      <div className="programme-picker-head">
        <span>
          {selected.size === 0
            ? "No programmes yet"
            : `${selected.size} programme${selected.size === 1 ? "" : "s"}`}
        </span>
        {selected.size > 0 ? (
          <button className="link-button" disabled={disabled} onClick={() => onChange([])} type="button">
            Clear
          </button>
        ) : null}
      </div>

      <div className="programme-picker-list">
        {byPartner.map(([partnerId, group]) => {
          const blocked = lockedPartnerId !== null && partnerId !== lockedPartnerId;

          return (
            <div className="programme-picker-group" key={partnerId}>
              <p className="programme-picker-partner">
                {group.name}
                {blocked ? <span className="programme-picker-note">different partner</span> : null}
              </p>

              {group.items.map((programme) => {
                const isSelected = selected.has(programme.id);
                return (
                  <label
                    className={`programme-picker-option ${isSelected ? "is-selected" : ""} ${
                      blocked ? "is-blocked" : ""
                    }`}
                    key={programme.id}
                  >
                    <input
                      checked={isSelected}
                      disabled={disabled || blocked}
                      onChange={() => toggle(programme)}
                      type="checkbox"
                    />
                    <span className="programme-picker-name">{programme.name}</span>
                    {programme.county ? (
                      <span className="programme-picker-county">{programme.county}</span>
                    ) : null}
                  </label>
                );
              })}
            </div>
          );
        })}
      </div>

      {lockedPartnerId ? (
        <p className="programme-picker-hint">
          An agent works for one partner. Clear the selection to choose programmes from
          another.
        </p>
      ) : null}
    </div>
  );
}
