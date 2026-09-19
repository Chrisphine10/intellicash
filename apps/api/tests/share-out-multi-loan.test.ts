import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword, type LedgerEntryType } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { appendLedgerEntry } from "../src/routes/groups";
import { buildMemberPassbook } from "../src/services/member-passbook-service";

const app = createApp();

/**
 * A member with more than one loan is netted, and settled, as ONE debt.
 *
 * Found in QA: the share-out netted 550.00 owed across two loans in a single
 * repayment row. A row points at one loan, so the surplus over that loan was
 * dropped and the member was left owing 300.00 on the newer loan — money the
 * share-out had already taken from their payout, to be taken again next time.
 */
describe("share-out with a member who owes on two loans", () => {
  let cookies: string[];
  let groupId: string;
  let meetingId: string;
  let borrower: string;
  let saver: string;
  let loanFundId: string;
  const day = 24 * 3600 * 1000;

  const entry = (memberId: string, type: LedgerEntryType, amountCents: number, direction: "CREDIT" | "DEBIT") =>
    prisma.$transaction((tx) =>
      appendLedgerEntry(tx, {
        groupId,
        memberId,
        fundAccountId: loanFundId,
        type,
        amountCents,
        direction,
        description: `Test ${type}`
      })
    );

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirstOrThrow({ orderBy: { createdAt: "asc" } });
    groupId = group.id;

    const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
    const login = await request(app)
      .post("/api/v1/auth/login")
      .send({ phone: admin.phone, password: demoPassword })
      .expect(200);
    const cookie = login.headers["set-cookie"];
    cookies = Array.isArray(cookie) ? cookie : [cookie as unknown as string];

    const fund = await prisma.fundAccount.findFirstOrThrow({ where: { groupId, type: "INTERNAL_LOAN" } });
    loanFundId = fund.id;
    await prisma.fundAccount.update({ where: { id: fund.id }, data: { balanceCents: 100_000_000 } });
    const social = await prisma.fundAccount.findFirstOrThrow({ where: { groupId, type: "SOCIAL" } });
    await prisma.fundAccount.update({ where: { id: social.id }, data: { balanceCents: 0 } });

    await prisma.ledgerEntry.deleteMany({ where: { groupId, type: "SHARE_PURCHASE" } });
    await prisma.loan.deleteMany({ where: { groupId } });

    borrower = (
      await prisma.member.create({
        data: { groupId, fullName: "Owes On Two Loans", phone: "254789000201", status: "ACTIVE" }
      })
    ).id;
    saver = (
      await prisma.member.create({
        data: { groupId, fullName: "Only Saved Here", phone: "254789000202", status: "ACTIVE" }
      })
    ).id;

    // 10% a month, 3 months.
    await prisma.groupPolicy.upsert({
      where: { groupId },
      create: { groupId, defaultLoanTermMonths: 3, loanInterestRateBps: 1000 },
      update: { defaultLoanTermMonths: 3, loanInterestRateBps: 1000 }
    });

    for (const memberId of [borrower, saver]) {
      await entry(memberId, "SHARE_PURCHASE", 500_000, "CREDIT");
    }

    // Loan A: 500.00, taken 200 days ago — long past its 3-month term, so
    // 150.00 of interest (capped) makes 650.00. Loan B: 300.00, taken 10 days ago.
    const a = await entry(borrower, "INTERNAL_LOAN_DISBURSEMENT", 50_000, "DEBIT");
    const b = await entry(borrower, "INTERNAL_LOAN_DISBURSEMENT", 30_000, "DEBIT");
    const dueFor = (from: Date) => {
      const due = new Date(from);
      due.setMonth(due.getMonth() + 3);
      return due;
    };
    const aged = new Date(Date.now() - 200 * day);
    const recent = new Date(Date.now() - 10 * day);
    await prisma.loan.update({ where: { disbursementEntryId: a.id }, data: { disbursedAt: aged, dueAt: dueFor(aged) } });
    await prisma.loan.update({ where: { disbursementEntryId: b.id }, data: { disbursedAt: recent, dueAt: dueFor(recent) } });

    // 400.00 repaid so far: it clears the oldest loan first.
    await entry(borrower, "LOAN_REPAYMENT", 40_000, "CREDIT");

    meetingId = (
      await prisma.meeting.create({
        data: { groupId, title: "Two-loan share-out", scheduledAt: new Date(), status: "OPEN" }
      })
    ).id;
  }, 60000);

  const preview = async () =>
    (
      await request(app)
        .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/share-out/preview`)
        .set("Cookie", cookies)
        .send({ poolAmountCents: 1_000_000 })
        .expect(200)
    ).body.data;

  it("knows what is owed across both loans before the share-out", async () => {
    const passbook = await buildMemberPassbook(borrower);
    // Older loan: 500.00 + 150.00 - 400.00 = 250.00. Newer loan: 300.00.
    expect(passbook!.loans.map((loan) => loan.outstandingCents).sort()).toEqual([25_000, 30_000]);
    expect(passbook!.summary.loanOutstandingWithInterestCents).toBe(55_000);

    const data = await preview();
    expect(data.rows.find((row: any) => row.memberId === borrower).loanOffsetCents).toBe(55_000);
  });

  it("nets the WHOLE debt and leaves nothing to be collected again", async () => {
    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/share-out/post`)
      .set("Cookie", cookies)
      .send({ poolAmountCents: 1_000_000, clientRequestPrefix: `multi-loan-${Date.now()}` })
      .expect(201);

    expect(response.body.data.settlements).toHaveLength(1);
    expect(response.body.data.settlements[0]).toEqual(
      expect.objectContaining({ type: "LOAN_REPAYMENT", amountCents: 55_000 })
    );

    // Both loan records agree the debt is gone.
    const loans = await prisma.loan.findMany({ where: { groupId, memberId: borrower } });
    expect(loans.map((loan) => loan.status)).toEqual(["REPAID", "REPAID"]);

    // …and so does every figure a person can read.
    const passbook = await buildMemberPassbook(borrower);
    expect(passbook!.summary.loanOutstandingWithInterestCents).toBe(0);
    expect(passbook!.loans.every((loan) => loan.outstandingCents === 0 && loan.settled)).toBe(true);
    expect(passbook!.loans.reduce((sum, loan) => sum + loan.overpaidCents, 0)).toBe(0);
  });

  it("would charge nothing at a further share-out", async () => {
    // A new cycle: the borrower buys shares again, so they are in the next split.
    await entry(borrower, "SHARE_PURCHASE", 100_000, "CREDIT");
    const data = await preview();
    expect(data.rows.find((row: any) => row.memberId === borrower).loanOffsetCents).toBe(0);
  });
});
