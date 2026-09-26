import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { allocateLargestRemainder } from "../src/domain/share-out";
import { closeCycleWithin } from "../src/services/cycle-service";

const app = createApp();
const DAY = 24 * 60 * 60 * 1000;

async function signIn(role: string) {
  const account = demoAccounts.find((entry) => entry.role === role)!;
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: account.phone, password: demoPassword })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

/**
 * The group statement, checked against figures worked out BY HAND here - never
 * read back from the code under test.
 *
 * Cycle 1: Amina buys KES 1,000 of shares; the cycle is closed with no payout,
 *          so the 1,000 is carried into cycle 2's loan fund.
 * Cycle 2 (current), one held meeting:
 *   shares   Amina 5,000  Baraka 3,000  Chege 2,000          = 10,000
 *   social   50 each (Amina, Baraka, Chege)                   =    150
 *   fine     Baraka 100
 *   welfare  200 paid to Amina out of the social fund
 *   loan     Chege borrows 1,000 at 10 % a month for 1 month, 40 days ago
 *            -> one month's interest 100, owed 1,100; he repays 300
 *   attendance: Amina, Baraka present, Chege late, Dan absent  -> 3 of 4
 * Plus a cancelled meeting and a future reminder plan, neither of which was held.
 *
 *   loan fund   1,000 (opening) + 10,000 + 300 - 1,000 = 10,300
 *   social      150 + 100 - 200                         =     50
 *   outstanding 1,100 - 300                             =    800
 *   equity      10,300 + 800                            = 11,100
 *   capital     10,000 + 1,000 carried                  = 11,000  -> return 0.9 %
 */
describe("the VSLA group statement", () => {
  let admin: string[];
  let groupId: string;
  let members: Record<string, string>;
  let previousCycleId: string;

  beforeAll(async () => {
    await seedDatabase();
    admin = await signIn("IWL_ADMIN");
    const partnerUser = await prisma.user.findFirstOrThrow({ where: { role: "PARTNER_OFFICER" } });
    const programme = await prisma.programme.findFirstOrThrow({ where: { partnerId: partnerUser.partnerId! } });

    const created = await request(app)
      .post("/api/v1/groups")
      .set("Cookie", admin)
      .send({ name: "Statement Test Group", code: `ST-${Date.now()}`, county: "Kisumu", phase: "INTENSIVE", programmeIds: [programme.id] })
      .expect(201);
    groupId = created.body.data.id;
    await request(app)
      .put(`/api/v1/groups/${groupId}/policy`)
      .set("Cookie", admin)
      .send({ loanInterestRateBps: 1000, defaultLoanTermMonths: 1 })
      .expect(200);

    members = {};
    for (const [i, name] of ["Amina Otieno", "Baraka Wekesa", "Chege Kamau", "Dan Mwangi", "Esther Achieng"].entries()) {
      const r = await request(app)
        .post(`/api/v1/groups/${groupId}/members`)
        .set("Cookie", admin)
        .send({ fullName: name, phone: `0712${String(Date.now()).slice(-3)}${i}0${i}` })
        .expect(201);
      members[name.split(" ")[0]!] = r.body.data.id;
    }

    const meeting = async (title: string) =>
      (
        await request(app)
          .post(`/api/v1/groups/${groupId}/meetings`)
          .set("Cookie", admin)
          .send({ title, scheduledAt: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString() })
          .expect(201)
      ).body.data.id as string;
    const post = (meetingId: string, entries: Array<[string, string, number]>) =>
      request(app)
        .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/ledger/batch`)
        .set("Cookie", admin)
        .send({
          entries: entries.map(([name, type, amountCents], index) => ({
            memberId: members[name],
            type,
            amountCents,
            clientRequestId: `st-${meetingId}-${type}-${name}-${index}`
          }))
        })
        .expect(201);

    // Cycle 1, then closed without a payout.
    const first = await meeting("Cycle 1 meeting");
    await post(first, [["Amina", "SHARE_PURCHASE", 100_000]]);
    // Closed without a payout: a legacy cycle, closed before the share-out rule
    // (the route now refuses this), so it goes through the mechanics directly.
    const closed = await prisma.$transaction((tx) => closeCycleWithin(tx, groupId));
    previousCycleId = closed.closed.id;

    // Cycle 2.
    const held = await meeting("Cycle 2 meeting");
    await post(held, [
      ["Amina", "SHARE_PURCHASE", 500_000],
      ["Baraka", "SHARE_PURCHASE", 300_000],
      ["Chege", "SHARE_PURCHASE", 200_000],
      ["Amina", "SOCIAL_CONTRIBUTION", 5_000],
      ["Baraka", "SOCIAL_CONTRIBUTION", 5_000],
      ["Chege", "SOCIAL_CONTRIBUTION", 5_000],
      ["Baraka", "FINE_COLLECTION", 10_000],
      ["Chege", "INTERNAL_LOAN_DISBURSEMENT", 100_000]
    ]);
    await post(held, [["Amina", "WELFARE_EXPENSE", 20_000]]);
    // The loan went out 40 days ago and fell due 10 days ago: one month of interest.
    await prisma.loan.updateMany({
      where: { groupId },
      data: { disbursedAt: new Date(Date.now() - 40 * DAY), dueAt: new Date(Date.now() - 10 * DAY) }
    });
    await post(held, [["Chege", "LOAN_REPAYMENT", 30_000]]);
    for (const [name, status] of [["Amina", "PRESENT"], ["Baraka", "PRESENT"], ["Chege", "LATE"], ["Dan", "ABSENT"]] as const) {
      await request(app)
        .post(`/api/v1/groups/${groupId}/meetings/${held}/attendance`)
        .set("Cookie", admin)
        .send({ memberId: members[name], status })
        .expect((r) => expect([200, 201]).toContain(r.status));
    }

    const cycle = await prisma.cycle.findFirstOrThrow({ where: { groupId, status: "ACTIVE" } });
    await prisma.meeting.create({
      data: { groupId, cycleId: cycle.id, title: "Rained off", status: "CANCELLED", scheduledAt: new Date(Date.now() - DAY) }
    });
    await prisma.meeting.create({
      data: { groupId, cycleId: cycle.id, title: "Next week", status: "SCHEDULED", source: "AUTO_SCHEDULE", scheduledAt: new Date(Date.now() + 5 * DAY) }
    });
  }, 120000);

  async function statement(cookie = admin, query = "") {
    const response = await request(app).get(`/api/v1/reports/group/${groupId}${query}`).set("Cookie", cookie).expect(200);
    return response.body.data;
  }

  it("adds up the current cycle's funds, signed, by hand", async () => {
    const { statement: s } = await statement();
    expect(s.cycle.status).toBe("ACTIVE");
    expect(s.loanFund).toMatchObject({
      openingCents: 100_000,
      sharesCents: 1_000_000,
      repaymentsCents: 30_000,
      disbursedCents: 100_000,
      shareOutPaidCents: 0,
      otherCents: 0,
      closingCents: 1_030_000
    });
    expect(s.socialFund).toMatchObject({
      openingCents: 0,
      contributionsCents: 15_000,
      finesCents: 10_000,
      welfarePaidCents: 20_000,
      otherCents: 0,
      closingCents: 5_000
    });
  });

  it("counts loans with their interest, and repayment against what fell due", async () => {
    const { statement: s } = await statement();
    expect(s.loans).toMatchObject({
      activeCount: 1,
      pastDueCount: 1,
      outstandingCents: 80_000,
      principalOutstandingCents: 70_000,
      par30Cents: 0,
      dueCents: 110_000,
      dueCollectedCents: 30_000,
      // 300 of 1,100 = 27 %. Never repaid / disbursed (which would read 30 %).
      repaymentRate: 27,
      interestCollectedCents: 0
    });
  });

  it("works out equity, return and each member's projected share-out", async () => {
    const { statement: s } = await statement();
    expect(s.equity.totalCents).toBe(1_110_000);
    expect(s.equity.capitalCents).toBe(1_100_000);
    expect(s.equity.returnOnSavings).toBe(0.9);
    expect(s.income).toEqual({ interestCents: 0, finesCents: 10_000, totalCents: 10_000 });

    const byName = Object.fromEntries(s.memberRows.map((row: { fullName: string }) => [row.fullName.split(" ")[0], row]));
    // 11,100 split 5 : 3 : 2 on this cycle's shares.
    expect(byName.Amina.projectedShareOutCents).toBe(555_000);
    expect(byName.Baraka.projectedShareOutCents).toBe(333_000);
    expect(byName.Chege.projectedShareOutCents).toBe(222_000);
    expect(byName.Chege.loanOutstandingCents).toBe(80_000);
    expect(byName.Chege.projectedNetCents).toBe(142_000);
    const projected = s.memberRows.reduce((sum: number, row: { projectedShareOutCents: number }) => sum + row.projectedShareOutCents, 0);
    expect(projected).toBe(s.equity.totalCents);
  });

  it("counts only meetings that were held, and late as there", async () => {
    const { statement: s, group, meetings } = await statement();
    expect(s.meetings).toMatchObject({ held: 1, cancelled: 1, notHeld: 1, attendanceRate: 75 });
    expect(group.meetingCount).toBe(1);
    expect(meetings.attendanceRate).toBe(0.75);
  });

  it("reconciles the ledger with the stored fund balances", async () => {
    const { statement: s } = await statement();
    expect(s.cash.reconciles).toBe(true);
    expect(s.cash.ledgerCents).toBe(1_035_000);
  });

  it("reports an earlier cycle as it closed", async () => {
    const { statement: s } = await statement(admin, `?cycleId=${previousCycleId}`);
    expect(s.cycle.id).toBe(previousCycleId);
    expect(s.loanFund.sharesCents).toBe(100_000);
    expect(s.loanFund.closingCents).toBe(100_000);
    expect(s.cycles.length).toBe(2);
  });

  it("gives a partner the group's figures but no member", async () => {
    const partner = await signIn("PARTNER_OFFICER");
    const data = await statement(partner);
    expect(data.statement.memberRows).toEqual([]);
    expect(data.members).toEqual([]);
    expect(data.statement.loanFund.closingCents).toBe(1_030_000);
    const body = JSON.stringify(data);
    for (const name of ["Amina", "Baraka", "Chege", "Dan", "Esther"]) expect(body).not.toContain(name);

    await request(app).get(`/api/v1/reports/member/${members.Amina}`).set("Cookie", partner).expect(403);
  });

  it("the portfolio report agrees with the statement and names nobody", async () => {
    const partner = await signIn("PARTNER_OFFICER");
    const response = await request(app).get("/api/v1/reports/portfolio-financials").set("Cookie", partner).expect(200);
    const row = response.body.data.groups.find((g: { groupId: string }) => g.groupId === groupId);
    expect(row).toMatchObject({
      shareCapitalCents: 1_000_000,
      loanFundCents: 1_030_000,
      socialFundCents: 5_000,
      loansOutstandingCents: 800_00,
      repaymentRate: 27,
      equityCents: 1_110_000,
      meetingsHeld: 1
    });
    const body = JSON.stringify(response.body);
    for (const name of ["Amina Otieno", "Baraka Wekesa", "Chege Kamau"]) expect(body).not.toContain(name);

    // A group or a member is not given the portfolio.
    const group = await signIn("GROUP_ACCOUNT");
    await request(app).get("/api/v1/reports/portfolio-financials").set("Cookie", group).expect(403);
  });

  it("the admin portfolio covers the whole platform and leaves demo groups out", async () => {
    const response = await request(app).get("/api/v1/reports/portfolio-financials").set("Cookie", admin).expect(200);
    const ids = response.body.data.groups.map((g: { groupId: string }) => g.groupId);
    expect(ids).toContain(groupId);
    const demo = await prisma.group.findMany({ where: { isDemo: true }, select: { id: true } });
    for (const { id } of demo) expect(ids).not.toContain(id);
  });
});

describe("largest-remainder share-out", () => {
  it("adds up to the pool exactly and gives spare cents where rounding cost most", () => {
    expect(allocateLargestRemainder(100, [1, 1, 1])).toEqual([34, 33, 33]);
    expect(allocateLargestRemainder(1000, [333, 333, 334])).toEqual([333, 333, 334]);
    const parts = [123_457, 98_765, 1, 500_000];
    const split = allocateLargestRemainder(9_999_999, parts);
    expect(split.reduce((a, b) => a + b, 0)).toBe(9_999_999);
    // Order does not decide who gets a cent.
    expect(allocateLargestRemainder(10, [1, 2])).toEqual([3, 7]);
    expect(allocateLargestRemainder(10, [2, 1])).toEqual([7, 3]);
  });
});
