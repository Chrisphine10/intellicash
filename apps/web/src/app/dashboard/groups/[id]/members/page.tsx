"use client";

import React from "react";
import type { FormEvent } from "react";
import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, KeyRound, Pencil, ShieldCheck, Trash2, UserPlus, X } from "@/lib/theme-icons";
import { memberRoles } from "@intellicash/shared";
import { apiFetch, humanizeEnum } from "../../../../../lib/api";
import { CollectionView } from "../../../../../components/dashboard/collection-view";
import { DataTable } from "../../../../../components/dashboard/data-table";
import type { Member, User } from "../../../../../components/dashboard/types";

interface GroupSummary {
  id: string;
  name: string;
  code: string;
}

const defaultForm = {
  fullName: "",
  phone: "",
  role: "MEMBER",
  kycStatus: "PENDING",
  status: "ACTIVE"
};

export default function GroupMembersPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [group, setGroup] = useState<GroupSummary | null>(null);
  const [members, setMembers] = useState<Member[]>([]);
  const [user, setUser] = useState<User | null>(null);
  const [form, setForm] = useState(defaultForm);
  const [editingMember, setEditingMember] = useState<Member | null>(null);
  const [editForm, setEditForm] = useState(defaultForm);

  /**
   * Erasing a member's personal data under the Data Protection Act.
   *
   * Separate from editing and from removing them from the group, because it is
   * neither: the person stays on the roster and their savings stay in the
   * group's books — under a pseudonym. What goes is the name, the phone, the
   * national ID hash and the PIN.
   */
  const [erasingMember, setErasingMember] = useState<Member | null>(null);
  const [eraseConfirmName, setEraseConfirmName] = useState("");
  const [eraseReason, setEraseReason] = useState("");
  const [erasing, setErasing] = useState(false);
  const [pinForm, setPinForm] = useState({ memberId: "" });
  const [otpForm, setOtpForm] = useState({ memberId: "" });
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  async function loadPage() {
    const [groupResponse, memberResponse, meResponse] = await Promise.all([
      apiFetch<GroupSummary>(`/groups/${id}`),
      apiFetch<Member[]>(`/groups/${id}/members`),
      apiFetch<User>("/auth/me")
    ]);
    setGroup(groupResponse);
    setMembers(memberResponse);
    setUser(meResponse);
  }

  useEffect(() => {
    let mounted = true;
    loadPage()
      .catch((loadError) => {
        if (mounted) setError(loadError instanceof Error ? loadError.message : "Members failed to load");
      })
      .finally(() => {
        if (mounted) setLoading(false);
      });
    return () => {
      mounted = false;
    };
  }, [id]);

  useEffect(() => {
    // The erasure modal locks the body too, or the page scrolls behind a
    // confirmation somebody is meant to read.
    document.body.classList.toggle(
      "modal-open",
      Boolean(editingMember) || Boolean(erasingMember)
    );
    return () => document.body.classList.remove("modal-open");
  }, [editingMember, erasingMember]);

  const canWrite = user?.permissions?.includes("members:write") ?? false;

  function openEditMember(member: Member) {
    setEditingMember(member);
    setEditForm({
      fullName: member.fullName,
      phone: member.phone,
      role: member.role,
      kycStatus: member.kycStatus,
      status: member.status
    });
    setMessage(null);
  }

  function startErasingMember(member: Member) {
    setErasingMember(member);
    setEraseConfirmName("");
    setEraseReason("");
    setMessage(null);
  }

  async function confirmEraseMember() {
    if (!erasingMember) return;
    setErasing(true);
    try {
      const result = await apiFetch<{ erased?: boolean; alreadyErased?: boolean }>(
        `/members/${erasingMember.id}/erase`,
        {
          method: "POST",
          body: JSON.stringify({
            confirmFullName: eraseConfirmName,
            reason: eraseReason.trim() === "" ? undefined : eraseReason.trim()
          })
        }
      );

      // Reloaded rather than removed from local state: the row is still there,
      // now pseudonymous. Making it disappear would suggest the savings went
      // with it.
      await loadPage();
      setErasingMember(null);
      setMessage({
        ok: true,
        text: result.alreadyErased
          ? "That member's details were already erased."
          : "Personal details erased. The group's financial record is kept under a pseudonym."
      });
    } catch (error) {
      setMessage({
        ok: false,
        text: error instanceof Error ? error.message : "Could not erase those details."
      });
    } finally {
      setErasing(false);
    }
  }

  function closeEditMember() {
    setEditingMember(null);
  }

  async function createMember(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSaving(true);
    setMessage(null);

    try {
      const created = await apiFetch<Member>(`/groups/${id}/members`, {
        method: "POST",
        body: JSON.stringify(form)
      });
      setForm(defaultForm);
      await loadPage();
      setMessage({ ok: true, text: `${created.fullName} added. Default meeting PIN queued for SMS.` });
    } catch (saveError) {
      setMessage({ ok: false, text: saveError instanceof Error ? saveError.message : "Member failed to save" });
    } finally {
      setSaving(false);
    }
  }

  async function updateMemberPin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSaving(true);
    setMessage(null);

    try {
      const member = await apiFetch<Member>(`/groups/${id}/members/${pinForm.memberId}/pin`, {
        method: "POST",
        body: JSON.stringify({})
      });
      setPinForm({ memberId: "" });
      await loadPage();
      setMessage({ ok: true, text: `${member.fullName} default meeting PIN generated and queued for SMS.` });
    } catch (pinError) {
      setMessage({ ok: false, text: pinError instanceof Error ? pinError.message : "PIN update failed" });
    } finally {
      setSaving(false);
    }
  }

  async function sendMemberOtp(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSaving(true);
    setMessage(null);

    try {
      const member = await apiFetch<Member>(`/groups/${id}/members/${otpForm.memberId}/otp`, {
        method: "POST",
        body: JSON.stringify({})
      });
      setOtpForm({ memberId: "" });
      await loadPage();
      setMessage({ ok: true, text: `${member.fullName} meeting OTP generated and queued for SMS.` });
    } catch (otpError) {
      setMessage({ ok: false, text: otpError instanceof Error ? otpError.message : "OTP update failed" });
    } finally {
      setSaving(false);
    }
  }

  async function submitMemberEdit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!editingMember) return;
    setSaving(true);
    setMessage(null);

    try {
      const updated = await apiFetch<Member>(`/groups/${id}/members/${editingMember.id}`, {
        method: "PATCH",
        body: JSON.stringify({
          fullName: editForm.fullName,
          phone: editForm.phone,
          role: editForm.role,
          kycStatus: editForm.kycStatus,
          status: editForm.status
        })
      });
      setMembers((current) => current.map((candidate) => (candidate.id === updated.id ? updated : candidate)));
      setEditingMember(null);
      setMessage({ ok: true, text: `${updated.fullName} updated.` });
    } catch (updateError) {
      setMessage({ ok: false, text: updateError instanceof Error ? updateError.message : "Member update failed" });
    } finally {
      setSaving(false);
    }
  }

  async function updateMember(member: Member, kycStatus: string) {
    setSaving(true);
    setMessage(null);

    try {
      const updated = await apiFetch<Member>(`/groups/${id}/members/${member.id}`, {
        method: "PATCH",
        body: JSON.stringify({ kycStatus })
      });
      setMembers((current) => current.map((candidate) => (candidate.id === updated.id ? updated : candidate)));
      setMessage({ ok: true, text: `${updated.fullName} updated.` });
    } catch (updateError) {
      setMessage({ ok: false, text: updateError instanceof Error ? updateError.message : "Member update failed" });
    } finally {
      setSaving(false);
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
          <p className="eyebrow">Group Members</p>
          <h2>{group?.code ?? "Members"}</h2>
        </div>
        <span className="pill">{members.length} members</span>
      </section>

      {!editingMember && message ? <div className={message.ok ? "notice success" : "notice warning"}>{message.text}</div> : null}

      {erasingMember ? (
        <div className="modal-overlay" role="dialog" aria-modal="true" aria-label="Erase personal data">
          <button
            aria-label="Close"
            className="modal-backdrop"
            onClick={() => setErasingMember(null)}
            type="button"
          />
          <section className="data-card credential-modal">
            <header>
              <div>
                <h3>Erase personal data</h3>
                <span>{erasingMember.fullName}</span>
              </div>
              <button
                aria-label="Close"
                className="icon-button"
                onClick={() => setErasingMember(null)}
                type="button"
              >
                <X size={18} />
              </button>
            </header>

            <div className="close-account-panel notice warning">
              <p>
                <strong>Removed:</strong> name, phone, national ID and the PIN. Afterwards
                nothing on the record says who this person was or how to reach them.
              </p>
              <p>
                <strong>Kept:</strong> their contributions, repayments and loans, under a
                pseudonym. Those rows are also other members&rsquo; balances — deleting them
                would change what the rest of the group is owed, which is a worse privacy
                outcome than a pseudonymous row.
              </p>
              <p>This cannot be undone.</p>

              <label className="credential-field">
                <span>Type {erasingMember.fullName} to confirm</span>
                <input
                  autoComplete="off"
                  onChange={(event) => setEraseConfirmName(event.target.value)}
                  value={eraseConfirmName}
                />
              </label>
              <label className="credential-field">
                <span>Reason (optional, recorded in the audit trail)</span>
                <input
                  onChange={(event) => setEraseReason(event.target.value)}
                  placeholder="Member asked for their details to be removed"
                  value={eraseReason}
                />
              </label>

              <div className="form-actions">
                <button
                  className="button danger"
                  disabled={erasing || eraseConfirmName.trim() !== erasingMember.fullName.trim()}
                  onClick={confirmEraseMember}
                  type="button"
                >
                  {erasing ? "Erasing…" : "Erase these details"}
                </button>
                <button
                  className="button secondary"
                  onClick={() => setErasingMember(null)}
                  type="button"
                >
                  Cancel
                </button>
              </div>
            </div>
          </section>
        </div>
      ) : null}

      {editingMember && canWrite ? (
        <div className="modal-overlay" role="dialog" aria-modal="true" aria-label="Edit member">
          <button className="modal-backdrop" onClick={closeEditMember} type="button" aria-label="Close member editor" />
          <section className="data-card credential-modal">
            <header>
              <div>
                <h3>Edit Member</h3>
                <span>Profile, role, KYC, and activation status.</span>
              </div>
              <button className="icon-button" onClick={closeEditMember} type="button" aria-label="Close">
                <X size={18} />
              </button>
            </header>
            <form className="credential-form" onSubmit={submitMemberEdit}>
              <div className="credential-grid">
                <label className="credential-field">
                  <span>Name</span>
                  <input
                    onChange={(event) => setEditForm((current) => ({ ...current, fullName: event.target.value }))}
                    required
                    value={editForm.fullName}
                  />
                </label>
                <label className="credential-field">
                  <span>Phone</span>
                  <input
                    onChange={(event) => setEditForm((current) => ({ ...current, phone: event.target.value }))}
                    required
                    value={editForm.phone}
                  />
                </label>
                <label className="credential-field">
                  <span>Role</span>
                  <select
                    onChange={(event) => setEditForm((current) => ({ ...current, role: event.target.value }))}
                    value={editForm.role}
                  >
                    {memberRoles.map((role) => (
                      <option key={role} value={role}>
                        {humanizeEnum(role)}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="credential-field">
                  <span>KYC</span>
                  <select
                    onChange={(event) => setEditForm((current) => ({ ...current, kycStatus: event.target.value }))}
                    value={editForm.kycStatus}
                  >
                    <option value="PENDING">Pending</option>
                    <option value="VERIFIED">Verified</option>
                    <option value="REJECTED">Rejected</option>
                  </select>
                </label>
                <label className="credential-field">
                  <span>Status</span>
                  <select
                    onChange={(event) => setEditForm((current) => ({ ...current, status: event.target.value }))}
                    value={editForm.status}
                  >
                    <option value="ACTIVE">Active</option>
                    <option value="INACTIVE">Inactive</option>
                    <option value="SUSPENDED">Suspended</option>
                  </select>
                </label>
              </div>
              {message ? (
                <div className={message.ok ? "notice success" : "notice warning"}>{message.text}</div>
              ) : null}
              <div className="credential-actions">
                <button className="button" disabled={saving} type="submit">
                  <Pencil size={16} />
                  {saving ? "Saving" : "Save member"}
                </button>
                <button className="button secondary" onClick={closeEditMember} type="button">
                  Cancel
                </button>
              </div>
            </form>
          </section>
        </div>
      ) : null}

      {canWrite ? (
        <section className="data-card">
          <header>
            <h3>Add Member</h3>
          </header>
          <form className="credential-form" onSubmit={createMember}>
            <div className="credential-grid">
              <label className="credential-field">
                <span>Name</span>
                <input
                  onChange={(event) => setForm((current) => ({ ...current, fullName: event.target.value }))}
                  required
                  value={form.fullName}
                />
              </label>
              <label className="credential-field">
                <span>Phone</span>
                <input
                  onChange={(event) => setForm((current) => ({ ...current, phone: event.target.value }))}
                  required
                  value={form.phone}
                />
              </label>
              <label className="credential-field">
                <span>Role</span>
                <select
                  onChange={(event) => setForm((current) => ({ ...current, role: event.target.value }))}
                  value={form.role}
                >
                  {memberRoles.map((role) => (
                    <option key={role} value={role}>
                      {humanizeEnum(role)}
                    </option>
                  ))}
                </select>
              </label>
              <label className="credential-field">
                <span>KYC</span>
                <select
                  onChange={(event) => setForm((current) => ({ ...current, kycStatus: event.target.value }))}
                  value={form.kycStatus}
                >
                  <option value="PENDING">Pending</option>
                  <option value="VERIFIED">Verified</option>
                  <option value="REJECTED">Rejected</option>
                </select>
              </label>
            </div>
            <div className="credential-actions">
              <button className="button" disabled={saving} type="submit">
                <UserPlus size={16} />
                {saving ? "Saving" : "Add member"}
              </button>
            </div>
          </form>
        </section>
      ) : null}

      {canWrite ? (
        <section className="data-card">
          <header>
            <h3>Default PIN</h3>
            <span className="pill">{members.filter((member) => member.pinSet).length} default PIN-ready</span>
          </header>
          <form className="credential-form" onSubmit={updateMemberPin}>
            <div className="credential-grid">
              <label className="credential-field">
                <span>Member</span>
                <select
                  onChange={(event) => setPinForm((current) => ({ ...current, memberId: event.target.value }))}
                  required
                  value={pinForm.memberId}
                >
                  <option value="">Select member</option>
                  {members.map((member) => (
                    <option key={member.id} value={member.id}>
                      {member.fullName} {member.pinSet ? "(PIN set)" : "(needs PIN)"}
                    </option>
                  ))}
                </select>
              </label>
            </div>
            <div className="credential-actions">
              <button className="button" disabled={saving} type="submit">
                <KeyRound size={16} />
                  {saving ? "Sending" : "Send default PIN"}
              </button>
            </div>
          </form>
        </section>
      ) : null}

      {canWrite ? (
        <section className="data-card">
          <header>
            <h3>Meeting OTP</h3>
            <span className="pill">{members.filter((member) => member.currentOtpSet).length} OTP-current</span>
          </header>
          <form className="credential-form" onSubmit={sendMemberOtp}>
            <div className="credential-grid">
              <label className="credential-field">
                <span>Member</span>
                <select
                  onChange={(event) => setOtpForm((current) => ({ ...current, memberId: event.target.value }))}
                  required
                  value={otpForm.memberId}
                >
                  <option value="">Select member</option>
                  {members.map((member) => (
                    <option key={member.id} value={member.id}>
                      {member.fullName} {member.currentOtpSet ? "(OTP current)" : ""}
                    </option>
                  ))}
                </select>
              </label>
            </div>
            <div className="credential-actions">
              <button className="button" disabled={saving} type="submit">
                <KeyRound size={16} />
                {saving ? "Sending" : "Send OTP"}
              </button>
            </div>
          </form>
        </section>
      ) : null}

      <section className="data-card">
        <header>
          <h3>Members</h3>
        </header>
        <CollectionView
          count={members.length}
          label="members"
          cards={
            <div className="card-grid compact">
              {members.map((member) => (
                <article className="record-card" key={member.id}>
                  <header>
                    <div>
                      <h4>{member.fullName}</h4>
                      <small>{member.phone}</small>
                    </div>
                    <span className={`pill ${member.pinSet ? "blue" : "gold"}`}>
                      {member.pinSet ? "PIN set" : "Needs PIN"}
                    </span>
                  </header>
                  <div className="record-card-meta">
                    <div>
                      <span>Role</span>
                      <strong>{humanizeEnum(member.role)}</strong>
                    </div>
                    <div>
                      <span>KYC</span>
                      <strong>{humanizeEnum(member.kycStatus)}</strong>
                    </div>
                    <div>
                      <span>Status</span>
                      <strong>{humanizeEnum(member.status)}</strong>
                    </div>
                    <div>
                      <span>PIN</span>
                      <strong>{member.pinSet ? "Ready" : "Pending"}</strong>
                    </div>
                  </div>
                  {canWrite ? (
                    <div className="record-card-actions">
                      <button
                        className="button secondary"
                        disabled={saving}
                        onClick={() => openEditMember(member)}
                        type="button"
                      >
                        <Pencil size={15} />
                        Edit
                      </button>
                      <button
                        className="button secondary"
                        disabled={saving || member.kycStatus === "VERIFIED"}
                        onClick={() => updateMember(member, "VERIFIED")}
                        type="button"
                      >
                        <ShieldCheck size={15} />
                        Verify
                      </button>
                      <button
                        className="link-button danger"
                        disabled={saving}
                        onClick={() => startErasingMember(member)}
                        type="button"
                      >
                        <Trash2 size={15} />
                        Erase details
                      </button>
                    </div>
                  ) : null}
                </article>
              ))}
              {members.length === 0 ? <div className="empty-state">No members</div> : null}
            </div>
          }
          list={
            <DataTable
              columns={[
            { key: "name", header: "Name", value: (member) => member.fullName },
            { key: "phone", header: "Phone", value: (member) => member.phone },
            { key: "role", header: "Role", value: (member) => humanizeEnum(member.role) },
            {
              key: "pin",
              header: "PIN",
              value: (member) => (member.pinSet ? "PIN set" : "Needs PIN"),
              cell: (member) => <span className={`pill ${member.pinSet ? "blue" : "gold"}`}>{member.pinSet ? "PIN set" : "Needs PIN"}</span>
            },
            {
              key: "kyc",
              header: "KYC",
              value: (member) => member.kycStatus,
              cell: (member) => <span className="pill">{humanizeEnum(member.kycStatus)}</span>
            },
            {
              key: "action",
              header: "Action",
              value: () => "",
              exportable: false,
              searchable: false,
              sortable: false,
              cell: (member) =>
                canWrite ? (
                  <div className="record-card-actions">
                    <button
                      className="button secondary table-action-button"
                      disabled={saving}
                      onClick={() => openEditMember(member)}
                      type="button"
                    >
                      <Pencil size={15} />
                      Edit
                    </button>
                    <button
                      className="button secondary table-action-button"
                      disabled={saving || member.kycStatus === "VERIFIED"}
                      onClick={() => updateMember(member, "VERIFIED")}
                      type="button"
                    >
                      <ShieldCheck size={15} />
                      Verify
                    </button>
                  </div>
                ) : (
                  "No action"
                )
            }
          ]}
          exportName={`${group?.code ?? "group"}-members`}
          getRowKey={(member) => member.id}
          rows={members}
          title="Members"
            />
          }
        />
      </section>
    </>
  );
}
