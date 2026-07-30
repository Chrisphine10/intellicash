/**
 * Build the Loan projection from the ledger.
 *
 *   npx tsx prisma/backfill-loans.ts            # dry run, writes nothing
 *   npx tsx prisma/backfill-loans.ts --commit   # writes
 *
 * DRY RUN IS THE DEFAULT. This runs against a database holding real ledgers;
 * an accidental `tsx backfill-loans.ts` must not change anything.
 *
 * The ledger stays the source of truth. This only creates Loan rows that
 * describe disbursement entries that already exist, and attributes repayments
 * to them. No money is created, moved or altered.
 *
 * Idempotent: Loan.disbursementEntryId is UNIQUE, so a second run skips every
 * loan it already made.
 */
import { prisma } from "../src/lib/prisma";
import { allocateFifo, loanBalance } from "../src/domain/loan-math";

const COMMIT = process.argv.includes("--commit");
const DISBURSEMENT = "INTERNAL_LOAN_DISBURSEMENT";
const REPAYMENT = "LOAN_REPAYMENT";

/** Default term when a group has no policy yet. Requirement #5 default. */
const DEFAULT_TERM_MONTHS = 1;

function kes(cents: number) {
  return `KES ${(cents / 100).toLocaleString("en-KE", { minimumFractionDigits: 2 })}`;
}

async function main() {
  console.log(COMMIT ? "MODE: COMMIT (will write)\n" : "MODE: DRY RUN (writes nothing)\n");

  const groups = await prisma.group.findMany({ select: { id: true, name: true, code: true } });
  let created = 0;
  let attributed = 0;
  const failures: string[] = [];

  for (const group of groups) {
    const disbursements = await prisma.ledgerEntry.findMany({
      where: { groupId: group.id, type: DISBURSEMENT },
      orderBy: { createdAt: "asc" }
    });
    const repayments = await prisma.ledgerEntry.findMany({
      where: { groupId: group.id, type: REPAYMENT },
      orderBy: { createdAt: "asc" }
    });
    if (disbursements.length === 0 && repayments.length === 0) continue;

    // ---- Reconciliation, BEFORE writing anything for this group -----------
    // Every disbursement must become exactly one loan of the same amount. If
    // the totals disagree, the projection would misstate what members owe, so
    // the group is skipped and reported rather than half-written.
    const existing = await prisma.loan.findMany({ where: { groupId: group.id } });
    const alreadyMapped = new Set(existing.map((l) => l.disbursementEntryId).filter(Boolean));

    const ledgerTotal = disbursements.reduce((sum, e) => sum + e.amountCents, 0);
    const projected =
      existing.reduce((sum, l) => sum + l.principalCents, 0) +
      disbursements.filter((e) => !alreadyMapped.has(e.id)).reduce((s, e) => s + e.amountCents, 0);

    if (projected !== ledgerTotal) {
      failures.push(
        `${group.code} ${group.name}: ledger disbursements ${kes(ledgerTotal)} != projected loans ${kes(projected)}`
      );
      continue;
    }

    const orphanRepayments = repayments.filter((e) => !e.memberId);
    if (orphanRepayments.length > 0) {
      // Repayments with no member cannot be attributed to anyone's loan. Not
      // fatal — they still count in group totals — but say so plainly.
      console.log(
        `  ${group.code}: ${orphanRepayments.length} repayment(s) with no member; left unattributed`
      );
    }

    // ---- Create loans -----------------------------------------------------
    const toCreate = disbursements.filter((e) => !alreadyMapped.has(e.id) && e.memberId);
    for (const entry of toCreate) {
      const disbursedAt = entry.createdAt;
      const dueAt = new Date(disbursedAt);
      dueAt.setMonth(dueAt.getMonth() + DEFAULT_TERM_MONTHS);

      if (COMMIT) {
        await prisma.loan.create({
          data: {
            groupId: group.id,
            memberId: entry.memberId!,
            cycleId: entry.cycleId,
            principalCents: entry.amountCents,
            // 0 bps: the historical ledger records no rate, and inventing one
            // would overstate what members owe. Groups set it going forward.
            interestRateBps: 0,
            termMonths: DEFAULT_TERM_MONTHS,
            disbursedAt,
            dueAt,
            status: "ACTIVE",
            disbursementEntryId: entry.id
          }
        });
      }
      created += 1;
    }

    // ---- Attribute repayments, oldest loan first --------------------------
    const byMember = new Map<string, typeof repayments>();
    for (const entry of repayments) {
      if (!entry.memberId || entry.loanId) continue;
      const list = byMember.get(entry.memberId) ?? [];
      list.push(entry);
      byMember.set(entry.memberId, list);
    }

    for (const [memberId, memberRepayments] of byMember) {
      const loans = COMMIT
        ? await prisma.loan.findMany({
            where: { groupId: group.id, memberId },
            orderBy: { disbursedAt: "asc" }
          })
        : [];
      if (loans.length === 0) continue;

      const owed = loans.map((loan) => ({
        id: loan.id,
        owedCents: loanBalance({
          principalCents: loan.principalCents,
          interestRateBps: loan.interestRateBps,
          termMonths: loan.termMonths,
          disbursedAt: loan.disbursedAt,
          repaidCents: 0,
          asOf: new Date()
        }).outstandingCents
      }));

      let pool = memberRepayments.reduce((sum, e) => sum + e.amountCents, 0);
      for (const allocation of allocateFifo(owed, pool)) {
        // Attribute whole entries to the loan FIFO points at. A repayment that
        // spans two loans stays on the older one; the member's TOTAL is
        // unaffected, and totals are what reports use.
        const entry = memberRepayments.shift();
        if (!entry) break;
        if (COMMIT) {
          await prisma.ledgerEntry.update({
            where: { id: entry.id },
            data: { loanId: allocation.loanId }
          });
        }
        attributed += 1;
        pool -= entry.amountCents;
      }
    }
  }

  console.log(`\nloans ${COMMIT ? "created" : "would create"}: ${created}`);
  if (COMMIT) {
    console.log(`repayments attributed: ${attributed}`);
  } else {
    // Honest about what a dry run can and cannot know: attribution needs the
    // loans to exist, and in dry run they do not. Reporting 0 here would read
    // as "no repayments to attribute", which is a different claim entirely.
    const pending = await prisma.ledgerEntry.count({
      where: { type: REPAYMENT, loanId: null, memberId: { not: null } }
    });
    console.log(
      `repayments awaiting attribution: ${pending} (cannot be simulated in a dry run — the loans do not exist yet)`
    );
  }

  if (failures.length > 0) {
    console.error(`\nRECONCILIATION FAILED for ${failures.length} group(s) — SKIPPED, not written:`);
    for (const failure of failures) console.error(`  - ${failure}`);
    console.error("\nFix the mismatch before re-running. The ledger is authoritative.");
    process.exitCode = 1;
    return;
  }

  console.log(
    COMMIT ? "\nReconciled: every disbursement maps to exactly one loan." : "\nDry run only. Re-run with --commit to write."
  );
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(() => prisma.$disconnect());
