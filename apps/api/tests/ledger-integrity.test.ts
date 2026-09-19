import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * Found in QA. The direct ledger route let the caller choose a type, a
 * direction and a fund independently, so a "loan disbursement" could be booked
 * as money coming IN (creating a loan the fund never paid), a share purchase as
 * money going OUT, or a social contribution into the loan fund. And an amount
 * or balance past what the database column holds came back as "Something went
 * wrong on our side".
 */
describe("the ledger keeps a type, a direction and a fund in agreement", () => {
  let cookies: string[];
  let groupId: string;
  let loanFundId: string;
  let socialFundId: string;
  let memberId: string;

  const post = (body: Record<string, unknown>) =>
    request(app)
      .post(`/api/v1/groups/${groupId}/ledger`)
      .set("Cookie", cookies)
      .send({ memberId, description: "Integrity test", ...body });
  const balance = async (id: string) => (await prisma.fundAccount.findUniqueOrThrow({ where: { id } })).balanceCents;

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirstOrThrow({ orderBy: { createdAt: "asc" } });
    groupId = group.id;
    const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
    const login = await request(app).post("/api/v1/auth/login").send({ phone: admin.phone, password: demoPassword }).expect(200);
    const cookie = login.headers["set-cookie"];
    cookies = Array.isArray(cookie) ? cookie : [cookie as unknown as string];

    loanFundId = (await prisma.fundAccount.findFirstOrThrow({ where: { groupId, type: "INTERNAL_LOAN" } })).id;
    socialFundId = (await prisma.fundAccount.findFirstOrThrow({ where: { groupId, type: "SOCIAL" } })).id;
    memberId = (await prisma.member.findFirstOrThrow({ where: { groupId, status: "ACTIVE" } })).id;
    await prisma.fundAccount.update({ where: { id: loanFundId }, data: { balanceCents: 500_000 } });
    await prisma.fundAccount.update({ where: { id: socialFundId }, data: { balanceCents: 0 } });
  }, 60000);

  const wrong: Array<[string, Record<string, unknown>, RegExp]> = [
    ["a share purchase as money out", { type: "SHARE_PURCHASE", direction: "DEBIT", amountCents: 1000 }, /Share purchase must be recorded as money into the loan fund/],
    ["a repayment as money out", { type: "LOAN_REPAYMENT", direction: "DEBIT", amountCents: 1000 }, /Loan repayment must be recorded as money into the loan fund/],
    ["a loan disbursement as money in", { type: "INTERNAL_LOAN_DISBURSEMENT", direction: "CREDIT", amountCents: 1000 }, /Loan disbursement must be recorded as money out of the loan fund/],
    ["a social contribution into the loan fund", { type: "SOCIAL_CONTRIBUTION", direction: "CREDIT", amountCents: 1000 }, /Social fund contribution must be recorded as money into the social fund/],
    ["a welfare expense out of the loan fund", { type: "WELFARE_EXPENSE", direction: "DEBIT", amountCents: 1000 }, /Welfare expense must be recorded as money out of the social fund/]
  ];

  for (const [name, body, message] of wrong) {
    it(`refuses ${name}, and changes nothing`, async () => {
      const fundBefore = await balance(loanFundId);
      const rowsBefore = await prisma.ledgerEntry.count({ where: { groupId } });
      const loansBefore = await prisma.loan.count({ where: { groupId } });
      const response = await post({ ...body, fundAccountId: loanFundId }).expect(400);
      expect(response.body.error.code).toBe("LEDGER_ENTRY_MISMATCH");
      expect(response.body.error.message).toMatch(message);
      expect(await balance(loanFundId)).toBe(fundBefore);
      expect(await prisma.ledgerEntry.count({ where: { groupId } })).toBe(rowsBefore);
      // The one that mattered most: no phantom loan from a "disbursement" that paid nothing.
      expect(await prisma.loan.count({ where: { groupId } })).toBe(loansBefore);
    });
  }

  it("accepts a consistent entry and moves the fund by exactly its amount", async () => {
    await post({ type: "SOCIAL_CONTRIBUTION", direction: "CREDIT", fundAccountId: socialFundId, amountCents: 2500 }).expect(201);
    expect(await balance(socialFundId)).toBe(2500);
  });

  it("refuses an amount the database cannot hold with a sentence, not a server error", async () => {
    const response = await post({ type: "SOCIAL_CONTRIBUTION", direction: "CREDIT", fundAccountId: socialFundId, amountCents: 2_147_483_648 }).expect(400);
    expect(response.body.error.message).toBe("That amount is too large to record. The most one figure can hold is KES 21,474,836.47.");
  });

  it("refuses to take a fund past what it can hold, and leaves it untouched", async () => {
    await prisma.fundAccount.update({ where: { id: socialFundId }, data: { balanceCents: 2_147_483_647 } });
    const response = await post({ type: "SOCIAL_CONTRIBUTION", direction: "CREDIT", fundAccountId: socialFundId, amountCents: 1 }).expect(400);
    expect(response.body.error.code).toBe("FUND_BALANCE_LIMIT");
    expect(await balance(socialFundId)).toBe(2_147_483_647);
  });
});
