"use client";

import React from "react";
import type { FormEvent } from "react";
import { use, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { Activity, ArrowLeft, BookOpenText, CheckCircle2, DoorOpen, KeyRound, LockKeyhole, UserCheck } from "@/lib/theme-icons";
import { meetingSteps } from "@intellicash/shared";
import { ApiClientError, apiFetch, humanizeEnum } from "../../../../../lib/api";
import {
  clearOfflineMeetingSchedule,
  getMeetingDeviceId,
  loadGroupMeetingWorkspace,
  loadOfflineMeetingSchedules,
  queueMeetingDraft,
  queueOfflineMeetingSchedule,
  storeGroupMeetingWorkspace,
  type OfflineMeetingScheduleDraft
} from "../../../../../lib/meeting-offline-store";
import { DataTable } from "../../../../../components/dashboard/data-table";
import type { MeetingRow, Member, User } from "../../../../../components/dashboard/types";

interface GroupSummary {
  id: string;
  name: string;
  code: string;
}

interface MeetingForm {
  title: string;
  scheduledAt: string;
}

const defaultMeetingForm: MeetingForm = {
  title: "",
  scheduledAt: ""
};

type MeetingWorkspaceCache = {
  group: GroupSummary;
  meetings: MeetingRow[];
  members: Member[];
  user: User;
  cachedAt: string;
};

const defaultUnlockSubmissions = [
  { memberId: "", pin: "" },
  { memberId: "", pin: "" },
  { memberId: "", pin: "" }
];

export default function GroupMeetingsPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [group, setGroup] = useState<GroupSummary | null>(null);
  const [meetings, setMeetings] = useState<MeetingRow[]>([]);
  const [members, setMembers] = useState<Member[]>([]);
  const [user, setUser] = useState<User | null>(null);
  const [form, setForm] = useState(defaultMeetingForm);
  const [attendanceForm, setAttendanceForm] = useState({ meetingId: "", memberId: "", status: "PRESENT" });
  const [unlockForm, setUnlockForm] = useState({
    meetingId: "",
    submissions: defaultUnlockSubmissions
  });
  const [memberKeyForm, setMemberKeyForm] = useState({ meetingId: "", pin: "" });
  const [pendingSchedules, setPendingSchedules] = useState<OfflineMeetingScheduleDraft[]>([]);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function isOffline() {
    return typeof navigator !== "undefined" && navigator.onLine === false;
  }

  function isNetworkFailure(error: unknown) {
    return (
      isOffline() ||
      (error instanceof ApiClientError && error.status === 0) ||
      (error instanceof TypeError && error.message.toLowerCase().includes("fetch")) ||
      (error instanceof Error && error.message.includes("Network request failed"))
    );
  }

  function offlineScheduleAsMeeting(schedule: OfflineMeetingScheduleDraft): MeetingRow {
    return {
      id: schedule.id,
      title: schedule.title,
      status: "QUEUED_OFFLINE",
      scheduledAt: schedule.scheduledAt,
      openedAt: null,
      closedAt: null,
      unlockStatus: "PENDING",
      gpsCompliant: schedule.gpsCompliant,
      transactionTotal: 0,
      minutes: null,
      steps: [],
      attendance: [],
      keySubmissions: []
    };
  }

  function applyWorkspace(workspace: MeetingWorkspaceCache, schedules: OfflineMeetingScheduleDraft[]) {
    setGroup(workspace.group);
    setMeetings([...schedules.map(offlineScheduleAsMeeting), ...workspace.meetings]);
    setMembers(workspace.members);
    setUser(workspace.user);
    setPendingSchedules(schedules);
  }

  async function loadPage() {
    const schedules = await loadOfflineMeetingSchedules(id);

    try {
      const [groupResponse, meetingResponse, memberResponse, meResponse] = await Promise.all([
        apiFetch<GroupSummary>(`/groups/${id}`),
        apiFetch<MeetingRow[]>(`/groups/${id}/meetings`),
        apiFetch<Member[]>(`/groups/${id}/members`),
        apiFetch<User>("/auth/me")
      ]);
      const workspace = {
        group: groupResponse,
        meetings: meetingResponse,
        members: memberResponse,
        user: meResponse,
        cachedAt: new Date().toISOString()
      };
      await storeGroupMeetingWorkspace(id, workspace);
      applyWorkspace(workspace, schedules);
      if (schedules.length > 0) {
        setMessage({
          ok: false,
          text: `${schedules.length} offline scheduled ${schedules.length === 1 ? "meeting is" : "meetings are"} waiting to sync.`
        });
      }
    } catch (loadError) {
      const cached = await loadGroupMeetingWorkspace<GroupSummary, MeetingRow, Member, User>(id);
      if (!cached) throw loadError;

      applyWorkspace(cached, schedules);
      setMessage({
        ok: false,
        text: `Offline mode: showing cached meetings from ${new Date(cached.cachedAt).toLocaleString("en-KE")}.`
      });
    }
  }

  useEffect(() => {
    let mounted = true;
    loadPage()
      .catch((loadError) => {
        if (mounted) setError(loadError instanceof Error ? loadError.message : "Meetings failed to load");
      })
      .finally(() => {
        if (mounted) setLoading(false);
      });
    return () => {
      mounted = false;
    };
  }, [id]);

  const canWrite = user?.permissions?.includes("meetings:write") ?? false;
  const canSubmitOwnKey = user?.role === "MEMBER";
  const activeMeetings = useMemo(
    () => meetings.filter((meeting) => meeting.status !== "SEALED"),
    [meetings]
  );
  const scheduledMeetings = useMemo(
    () => meetings.filter((meeting) => meeting.status === "SCHEDULED"),
    [meetings]
  );

  function nextStep(meeting: MeetingRow) {
    const completed = new Set(meeting.steps.filter((step) => step.status === "COMPLETED").map((step) => step.step));
    return meetingSteps.find((step) => !completed.has(step));
  }

  async function createMeeting(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSaving(true);
    setMessage(null);

    try {
      const scheduledAt = form.scheduledAt ? new Date(form.scheduledAt).toISOString() : new Date().toISOString();
      if (isOffline()) {
        const queued = await queueOfflineMeetingSchedule({
          groupId: id,
          title: form.title,
          scheduledAt,
          gpsCompliant: false
        });
        const schedules = await loadOfflineMeetingSchedules(id);
        setPendingSchedules(schedules);
        setMeetings((current) => [offlineScheduleAsMeeting(queued), ...current]);
        setForm(defaultMeetingForm);
        setMessage({ ok: true, text: `${queued.title} saved offline. Sync when the connection is available.` });
        return;
      }

      const created = await apiFetch<MeetingRow>(`/groups/${id}/meetings`, {
        method: "POST",
        body: JSON.stringify({ title: form.title, scheduledAt })
      });
      setForm(defaultMeetingForm);
      setAttendanceForm((current) => ({ ...current, meetingId: created.id }));
      setUnlockForm((current) => ({ ...current, meetingId: created.id }));
      await loadPage();
      setMessage({ ok: true, text: `${created.title} scheduled.` });
    } catch (saveError) {
      if (isNetworkFailure(saveError)) {
        const scheduledAt = form.scheduledAt ? new Date(form.scheduledAt).toISOString() : new Date().toISOString();
        const queued = await queueOfflineMeetingSchedule({
          groupId: id,
          title: form.title,
          scheduledAt,
          gpsCompliant: false
        });
        const schedules = await loadOfflineMeetingSchedules(id);
        setPendingSchedules(schedules);
        setMeetings((current) => [offlineScheduleAsMeeting(queued), ...current]);
        setForm(defaultMeetingForm);
        setMessage({ ok: true, text: `${queued.title} saved offline. Sync when the connection is available.` });
      } else {
        setMessage({ ok: false, text: saveError instanceof Error ? saveError.message : "Meeting failed to save" });
      }
    } finally {
      setSaving(false);
    }
  }

  async function syncOfflineSchedules() {
    setSaving(true);
    setMessage(null);
    try {
      const schedules = await loadOfflineMeetingSchedules(id);
      let synced = 0;
      for (const schedule of schedules) {
        await apiFetch<MeetingRow>(`/groups/${id}/meetings`, {
          method: "POST",
          body: JSON.stringify({
            title: schedule.title,
            scheduledAt: schedule.scheduledAt,
            gpsCompliant: schedule.gpsCompliant
          })
        });
        await clearOfflineMeetingSchedule(id, schedule.id);
        synced += 1;
      }
      await loadPage();
      setMessage({ ok: true, text: `${synced} offline scheduled ${synced === 1 ? "meeting" : "meetings"} synced.` });
    } catch (syncError) {
      setMessage({ ok: false, text: syncError instanceof Error ? syncError.message : "Offline schedule sync failed." });
    } finally {
      setSaving(false);
    }
  }

  async function postAction(path: string, success: string, payload: unknown = {}) {
    setSaving(true);
    setMessage(null);

    try {
      await apiFetch(path, {
        method: "POST",
        body: JSON.stringify(payload)
      });
      await loadPage();
      setMessage({ ok: true, text: success });
      return true;
    } catch (actionError) {
      setMessage({ ok: false, text: actionError instanceof Error ? actionError.message : "Meeting action failed" });
      return false;
    } finally {
      setSaving(false);
    }
  }

  async function openMeeting(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const opened = await postAction(
      `/groups/${id}/meetings/${unlockForm.meetingId}/open`,
      "Meeting opened after three member credentials were verified.",
      {
        gpsCompliant: true,
        keySubmissions: unlockForm.submissions.map((submission) => ({
          memberId: submission.memberId,
          pin: submission.pin,
          deviceId: "web-group-console"
        }))
      }
    );
    if (opened) setUnlockForm({ meetingId: "", submissions: defaultUnlockSubmissions });
  }

  async function submitOwnKey(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const submitted = await postAction(
      `/groups/${id}/meetings/${memberKeyForm.meetingId}/key-submissions`,
      "Your meeting key was submitted.",
      {
        pin: memberKeyForm.pin,
        deviceId: "web-member-console"
      }
    );
    if (submitted) setMemberKeyForm({ meetingId: "", pin: "" });
  }

  async function recordAttendance(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    try {
      if (isOffline()) throw new ApiClientError({ status: 0, code: "NETWORK_ERROR", message: "Offline", path: "attendance", method: "POST" });
      await postAction(
        `/groups/${id}/meetings/${attendanceForm.meetingId}/attendance`,
        "Attendance recorded.",
        {
          memberId: attendanceForm.memberId,
          status: attendanceForm.status
        }
      );
    } catch (attendanceError) {
      if (!isNetworkFailure(attendanceError)) {
        setMessage({ ok: false, text: attendanceError instanceof Error ? attendanceError.message : "Attendance failed." });
        return;
      }
      await queueMeetingDraft({
        groupId: id,
        meetingId: attendanceForm.meetingId,
        deviceId: getMeetingDeviceId(),
        keySubmissions: [],
        attendance: [
          {
            memberId: attendanceForm.memberId,
            status: attendanceForm.status as "PRESENT" | "ABSENT" | "LATE" | "EXCUSED",
            clientRequestId: `attendance-${attendanceForm.meetingId}-${attendanceForm.memberId}-${Date.now()}`
          }
        ],
        ledgerEntries: [],
        savedAt: new Date().toISOString()
      });
      setMessage({ ok: true, text: "Attendance saved offline. Sync from the meeting entry screen when online." });
    }
  }

  if (loading) return <div className="loading-panel">Loading...</div>;
  if (error) return <div className="error">{error}</div>;

  return (
    <>
      <section className="page-heading">
        <div>
          <Link className="inline-back" href={user?.role === "MEMBER" ? "/dashboard/meetings" : `/dashboard/groups/${id}`}>
            <ArrowLeft size={17} />
            {user?.role === "MEMBER" ? "Meetings" : group?.name ?? "Group"}
          </Link>
          <p className="eyebrow">Group Meetings</p>
          <h2>{group?.code ?? "Meetings"}</h2>
        </div>
        <span className="pill">{meetings.length} meetings</span>
      </section>

      {message ? <div className={message.ok ? "notice success" : "notice warning"}>{message.text}</div> : null}

      {canWrite ? (
        <section className="two-column">
          <div className="data-card">
            <header>
              <h3>Schedule</h3>
            </header>
            <form className="credential-form" onSubmit={createMeeting}>
              <div className="credential-grid">
                <label className="credential-field">
                  <span>Title</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, title: event.target.value }))}
                    required
                    value={form.title}
                  />
                </label>
                <label className="credential-field">
                  <span>Scheduled time</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, scheduledAt: event.target.value }))}
                    required
                    type="datetime-local"
                    value={form.scheduledAt}
                  />
                </label>
              </div>
              <div className="credential-actions">
                <button className="button" disabled={saving} type="submit">
                  <Activity size={16} />
                  {saving ? "Saving" : "Schedule"}
                </button>
              </div>
            </form>
            {pendingSchedules.length > 0 ? (
              <div className="notice warning">
                <strong>{pendingSchedules.length} offline scheduled {pendingSchedules.length === 1 ? "meeting" : "meetings"}</strong>
                <span> saved on this device.</span>
                <div className="credential-actions">
                  <button className="button secondary" disabled={saving || isOffline()} onClick={syncOfflineSchedules} type="button">
                    Sync offline schedules
                  </button>
                </div>
              </div>
            ) : null}
          </div>

          <div className="data-card">
            <header>
              <h3>Unlock</h3>
              <span className="pill">{members.filter((member) => member.pinSet).length} default PIN-ready</span>
            </header>
            <form className="credential-form" onSubmit={openMeeting}>
              <div className="credential-grid">
                <label className="credential-field">
                  <span>Meeting</span>
                  <select
                    onChange={(event) => setUnlockForm((current) => ({ ...current, meetingId: event.target.value }))}
                    required
                    value={unlockForm.meetingId}
                  >
                    <option value="">Select scheduled meeting</option>
                    {scheduledMeetings.map((meeting) => (
                      <option key={meeting.id} value={meeting.id}>
                        {meeting.title}
                      </option>
                    ))}
                  </select>
                </label>
                {unlockForm.submissions.map((submission, index) => (
                  <React.Fragment key={index}>
                    <label className="credential-field">
                      <span>Key member {index + 1}</span>
                      <select
                        onChange={(event) =>
                          setUnlockForm((current) => ({
                            ...current,
                            submissions: current.submissions.map((item, itemIndex) =>
                              itemIndex === index ? { ...item, memberId: event.target.value } : item
                            )
                          }))
                        }
                        required
                        value={submission.memberId}
                      >
                        <option value="">Select member</option>
                        {members.map((member) => (
                          <option key={member.id} value={member.id}>
                            {member.fullName} {member.pinSet ? "" : "(needs PIN)"}
                          </option>
                        ))}
                      </select>
                    </label>
                    <label className="credential-field">
                      <span>PIN or OTP {index + 1}</span>
                      <input
                        inputMode="numeric"
                        /* 4 for a PIN, 6 for a one-time code. */
                        maxLength={6}
                        minLength={4}
                        onChange={(event) =>
                          setUnlockForm((current) => ({
                            ...current,
                            submissions: current.submissions.map((item, itemIndex) =>
                              itemIndex === index ? { ...item, pin: event.target.value } : item
                            )
                          }))
                        }
                        pattern="[0-9]{4}|[0-9]{6}"
                        required
                        type="password"
                        value={submission.pin}
                      />
                    </label>
                  </React.Fragment>
                ))}
              </div>
              <div className="credential-actions">
                <button className="button" disabled={saving || scheduledMeetings.length === 0} type="submit">
                  <KeyRound size={16} />
                  Open
                </button>
              </div>
            </form>
          </div>
        </section>
      ) : null}

      {canSubmitOwnKey ? (
        <section className="data-card">
          <header>
            <h3>My Key</h3>
          </header>
          <form className="credential-form" onSubmit={submitOwnKey}>
            <div className="credential-grid">
              <label className="credential-field">
                <span>Meeting</span>
                <select
                  onChange={(event) => setMemberKeyForm((current) => ({ ...current, meetingId: event.target.value }))}
                  required
                  value={memberKeyForm.meetingId}
                >
                  <option value="">Select meeting</option>
                  {scheduledMeetings.map((meeting) => (
                    <option key={meeting.id} value={meeting.id}>
                      {meeting.title}
                    </option>
                  ))}
                </select>
              </label>
              <label className="credential-field">
                <span>My PIN or OTP</span>
                <input
                  inputMode="numeric"
                  /* 4 for a PIN, 6 for a one-time code. */
                  maxLength={6}
                  minLength={4}
                  onChange={(event) => setMemberKeyForm((current) => ({ ...current, pin: event.target.value }))}
                  pattern="[0-9]{4}|[0-9]{6}"
                  required
                  type="password"
                  value={memberKeyForm.pin}
                />
              </label>
            </div>
            <div className="credential-actions">
              <button className="button" disabled={saving || scheduledMeetings.length === 0} type="submit">
                <KeyRound size={16} />
                Submit key
              </button>
            </div>
          </form>
        </section>
      ) : null}

      {canWrite ? (
        <section className="two-column">
          <div className="data-card">
            <header>
              <h3>Record Attendance</h3>
            </header>
            <form className="credential-form" onSubmit={recordAttendance}>
              <div className="credential-grid">
                <label className="credential-field">
                  <span>Meeting</span>
                  <select
                    onChange={(event) => setAttendanceForm((current) => ({ ...current, meetingId: event.target.value }))}
                    required
                    value={attendanceForm.meetingId}
                  >
                    <option value="">Select meeting</option>
                    {activeMeetings.map((meeting) => (
                      <option key={meeting.id} value={meeting.id}>
                        {meeting.title}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="credential-field">
                  <span>Member</span>
                  <select
                    onChange={(event) => setAttendanceForm((current) => ({ ...current, memberId: event.target.value }))}
                    required
                    value={attendanceForm.memberId}
                  >
                    <option value="">Select member</option>
                    {members.map((member) => (
                      <option key={member.id} value={member.id}>
                        {member.fullName}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="credential-field">
                  <span>Status</span>
                  <select
                    onChange={(event) => setAttendanceForm((current) => ({ ...current, status: event.target.value }))}
                    value={attendanceForm.status}
                  >
                    <option value="PRESENT">Present</option>
                    <option value="LATE">Late</option>
                    <option value="ABSENT">Absent</option>
                    <option value="EXCUSED">Excused</option>
                  </select>
                </label>
              </div>
              <div className="credential-actions">
                <button className="button" disabled={saving} type="submit">
                  <UserCheck size={16} />
                  Record
                </button>
              </div>
            </form>
          </div>
        </section>
      ) : null}

      <section className="data-card">
        <header>
          <h3>Meetings</h3>
        </header>
        <DataTable
          columns={[
            {
              key: "title",
              header: "Meeting",
              value: (meeting) => `${meeting.title} ${meeting.scheduledAt}`,
              cell: (meeting) => (
                <>
                  <strong>{meeting.title}</strong>
                  <br />
                  <span>{new Date(meeting.scheduledAt).toLocaleString("en-KE")}</span>
                </>
              )
            },
            {
              key: "status",
              header: "Status",
              value: (meeting) => humanizeEnum(meeting.status),
              cell: (meeting) => <span className="pill blue">{humanizeEnum(meeting.status)}</span>
            },
            {
              key: "step",
              header: "Next Step",
              value: (meeting) => nextStep(meeting) ?? "Complete",
              cell: (meeting) => {
                const step = nextStep(meeting);
                return step ? humanizeEnum(step) : "Complete";
              }
            },
            {
              key: "keys",
              header: "Keys",
              value: (meeting) => meeting.keySubmissions?.length ?? 0,
              cell: (meeting) => `${meeting.keySubmissions?.length ?? 0}/3`
            },
            {
              key: "action",
              header: "Action",
              value: () => "",
              exportable: false,
              searchable: false,
              sortable: false,
              cell: (meeting) => {
                const step = nextStep(meeting);
                if (!canWrite) return "No action";
                if (meeting.status === "QUEUED_OFFLINE") return "Waiting to sync";
                let primaryAction: React.ReactNode = "No action";
                if (meeting.status === "SCHEDULED") {
                  primaryAction = (
                    <button
                      className="button secondary table-action-button"
                      disabled={saving}
                      onClick={() =>
                        setUnlockForm((current) => ({
                          ...current,
                          meetingId: meeting.id
                        }))
                      }
                      type="button"
                    >
                      <DoorOpen size={15} />
                      Unlock
                    </button>
                  );
                } else if (meeting.status === "IN_PROGRESS" && step) {
                  primaryAction = (
                    <button
                      className="button secondary table-action-button"
                      disabled={saving}
                      onClick={() => postAction(`/groups/${id}/meetings/${meeting.id}/steps/${step}/complete`, `${humanizeEnum(step)} completed.`)}
                      type="button"
                    >
                      <CheckCircle2 size={15} />
                      Step
                    </button>
                  );
                } else if (meeting.status === "IN_PROGRESS") {
                  primaryAction = (
                    <button
                      className="button secondary table-action-button"
                      disabled={saving}
                      onClick={() => postAction(`/groups/${id}/meetings/${meeting.id}/seal`, "Meeting sealed.")}
                      type="button"
                    >
                      <LockKeyhole size={15} />
                      Seal
                    </button>
                  );
                }
                return (
                  <div className="table-action-group">
                    {primaryAction}
                    <Link className="button table-action-button" href={`/dashboard/meetings/${meeting.id}/entry`}>
                      <BookOpenText size={15} />
                      Entry
                    </Link>
                  </div>
                );
              }
            }
          ]}
          exportName={`${group?.code ?? "group"}-meetings`}
          getRowKey={(meeting) => meeting.id}
          rows={meetings}
          title="Meetings"
        />
      </section>
    </>
  );
}
