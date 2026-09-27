/**
 * Rehearsal helper (read-only): the money figures the app shows, for every
 * group and member of the database in DATABASE_URL, as JSON on stdout. Run on
 * a copy before and after a migration and diff the two. Ids only, no names.
 */
import { prisma } from "../src/lib/prisma";
import { groupConsistency } from "../src/services/group-rules-service";
import { buildMemberPassbook } from "../src/services/member-passbook-service";

async function main() {
  const out: Record<string, unknown> = {};
  for (const group of await prisma.group.findMany({ select: { id: true }, orderBy: { id: "asc" } })) {
    const consistency = await groupConsistency(prisma, group.id);
    out[`group:${group.id}`] = consistency;
  }
  for (const member of await prisma.member.findMany({ select: { id: true }, orderBy: { id: "asc" } })) {
    const book = await buildMemberPassbook(member.id);
    out[`member:${member.id}`] = book ? (book as { summary?: unknown }).summary ?? null : null;
  }
  process.stdout.write(JSON.stringify(out));
}

main().finally(() => prisma.$disconnect());
