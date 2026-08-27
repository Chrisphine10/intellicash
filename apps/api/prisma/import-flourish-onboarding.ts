import fs from "node:fs";
import path from "node:path";
import bcrypt from "bcryptjs";
import { randomBytes } from "node:crypto";
import { fileURLToPath } from "node:url";
import { PrismaClient } from "@prisma/client";

import { prisma as defaultClient } from "../src/lib/prisma";

/**
 * Imports the FLOURISH onboarding pack.
 *
 * Reads `data/flourish-onboarding.json`, which is extracted from the workbook
 * and committed, so what this writes can be reviewed without opening Excel and a
 * re-run cannot silently pick up a different spreadsheet.
 *
 * **Idempotent.** Every row is keyed on `sourceSystem` + `sourceReference`, so
 * running it twice updates rather than duplicates. That matters more than usual
 * here: a duplicated group is a second set of books for the same women.
 *
 * ## What it deliberately does not create
 *
 * Sheets 3, 4, 5 and 6 of the workbook are blank BY DESIGN, and its README says
 * so in terms: "Do not enter estimated values — opening balances must be
 * validated and minuted." So there are no members, no PINs, no share values, no
 * opening balances, no savings and no loans here.
 *
 * That is not an omission to be tidied up later by guessing. A group's opening
 * balance is the number every future balance is measured from; an invented one
 * is wrong for the whole life of the group, and it is wrong about other people's
 * money. The groups arrive ready to be configured at their own General Assembly,
 * which is where those figures are supposed to come from.
 */

interface GroupRow {
  key: string;
  name: string;
  county: string;
  countyCode: string;
  subCounty: string;
  ward: string;
  phase: string;
  status: string;
  category: string;
  contactPersonName: string;
  contactPhone: string;
  estimatedMembers: number | null;
  totalMembers: number | null;
  mentor: string;
  note: string;
}

interface VisitRow {
  groupKey: string;
  groupName: string;
  ward: string;
  primarySupport: string;
  mentor: string;
  visitDate: string;
  visitNumber: string;
  actionAgreed: string;
  nextPlan: string;
  signature: string;
}

interface EnterpriseRow {
  groupKey: string;
  groupName: string;
  activity: string;
  valueChain: string;
  status: string;
  supportProvided: string;
}

interface Pack {
  groups: GroupRow[];
  mentorshipVisits: VisitRow[];
  groupEnterprises: EnterpriseRow[];
}

const SOURCE_SYSTEM = "FLOURISH_ONBOARDING_2026";

/** Which programme these groups belong to. Overridable without a code change. */
const PROGRAMME_NAME = process.env.FLOURISH_PROGRAMME ?? "Brew the Coffee: Coffee to Stay";

/**
 * The agent every imported group is assigned to.
 *
 * The workbook names Caleb Ogara as the owner of the Kirinyaga reconciliation
 * but carries no phone or email for him, and `VillageAgent.phone` is required.
 * Both are therefore overridable, and the run prints loudly when it has had to
 * fall back — a placeholder number in a system where the phone is how somebody
 * signs in is worth seeing rather than discovering.
 */
const AGENT_NAME = process.env.FLOURISH_AGENT_NAME ?? "Caleb Ogara";
const AGENT_PHONE = process.env.FLOURISH_AGENT_PHONE ?? "";
const AGENT_EMAIL = process.env.FLOURISH_AGENT_EMAIL ?? "caleb.ogara@intellicash.co.ke";

function normalise(value: string) {
  return value
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

/**
 * Joins the sheets, which spell the same group three different ways.
 *
 * The register writes "Smart Coffee Self Help Group"; the mentorship log writes
 * "Smart Coffee SHG"; the enterprise sheet writes "Tupande Joy" for "Tupande Joy
 * Farmers' & Bee Keeping Group". Exact matching drops seven of twenty-four
 * historic visits on the floor without saying so, which is the failure worth
 * designing against.
 *
 * Distinguishing words are kept. Only the words that appear in nearly every
 * group name are ignored, and "women", "men" and "youth" are NOT among them —
 * those are how two otherwise identical names are told apart.
 *
 * Returns null when more than one group fits. "Mwikuria" matches both
 * "Mwikuria SHG" and "Mwikuria SHG II", and attaching a business to the wrong
 * one is worse than reporting it unmatched for somebody to resolve.
 */
const GENERIC_WORDS = new Set(["shg", "self", "help", "group", "sacco", "the"]);

function tokens(key: string): Set<string> {
  return new Set(key.split("-").filter((word) => word && !GENERIC_WORDS.has(word)));
}

function findGroupKey(
  keys: string[],
  candidate: string,
  /** Ward from the same row, used only to break a genuine tie. */
  ward?: string,
  wardByKey?: Map<string, string>
): string | null {
  if (keys.includes(candidate)) return candidate;

  const wanted = tokens(candidate);
  if (wanted.size === 0) return null;

  const matches = keys.filter((key) => {
    const have = tokens(key);
    return [...wanted].every((word) => have.has(word));
  });

  if (matches.length === 1) return matches[0] as string;

  /*
   * Two groups can share every distinguishing word — "Mwikuria Self Help Group"
   * and "Mwikuria Self Help Group (II)" both reduce to {mwikuria}. Where the
   * source row names a ward, that is evidence rather than a preference: the
   * July mentorship visit to "Mwikuria SHG" is recorded in Kithimu, and only one
   * of the two groups is in Kithimu.
   *
   * Still all-or-nothing. If the ward does not single one out, the row stays
   * unmatched and is reported.
   */
  if (ward && wardByKey) {
    const byWard = matches.filter(
      (key) => (wardByKey.get(key) ?? "").toLowerCase() === ward.toLowerCase()
    );
    if (byWard.length === 1) return byWard[0] as string;
  }

  return null;
}

const MONTHS = [
  "jan", "feb", "mar", "apr", "may", "jun",
  "jul", "aug", "sep", "oct", "nov", "dec"
];

/**
 * "22 Jul 2026", and also "Jul 2026" — eight rows in the log carry no day.
 *
 * Anchored at UTC midday, NOT `Date.parse`. That reads a bare date as midnight
 * in the SERVER's zone, so "8 Jul 2026" imported on an EAT machine is stored as
 * 7 Jul 21:00 UTC and every UTC day-bucketed report files the visit on the
 * seventh. The workbook says the eighth and so does the paper form. A date off a
 * paper form has no time of day, so midday it is: far enough from both midnight
 * boundaries to survive being rendered anywhere from UTC-11 to UTC+12.
 *
 * A month with no day is imported rather than dropped — a visit that happened is
 * a fact, and the day is the only part missing — but the record says so, because
 * a made-up first-of-the-month rendered like every other date is a precision
 * nobody actually has.
 */
function parseVisitDate(value: string): { date: Date; dayKnown: boolean } | null {
  const text = value.trim();

  const full = /^(\d{1,2})\s+([A-Za-z]{3})[a-z]*\s+(\d{4})$/.exec(text);
  if (full) {
    const month = MONTHS.indexOf((full[2] as string).toLowerCase());
    const day = Number(full[1]);
    if (month === -1 || day < 1 || day > 31) return null;
    return {
      date: new Date(Date.UTC(Number(full[3]), month, day, 12, 0, 0)),
      dayKnown: true
    };
  }

  const monthOnly = /^([A-Za-z]{3})[a-z]*\s+(\d{4})$/.exec(text);
  if (monthOnly) {
    const month = MONTHS.indexOf((monthOnly[1] as string).toLowerCase());
    if (month === -1) return null;
    return { date: new Date(Date.UTC(Number(monthOnly[2]), month, 1, 12, 0, 0)), dayKnown: false };
  }

  return null;
}

export async function importFlourishOnboarding(client: PrismaClient = defaultClient) {
  // `__dirname` is not defined under the ESM loader tsx uses. `fileURLToPath`
  // rather than reading `.pathname`, which leaves %20 in a path containing a
  // space and fails on this very repository.
  const here = path.dirname(fileURLToPath(import.meta.url));
  const file = path.resolve(here, "data/flourish-onboarding.json");
  const pack = JSON.parse(fs.readFileSync(file, "utf8")) as Pack;

  const notes: string[] = [];
  const summary = {
    groupsCreated: 0,
    groupsUpdated: 0,
    visitsCreated: 0,
    mentorshipSessions: 0,
    actionItems: 0,
    enterprisesCreated: 0,
    visitsWithMonthOnlyDate: 0,
    unmatchedEnterprises: [] as string[],
    unmatchedVisits: [] as string[]
  };

  const programme = await client.programme.findFirst({
    where: { name: PROGRAMME_NAME },
    select: { id: true, name: true, partnerId: true }
  });
  if (!programme) {
    throw new Error(
      `No programme named "${PROGRAMME_NAME}". Set FLOURISH_PROGRAMME to one that exists — ` +
        "guessing would file 40 groups under the wrong project."
    );
  }

  // ---------------------------------------------------------------------
  // The agent
  // ---------------------------------------------------------------------
  let agent = await client.villageAgent.findFirst({
    where: { name: { contains: AGENT_NAME.split(" ")[0] as string } },
    select: { id: true, name: true, phone: true, partnerId: true }
  });

  if (!agent) {
    const phone = AGENT_PHONE || `254700000${Math.floor(100 + Math.random() * 899)}`;
    if (!AGENT_PHONE) {
      notes.push(
        `No phone supplied for ${AGENT_NAME}; created with placeholder ${phone}. ` +
          "Correct it before he tries to sign in — the phone is the sign-in identifier."
      );
    }
    agent = await client.villageAgent.create({
      data: {
        name: AGENT_NAME,
        phone,
        email: AGENT_EMAIL,
        county: "Kirinyaga",
        partnerId: programme.partnerId,
        sourceSystem: SOURCE_SYSTEM
      },
      select: { id: true, name: true, phone: true, partnerId: true }
    });
    notes.push(`Created VA record for ${AGENT_NAME}.`);
  } else {
    notes.push(`Reusing existing VA record for ${agent.name}.`);
  }

  // Serving this programme, so the agent's own caseload resolves.
  await client.villageAgentProgramme.upsert({
    where: {
      villageAgentId_programmeId: { villageAgentId: agent.id, programmeId: programme.id }
    },
    create: { villageAgentId: agent.id, programmeId: programme.id },
    update: {}
  });

  // ---------------------------------------------------------------------
  // The agent's login
  // ---------------------------------------------------------------------
  let agentPassword: string | null = null;
  const existingUser = await client.user.findFirst({
    where: { villageAgentId: agent.id },
    select: { id: true, email: true }
  });

  if (!existingUser) {
    // Generated, shown once, never a shared constant. Anything reused across
    // environments ends up in a chat log and then in production.
    agentPassword = randomBytes(12).toString("base64url");
    await client.user.create({
      data: {
        name: agent.name,
        email: AGENT_EMAIL,
        phone: agent.phone,
        passwordHash: await bcrypt.hash(agentPassword, 12),
        role: "VILLAGE_AGENT",
        villageAgentId: agent.id
      }
    });
    notes.push(`Created agent login ${AGENT_EMAIL}.`);
  } else {
    notes.push(`Agent login already exists (${existingUser.email}); left untouched.`);
  }

  // ---------------------------------------------------------------------
  // Groups
  // ---------------------------------------------------------------------
  const codeCounters = new Map<string, number>();
  const existingCodes = new Set(
    (await client.group.findMany({ select: { code: true } })).map((row) => row.code)
  );

  function nextCode(countyCode: string) {
    let n = codeCounters.get(countyCode) ?? 0;
    let code: string;
    do {
      n += 1;
      code = `IWL-${countyCode}-${String(n).padStart(4, "0")}`;
    } while (existingCodes.has(code));
    codeCounters.set(countyCode, n);
    existingCodes.add(code);
    return code;
  }

  const groupIdByKey = new Map<string, string>();

  for (const row of pack.groups) {
    const existing = await client.group.findFirst({
      where: { sourceSystem: SOURCE_SYSTEM, sourceReference: row.key },
      select: { id: true }
    });

    const data = {
      name: row.name,
      county: row.county,
      subCounty: row.subCounty || null,
      location: row.ward || null,
      phase: row.phase,
      programmeId: programme.id,
      villageAgentId: agent.id,
      contactPersonName: row.contactPersonName || null,
      contactPhone: row.contactPhone || null,
      // The workbook's own reservations travel with the group rather than being
      // dropped: a conflicting county or an unconfirmed status is something
      // whoever opens this record needs to know.
      onboardingFeedback:
        [
          row.status ? `Status: ${row.status}` : "",
          row.category ? `Category: ${row.category}` : "",
          row.mentor ? `Mentor: ${row.mentor}` : "",
          row.totalMembers ? `Baseline members: ${row.totalMembers}` : "",
          row.estimatedMembers ? `Estimated members: ${row.estimatedMembers}` : "",
          row.note ? `Note: ${row.note}` : ""
        ]
          .filter(Boolean)
          .join(" | ") || null,
      sourceSystem: SOURCE_SYSTEM,
      sourceReference: row.key
    };

    if (existing) {
      await client.group.update({ where: { id: existing.id }, data });
      groupIdByKey.set(row.key, existing.id);
      summary.groupsUpdated += 1;
    } else {
      const created = await client.group.create({
        data: { ...data, code: nextCode(row.countyCode) },
        select: { id: true }
      });
      groupIdByKey.set(row.key, created.id);
      summary.groupsCreated += 1;
    }
  }

  const keys = [...groupIdByKey.keys()];
  const wardByKey = new Map(pack.groups.map((row) => [row.key, row.ward]));

  // ---------------------------------------------------------------------
  // Group enterprises
  // ---------------------------------------------------------------------
  for (const row of pack.groupEnterprises) {
    const key = findGroupKey(keys, row.groupKey);
    if (!key) {
      summary.unmatchedEnterprises.push(row.groupName);
      continue;
    }
    const groupId = groupIdByKey.get(key) as string;
    const name = row.activity || "Group enterprise";

    const existing = await client.groupEnterprise.findFirst({
      where: { groupId, name },
      select: { id: true }
    });
    if (existing) continue;

    await client.groupEnterprise.create({
      data: {
        groupId,
        name,
        enterpriseType: row.valueChain || null,
        // Recorded as what it is. "Support provided" is what the project GAVE
        // them, which is not the same as what they still need, and filing it as
        // a support need would overstate the outstanding ask on every report.
        description:
          [
            row.valueChain ? `Value chain: ${row.valueChain}` : "",
            row.status ? `Status at transition: ${row.status}` : "",
            row.supportProvided ? `Support already provided: ${row.supportProvided}` : ""
          ]
            .filter(Boolean)
            .join(" | ") || null,
        status: /existing/i.test(row.status) ? "ACTIVE" : "ACTIVE"
      }
    });
    summary.enterprisesCreated += 1;
  }

  // ---------------------------------------------------------------------
  // Mentorship history
  // ---------------------------------------------------------------------
  for (const row of pack.mentorshipVisits) {
    const key = findGroupKey(keys, row.groupKey, row.ward, wardByKey);
    if (!key) {
      summary.unmatchedVisits.push(`${row.groupName} (visit ${row.visitNumber})`);
      continue;
    }
    const groupId = groupIdByKey.get(key) as string;
    const parsed = parseVisitDate(row.visitDate);
    if (!parsed) {
      summary.unmatchedVisits.push(`${row.groupName} (unreadable date "${row.visitDate}")`);
      continue;
    }
    const startedAt = parsed.date;
    if (!parsed.dayKnown) summary.visitsWithMonthOnlyDate += 1;

    // Deterministic, so a re-run recognises the same visit rather than
    // recording a second one for the same day.
    const clientRequestId = `flourish-${key}-visit-${row.visitNumber || normalise(row.visitDate)}`;

    const existing = await client.groupVisit.findUnique({
      where: { clientRequestId },
      select: { id: true }
    });
    if (existing) continue;

    const visit = await client.groupVisit.create({
      data: {
        groupId,
        clientRequestId,
        villageAgentId: agent.id,
        visitType: "FOLLOW_UP",
        status: "SUBMITTED",
        startedAt,
        completedAt: startedAt,
        submittedAt: startedAt,
        // Recorded from a paper form months later, so there is no device fix and
        // saying otherwise would fabricate evidence of attendance.
        locationOutcome: "NO_DEVICE_FIX",
        withinGeofence: false,
        notes:
          [
            row.mentor ? `Mentor: ${row.mentor}` : "",
            row.primarySupport ? `Primary support: ${row.primarySupport}` : "",
            row.signature ? `Signature: ${row.signature}` : "",
            parsed.dayKnown
              ? ""
              : `Date recorded as "${row.visitDate}" only — the day is not known, so this is filed at the start of the month.`,
            "Back-recorded from the July 2026 mentorship report."
          ]
            .filter(Boolean)
            .join(" | ")
      },
      select: { id: true }
    });
    summary.visitsCreated += 1;

    if (row.primarySupport) {
      await client.visitMentorshipSession.create({
        data: {
          visitId: visit.id,
          topicKeySnapshot: normalise(row.primarySupport),
          topicTitleSnapshot: row.primarySupport,
          notes: row.actionAgreed || null
        }
      });
      summary.mentorshipSessions += 1;
    }

    if (row.nextPlan) {
      await client.visitActionItem.create({
        data: {
          visitId: visit.id,
          groupId,
          title: row.nextPlan,
          detail: row.actionAgreed || null,
          owner: row.mentor || null,
          // Left OPEN rather than closed: the workbook records what was agreed,
          // never that it was done. Marking these done would credit the
          // programme with follow-through nobody has evidence of.
          status: "OPEN"
        }
      });
      summary.actionItems += 1;
    }
  }

  return { programme: programme.name, agent: agent.name, agentPassword, summary, notes };
}

const isDirectRun =
  process.argv[1]?.replace(/\\/g, "/").endsWith("import-flourish-onboarding.ts") ?? false;

if (isDirectRun) {
  importFlourishOnboarding()
    .then((result) => {
      console.log("\nFLOURISH onboarding import");
      console.log("  programme :", result.programme);
      console.log("  agent     :", result.agent);
      console.log("  summary   :", JSON.stringify(result.summary, null, 2));
      for (const note of result.notes) console.log("  note      :", note);
      if (result.agentPassword) {
        console.log("\n  AGENT PASSWORD (shown once):", result.agentPassword);
      }
      console.log(
        "\n  Not imported, by design: members, PINs, share values, opening balances,\n" +
          "  savings and loans. Those are blank in the workbook and must be minuted at\n" +
          "  each group's General Assembly."
      );
    })
    .catch((error) => {
      console.error(error);
      process.exit(1);
    });
}
