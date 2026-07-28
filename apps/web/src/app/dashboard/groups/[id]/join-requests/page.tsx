"use client";

import React from "react";
import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, UserPlus } from "@/lib/theme-icons";
import { ApiClientError, apiFetch } from "../../../../../lib/api";
import { DataTable } from "../../../../../components/dashboard/data-table";
import type { User } from "../../../../../components/dashboard/types";

interface GroupSummary {
  id: string;
  name: string;
  code: string;
}

interface JoinRequestRow {
  id: string;
  requestedName: string;
  phone: string;
  status: string;
  memberId: string | null;
  reviewNotes: string | null;
  createdAt: string;
  decidedAt: string | null;
  /** Set when this phone already belongs to someone on the roster. */
  willLinkToMemberId: string | null;
  willLinkToMemberName: string | null;
}

const statusPill: Record<string, string> = {
  PENDING: "gold",
  APPROVED: "green",
  REJECTED: "grey"
};

function whenAsked(iso: string) {
  const asked = new Date(iso);
  if (Number.isNaN(asked.getTime())) return "";
  return asked.toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric"
  });
}

/**
 * People asking to be added to a group's roster.
 *
 * A group's code is printed on its records, so knowing it proves nothing —
 * approving here is what actually opens the group's books to someone. The
 * page says so before an officer commits.
 */
export default function GroupJoinRequestsPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [group, setGroup] = useState<GroupSummary | null>(null);
  const [requests, setRequests] = useState<JoinRequestRow[]>([]);
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);

  async function loadPage() {
    const [groupResponse, requestResponse, meResponse] = await Promise.all([
      apiFetch<GroupSummary>(`/groups/${id}`),
      // Everything, not just pending — an officer needs to see what was
      // already answered and why.
      apiFetch<JoinRequestRow[]>(`/groups/${id}/join-requests?status=ALL`),
      apiFetch<User>("/auth/me")
    ]);
    setGroup(groupResponse);
    setRequests(requestResponse);
    setUser(meResponse);
  }

  useEffect(() => {
    let mounted = true;
    loadPage()
      .catch((loadError) => {
        if (!mounted) return;
        // The queue holds the names and numbers of people who are not members
        // yet, so only those who can answer a request may read it. Say that
        // plainly instead of showing a raw permission error.
        if (loadError instanceof ApiClientError && loadError.status === 403) {
          setError("Only an officer of this group can see who has asked to join.");
          return;
        }
        setError(loadError instanceof Error ? loadError.message : "Join requests failed to load");
      })
      .finally(() => {
        if (mounted) setLoading(false);
      });
    return () => {
      mounted = false;
    };
  }, [id]);

  const canDecide = user?.permissions?.includes("members:write") ?? false;
  const pending = requests.filter((request) => request.status === "PENDING");

  async function decide(request: JoinRequestRow, approve: boolean) {
    if (approve) {
      // Nothing verifies the number someone types at sign-up, so a match with
      // the roster is a claim rather than proof. Spell out whose records are
      // about to be handed over.
      const message = request.willLinkToMemberName
        ? `${request.requestedName} gave a phone number already on the roster for ` +
          `${request.willLinkToMemberName}.\n\n` +
          `Accepting attaches this login to ${request.willLinkToMemberName}'s existing ` +
          "savings and loan records. Only continue if you know this is the same person."
        : `Add ${request.requestedName} to ${group?.name ?? "this group"}?\n\n` +
          "They will be able to see the group's savings, loans and meeting " +
          "records. Only approve someone the group knows.";
      if (!window.confirm(message)) return;
    }

    let notes: string | null = null;
    if (!approve) {
      // Optional, but a refused person is told why, so it is worth offering.
      notes = window.prompt(`Why is ${request.requestedName} being declined? (optional)`, "");
      if (notes === null) return;
    }

    setBusyId(request.id);
    setMessage(null);
    try {
      const result = await apiFetch<{ matchedExistingMember?: boolean }>(
        `/groups/${id}/join-requests/${request.id}/decision`,
        {
          method: "POST",
          body: JSON.stringify({
            decision: approve ? "APPROVE" : "REJECT",
            notes: notes && notes.trim() ? notes.trim() : undefined,
            // Echoes back the member the official was actually shown, so a
            // stale page cannot approve a handover they never saw.
            confirmMemberId: approve ? request.willLinkToMemberId ?? undefined : undefined
          })
        }
      );
      await loadPage();
      setMessage({
        ok: true,
        text: approve
          ? result.matchedExistingMember
            ? `${request.requestedName} was matched to the savings already recorded for them.`
            : `${request.requestedName} was added as a new member.`
          : `${request.requestedName}'s request was declined.`
      });
    } catch (decideError) {
      const text = decideError instanceof Error ? decideError.message : "Decision failed to save";
      setMessage({ ok: false, text });
      // Most likely someone else answered it first — show the current state.
      await loadPage().catch(() => undefined);
    } finally {
      setBusyId(null);
    }
  }

  if (loading) return <div className="loading-panel">Loading...</div>;
  if (error) return <div className="error">{error}</div>;

  return (
    <>
      <section className="page-heading">
        <div>
          <Link className="inline-back" href={`/dashboard/groups/${id}`}>
            <ArrowLeft size={17} />
            {group?.name ?? "Group"}
          </Link>
          <p className="eyebrow">Requests To Join</p>
          <h2>{group?.code ?? "Join requests"}</h2>
        </div>
        <span className="pill">{pending.length} waiting</span>
      </section>

      {message ? <div className={message.ok ? "notice success" : "notice warning"}>{message.text}</div> : null}

      {requests.length === 0 ? (
        <section className="data-card">
          <header>
            <h3>
              <UserPlus size={16} /> Nobody is waiting
            </h3>
          </header>
          <p className="muted">
            When someone enters this group&apos;s code in the app, their request appears here for an
            officer to accept or decline.
          </p>
        </section>
      ) : (
        <DataTable
          title="Requests to join"
          exportName={`join-requests-${group?.code ?? id}`}
          rows={requests}
          getRowKey={(request) => request.id}
          defaultSort={{ key: "asked", direction: "desc" }}
          filters={[
            {
              key: "status",
              label: "Status",
              getValue: (request) => request.status
            }
          ]}
          columns={[
            { key: "name", header: "Name", value: (request) => request.requestedName },
            { key: "phone", header: "Phone", value: (request) => request.phone },
            {
              key: "asked",
              header: "Asked",
              value: (request) => request.createdAt,
              cell: (request) => <>{whenAsked(request.createdAt)}</>
            },
            {
              key: "status",
              header: "Status",
              value: (request) => request.status,
              cell: (request) => (
                <span className={`pill ${statusPill[request.status] ?? "grey"}`}>
                  {request.status.charAt(0) + request.status.slice(1).toLowerCase()}
                </span>
              )
            },
            {
              key: "existing",
              header: "Matches",
              value: (request) => request.willLinkToMemberName ?? "",
              cell: (request) =>
                request.willLinkToMemberName ? (
                  <span className="pill gold">{request.willLinkToMemberName}&apos;s records</span>
                ) : (
                  <>New member</>
                )
            },
            {
              key: "notes",
              header: "Reason given",
              value: (request) => request.reviewNotes ?? "",
              cell: (request) => <>{request.reviewNotes ?? "-"}</>
            },
            {
              key: "actions",
              header: "",
              exportable: false,
              searchable: false,
              sortable: false,
              value: () => "",
              cell: (request) =>
                canDecide && request.status === "PENDING" ? (
                  <span className="table-actions">
                    <button
                      className="button"
                      disabled={busyId === request.id}
                      onClick={() => decide(request, true)}
                      type="button"
                    >
                      {busyId === request.id ? "Working..." : "Accept"}
                    </button>
                    <button
                      className="button secondary"
                      disabled={busyId === request.id}
                      onClick={() => decide(request, false)}
                      type="button"
                    >
                      Decline
                    </button>
                  </span>
                ) : (
                  <></>
                )
            }
          ]}
        />
      )}
    </>
  );
}
