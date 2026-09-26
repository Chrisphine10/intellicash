"use client";

import type { ComponentType, FormEvent } from "react";
import { use, useEffect, useState } from "react";
import Link from "next/link";
import {
  Activity,
  ArrowLeft,
  Banknote,
  Building2,
  CalendarDays,
  ClipboardList,
  FileText,
  HeartHandshake,
  Pencil,
  Settings,
  TrendingUp,
  UserCog,
  UserPlus,
  UsersRound,
  Vote,
  X
} from "@/lib/theme-icons";
import { apiFetch, formatDateTime, formatKes, humanizeEnum } from "../../../../lib/api";
import { meetingStatusClass, meetingStatusLabel, meetingStatusName } from "../../../../features/meetings/model";
import { DataTable } from "../../../../components/dashboard/data-table";
import { GroupMoneyCards, type GroupMoneyStatement } from "../../../../features/groups/group-money";
import { ChampionAccessCard } from "../../../../components/dashboard/champion-access-card";
import {
  isGroupSteward,
  isViewOnlyOverGroups,
  useCurrentUser,
  userCan,
  ViewOnlyNotice
} from "../../../../lib/current-user";
import type { AgentRow, LedgerEntry, MeetingRow, Member, ProgrammeRow, VoteRow } from "../../../../components/dashboard/types";

const groupPhases = ["MOBILISATION", "INTENSIVE", "DEVELOPMENT", "MATURITY", "POST_GRADUATION"];

const defaultGroupForm = {
  name: "",
  code: "",
  county: "",
  subCounty: "",
  phase: "MOBILISATION",
  programmeIds: [] as string[],
  // Every VA / CBT serving the group; `villageAgentId` is the lead among them.
  agentIds: [] as string[],
  villageAgentId: "",
  location: "",
  objective: "",
  contactPersonName: "",
  contactPhone: "",
  meetingDay: "",
  gpsLatitude: "",
  gpsLongitude: "",
  gpsRadiusMeters: "50",
  shareValue: "500",
  maxSharesPerMemberPerMeeting: "10",
  constitutionVersion: "IWLSGS-1.0",
  cycleNumber: "1"
};

interface GroupDetail {
  /** Optional programme modules; voting off hides the Votes page link. */
  modules?: { store: boolean; voting: boolean };
  id: string;
  name: string;
  code: string;
  phase: string;
  county: string;
  subCounty?: string | null;
  location?: string | null;
  gpsLatitude?: number | null;
  gpsLongitude?: number | null;
  gpsRadiusMeters?: number | null;
  shareValueCents?: number;
  maxSharesPerMemberPerMeeting?: number;
  composition?: string | null;
  objective?: string | null;
  contactPersonName?: string | null;
  contactPhone?: string | null;
  onboardingFeedback?: string | null;
  meetingDay?: string | null;
  constitutionVersion: string;
  cycleNumber: number;
  programme?: { id?: string; name: string; partner?: { name: string } | null } | null;
  programmeLinks?: Array<{ id: string; role: string; programme: ProgrammeRow }>;
  villageAgent?: { id?: string; name: string } | null;
  /** Every agent serving the group, lead first (a group can have several). */
  agentLinks?: Array<{ isLead: boolean; villageAgent: { id: string; name: string } }>;
  fundAccounts: Array<{ type: string; balanceCents: number; currency: string }>;
  creditScores: Array<{ score: number; computedAt: string; breakdownJson: string }>;
  _count: {
    members: number;
    meetings: number;
    votes: number;
    ledgerEntries: number;
  };
}

/** Funds shown only when they hold money; the four cards cover the others. */
const EXTRA_FUNDS: Record<string, string> = {
  GRANT: "Grant fund",
  EXTERNAL_LOAN: "External loan fund",
  VSLF: "VSLF"
};

const KYC_LABELS: Record<string, string> = { PENDING: "Pending", VERIFIED: "Verified", REJECTED: "Rejected" };
const VOTE_RESULTS: Record<string, string> = { PASSED: "Passed", FAILED: "Failed", TIED: "Tied", DEFERRED: "Deferred" };

type Section = "statement" | "members" | "meetings" | "ledger" | "votes";

interface ManageLink {
  href: string;
  label: string;
  note: string;
  icon: ComponentType<{ size?: number }>;
  show: boolean;
}

export default function DashboardGroupDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const user = useCurrentUser();
  const isMember = user?.role === "MEMBER";
  const viewOnly = isViewOnlyOverGroups(user);
  const canEditGroup = userCan(user, "groups:write");

  const [group, setGroup] = useState<GroupDetail | null>(null);
  const [statement, setStatement] = useState<GroupMoneyStatement | null>(null);
  const [members, setMembers] = useState<Member[]>([]);
  const [meetings, setMeetings] = useState<MeetingRow[]>([]);
  const [ledger, setLedger] = useState<LedgerEntry[]>([]);
  const [votes, setVotes] = useState<VoteRow[]>([]);
  // A section that could not load says so in its own card; the rest of the
  // page still shows. One refused call used to blank everything.
  const [failed, setFailed] = useState<Partial<Record<Section, string>>>({});
  const [programmes, setProgrammes] = useState<ProgrammeRow[]>([]);
  const [agents, setAgents] = useState<AgentRow[]>([]);
  const [form, setForm] = useState(defaultGroupForm);
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const canRead = (permission: string) => userCan(user, permission);
  const showMoney = !isMember && canRead("ledger:read");
  const showMembers = canRead("members:read");
  const showMeetings = canRead("meetings:read");
  const showLedger = canRead("ledger:read");
  const showVotes = !isMember && canRead("votes:read");

  useEffect(() => {
    let mounted = true;

    async function loadGroup() {
      try {
        const groupResponse = await apiFetch<GroupDetail>(`/groups/${id}`);
        if (!mounted) return;
        setGroup(groupResponse);

        const sections: Array<[Section, boolean, () => Promise<unknown>, (value: never) => void]> = [
          ["statement", showMoney, () => apiFetch<{ statement: GroupMoneyStatement }>(`/reports/group/${id}`), (value: { statement: GroupMoneyStatement }) => setStatement(value.statement)],
          ["members", showMembers, () => apiFetch<Member[]>(`/groups/${id}/members`), setMembers],
          ["meetings", showMeetings, () => apiFetch<MeetingRow[]>(`/groups/${id}/meetings`), setMeetings],
          ["ledger", showLedger, () => apiFetch<LedgerEntry[]>(`/groups/${id}/ledger`), setLedger],
          ["votes", showVotes && groupResponse.modules?.voting !== false, () => apiFetch<VoteRow[]>(`/groups/${id}/votes`), setVotes]
        ];
        const wanted = sections.filter(([, show]) => show);
        const results = await Promise.allSettled(wanted.map(([, , load]) => load()));
        if (!mounted) return;
        const problems: Partial<Record<Section, string>> = {};
        results.forEach((result, index) => {
          const [key, , , apply] = wanted[index]!;
          if (result.status === "fulfilled") apply(result.value as never);
          else problems[key] = result.reason instanceof Error ? result.reason.message : "Could not load this section.";
        });
        setFailed(problems);
      } catch (loadError) {
        if (mounted) setError(loadError instanceof Error ? loadError.message : "Group failed to load");
      } finally {
        if (mounted) setLoading(false);
      }
    }

    loadGroup();
    return () => {
      mounted = false;
    };
    // The viewer's permissions come from the shell and do not change here.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  useEffect(() => {
    document.body.classList.toggle("modal-open", isEditOpen);
    return () => document.body.classList.remove("modal-open");
  }, [isEditOpen]);

  async function openEditGroup(target: GroupDetail) {
    setForm({
      name: target.name,
      code: target.code,
      county: target.county,
      subCounty: target.subCounty ?? "",
      phase: target.phase,
      programmeIds: target.programmeLinks?.map((link) => link.programme.id) ?? (target.programme?.id ? [target.programme.id] : []),
      agentIds: target.agentLinks?.length
        ? target.agentLinks.map((link) => link.villageAgent.id)
        : target.villageAgent?.id
          ? [target.villageAgent.id]
          : [],
      villageAgentId: target.villageAgent?.id ?? "",
      location: target.location ?? "",
      objective: target.objective ?? "",
      contactPersonName: target.contactPersonName ?? "",
      contactPhone: target.contactPhone ?? "",
      meetingDay: target.meetingDay ?? "",
      gpsLatitude: target.gpsLatitude === null || target.gpsLatitude === undefined ? "" : String(target.gpsLatitude),
      gpsLongitude: target.gpsLongitude === null || target.gpsLongitude === undefined ? "" : String(target.gpsLongitude),
      gpsRadiusMeters: String(target.gpsRadiusMeters ?? 50),
      shareValue: String((target.shareValueCents ?? 50000) / 100),
      maxSharesPerMemberPerMeeting: String(target.maxSharesPerMemberPerMeeting ?? 10),
      constitutionVersion: target.constitutionVersion ?? "IWLSGS-1.0",
      cycleNumber: String(target.cycleNumber ?? 1)
    });
    setMessage(null);
    setIsEditOpen(true);
    // The choices only an editor needs, fetched when the editor opens.
    if (programmes.length === 0) {
      const [programmeList, agentList] = await Promise.all([
        apiFetch<ProgrammeRow[]>("/programmes").catch(() => [] as ProgrammeRow[]),
        apiFetch<AgentRow[]>("/village-agents").catch(() => [] as AgentRow[])
      ]);
      setProgrammes(programmeList);
      setAgents(agentList);
    }
  }

  function closeGroupModal() {
    setIsEditOpen(false);
  }

  async function submitGroup(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!group) return;
    setSaving(true);
    setMessage(null);

    try {
      const saved = await apiFetch<GroupDetail>(`/groups/${group.id}`, {
        method: "PATCH",
        body: JSON.stringify({
          name: form.name,
          code: form.code,
          county: form.county,
          subCounty: form.subCounty || null,
          phase: form.phase,
          programmeIds: form.programmeIds,
          agentIds: form.agentIds,
          leadAgentId: form.agentIds.includes(form.villageAgentId) ? form.villageAgentId : form.agentIds[0] ?? null,
          location: form.location || null,
          objective: form.objective || null,
          contactPersonName: form.contactPersonName || null,
          contactPhone: form.contactPhone || null,
          meetingDay: form.meetingDay || null,
          gpsLatitude: form.gpsLatitude === "" ? null : Number(form.gpsLatitude),
          gpsLongitude: form.gpsLongitude === "" ? null : Number(form.gpsLongitude),
          gpsRadiusMeters: Number(form.gpsRadiusMeters || 50),
          shareValueCents: Math.round(Number(form.shareValue || 500) * 100),
          maxSharesPerMemberPerMeeting: Number(form.maxSharesPerMemberPerMeeting || 10),
          constitutionVersion: form.constitutionVersion,
          cycleNumber: Number(form.cycleNumber || 1)
        })
      });

      setGroup(saved);
      setMessage({ ok: true, text: `${saved.name} group updated.` });
      setIsEditOpen(false);
    } catch (saveError) {
      setMessage({
        ok: false,
        text: saveError instanceof Error ? saveError.message : "Group failed to save"
      });
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <div className="loading-panel">Loading group…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!group) return <div className="empty-state">This group was not found.</div>;

  const latestScore = group.creditScores[0]?.score;
  const programmeNames =
    group.programmeLinks && group.programmeLinks.length > 0
      ? group.programmeLinks.map((link) => link.programme.name).join(", ")
      : group.programme?.name ?? null;
  const cycleNumber = statement?.cycle?.number ?? group.cycleNumber;
  const votingOn = group.modules?.voting !== false;
  const steward = isGroupSteward(user, group.id);
  const base = `/dashboard/groups/${group.id}`;

  const manage: Array<{ title: string; links: ManageLink[] }> = [
    {
      title: "Money",
      links: [
        { href: `${base}/ledger`, label: "Ledger", note: "Shares, loans, repayments", icon: FileText, show: canRead("ledger:read") },
        { href: `${base}/welfare`, label: "Welfare", note: "Social fund payments", icon: HeartHandshake, show: canRead("ledger:read") },
        { href: `${base}/policy`, label: "Loan rules", note: "Share value, interest, limits", icon: Settings, show: canRead("groups:read") },
        { href: `${base}/payment-providers`, label: "Payment providers", note: "M-Pesa and bank set-up", icon: Banknote, show: canRead("groups:read") }
      ]
    },
    {
      title: "People",
      links: [
        { href: `${base}/members`, label: "Members", note: `${group._count.members} on the roster`, icon: UsersRound, show: canRead("members:read") },
        { href: `${base}/officials`, label: "Officials", note: "Who holds each office", icon: UserCog, show: canRead("members:read") },
        {
          href: `${base}/join-requests`,
          label: "Requests to join",
          note: "People asking to be added",
          icon: UserPlus,
          show: steward || user?.role === "VILLAGE_AGENT"
        }
      ]
    },
    {
      title: "Records",
      links: [
        { href: `${base}/meetings`, label: "Meetings", note: `${group._count.meetings} recorded`, icon: Activity, show: canRead("meetings:read") },
        { href: `${base}/cycles`, label: "Cycles", note: "Cycles and share-outs", icon: CalendarDays, show: canRead("groups:read") },
        { href: `${base}/votes`, label: "Votes", note: "Resolutions and polls", icon: Vote, show: votingOn && canRead("votes:read") },
        { href: `${base}/documents`, label: "Documents", note: "Certificates and mandates", icon: ClipboardList, show: canRead("documents:read") },
        { href: `${base}/business`, label: "Business", note: "Enterprises the group runs", icon: Building2, show: canRead("visits:read") },
        { href: `${base}/visit-trend`, label: "Visit trend", note: "Scores across field visits", icon: TrendingUp, show: canRead("visits:read") }
      ]
    }
  ]
    // A member's view of their group stays as it was: meetings only.
    .map((section) => ({
      ...section,
      links: section.links.filter((link) => link.show && (!isMember || link.label === "Meetings"))
    }))
    .filter((section) => section.links.length > 0);

  const extraFunds = statement?.suppressed
    ? []
    : group.fundAccounts.filter((account) => EXTRA_FUNDS[account.type] && account.balanceCents !== 0);

  const facts: Array<[string, string | null | undefined]> = [
    ["County", group.county],
    ["Sub-county", group.subCounty],
    ["Location", group.location],
    ["Programme", programmeNames],
    [
      group.agentLinks && group.agentLinks.length > 1 ? "VAs / CBTs" : "VA / CBT",
      group.agentLinks?.length
        ? group.agentLinks.map((link) => `${link.villageAgent.name}${link.isLead && group.agentLinks!.length > 1 ? " (lead)" : ""}`).join(", ")
        : group.villageAgent?.name ?? "Not assigned"
    ],
    ["Contact person", group.contactPersonName],
    // A contact's number is for the people who work with the group.
    ["Contact phone", viewOnly ? null : group.contactPhone],
    ["Meets on", group.meetingDay],
    ["Share value", group.shareValueCents ? formatKes(group.shareValueCents) : null],
    ["Phase", humanizeEnum(group.phase)],
    ["Constitution", group.constitutionVersion]
  ];

  return (
    <>
      <section className="page-heading">
        <div>
          <Link className="inline-back" href={isMember ? "/dashboard" : "/dashboard/groups"}>
            <ArrowLeft size={17} />
            {isMember ? "Dashboard" : "Groups"}
          </Link>
          <p className="eyebrow">Group</p>
          <h2>{group.name}</h2>
          <p>
            {group.code} · {humanizeEnum(group.phase)} · Cycle {cycleNumber}
          </p>
        </div>
        <div className="page-heading-actions">
          <span className="pill blue">
            {latestScore === undefined ? "Credit score: not rated yet" : `Credit score ${latestScore}`}
          </span>
          {canEditGroup ? (
            <button className="button secondary" onClick={() => openEditGroup(group)} type="button">
              <Pencil size={16} />
              Edit
            </button>
          ) : null}
          {canRead("meetings:read") ? (
            <Link className="button secondary" href={`${base}/meetings`}>
              <Activity size={16} />
              Meetings
            </Link>
          ) : null}
        </div>
      </section>

      <ViewOnlyNotice />

      {!isEditOpen && message ? (
        <div className={message.ok ? "notice success" : "notice warning"}>{message.text}</div>
      ) : null}

      {isEditOpen && canEditGroup ? (
        <div className="modal-overlay" role="dialog" aria-modal="true" aria-label="Edit group">
          <button className="modal-backdrop" onClick={closeGroupModal} type="button" aria-label="Close group editor" />
          <section className="data-card credential-modal group-editor-modal">
            <header>
              <div>
                <h3>Edit Group</h3>
                <span>Profile, programs, and VA / CBT.</span>
              </div>
              <button className="icon-button" onClick={closeGroupModal} type="button" aria-label="Close">
                <X size={18} />
              </button>
            </header>
            <form className="credential-form group-editor-form" onSubmit={submitGroup}>
              <div className="group-form-scroll">
                <div className="credential-grid">
                <label className="credential-field">
                  <span>Group name</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
                    required
                    value={form.name}
                  />
                </label>
                <label className="credential-field">
                  <span>Code</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, code: event.target.value }))}
                    required
                    value={form.code}
                  />
                </label>
                <label className="credential-field">
                  <span>County</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, county: event.target.value }))}
                    required
                    value={form.county}
                  />
                </label>
                <label className="credential-field">
                  <span>Sub-county</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, subCounty: event.target.value }))}
                    value={form.subCounty}
                  />
                </label>
                <label className="credential-field">
                  <span>Phase</span>
                  <select
                    onChange={(event) => setForm((current) => ({ ...current, phase: event.target.value }))}
                    value={form.phase}
                  >
                    {groupPhases.map((phase) => (
                      <option key={phase} value={phase}>
                        {humanizeEnum(phase)}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="credential-field">
                  <span>Programs</span>
                  <select
                    multiple
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        programmeIds: Array.from(event.currentTarget.selectedOptions).map((option) => option.value)
                      }))
                    }
                    required
                    value={form.programmeIds}
                  >
                    {programmes.map((programme) => (
                      <option key={programme.id} value={programme.id}>
                        {programme.name}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="credential-field">
                  <span>VAs / CBTs serving this group</span>
                  <select
                    multiple
                    onChange={(event) => {
                      const chosen = Array.from(event.target.selectedOptions, (option) => option.value);
                      setForm((current) => ({
                        ...current,
                        agentIds: chosen,
                        villageAgentId: chosen.includes(current.villageAgentId) ? current.villageAgentId : chosen[0] ?? ""
                      }));
                    }}
                    value={form.agentIds}
                  >
                    {agents.map((agent) => (
                      <option key={agent.id} value={agent.id}>
                        {agent.name}
                      </option>
                    ))}
                  </select>
                  <small>Each one has the same access to the group. Hold Ctrl (or Cmd) to choose more than one.</small>
                </label>
                {form.agentIds.length > 1 ? (
                  <label className="credential-field">
                    <span>Lead VA / CBT</span>
                    <select
                      onChange={(event) => setForm((current) => ({ ...current, villageAgentId: event.target.value }))}
                      value={form.villageAgentId}
                    >
                      {agents
                        .filter((agent) => form.agentIds.includes(agent.id))
                        .map((agent) => (
                          <option key={agent.id} value={agent.id}>
                            {agent.name}
                          </option>
                        ))}
                    </select>
                  </label>
                ) : null}
                <label className="credential-field">
                  <span>Location</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, location: event.target.value }))}
                    value={form.location}
                  />
                </label>
                <label className="credential-field">
                  <span>Meeting day</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, meetingDay: event.target.value }))}
                    value={form.meetingDay}
                  />
                </label>
                <label className="credential-field">
                  <span>Contact person</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, contactPersonName: event.target.value }))}
                    value={form.contactPersonName}
                  />
                </label>
                <label className="credential-field">
                  <span>Contact phone</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, contactPhone: event.target.value }))}
                    value={form.contactPhone}
                  />
                </label>
                <label className="credential-field">
                  <span>Objective</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, objective: event.target.value }))}
                    value={form.objective}
                  />
                </label>
                <label className="credential-field">
                  <span>GPS latitude</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, gpsLatitude: event.target.value }))}
                    step="0.000001"
                    type="number"
                    value={form.gpsLatitude}
                  />
                </label>
                <label className="credential-field">
                  <span>GPS longitude</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, gpsLongitude: event.target.value }))}
                    step="0.000001"
                    type="number"
                    value={form.gpsLongitude}
                  />
                </label>
                <label className="credential-field">
                  <span>GPS radius meters</span>
                  <input
                    max="1000"
                    min="10"
                    onChange={(event) => setForm((current) => ({ ...current, gpsRadiusMeters: event.target.value }))}
                    type="number"
                    value={form.gpsRadiusMeters}
                  />
                </label>
                <label className="credential-field">
                  <span>Share value</span>
                  <input
                    min="1"
                    onChange={(event) => setForm((current) => ({ ...current, shareValue: event.target.value }))}
                    step="1"
                    type="number"
                    value={form.shareValue}
                  />
                </label>
                <label className="credential-field">
                  <span>Max shares per member per meeting</span>
                  <input
                    max="100"
                    min="1"
                    onChange={(event) =>
                      setForm((current) => ({ ...current, maxSharesPerMemberPerMeeting: event.target.value }))
                    }
                    type="number"
                    value={form.maxSharesPerMemberPerMeeting}
                  />
                </label>
                <label className="credential-field">
                  <span>Constitution version</span>
                  <input
                    onChange={(event) => setForm((current) => ({ ...current, constitutionVersion: event.target.value }))}
                    value={form.constitutionVersion}
                  />
                </label>
                <label className="credential-field">
                  <span>Cycle number</span>
                  <input
                    min="1"
                    onChange={(event) => setForm((current) => ({ ...current, cycleNumber: event.target.value }))}
                    type="number"
                    value={form.cycleNumber}
                  />
                </label>
                </div>
                {message ? (
                  <div className={message.ok ? "notice success" : "notice warning"}>{message.text}</div>
                ) : null}
              </div>
              <div className="credential-actions">
                <button className="button" disabled={saving} type="submit">
                  <Pencil size={16} />
                  {saving ? "Saving" : "Save group"}
                </button>
                <button className="button secondary" onClick={closeGroupModal} type="button">
                  Cancel
                </button>
              </div>
            </form>
          </section>
        </div>
      ) : null}

      {showMoney ? (
        <section className="group-money" aria-label="Money this cycle">
          {failed.statement ? (
            <p className="dashboard-notice">The money figures could not be loaded: {failed.statement}</p>
          ) : statement?.suppressed ? (
            <p className="dashboard-notice">
              Figures withheld: this group has fewer than 5 active members, so its money is shown only in
              programme totals (Kenya Data Protection Act, 2019).
            </p>
          ) : statement ? (
            <div className="stat-grid">
              <GroupMoneyCards statement={statement} />
            </div>
          ) : null}
          {extraFunds.length > 0 ? (
            <p className="card-note">
              Also held:{" "}
              {extraFunds.map((account) => `${EXTRA_FUNDS[account.type]} ${formatKes(account.balanceCents)}`).join(" · ")}
            </p>
          ) : null}
        </section>
      ) : null}

      {!isMember ? (
        <section className="data-card">
          <header>
            <h3>About this group</h3>
          </header>
          <div className="card-body fact-grid">
            {facts
              .filter(([, value]) => value !== null && value !== undefined && value !== "")
              .map(([label, value]) => (
                <div className="fact" key={label}>
                  <span className="label">{label}</span>
                  <span className="value">{value}</span>
                </div>
              ))}
          </div>
        </section>
      ) : null}

      {manage.length > 0 ? (
        <section className="data-card group-manage">
          <header>
            <h3>{viewOnly ? "This group's records" : "Manage this group"}</h3>
          </header>
          {manage.map((section) => (
            <div className="group-manage-section" key={section.title}>
              <h4 className="group-manage-title">{section.title}</h4>
              <div className="dashboard-module-grid">
                {section.links.map((link) => {
                  const Icon = link.icon;
                  return (
                    <Link className="dashboard-module-link" href={link.href} key={link.href}>
                      <Icon size={18} />
                      <span>
                        <strong>{link.label}</strong>
                        <em>{link.note}</em>
                      </span>
                    </Link>
                  );
                })}
              </div>
            </div>
          ))}
        </section>
      ) : null}

      {/* The champion is linked by an admin or the group's field agent, at the
          group's meeting; the server checks the agent's caseload. */}
      {(user?.role === "IWL_ADMIN" || user?.role === "VILLAGE_AGENT") && canRead("members:write") ? (
        <ChampionAccessCard
          championName={group.contactPersonName}
          championPhone={group.contactPhone}
          groupId={group.id}
        />
      ) : null}

      {showMembers || showMeetings ? (
        <section className="two-column balanced">
          {showMembers ? (
            <div className="data-card">
              <header>
                <h3>Members</h3>
                <span className="pill">{members.length}</span>
              </header>
              {failed.members ? (
                <p className="card-body card-note">Members could not be loaded: {failed.members}</p>
              ) : (
                <DataTable
                  columns={[
                    { key: "name", header: "Name", value: (member) => member.fullName },
                    { key: "role", header: "Role", value: (member) => humanizeEnum(member.role) },
                    ...(viewOnly
                      ? []
                      : [
                          {
                            key: "pin",
                            header: "PIN",
                            value: (member: Member) => (member.pinSet ? "PIN set" : "Needs PIN"),
                            cell: (member: Member) => (
                              <span className={`pill ${member.pinSet ? "blue" : "gold"}`}>
                                {member.pinSet ? "PIN set" : "Needs PIN"}
                              </span>
                            )
                          }
                        ]),
                    {
                      key: "kyc",
                      header: "KYC",
                      value: (member) => KYC_LABELS[member.kycStatus] ?? humanizeEnum(member.kycStatus),
                      cell: (member) => (
                        <span className={member.kycStatus === "VERIFIED" ? "pill" : member.kycStatus === "REJECTED" ? "pill red" : "pill gold"}>
                          {KYC_LABELS[member.kycStatus] ?? humanizeEnum(member.kycStatus)}
                        </span>
                      )
                    },
                    ...(viewOnly ? [] : [{ key: "phone", header: "Phone", value: (member: Member) => member.phone }])
                  ]}
                  exportName={`${group.code}-members`}
                  filters={[
                    {
                      key: "role",
                      label: "Role",
                      allLabel: "All roles",
                      getValue: (member) => member.role,
                      options: Array.from(new Set(members.map((member) => member.role))).map((value) => ({
                        label: humanizeEnum(value),
                        value
                      }))
                    },
                    {
                      key: "kyc",
                      label: "KYC",
                      allLabel: "All KYC",
                      getValue: (member) => member.kycStatus,
                      options: Object.entries(KYC_LABELS).map(([value, label]) => ({ label, value }))
                    }
                  ]}
                  getRowKey={(member) => member.id}
                  initialPageSize={5}
                  rows={members}
                  title="Members"
                />
              )}
            </div>
          ) : null}

          {showMeetings ? (
            <div className="data-card">
              <header>
                <h3>Meetings</h3>
                <span className="pill">{meetings.length}</span>
              </header>
              {failed.meetings ? (
                <p className="card-body card-note">Meetings could not be loaded: {failed.meetings}</p>
              ) : (
                <DataTable
                  columns={[
                    {
                      key: "meeting",
                      header: "Meeting",
                      value: (meeting) => new Date(meeting.scheduledAt).getTime(),
                      exportValue: (meeting) => `${meeting.title} (${formatDateTime(meeting.scheduledAt)})`,
                      cell: (meeting) => (
                        <>
                          <strong>{meeting.title}</strong>
                          <br />
                          <span>{formatDateTime(meeting.scheduledAt)}</span>
                        </>
                      )
                    },
                    {
                      key: "status",
                      header: "Status",
                      value: (meeting) => meetingStatusLabel(meeting),
                      cell: (meeting) => (
                        <span className={meetingStatusClass(meeting.status, meeting.scheduledAt)}>{meetingStatusLabel(meeting)}</span>
                      )
                    },
                    {
                      key: "keys",
                      header: "Keys",
                      value: (meeting) => meeting.keySubmissions?.length ?? 0,
                      cell: (meeting) => `${meeting.keySubmissions?.length ?? 0} of 3`
                    }
                  ]}
                  defaultSort={{ key: "meeting", direction: "desc" }}
                  exportName={`${group.code}-meetings`}
                  filters={[
                    {
                      key: "status",
                      label: "Status",
                      allLabel: "All statuses",
                      getValue: (meeting) => meeting.status,
                      options: Array.from(new Set(meetings.map((meeting) => meeting.status))).map((value) => ({
                        label: meetingStatusName(value),
                        value
                      }))
                    }
                  ]}
                  getRowKey={(meeting) => meeting.id}
                  initialPageSize={5}
                  rows={meetings}
                  title="Meetings"
                />
              )}
            </div>
          ) : null}
        </section>
      ) : null}

      {showLedger ? (
        <section className="data-card">
          <header>
            <h3>Ledger</h3>
            <span className="pill">
              {ledger.length} {ledger.length === 1 ? "entry" : "entries"}
            </span>
          </header>
          {failed.ledger ? (
            <p className="card-body card-note">The ledger could not be loaded: {failed.ledger}</p>
          ) : (
            <DataTable
              columns={[
                {
                  key: "time",
                  header: "When",
                  value: (entry) => new Date(entry.createdAt).getTime(),
                  exportValue: (entry) => formatDateTime(entry.createdAt),
                  cell: (entry) => formatDateTime(entry.createdAt)
                },
                {
                  key: "type",
                  header: "What",
                  value: (entry) => humanizeEnum(entry.type),
                  cell: (entry) => (
                    <>
                      <strong>{humanizeEnum(entry.type)}</strong>
                      {/* Descriptions often name the member, so oversight roles see the type only. */}
                      {entry.description && !viewOnly ? (
                        <>
                          <br />
                          <span>{entry.description}</span>
                        </>
                      ) : null}
                    </>
                  )
                },
                // Partners, lenders and read-only viewers see group-level money.
                ...(viewOnly
                  ? []
                  : [{ key: "member", header: "Member", value: (entry: LedgerEntry) => entry.member?.fullName ?? "Group" }]),
                {
                  key: "fund",
                  header: "Fund",
                  value: (entry) => (entry.fundAccount?.type ? humanizeEnum(entry.fundAccount.type) : "Not assigned")
                },
                {
                  key: "amount",
                  header: "Amount",
                  value: (entry) => (entry.direction === "DEBIT" ? -entry.amountCents : entry.amountCents),
                  exportValue: (entry) => `${entry.direction === "DEBIT" ? "-" : "+"}${formatKes(entry.amountCents)}`,
                  cell: (entry) =>
                    entry.direction === "DEBIT" ? (
                      <span className="pill gold">Out {formatKes(entry.amountCents)}</span>
                    ) : (
                      <span className="pill">In {formatKes(entry.amountCents)}</span>
                    )
                }
              ]}
              defaultSort={{ key: "time", direction: "desc" }}
              exportName={`${group.code}-ledger`}
              filters={[
                {
                  key: "type",
                  label: "Type",
                  allLabel: "All types",
                  getValue: (entry) => entry.type,
                  options: Array.from(new Set(ledger.map((entry) => entry.type))).map((value) => ({
                    label: humanizeEnum(value),
                    value
                  }))
                },
                {
                  key: "direction",
                  label: "In or out",
                  allLabel: "In and out",
                  getValue: (entry) => entry.direction,
                  options: [
                    { label: "Money in", value: "CREDIT" },
                    { label: "Money out", value: "DEBIT" }
                  ]
                }
              ]}
              getRowKey={(entry) => entry.id}
              initialPageSize={8}
              rows={ledger}
              title="Ledger"
            />
          )}
        </section>
      ) : null}

      {showVotes && votingOn ? (
        <section className="data-card">
          <header>
            <h3>Votes</h3>
            <Vote size={18} />
          </header>
          {failed.votes ? (
            <p className="card-body card-note">Votes could not be loaded: {failed.votes}</p>
          ) : (
            <DataTable
              columns={[
                {
                  key: "resolution",
                  header: "Resolution",
                  value: (vote) => `${humanizeEnum(vote.resolutionType)} ${vote.motion}`,
                  exportValue: (vote) => humanizeEnum(vote.resolutionType),
                  cell: (vote) => (
                    <>
                      <strong>{humanizeEnum(vote.resolutionType)}</strong>
                      <br />
                      <span>{vote.motion}</span>
                    </>
                  )
                },
                {
                  key: "result",
                  header: "Result",
                  value: (vote) => VOTE_RESULTS[vote.result] ?? humanizeEnum(vote.result),
                  cell: (vote) => (
                    <span className={vote.result === "PASSED" ? "pill" : vote.result === "FAILED" ? "pill red" : "pill gold"}>
                      {VOTE_RESULTS[vote.result] ?? humanizeEnum(vote.result)}
                    </span>
                  )
                },
                { key: "yes", header: "Yes", value: (vote) => vote.yesCount },
                { key: "no", header: "No", value: (vote) => vote.noCount },
                {
                  key: "time",
                  header: "When",
                  value: (vote) => new Date(vote.createdAt).getTime(),
                  exportValue: (vote) => formatDateTime(vote.createdAt),
                  cell: (vote) => formatDateTime(vote.createdAt)
                }
              ]}
              defaultSort={{ key: "time", direction: "desc" }}
              exportName={`${group.code}-votes`}
              filters={[
                {
                  key: "resolution",
                  label: "Resolution",
                  allLabel: "All resolutions",
                  getValue: (vote) => vote.resolutionType,
                  options: Array.from(new Set(votes.map((vote) => vote.resolutionType))).map((value) => ({
                    label: humanizeEnum(value),
                    value
                  }))
                },
                {
                  key: "result",
                  label: "Result",
                  allLabel: "All results",
                  getValue: (vote) => vote.result,
                  options: Object.entries(VOTE_RESULTS).map(([value, label]) => ({ label, value }))
                }
              ]}
              getRowKey={(vote) => vote.id}
              initialPageSize={5}
              rows={votes}
              title="Votes"
            />
          )}
        </section>
      ) : null}
    </>
  );
}
