"use client";

import React from "react";
import type { FormEvent } from "react";
import { useEffect, useMemo, useState } from "react";
import { CheckCircle2, Megaphone, Send, UsersRound } from "@/lib/theme-icons";
import { apiFetch } from "../../../lib/api";
import { StatCard } from "../../../components/dashboard/stat-card";

interface GroupOption {
  id: string;
  name: string;
  code: string;
  _count?: { members?: number };
}

interface MemberOption {
  id: string;
  fullName: string;
  phone: string;
  role: string;
  status: string;
}

interface SmsBroadcastRecipient {
  id: string;
  memberId?: string | null;
  memberName: string;
  phone: string;
  provider: string;
  status: string;
  providerStatus?: string | null;
  providerMessage?: string | null;
  sentAt?: string | null;
}

interface SmsBroadcast {
  id: string;
  targetType: "GROUP" | "MEMBER";
  targetGroupId?: string | null;
  targetMemberId?: string | null;
  provider: string;
  message: string;
  status: string;
  recipientCount: number;
  queuedCount: number;
  sentCount: number;
  failedCount: number;
  createdAt: string;
  recipients: SmsBroadcastRecipient[];
}

const defaultMessage = "Reminder: please attend your group meeting and check your Intelli Cash updates.";

function formatDate(value: string) {
  return new Intl.DateTimeFormat("en-KE", {
    dateStyle: "medium",
    timeStyle: "short"
  }).format(new Date(value));
}

function providerLabel(provider: string) {
  return provider === "BONGA_SMS" ? "Bonga" : provider.replace(/_/g, " ");
}

/**
 * Why a recipient did not get a text.
 *
 * This used to return null for anything but FAILED, which hid the single most
 * important message the provider layer produces: a QUEUED row carrying
 * "SMS network delivery is disabled." looked exactly like one on its way. The
 * distinction the operator needs is delivered / not delivered, and QUEUED is
 * firmly on the second side of it.
 */
function recipientProblemDetail(recipient: SmsBroadcastRecipient) {
  if (recipient.status === "SENT") return null;

  const status = recipient.providerStatus
    ? `${providerLabel(recipient.provider)} status ${recipient.providerStatus}`
    : recipient.status === "FAILED"
      ? `${providerLabel(recipient.provider)} failure`
      : `${providerLabel(recipient.provider)} not sent`;
  const message = recipient.providerMessage ? ` - ${recipient.providerMessage}` : "";
  return `${recipient.memberName}: ${status}${message}`;
}

function broadcastProblemDetails(broadcast: SmsBroadcast) {
  return broadcast.recipients
    .map((recipient) => recipientProblemDetail(recipient))
    .filter((detail): detail is string => Boolean(detail));
}

function broadcastNoticeText(broadcast: SmsBroadcast) {
  // "queued for 12 recipients" reads like success. Say how many were actually
  // delivered, which is the only number worth reporting.
  const plural = broadcast.recipientCount === 1 ? "" : "s";
  const base =
    broadcast.sentCount === broadcast.recipientCount
      ? `${providerLabel(broadcast.provider)} delivered to ${broadcast.recipientCount} recipient${plural}.`
      : `${providerLabel(broadcast.provider)} delivered to ${broadcast.sentCount} of ${broadcast.recipientCount} recipient${plural}.`;
  const details = broadcastProblemDetails(broadcast);
  return details.length > 0 ? `${base} ${details.join(" ")}` : base;
}

export default function SmsBroadcastPage() {
  const [groups, setGroups] = useState<GroupOption[]>([]);
  const [members, setMembers] = useState<MemberOption[]>([]);
  const [broadcasts, setBroadcasts] = useState<SmsBroadcast[]>([]);
  const [targetType, setTargetType] = useState<"GROUP" | "MEMBER">("GROUP");
  const [groupId, setGroupId] = useState("");
  const [memberId, setMemberId] = useState("");
  const [message, setMessage] = useState(defaultMessage);
  const [loading, setLoading] = useState(true);
  const [loadingMembers, setLoadingMembers] = useState(false);
  const [sending, setSending] = useState(false);
  const [notice, setNotice] = useState<{ ok: boolean; text: string } | null>(null);
  const [error, setError] = useState<string | null>(null);

  const selectedGroup = groups.find((group) => group.id === groupId);
  const activeMembers = useMemo(() => members.filter((member) => member.status === "ACTIVE"), [members]);
  const selectedMember = activeMembers.find((member) => member.id === memberId);
  const recipientCount = targetType === "GROUP" ? selectedGroup?._count?.members ?? activeMembers.length : selectedMember ? 1 : 0;

  async function loadBroadcasts() {
    const response = await apiFetch<SmsBroadcast[]>("/sms/broadcasts");
    setBroadcasts(response);
  }

  useEffect(() => {
    let mounted = true;

    async function loadPage() {
      try {
        const [groupResponse, broadcastResponse] = await Promise.all([
          apiFetch<GroupOption[]>("/groups"),
          apiFetch<SmsBroadcast[]>("/sms/broadcasts")
        ]);
        if (!mounted) return;
        setGroups(groupResponse);
        setBroadcasts(broadcastResponse);
        setGroupId((current) => current || groupResponse[0]?.id || "");
      } catch (loadError) {
        if (mounted) setError(loadError instanceof Error ? loadError.message : "SMS broadcasts failed to load");
      } finally {
        if (mounted) setLoading(false);
      }
    }

    void loadPage();
    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    if (!groupId) {
      setMembers([]);
      setMemberId("");
      return;
    }

    let mounted = true;
    setLoadingMembers(true);
    apiFetch<MemberOption[]>(`/groups/${groupId}/members`)
      .then((response) => {
        if (!mounted) return;
        setMembers(response);
        const firstActive = response.find((member) => member.status === "ACTIVE");
        setMemberId((current) =>
          current && response.some((member) => member.id === current && member.status === "ACTIVE")
            ? current
            : firstActive?.id ?? ""
        );
      })
      .catch((memberError) => {
        if (mounted) setNotice({ ok: false, text: memberError instanceof Error ? memberError.message : "Members failed to load" });
      })
      .finally(() => {
        if (mounted) setLoadingMembers(false);
      });

    return () => {
      mounted = false;
    };
  }, [groupId]);

  async function sendBroadcast(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSending(true);
    setNotice(null);

    try {
      const payload =
        targetType === "GROUP"
          ? { targetType, groupId, message }
          : { targetType, memberId, message };
      const broadcast = await apiFetch<SmsBroadcast>("/sms/broadcasts", {
        method: "POST",
        body: JSON.stringify(payload)
      });
      setNotice({
        ok: broadcast.sentCount === broadcast.recipientCount,
        text: broadcastNoticeText(broadcast)
      });
      await loadBroadcasts();
    } catch (sendError) {
      setNotice({ ok: false, text: sendError instanceof Error ? sendError.message : "SMS broadcast failed" });
    } finally {
      setSending(false);
    }
  }

  if (loading) return <div className="loading-panel">Loading SMS broadcasts...</div>;
  if (error) return <div className="error">{error}</div>;

  const sentTotal = broadcasts.reduce((total, broadcast) => total + broadcast.sentCount, 0);
  const queuedTotal = broadcasts.reduce((total, broadcast) => total + broadcast.queuedCount, 0);
  const failedTotal = broadcasts.reduce((total, broadcast) => total + broadcast.failedCount, 0);

  return (
    <section className="page-stack">
      <section className="page-heading">
        <div>
          <p className="eyebrow">Messaging</p>
          <h2>SMS Broadcasts</h2>
          <p>Send SMS notifications to a full group or one selected member using the configured SMS provider.</p>
        </div>
      </section>

      <section className="stat-grid">
        <StatCard icon={<Megaphone size={20} />} label="Broadcasts" note="Recent admin batches" value={broadcasts.length.toString()} />
        <StatCard icon={<UsersRound size={20} />} label="Recipients" note="Selected target" value={recipientCount.toString()} />
        <StatCard icon={<CheckCircle2 size={20} />} label="Sent" note="Delivered by provider" value={sentTotal.toString()} />
        <StatCard icon={<Send size={20} />} label="Queued" note={`${failedTotal} failed`} value={queuedTotal.toString()} />
      </section>

      <section className="two-column">
        <section className="data-card">
          <header>
            <div>
              <h3>Send SMS</h3>
              <span>{targetType === "GROUP" ? "Broadcast to active group members." : "Send to one active member."}</span>
            </div>
            <span className="pill blue">SMS provider</span>
          </header>

          <form className="credential-form" onSubmit={sendBroadcast}>
            <div className="credential-grid">
              <label className="credential-field">
                <span>Target</span>
                <select onChange={(event) => setTargetType(event.target.value as "GROUP" | "MEMBER")} value={targetType}>
                  <option value="GROUP">Group</option>
                  <option value="MEMBER">Individual member</option>
                </select>
              </label>

              <label className="credential-field">
                <span>Group</span>
                <select onChange={(event) => setGroupId(event.target.value)} required value={groupId}>
                  {groups.map((group) => (
                    <option key={group.id} value={group.id}>
                      {group.name} ({group.code})
                    </option>
                  ))}
                </select>
              </label>

              {targetType === "MEMBER" ? (
                <label className="credential-field">
                  <span>Member</span>
                  <select
                    disabled={loadingMembers || activeMembers.length === 0}
                    onChange={(event) => setMemberId(event.target.value)}
                    required
                    value={memberId}
                  >
                    {activeMembers.map((member) => (
                      <option key={member.id} value={member.id}>
                        {member.fullName} - {member.phone}
                      </option>
                    ))}
                  </select>
                </label>
              ) : null}

              <label className="credential-field span-2">
                <span>SMS text</span>
                <textarea
                  maxLength={480}
                  onChange={(event) => setMessage(event.target.value)}
                  required
                  rows={5}
                  value={message}
                />
              </label>
            </div>

            {notice ? <div className={notice.ok ? "notice success" : "notice warning"}>{notice.text}</div> : null}

            <div className="credential-actions">
              <button className="button" disabled={sending || !groupId || !message.trim() || (targetType === "MEMBER" && !memberId)} type="submit">
                <Send size={16} />
                {sending ? "Sending" : "Send SMS"}
              </button>
            </div>
          </form>
        </section>

        <section className="data-card">
          <header>
            <div>
              <h3>Recent Broadcasts</h3>
              <span>Latest admin SMS batches and delivery counts.</span>
            </div>
            <span className="pill">{broadcasts.length} listed</span>
          </header>

          <div className="list">
            {broadcasts.length === 0 ? <div className="empty-state">No SMS broadcasts yet.</div> : null}
            {broadcasts.map((broadcast) => (
              <div className="list-row" key={broadcast.id}>
                <div>
                  <strong>{broadcast.targetType === "GROUP" ? "Group broadcast" : "Member SMS"}</strong>
                  <span>
                    {broadcast.provider} - {broadcast.recipientCount} recipients - {formatDate(broadcast.createdAt)}
                  </span>
                  <em>{broadcast.message}</em>
                  {broadcastProblemDetails(broadcast).map((detail) => (
                    <span className="warning-text" key={detail}>
                      {detail}
                    </span>
                  ))}
                </div>
                <span className={`pill ${broadcast.status === "SENT" ? "blue" : broadcast.status === "FAILED" ? "gold" : ""}`}>
                  {broadcast.status}: {broadcast.sentCount} sent / {broadcast.queuedCount} queued / {broadcast.failedCount} failed
                </span>
              </div>
            ))}
          </div>
        </section>
      </section>
    </section>
  );
}
