import { prisma } from "../src/lib/prisma";
import { normalisePhone } from "../src/lib/phone";
import { appendAuditEvent } from "../src/services/audit-service";
import { logNeedsAssessmentFlags } from "../src/services/system-issue-service";

/**
 * One-off, October 2026. Two things, both safe to run again:
 *
 * 1. Moves the baseline needs assessments' quality checks ("loan portfolio is
 *    more than ten times total savings") into the system issue log, where the
 *    development team works them — they no longer show on the group page.
 * 2. Registers Chrisphine Otieno Ondiek as a CBT for Rainforest Alliance's two
 *    programmes and credits him with the baseline visits he conducted
 *    (field officer "Chrisphine Ondiek" in the Kobo export). No groups are
 *    assigned and no sign-in is created.
 *
 *   bash prisma/run-with-service-env.sh prisma/oct-2026-issue-log-and-cbt.ts            dry run
 *   bash prisma/run-with-service-env.sh prisma/oct-2026-issue-log-and-cbt.ts --commit   apply
 */

const COMMIT = process.argv.includes("--commit");
const CBT = { name: "Chrisphine Otieno Ondiek", phone: "0757255710", county: "Embu/Kirinyaga" };
const FIELD_OFFICER_AS_RECORDED = /^chrisphine\s+(otieno\s+)?ondiek$/i;
const PARTNER_NAME = "Rain Forest Alliance";

async function backfillFlags() {
  const rows = await prisma.groupNeedsAssessment.findMany({
    select: { id: true, groupId: true, assessedOn: true, fieldOfficer: true, qualityFlagsJson: true, group: { select: { code: true } } }
  });
  let flags = 0;
  for (const row of rows) {
    const list = JSON.parse(row.qualityFlagsJson) as string[];
    flags += list.length;
    if (COMMIT && list.length) {
      await logNeedsAssessmentFlags({
        needsAssessmentId: row.id,
        groupId: row.groupId,
        groupCode: row.group.code,
        assessedOn: row.assessedOn,
        flags: list,
        fieldOfficer: row.fieldOfficer
      });
    }
  }
  console.log(`Quality checks: ${flags} across ${rows.length} assessments ${COMMIT ? "logged" : "would be logged"}.`);
}

async function registerCbt() {
  const phone = normalisePhone(CBT.phone)!;
  const partner = await prisma.partner.findFirstOrThrow({ where: { name: PARTNER_NAME }, select: { id: true } });
  const programmes = await prisma.programme.findMany({
    where: { partnerId: partner.id, isDemo: false },
    select: { id: true, name: true }
  });

  const candidates = await prisma.villageAgent.findMany({ select: { id: true, name: true, phone: true } });
  let agent = candidates.find((row) => normalisePhone(row.phone) === phone) ?? null;
  if (agent && !/chrisphine/i.test(agent.name)) {
    throw new Error(`Phone already belongs to another agent (${agent.id}); refusing to reuse it.`);
  }
  console.log(agent ? `CBT exists: ${agent.id}` : `CBT ${CBT.name} ${COMMIT ? "created" : "would be created"}.`);

  if (COMMIT && !agent) {
    agent = await prisma.villageAgent.create({
      data: { name: CBT.name, phone, county: CBT.county, partnerId: partner.id, status: "ACTIVE" },
      select: { id: true, name: true, phone: true }
    });
    await appendAuditEvent({
      entityType: "VillageAgent",
      entityId: agent.id,
      type: "VILLAGE_AGENT_CREATED",
      payload: { name: CBT.name, partnerId: partner.id, programmes: programmes.map((p) => p.id), source: "oct-2026-issue-log-and-cbt" }
    });
  }

  for (const programme of programmes) {
    console.log(`  programme ${programme.name}`);
    if (COMMIT && agent) {
      await prisma.villageAgentProgramme.upsert({
        where: { villageAgentId_programmeId: { villageAgentId: agent.id, programmeId: programme.id } },
        create: { villageAgentId: agent.id, programmeId: programme.id },
        update: {}
      });
    }
  }

  const visits = (
    await prisma.groupNeedsAssessment.findMany({
      select: { visitId: true, fieldOfficer: true, group: { select: { code: true } }, visit: { select: { villageAgentId: true } } }
    })
  ).filter((row) => FIELD_OFFICER_AS_RECORDED.test((row.fieldOfficer ?? "").trim()));
  for (const row of visits) {
    const already = agent && row.visit.villageAgentId === agent.id;
    if (row.visit.villageAgentId && !already) {
      console.log(`  ${row.group.code}: visit already credited to another agent — left alone`);
      continue;
    }
    console.log(`  ${row.group.code}: baseline visit ${already ? "already credited" : COMMIT ? "credited" : "would be credited"}`);
    if (COMMIT && agent && !already) {
      await prisma.groupVisit.update({ where: { id: row.visitId }, data: { villageAgentId: agent.id } });
    }
  }
}

async function main() {
  console.log(COMMIT ? "Mode: COMMIT" : "Mode: dry run (pass --commit to apply)");
  await backfillFlags();
  await registerCbt();
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(() => prisma.$disconnect());
