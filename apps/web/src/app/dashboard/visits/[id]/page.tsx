"use client";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, MapPinned } from "@/lib/theme-icons";
import { ApiClientError, apiFetch, humanizeEnum } from "../../../../lib/api";

/**
 * One field visit, with the evidence collected during it.
 *
 * The evidence is grouped by the section it answers rather than shown as a
 * gallery. A photograph on its own proves nothing — what makes it evidence is
 * the claim it sits against, so the claim is what the page is organised by.
 */
interface Visit {
  id: string;
  groupId: string;
  visitType: string;
  status: string;
  startedAt: string | null;
  submittedAt: string | null;
  group?: { name: string; code: string } | null;
  agentName?: string | null;
  notes?: string | null;
  location?: {
    outcome: string;
    withinGeofence: boolean;
    distanceFromGroupM: number | null;
    accuracyM: number | null;
    latitude: number | null;
    longitude: number | null;
    note?: string | null;
  } | null;
}

interface Attachment {
  id: string;
  sectionKey: string | null;
  questionKey: string | null;
  url: string;
  fileName: string;
  size: number;
  capturedAt: string | null;
  caption: string | null;
}

interface AssessmentSummary {
  earnedPoints: number;
  maxPoints: number;
  percentage: number;
  bandLabel: string | null;
  complete: boolean;
  templateVersion: number;
}

export default function VisitDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [visit, setVisit] = useState<Visit | null>(null);
  const [attachments, setAttachments] = useState<Attachment[]>([]);
  const [assessment, setAssessment] = useState<AssessmentSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    async function load() {
      const [visitData, attachmentData] = await Promise.all([
        apiFetch<Visit>(`/visits/${id}`),
        apiFetch<Attachment[]>(`/visits/${id}/attachments`)
      ]);
      setVisit(visitData);
      setAttachments(attachmentData ?? []);

      // A visit without an assessment is ordinary — an agent may record a visit
      // without filling the scorecard in — so a 404 here is not an error.
      try {
        setAssessment(await apiFetch<AssessmentSummary>(`/visits/${id}/assessment`));
      } catch (e) {
        if (!(e instanceof ApiClientError && e.status === 404)) throw e;
      }
    }

    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load this visit."))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) return <div className="loading-panel">Loading visit…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!visit) return <div className="empty-state">Visit not found.</div>;

  const bySection = new Map<string, Attachment[]>();
  for (const attachment of attachments) {
    const key = attachment.sectionKey ?? "unfiled";
    bySection.set(key, [...(bySection.get(key) ?? []), attachment]);
  }

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href="/dashboard/visits">
            <ArrowLeft size={17} />
            <span>Field visits</span>
          </Link>
          <h2>{visit.group?.name ?? "Visit"}</h2>
          <p>
            {humanizeEnum(visit.visitType)}
            {visit.agentName ? ` · ${visit.agentName}` : ""}
            {visit.startedAt ? ` · ${new Date(visit.startedAt).toLocaleString()}` : ""}
          </p>
        </div>
        <MapPinned size={22} />
      </header>

      {visit.location ? (
        <article className="data-card">
          <h3>Where it happened</h3>
          <p className="metric-value">
            {visit.location.withinGeofence ? "At the group" : humanizeEnum(visit.location.outcome)}
          </p>
          <p className="eyebrow">
            {visit.location.distanceFromGroupM === null
              ? "No distance could be computed."
              : `${formatDistance(visit.location.distanceFromGroupM)} from the group's registered point`}
            {visit.location.accuracyM ? ` · fix ±${Math.round(visit.location.accuracyM)} m` : ""}
          </p>
          {visit.location.note ? (
            <p className="eyebrow">
              Agent&apos;s explanation: {visit.location.note}
            </p>
          ) : null}
        </article>
      ) : null}

      {assessment ? (
        <article className="data-card">
          <h3>Assessment</h3>
          <p className="metric-value">
            {assessment.earnedPoints} / {assessment.maxPoints}
            {assessment.bandLabel ? ` · ${assessment.bandLabel}` : ""}
          </p>
          <p className="eyebrow">
            {assessment.percentage}% · scorecard v{assessment.templateVersion}
            {assessment.complete ? "" : " · some questions were left unanswered"}
          </p>
        </article>
      ) : null}

      <article className="data-card">
        <h3>Evidence</h3>
        {attachments.length === 0 ? (
          <p className="eyebrow">No photographs were taken during this visit.</p>
        ) : (
          <>
            <p className="eyebrow">
              {attachments.length} photo{attachments.length === 1 ? "" : "s"}, grouped by the
              question each one answers. A photograph with no claim attached to it is not
              evidence of anything, so every one here names what it was taken to show.
            </p>
            {[...bySection.entries()].map(([sectionKey, items]) => (
              <div key={sectionKey}>
                <h4>{humanizeEnum(sectionKey)}</h4>
                <div className="evidence-grid">
                  {items.map((attachment) => (
                    <figure key={attachment.id} className="evidence-item">
                      <a href={attachment.url} target="_blank" rel="noreferrer">
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img
                          src={attachment.url}
                          alt={attachment.caption ?? attachment.questionKey ?? "Visit evidence"}
                          loading="lazy"
                        />
                      </a>
                      <figcaption className="eyebrow">
                        {attachment.questionKey
                          ? humanizeEnum(attachment.questionKey)
                          : "Section evidence"}
                        {attachment.capturedAt
                          ? ` · ${new Date(attachment.capturedAt).toLocaleDateString()}`
                          : ""}
                      </figcaption>
                    </figure>
                  ))}
                </div>
              </div>
            ))}
          </>
        )}
      </article>

      {visit.notes ? (
        <article className="data-card">
          <h3>Notes</h3>
          <p>{visit.notes}</p>
        </article>
      ) : null}

      <p className="eyebrow">
        A submitted visit is immutable. Corrections are recorded as amendments, which keep the
        original report readable alongside them.
      </p>
    </section>
  );
}

function formatDistance(metres: number) {
  if (metres >= 1000) return `${(metres / 1000).toFixed(1)} km`;
  return `${Math.round(metres)} m`;
}
