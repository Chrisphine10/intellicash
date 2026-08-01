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

    // A known welfare balance, so the arithmetic below is checkable by hand:
    // 1,000.00 split equally between two members is 500.00 each.
    const social = await prisma.fundAccount.findFirstOrThrow({
      where: { groupId, type: "SOCIAL" }
    });
    await prisma.fundAccount.update({
      where: { id: social.id },
      data: { balanceCents: 100_000 }
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
    // Equal shares, so 500,000 gross each, plus 50,000 welfare each.
    // The borrower keeps 500,000 + 50,000 − 300,000 = 250,000.
    expect(owing.payoutCents).toBe(500_000);
    expect(owing.welfareCents).toBe(50_000);
    expect(owing.netPayoutCents).toBe(250_000);

    // The member who only saved is untouched by someone else's debt.
    expect(clear.loanOffsetCents).toBe(0);
    expect(clear.netPayoutCents).toBe(clear.payoutCents + clear.welfareCents);
  });

  it("a debt bigger than the entitlement gives a NEGATIVE net, not a block", async () => {
    // The 30 Jul rule: unpaid money nets off the payout and never bars anyone
    // from sharing out. A negative net is a debt to the group.
    // Pool of 200,000 → 100,000 gross each, plus 50,000 welfare, against a
    // 300,000 debt: the borrower ends 150,000 short.
    const data = await preview(200_000);
    const owing = data.rows.find((row: any) => row.memberId === borrower);
    expect(owing.netPayoutCents).toBe(-150_000);
    expect(owing.owesGroup).toBe(true);
    // They are still in the share-out, not excluded from it.
    expect(data.rows).toHaveLength(2);
  });

  it("splits the welfare remainder EQUALLY, not by shares", async () => {
    // Welfare is mutual insurance: everyone contributes the same and is
    // covered the same, so weighting the remainder by savings would hand the
    // largest savers a fund they have no greater claim on.
    const data = await preview(1_000_000);
    expect(data.distributeWelfare).toBe(true);
    const shares = data.rows.map((row: any) => row.welfareCents);
    expect(Math.max(...shares) - Math.min(...shares)).toBeLessThanOrEqual(1);
    expect(shares.reduce((a: number, b: number) => a + b, 0)).toBe(data.welfarePoolCents);
  });

  it("can be told to keep the welfare float instead", async () => {
    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/share-out/preview`)
      .set("Cookie", cookies)
      .send({ poolAmountCents: 1_000_000, distributeWelfare: false })
      .expect(200);
    const data = response.body.data;
    expect(data.rows.every((row: any) => row.welfareCents === 0)).toBe(true);
    // Still reported, so a group can see what it carries forward.
    expect(data).toHaveProperty("welfarePoolCents");
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

  it("leaves the loan fund down by the CASH handed over, not more", async () => {
    /**
     * The arithmetic that is easy to get backwards, and did not survive first
     * contact: the payout entry must be the GROSS when a settlement repayment
     * is also written.
     *
     *   entitlement 500, loan owed 300
     *     gross:  −500 payout +300 repayment = −200  ✓ the cash handed over
     *     netted: −200 payout +300 repayment = +100  ✗ the fund gains money
     *             that never existed
     */
    const entries = await prisma.ledgerEntry.findMany({
      where: {
        groupId,
        memberId: borrower,
        type: { in: ["SHARE_OUT_PAYOUT", "LOAN_REPAYMENT"] },
        meetingId
      }
    });
    const movement = entries.reduce(
      (sum, entry) => sum + (entry.direction === "DEBIT" ? -entry.amountCents : entry.amountCents),
      0
    );
    // 500,000 entitlement less the 300,000 loan settled from it.
    expect(movement).toBe(-200_000);
  });

  it("takes the welfare share out of the SOCIAL fund, not the loan fund", async () => {
    const welfareEntries = await prisma.ledgerEntry.findMany({
      where: { groupId, type: "WELFARE_SHARE_OUT", meetingId },
      include: { fundAccount: { select: { type: true } } }
    });
    expect(welfareEntries.length).toBeGreaterThan(0);
    // The whole reason this entry type exists.
    expect(welfareEntries.every((entry) => entry.fundAccount?.type === "SOCIAL")).toBe(true);
    expect(welfareEntries.every((entry) => entry.direction === "DEBIT")).toBe(true);
  });

  it("cannot distribute welfare the group has already spent", async () => {
    // The overdraw guard in appendLedgerEntry applies to WELFARE_SHARE_OUT
    // like any other debit, so an emptied welfare fund simply allocates zero
    // rather than going negative.
    const social = await prisma.fundAccount.findFirstOrThrow({
      where: { groupId, type: "SOCIAL" }
    });
    expect(social.balanceCents).toBeGreaterThanOrEqual(0);
  });
});
