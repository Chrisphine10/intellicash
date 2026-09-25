/**
 * The programme performance pack: what a partner reads to judge whether groups
 * are doing well and what the village agents are actually delivering.
 *
 * Built to the Kenya Data Protection Act's minimisation principles: no member
 * is identified, small groups have their money figures suppressed, and no
 * free text leaves the database.
 */

import { prisma } from "../lib/prisma";
import { latestCreditRating } from "./credit-rating-service";
import { loadLoanPositions } from "./loan-position-service";
import { SMALL_GROUP_THRESHOLD as THRESHOLD, buildGroupStatement, type GroupStatement } from "./vsla-statement-service";

/**
 * The programme performance pack: what a partner reads to judge whether groups
 * are doing well and what the CBTs are actually delivering.
 *
 * Organised the way a MEAL officer would present it to a funder —
 *   1. Reach        who the programme touches
 *   2. Performance  how the groups are doing (the group, never the member)
 *   3. Delivery     what each CBT did: visits, coverage, follow-through
 *   4. Content      what was mentored and trained on, and what was not
 * — with outcomes (baseline against latest) left to the MEAL report, which
 * already carries its own methodology.
 *
 * ## Data protection (Kenya Data Protection Act, 2019)
 *
 * A partner monitoring a programme needs group-level results, not people.
 * So this pack is built to the Act's minimisation and purpose-limitation
 * principles (s.25):
 *
 * - **No member is identified.** No names, phone numbers, IDs, or individual
 *   transactions. Members are only ever counted.
 * - **Small groups are suppressed.** A money figure for a group of three is
 *   close to a figure for each of the three. Below `SMALL_GROUP_THRESHOLD`
 *   active members, per-group money is withheld and marked as such; it still
 *   counts towards portfolio totals, which cover many groups.
 * - **No free text.** Mentorship notes, action-item owners and rating comments
 *   can name people; only their counts leave the database.
 * - **No locations of people.** Visit GPS is reduced to "confirmed at the
 *   group" or not.
 * - CBTs are named: they are programme staff, and their delivery is the thing
 *   being reported — the work, not their private life.
 *
 * Every figure is computed from records on each request. Nothing is cached, so
 * nothing here can be older than the data it describes.
 */

export const SMALL_GROUP_THRESHOLD = THRESHOLD;
const DAY_MS = 24 * 60 * 60 * 1000;

const OPENED_MEETING_STATUSES = ["IN_PROGRESS", "SEALED", "SYNC_CONFLICT"];

/**
 * A meeting was HELD if it was formally opened, or if anything was recorded in
 * it. A group that keeps its book on the phone records attendance and money
 * against a meeting without ever running the open/seal steps on the server, so
 * its meetings stay SCHEDULED — and counting only opened ones reported active
 * groups as having held no meetings at all, with an attendance rate of zero.
 */
function wasHeld(meeting: { status: string; attendance: unknown[]; _count: { ledgerEntries: number } }) {
  return (
    OPENED_MEETING_STATUSES.includes(meeting.status) ||
    meeting.attendance.length > 0 ||
    meeting._count.ledgerEntries > 0
  );
}
const PRESENT_STATUSES = ["PRESENT", "LATE"];

export interface ReportPeriod {
  from: Date;
  to: Date;
}

const pct = (part: number, whole: number) => (whole > 0 ? Math.round((part / whole) * 1000) / 10 : null);

export async function buildProgrammePerformanceReport(groupIds: string[], period: ReportPeriod) {
  const now = new Date();
  const inPeriod = { gte: period.from, lte: period.to };

  const [groups, members, meetings, visits, sessions, ratings, actionsRaised, actionsClosed, openActions, topics] =
    await Promise.all([
      prisma.group.findMany({
        where: { id: { in: groupIds } },
        select: {
          id: true,
          name: true,
          code: true,
          county: true,
          phase: true,
          programme: { select: { name: true } },
          villageAgent: { select: { id: true, name: true } }
        },
        orderBy: { name: "asc" }
      }),
      prisma.member.groupBy({
        by: ["groupId"],
        where: { groupId: { in: groupIds }, status: "ACTIVE" },
        _count: { _all: true }
      }),
      // A cancelled meeting was never going to happen, and one still in the
      // future has not had the chance to: neither counts as scheduled-and-missed.
      prisma.meeting.findMany({
        where: {
          groupId: { in: groupIds },
          scheduledAt: { gte: period.from, lte: period.to < now ? period.to : now },
          status: { not: "CANCELLED" }
        },
        select: {
          groupId: true,
          status: true,
          attendance: { select: { status: true } },
          _count: { select: { ledgerEntries: true } }
        }
      }),
      prisma.groupVisit.findMany({
        where: { groupId: { in: groupIds }, startedAt: inPeriod },
        select: {
          id: true,
          groupId: true,
          villageAgentId: true,
          startedAt: true,
          withinGeofence: true,
          assessment: { select: { percentage: true, bandLabel: true } }
        }
      }),
      prisma.visitMentorshipSession.findMany({
        where: { visit: { groupId: { in: groupIds }, startedAt: inPeriod } },
        select: {
          topicKeySnapshot: true,
          topicTitleSnapshot: true,
          durationMinutes: true,
          createdAt: true,
          visit: { select: { groupId: true, villageAgentId: true, startedAt: true } }
        }
      }),
      prisma.visitMentorshipRating.findMany({
        where: {
          ratedByRole: "GROUP_REPRESENTATIVE",
          visit: { groupId: { in: groupIds }, startedAt: inPeriod }
        },
        select: { score: true, visit: { select: { villageAgentId: true } } }
      }),
      prisma.visitActionItem.findMany({
        where: { groupId: { in: groupIds }, createdAt: inPeriod },
        select: { visit: { select: { villageAgentId: true } } }
      }),
      prisma.visitActionItem.findMany({
        where: { groupId: { in: groupIds }, closedAt: inPeriod },
        select: { visit: { select: { villageAgentId: true } } }
      }),
      prisma.visitActionItem.findMany({
        where: { groupId: { in: groupIds }, status: "OPEN" },
        select: { dueDate: true, visit: { select: { villageAgentId: true } } }
      }),
      prisma.mentorshipTopic.findMany({
        where: { isActive: true },
        select: { key: true, title: true },
        orderBy: { position: "asc" }
      })
    ]);

  // The latest assessment per group, whenever it was — performance is judged
  // on where a group stands now, not only on visits inside the period.
  const latestAssessments = await prisma.groupVisitAssessment.findMany({
    where: { visit: { groupId: { in: groupIds } } },
    select: { percentage: true, bandLabel: true, visit: { select: { groupId: true, startedAt: true } } },
    orderBy: { createdAt: "desc" }
  });
  // Loans are measured from the loan records and their repayments, through the
  // same balance maths the passbook uses. A ratio of repayments to
  // disbursements reads above 100% as soon as interest is paid or a loan
  // predates the ledger, which tells a partner nothing true.
  const positions = await loadLoanPositions(prisma, { groupIds }, now);
  const loanStats = new Map<string, { active: number; outstanding: number; atRisk: number; pastDue: number }>();
  for (const position of positions.values()) {
    for (const { loan, ...balance } of position.loans) {
      if (balance.settled) continue;
      const stats = loanStats.get(loan.groupId) ?? { active: 0, outstanding: 0, atRisk: 0, pastDue: 0 };
      stats.active += 1;
      stats.outstanding += balance.outstandingCents;
      if (loan.dueAt < now) stats.pastDue += 1;
      // PAR30: balance on loans more than 30 days past their due date.
      if (now.getTime() - loan.dueAt.getTime() > 30 * DAY_MS) stats.atRisk += balance.outstandingCents;
      loanStats.set(loan.groupId, stats);
    }
  }

  const lastVisits = await prisma.groupVisit.groupBy({
    by: ["groupId"],
    where: { groupId: { in: groupIds } },
    _max: { startedAt: true }
  });

  const activeMembersByGroup = new Map(members.map((row) => [row.groupId, row._count._all]));
  const lastVisitByGroup = new Map(lastVisits.map((row) => [row.groupId, row._max.startedAt]));
  const latestAssessmentByGroup = new Map<string, (typeof latestAssessments)[number]>();
  for (const row of latestAssessments) {
    if (!latestAssessmentByGroup.has(row.visit.groupId)) latestAssessmentByGroup.set(row.visit.groupId, row);
  }
  // Money comes from each group's statement for its CURRENT CYCLE - the same
  // figures the group, the portfolio report and the phone show. It used to be
  // every ledger row ever, so shares already paid out at share-out were still
  // reported as savings, and the "social fund" ignored fines and welfare paid.
  const statements = new Map<string, GroupStatement>();
  for (const group of groups) {
    const statement = await buildGroupStatement(group.id);
    if (statement) statements.set(group.id, statement);
  }

  // ---- 2. Group performance ------------------------------------------------
  const groupRows = [];
  for (const group of groups) {
    const activeMembers = activeMembersByGroup.get(group.id) ?? 0;
    const suppressed = activeMembers < SMALL_GROUP_THRESHOLD;

    const groupMeetings = meetings.filter((meeting) => meeting.groupId === group.id);
    const held = groupMeetings.filter(wasHeld);
    const attendanceRows = held.flatMap((meeting) => meeting.attendance);
    const present = attendanceRows.filter((row) => PRESENT_STATUSES.includes(row.status)).length;

    const statement = statements.get(group.id);
    const savings = statement?.loanFund.sharesCents ?? 0;
    const social = statement?.socialFund.closingCents ?? 0;
    const disbursed = statement?.loanFund.disbursedCents ?? 0;
    const loans = loanStats.get(group.id) ?? { active: 0, outstanding: 0, atRisk: 0, pastDue: 0 };

    const rating = await latestCreditRating(group.id);
    const assessment = latestAssessmentByGroup.get(group.id);
    const lastVisit = lastVisitByGroup.get(group.id) ?? null;

    groupRows.push({
      id: group.id,
      name: group.name,
      code: group.code,
      county: group.county,
      phase: group.phase,
      programme: group.programme?.name ?? null,
      cbt: group.villageAgent?.name ?? null,
      activeMembers,
      meetingsScheduled: groupMeetings.length,
      meetingsHeld: held.length,
      attendanceRate: pct(present, attendanceRows.length),
      // Suppressed below the threshold: see the file comment.
      suppressed,
      savingsCents: suppressed ? null : savings,
      socialFundCents: suppressed ? null : social,
      loansDisbursedCents: suppressed ? null : disbursed,
      activeLoans: loans.active,
      loansPastDue: loans.pastDue,
      loanBookCents: suppressed ? null : loans.outstanding,
      // A small group's arrears describe one or two people: withheld too.
      par30Rate: suppressed ? null : pct(loans.atRisk, loans.outstanding),
      assessmentPercent: assessment ? Math.round(assessment.percentage * 10) / 10 : null,
      assessmentBand: assessment?.bandLabel ?? null,
      assessedAt: assessment?.visit.startedAt ?? null,
      creditBand: rating?.rated ? rating.band : null,
      creditScore: rating?.rated ? rating.score : null,
      lastVisitAt: lastVisit,
      daysSinceVisit: lastVisit ? Math.floor((now.getTime() - lastVisit.getTime()) / DAY_MS) : null
    });
  }

  // Portfolio totals include suppressed groups: many groups together no longer
  // point at anyone. Withheld only if the whole scope is itself that small.
  const totalActiveMembers = groupRows.reduce((sum, row) => sum + row.activeMembers, 0);
  const totalsSuppressed = totalActiveMembers < SMALL_GROUP_THRESHOLD;
  const sumStatements = (pick: (statement: GroupStatement) => number) =>
    [...statements.values()].reduce((sum, statement) => sum + pick(statement), 0);
  const totalDisbursed = sumStatements((statement) => statement.loanFund.disbursedCents);
  const allLoans = [...loanStats.values()].reduce(
    (sum, stats) => ({
      active: sum.active + stats.active,
      outstanding: sum.outstanding + stats.outstanding,
      atRisk: sum.atRisk + stats.atRisk,
      pastDue: sum.pastDue + stats.pastDue
    }),
    { active: 0, outstanding: 0, atRisk: 0, pastDue: 0 }
  );
  const allHeld = meetings.filter(wasHeld);
  const allAttendance = allHeld.flatMap((meeting) => meeting.attendance);

  const bandCounts = new Map<string, number>();
  for (const row of groupRows) {
    const band = row.assessmentBand ?? "Not yet assessed";
    bandCounts.set(band, (bandCounts.get(band) ?? 0) + 1);
  }

  // ---- 1. Reach -------------------------------------------------------------
  const countBy = (values: string[]) =>
    [...values.reduce((map, value) => map.set(value, (map.get(value) ?? 0) + 1), new Map<string, number>())]
      .map(([label, count]) => ({ label, count }))
      .sort((left, right) => right.count - left.count);

  // ---- 3. CBT delivery ------------------------------------------------------
  const agentIds = [...new Set(groups.map((group) => group.villageAgent?.id).filter((id): id is string => Boolean(id)))];
  const cbtRows = agentIds.map((agentId) => {
    const assigned = groups.filter((group) => group.villageAgent?.id === agentId);
    const agentVisits = visits.filter((visit) => visit.villageAgentId === agentId);
    const agentSessions = sessions.filter((session) => session.visit.villageAgentId === agentId);
    const agentRatings = ratings.filter((rating) => rating.visit.villageAgentId === agentId);
    const visitedGroups = new Set(agentVisits.map((visit) => visit.groupId));
    const overdue = openActions.filter(
      (item) => item.visit.villageAgentId === agentId && item.dueDate && item.dueDate < now
    ).length;

    return {
      id: agentId,
      name: assigned[0]?.villageAgent?.name ?? "",
      groupsAssigned: assigned.length,
      groupsVisited: visitedGroups.size,
      coverageRate: pct(visitedGroups.size, assigned.length),
      visits: agentVisits.length,
      confirmedAtGroupRate: pct(agentVisits.filter((visit) => visit.withinGeofence).length, agentVisits.length),
      assessmentsDone: agentVisits.filter((visit) => visit.assessment).length,
      mentorshipSessions: agentSessions.length,
      topicsCovered: new Set(agentSessions.map((session) => session.topicKeySnapshot)).size,
      mentoringMinutes: agentSessions.reduce((sum, session) => sum + (session.durationMinutes ?? 0), 0),
      groupRating:
        agentRatings.length > 0
          ? Math.round((agentRatings.reduce((sum, rating) => sum + rating.score, 0) / agentRatings.length) * 10) / 10
          : null,
      ratingsCount: agentRatings.length,
      actionsRaised: actionsRaised.filter((item) => item.visit.villageAgentId === agentId).length,
      actionsClosed: actionsClosed.filter((item) => item.visit.villageAgentId === agentId).length,
      actionsOverdue: overdue
    };
  });

  // ---- 4. Mentorship and training content -----------------------------------
  const topicKeys = new Map<string, string>();
  for (const topic of topics) topicKeys.set(topic.key, topic.title);
  for (const session of sessions) {
    if (!topicKeys.has(session.topicKeySnapshot)) topicKeys.set(session.topicKeySnapshot, session.topicTitleSnapshot);
  }
  const topicRows = [...topicKeys.entries()]
    .map(([key, title]) => {
      const delivered = sessions.filter((session) => session.topicKeySnapshot === key);
      const lastDelivered = delivered.reduce<Date | null>(
        (latest, session) => (!latest || session.visit.startedAt > latest ? session.visit.startedAt : latest),
        null
      );
      return {
        key,
        title,
        sessions: delivered.length,
        groupsReached: new Set(delivered.map((session) => session.visit.groupId)).size,
        groupsReachedRate: pct(new Set(delivered.map((session) => session.visit.groupId)).size, groups.length),
        cbtsDelivering: new Set(delivered.map((session) => session.visit.villageAgentId).filter(Boolean)).size,
        minutes: delivered.reduce((sum, session) => sum + (session.durationMinutes ?? 0), 0),
        lastDelivered
      };
    })
    .sort((left, right) => right.sessions - left.sessions || left.title.localeCompare(right.title));

  return {
    generatedAt: now.toISOString(),
    period: { from: period.from.toISOString(), to: period.to.toISOString() },
    dataProtection: {
      smallGroupThreshold: SMALL_GROUP_THRESHOLD,
      suppressedGroups: groupRows.filter((row) => row.suppressed).length,
      statement:
        "Group-level results only. No member is named or individually identifiable, free-text notes are excluded, and money figures for groups with fewer than " +
        `${SMALL_GROUP_THRESHOLD} active members are withheld, in line with the minimisation and purpose-limitation principles of the Kenya Data Protection Act, 2019.`
    },
    reach: {
      groups: groups.length,
      activeMembers: totalActiveMembers,
      counties: countBy(groups.map((group) => group.county)),
      phases: countBy(groups.map((group) => group.phase)),
      programmes: countBy(groups.map((group) => group.programme?.name ?? "Unassigned")),
      cbts: agentIds.length,
      groupsWithoutCbt: groups.filter((group) => !group.villageAgent).length
    },
    performance: {
      totals: {
        suppressed: totalsSuppressed,
        savingsCents: totalsSuppressed ? null : sumStatements((statement) => statement.loanFund.sharesCents),
        socialFundCents: totalsSuppressed ? null : sumStatements((statement) => statement.socialFund.closingCents),
        loansDisbursedCents: totalsSuppressed ? null : totalDisbursed,
        loanBookCents: totalsSuppressed ? null : allLoans.outstanding,
        activeLoans: allLoans.active,
        loansPastDue: allLoans.pastDue,
        par30Rate: pct(allLoans.atRisk, allLoans.outstanding),
        meetingsHeld: allHeld.length,
        meetingsScheduled: meetings.length,
        attendanceRate: pct(
          allAttendance.filter((row) => PRESENT_STATUSES.includes(row.status)).length,
          allAttendance.length
        ),
        groupsAssessed: groupRows.filter((row) => row.assessmentPercent !== null).length,
        averageAssessmentPercent: (() => {
          const scored = groupRows.filter((row) => row.assessmentPercent !== null);
          return scored.length
            ? Math.round((scored.reduce((sum, row) => sum + (row.assessmentPercent ?? 0), 0) / scored.length) * 10) / 10
            : null;
        })(),
        groupsNotVisited90Days: groupRows.filter((row) => row.daysSinceVisit === null || row.daysSinceVisit > 90).length
      },
      assessmentBands: [...bandCounts.entries()].map(([band, count]) => ({ band, count })),
      groups: groupRows
    },
    delivery: {
      totals: {
        visits: visits.length,
        groupsVisited: new Set(visits.map((visit) => visit.groupId)).size,
        coverageRate: pct(new Set(visits.map((visit) => visit.groupId)).size, groups.length),
        confirmedAtGroupRate: pct(visits.filter((visit) => visit.withinGeofence).length, visits.length),
        mentorshipSessions: sessions.length,
        mentoringMinutes: sessions.reduce((sum, session) => sum + (session.durationMinutes ?? 0), 0),
        averageGroupRating:
          ratings.length > 0
            ? Math.round((ratings.reduce((sum, rating) => sum + rating.score, 0) / ratings.length) * 10) / 10
            : null,
        actionsRaised: actionsRaised.length,
        actionsClosed: actionsClosed.length,
        actionsOverdue: openActions.filter((item) => item.dueDate && item.dueDate < now).length
      },
      cbts: cbtRows
    },
    content: {
      topicsDelivered: topicRows.filter((row) => row.sessions > 0).length,
      topicsAvailable: topics.length,
      topics: topicRows
    }
  };
}
