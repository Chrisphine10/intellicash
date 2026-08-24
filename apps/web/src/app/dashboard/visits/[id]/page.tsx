"use client";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, MapPinned } from "@/lib/theme-icons";
import { ApiClientError, apiFetch, evidenceSrc, humanizeEnum } from "../../../../lib/api";

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

interface Mentorship {
  sessions: {
    topicKey: string;
    topicTitle: string;
    notes: string | null;
    durationMinutes: number | null;
  }[];
  ratings: {
    dimensionKey: string;
    score: number;
    ratedByRole: string;
    comment: string | null;
  }[];
  /** Null when the group was never asked — which is not the same as zero. */
  averageGroupRating: number | null;
  ratedByGroup: boolean;
}

interface ActionItem {
  id: string;
  title: string;
  detail: string | null;
  owner: string | null;
  status: string;
  closingNote: string | null;
  /** Lateness is computed by the server on every read, never stored. */
  state: {
    state: string;
    label: string;
    daysOverdue: number;
    daysUntilDue: number | null;
    dueDate: string | null;
    open: boolean;
  };
}

export default function VisitDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [visit, setVisit] = useState<Visit | null>(null);
  const [attachments, setAttachments] = useState<Attachment[]>([]);
  const [assessment, setAssessment] = useState<AssessmentSummary | null>(null);
  const [mentorship, setMentorship] = useState<Mentorship | null>(null);
  const [actionItems, setActionItems] = useState<ActionItem[]>([]);
  const [busyItem, setBusyItem] = useState<string | null>(null);
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

      setMentorship(await apiFetch<Mentorship>(`/visits/${id}/mentorship`));
      await loadActionItems(visitData.groupId);
    }

    async function loadActionItems(groupId: string) {
      // Fetched for the GROUP, not the visit: an item raised here may be
      // closed at a later one, and the point of the plan is that it outlives
      // the occasion it was agreed at.
      const data = await apiFetch<{ items: ActionItem[] }>(
        `/groups/${groupId}/action-items`
      );
      setActionItems(data.items ?? []);
    }

    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load this visit."))
      .finally(() => setLoading(false));
  }, [id]);

  async function closeItem(itemId: string) {
    if (!visit) return;
    setBusyItem(itemId);
    try {
      await apiFetch(`/action-items/${itemId}`, {
        method: "PATCH",
        // Recorded against THIS visit, so the loop is traceable both ways:
        // where it was agreed, and where it was closed.
        body: JSON.stringify({ status: "DONE", closedAtVisitId: id })
      });
      const data = await apiFetch<{ items: ActionItem[] }>(
        `/groups/${visit.groupId}/action-items`
      );
      setActionItems(data.items ?? []);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not close that item.");
    } finally {
      setBusyItem(null);
    }
  }

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

      {mentorship && (mentorship.sessions.length > 0 || mentorship.ratings.length > 0) ? (
        <article className="data-card">
          <h3>Mentorship</h3>
          <p className="metric-value">
            {mentorship.averageGroupRating === null
              ? "Not rated"
              : `${mentorship.averageGroupRating} / 5`}
          </p>
          <p className="eyebrow">
            {mentorship.ratedByGroup
              ? "Scored by the group's representative. An agent's own score of their own session is recorded but never counted here."
              : "The group was not asked to score this session. That is different from a low score, and is why the figure above is blank rather than zero."}
          </p>

          {mentorship.sessions.length > 0 ? (
            <table className="data-table">
              <thead>
                <tr>
                  <th>Topic</th>
                  <th>What was advised</th>
                  <th>Time</th>
                </tr>
              </thead>
              <tbody>
                {mentorship.sessions.map((session) => (
                  <tr key={session.topicKey}>
                    {/* The title is the one snapshotted at the visit, so this
                        still reads correctly after a topic is retired. */}
                    <td>{session.topicTitle}</td>
                    <td>{session.notes ?? <span className="eyebrow">No note</span>}</td>
                    <td>
                      {session.durationMinutes === null
                        ? "—"
                        : `${session.durationMinutes} min`}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : null}

          {mentorship.ratings.length > 0 ? (
            <table className="data-table">
              <thead>
                <tr>
                  <th>Question</th>
                  <th>Score</th>
                  <th>Answered by</th>
                </tr>
              </thead>
              <tbody>
                {mentorship.ratings.map((rating) => (
                  <tr key={rating.dimensionKey}>
                    <td>{humanizeEnum(rating.dimensionKey)}</td>
                    <td>{rating.score} / 5</td>
                    <td>
                      {rating.ratedByRole === "GROUP_REPRESENTATIVE" ? (
                        "The group"
                      ) : (
                        <span className="pill gold">The agent</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : null}
        </article>
      ) : null}

      <article className="data-card">
        <h3>Action plan</h3>
        {actionItems.length === 0 ? (
          <p className="eyebrow">Nothing was agreed for this group.</p>
        ) : (
          <>
            <p className="eyebrow">
              Everything outstanding for this group, not only what was raised at this
              visit — an item agreed here is usually closed at the next one.
              &ldquo;Overdue&rdquo; is worked out from the due date on every read, so it
              cannot drift out of step with the calendar.
            </p>
            <table className="data-table">
              <thead>
                <tr>
                  <th>Action</th>
                  <th>Owner</th>
                  <th>Due</th>
                  <th>State</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {actionItems.map((item) => (
                  <tr key={item.id}>
                    <td>
                      {item.title}
                      {item.detail ? (
                        <div className="eyebrow">{item.detail}</div>
                      ) : null}
                    </td>
                    <td>{item.owner ?? "—"}</td>
                    <td>
                      {item.state.dueDate
                        ? new Date(item.state.dueDate).toLocaleDateString()
                        : "—"}
                      {item.state.daysOverdue > 0 ? (
                        <div className="eyebrow">
                          {item.state.daysOverdue} days late
                        </div>
                      ) : null}
                    </td>
                    <td>
                      <span className={actionPill(item.state.state)}>
                        {item.state.label}
                      </span>
                    </td>
                    <td>
                      {item.state.open ? (
                        <button
                          className="button subtle"
                          disabled={busyItem === item.id}
                          onClick={() => closeItem(item.id)}
                        >
                          Mark done
                        </button>
                      ) : (
                        <span className="eyebrow">{item.closingNote ?? "Closed"}</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </>
        )}
      </article>

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
                      <a href={evidenceSrc(attachment.url)} target="_blank" rel="noreferrer">
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img
                          src={evidenceSrc(attachment.url)}
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

/**
 * Maps the server's derived state onto the house pill variants.
 *
 * Keyed on the derived state rather than the stored status, so the colour and
 * the label can never disagree.
 */
function actionPill(state: string) {
  if (state === "OVERDUE") return "pill red";
  if (state === "DUE_SOON") return "pill gold";
  return "pill";
}
