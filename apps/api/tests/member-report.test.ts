import { beforeAll, describe, expect, it } from "vitest";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { buildMemberPassbook } from "../src/services/member-passbook-service";

/**
 * The member report is what a member is shown about their own money, so the
 * figure that matters most is what they OWE — and until Loan existed the
 * report computed that as disbursed minus repaid, which ignores interest.
 */
describe("member report", () => {
  let memberId: string;
  let groupId: string;

  beforeAll(async () => {
    await seedDatabase();
    const member = await prisma.member.findFirst({ orderBy: { createdAt: "asc" } });
    memberId = member!.id;
    groupId = member!.groupId;
  }, 60000);

  it("reports outstanding WITH interest, not just disbursed minus repaid", async () => {
    const cycle = await prisma.cycle.findFirst({ where: { groupId, status: "ACTIVE" } });
    // 10,000.00 at 10% a month, one month elapsed => 1,000.00 interest.
    await prisma.loan.create({
      data: {
        groupId,
        memberId,
        cycleId: cycle?.id ?? null,
        principalCents: 1_000_000,
        interestRateBps: 1000,
        termMonths: 3,
        disbursedAt: new Date(Date.now() - 31 * 24 * 3600 * 1000),
        dueAt: new Date(Date.now() + 60 * 24 * 3600 * 1000),
        status: "ACTIVE"
      }
    });

    const passbook = await buildMemberPassbook(memberId);
    const loan = passbook!.loans.find((l) => l.principalCents === 1_000_000);

    expect(loan).toBeDefined();
    expect(loan!.interestCents).toBe(100_000);
    // Principal + interest, since nothing has been repaid against it.
    expect(loan!.outstandingCents).toBe(1_100_000);

    // The headline figure must include interest. The legacy field is kept for
    // compatibility but is knowingly lower — asserting the difference stops
    // anyone "simplifying" them back into one.
    expect(passbook!.summary.loanOutstandingWithInterestCents).toBeGreaterThanOrEqual(1_100_000);
    expect(passbook!.summary.loanInterestCents).toBeGreaterThanOrEqual(100_000);
  });

  it("never reports zero owed just because a loan was never projected", async () => {
    /**
     * `Loan` is a projection. A loan disbursed before it existed has no Loan
     * row until the backfill script has run, so summing projected loans alone
     * returns ZERO for a member the ledger says owes real money — and a client
     * preferring that figure tells them their debt is cleared.
     *
     * Caught on a seeded database: the ledger showed 4,500.00 outstanding and
     * the interest-aware figure came back 0.
     */
    const group = await prisma.group.findFirstOrThrow({ where: { id: groupId } });
    const orphan = await prisma.member.create({
      data: {
        groupId: group.id,
        fullName: "Borrowed Before Loans Existed",
        phone: "254788000222",
        status: "ACTIVE"
      }
    });
    const sample = await prisma.ledgerEntry.findFirstOrThrow({
      select: { currency: true, fundAccountId: true }
    });

    // Ledger only — deliberately NO Loan row, exactly like un-backfilled data.
    await prisma.ledgerEntry.createMany({
      data: [
        {
          groupId: group.id,
          memberId: orphan.id,
          fundAccountId: sample.fundAccountId,
          currency: sample.currency,
          signature: "orphan-loan-test",
          type: "INTERNAL_LOAN_DISBURSEMENT",
          direction: "DEBIT",
          amountCents: 1_000_000,
          description: "Legacy loan"
        },
        {
          groupId: group.id,
          memberId: orphan.id,
          fundAccountId: sample.fundAccountId,
          currency: sample.currency,
          signature: "orphan-repay-test",
          type: "LOAN_REPAYMENT",
          direction: "CREDIT",
          amountCents: 550_000,
          description: "Legacy repayment"
        }
      ]
    });

    const passbook = await buildMemberPassbook(orphan.id);
    expect(passbook!.loans).toHaveLength(0);
    // 10,000.00 borrowed, 5,500.00 repaid. Interest is unknown without a Loan
    // row, so the ledger difference stands — understating is a smaller lie
    // than reporting nothing owed.
    expect(passbook!.summary.loanOutstandingWithInterestCents).toBe(450_000);
    expect(passbook!.summary.loanOutstandingCents).toBe(450_000);
  });

  it("separates welfare RECEIVED from welfare contributed", async () => {
    const passbook = await buildMemberPassbook(memberId);
    // Contributions live in summary.socialCents; benefits are their own list.
    // A passbook showing only what a member paid in misses half the story.
    expect(passbook!.summary).toHaveProperty("welfareReceivedCents");
    expect(Array.isArray(passbook!.welfareReceived)).toBe(true);
  });

  it("includes share-out history", async () => {
    const passbook = await buildMemberPassbook(memberId);
    expect(Array.isArray(passbook!.shareOutHistory)).toBe(true);
    expect(passbook!.summary).toHaveProperty("shareOutReceivedCents");
  });

  it("flags a loan that is past its due date", async () => {
    const cycle = await prisma.cycle.findFirst({ where: { groupId, status: "ACTIVE" } });
    await prisma.loan.create({
      data: {
        groupId,
        memberId,
        cycleId: cycle?.id ?? null,
        principalCents: 50_000,
        interestRateBps: 0,
        termMonths: 1,
        disbursedAt: new Date(Date.now() - 90 * 24 * 3600 * 1000),
        dueAt: new Date(Date.now() - 60 * 24 * 3600 * 1000),
        status: "ACTIVE"
      }
    });

    const passbook = await buildMemberPassbook(memberId);
    const overdue = passbook!.loans.find((l) => l.principalCents === 50_000);
    expect(overdue!.overdue).toBe(true);
  });
});
