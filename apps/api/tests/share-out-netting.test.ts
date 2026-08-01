import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { appendLedgerEntry } from "../src/routes/groups";

const app = createApp();

/**
 * Share-out must collect what members owe.
 *
 * Until 1 Aug 2026 the server paid out the pro-rata split GROSS: outstanding
 * loans were never netted off, so a member could borrow, not repay, and still
 * receive their full share — the group forgiving the debt without deciding to.
 * The phone's calculator had always netted, so the two disagreed about real
 * money depending on which screen ran the share-out.
 */
describe("share-out nets off what members owe", () => {
  let cookies: string[];
  let groupId: string;
  let meetingId: string;
  let borrower: string;
  let saver: string;
  let loanFundId: string;

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

    const fund = await prisma.fundAccount.findFirstOrThrow({
      where: { groupId, type: "INTERNAL_LOAN" }
    });
    loanFundId = fund.id;
    await prisma.fundAccount.update({
      where: { id: fund.id },
      data: { balanceCents: 100_000_000 }
    });

    // A clean slate: two members, equal shares, one of whom borrows.
    await prisma.ledgerEntry.deleteMany({ where: { groupId, type: "SHARE_PURCHASE" } });
    await prisma.loan.deleteMany({ where: { groupId } });

    const one = await prisma.member.create({
      data: { groupId, fullName: "Borrowed And Kept It", phone: "254789000001", status: "ACTIVE" }
    });
    const two = await prisma.member.create({
      data: { groupId, fullName: "Only Saved", phone: "254789000002", status: "ACTIVE" }
    });
    borrower = one.id;
    saver = two.id;

    await prisma.groupPolicy.upsert({
      where: { groupId },
      create: { groupId, defaultLoanTermMonths: 3, loanInterestRateBps: 0 },
      update: { defaultLoanTermMonths: 3, loanInterestRateBps: 0 }
    });

    for (const memberId of [borrower, saver]) {
      await prisma.$transaction((tx) =>
        appendLedgerEntry(tx, {
          groupId,
          memberId,
          fundAccountId: loanFundId,
          type: "SHARE_PURCHASE",
          amountCents: 500_000,
          direction: "CREDIT",
          description: "Shares"
        })
      );
    }

    // The borrower takes 300.00 and repays nothing.
    await prisma.$transaction((tx) =>
      appendLedgerEntry(tx, {
        groupId,
        memberId: borrower,
        fundAccountId: loanFundId,
        type: "INTERNAL_LOAN_DISBURSEMENT",
        amountCents: 300_000,
        direction: "DEBIT",
        description: "Unrepaid loan"
      })
    );

    const meeting = await prisma.meeting.create({
      data: {
        groupId,
        title: "Share-out meeting",
        scheduledAt: new Date(),
        status: "OPEN"
      }
    });
    meetingId = meeting.id;
  }, 60000);

  async function preview(poolAmountCents: number) {
    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/share-out/preview`)
      .set("Cookie", cookies)
      .send({ poolAmountCents })
      .expect(200);
    return response.body.data;
  }

  it("still splits the pool pro-rata, to the cent", async () => {
    // The invariant that existed before netting must survive it: gross is
    // still the whole pool, so a group can reconcile against the box.
    const data = await preview(1_000_000);
    const gross = data.rows.reduce((sum: number, row: any) => sum + row.payoutCents, 0);
    expect(gross).toBe(1_000_000);
    expect(data.roundingDifferenceCents).toBe(0);
  });

  it("subtracts the borrower's outstanding loan from their payout", async () => {
    const data = await preview(1_000_000);
    const owing = data.rows.find((row: any) => row.memberId === borrower);
    const clear = data.rows.find((row: any) => row.memberId === saver);

    expect(owing.loanOffsetCents).toBe(300_000);
    // Equal shares, so 500,000 gross each; the borrower keeps 200,000.
    expect(owing.payoutCents).toBe(500_000);
    expect(owing.netPayoutCents).toBe(200_000);

    // The member who only saved is untouched by someone else's debt.
    expect(clear.loanOffsetCents).toBe(0);
    expect(clear.netPayoutCents).toBe(clear.payoutCents);
  });

  it("a debt bigger than the entitlement gives a NEGATIVE net, not a block", async () => {
    // The 30 Jul rule: unpaid money nets off the payout and never bars anyone
    // from sharing out. A negative net is a debt to the group.
    const data = await preview(200_000);
    const owing = data.rows.find((row: any) => row.memberId === borrower);
    expect(owing.netPayoutCents).toBeLessThan(0);
    expect(owing.owesGroup).toBe(true);
    // They are still in the share-out, not excluded from it.
    expect(data.rows).toHaveLength(2);
  });

  it("reports the welfare fund without paying it out of the wrong pot", async () => {
    // SHARE_OUT_PAYOUT debits the loan fund, so welfare cannot ride on it.
    // The balance is reported so a group can hand it out deliberately.
    const data = await preview(1_000_000);
    expect(data).toHaveProperty("welfarePoolCents");
    expect(data.distributeWelfare).toBe(false);
    expect(data.rows.every((row: any) => row.welfareCents === 0)).toBe(true);
  });

  it("posting settles the loan instead of leaving it to be collected twice", async () => {
    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/share-out/post`)
      .set("Cookie", cookies)
      .send({ poolAmountCents: 1_000_000, clientRequestPrefix: `netting-${Date.now()}` })
      .expect(201);

    // A real repayment, through the same path every other repayment takes.
    expect(response.body.data.settlements).toHaveLength(1);
    expect(response.body.data.settlements[0]).toEqual(
      expect.objectContaining({ type: "LOAN_REPAYMENT", amountCents: 300_000 })
    );

    // "Netted off, never carried forward": the loan must not survive into the
    // next cycle for the member to be charged again.
    const loans = await prisma.loan.findMany({ where: { groupId, memberId: borrower } });
    expect(loans.every((loan) => loan.status === "REPAID")).toBe(true);

    const passbook = await prisma.loan.findMany({
      where: { groupId, memberId: borrower, status: "ACTIVE" }
    });
    expect(passbook).toHaveLength(0);
  });

  it("pays out the NET, not the gross", async () => {
    const payouts = await prisma.ledgerEntry.findMany({
      where: { groupId, type: "SHARE_OUT_PAYOUT", memberId: borrower }
    });
    // 500,000 gross less the 300,000 loan. Paying the gross would hand the
    // borrower money that had already settled their debt.
    expect(payouts.reduce((sum, entry) => sum + entry.amountCents, 0)).toBe(200_000);
  });
});
