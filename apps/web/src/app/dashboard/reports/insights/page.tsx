"use client";

import React, { useEffect, useState } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  BarChart3,
  Building2,
  CalendarDays,
  ClipboardList,
  FileCheck2,
  FileText,
  GraduationCap,
  HandCoins,
  Landmark,
  Phone,
  ShieldCheck,
  TrendingUp,
  UserCog,
  UsersRound,
  WalletCards
} from "@/lib/theme-icons";
import { ApiClientError, apiFetch, formatKes, humanizeEnum } from "../../../../lib/api";
import { DataTable } from "../../../../components/dashboard/data-table";
import { StatCard } from "../../../../components/dashboard/stat-card";
import type { AgentRow, GroupRow } from "../../../../components/dashboard/types";

interface CreditRating {
  score: number;
  band: string;
  rated: boolean;
}

interface GroupReport {
  generatedAt: string;
  group: {
    id: string;
    name: string;
    code: string;
    county: string;
    phase: string;
    cycleNumber: number;
    memberCount: number;
    meetingCount: number;
  };
  funds: Array<{ fundType: string; balanceCents: number }>;
  ledger: Array<{ type: string; direction: string; totalCents: number; entries: number }>;
  members: Array<{
    id: string;
    fullName: string;
    role: string;
    status: string;
    sharesCents: number;
    socialCents: number;
    finesCents: number;
    loanRepaymentsCents: number;
    loanDisbursementsCents: number;
  }>;
  meetings: {
    byStatus: Array<{ status: string; count: number }>;
    attendanceRate: number | null;
  };
  creditRating: CreditRating | null;
  externalLoans: Array<{ status: string; count: number; totalCents: number }>;
  storeCredit: Array<{ status: string; count: number; totalCents: number }>;
}

interface MemberReport {
  generatedAt: string;
  member: {
    id: string;
    fullName: string;
    role: string;
    status: string;
    joinedAt: string | null;
    group: { id: string; name: string; code: string; cycleNumber: number };
  };
  totals: Array<{ type: string; totalCents: number; entries: number }>;
  attendance: { present: number; total: number; rate: number | null };
  recentEntries: Array<{
    id: string;
    type: string;
    direction: string;
    amountCents: number;
    description: string;
    createdAt: string;
  }>;
}

interface AgentReport {
  generatedAt: string;
  agent: {
    id: string;
    name: string;
    phone: string;
    county: string | null;
    status: string;
    caseloadLimit: number;
    programme: { id: string; name: string } | null;
  };
  summary: { groups: number; rated: number; needSupport: number; totalMembers: number };
  groups: Array<{
    id: string;
    name: string;
    code: string;
    county: string;
    cycleNumber: number;
    memberCount: number;
    meetingCount: number;
    creditRating: CreditRating | null;
    needsSupport: boolean;
  }>;
}

interface MemberOption {
  id: string;
  fullName: string;
  role: string;
}

type InsightTab = "group" | "member" | "agent";

async function fetchOrFallback<T>(path: string, fallback: T): Promise<T> {
  try {
    return await apiFetch<T>(path);
  } catch (error) {
    if (error instanceof ApiClientError && error.status === 403) return fallback;
    throw error;
  }
}

function reportErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiClientError && error.status === 403) {
    return "You do not have permission to view this report.";
  }
  return error instanceof Error ? error.message : fallback;
}

function percentLabel(rate: number | null | undefined) {
  if (rate === null || rate === undefined) return "—";
  return `${Math.round(rate * 100)}%`;
}

function ratingLabel(rating: CreditRating | null | undefined) {
  if (!rating || !rating.rated) return "Unrated";
  return `${rating.band} · ${rating.score}`;
}

function dateLabel(value: string | null | undefined) {
  if (!value) return "Not captured";
  return new Date(value).toLocaleDateString("en-KE");
}

function dateTimeLabel(value: string) {
  return new Date(value).toLocaleString("en-KE");
}

export default function DetailedReportsPage() {
  const [activeTab, setActiveTab] = useState<InsightTab>("group");
  const [groups, setGroups] = useState<GroupRow[]>([]);
  const [agents, setAgents] = useState<AgentRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [selectedGroupId, setSelectedGroupId] = useState("");
  const [groupReport, setGroupReport] = useState<GroupReport | null>(null);
  const [groupLoading, setGroupLoading] = useState(false);
  const [groupError, setGroupError] = useState<string | null>(null);

  const [memberGroupId, setMemberGroupId] = useState("");
  const [memberOptions, setMemberOptions] = useState<MemberOption[]>([]);
  const [membersLoading, setMembersLoading] = useState(false);
  const [selectedMemberId, setSelectedMemberId] = useState("");
  const [memberReport, setMemberReport] = useState<MemberReport | null>(null);
  const [memberLoading, setMemberLoading] = useState(false);
  const [memberError, setMemberError] = useState<string | null>(null);

  const [selectedAgentId, setSelectedAgentId] = useState("");
  const [agentReport, setAgentReport] = useState<AgentReport | null>(null);
  const [agentLoading, setAgentLoading] = useState(false);
  const [agentError, setAgentError] = useState<string | null>(null);

  useEffect(() => {
    let mounted = true;

    async function loadPickers() {
      try {
        const [groupRows, agentRows] = await Promise.all([
          fetchOrFallback<GroupRow[]>("/groups", []),
          fetchOrFallback<AgentRow[]>("/village-agents", [])
        ]);

        if (!mounted) return;
        setGroups(groupRows);
        setAgents(agentRows);
      } catch (loadError) {
        if (mounted) {
          setError(loadError instanceof Error ? loadError.message : "Detailed reports failed");
        }
      } finally {
        if (mounted) setLoading(false);
      }
    }

    loadPickers();
    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    if (!selectedGroupId) {
      setGroupReport(null);
      setGroupError(null);
      return;
    }

    let mounted = true;
    setGroupLoading(true);
    setGroupError(null);

    apiFetch<GroupReport>(`/reports/group/${selectedGroupId}`)
      .then((report) => {
        if (mounted) setGroupReport(report);
      })
      .catch((reportError) => {
        if (mounted) {
          setGroupReport(null);
          setGroupError(reportErrorMessage(reportError, "Group report failed"));
        }
      })
      .finally(() => {
        if (mounted) setGroupLoading(false);
      });

    return () => {
      mounted = false;
    };
  }, [selectedGroupId]);

  useEffect(() => {
    setSelectedMemberId("");
    setMemberReport(null);
    setMemberError(null);

    if (!memberGroupId) {
      setMemberOptions([]);
      return;
    }

    let mounted = true;
    setMembersLoading(true);

    apiFetch<MemberOption[]>(`/groups/${memberGroupId}/members`)
      .then((rows) => {
        if (mounted) setMemberOptions(rows);
      })
      .catch((membersError) => {
        if (mounted) {
          setMemberOptions([]);
          setMemberError(reportErrorMessage(membersError, "Group members failed"));
        }
      })
      .finally(() => {
        if (mounted) setMembersLoading(false);
      });

    return () => {
      mounted = false;
    };
  }, [memberGroupId]);

  useEffect(() => {
    if (!selectedMemberId) {
      setMemberReport(null);
      return;
    }

    let mounted = true;
    setMemberLoading(true);
    setMemberError(null);

    apiFetch<MemberReport>(`/reports/member/${selectedMemberId}`)
      .then((report) => {
        if (mounted) setMemberReport(report);
      })
      .catch((reportError) => {
        if (mounted) {
          setMemberReport(null);
          setMemberError(reportErrorMessage(reportError, "Member report failed"));
        }
      })
      .finally(() => {
        if (mounted) setMemberLoading(false);
      });

    return () => {
      mounted = false;
    };
  }, [selectedMemberId]);

  useEffect(() => {
    if (!selectedAgentId) {
      setAgentReport(null);
      setAgentError(null);
      return;
    }

    let mounted = true;
    setAgentLoading(true);
    setAgentError(null);

    const path =
      selectedAgentId === "self" ? "/reports/agent" : `/reports/agent?agentId=${selectedAgentId}`;

    apiFetch<AgentReport>(path)
      .then((report) => {
        if (mounted) setAgentReport(report);
      })
      .catch((reportError) => {
        if (mounted) {
          setAgentReport(null);
          setAgentError(reportErrorMessage(reportError, "Agent report failed"));
        }
      })
      .finally(() => {
        if (mounted) setAgentLoading(false);
      });

    return () => {
      mounted = false;
    };
  }, [selectedAgentId]);

  if (loading) return <div className="loading-panel">Loading...</div>;
  if (error) return <div className="error">{error}</div>;

  const activeReportLoaded =
    (activeTab === "group" && Boolean(groupReport)) ||
    (activeTab === "member" && Boolean(memberReport)) ||
    (activeTab === "agent" && Boolean(agentReport));

  const memberShareCents =
    memberReport?.totals.find((total) => total.type === "SHARE_PURCHASE")?.totalCents ?? 0;
  const memberLoanRepaidCents =
    memberReport?.totals.find((total) => total.type === "LOAN_REPAYMENT")?.totalCents ?? 0;

  return (
    <>
      <section className="page-heading">
        <div>
          <Link className="inline-back" href="/dashboard/reports">
            <ArrowLeft size={17} />
            Reports
          </Link>
          <p className="eyebrow">Reporting Center</p>
          <h2
            aria-label="Detailed Reports"
            className="has-hint"
            data-hint="Pick a group, member, or agent to open a focused report with balances, movement, attendance, and credit signals."
            tabIndex={0}
          >
            Detailed Reports
          </h2>
        </div>
        <div className="page-heading-actions">
          <div className="segmented view-toggle" role="group" aria-label="Detailed report type">
            <button
              aria-pressed={activeTab === "group"}
              className={activeTab === "group" ? "active" : ""}
              onClick={() => setActiveTab("group")}
              type="button"
            >
              <Building2 size={15} />
              Group
            </button>
            <button
              aria-pressed={activeTab === "member"}
              className={activeTab === "member" ? "active" : ""}
              onClick={() => setActiveTab("member")}
              type="button"
            >
              <UsersRound size={15} />
              Member
            </button>
            <button
              aria-pressed={activeTab === "agent"}
              className={activeTab === "agent" ? "active" : ""}
              onClick={() => setActiveTab("agent")}
              type="button"
            >
              <UserCog size={15} />
              Agent
            </button>
          </div>
          {activeReportLoaded ? (
            <button className="button secondary" onClick={() => window.print()} type="button">
              <FileText size={16} />
              Print
            </button>
          ) : null}
        </div>
      </section>

      {activeTab === "group" ? (
        <>
          <section className="dashboard-filter-row" aria-label="Group report picker">
            <label className="table-filter compact-filter" title="Group">
              <Building2 aria-hidden="true" size={15} />
              <span className="sr-only">Group</span>
              <select
                aria-label="Group"
                onChange={(event) => setSelectedGroupId(event.target.value)}
                value={selectedGroupId}
              >
                <option value="">Choose a group</option>
                {groups.map((group) => (
                  <option key={group.id} value={group.id}>
                    {group.name} ({group.code})
                  </option>
                ))}
              </select>
            </label>
            {groupReport ? (
              <span className="pill">Generated {dateTimeLabel(groupReport.generatedAt)}</span>
            ) : null}
          </section>

          {groupError ? <div className="notice warning">{groupError}</div> : null}
          {groupLoading ? <div className="loading-panel">Loading group report...</div> : null}
          {!groupLoading && !groupError && !selectedGroupId ? (
            <div className="empty-state">Choose a group to open its report.</div>
          ) : null}

          {!groupLoading && groupReport ? (
            <>
              <section className="data-card">
                <header>
                  <div>
                    <h3>{groupReport.group.name}</h3>
                    <span>
                      Code {groupReport.group.code} · {groupReport.group.county} · Cycle{" "}
                      {groupReport.group.cycleNumber}
                    </span>
                  </div>
                  <span className="pill blue">{humanizeEnum(groupReport.group.phase)}</span>
                </header>
              </section>

              <section className="stat-grid">
                <StatCard
                  icon={<UsersRound size={20} />}
                  label="Members"
                  note="Registered in this group"
                  value={String(groupReport.group.memberCount)}
                />
                <StatCard
                  icon={<CalendarDays size={20} />}
                  label="Meetings"
                  note="Recorded so far"
                  value={String(groupReport.group.meetingCount)}
                />
                <StatCard
                  icon={<BarChart3 size={20} />}
                  label="Attendance"
                  note="Average member attendance"
                  value={percentLabel(groupReport.meetings.attendanceRate)}
                />
                <StatCard
                  icon={<ShieldCheck size={20} />}
                  label="Credit rating"
                  note="Latest group score"
                  value={ratingLabel(groupReport.creditRating)}
                />
              </section>

              <section className="data-card">
                <header>
                  <h3>Fund balances</h3>
                  <span className="pill">{groupReport.funds.length}</span>
                </header>
                {groupReport.funds.length === 0 ? (
                  <div className="empty-state">No fund balances yet</div>
                ) : (
                  <div className="table-wrap">
                    <table aria-label="Fund balances">
                      <thead>
                        <tr>
                          <th>Fund</th>
                          <th>Balance</th>
                        </tr>
                      </thead>
                      <tbody>
                        {groupReport.funds.map((fund) => (
                          <tr key={fund.fundType}>
                            <td>{humanizeEnum(fund.fundType)}</td>
                            <td>{formatKes(fund.balanceCents)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </section>

              <section className="data-card">
                <header>
                  <h3>Money movement</h3>
                  <span className="pill">{groupReport.ledger.length}</span>
                </header>
                {groupReport.ledger.length === 0 ? (
                  <div className="empty-state">No ledger activity yet</div>
                ) : (
                  <div className="table-wrap">
                    <table aria-label="Money movement">
                      <thead>
                        <tr>
                          <th>Type</th>
                          <th>Direction</th>
                          <th>Total</th>
                          <th>Entries</th>
                        </tr>
                      </thead>
                      <tbody>
                        {groupReport.ledger.map((line) => (
                          <tr key={`${line.type}-${line.direction}`}>
                            <td>{humanizeEnum(line.type)}</td>
                            <td>{humanizeEnum(line.direction)}</td>
                            <td>{formatKes(line.totalCents)}</td>
                            <td>{line.entries}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </section>

              <section className="data-card">
                <header>
                  <h3>Members</h3>
                  <span className="pill">{groupReport.members.length}</span>
                </header>
                <DataTable
                  columns={[
                    {
                      key: "member",
                      header: "Member",
                      value: (member) => member.fullName,
                      cell: (member) => (
                        <>
                          <strong>{member.fullName}</strong>
                          <br />
                          <span>{humanizeEnum(member.status)}</span>
                        </>
                      )
                    },
                    {
                      key: "role",
                      header: "Role",
                      value: (member) => humanizeEnum(member.role)
                    },
                    {
                      key: "shares",
                      header: "Shares",
                      value: (member) => member.sharesCents,
                      exportValue: (member) => formatKes(member.sharesCents),
                      cell: (member) => formatKes(member.sharesCents),
                      searchable: false
                    },
                    {
                      key: "social",
                      header: "Social fund",
                      value: (member) => member.socialCents,
                      exportValue: (member) => formatKes(member.socialCents),
                      cell: (member) => formatKes(member.socialCents),
                      searchable: false
                    },
                    {
                      key: "fines",
                      header: "Fines",
                      value: (member) => member.finesCents,
                      exportValue: (member) => formatKes(member.finesCents),
                      cell: (member) => formatKes(member.finesCents),
                      searchable: false
                    },
                    {
                      key: "loan-repayments",
                      header: "Loan repayments",
                      value: (member) => member.loanRepaymentsCents,
                      exportValue: (member) => formatKes(member.loanRepaymentsCents),
                      cell: (member) => formatKes(member.loanRepaymentsCents),
                      searchable: false
                    },
                    {
                      key: "loans-taken",
                      header: "Loans taken",
                      value: (member) => member.loanDisbursementsCents,
                      exportValue: (member) => formatKes(member.loanDisbursementsCents),
                      cell: (member) => formatKes(member.loanDisbursementsCents),
                      searchable: false
                    }
                  ]}
                  exportName={`group-report-members-${groupReport.group.code}`}
                  getRowKey={(member) => member.id}
                  rows={groupReport.members}
                  title="Group members"
                />
              </section>

              {groupReport.externalLoans.length > 0 ? (
                <section className="data-card">
                  <header>
                    <h3>External loans</h3>
                    <span className="pill">{groupReport.externalLoans.length}</span>
                  </header>
                  <div className="table-wrap">
                    <table aria-label="External loans by status">
                      <thead>
                        <tr>
                          <th>Status</th>
                          <th>Loans</th>
                          <th>Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        {groupReport.externalLoans.map((line) => (
                          <tr key={line.status}>
                            <td>{humanizeEnum(line.status)}</td>
                            <td>{line.count}</td>
                            <td>{formatKes(line.totalCents)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </section>
              ) : null}

              {groupReport.storeCredit.length > 0 ? (
                <section className="data-card">
                  <header>
                    <h3>Store credit</h3>
                    <span className="pill">{groupReport.storeCredit.length}</span>
                  </header>
                  <div className="table-wrap">
                    <table aria-label="Store credit by status">
                      <thead>
                        <tr>
                          <th>Status</th>
                          <th>Requests</th>
                          <th>Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        {groupReport.storeCredit.map((line) => (
                          <tr key={line.status}>
                            <td>{humanizeEnum(line.status)}</td>
                            <td>{line.count}</td>
                            <td>{formatKes(line.totalCents)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </section>
              ) : null}
            </>
          ) : null}
        </>
      ) : null}

      {activeTab === "member" ? (
        <>
          <section className="dashboard-filter-row" aria-label="Member report picker">
            <label className="table-filter compact-filter" title="Group">
              <Building2 aria-hidden="true" size={15} />
              <span className="sr-only">Group</span>
              <select
                aria-label="Group"
                onChange={(event) => setMemberGroupId(event.target.value)}
                value={memberGroupId}
              >
                <option value="">Choose a group</option>
                {groups.map((group) => (
                  <option key={group.id} value={group.id}>
                    {group.name} ({group.code})
                  </option>
                ))}
              </select>
            </label>
            <label className="table-filter compact-filter" title="Member">
              <UsersRound aria-hidden="true" size={15} />
              <span className="sr-only">Member</span>
              <select
                aria-label="Member"
                disabled={!memberGroupId || membersLoading}
                onChange={(event) => setSelectedMemberId(event.target.value)}
                value={selectedMemberId}
              >
                <option value="">{membersLoading ? "Loading members..." : "Choose a member"}</option>
                {memberOptions.map((member) => (
                  <option key={member.id} value={member.id}>
                    {member.fullName} ({humanizeEnum(member.role)})
                  </option>
                ))}
              </select>
            </label>
            {memberReport ? (
              <span className="pill">Generated {dateTimeLabel(memberReport.generatedAt)}</span>
            ) : null}
          </section>

          {memberError ? <div className="notice warning">{memberError}</div> : null}
          {memberLoading ? <div className="loading-panel">Loading member report...</div> : null}
          {!memberLoading && !memberError && !selectedMemberId ? (
            <div className="empty-state">Choose a group, then a member, to open their report.</div>
          ) : null}

          {!memberLoading && memberReport ? (
            <>
              <section className="data-card">
                <header>
                  <div>
                    <h3>{memberReport.member.fullName}</h3>
                    <span>
                      {humanizeEnum(memberReport.member.role)} · {memberReport.member.group.name} (
                      {memberReport.member.group.code}) · Joined {dateLabel(memberReport.member.joinedAt)}
                    </span>
                  </div>
                  <span className="pill blue">{humanizeEnum(memberReport.member.status)}</span>
                </header>
              </section>

              <section className="stat-grid">
                <StatCard
                  icon={<CalendarDays size={20} />}
                  label="Attendance"
                  note={`${memberReport.attendance.present}/${memberReport.attendance.total} meetings attended`}
                  value={percentLabel(memberReport.attendance.rate)}
                />
                <StatCard
                  icon={<WalletCards size={20} />}
                  label="Total saved"
                  note="Share purchases to date"
                  value={formatKes(memberShareCents)}
                />
                <StatCard
                  icon={<HandCoins size={20} />}
                  label="Loans repaid"
                  note="Repayments to date"
                  value={formatKes(memberLoanRepaidCents)}
                />
              </section>

              <section className="data-card">
                <header>
                  <h3>Totals by type</h3>
                  <span className="pill">{memberReport.totals.length}</span>
                </header>
                {memberReport.totals.length === 0 ? (
                  <div className="empty-state">No transactions yet</div>
                ) : (
                  <div className="table-wrap">
                    <table aria-label="Totals by type">
                      <thead>
                        <tr>
                          <th>Type</th>
                          <th>Total</th>
                          <th>Entries</th>
                        </tr>
                      </thead>
                      <tbody>
                        {memberReport.totals.map((total) => (
                          <tr key={total.type}>
                            <td>{humanizeEnum(total.type)}</td>
                            <td>{formatKes(total.totalCents)}</td>
                            <td>{total.entries}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </section>

              <section className="data-card">
                <header>
                  <h3>Recent transactions</h3>
                  <span className="pill">{memberReport.recentEntries.length}</span>
                </header>
                <DataTable
                  columns={[
                    {
                      key: "date",
                      header: "Date",
                      value: (entry) => new Date(entry.createdAt),
                      exportValue: (entry) => dateTimeLabel(entry.createdAt),
                      cell: (entry) => dateLabel(entry.createdAt),
                      searchable: false
                    },
                    {
                      key: "type",
                      header: "Type",
                      value: (entry) => humanizeEnum(entry.type)
                    },
                    {
                      key: "direction",
                      header: "Direction",
                      value: (entry) => humanizeEnum(entry.direction)
                    },
                    {
                      key: "amount",
                      header: "Amount",
                      value: (entry) => entry.amountCents,
                      exportValue: (entry) => formatKes(entry.amountCents),
                      cell: (entry) => formatKes(entry.amountCents),
                      searchable: false
                    },
                    {
                      key: "description",
                      header: "Description",
                      value: (entry) => entry.description
                    }
                  ]}
                  defaultSort={{ key: "date", direction: "desc" }}
                  exportName={`member-report-transactions-${memberReport.member.id}`}
                  getRowKey={(entry) => entry.id}
                  rows={memberReport.recentEntries}
                  title="Recent transactions"
                />
              </section>
            </>
          ) : null}
        </>
      ) : null}

      {activeTab === "agent" ? (
        <>
          <section className="dashboard-filter-row" aria-label="Agent report picker">
            <label className="table-filter compact-filter" title="Agent">
              <UserCog aria-hidden="true" size={15} />
              <span className="sr-only">Agent</span>
              <select
                aria-label="Agent"
                onChange={(event) => setSelectedAgentId(event.target.value)}
                value={selectedAgentId}
              >
                <option value="">Choose an agent</option>
                <option value="self">My caseload</option>
                {agents.map((agent) => (
                  <option key={agent.id} value={agent.id}>
                    {agent.name}
                  </option>
                ))}
              </select>
            </label>
            {agentReport ? (
              <span className="pill">Generated {dateTimeLabel(agentReport.generatedAt)}</span>
            ) : null}
          </section>

          {agentError ? <div className="notice warning">{agentError}</div> : null}
          {agentLoading ? <div className="loading-panel">Loading agent report...</div> : null}
          {!agentLoading && !agentError && !selectedAgentId ? (
            <div className="empty-state">Choose an agent to open their caseload report.</div>
          ) : null}

          {!agentLoading && agentReport ? (
            <>
              <section className="data-card">
                <header>
                  <div>
                    <h3>{agentReport.agent.name}</h3>
                    <span>
                      <Phone aria-hidden="true" size={13} /> {agentReport.agent.phone} ·{" "}
                      {agentReport.agent.county ?? "No county"} ·{" "}
                      {agentReport.agent.programme?.name ?? "No programme"}
                    </span>
                  </div>
                  <span className="pill blue">{humanizeEnum(agentReport.agent.status)}</span>
                </header>
              </section>

              <section className="stat-grid">
                <StatCard
                  icon={<Building2 size={20} />}
                  label="Groups"
                  note={`Caseload limit ${agentReport.agent.caseloadLimit}`}
                  value={String(agentReport.summary.groups)}
                />
                <StatCard
                  icon={<FileCheck2 size={20} />}
                  label="Rated"
                  note="Groups with a credit rating"
                  value={String(agentReport.summary.rated)}
                />
                <StatCard
                  icon={<ClipboardList size={20} />}
                  label="Need support"
                  note="Groups flagged for follow-up"
                  value={String(agentReport.summary.needSupport)}
                />
                <StatCard
                  icon={<UsersRound size={20} />}
                  label="Total members"
                  note="Across all assigned groups"
                  value={String(agentReport.summary.totalMembers)}
                />
              </section>

              <section className="data-card">
                <header>
                  <h3>Caseload</h3>
                  <span className="pill">{agentReport.groups.length}</span>
                </header>
                <DataTable
                  columns={[
                    {
                      key: "group",
                      header: "Group",
                      value: (group) => group.name,
                      cell: (group) => (
                        <>
                          <strong>{group.name}</strong>
                          <br />
                          <span>Cycle {group.cycleNumber}</span>
                        </>
                      )
                    },
                    {
                      key: "code",
                      header: "Code",
                      value: (group) => group.code
                    },
                    {
                      key: "county",
                      header: "County",
                      value: (group) => group.county
                    },
                    {
                      key: "members",
                      header: "Members",
                      value: (group) => group.memberCount,
                      searchable: false
                    },
                    {
                      key: "meetings",
                      header: "Meetings",
                      value: (group) => group.meetingCount,
                      searchable: false
                    },
                    {
                      key: "rating",
                      header: "Credit rating",
                      value: (group) => (group.creditRating?.rated ? group.creditRating.score : -1),
                      exportValue: (group) => ratingLabel(group.creditRating),
                      cell: (group) => ratingLabel(group.creditRating),
                      searchable: false
                    },
                    {
                      key: "needs-support",
                      header: "Needs support",
                      value: (group) => group.needsSupport,
                      cell: (group) =>
                        group.needsSupport ? (
                          <span className="pill red">Yes</span>
                        ) : (
                          <span className="pill">No</span>
                        ),
                      searchable: false
                    }
                  ]}
                  exportName={`agent-report-caseload-${agentReport.agent.id}`}
                  getRowKey={(group) => group.id}
                  rows={agentReport.groups}
                  title="Agent caseload"
                />
              </section>
            </>
          ) : null}
        </>
      ) : null}
    </>
  );
}
