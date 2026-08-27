"use client";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, MapPinned } from "@/lib/theme-icons";
import { ApiClientError, apiFetch, evidenceSrc, humanizeEnum } from "../../../../lib/api";
import {
  AssessmentRecord,
  type AssessmentRecordData
} from "../../../../components/dashboard/assessment-record";

/**
 * One field visit, with the evidence collected during it.
 *
 * The evidence is grouped by the section it answers rather than shown as a
 * gallery. A photograph on its own proves nothing — what makes it evidence is
 * the claim it sits against, so the claim is what the page is organised by.
 */
/**
 * The visit itself. `GET /visits/:id` answers with a WRAPPER —
 * `{ visit, group, agent, submittedBy, revisions }` — and this page used to
 * treat that wrapper as the visit.
 *
 * The result was every field on the page reading undefined, and
 * `visitData.groupId` resolving to nothing, so the action-plan call went to
 * `/groups/undefined/action-items` and came back "Group does not exist or is
 * outside your access." An admin has unrestricted group scope, so the message
 * was true and useless: there is no group called "undefined".
 */
interface Visit {
  id: string;
  groupId: string;
  clientRequestId: string;
  visitType: string;
  status: string;
  startedAt: string | null;
  completedAt?: string | null;
  submittedAt: string | null;
  revision?: number;
  authenticityFlags?: string[];
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

interface VisitGroup {
  id: string;
  name: string;
  code: string;
  county?: string | null;
  subCounty?: string | null;
  location?: string | null;
  phase?: string | null;
}

/** One amendment. A submitted visit is immutable; edits append. */
interface Revision {
  revision: number;
  reason: string | null;
  amendedByUserId: string | null;
  createdAt: string;
}

interface VisitDetail {
  visit: Visit;
  group: VisitGroup | null;
  agent: { id: string; name: string; phone: string | null } | null;
  submittedBy: { id: string; name: string } | null;
  revisions: Revision[];
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
  const [group, setGroup] = useState<VisitGroup | null>(null);
  const [agent, setAgent] = useState<VisitDetail["agent"]>(null);
  const [submittedBy, setSubmittedBy] = useState<VisitDetail["submittedBy"]>(null);
  const [revisions, setRevisions] = useState<Revision[]>([]);
  const [attachments, setAttachments] = useState<Attachment[]>([]);
  const [assessment, setAssessment] = useState<AssessmentRecordData | null>(null);
  const [mentorship, setMentorship] = useState<Mentorship | null>(null);
  const [actionItems, setActionItems] = useState<ActionItem[]>([]);
  const [busyItem, setBusyItem] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    async function load() {
      const [detail, attachmentData] = await Promise.all([
        apiFetch<VisitDetail>(`/visits/${id}`),
        apiFetch<Attachment[]>(`/visits/${id}/attachments`)
      ]);

      setVisit(detail.visit);
      setGroup(detail.group);
      setAgent(detail.agent);
      setSubmittedBy(detail.submittedBy);
      setRevisions(detail.revisions ?? []);
      setAttachments(attachmentData ?? []);

      // A visit without an assessment is ordinary — an agent may record a visit
      // without filling the scorecard in — so a 404 here is not an error.
      try {
        setAssessment(await apiFetch<AssessmentRecordData>(`/visits/${id}/assessment`));
      } catch (e) {
        if (!(e instanceof ApiClientError && e.status === 404)) throw e;
      }

      setMentorship(await apiFetch<Mentorship>(`/visits/${id}/mentorship`));
      await loadActionItems(detail.visit.groupId);
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
          <h2>{group?.name ?? "Visit"}</h2>
          <p>
            {humanizeEnum(visit.visitType)}
            {agent ? ` · ${agent.name}` : ""}
            {visit.startedAt ? ` · ${new Date(visit.startedAt).toLocaleString()}` : ""}
          </p>
          {group ? (
            <p className="eyebrow">
              <Link href={`/dashboard/groups/${group.id}`}>{group.code}</Link>
              {group.location ? ` · ${group.location}` : ""}
              {group.subCounty ? `, ${group.subCounty}` : ""}
              {group.county ? `, ${group.county}` : ""}
              {group.phase ? ` · ${humanizeEnum(group.phase)}` : ""}
            </p>
          ) : null}
        </div>
        <MapPinned size={22} />
      </header>

      {/* What the visit IS, before what it found. Previously none of this was
          on the page at all: an amended visit looked identical to an original,
          and a submission recorded weeks after the fact looked like one made on
          the day. */}
      <article className="data-card">
        <h3>The visit</h3>
        <div className="enterprise-figures">
          <div className="enterprise-figure">
            <span className="label">Type</span>
            <span className="value">{humanizeEnum(visit.visitType)}</span>
          </div>
          <div className="enterprise-figure">
            <span className="label">Status</span>
            <span className="value">{humanizeEnum(visit.status)}</span>
          </div>
          <div className="enterprise-figure">
            <span className="label">Visited</span>
            <span className="value">
              {visit.startedAt ? new Date(visit.startedAt).toLocaleDateString() : "—"}
            </span>
          </div>
          <div className="enterprise-figure">
            <span className="label">Submitted</span>
            <span className="value">
              {visit.submittedAt ? new Date(visit.submittedAt).toLocaleDateString() : "—"}
            </span>
          </div>
          <div className="enterprise-figure">
            <span className="label">Agent</span>
            <span className="value">{agent?.name ?? "Not recorded"}</span>
          </div>
          <div className="enterprise-figure">
            <span className="label">Revision</span>
            <span className="value">{visit.revision ?? 1}</span>
          </div>
        </div>

        {submittedBy && agent && submittedBy.name !== agent.name ? (
          <p className="eyebrow">
            Entered by {submittedBy.name}, who is not the agent named on it.
          </p>
        ) : null}

        {visit.notes ? <p className="eyebrow">{visit.notes}</p> : null}

        {visit.authenticityFlags && visit.authenticityFlags.length > 0 ? (
          <p className="notice warning">
            {/* Raised by the server at submission. Worth reading before the
                score below is quoted anywhere. */}
            Flagged at submission: {visit.authenticityFlags.join(", ")}
          </p>
        ) : null}
      </article>

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
          {visit.location.latitude !== null && visit.location.longitude !== null ? (
            <p className="eyebrow">
              {visit.location.latitude}, {visit.location.longitude}
            </p>
          ) : null}
          {visit.location.note ? (
            <p className="eyebrow">
              {/* An explanation, not evidence. The server's own distance
                  calculation is the signal; this says why it may look wrong. */}
              Agent&apos;s explanation: {visit.location.note}
            </p>
          ) : null}
        </article>
      ) : null}

      {assessment ? <AssessmentRecord assessment={assessment} /> : null}

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
                  <th>What they said</th>
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
                    <td>
                      {/* The sentence is usually worth more than the score: a 3
                          with a reason is actionable, a 3 alone is not. */}
                      {rating.comment ?? <span className="eyebrow">No comment</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : null}
        </article>
      ) : null}

      {/* The amendment trail. A SUBMITTED visit is immutable and edits append a
          revision, which is the whole point of that design — but the page threw
          the revisions away, so a corrected visit was indistinguishable from an
          untouched one. */}
      {revisions.length > 0 ? (
        <article className="data-card">
          <h3>Amendments</h3>
          <p className="eyebrow">
            This visit has been amended {revisions.length}
            {revisions.length === 1 ? " time" : " times"}. The original submission is
            kept; each change is recorded rather than overwriting it.
          </p>
          <table className="data-table">
            <thead>
              <tr>
                <th>Revision</th>
                <th>When</th>
                <th>Reason</th>
              </tr>
            </thead>
            <tbody>
              {revisions.map((revision) => (
                <tr key={revision.revision}>
                  <td>{revision.revision}</td>
                  <td>{new Date(revision.createdAt).toLocaleString()}</td>
                  <td>{revision.reason ?? <span className="eyebrow">No reason given</span>}</td>
                </tr>
              ))}
            </tbody>
          </table>
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
