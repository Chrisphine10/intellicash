"use client";

import { useEffect, useMemo, useState } from "react";
import { CalendarDays, CheckCircle2, MapPinned, ShieldCheck } from "@/lib/theme-icons";
import { apiFetch, humanizeEnum } from "../../../lib/api";
import { DataTable } from "../../../components/dashboard/data-table";
import { StatCard } from "../../../components/dashboard/stat-card";

/**
 * Field visits recorded by agents.
 *
 * The column that earns its place is Location: it is the difference between a
 * visit that happened where it says it did and one typed up somewhere else.
 * The verdict is the server's — the phone reports a coordinate and nothing
 * more — so this page shows what was decided centrally rather than what the
 * device claimed.
 */

type VisitRow = {
  id: string;
  groupId: string;
  visitType: string;
  status: string;
  startedAt: string | null;
  submittedAt: string | null;
  notes: string | null;
  revision: number;
  authenticityFlags: string[];
  location: {
    latitude: number | null;
    longitude: number | null;
    accuracyM: number | null;
    distanceFromGroupM: number | null;
    outcome: string;
    withinGeofence: boolean;
    note: string | null;
  };
  group: { id: string; name: string; code: string; county: string | null } | null;
  agent: { id: string; name: string } | null;
};

function formatDate(value: string | null) {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "—" : date.toLocaleString();
}

/** Metres, rounded to something a person reads rather than 38.42917. */
function formatDistance(metres: number | null) {
  if (metres === null || !Number.isFinite(metres)) return "—";
  if (metres < 1000) return `${Math.round(metres)} m`;
  return `${(metres / 1000).toFixed(1)} km`;
}

const OUTCOME_LABEL: Record<string, string> = {
  WITHIN_GEOFENCE: "At the group",
  OUTSIDE_GEOFENCE: "Away from the group",
  LOW_ACCURACY: "Fix too vague",
  NO_DEVICE_FIX: "No location",
  NO_GROUP_LOCATION: "Group has no location"
};

/**
 * Deliberately three states, not two.
 *
 * "Fix too vague" and "no location" are not accusations — a phone under a tin
 * roof genuinely cannot do better — so they must not be dressed in the same
 * red as a visit filed from another town.
 */
function outcomeClass(outcome: string) {
  if (outcome === "WITHIN_GEOFENCE") return "pill blue";
  if (outcome === "OUTSIDE_GEOFENCE") return "pill red";
  // Gold, not red. "Fix too vague" and "no location" are facts about a phone
  // under a tin roof, not accusations, and must not be dressed the same as a
  // visit filed from another town.
  return "pill gold";
}

export default function VisitsPage() {
  const [visits, setVisits] = useState<VisitRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const data = await apiFetch<{ visits: VisitRow[] }>("/visits");
        if (!cancelled) setVisits(data.visits ?? []);
      } catch (loadError) {
        if (!cancelled) {
          setError(
            loadError instanceof Error ? loadError.message : "Could not load visits."
          );
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const summary = useMemo(() => {
    const total = visits.length;
    const confirmed = visits.filter((visit) => visit.location.withinGeofence).length;
    const away = visits.filter(
      (visit) => visit.location.outcome === "OUTSIDE_GEOFENCE"
    ).length;
    const groups = new Set(visits.map((visit) => visit.groupId)).size;
    return { total, confirmed, away, groups };
  }, [visits]);

  return (
    <>
      <section className="page-heading">
        <div>
          <p className="eyebrow">Field operations</p>
          <h2>Field visits</h2>
          <p>
            Visits recorded by agents on their phones. Location is checked against the
            group&apos;s registered meeting point by the server, not by the device.
          </p>
        </div>
      </section>

      <div className="stat-grid">
        <StatCard icon={<CalendarDays size={18} />} label="Visits" value={`${summary.total}`} />
        <StatCard
          icon={<CheckCircle2 size={18} />}
          label="Confirmed at the group"
          value={`${summary.confirmed}`}
          note="Device was inside the group's geofence"
        />
        <StatCard
          icon={<MapPinned size={18} />}
          label="Away from the group"
          value={`${summary.away}`}
          note="Worth a conversation, not an accusation"
        />
        <StatCard
          icon={<ShieldCheck size={18} />}
          label="Groups visited"
          value={`${summary.groups}`}
        />
      </div>

      {error ? <p className="error">{error}</p> : null}
      {loading ? (
        <p className="loading-panel">Loading visits…</p>
      ) : (
        <DataTable<VisitRow>
          title="Visits"
          rows={visits}
          exportName="intelli-cash-field-visits"
          getRowKey={(row) => row.id}
          defaultSort={{ key: "startedAt", direction: "desc" }}
          filters={[
            {
              key: "outcome",
              label: "Location",
              getValue: (row) => OUTCOME_LABEL[row.location.outcome] ?? row.location.outcome
            },
            {
              key: "visitType",
              label: "Visit type",
              getValue: (row) => humanizeEnum(row.visitType)
            }
          ]}
          columns={[
            {
              key: "group",
              header: "Group",
              value: (row) => row.group?.name ?? "—",
              cell: (row) => (
                <div className="record-card-meta">
                  <strong>{row.group?.name ?? "Unknown group"}</strong>
                  <span className="eyebrow">
                    {[row.group?.code, row.group?.county].filter(Boolean).join(" · ")}
                  </span>
                </div>
              ),
              searchable: true
            },
            {
              key: "agent",
              header: "Agent",
              value: (row) => row.agent?.name ?? "—",
              searchable: true
            },
            {
              key: "visitType",
              header: "Type",
              value: (row) => humanizeEnum(row.visitType)
            },
            {
              key: "startedAt",
              header: "Started",
              value: (row) => (row.startedAt ? new Date(row.startedAt) : ""),
              cell: (row) => formatDate(row.startedAt)
            },
            {
              key: "location",
              header: "Location",
              value: (row) => OUTCOME_LABEL[row.location.outcome] ?? row.location.outcome,
              cell: (row) => (
                <div className="record-card-meta">
                  <span className={outcomeClass(row.location.outcome)}>
                    {OUTCOME_LABEL[row.location.outcome] ?? row.location.outcome}
                  </span>
                  {row.location.distanceFromGroupM !== null ? (
                    <span className="eyebrow">
                      {formatDistance(row.location.distanceFromGroupM)} from the group
                      {row.location.accuracyM
                        ? ` · fix ±${Math.round(row.location.accuracyM)} m`
                        : ""}
                    </span>
                  ) : null}
                  {row.location.note ? (
                    <span className="eyebrow">“{row.location.note}”</span>
                  ) : null}
                </div>
              )
            },
            {
              key: "revision",
              header: "Revision",
              value: (row) => row.revision,
              cell: (row) =>
                row.revision > 1 ? (
                  // Amended visits keep their earlier text; the number is the
                  // signal that there is a prior version to read.
                  <span className="pill">Amended (r{row.revision})</span>
                ) : (
                  <span className="eyebrow">—</span>
                )
            }
          ]}
        />
      )}
    </>
  );
}
