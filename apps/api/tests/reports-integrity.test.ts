import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { buildGroupStatement } from "../src/services/vsla-statement-service";
import { buildRestoreBundle, shareValueFromHistory } from "../src/services/restore-bundle-service";
import { groupRules } from "../src/services/group-rules-service";

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
 * What the production rehearsal of 25 Sep 2026 and the report review found,
 * each pinned with figures worked out by hand.
 *
 * One group, one cycle:
 *   shares   Amina 6,000  Baraka 4,000                    = 10,000
 *   loan     Baraka borrows 2,000 at 10 % a month, 1 month, 40 days ago
 *            -> owed 2,200; he pays 2,500 (300 over)
 *   loan fund after: 10,000 - 2,000 + 2,500              = 10,500
 *   interest earned: 200 (the 300 overpaid is his money, not interest)
 * Then the whole loan fund (10,500) is shared out on the web.
 */
describe("reports and savings records hold together", () => {
  let admin: string[];
  let groupId: string;
  let meetingId: string;
  let amina: string;
  let baraka: string;

  const post = (entries: Array<[string, string, number]>, source?: "WEB") =>
    request(app)
      .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/ledger/batch`)
      .set("Cookie", admin)
      .send({
        ...(source ? { source } : {}),
        entries: entries.map(([memberId, type, amountCents], index) => ({
          memberId,
          type,
          amountCents,
          clientRequestId: `ri-${meetingId}-${type}-${memberId}-${index}-${amountCents}`
        }))
      });

  beforeAll(async () => {
    await seedDatabase();
    admin = await signIn("IWL_ADMIN");
    const partnerUser = await prisma.user.findFirstOrThrow({ where: { role: "PARTNER_OFFICER" } });
    const programme = await prisma.programme.findFirstOrThrow({ where: { partnerId: partnerUser.partnerId! } });
    const created = await request(app)
      .post("/api/v1/groups")
      .set("Cookie", admin)
      .send({ name: "Report Integrity Group", code: `RI-${Date.now()}`, county: "Kisumu", phase: "INTENSIVE", programmeIds: [programme.id] })
      .expect(201);
    groupId = created.body.data.id;
    await request(app)
      .put(`/api/v1/groups/${groupId}/policy`)
      .set("Cookie", admin)
      .send({ loanInterestRateBps: 1000, defaultLoanTermMonths: 1 })
      .expect(200);
    const add = async (fullName: string, i: number) =>
      (
        await request(app)
          .post(`/api/v1/groups/${groupId}/members`)
          .set("Cookie", admin)
          .send({ fullName, phone: `0713${String(Date.now()).slice(-4)}${i}${i}` })
          .expect(201)
      ).body.data.id as string;
    amina = await add("Amina Report", 1);
    baraka = await add("Baraka Report", 2);
    meetingId = (
      await request(app)
        .post(`/api/v1/groups/${groupId}/meetings`)
        .set("Cookie", admin)
        .send({ title: "Report meeting", scheduledAt: new Date(Date.now() - 60 * 60 * 1000).toISOString() })
        .expect(201)
    ).body.data.id;

    await post([
      [amina, "SHARE_PURCHASE", 600_000],
      [baraka, "SHARE_PURCHASE", 400_000],
      [baraka, "INTERNAL_LOAN_DISBURSEMENT", 200_000]
    ]).expect(201);
    await prisma.loan.updateMany({
      where: { groupId, memberId: baraka },
      data: { disbursedAt: new Date(Date.now() - 40 * DAY), dueAt: new Date(Date.now() - 10 * DAY) }
    });
    await post([[baraka, "LOAN_REPAYMENT", 250_000]]).expect(201);
  }, 90_000);

  it("counts an overpayment as the member's money, not interest earned", async () => {
    const statement = (await buildGroupStatement(groupId))!;
    expect(statement.loanFund.closingCents).toBe(1_050_000);
    expect(statement.loans.interestCollectedCents).toBe(20_000);
  });

  it("shows the same savings on the dashboard, the group list and the statement", async () => {
    const statement = (await buildGroupStatement(groupId))!;
    expect(statement.loanFund.sharesCents).toBe(1_000_000);
    const list = await request(app).get("/api/v1/groups").set("Cookie", admin).expect(200);
    const rows = (list.body.data.items ?? list.body.data) as Array<{ id: string; totalSavingsCents: number }>;
    expect(rows.find((row) => row.id === groupId)?.totalSavingsCents).toBe(1_000_000);
  });

  it("does not hold a group to share rules it never set", async () => {
    // The group row says KSh 500 a share (a schema default); nobody chose it.
    expect((await groupRules(prisma, groupId)).shareValueCents).toBeNull();
    await post([[amina, "SHARE_PURCHASE", 5_000]], "WEB").expect(201);
  });

  it("gives a restored phone the share value the group's own book shows", async () => {
    expect(shareValueFromHistory([{ type: "SHARE_PURCHASE", amountCents: 5_000 }, { type: "SHARE_PURCHASE", amountCents: 15_000 }], 50_000)).toBe(5_000);
    expect(shareValueFromHistory([{ type: "SHARE_PURCHASE", amountCents: 100_000 }], 50_000)).toBe(50_000);
    expect(shareValueFromHistory([], 50_000)).toBe(50_000);
    const bundle = await buildRestoreBundle(groupId);
    // 6,000 + 4,000 + 50 bought: every purchase is a multiple of 50, not of 500.
    expect(bundle.policy.shareValueCents).toBe(5_000);
    // Baraka's 2,500 cleared his 2,200 loan; the 300 over is his, not the loan's.
    const repayment = await prisma.ledgerEntry.findFirstOrThrow({ where: { groupId, memberId: baraka, type: "LOAN_REPAYMENT" } });
    expect(bundle.allocations.filter((slice) => slice.repaymentEntryId === repayment.id)).toEqual([
      expect.objectContaining({ cents: 220_000 })
    ]);
  });

  describe("a share-out done on the web", () => {
    let posted: { closed: { id: string; number: number }; opened: { id: string } };

    it("closes the cycle it pays out, and seals its meeting", async () => {
      const response = await request(app)
        .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/share-out/post`)
        .set("Cookie", admin)
        .send({ poolAmountCents: 1_055_000, distributeWelfare: false })
        .expect(201);
      posted = response.body.data;
      expect(posted.closed.number).toBe(1);
      const cycle = await prisma.cycle.findUniqueOrThrow({ where: { id: posted.closed.id } });
      expect(cycle.status).toBe("CLOSED");
      expect(cycle.closedByShareOutId).toBe(`web-shareout-${meetingId}`);
      expect((await prisma.meeting.findUniqueOrThrow({ where: { id: meetingId } })).status).toBe("SEALED");
    });

    it("is recognised when sent again, not paid a second time", async () => {
      const again = await request(app)
        .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/share-out/post`)
        .set("Cookie", admin)
        .send({ poolAmountCents: 1_055_000, distributeWelfare: false })
        .expect(201);
      expect(again.body.data.replayed).toBe(true);
      const payouts = await prisma.ledgerEntry.count({ where: { groupId, type: "SHARE_OUT_PAYOUT" } });
      expect(payouts).toBe(2);
    });

    it("leaves the new cycle with no savings yet, everywhere", async () => {
      const current = (await buildGroupStatement(groupId))!;
      expect(current.cycle.number).toBe(2);
      expect(current.loanFund.sharesCents).toBe(0);
      const list = await request(app).get("/api/v1/groups").set("Cookie", admin).expect(200);
      const rows = (list.body.data.items ?? list.body.data) as Array<{ id: string; totalSavingsCents: number }>;
      expect(rows.find((row) => row.id === groupId)?.totalSavingsCents).toBe(0);
    });

    it("reports the closed cycle by what it was worth, not a near -100% loss", async () => {
      const closed = (await buildGroupStatement(groupId, { cycleId: posted.closed.id }))!;
      // Paid out 10,550 of a fund that held 10,550: equity is what was shared.
      expect(closed.loanFund.shareOutPaidCents).toBe(1_055_000);
      expect(closed.equity.totalCents).toBe(1_055_000);
      // Capital 10,050 of shares grew to 10,550: about +5 %, not -100 %.
      expect(closed.equity.returnOnSavings).toBeCloseTo(5, 0);
    });

    it("refuses to share out a cycle nobody has saved in", async () => {
      const next = (
        await request(app)
          .post(`/api/v1/groups/${groupId}/meetings`)
          .set("Cookie", admin)
          .send({ title: "Next cycle", scheduledAt: new Date().toISOString() })
          .expect(201)
      ).body.data.id;
      const refused = await request(app)
        .post(`/api/v1/groups/${groupId}/meetings/${next}/share-out/post`)
        .set("Cookie", admin)
        .send({ poolAmountCents: 10_000 })
        .expect(409);
      expect(refused.body.error.code).toBe("NOTHING_TO_SHARE_OUT");
    });
  });
});
