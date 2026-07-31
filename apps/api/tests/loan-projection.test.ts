import { beforeAll, describe, expect, it } from "vitest";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { appendLedgerEntry } from "../src/routes/groups";
import { buildMemberPassbook } from "../src/services/member-passbook-service";

/**
 * Disbursement must create the `Loan` the ledger line cannot describe.
 *
 * Until 31 Jul 2026 NOTHING in the application created a Loan row — the only
 * writers were the backfill script and tests. So `loan-math.ts` and the whole
 * flat-monthly interest rule were live code with no caller, and interest was
 * never charged on anything the app recorded. These tests exist so that can
 * never quietly become true again.
 */
describe("loans are projected from the ledger as money moves", () => {
  let groupId: string;
  let memberId: string;
  let otherMemberId: string;
  let loanFundId: string;

  async function disburse(amountCents: number, member = memberId) {
    return prisma.$transaction((tx) =>
      appendLedgerEntry(tx, {
        groupId,
        memberId: member,
        fundAccountId: loanFundId,
        type: "INTERNAL_LOAN_DISBURSEMENT",
        amountCents,
        direction: "DEBIT",
        description: "Test disbursement"
      })
    );
  }

  async function repay(amountCents: number, member = memberId) {
    return prisma.$transaction((tx) =>
      appendLedgerEntry(tx, {
        groupId,
        memberId: member,
        fundAccountId: loanFundId,
        type: "LOAN_REPAYMENT",
        amountCents,
        direction: "CREDIT",
        description: "Test repayment"
      })
    );
  }

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirstOrThrow({ orderBy: { createdAt: "asc" } });
    groupId = group.id;

    const members = await prisma.member.findMany({ where: { groupId }, take: 2 });
    memberId = members[0]!.id;
    otherMemberId = members[1]!.id;

    const fund = await prisma.fundAccount.findFirstOrThrow({
      where: { groupId, type: "INTERNAL_LOAN" }
    });
    loanFundId = fund.id;
    // Plenty of headroom so the loan-fund guard is not what these tests hit.
    await prisma.fundAccount.update({
      where: { id: fund.id },
      data: { balanceCents: 50_000_000 }
    });

    // 10% a month over 3 months — the common VSLA rate.
    await prisma.groupPolicy.upsert({
      where: { groupId },
      create: { groupId, defaultLoanTermMonths: 3, loanInterestRateBps: 1000 },
      update: { defaultLoanTermMonths: 3, loanInterestRateBps: 1000 }
    });
  }, 60000);

  it("creates a Loan when money is disbursed", async () => {
    const entry = await disburse(1_000_000);
    const loan = await prisma.loan.findUnique({
      where: { disbursementEntryId: entry.id }
    });

    expect(loan).not.toBeNull();
    expect(loan!.principalCents).toBe(1_000_000);
    expect(loan!.memberId).toBe(memberId);
    expect(loan!.status).toBe("ACTIVE");
  });

  it("takes the term and rate from the group's own policy", async () => {
    const entry = await disburse(500_000);
    const loan = await prisma.loan.findUniqueOrThrow({
      where: { disbursementEntryId: entry.id }
    });

    expect(loan.termMonths).toBe(3);
    expect(loan.interestRateBps).toBe(1000);
    // Due date is the term out from disbursement, not an arbitrary default.
    const expected = new Date(loan.disbursedAt);
    expected.setMonth(expected.getMonth() + 3);
    expect(loan.dueAt.toISOString()).toBe(expected.toISOString());
  });

  it("stamps the loan with the cycle it was made in", async () => {
    const entry = await disburse(100_000);
    const loan = await prisma.loan.findUniqueOrThrow({
      where: { disbursementEntryId: entry.id }
    });
    expect(loan.cycleId).toBe(entry.cycleId);
    expect(loan.cycleId).toBeTruthy();
  });

  it("COPIES the rate, so raising it later cannot reprice money already lent", async () => {
    // The single most damaging thing this could get wrong: a group raising its
    // rate must not retroactively increase what members already owe.
    const entry = await disburse(200_000, otherMemberId);
    await prisma.groupPolicy.update({
      where: { groupId },
      data: { loanInterestRateBps: 2000 }
    });

    const loan = await prisma.loan.findUniqueOrThrow({
      where: { disbursementEntryId: entry.id }
    });
    expect(loan.interestRateBps).toBe(1000);

    await prisma.groupPolicy.update({
      where: { groupId },
      data: { loanInterestRateBps: 1000 }
    });
  });

  it("lends interest-free when the group has set no rate", async () => {
    // Defaulting to a "typical" rate would charge members money their
    // constitution never agreed to.
    const bare = await prisma.group.findFirstOrThrow({
      where: { id: { not: groupId } }
    });
    const bareMember = await prisma.member.findFirstOrThrow({ where: { groupId: bare.id } });
    const bareFund = await prisma.fundAccount.findFirstOrThrow({
      where: { groupId: bare.id, type: "INTERNAL_LOAN" }
    });
    await prisma.groupPolicy.deleteMany({ where: { groupId: bare.id } });
    await prisma.fundAccount.update({
      where: { id: bareFund.id },
      data: { balanceCents: 10_000_000 }
    });

    const entry = await prisma.$transaction((tx) =>
      appendLedgerEntry(tx, {
        groupId: bare.id,
        memberId: bareMember.id,
        fundAccountId: bareFund.id,
        type: "INTERNAL_LOAN_DISBURSEMENT",
        amountCents: 400_000,
        direction: "DEBIT",
        description: "Unconfigured group loan"
      })
    );

    const loan = await prisma.loan.findUniqueOrThrow({
      where: { disbursementEntryId: entry.id }
    });
    expect(loan.interestRateBps).toBe(0);
    expect(loan.termMonths).toBe(1);
  });

  it("points a repayment at the loan it pays down", async () => {
    const fresh = await prisma.member.create({
      data: { groupId, fullName: "Repays Properly", phone: "254788111000", status: "ACTIVE" }
    });
    const disbursement = await disburse(1_000_000, fresh.id);
    const loan = await prisma.loan.findUniqueOrThrow({
      where: { disbursementEntryId: disbursement.id }
    });

    const repayment = await repay(300_000, fresh.id);
    const stored = await prisma.ledgerEntry.findUniqueOrThrow({ where: { id: repayment.id } });
    // Without this the repayment is invisible to the projection and the
    // member's balance never falls, however much they pay.
    expect(stored.loanId).toBe(loan.id);
  });

  it("reduces what the member owes as they repay", async () => {
    const fresh = await prisma.member.create({
      data: { groupId, fullName: "Pays It Down", phone: "254788111001", status: "ACTIVE" }
    });
    await disburse(1_000_000, fresh.id);

    const before = await buildMemberPassbook(fresh.id);
    await repay(400_000, fresh.id);
    const after = await buildMemberPassbook(fresh.id);

    expect(after!.summary.loanOutstandingWithInterestCents).toBe(
      before!.summary.loanOutstandingWithInterestCents - 400_000
    );
  });

  it("closes the loan once it is fully repaid", async () => {
    const fresh = await prisma.member.create({
      data: { groupId, fullName: "Clears The Debt", phone: "254788111002", status: "ACTIVE" }
    });
    const disbursement = await disburse(1_000_000, fresh.id);
    const loanId = (
      await prisma.loan.findUniqueOrThrow({ where: { disbursementEntryId: disbursement.id } })
    ).id;

    // Same day, so no month has elapsed and no interest has accrued yet.
    await repay(1_000_000, fresh.id);

    const loan = await prisma.loan.findUniqueOrThrow({ where: { id: loanId } });
    // Leaving it ACTIVE would keep accruing interest on a settled debt.
    expect(loan.status).toBe("REPAID");

    const passbook = await buildMemberPassbook(fresh.id);
    expect(passbook!.summary.loanOutstandingWithInterestCents).toBe(0);
  });

  it("pays off the OLDEST loan first", async () => {
    const fresh = await prisma.member.create({
      data: { groupId, fullName: "Owes Twice", phone: "254788111003", status: "ACTIVE" }
    });
    const first = await disburse(300_000, fresh.id);
    const second = await disburse(500_000, fresh.id);

    const repayment = await repay(100_000, fresh.id);
    const stored = await prisma.ledgerEntry.findUniqueOrThrow({ where: { id: repayment.id } });
    const oldest = await prisma.loan.findUniqueOrThrow({
      where: { disbursementEntryId: first.id }
    });
    const newest = await prisma.loan.findUniqueOrThrow({
      where: { disbursementEntryId: second.id }
    });

    expect(stored.loanId).toBe(oldest.id);
    expect(stored.loanId).not.toBe(newest.id);
  });

  it("a repayment from someone with no loan is left alone, not invented", async () => {
    // Attributing this to a loan that does not exist would either crash or
    // credit somebody else's debt.
    const stranger = await prisma.member.create({
      data: { groupId, fullName: "Owes Nothing", phone: "254788111004", status: "ACTIVE" }
    });
    const repayment = await repay(50_000, stranger.id);
    const stored = await prisma.ledgerEntry.findUniqueOrThrow({ where: { id: repayment.id } });
    expect(stored.loanId).toBeNull();
  });

  it("never double-counts: the backfill cannot re-create these loans", async () => {
    // disbursementEntryId is UNIQUE, which is what makes the two routes into
    // the projection safe to run against the same database.
    const entry = await disburse(700_000);
    await expect(
      prisma.loan.create({
        data: {
          groupId,
          memberId,
          principalCents: 700_000,
          interestRateBps: 1000,
          termMonths: 3,
          disbursedAt: new Date(),
          dueAt: new Date(),
          disbursementEntryId: entry.id
        }
      })
    ).rejects.toThrow();
  });

  it("charges interest once a month has passed", async () => {
    // The whole point of the projection. Backdated because a loan made today
    // has, correctly, accrued nothing yet.
    const fresh = await prisma.member.create({
      data: { groupId, fullName: "Owes Interest", phone: "254788111005", status: "ACTIVE" }
    });
    const disbursement = await disburse(1_000_000, fresh.id);
    await prisma.loan.update({
      where: { disbursementEntryId: disbursement.id },
      data: { disbursedAt: new Date(Date.now() - 31 * 24 * 3600 * 1000) }
    });

    const passbook = await buildMemberPassbook(fresh.id);
    // 10,000.00 at 10% a month, one month elapsed => 1,000.00.
    expect(passbook!.summary.loanInterestCents).toBe(100_000);
    expect(passbook!.summary.loanOutstandingWithInterestCents).toBe(1_100_000);
    // And the ledger-only figure is knowably lower, which is the gap that
    // existed for every loan before this projection ran.
    expect(passbook!.summary.loanOutstandingCents).toBe(1_000_000);
  });
});
