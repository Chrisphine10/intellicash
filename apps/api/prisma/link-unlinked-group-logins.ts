import { prisma } from "../src/lib/prisma";
import { ensureGroupForLogin } from "../src/services/group-login-link";

/**
 * Gives every group login that has no group the group it should open.
 *
 * The same rule sign-in applies automatically (services/group-login-link):
 * the champion's number, then the name, then a group of its own. Running it
 * here closes the gap for logins that have not signed in since, so none is
 * left pointing at nothing.
 *
 * DRY RUN unless APPLY=1: prints the decision for every login first. Nothing
 * is deleted. Phones are masked in the output.
 */

const APPLY = process.env.APPLY === "1";
const mask = (phone: string | null) => (phone ? `${"*".repeat(Math.max(0, phone.length - 3))}${phone.slice(-3)}` : "(none)");

async function main() {
  console.log(APPLY ? "\nAPPLYING\n" : "\nDRY RUN — nothing will be written (set APPLY=1)\n");

  const unlinked = await prisma.user.findMany({
    where: { role: "GROUP_ACCOUNT", groupId: null },
    select: { id: true, name: true, phone: true, status: true },
    orderBy: { createdAt: "asc" }
  });
  console.log(`Group logins with no group: ${unlinked.length}\n`);

  const tally = new Map<string, number>();
  for (const login of unlinked) {
    const result = await ensureGroupForLogin(login.id, { apply: APPLY });
    tally.set(result.outcome, (tally.get(result.outcome) ?? 0) + 1);
    const target =
      result.outcome === "CLOSED_SKIPPED"
        ? "(closed account — left alone)"
        : result.outcome === "GROUP_CREATED"
          ? `new group "${result.groupName}"`
          : `"${result.groupName}"`;
    const dupes = result.possibleDuplicates?.length ? `  (possible duplicate of: ${[...new Set(result.possibleDuplicates)].join(", ")})` : "";
    console.log(`  ${login.name.slice(0, 34).padEnd(34)} ${mask(login.phone).padEnd(14)} ${login.status.padEnd(8)} ${result.outcome.padEnd(16)} -> ${target}${dupes}`);
  }

  console.log("\nSummary:", Object.fromEntries(tally));
  const remaining = await prisma.user.count({
    where: { role: "GROUP_ACCOUNT", groupId: null, status: { not: "CLOSED" } }
  });
  console.log(`Group logins still without a group: ${remaining}${APPLY ? "" : " (dry run — unchanged)"}`);
  await prisma.$disconnect();
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
