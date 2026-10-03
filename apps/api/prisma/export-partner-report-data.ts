import { prisma } from "../src/lib/prisma";
import { buildProgrammePerformanceReport } from "../src/services/programme-performance-report";
import { buildMealReport } from "../src/services/meal-report";
import {
  SMALL_GROUP_THRESHOLD,
  buildGroupStatement,
  redactStatementForRole,
  type GroupStatement
} from "../src/services/vsla-statement-service";

/**
 * Read-only: the data behind a partner report, produced by the system's own
 * report code exactly as a PARTNER_OFFICER would receive it — group-level
 * only, members never named, money withheld for groups under the small-group
 * threshold. Prints one JSON document to stdout.
 *
 *   bash prisma/run-with-service-env.sh prisma/export-partner-report-data.ts PARTNER_NAME="Rain Forest Alliance" FROM=2026-04-01
 *
 * Alongside each statement it recomputes the headline figures straight from
 * the ledger and loan rows (`check`), so the report can say whether the
 * system's numbers agree with the raw records.
 */

const PARTNER_NAME = process.env.PARTNER_NAME ?? "Rain Forest Alliance";
const FROM = new Date(`${process.env.FROM ?? "2026-04-01"}T00:00:00.000Z`);

async function independentCheck(groupId: string, statement: GroupStatement) {
  // Shares bought this cycle, straight from the ledger.
  const cycleId = statement.cycle?.id ?? null;
  const shareRows = await prisma.ledgerEntry.aggregate({
    where: { groupId, type: "SHARE_PURCHASE", ...(cycleId ? { OR: [{ cycleId }, { cycleId: null }] } : {}) },
    _sum: { amountCents: true },
    _count: true
  });
  const loans = await prisma.loan.findMany({
    where: { groupId, status: { notIn: ["REPAID", "WRITTEN_OFF", "CANCELLED"] } },
    select: { principalCents: true }
  });
  return {
    ledgerShareCents: shareRows._sum.amountCents ?? 0,
    ledgerShareEntries: shareRows._count,
    openLoans: loans.length,
    openLoanPrincipalCents: loans.reduce((sum, loan) => sum + loan.principalCents, 0)
  };
}

async function main() {
  const partner = await prisma.partner.findFirstOrThrow({ where: { name: PARTNER_NAME }, select: { id: true, name: true } });
  const programmes = await prisma.programme.findMany({
    where: { partnerId: partner.id, isDemo: false },
    select: { id: true, name: true, publicStatus: true }
  });

  const out: Record<string, unknown> = {
    generatedAt: new Date().toISOString(),
    partner: partner.name,
    period: { from: FROM.toISOString(), to: new Date().toISOString() },
    smallGroupThreshold: SMALL_GROUP_THRESHOLD,
    programmes: [] as unknown[]
  };

  for (const programme of programmes) {
    const groups = await prisma.group.findMany({
      where: { isDemo: false, OR: [{ programmeId: programme.id }, { programmeLinks: { some: { programmeId: programme.id } } }] },
      select: {
        id: true,
        code: true,
        name: true,
        county: true,
        subCounty: true,
        location: true,
        phase: true,
        createdAt: true,
        sourceSystem: true,
        gpsLatitude: true,
        _count: {
          select: { members: true, meetings: true, ledgerEntries: true, visits: true, loans: true, offlineDevices: true }
        }
      },
      orderBy: { code: "asc" }
    });
    const groupIds = groups.map((group) => group.id);

    const rows = [];
    for (const group of groups) {
      const raw = await buildGroupStatement(group.id);
      const statement = raw ? redactStatementForRole(raw, "PARTNER_OFFICER") : null;
      const withheld = !!statement && statement.members.active < SMALL_GROUP_THRESHOLD;
      const lastLedger = await prisma.ledgerEntry.findFirst({
        where: { groupId: group.id },
        orderBy: { createdAt: "desc" },
        select: { createdAt: true }
      });
      const officials = await prisma.memberRoleAssignment.groupBy({
        by: ["role"],
        where: { groupId: group.id, endedAt: null },
        _count: true
      });
      const needs = await prisma.groupNeedsAssessment.findFirst({
        where: { groupId: group.id },
        orderBy: { assessedOn: "asc" },
        select: {
          assessedOn: true,
          totalMembers: true,
          womenMembers: true,
          youthMembers: true,
          membersWithDisability: true,
          completedCycles: true,
          currentCycle: true,
          shareValueCents: true,
          totalSavingsCents: true,
          loanPortfolioCents: true,
          arrearsCents: true,
          par30Cents: true,
          welfareBalanceCents: true,
          smartphoneMembers: true,
          literacyPct: true,
          digitalChampion: true,
          answersJson: true,
          qualityFlagsJson: true,
          visit: { select: { assessment: { select: { percentage: true, bandLabel: true, breakdownJson: true } } } }
        }
      });
      let baseline: Record<string, unknown> | null = null;
      if (needs) {
        const answers = JSON.parse(needs.answersJson) as Record<string, Record<string, string>>;
        // Leaders' genders and age brackets only — never their names.
        const leadership = Object.fromEntries(
          Object.entries(answers.leadership ?? {}).filter(([label]) => /gender|age bracket|years in role/i.test(label))
        );
        const score = needs.visit.assessment ? (JSON.parse(needs.visit.assessment.breakdownJson) as { sections?: Array<{ questions?: Array<{ answered?: boolean; excluded?: boolean }> }> }) : null;
        const questions = (score?.sections ?? []).flatMap((section) => section.questions ?? []);
        const { answersJson: _a, qualityFlagsJson, visit, ...fields } = needs;
        void _a;
        baseline = {
          ...fields,
          leadership,
          governance: answers.governance ?? {},
          records: answers.records ?? {},
          training: answers.training ?? {},
          linkages: Object.fromEntries(Object.entries(answers.linkages ?? {}).filter(([label]) => !/name & relationship/i.test(label))),
          markets: Object.fromEntries(
            Object.entries(answers.markets ?? {}).filter(([label]) => !/specify|buyers|nearest|name/i.test(label))
          ),
          digital: answers.digital ?? {},
          vslaCategory: answers.profile?.["VSLA Category"] ?? null,
          qualityFlags: JSON.parse(qualityFlagsJson),
          scorecard: visit.assessment
            ? {
                percentage: visit.assessment.percentage,
                band: visit.assessment.bandLabel,
                asked: questions.filter((q) => q.answered && !q.excluded).length,
                total: questions.length
              }
            : null
        };
      }
      rows.push({
        code: group.code,
        name: group.name,
        county: group.county,
        subCounty: group.subCounty,
        ward: group.location,
        phase: group.phase,
        registeredAt: group.createdAt,
        source: group.sourceSystem,
        hasGps: group.gpsLatitude !== null,
        counts: group._count,
        lastLedgerEntryAt: lastLedger?.createdAt ?? null,
        officials: Object.fromEntries(officials.map((o) => [o.role, o._count])),
        moneyWithheld: withheld,
        statement: statement && !withheld ? statement : statement ? { members: statement.members, meetings: statement.meetings, cycle: statement.cycle } : null,
        check: statement && !withheld && raw ? await independentCheck(group.id, raw) : null,
        baseline
      });
    }

    (out.programmes as unknown[]).push({
      id: programme.id,
      name: programme.name,
      groups: rows,
      performance: await buildProgrammePerformanceReport(groupIds, { from: FROM, to: new Date() }),
      meal: await buildMealReport(groupIds)
    });
  }

  process.stdout.write(JSON.stringify(out));
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(() => prisma.$disconnect());
