import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { PrismaClient } from "@prisma/client";

import { prisma as defaultClient } from "../src/lib/prisma";

/**
 * Checks what is in the database against the workbook it came from.
 *
 * Read-only. An importer reporting "40 created" says what it believed it did,
 * which is not the same as what is there — a row can be written and then
 * updated by something else, a link can be created against the wrong parent,
 * and the count that proves it is the one nobody ran.
 *
 * Every check names the group it failed on, because "39 of 40" is not
 * actionable and "Witiiko Women Group has no programme link" is.
 */

interface Pack {
  groups: {
    key: string;
    name: string;
    county: string;
    subCounty: string;
    ward: string;
    phase: string;
    status: string;
    contactPersonName: string;
    contactPhone: string;
  }[];
  mentorshipVisits: { groupKey: string; visitDate: string; ward: string }[];
  groupEnterprises: { groupKey: string; activity: string }[];
}

const SOURCE_SYSTEM = "FLOURISH_ONBOARDING_2026";
const BREW_STATUS = "Active - Kirinyaga";

export async function validateFlourishImport(client: PrismaClient = defaultClient) {
  const here = path.dirname(fileURLToPath(import.meta.url));
  const pack = JSON.parse(
    fs.readFileSync(path.resolve(here, "data/flourish-onboarding.json"), "utf8")
  ) as Pack;

  const problems: string[] = [];
  const ok: string[] = [];

  const rows = await client.group.findMany({
    where: { sourceSystem: SOURCE_SYSTEM },
    select: {
      id: true,
      name: true,
      code: true,
      county: true,
      subCounty: true,
      location: true,
      phase: true,
      sourceReference: true,
      villageAgentId: true,
      programmeId: true,
      programme: { select: { name: true } },
      programmeLinks: { select: { programmeId: true, programme: { select: { name: true } } } },
      userAccounts: { where: { role: "GROUP_ACCOUNT" }, select: { id: true, email: true } },
      visits: { select: { id: true } },
      enterprises: { select: { id: true } }
    }
  });

  const byKey = new Map(rows.map((row) => [row.sourceReference ?? "", row]));

  // --- every group in the workbook is in the database ---------------------
  const missing = pack.groups.filter((g) => !byKey.has(g.key));
  if (missing.length > 0) {
    problems.push(`${missing.length} groups missing: ${missing.map((g) => g.name).join(", ")}`);
  } else {
    ok.push(`all ${pack.groups.length} groups present`);
  }

  // --- and nothing extra crept in -----------------------------------------
  const packKeys = new Set(pack.groups.map((g) => g.key));
  const extra = rows.filter((row) => !packKeys.has(row.sourceReference ?? ""));
  if (extra.length > 0) {
    problems.push(`${extra.length} imported groups are not in the workbook: ${extra.map((r) => r.name).join(", ")}`);
  }

  // --- the fields match ----------------------------------------------------
  for (const source of pack.groups) {
    const row = byKey.get(source.key);
    if (!row) continue;

    if (row.name !== source.name) problems.push(`${source.name}: name is "${row.name}"`);
    if (row.county !== source.county) {
      problems.push(`${source.name}: county is "${row.county}", workbook says "${source.county}"`);
    }
    if ((row.subCounty ?? "") !== source.subCounty) {
      problems.push(`${source.name}: sub-county is "${row.subCounty}", workbook says "${source.subCounty}"`);
    }
    if ((row.location ?? "") !== source.ward) {
      problems.push(`${source.name}: ward is "${row.location}", workbook says "${source.ward}"`);
    }
    if (row.phase !== source.phase) {
      problems.push(`${source.name}: phase is ${row.phase}, expected ${source.phase}`);
    }

    // The agent, on every group.
    if (!row.villageAgentId) problems.push(`${source.name}: no agent assigned`);

    // The programme, and the join link the console reads.
    const expected = source.status === BREW_STATUS ? "Brew the Coffee" : "Flourish";
    if (!row.programme?.name.includes(expected)) {
      problems.push(
        `${source.name}: on "${row.programme?.name}", expected the ${expected} programme`
      );
    }
    if (row.programmeLinks.length === 0) {
      // The fault that made /dashboard/programmes show zero groups.
      problems.push(`${source.name}: no ProgrammeGroup link — it will not appear on the programme`);
    } else if (row.programmeLinks[0]?.programmeId !== row.programmeId) {
      problems.push(
        `${source.name}: linked to "${row.programmeLinks[0]?.programme.name}" but filed under "${row.programme?.name}"`
      );
    }

    if (row.userAccounts.length === 0) problems.push(`${source.name}: no group login`);
  }

  if (rows.every((r) => r.villageAgentId)) ok.push("every group has an agent");
  if (rows.every((r) => r.programmeLinks.length > 0)) ok.push("every group is linked to its programme");
  if (rows.every((r) => r.userAccounts.length > 0)) ok.push("every group has a login");

  // --- historic records ----------------------------------------------------
  const visits = await client.groupVisit.count({
    where: { clientRequestId: { startsWith: "flourish-" } }
  });
  if (visits !== pack.mentorshipVisits.length) {
    problems.push(`${visits} historic visits recorded, workbook has ${pack.mentorshipVisits.length}`);
  } else {
    ok.push(`all ${visits} historic visits recorded`);
  }

  const enterprises = rows.reduce((sum, row) => sum + row.enterprises.length, 0);
  // Two rows say only "Mwikuria", which fits two groups; they are left out on
  // purpose rather than attached to a guess.
  const expectedEnterprises = pack.groupEnterprises.length - 2;
  if (enterprises !== expectedEnterprises) {
    problems.push(`${enterprises} group enterprises, expected ${expectedEnterprises}`);
  } else {
    ok.push(`${enterprises} group enterprises (2 "Mwikuria" rows deliberately unresolved)`);
  }

  // --- nothing financial was invented --------------------------------------
  const groupIds = rows.map((r) => r.id);
  const [members, ledger, loans, meetings] = await Promise.all([
    client.member.count({ where: { groupId: { in: groupIds } } }),
    client.ledgerEntry.count({ where: { groupId: { in: groupIds } } }),
    client.loan.count({ where: { groupId: { in: groupIds } } }),
    client.meeting.count({ where: { groupId: { in: groupIds } } })
  ]);
  if (members || ledger || loans || meetings) {
    problems.push(
      `financial or roster data exists against imported groups (members ${members}, ledger ${ledger}, loans ${loans}, meetings ${meetings}) — the workbook has none and none should have been created`
    );
  } else {
    ok.push("no members, ledger, loans or meetings invented");
  }

  const byProgramme = new Map<string, number>();
  for (const row of rows) {
    const name = row.programme?.name ?? "(none)";
    byProgramme.set(name, (byProgramme.get(name) ?? 0) + 1);
  }

  return { groups: rows.length, byProgramme: [...byProgramme.entries()], ok, problems };
}

const isDirectRun =
  process.argv[1]?.replace(/\\/g, "/").endsWith("validate-flourish-import.ts") ?? false;

if (isDirectRun) {
  validateFlourishImport()
    .then((result) => {
      console.log("\nFLOURISH import validation");
      console.log("  groups in database :", result.groups);
      for (const [name, count] of result.byProgramme) {
        console.log(`     ${count} on ${name}`);
      }
      console.log();
      for (const line of result.ok) console.log("  OK   ", line);
      if (result.problems.length === 0) {
        console.log("\n  Everything matches the workbook.");
      } else {
        console.log();
        for (const line of result.problems) console.log("  FAIL ", line);
        console.log(`\n  ${result.problems.length} problems.`);
        process.exit(1);
      }
    })
    .catch((error) => {
      console.error(error);
      process.exit(1);
    });
}
