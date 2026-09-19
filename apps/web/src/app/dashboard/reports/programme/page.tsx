"use client";

import React, { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { ArrowLeft, ShieldCheck } from "@/lib/theme-icons";
import { apiFetch, formatDate, formatKes, humanizeEnum } from "../../../../lib/api";
import { DataTable } from "../../../../components/dashboard/data-table";

/**
 * Programme performance — the report a partner reads.
 *
 * Laid out as a MEAL officer would present it: the reporting period first,
 * then reach (who), group performance (how they are doing), CBT delivery (what
 * the field team did), mentorship and training content (what was taught, and
 * what was not), and outcomes, which live in the Impact report with their
 * method attached.
 *
 * Everything here is group-level. The server never sends a member's name,
 * phone or individual transaction, and withholds money figures for groups too
 * small to hide their members; this page only explains that, it does not
 * enforce it.
 */

interface Count {
  label: string;
  count: number;
}

interface GroupRow {
  id: string;
  name: string;
  code: string;
  county: string;
  phase: string;
  programme: string | null;
  cbt: string | null;
  activeMembers: number;
  meetingsScheduled: number;
  meetingsHeld: number;
  attendanceRate: number | null;
  suppressed: boolean;
  savingsCents: number | null;
  socialFundCents: number | null;
  loansDisbursedCents: number | null;
  activeLoans: number;
  loansPastDue: number;
  loanBookCents: number | null;
  par30Rate: number | null;
  assessmentPercent: number | null;
  assessmentBand: string | null;
  assessedAt: string | null;
  creditBand: string | null;
  creditScore: number | null;
  lastVisitAt: string | null;
  daysSinceVisit: number | null;
}

interface CbtRow {
  id: string;
  name: string;
  groupsAssigned: number;
  groupsVisited: number;
  coverageRate: number | null;
  visits: number;
  confirmedAtGroupRate: number | null;
  assessmentsDone: number;
  mentorshipSessions: number;
  topicsCovered: number;
  mentoringMinutes: number;
  groupRating: number | null;
  ratingsCount: number;
  actionsRaised: number;
  actionsClosed: number;
  actionsOverdue: number;
}

interface TopicRow {
  key: string;
  title: string;
  sessions: number;
  groupsReached: number;
  groupsReachedRate: number | null;
  cbtsDelivering: number;
  minutes: number;
  lastDelivered: string | null;
}

interface ProgrammeReport {
  generatedAt: string;
  period: { from: string; to: string };
  dataProtection: { smallGroupThreshold: number; suppressedGroups: number; statement: string };
  reach: {
    groups: number;
    activeMembers: number;
    counties: Count[];
    phases: Count[];
    programmes: Count[];
    cbts: number;
    groupsWithoutCbt: number;
  };
  performance: {
    totals: {
      suppressed: boolean;
      savingsCents: number | null;
      socialFundCents: number | null;
      loansDisbursedCents: number | null;
      loanBookCents: number | null;
      activeLoans: number;
      loansPastDue: number;
      par30Rate: number | null;
      meetingsHeld: number;
      meetingsScheduled: number;
      attendanceRate: number | null;
      groupsAssessed: number;
      averageAssessmentPercent: number | null;
      groupsNotVisited90Days: number;
    };
    assessmentBands: Array<{ band: string; count: number }>;
    groups: GroupRow[];
  };
  delivery: {
    totals: {
      visits: number;
      groupsVisited: number;
      coverageRate: number | null;
      confirmedAtGroupRate: number | null;
      mentorshipSessions: number;
      mentoringMinutes: number;
      averageGroupRating: number | null;
      actionsRaised: number;
      actionsClosed: number;
      actionsOverdue: number;
    };
    cbts: CbtRow[];
  };
  content: { topicsDelivered: number; topicsAvailable: number; topics: TopicRow[] };
}

const WITHHELD = "Withheld";
const percent = (value: number | null) => (value === null ? "—" : `${value}%`);
const money = (cents: number | null) => (cents === null ? WITHHELD : formatKes(cents));
const hours = (minutes: number) => (minutes >= 60 ? `${Math.round((minutes / 60) * 10) / 10} h` : `${minutes} min`);
const isoDay = (date: Date) => date.toISOString().slice(0, 10);

const SECTIONS = [
  { id: "reach", label: "1. Reach" },
  { id: "performance", label: "2. Group performance" },
  { id: "delivery", label: "3. CBT delivery" },
  { id: "content", label: "4. Mentorship & training" },
  { id: "outcomes", label: "5. Outcomes" }
];

function Fact({ label, value, note }: { label: string; value: string; note?: string }) {
  return (
    <div className="fact">
      <span className="label">{label}</span>
      <span className="value metric-value small">{value}</span>
      {note ? <span className="card-note">{note}</span> : null}
    </div>
  );
}

function CountList({ items, empty, codes = false }: { items: Count[]; empty: string; codes?: boolean }) {
  if (items.length === 0) return <p className="card-note">{empty}</p>;
  return (
    <ul className="programme-count-list">
      {items.map((item) => (
        <li key={item.label}>
          {/* Only enum codes (phases) are re-cased; names are shown as written. */}
          <span>{codes ? humanizeEnum(item.label) : item.label}</span>
          <strong>{item.count}</strong>
        </li>
      ))}
    </ul>
  );
}

export default function ProgrammePerformancePage() {
  const today = useMemo(() => new Date(), []);
  const [from, setFrom] = useState(() => isoDay(new Date(today.getTime() - 90 * 24 * 60 * 60 * 1000)));
  const [to, setTo] = useState(() => isoDay(today));
  const [report, setReport] = useState<ProgrammeReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);
    apiFetch<ProgrammeReport>(`/reports/programme-performance?from=${from}&to=${to}`)
      .then((response) => {
        if (active) setReport(response);
      })
      .catch((loadError: unknown) => {
        if (active) setError(loadError instanceof Error ? loadError.message : "The report could not be loaded.");
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [from, to]);

  function preset(days: number | "year") {
    const end = new Date();
    const start = days === "year" ? new Date(end.getFullYear(), 0, 1) : new Date(end.getTime() - days * 24 * 60 * 60 * 1000);
    setFrom(isoDay(start));
    setTo(isoDay(end));
  }

  const totals = report?.performance.totals;
  const delivery = report?.delivery.totals;
  const undelivered = report?.content.topics.filter((topic) => topic.sessions === 0) ?? [];

  return (
    <section className="dashboard-section programme-report">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href="/dashboard/reports">
            <ArrowLeft size={17} />
            <span>Reports</span>
          </Link>
          <h2>Programme performance</h2>
          <p className="card-note">
            How the groups are performing and what the CBTs are delivering, for the reporting period below.
          </p>
        </div>
      </header>

      <article className="data-card">
        <header>
          <div>
            <h3>Reporting period</h3>
            <span>Activity figures cover this period; balances and ratings are as at today</span>
          </div>
        </header>
        <div className="card-body programme-period">
          <label className="credential-field">
            <span>From</span>
            <input max={to} onChange={(event) => setFrom(event.target.value)} type="date" value={from} />
          </label>
          <label className="credential-field">
            <span>To</span>
            <input min={from} onChange={(event) => setTo(event.target.value)} type="date" value={to} />
          </label>
          <div className="programme-presets">
            <button className="button secondary compact" onClick={() => preset(30)} type="button">
              Last 30 days
            </button>
            <button className="button secondary compact" onClick={() => preset(90)} type="button">
              Last quarter
            </button>
            <button className="button secondary compact" onClick={() => preset("year")} type="button">
              This year
            </button>
          </div>
        </div>
      </article>

      {report ? (
        <p className="notice success programme-dpa">
          <ShieldCheck size={16} /> {report.dataProtection.statement}
          {report.dataProtection.suppressedGroups > 0
            ? ` ${report.dataProtection.suppressedGroups} group${report.dataProtection.suppressedGroups === 1 ? " is" : "s are"} below that size in this report.`
            : ""}
        </p>
      ) : null}

      <nav aria-label="Report sections" className="programme-toc">
        {SECTIONS.map((section) => (
          <a href={`#${section.id}`} key={section.id}>
            {section.label}
          </a>
        ))}
      </nav>

      {error ? <p className="notice warning">{error}</p> : null}
      {loading && !report ? <div className="loading-panel">Loading the report…</div> : null}

      {report && totals && delivery ? (
        <>
          {/* 1. Reach ------------------------------------------------------ */}
          <article className="data-card" id="reach">
            <header>
              <div>
                <h3>1. Reach</h3>
                <span>Who the programme is working with</span>
              </div>
            </header>
            <div className="card-body">
              <div className="fact-grid">
                <Fact label="Groups" value={String(report.reach.groups)} />
                <Fact label="Active members" value={String(report.reach.activeMembers)} />
                <Fact label="Counties" value={String(report.reach.counties.length)} />
                <Fact
                  label="CBTs deployed"
                  note={report.reach.groupsWithoutCbt > 0 ? `${report.reach.groupsWithoutCbt} groups have no CBT` : "Every group has a CBT"}
                  value={String(report.reach.cbts)}
                />
              </div>
              <div className="programme-breakdowns">
                <div>
                  <h4>By county</h4>
                  <CountList empty="No groups." items={report.reach.counties} />
                </div>
                <div>
                  <h4>By phase</h4>
                  <CountList codes empty="No groups." items={report.reach.phases} />
                </div>
                <div>
                  <h4>By programme</h4>
                  <CountList empty="No groups." items={report.reach.programmes} />
                </div>
              </div>
            </div>
          </article>

          {/* 2. Group performance -------------------------------------------- */}
          <article className="data-card" id="performance">
            <header>
              <div>
                <h3>2. Group performance</h3>
                <span>Savings, credit, meetings and assessed capacity — group totals only</span>
              </div>
            </header>
            <div className="card-body">
              <div className="fact-grid">
                <Fact label="Savings mobilised" note="Shares bought, all time" value={money(totals.savingsCents)} />
                <Fact label="Loan book" note={`${totals.activeLoans} active loans`} value={money(totals.loanBookCents)} />
                <Fact
                  label="Portfolio at risk (30 days)"
                  note={`${totals.loansPastDue} loans past due`}
                  value={percent(totals.par30Rate)}
                />
                <Fact label="Social fund" note="Contributions, all time" value={money(totals.socialFundCents)} />
                <Fact
                  label="Meetings held"
                  note={`of ${totals.meetingsScheduled} scheduled in the period`}
                  value={String(totals.meetingsHeld)}
                />
                <Fact label="Attendance" note="Present or late, at meetings held" value={percent(totals.attendanceRate)} />
                <Fact
                  label="Assessed capacity"
                  note={`Average score, ${totals.groupsAssessed} of ${report.reach.groups} groups assessed`}
                  value={percent(totals.averageAssessmentPercent)}
                />
                <Fact
                  label="Not visited in 90 days"
                  note="Groups needing follow-up"
                  value={String(totals.groupsNotVisited90Days)}
                />
              </div>

              {report.performance.assessmentBands.length > 0 ? (
                <div>
                  <h4>Latest assessment band</h4>
                  <CountList
                    empty="No group has been assessed."
                    items={report.performance.assessmentBands.map((band) => ({ label: band.band, count: band.count }))}
                  />
                </div>
              ) : null}

              <DataTable
                columns={[
                  { key: "group", header: "Group", value: (row) => `${row.name} (${row.code})` },
                  { key: "county", header: "County", value: (row) => row.county },
                  { key: "phase", header: "Phase", value: (row) => humanizeEnum(row.phase) },
                  { key: "cbt", header: "CBT", value: (row) => row.cbt ?? "Unassigned" },
                  { key: "members", header: "Members", value: (row) => row.activeMembers },
                  {
                    key: "savings",
                    header: "Savings",
                    value: (row) => row.savingsCents ?? -1,
                    cell: (row) => money(row.savingsCents),
                    exportValue: (row) => (row.savingsCents === null ? WITHHELD : row.savingsCents / 100)
                  },
                  {
                    key: "loanBook",
                    header: "Loan book",
                    value: (row) => row.loanBookCents ?? -1,
                    cell: (row) => money(row.loanBookCents),
                    exportValue: (row) => (row.loanBookCents === null ? WITHHELD : row.loanBookCents / 100)
                  },
                  { key: "par", header: "PAR 30", value: (row) => row.par30Rate ?? -1, cell: (row) => percent(row.par30Rate), exportValue: (row) => row.par30Rate },
                  {
                    key: "meetings",
                    header: "Meetings held",
                    value: (row) => row.meetingsHeld,
                    cell: (row) => `${row.meetingsHeld} / ${row.meetingsScheduled}`
                  },
                  { key: "attendance", header: "Attendance", value: (row) => row.attendanceRate ?? -1, cell: (row) => percent(row.attendanceRate), exportValue: (row) => row.attendanceRate },
                  {
                    key: "assessment",
                    header: "Assessment",
                    value: (row) => row.assessmentPercent ?? -1,
                    cell: (row) =>
                      row.assessmentPercent === null ? "Not assessed" : `${row.assessmentPercent}% · ${row.assessmentBand ?? ""}`,
                    exportValue: (row) => row.assessmentPercent
                  },
                  { key: "credit", header: "Credit", value: (row) => row.creditBand ?? "Not rated" },
                  {
                    key: "lastVisit",
                    header: "Last visit",
                    value: (row) => row.daysSinceVisit ?? 99999,
                    cell: (row) => (row.lastVisitAt ? `${formatDate(row.lastVisitAt)} (${row.daysSinceVisit} d)` : "Never"),
                    exportValue: (row) => (row.lastVisitAt ? formatDate(row.lastVisitAt) : "Never")
                  }
                ]}
                defaultSort={{ key: "group", direction: "asc" }}
                exportName="programme-group-performance"
                filters={[
                  { key: "county", label: "County", allLabel: "All counties", getValue: (row) => row.county },
                  { key: "cbt", label: "CBT", allLabel: "All CBTs", getValue: (row) => row.cbt ?? "Unassigned" },
                  { key: "phase", label: "Phase", allLabel: "All phases", getValue: (row) => humanizeEnum(row.phase) }
                ]}
                getRowKey={(row) => row.id}
                rows={report.performance.groups}
                title="Group scorecard"
              />
            </div>
          </article>

          {/* 3. CBT delivery ------------------------------------------------ */}
          <article className="data-card" id="delivery">
            <header>
              <div>
                <h3>3. CBT delivery</h3>
                <span>Visits, coverage and follow-through by each community-based trainer</span>
              </div>
            </header>
            <div className="card-body">
              <div className="fact-grid">
                <Fact label="Visits" note={`${delivery.groupsVisited} groups visited`} value={String(delivery.visits)} />
                <Fact label="Coverage" note="Groups visited at least once" value={percent(delivery.coverageRate)} />
                <Fact label="Confirmed at the group" note="Device inside the meeting-point radius" value={percent(delivery.confirmedAtGroupRate)} />
                <Fact label="Mentoring time" note={`${delivery.mentorshipSessions} sessions`} value={hours(delivery.mentoringMinutes)} />
                <Fact
                  label="Group rating of mentoring"
                  note="Out of 5, given by the group"
                  value={delivery.averageGroupRating === null ? "—" : String(delivery.averageGroupRating)}
                />
                <Fact
                  label="Action points"
                  note={`${delivery.actionsClosed} closed, ${delivery.actionsOverdue} overdue`}
                  value={String(delivery.actionsRaised)}
                />
              </div>

              <DataTable
                columns={[
                  { key: "cbt", header: "CBT", value: (row) => row.name },
                  { key: "groups", header: "Groups", value: (row) => row.groupsAssigned },
                  {
                    key: "coverage",
                    header: "Visited",
                    value: (row) => row.coverageRate ?? -1,
                    cell: (row) => `${row.groupsVisited} / ${row.groupsAssigned} (${percent(row.coverageRate)})`,
                    exportValue: (row) => row.coverageRate
                  },
                  { key: "visits", header: "Visits", value: (row) => row.visits },
                  { key: "confirmed", header: "At group", value: (row) => row.confirmedAtGroupRate ?? -1, cell: (row) => percent(row.confirmedAtGroupRate), exportValue: (row) => row.confirmedAtGroupRate },
                  { key: "assessments", header: "Assessments", value: (row) => row.assessmentsDone },
                  { key: "sessions", header: "Mentoring sessions", value: (row) => row.mentorshipSessions },
                  { key: "topics", header: "Topics", value: (row) => row.topicsCovered },
                  { key: "minutes", header: "Time", value: (row) => row.mentoringMinutes, cell: (row) => hours(row.mentoringMinutes) },
                  {
                    key: "rating",
                    header: "Group rating",
                    value: (row) => row.groupRating ?? -1,
                    cell: (row) => (row.groupRating === null ? "—" : `${row.groupRating} / 5 (${row.ratingsCount})`),
                    exportValue: (row) => row.groupRating
                  },
                  {
                    key: "actions",
                    header: "Actions",
                    value: (row) => row.actionsRaised,
                    cell: (row) => `${row.actionsRaised} raised · ${row.actionsClosed} closed · ${row.actionsOverdue} overdue`
                  }
                ]}
                defaultSort={{ key: "visits", direction: "desc" }}
                exportName="programme-cbt-delivery"
                getRowKey={(row) => row.id}
                rows={report.delivery.cbts}
                title="CBT delivery"
              />
            </div>
          </article>

          {/* 4. Mentorship and training content ----------------------------- */}
          <article className="data-card" id="content">
            <header>
              <div>
                <h3>4. Mentorship &amp; training</h3>
                <span>What the CBTs coached on, and how widely</span>
              </div>
              <span className="pill">
                {report.content.topicsDelivered} of {report.content.topicsAvailable} topics delivered
              </span>
            </header>
            <div className="card-body">
              {undelivered.length > 0 ? (
                <p className="notice warning">
                  Not covered in this period: {undelivered.map((topic) => topic.title).join(", ")}.
                </p>
              ) : null}
              <DataTable
                columns={[
                  { key: "topic", header: "Topic", value: (row) => row.title },
                  { key: "sessions", header: "Sessions", value: (row) => row.sessions },
                  {
                    key: "groups",
                    header: "Groups reached",
                    value: (row) => row.groupsReached,
                    cell: (row) => `${row.groupsReached} (${percent(row.groupsReachedRate)})`,
                    exportValue: (row) => row.groupsReached
                  },
                  { key: "cbts", header: "CBTs delivering", value: (row) => row.cbtsDelivering },
                  { key: "minutes", header: "Time", value: (row) => row.minutes, cell: (row) => hours(row.minutes) },
                  {
                    key: "last",
                    header: "Last delivered",
                    value: (row) => (row.lastDelivered ? new Date(row.lastDelivered).getTime() : 0),
                    cell: (row) => (row.lastDelivered ? formatDate(row.lastDelivered) : "Not in period"),
                    exportValue: (row) => (row.lastDelivered ? formatDate(row.lastDelivered) : "")
                  }
                ]}
                defaultSort={{ key: "sessions", direction: "desc" }}
                exportName="programme-mentorship-topics"
                getRowKey={(row) => row.key}
                rows={report.content.topics}
                title="Topics"
              />
            </div>
          </article>

          {/* 5. Outcomes ---------------------------------------------------- */}
          <article className="data-card" id="outcomes">
            <header>
              <div>
                <h3>5. Outcomes</h3>
                <span>Did anything change? Baseline against latest, per indicator</span>
              </div>
            </header>
            <div className="card-body">
              <p className="card-note">
                Outcome indicators compare each group&apos;s first assessment with its latest, and carry their own
                definitions, denominators and exclusions so a figure keeps its meaning when it is quoted.
              </p>
              <div>
                <Link className="button secondary" href="/dashboard/reports/meal">
                  Open the impact report
                </Link>
              </div>
            </div>
          </article>

          <p className="card-note">
            Generated {formatDate(report.generatedAt)} from live records for {formatDate(report.period.from)} –{" "}
            {formatDate(report.period.to)}. “Withheld” marks a money figure for a group with fewer than{" "}
            {report.dataProtection.smallGroupThreshold} active members.
          </p>
        </>
      ) : null}
    </section>
  );
}
