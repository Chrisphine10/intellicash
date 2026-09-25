"use client";

import React, { useEffect, useState } from "react";
import Link from "next/link";
import { AlertTriangle, ArrowLeft, Banknote, FileText, HandCoins, Landmark, ShieldCheck, UsersRound } from "@/lib/theme-icons";
import { apiFetch, formatDate, formatKes } from "../../../../lib/api";
import { DataTable } from "../../../../components/dashboard/data-table";
import { StatCard } from "../../../../components/dashboard/stat-card";
import type { User } from "../../../../components/dashboard/types";
import "../../../styles/financial-reports.css";

/**
 * Financial Reports - the VSLA money picture, one page for every role.
 *
 * - IWL admin: the platform portfolio (optionally one programme).
 * - Partner, lender, read-only: their programmes' portfolio, group-level only.
 * - Group: its own Group Financial Statement.
 * - Field agent: their caseload's portfolio, and each group's statement.
 *
 * Every figure comes from the server's single statement calculation, for the
 * CURRENT CYCLE unless another is chosen - the same numbers the phone and the
 * group see. The server decides what each role may see (no member names for
 * partners, no money for very small groups); this page only lays it out.
 */

interface PortfolioRow {
  groupId: string;
  name: string;
  code: string;
  county: string;
  cycleNumber: number;
  activeMembers: number;
  meetingsHeld: number;
  attendanceRate: number | null;
  suppressed: boolean;
  shareCapitalCents: number | null;
  loanFundCents: number | null;
  socialFundCents: number | null;
  loansOutstandingCents: number | null;
  activeLoans: number | null;
  par30Rate: number | null;
  repaymentRate: number | null;
  interestCents: number | null;
  finesCents: number | null;
  equityCents: number | null;
  returnOnSavings: number | null;
  cashReconciles: boolean;
}

interface PortfolioReport {
  generatedAt: string;
  cycle: "current" | "previous";
  smallGroupThreshold: number;
  totals: {
    groups: number;
    activeMembers: number;
    meetingsHeld: number;
    suppressed: boolean;
    shareCapitalCents?: number;
    loanFundCents?: number;
    socialFundCents?: number;
    loansOutstandingCents?: number;
    activeLoans?: number;
    loansPastDue?: number;
    par30Rate?: number | null;
    repaymentRate?: number | null;
    interestCents?: number;
    finesCents?: number;
    welfarePaidCents?: number;
    shareOutPaidCents?: number;
    equityCents?: number;
    returnOnSavings?: number | null;
    groupsNotReconciling?: number;
  };
  groups: PortfolioRow[];
}

interface Cycle {
  id: string;
  number: number;
  status: string;
  startedAt: string;
  closedAt: string | null;
}

interface MemberRow {
  memberId: string;
  fullName: string;
  role: string;
  status: string;
  sharesCents: number;
  socialCents: number;
  finesCents: number;
  loanOutstandingCents: number;
  projectedShareOutCents: number;
  projectedNetCents: number;
}

interface Statement {
  generatedAt: string;
  asOf: string;
  suppressed: boolean;
  group: { id: string; name: string; code: string; county: string };
  cycle: Cycle;
  cycles: Cycle[];
  members: { active: number; total: number };
  meetings: { held: number; cancelled: number; notHeld: number; attendanceRate: number | null };
  loanFund: {
    openingCents: number;
    sharesCents: number;
    repaymentsCents: number;
    disbursedCents: number;
    shareOutPaidCents: number;
    otherCents: number;
    closingCents: number;
  } | null;
  socialFund: {
    openingCents: number;
    contributionsCents: number;
    finesCents: number;
    welfarePaidCents: number;
    welfareShareOutCents: number;
    otherCents: number;
    closingCents: number;
  } | null;
  loans: {
    activeCount: number;
    pastDueCount: number;
    principalOutstandingCents: number;
    outstandingCents: number;
    par30Cents: number;
    par30Rate: number | null;
    repaymentRate: number | null;
    interestCollectedCents: number;
  } | null;
  income: { interestCents: number; finesCents: number; totalCents: number } | null;
  equity: { totalCents: number; capitalCents: number; returnOnSavings: number | null; valuePer100Cents: number | null } | null;
  cash: { ledgerCents: number; storedCents: number; reconciles: boolean } | null;
  memberRows: MemberRow[];
}

const kes = (cents: number | null | undefined) => (cents === null || cents === undefined ? "Withheld" : formatKes(cents));
const rate = (value: number | null | undefined) => (value === null || value === undefined ? "-" : `${value}%`);

function Lines({ rows }: { rows: Array<[string, number, "in" | "out" | "total" | "plain"]> }) {
  return (
    <table className="statement-lines">
      <tbody>
        {rows.map(([label, cents, kind]) => (
          <tr className={kind === "total" ? "statement-total" : undefined} key={label}>
            <td>{label}</td>
            <td className="numeric">
              {kind === "out" && cents !== 0 ? "- " : kind === "in" && cents !== 0 ? "+ " : ""}
              {formatKes(Math.abs(cents))}
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function GroupStatementView({ groupId, onBack }: { groupId: string; onBack?: () => void }) {
  const [cycleId, setCycleId] = useState<string>("");
  const [statement, setStatement] = useState<Statement | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    setError(null);
    apiFetch<{ statement: Statement }>(`/reports/group/${groupId}${cycleId ? `?cycleId=${cycleId}` : ""}`)
      .then((data) => {
        if (active) setStatement(data.statement);
      })
      .catch((loadError: unknown) => {
        if (active) setError(loadError instanceof Error ? loadError.message : "The statement could not be loaded.");
      });
    return () => {
      active = false;
    };
  }, [groupId, cycleId]);

  if (error) return <div className="notice warning">{error}</div>;
  if (!statement) return <div className="loading-panel">Loading statement...</div>;
  const s = statement;

  return (
    <>
      <header className="page-heading">
        <div>
          {onBack ? (
            <button className="inline-back" onClick={onBack} type="button">
              <ArrowLeft size={17} />
              <span>Portfolio</span>
            </button>
          ) : (
            <Link className="inline-back" href="/dashboard/reports">
              <ArrowLeft size={17} />
              <span>Reports</span>
            </Link>
          )}
          <p className="eyebrow">Group Financial Statement</p>
          <h2>
            {s.group.name} <span className="card-note">({s.group.code})</span>
          </h2>
          <p className="card-note">
            Cycle {s.cycle.number} {s.cycle.status === "ACTIVE" ? "(current)" : "(closed)"} - from {formatDate(s.cycle.startedAt)}
            {s.cycle.closedAt ? ` to ${formatDate(s.cycle.closedAt)}` : ` to ${formatDate(s.asOf)}`}
          </p>
        </div>
        <div className="page-heading-actions no-print">
          <label className="credential-field">
            <span>Cycle</span>
            <select onChange={(event) => setCycleId(event.target.value)} value={cycleId || s.cycle.id}>
              {s.cycles.map((cycle) => (
                <option key={cycle.id} value={cycle.id}>
                  Cycle {cycle.number} {cycle.status === "ACTIVE" ? "(current)" : ""}
                </option>
              ))}
            </select>
          </label>
          <button className="button secondary" onClick={() => window.print()} type="button">
            <FileText size={16} />
            Print / PDF
          </button>
        </div>
      </header>

      {s.suppressed ? (
        <div className="notice warning">
          This group has fewer than 5 active members, so its money figures are not shared outside the group (Kenya Data Protection
          Act, 2019).
        </div>
      ) : null}
      {s.cash && !s.cash.reconciles ? (
        <div className="notice warning">
          <AlertTriangle size={16} /> The stored fund balances ({formatKes(s.cash.storedCents)}) differ from the ledger (
          {formatKes(s.cash.ledgerCents)}) by {formatKes(Math.abs(s.cash.storedCents - s.cash.ledgerCents))}. The statement follows the
          ledger; the difference needs checking.
        </div>
      ) : null}

      <section className="stat-grid">
        <StatCard icon={<Landmark size={20} />} label="Share capital (this cycle)" value={kes(s.loanFund?.sharesCents)} note={`${s.members.active} active members`} />
        <StatCard icon={<HandCoins size={20} />} label="Loans outstanding" value={kes(s.loans?.outstandingCents)} note={s.loans ? `${s.loans.activeCount} active, PAR30 ${rate(s.loans.par30Rate)}` : undefined} />
        <StatCard icon={<Banknote size={20} />} label="Group equity" value={kes(s.equity?.totalCents)} note={s.equity ? `Return on savings ${rate(s.equity.returnOnSavings)}` : undefined} />
        <StatCard icon={<UsersRound size={20} />} label="Meetings held" value={String(s.meetings.held)} note={`Attendance ${rate(s.meetings.attendanceRate)}${s.meetings.cancelled ? `, ${s.meetings.cancelled} cancelled` : ""}`} />
      </section>

      {s.loanFund && s.socialFund && s.loans && s.income && s.equity ? (
        <div className="report-grid statement-grid">
          <article className="data-card">
            <header>
              <h3>Loan fund (savings and lending)</h3>
            </header>
            <Lines
              rows={[
                ["Brought forward", s.loanFund.openingCents, "plain"],
                ["Shares bought", s.loanFund.sharesCents, "in"],
                ["Loan repayments (with interest)", s.loanFund.repaymentsCents, "in"],
                ["Loans given out", s.loanFund.disbursedCents, "out"],
                ["Share-out paid", s.loanFund.shareOutPaidCents, "out"],
                ...(s.loanFund.otherCents !== 0 ? ([["Other movements", s.loanFund.otherCents, "plain"]] as Array<[string, number, "plain"]>) : []),
                ["Cash in the loan fund", s.loanFund.closingCents, "total"]
              ]}
            />
          </article>
          <article className="data-card">
            <header>
              <h3>Social (welfare) fund</h3>
            </header>
            <Lines
              rows={[
                ["Brought forward", s.socialFund.openingCents, "plain"],
                ["Contributions", s.socialFund.contributionsCents, "in"],
                ["Fines", s.socialFund.finesCents, "in"],
                ["Welfare paid out", s.socialFund.welfarePaidCents, "out"],
                ["Welfare share-out", s.socialFund.welfareShareOutCents, "out"],
                ...(s.socialFund.otherCents !== 0 ? ([["Other movements", s.socialFund.otherCents, "plain"]] as Array<[string, number, "plain"]>) : []),
                ["Social fund balance", s.socialFund.closingCents, "total"]
              ]}
            />
          </article>
          <article className="data-card">
            <header>
              <h3>Loan portfolio</h3>
            </header>
            <table className="statement-lines">
              <tbody>
                <tr><td>Active loans</td><td className="numeric">{s.loans.activeCount}</td></tr>
                <tr><td>Past due</td><td className="numeric">{s.loans.pastDueCount}</td></tr>
                <tr><td>Principal still out</td><td className="numeric">{formatKes(s.loans.principalOutstandingCents)}</td></tr>
                <tr><td>Owed with interest</td><td className="numeric">{formatKes(s.loans.outstandingCents)}</td></tr>
                <tr><td>More than 30 days late (PAR30)</td><td className="numeric">{formatKes(s.loans.par30Cents)} ({rate(s.loans.par30Rate)})</td></tr>
                <tr><td>Repaid of what fell due</td><td className="numeric">{rate(s.loans.repaymentRate)}</td></tr>
              </tbody>
            </table>
          </article>
          <article className="data-card">
            <header>
              <h3>Income and equity</h3>
            </header>
            <table className="statement-lines">
              <tbody>
                <tr><td>Interest earned</td><td className="numeric">{formatKes(s.income.interestCents)}</td></tr>
                <tr><td>Fines</td><td className="numeric">{formatKes(s.income.finesCents)}</td></tr>
                <tr className="statement-total"><td>Income this cycle</td><td className="numeric">{formatKes(s.income.totalCents)}</td></tr>
                <tr><td>Members&apos; capital</td><td className="numeric">{formatKes(s.equity.capitalCents)}</td></tr>
                <tr className="statement-total"><td>Group equity (to share out)</td><td className="numeric">{formatKes(s.equity.totalCents)}</td></tr>
                <tr><td>Return on savings</td><td className="numeric">{rate(s.equity.returnOnSavings)}</td></tr>
                <tr>
                  <td>Every KES 100 saved is now worth</td>
                  <td className="numeric">{s.equity.valuePer100Cents === null ? "-" : formatKes(s.equity.valuePer100Cents)}</td>
                </tr>
              </tbody>
            </table>
          </article>
        </div>
      ) : null}

      {s.memberRows.length > 0 ? (
        <section className="data-card">
          <DataTable
            columns={[
              { key: "name", header: "Member", value: (row) => row.fullName },
              { key: "shares", header: "Shares", value: (row) => row.sharesCents, cell: (row) => formatKes(row.sharesCents), exportValue: (row) => row.sharesCents / 100 },
              { key: "social", header: "Social", value: (row) => row.socialCents, cell: (row) => formatKes(row.socialCents), exportValue: (row) => row.socialCents / 100 },
              { key: "fines", header: "Fines", value: (row) => row.finesCents, cell: (row) => formatKes(row.finesCents), exportValue: (row) => row.finesCents / 100 },
              { key: "owed", header: "Loan owed", value: (row) => row.loanOutstandingCents, cell: (row) => formatKes(row.loanOutstandingCents), exportValue: (row) => row.loanOutstandingCents / 100 },
              { key: "share-out", header: "Share-out if closed today", value: (row) => row.projectedShareOutCents, cell: (row) => formatKes(row.projectedShareOutCents), exportValue: (row) => row.projectedShareOutCents / 100 },
              {
                key: "net",
                header: "After loan",
                value: (row) => row.projectedNetCents,
                cell: (row) => <span className={row.projectedNetCents < 0 ? "pill red" : undefined}>{formatKes(row.projectedNetCents)}</span>,
                exportValue: (row) => row.projectedNetCents / 100
              }
            ]}
            exportName={`${s.group.code}-cycle-${s.cycle.number}-members`}
            getRowKey={(row) => row.memberId}
            rows={s.memberRows}
            title="Members"
          />
        </section>
      ) : null}
      <p className="card-note">
        Generated {formatDate(s.generatedAt)}. Figures are this cycle&apos;s; loans are valued with interest up to{" "}
        {formatDate(s.asOf)}.
      </p>
    </>
  );
}

export default function FinancialReportsPage() {
  const [user, setUser] = useState<User | null>(null);
  const [cycle, setCycle] = useState<"current" | "previous">("current");
  const [report, setReport] = useState<PortfolioReport | null>(null);
  const [selectedGroup, setSelectedGroup] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiFetch<User>("/auth/me")
      .then(setUser)
      .catch((loadError: unknown) => setError(loadError instanceof Error ? loadError.message : "Could not load your account."));
  }, []);

  const isGroup = user?.role === "GROUP_ACCOUNT";

  useEffect(() => {
    if (!user || isGroup || user.role === "MEMBER") return;
    let active = true;
    setReport(null);
    apiFetch<PortfolioReport>(`/reports/portfolio-financials?cycle=${cycle}`)
      .then((data) => {
        if (active) setReport(data);
      })
      .catch((loadError: unknown) => {
        if (active) setError(loadError instanceof Error ? loadError.message : "The report could not be loaded.");
      });
    return () => {
      active = false;
    };
  }, [user, isGroup, cycle]);

  if (error) return <div className="notice warning">{error}</div>;
  if (!user) return <div className="loading-panel">Loading...</div>;
  if (user.role === "MEMBER") {
    return (
      <div className="notice">
        Your own statement is in your <Link href="/dashboard/passbook">Passbook</Link>.
      </div>
    );
  }
  if (isGroup && user.groupId) return <GroupStatementView groupId={user.groupId} />;
  if (selectedGroup) return <GroupStatementView groupId={selectedGroup} onBack={() => setSelectedGroup(null)} />;

  const t = report?.totals;
  const groupLevelOnly = ["PARTNER_OFFICER", "LENDER", "READ_ONLY"].includes(user.role);

  return (
    <>
      <header className="page-heading">
        <div>
          <Link className="inline-back" href="/dashboard/reports">
            <ArrowLeft size={17} />
            <span>Reports</span>
          </Link>
          <p className="eyebrow">Portfolio Financial Report</p>
          <h2>{user.role === "IWL_ADMIN" ? "Platform portfolio" : user.role === "VILLAGE_AGENT" ? "My caseload" : "Programme portfolio"}</h2>
          <p className="card-note">
            Savings, lending and welfare across your groups, each group&apos;s {cycle === "current" ? "current" : "last closed"} cycle.
          </p>
        </div>
        <div className="page-heading-actions no-print">
          <div className="segmented" role="group" aria-label="Cycle">
            <button aria-pressed={cycle === "current"} className={cycle === "current" ? "active" : ""} onClick={() => setCycle("current")} type="button">
              Current cycle
            </button>
            <button aria-pressed={cycle === "previous"} className={cycle === "previous" ? "active" : ""} onClick={() => setCycle("previous")} type="button">
              Last closed cycle
            </button>
          </div>
          <button className="button secondary" onClick={() => window.print()} type="button">
            <FileText size={16} />
            Print / PDF
          </button>
        </div>
      </header>

      {groupLevelOnly ? (
        <div className="notice">
          <ShieldCheck size={16} /> Group-level figures only. No member is named, and groups with fewer than {report?.smallGroupThreshold ?? 5} active
          members show no money of their own (Kenya Data Protection Act, 2019). They are still counted in the totals.
        </div>
      ) : null}

      {!report ? (
        <div className="loading-panel">Loading portfolio...</div>
      ) : (
        <>
          <section className="stat-grid">
            <StatCard icon={<UsersRound size={20} />} label="Groups" value={String(t!.groups)} note={`${t!.activeMembers} active members, ${t!.meetingsHeld} meetings held`} />
            <StatCard icon={<Landmark size={20} />} label="Share capital" value={kes(t!.shareCapitalCents)} note={`Loan fund cash ${kes(t!.loanFundCents)}`} />
            <StatCard icon={<HandCoins size={20} />} label="Loans outstanding" value={kes(t!.loansOutstandingCents)} note={`PAR30 ${rate(t!.par30Rate)}, repaid ${rate(t!.repaymentRate)} of what fell due`} />
            <StatCard icon={<Banknote size={20} />} label="Group equity" value={kes(t!.equityCents)} note={`Return on savings ${rate(t!.returnOnSavings)}`} />
          </section>
          {!t!.suppressed ? (
            <section className="stat-grid">
              <StatCard icon={<Banknote size={20} />} label="Interest earned" value={kes(t!.interestCents)} />
              <StatCard icon={<Banknote size={20} />} label="Fines" value={kes(t!.finesCents)} />
              <StatCard icon={<Banknote size={20} />} label="Social fund" value={kes(t!.socialFundCents)} note={`Welfare paid ${kes(t!.welfarePaidCents)}`} />
              <StatCard icon={<Banknote size={20} />} label="Share-out paid" value={kes(t!.shareOutPaidCents)} note={`${t!.loansPastDue ?? 0} loan(s) past due`} />
            </section>
          ) : null}
          {t!.groupsNotReconciling ? (
            <div className="notice warning">
              <AlertTriangle size={16} /> {t!.groupsNotReconciling} group(s) have stored fund balances that differ from their ledger. The report
              follows the ledger.
            </div>
          ) : null}

          <section className="data-card">
            <DataTable
              columns={[
                {
                  key: "group",
                  header: "Group",
                  value: (row) => `${row.name} ${row.code}`,
                  exportValue: (row) => row.name,
                  cell: (row) => (
                    <button className="link-button" onClick={() => setSelectedGroup(row.groupId)} type="button">
                      <strong>{row.name}</strong>
                      <br />
                      <span>
                        {row.code} - {row.county} - cycle {row.cycleNumber}
                      </span>
                    </button>
                  )
                },
                { key: "members", header: "Members", value: (row) => row.activeMembers },
                { key: "meetings", header: "Meetings", value: (row) => row.meetingsHeld },
                { key: "attendance", header: "Attendance", value: (row) => row.attendanceRate, cell: (row) => rate(row.attendanceRate) },
                { key: "shares", header: "Share capital", value: (row) => row.shareCapitalCents, cell: (row) => kes(row.shareCapitalCents), exportValue: (row) => (row.shareCapitalCents === null ? "" : row.shareCapitalCents / 100) },
                { key: "outstanding", header: "Loans owed", value: (row) => row.loansOutstandingCents, cell: (row) => kes(row.loansOutstandingCents), exportValue: (row) => (row.loansOutstandingCents === null ? "" : row.loansOutstandingCents / 100) },
                { key: "par", header: "PAR30", value: (row) => row.par30Rate, cell: (row) => rate(row.par30Rate) },
                { key: "repaid", header: "Repaid", value: (row) => row.repaymentRate, cell: (row) => rate(row.repaymentRate) },
                { key: "social", header: "Social fund", value: (row) => row.socialFundCents, cell: (row) => kes(row.socialFundCents), exportValue: (row) => (row.socialFundCents === null ? "" : row.socialFundCents / 100) },
                { key: "equity", header: "Equity", value: (row) => row.equityCents, cell: (row) => kes(row.equityCents), exportValue: (row) => (row.equityCents === null ? "" : row.equityCents / 100) },
                { key: "return", header: "Return", value: (row) => row.returnOnSavings, cell: (row) => rate(row.returnOnSavings) }
              ]}
              exportName={`portfolio-${cycle}-cycle`}
              getRowKey={(row) => row.groupId}
              rows={report.groups}
              title="Groups"
            />
          </section>
          <p className="card-note">
            Generated {formatDate(report.generatedAt)}. Savings are shares bought this cycle; loans are valued with interest to date;
            repayment counts only loans that have fallen due.
          </p>
        </>
      )}
    </>
  );
}
