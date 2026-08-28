"use client";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft } from "@/lib/theme-icons";
import {
  ApiClientError,
  apiFetch,
  evidenceSrc,
  formatDate,
  formatDateTime,
  humanizeEnum
} from "../../../../lib/api";
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

type ActionStatus = "OPEN" | "IN_PROGRESS" | "DONE" | "CANCELLED";

interface NewAction {
  title: string;
  detail: string;
  owner: string;
  dueDate: string;
}

const EMPTY_ACTION: NewAction = { title: "", detail: "", owner: "", dueDate: "" };

/**
 * Who an action can belong to.
 *
 * A datalist rather than a select: these cover almost every case, and a group
 * that hands something to a named member must still be able to say so.
 */
const ACTION_OWNERS = [
  "Chairperson",
  "Secretary",
  "Treasurer",
  "Money counter",
  "Key holder",
  "The group",
  "Field agent"
];

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
  const [addingAction, setAddingAction] = useState(false);
  const [savingAction, setSavingAction] = useState(false);
  const [newAction, setNewAction] = useState<NewAction>(EMPTY_ACTION);
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

  async function refreshActionItems(groupId: string) {
    const data = await apiFetch<{ items: ActionItem[] }>(`/groups/${groupId}/action-items`);
    setActionItems(data.items ?? []);
  }

  /**
   * Change an item's state.
   *
   * `closedAtVisitId` is sent whenever an item is being closed, so the loop is
   * traceable both ways: where the work was agreed, and where it was signed
   * off. Reopening clears it server-side rather than leaving an item that
   * claims to have been finished at a visit months ago.
   */
  async function setItemStatus(itemId: string, status: ActionStatus) {
    if (!visit) return;
    setBusyItem(itemId);
    setError(null);
    try {
      const closing = status === "DONE" || status === "CANCELLED";
      await apiFetch(`/action-items/${itemId}`, {
        method: "PATCH",
        body: JSON.stringify({ status, ...(closing ? { closedAtVisitId: id } : {}) })
      });
      await refreshActionItems(visit.groupId);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not update that item.");
    } finally {
      setBusyItem(null);
    }
  }

  /**
   * Agree a new action against THIS visit.
   *
   * The endpoint has existed since the action plan shipped and nothing called
   * it: the console could close an item and never raise one, so anything
   * agreed at a visit had to be typed into the phone or lost. The same gap
   * existed in the mobile app.
   */
  async function addAction(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!visit) return;

    const title = newAction.title.trim();
    if (!title) {
      setError("An action needs a title — what the group agreed to do.");
      return;
    }

    setSavingAction(true);
    setError(null);
    try {
      await apiFetch(`/visits/${id}/action-items`, {
        method: "POST",
        body: JSON.stringify({
          title,
          ...(newAction.detail.trim() ? { detail: newAction.detail.trim() } : {}),
          ...(newAction.owner.trim() ? { owner: newAction.owner.trim() } : {}),
          ...(newAction.dueDate ? { dueDate: newAction.dueDate } : {})
        })
      });
      await refreshActionItems(visit.groupId);
      setNewAction(EMPTY_ACTION);
      setAddingAction(false);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not add that action.");
    } finally {
      setSavingAction(false);
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

  const openItems = actionItems.filter((item) => item.state.open);
  const overdueItems = openItems.filter((item) => item.state.state === "OVERDUE");

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
            {visit.startedAt ? ` · ${formatDateTime(visit.startedAt)}` : ""}
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
        {/* The status, not a decorative map pin. The pin said nothing and was
            the only thing in the corner where a reader looks for the state of
            the record they are reading. */}
        <span className={visit.status === "SUBMITTED" ? "pill" : "pill gold"}>
          {humanizeEnum(visit.status)}
        </span>
      </header>

      {/*
        * The four questions a reader opens this page with, answered before any
        * scrolling: did it score well, was it where it should have been, what
        * is still owed, and is there anything to look at.
        *
        * The page is 5,600 pixels long -- 46 scorecard rows sit between the top
        * and the action plan -- so without this the one section a reader has to
        * ACT on was the furthest away. Each tile jumps to its section.
        */}
      <div className="visit-summary">
        <a className="visit-summary-tile" href="#visit-assessment">
          <span className="label">Assessment</span>
          {assessment ? (
            <>
              <strong>{assessment.percentage}%</strong>
              <span className={`pill ${bandPill(assessment.bandLabel)}`}>
                {assessment.bandLabel ?? "Unscored"}
              </span>
              <ScoreBar percentage={assessment.percentage} />
            </>
          ) : (
            <>
              <strong>Not scored</strong>
              <span className="note">The scorecard was not filled in.</span>
            </>
          )}
        </a>

        <a className="visit-summary-tile" href="#visit-location">
          <span className="label">Location</span>
          {visit.location ? (
            <>
              <strong>{locationHeadline(visit.location)}</strong>
              <span className={visit.location.withinGeofence ? "pill" : "pill gold"}>
                {visit.location.withinGeofence ? "At the group" : "Not confirmed"}
              </span>
            </>
          ) : (
            <>
              <strong>Not recorded</strong>
              <span className="note">The phone never got a fix.</span>
            </>
          )}
        </a>

        <a className="visit-summary-tile" href="#visit-actions">
          <span className="label">Still to do</span>
          <strong>
            {openItems.length} open
          </strong>
          {overdueItems.length > 0 ? (
            <span className="pill red">{overdueItems.length} overdue</span>
          ) : (
            <span className="note">
              {openItems.length === 0 ? "Nothing outstanding." : "None overdue."}
            </span>
          )}
        </a>

        <a className="visit-summary-tile" href="#visit-evidence">
          <span className="label">Evidence</span>
          <strong>
            {attachments.length} photo{attachments.length === 1 ? "" : "s"}
          </strong>
          <span className="note">
            {attachments.length === 0
              ? "Nothing was photographed."
              : `Across ${bySection.size} section${bySection.size === 1 ? "" : "s"}.`}
          </span>
        </a>
      </div>

      {/* What the visit IS, before what it found. Previously none of this was
          on the page at all: an amended visit looked identical to an original,
          and a submission recorded weeks after the fact looked like one made on
          the day. */}
      <article className="data-card">
        <header>
          <div>
            <h3>The visit</h3>
            <span>What was recorded, and when</span>
          </div>
          <span className={visit.status === "SUBMITTED" ? "pill" : "pill gold"}>
            {humanizeEnum(visit.status)}
          </span>
        </header>
        <div className="card-body">
          <div className="fact-grid">
            <div className="fact">
              <span className="label">Type</span>
              <span className="value">{humanizeEnum(visit.visitType)}</span>
            </div>
            <div className="fact">
              <span className="label">Visited</span>
              <span className="value">{formatDate(visit.startedAt)}</span>
            </div>
            <div className="fact">
              <span className="label">Submitted</span>
              <span className="value">{formatDate(visit.submittedAt)}</span>
              {daysBetween(visit.startedAt, visit.submittedAt) >= 1 ? (
                // Field visits are recorded offline and sync days later. Two
                // dates side by side invite "why are these different?", so the
                // page answers it rather than leaving the reader to wonder.
                <span className="fact-note">
                  {daysBetween(visit.startedAt, visit.submittedAt)} days after the visit
                </span>
              ) : null}
            </div>
            <div className="fact">
              <span className="label">Agent</span>
              <span className="value">{agent?.name ?? "Not recorded"}</span>
            </div>
            <div className="fact">
              <span className="label">Revision</span>
              <span className="value">{visit.revision ?? 1}</span>
            </div>
          </div>

          {submittedBy && agent && submittedBy.name !== agent.name ? (
            <p className="card-note">
              Entered by {submittedBy.name}, who is not the agent named on it.
            </p>
          ) : null}

          {visit.notes ? (
            <div className="visit-notes">
              <span className="label">What the agent found</span>
              <p>{visit.notes}</p>
            </div>
          ) : null}

          {visit.authenticityFlags && visit.authenticityFlags.length > 0 ? (
            <div className="notice warning">
              {/* Raised by the server at submission. These used to print the
                  raw enum -- "Flagged at submission: LOW_ACCURACY_FIX" -- which
                  is a database value, not a sentence, and told a field officer
                  nothing about whether to trust the record. */}
              <strong>Worth knowing before you quote this visit</strong>
              <ul>
                {visit.authenticityFlags.map((flag) => (
                  <li key={flag}>{describeFlag(flag, visit.location?.accuracyM ?? null)}</li>
                ))}
              </ul>
            </div>
          ) : null}
        </div>
      </article>

      {visit.location ? (
        <article className="data-card" id="visit-location">
          <header>
            <div>
              <h3>Where it happened</h3>
              <span>Distance computed by the server, never asserted by the phone</span>
            </div>
            <span className={visit.location.withinGeofence ? "pill" : "pill gold"}>
              {visit.location.withinGeofence ? "At the group" : "Not confirmed"}
            </span>
          </header>
          <div className="card-body">
            {/* The headline used to be `humanizeEnum(outcome)` -- "Outside
                Geofence" -- which is the name of a code path, not a finding.
                The distance is the finding. */}
            <p className="metric-value small">{locationHeadline(visit.location)}</p>
            <p className="card-note">{locationExplanation(visit.location)}</p>

            {visit.location.note ? (
              <p className="notice">
                {/* An explanation, not evidence. The server's own distance
                    calculation is the signal; this says why it may look wrong. */}
                <strong>The agent&apos;s explanation</strong>
                {/* A span, not a bare text node: `.notice` sets font-weight 800
                    for one-line warnings, and the rule that returns the body to
                    normal weight can only match an element. */}
                <span>{visit.location.note}</span>
              </p>
            ) : null}

            <div className="fact-grid">
              <div className="fact">
                <span className="label">Reading</span>
                <span className="value">
                  {visit.location.latitude !== null && visit.location.longitude !== null
                    ? `${visit.location.latitude}, ${visit.location.longitude}`
                    : "None"}
                </span>
              </div>
              <div className="fact">
                <span className="label">Accuracy</span>
                <span className="value">
                  {visit.location.accuracyM ? `±${Math.round(visit.location.accuracyM)} m` : "—"}
                </span>
              </div>
              <div className="fact">
                <span className="label">Distance</span>
                <span className="value">
                  {visit.location.distanceFromGroupM === null
                    ? "—"
                    : formatDistance(visit.location.distanceFromGroupM)}
                </span>
              </div>
            </div>
          </div>
        </article>
      ) : null}

      <article className="data-card" id="visit-actions">
        <header>
          <div>
            <h3>Action plan</h3>
            <span>Everything outstanding for this group, not only this visit</span>
          </div>
          <div className="card-header-actions">
            {overdueItems.length > 0 ? (
              <span className="pill red">{overdueItems.length} overdue</span>
            ) : (
              <span className="pill">{openItems.length} open</span>
            )}
            <button
              className="button subtle"
              onClick={() => {
                setAddingAction((open) => !open);
                setError(null);
              }}
              type="button"
            >
              {addingAction ? "Cancel" : "Agree an action"}
            </button>
          </div>
        </header>

        {addingAction ? (
          <form className="action-form" onSubmit={addAction}>
            <p className="card-note">
              Recorded against this visit, and shown to the agent at the start of the
              next one.
            </p>
            <div className="action-form-grid">
              <label className="field action-form-wide">
                <span>What was agreed</span>
                <input
                  autoFocus
                  maxLength={300}
                  onChange={(event) =>
                    setNewAction((draft) => ({ ...draft, title: event.target.value }))
                  }
                  placeholder="Write up the ledger to the last meeting"
                  required
                  value={newAction.title}
                />
              </label>
              <label className="field">
                <span>Who is responsible</span>
                <input
                  list="action-owner-suggestions"
                  maxLength={120}
                  onChange={(event) =>
                    setNewAction((draft) => ({ ...draft, owner: event.target.value }))
                  }
                  placeholder="Secretary"
                  value={newAction.owner}
                />
                {/* The offices a VSLA actually has, so an owner is a role that
                    survives the person leaving rather than a free-text name. */}
                <datalist id="action-owner-suggestions">
                  {ACTION_OWNERS.map((owner) => (
                    <option key={owner} value={owner} />
                  ))}
                </datalist>
              </label>
              <label className="field">
                <span>Due by</span>
                <input
                  onChange={(event) =>
                    setNewAction((draft) => ({ ...draft, dueDate: event.target.value }))
                  }
                  type="date"
                  value={newAction.dueDate}
                />
              </label>
              <label className="field action-form-wide">
                <span>Detail (optional)</span>
                <textarea
                  maxLength={2000}
                  onChange={(event) =>
                    setNewAction((draft) => ({ ...draft, detail: event.target.value }))
                  }
                  placeholder="Three meetings are unrecorded. The secretary has the notes."
                  rows={2}
                  value={newAction.detail}
                />
              </label>
            </div>
            <div className="action-form-buttons">
              <button className="button" disabled={savingAction} type="submit">
                {savingAction ? "Saving\u2026" : "Add to the plan"}
              </button>
              <button
                className="button subtle"
                onClick={() => {
                  setAddingAction(false);
                  setNewAction(EMPTY_ACTION);
                }}
                type="button"
              >
                Discard
              </button>
            </div>
          </form>
        ) : null}

        {actionItems.length === 0 ? (
          <div className="card-body">
            <p className="card-note">
              Nothing has been agreed for this group yet. Anything recorded here is
              shown to the agent at the start of their next visit.
            </p>
          </div>
        ) : (
          <>
            <div className="card-body">
              <p className="card-note">
                An item agreed here is usually closed at the next visit, so this list
                is the group&rsquo;s, not this visit&rsquo;s.
                &ldquo;Overdue&rdquo; is worked out from the due date on every read, so
                it cannot drift out of step with the calendar.
              </p>
            </div>
            <table className="data-table">
              <thead>
                <tr>
                  <th>Action</th>
                  <th>Owner</th>
                  <th>Due</th>
                  <th>State</th>
                  <th className="column-actions">Manage</th>
                </tr>
              </thead>
              <tbody>
                {actionItems.map((item) => (
                  <tr key={item.id}>
                    <td>
                      {item.title}
                      {item.detail ? <div className="eyebrow">{item.detail}</div> : null}
                      {!item.state.open && item.closingNote ? (
                        <div className="eyebrow">Closed: {item.closingNote}</div>
                      ) : null}
                    </td>
                    <td>{item.owner ?? "\u2014"}</td>
                    <td>
                      {formatDate(item.state.dueDate)}
                      {item.state.daysOverdue > 0 ? (
                        <div className="eyebrow">{item.state.daysOverdue} days late</div>
                      ) : null}
                    </td>
                    <td>
                      <span className={actionPill(item.state.state)}>{item.state.label}</span>
                    </td>
                    <td className="column-actions">
                      {/* Closing and reopening rather than editing in place: an
                          action plan is a record of what was agreed, and a row
                          quietly rewritten months later is not one. */}
                      {item.state.open ? (
                        <div className="row-actions">
                          <button
                            className="button subtle"
                            disabled={busyItem === item.id}
                            onClick={() => setItemStatus(item.id, "DONE")}
                            type="button"
                          >
                            Mark done
                          </button>
                          <button
                            className="button quiet"
                            disabled={busyItem === item.id}
                            onClick={() => setItemStatus(item.id, "CANCELLED")}
                            type="button"
                          >
                            Drop
                          </button>
                        </div>
                      ) : (
                        <button
                          className="button quiet"
                          disabled={busyItem === item.id}
                          onClick={() => setItemStatus(item.id, "OPEN")}
                          type="button"
                        >
                          Reopen
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </>
        )}
      </article>

      <div id="visit-assessment">
        {assessment ? <AssessmentRecord assessment={assessment} /> : null}
      </div>

      {mentorship && (mentorship.sessions.length > 0 || mentorship.ratings.length > 0) ? (
        <article className="data-card">
          <header>
            <div>
              <h3>Mentorship</h3>
              <span>Coaching delivered, and what the group made of it</span>
            </div>
            <span className="pill blue">
              {mentorship.sessions.length}
              {mentorship.sessions.length === 1 ? " topic" : " topics"}
            </span>
          </header>
          <div className="card-body">
          <p className="metric-value">
            {mentorship.averageGroupRating === null
              ? "Not rated"
              : `${mentorship.averageGroupRating} / 5`}
          </p>
          <p className="card-note">
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
          </div>
        </article>
      ) : null}

      {/* The amendment trail. A SUBMITTED visit is immutable and edits append a
          revision, which is the whole point of that design — but the page threw
          the revisions away, so a corrected visit was indistinguishable from an
          untouched one. */}
      {revisions.length > 0 ? (
        <article className="data-card">
          <header>
            <div>
              <h3>Amendments</h3>
              <span>The original submission is kept; each change is appended</span>
            </div>
            <span className="pill gold">{revisions.length}</span>
          </header>
          <div className="card-body">
            <p className="card-note">
              This visit has been amended {revisions.length}
              {revisions.length === 1 ? " time" : " times"}. A submitted visit is never
              overwritten, so the record still shows what it said before.
            </p>
          </div>
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
                  <td>{formatDateTime(revision.createdAt)}</td>
                  <td>{revision.reason ?? <span className="eyebrow">No reason given</span>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </article>
      ) : null}

      <article className="data-card" id="visit-evidence">
        <header>
          <div>
            <h3>Evidence</h3>
            <span>Grouped by the claim it supports, never as a loose gallery</span>
          </div>
          <span className="pill blue">{attachments.length}</span>
        </header>
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
                        {attachment.capturedAt ? ` · ${formatDate(attachment.capturedAt)}` : ""}
                      </figcaption>
                    </figure>
                  ))}
                </div>
              </div>
            ))}
          </>
        )}
      </article>

      <p className="eyebrow">
        A submitted visit is immutable. Corrections are recorded as amendments, which keep the
        original report readable alongside them.
      </p>
    </section>
  );
}

/**
 * A flag, said as a sentence.
 *
 * These are raised by the server at submission and were printed raw --
 * "Flagged at submission: LOW_ACCURACY_FIX". That is a database value. A field
 * officer deciding whether to quote a visit in a funder report needs to know
 * what it means for the record, which is what these say.
 */
function describeFlag(flag: string, accuracyM: number | null): string {
  const fix = accuracyM ? ` (±${Math.round(accuracyM)} m)` : "";
  switch (flag) {
    case "LOW_ACCURACY_FIX":
      return `The phone's location reading was imprecise${fix}, so the distance below is approximate.`;
    case "NO_DEVICE_FIX":
      return "The phone never got a location fix, so where this visit happened cannot be checked.";
    case "OUTSIDE_GEOFENCE":
      return "The reading was outside the group's registered area. The agent's explanation, if any, is below.";
    case "NO_GROUP_LOCATION":
      return "This group has no registered location on file, so there was nothing to check the reading against.";
    case "LATE_SUBMISSION":
      return "The visit reached the office well after it was recorded, which is ordinary in the field but worth noting.";
    default:
      // An unrecognised flag still has to read as a sentence rather than
      // disappear -- a flag nobody can see is worse than an ugly one.
      return humanizeEnum(flag);
  }
}

/** The finding, in the reader's terms: how far away, not which code path ran. */
function locationHeadline(location: {
  withinGeofence: boolean;
  distanceFromGroupM: number | null;
  outcome: string;
}): string {
  if (location.withinGeofence) return "At the group";
  if (location.distanceFromGroupM !== null) {
    return `${formatDistance(location.distanceFromGroupM)} away`;
  }
  if (location.outcome === "NO_DEVICE_FIX") return "No reading";
  if (location.outcome === "NO_GROUP_LOCATION") return "Nothing to compare";
  return "Not confirmed";
}

function locationExplanation(location: {
  withinGeofence: boolean;
  distanceFromGroupM: number | null;
  outcome: string;
}): string {
  if (location.outcome === "NO_GROUP_LOCATION") {
    return "This group has no registered meeting point, so the reading could not be checked against anything. Set one on the group's page and later visits will be checked automatically.";
  }
  if (location.outcome === "NO_DEVICE_FIX") {
    return "The phone recorded no position. A visit can still be filed without one — the field is deliberately not required.";
  }
  if (location.withinGeofence) {
    return "The reading falls inside the area registered for this group.";
  }
  return "The reading falls outside the area registered for this group. That is recorded, not blocked: a group that met somewhere else must still be able to file its visit.";
}

/** Whole days between two timestamps, or 0 when either is missing. */
function daysBetween(from: string | null, to: string | null): number {
  if (!from || !to) return 0;
  const ms = new Date(to).getTime() - new Date(from).getTime();
  if (!Number.isFinite(ms) || ms <= 0) return 0;
  return Math.floor(ms / 86_400_000);
}

/** The assessment band, on the house pill variants. */
function bandPill(label: string | null): string {
  if (label === "Excellent" || label === "Good") return "";
  if (label === "Fair") return "gold";
  if (label === "Weak") return "red";
  return "blue";
}

/**
 * A score as a bar.
 *
 * A percentage in text is read; a bar is seen. The colour follows the band so
 * a weak section is recognisable before the number is read at all.
 */
function ScoreBar({ percentage }: { percentage: number }) {
  const tone = percentage >= 80 ? "good" : percentage >= 60 ? "fair" : percentage >= 40 ? "warn" : "poor";
  return (
    <span
      aria-hidden="true"
      className={`score-bar score-bar-${tone}`}
    >
      <span className="score-bar-fill" style={{ width: `${Math.max(0, Math.min(100, percentage))}%` }} />
    </span>
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
