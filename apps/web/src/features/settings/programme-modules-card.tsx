"use client";

import React, { useEffect, useState } from "react";
import { apiFetch } from "../../lib/api";

export type ProgrammeModuleRow = {
  id: string;
  name: string;
  storeEnabled: boolean;
  votingEnabled: boolean;
};

type ModuleField = "storeEnabled" | "votingEnabled";

const moduleLabels: Record<ModuleField, { title: string; note: string }> = {
  storeEnabled: {
    title: "Intelli-Store",
    note: "Store, credit requests and agent bookings for this programme's groups and on the public site."
  },
  votingEnabled: {
    title: "Voting",
    note: "Group polls and recorded resolutions on the phone and console."
  }
};

/**
 * Per-programme switches for the optional modules.
 *
 * A group gets a module when any programme it belongs to has it on. Admins can
 * still set a module up while it is off; everyone else only sees it once it is
 * switched on. Optimistic like the SMS switches: the box flips at once and
 * goes back if the server refuses.
 */
export function ProgrammeModulesCard() {
  const [rows, setRows] = useState<ProgrammeModuleRow[]>([]);
  const [saving, setSaving] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    let mounted = true;
    apiFetch<ProgrammeModuleRow[]>("/programmes")
      .then((programmes) => {
        if (!mounted) return;
        setRows(
          programmes.map((programme) => ({
            id: programme.id,
            name: programme.name,
            storeEnabled: Boolean(programme.storeEnabled),
            votingEnabled: Boolean(programme.votingEnabled)
          }))
        );
      })
      .catch((loadError) => {
        if (mounted) setError(loadError instanceof Error ? loadError.message : "Could not load programmes.");
      })
      .finally(() => {
        if (mounted) setLoaded(true);
      });
    return () => {
      mounted = false;
    };
  }, []);

  async function toggle(programmeId: string, field: ModuleField, enabled: boolean) {
    const previous = rows;
    setError(null);
    setSaving(`${programmeId}:${field}`);
    setRows((current) => current.map((row) => (row.id === programmeId ? { ...row, [field]: enabled } : row)));
    try {
      const saved = await apiFetch<ProgrammeModuleRow>(`/programmes/${programmeId}/modules`, {
        method: "PATCH",
        body: JSON.stringify({ [field]: enabled })
      });
      setRows((current) =>
        current.map((row) =>
          row.id === programmeId
            ? { ...row, storeEnabled: saved.storeEnabled, votingEnabled: saved.votingEnabled }
            : row
        )
      );
    } catch (saveError) {
      setRows(previous);
      setError(saveError instanceof Error ? saveError.message : "Could not save that switch.");
    } finally {
      setSaving(null);
    }
  }

  const count = (field: ModuleField) => rows.filter((row) => row[field]).length;

  return (
    <section className="data-card notification-sms" aria-label="Programme modules">
      <header>
        <div>
          <h3>Modules</h3>
          <span>
            Switch optional features on per programme. A group gets a module when any programme it
            belongs to has it on. Admins can prepare a module while it is off; groups, members,
            agents, partners and the public only see it once it is on.
          </span>
        </div>
        <span className="pill">
          Store {count("storeEnabled")} · Voting {count("votingEnabled")} of {rows.length}
        </span>
      </header>

      {error ? <div className="error">{error}</div> : null}

      <div className="notification-sms-list">
        {rows.map((row) => (
          <div className="list-row" key={row.id}>
            <div>
              <strong>{row.name}</strong>
            </div>
            {(Object.keys(moduleLabels) as ModuleField[]).map((field) => (
              <label className="checkbox-field" key={field}>
                <input
                  aria-label={`${moduleLabels[field].title} for ${row.name}`}
                  checked={row[field]}
                  disabled={saving === `${row.id}:${field}`}
                  onChange={(event) => toggle(row.id, field, event.target.checked)}
                  type="checkbox"
                />
                <span>
                  <strong>{moduleLabels[field].title}</strong>
                  <small>{moduleLabels[field].note}</small>
                </span>
              </label>
            ))}
          </div>
        ))}
        {loaded && rows.length === 0 && !error ? <div className="empty-state">No programmes yet</div> : null}
      </div>
    </section>
  );
}
