import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import bcrypt from "bcryptjs";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();
const PASSWORD = "Overview#2026";

/**
 * The combined report: what one person has saved across every group.
 *
 * Its whole value is that the totals are right, so these check arithmetic
 * against figures written directly into the ledger — not just that the
 * endpoint answers.
 */
describe("a member's report across all their groups", () => {
  let member: ReturnType<typeof request.agent>;
  let groupA: { id: string; name: string };
  let groupB: { id: string; name: string };

  beforeAll(async () => {
    await seedDatabase();
    const a = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0001" } });
    const b = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0002" } });
    groupA = { id: a.id, name: a.name };
    groupB = { id: b.id, name: b.name };

    await prisma.user.deleteMany({ where: { email: "overview@example.com" } });
    await prisma.member.deleteMany({ where: { phone: "254787000111" } });

    const inA = await prisma.member.create({
      data: { groupId: a.id, fullName: "Saves With Two", phone: "254787000111", status: "ACTIVE" }
    });
    const inB = await prisma.member.create({
      data: { groupId: b.id, fullName: "Saves With Two", phone: "254787000111", status: "ACTIVE" }
    });
    const user = await prisma.user.create({
      data: {
        name: "Saves With Two",
        email: "overview@example.com",
        phone: "254787000111",
        passwordHash: await bcrypt.hash(PASSWORD, 12),
        role: "MEMBER",
        memberId: inA.id,
        groupId: a.id
      }
    });
    await prisma.userMembership.createMany({
      data: [
        { userId: user.id, memberId: inA.id, groupId: a.id },
        { userId: user.id, memberId: inB.id, groupId: b.id }
      ]
    });

    // Known figures, so the totals can be checked rather than trusted.
    const sample = await prisma.ledgerEntry.findFirstOrThrow({
      select: { currency: true, signature: true }
    });
    const fundA = await prisma.fundAccount.findFirstOrThrow({ where: { groupId: a.id } });
    const fundB = await prisma.fundAccount.findFirstOrThrow({ where: { groupId: b.id } });
    const base = { currency: sample.currency, signature: "overview-test" };

    await prisma.ledgerEntry.createMany({
      data: [
        // Group A: 5,000 shares + 1,000 social; borrowed 3,000, repaid 1,000.
        { ...base, groupId: a.id, memberId: inA.id, fundAccountId: fundA.id, type: "SHARE_PURCHASE", direction: "CREDIT", amountCents: 500_000, description: "A shares" },
        { ...base, groupId: a.id, memberId: inA.id, fundAccountId: fundA.id, type: "SOCIAL_CONTRIBUTION", direction: "CREDIT", amountCents: 100_000, description: "A social" },
        { ...base, groupId: a.id, memberId: inA.id, fundAccountId: fundA.id, type: "INTERNAL_LOAN_DISBURSEMENT", direction: "DEBIT", amountCents: 300_000, description: "A loan" },
        { ...base, groupId: a.id, memberId: inA.id, fundAccountId: fundA.id, type: "LOAN_REPAYMENT", direction: "CREDIT", amountCents: 100_000, description: "A repayment" },
        // Group B: 2,500 shares; borrowed 1,000 and repaid 1,500 (overpaid).
        { ...base, groupId: b.id, memberId: inB.id, fundAccountId: fundB.id, type: "SHARE_PURCHASE", direction: "CREDIT", amountCents: 250_000, description: "B shares" },
        { ...base, groupId: b.id, memberId: inB.id, fundAccountId: fundB.id, type: "INTERNAL_LOAN_DISBURSEMENT", direction: "DEBIT", amountCents: 100_000, description: "B loan" },
        { ...base, groupId: b.id, memberId: inB.id, fundAccountId: fundB.id, type: "LOAN_REPAYMENT", direction: "CREDIT", amountCents: 150_000, description: "B repayment" }
      ]
    });

    member = request.agent(app);
    await member
      .post("/api/v1/auth/login")
      .send({ email: "overview@example.com", password: PASSWORD })
      .expect(200);
  }, 60000);

  it("lists every group the person saves with", async () => {
    const res = await member.get("/api/v1/members/me/overview").expect(200);
    expect(res.body.data.groupCount).toBe(2);
    expect(res.body.data.groups.map((g: any) => g.member.group.name).sort()).toEqual(
      [groupA.name, groupB.name].sort()
    );
    // Exactly one is the group currently in view.
    expect(res.body.data.groups.filter((g: any) => g.isActive)).toHaveLength(1);
  });

  it("adds shares and savings across groups correctly", async () => {
    const { combined } = (await member.get("/api/v1/members/me/overview").expect(200)).body.data;
    expect(combined.sharesCents).toBe(500_000 + 250_000);
    expect(combined.socialCents).toBe(100_000);
    expect(combined.totalPaidInCents).toBe(500_000 + 100_000 + 250_000);
  });

  it("tracks loans taken and repaid across groups", async () => {
    const { combined } = (await member.get("/api/v1/members/me/overview").expect(200)).body.data;
    expect(combined.loansReceivedCents).toBe(300_000 + 100_000);
    expect(combined.loansRepaidCents).toBe(100_000 + 150_000);
  });

  it("does not let an overpayment in one group cancel a debt in another", async () => {
    // Group A still owes 2,000. Group B overpaid by 500. Naively subtracting
    // would report 1,500 owed and understate a real debt.
    const { combined, groups } = (await member.get("/api/v1/members/me/overview").expect(200))
      .body.data;
    const a = groups.find((g: any) => g.member.group.id === groupA.id);
    const b = groups.find((g: any) => g.member.group.id === groupB.id);
    expect(a.summary.loanOutstandingCents).toBe(200_000);
    expect(b.summary.loanOutstandingCents).toBe(0);
    expect(combined.loanOutstandingCents).toBe(200_000);
  });

  it("agrees exactly with the single-group passbook for the group in view", async () => {
    const overview = (await member.get("/api/v1/members/me/overview").expect(200)).body.data;
    const passbook = (await member.get("/api/v1/members/me").expect(200)).body.data;
    const active = overview.groups.find((g: any) => g.isActive);
    // Same builder both sides, so this must be identical, not merely close.
    expect(active.summary).toEqual(passbook.summary);
  });

  it("follows the member when they switch which group is in view", async () => {
    await member
      .post("/api/v1/members/me/active-membership")
      .send({ groupId: groupB.id })
      .expect(200);

    const overview = (await member.get("/api/v1/members/me/overview").expect(200)).body.data;
    const active = overview.groups.find((g: any) => g.isActive);
    expect(active.member.group.id).toBe(groupB.id);
    // The combined totals are unchanged — switching is a view, not a filter.
    expect(overview.combined.sharesCents).toBe(750_000);
  });

  it("keeps money in integer cents throughout", async () => {
    const { combined, groups } = (await member.get("/api/v1/members/me/overview").expect(200))
      .body.data;
    for (const value of Object.values(combined)) {
      expect(Number.isInteger(value as number)).toBe(true);
    }
    for (const g of groups) {
      for (const value of Object.values(g.summary)) {
        expect(Number.isInteger(value as number)).toBe(true);
      }
    }
  });

  it("is refused to accounts that are not members", async () => {
    for (const email of ["group@intellicash.co.ke", "agent@intellicash.co.ke"]) {
      const other = request.agent(app);
      await other
        .post("/api/v1/auth/login")
        .send({ email, password: "IntellicashDemo#2026" })
        .expect(200);
      const res = await other.get("/api/v1/members/me/overview").expect(400);
      expect(res.body.error.code).toBe("NOT_A_MEMBER_ACCOUNT");
    }
  });

  it("cannot be read without signing in", async () => {
    await request(app).get("/api/v1/members/me/overview").expect(401);
  });

  /**
   * The single-group passbook learned about interest when `Loan` landed; this
   * rollup did not — it summed only the ledger difference, disbursed minus
   * repaid. Someone borrowing in two groups was therefore shown a combined
   * debt smaller than either group would actually collect. Interest is where
   * the group's money is, so understating it is not a rounding problem.
   */
  describe("interest, welfare and share-out reach the combined figures", () => {
    beforeAll(async () => {
      // Two loans, 10,000.00 each at 10% a month with one month elapsed, so
      // 1,000.00 of interest apiece under the flat-monthly rule.
      const memberships = await prisma.userMembership.findMany({
        where: { user: { email: "overview@example.com" } },
        select: { memberId: true, groupId: true }
      });
      expect(memberships).toHaveLength(2);

      for (const membership of memberships) {
        const cycle = await prisma.cycle.findFirst({
          where: { groupId: membership.groupId, status: "ACTIVE" }
        });
        await prisma.loan.create({
          data: {
            groupId: membership.groupId,
            memberId: membership.memberId,
            cycleId: cycle?.id ?? null,
            principalCents: 1_000_000,
            interestRateBps: 1000,
            termMonths: 3,
            disbursedAt: new Date(Date.now() - 31 * 24 * 3600 * 1000),
            dueAt: new Date(Date.now() + 60 * 24 * 3600 * 1000),
            status: "ACTIVE"
          }
        });
      }
    }, 60000);

    it("rolls up interest instead of dropping it", async () => {
      const { combined } = (await member.get("/api/v1/members/me/overview").expect(200)).body.data;
      expect(combined.loanInterestCents).toBe(200_000);
    });

    it("the interest-aware total is knowably HIGHER than the legacy one", async () => {
      // The legacy field stays for older clients. Asserting the gap is the
      // point: anyone tempted to collapse these back into one field breaks
      // this test rather than a member's statement.
      const { combined } = (await member.get("/api/v1/members/me/overview").expect(200)).body.data;
      expect(combined.loanOutstandingWithInterestCents).toBeGreaterThan(
        combined.loanOutstandingCents
      );
    });

    it("the combined figure equals the sum of the per-group figures", async () => {
      // A member checking the total against each group's own page must not
      // find a discrepancy — that is how trust is lost in a meeting.
      const { combined, groups } = (await member.get("/api/v1/members/me/overview").expect(200))
        .body.data;
      const perGroup = groups.reduce(
        (total: number, group: any) => total + group.summary.loanOutstandingWithInterestCents,
        0
      );
      expect(combined.loanOutstandingWithInterestCents).toBe(perGroup);
    });

    it("carries welfare received and share-out history into the rollup", async () => {
      const { combined, groups } = (await member.get("/api/v1/members/me/overview").expect(200))
        .body.data;
      expect(combined).toHaveProperty("welfareReceivedCents");
      expect(combined).toHaveProperty("shareOutReceivedCents");
      expect(combined.welfareReceivedCents).toBe(
        groups.reduce((t: number, g: any) => t + g.summary.welfareReceivedCents, 0)
      );
      expect(combined.shareOutReceivedCents).toBe(
        groups.reduce((t: number, g: any) => t + g.summary.shareOutReceivedCents, 0)
      );
    });

    it("still keeps every combined figure in integer cents", async () => {
      const { combined } = (await member.get("/api/v1/members/me/overview").expect(200)).body.data;
      for (const value of Object.values(combined)) {
        expect(Number.isInteger(value as number)).toBe(true);
      }
    });
  });
});
