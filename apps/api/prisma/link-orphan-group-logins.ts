import { prisma } from "../src/lib/prisma";
import { appendAuditEvent } from "../src/services/audit-service";
import { linkGroupChampion } from "../src/services/group-champion-service";

/**
 * Attaches the group logins that field sign-ups created with no group to the
 * imported group each one was really meant for.
 *
 * The mapping is explicit and was confirmed by a person on 16 Sep 2026. Names
 * that were uncertain (Emmanuel Wendo, Mwikuria S H G) or not in the onboarding
 * pack (Osiepe, Waigiri Vision, Mimi, LEJAZA, Kenya 1) are deliberately NOT
 * here: attaching a login to the wrong group hands one group's books to
 * another group's champion.
 *
 * DRY RUN by default. Set APPLY=1 to write. Every match must be unambiguous —
 * exactly one orphan login with that name per expected count, exactly one
 * imported group with that name — or the whole run stops before writing
 * anything.
 *
 * Nothing is deleted. Linking goes through the same service the console uses,
 * so the champion's existing password opens the real group afterwards.
 */

const MAPPING: { orphanName: string; groupName: string; expectedLogins: number }[] = [
  { orphanName: "Marui Women Group", groupName: "Marui Women Group", expectedLogins: 1 },
  { orphanName: "Kiguru Women Group", groupName: "Kiguru Women Group", expectedLogins: 1 },
  { orphanName: "One Faith Women Group", groupName: "One Faith Women Group", expectedLogins: 1 },
  { orphanName: "Gaciangara Self Help Group", groupName: "Gaciangara SHG", expectedLogins: 1 },
  { orphanName: "KIAMUVIA WENDANI SHG", groupName: "Kiamuvia Wendani Women Group", expectedLogins: 1 },
  { orphanName: "Ivururu Self Help Group", groupName: "Ivururu Women Self Help Group", expectedLogins: 1 },
  { orphanName: "GIEKAWA DAIRY SHG", groupName: "Giekawa Dairy Farming Self Help Group", expectedLogins: 1 },
  { orphanName: "Witikio", groupName: "Witiiko Women Group", expectedLogins: 1 },
  { orphanName: "ISSWA", groupName: "ISWA (INOI Staff Welfare Association)", expectedLogins: 2 },
  { orphanName: "Sunshine", groupName: "Sunshine Mwendiwega Women Group SHG", expectedLogins: 1 },
  { orphanName: "unjiru wa mbari ya botha", groupName: "Muhiriga wa bari ya butha Women Group", expectedLogins: 1 }
];

const APPLY = process.env.APPLY === "1";
const mask = (phone: string | null) => (phone ? `${"*".repeat(phone.length - 3)}${phone.slice(-3)}` : "(none)");

async function main() {
  console.log(APPLY ? "\nAPPLYING links\n" : "\nDRY RUN — nothing will be written (set APPLY=1)\n");

  const plan: { orphanId: string; orphanName: string; phone: string; groupId: string; groupName: string }[] = [];
  const problems: string[] = [];

  for (const row of MAPPING) {
    const orphans = await prisma.user.findMany({
      where: {
        name: row.orphanName,
        role: "GROUP_ACCOUNT",
        groupId: null,
        memberId: null,
        villageAgentId: null
      },
      select: { id: true, name: true, phone: true }
    });
    const groups = await prisma.group.findMany({
      where: { name: row.groupName, sourceSystem: "FLOURISH_ONBOARDING_2026" },
      select: { id: true, name: true, code: true }
    });

    if (orphans.length !== row.expectedLogins) {
      problems.push(`"${row.orphanName}": expected ${row.expectedLogins} orphan login(s), found ${orphans.length}`);
      continue;
    }
    if (groups.length !== 1) {
      problems.push(`"${row.groupName}": expected 1 imported group, found ${groups.length}`);
      continue;
    }
    for (const orphan of orphans) {
      if (!orphan.phone) {
        problems.push(`"${row.orphanName}": a login has no phone to link by`);
        continue;
      }
      plan.push({
        orphanId: orphan.id,
        orphanName: orphan.name,
        phone: orphan.phone,
        groupId: groups[0]!.id,
        groupName: `${groups[0]!.name} (${groups[0]!.code})`
      });
    }
  }

  for (const step of plan) {
    console.log(`  ${step.orphanName.padEnd(28)} ${mask(step.phone)}  ->  ${step.groupName}`);
  }

  if (problems.length > 0) {
    console.log("\nSTOPPED — nothing written. Resolve these first:");
    for (const problem of problems) console.log("  -", problem);
    process.exitCode = 1;
    return;
  }

  if (!APPLY) {
    console.log(`\n${plan.length} link(s) planned. Re-run with APPLY=1 to write them.`);
    return;
  }

  for (const step of plan) {
    const result = await linkGroupChampion({ groupId: step.groupId, phone: step.phone });
    await appendAuditEvent({
      entityType: "GROUP",
      entityId: step.groupId,
      type: "GROUP_CHAMPION_LINKED",
      payload: {
        outcome: result.outcome,
        userId: result.userId,
        linkedOrphanName: step.orphanName,
        source: "link-orphan-group-logins.ts, mapping confirmed 2026-09-16"
      }
    });
    console.log(`  ${result.outcome.padEnd(22)} ${step.orphanName} -> ${step.groupName}`);
  }
  console.log(`\n${plan.length} login(s) linked.`);
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(() => prisma.$disconnect());
