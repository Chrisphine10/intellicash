import request from "supertest";
import { beforeAll, beforeEach, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword, type FundType, type LedgerEntryType } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { appendLedgerEntry } from "../src/routes/groups";
import { lineArithmeticProblems, shareDifferences } from "../src/services/share-out-record-service";

const app = createApp();
const day = 24 * 3600 * 1000;

async function cookiesFor(role: "GROUP_ACCOUNT" | "VILLAGE_AGENT" | "IWL_ADMIN") {
  const account = demoAccounts.find((candidate) => candidate.role === role)!;
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: account.phone, password: demoPassword })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

/**
 * A share-out done on a phone is recorded on the server as it happened, and the
 * cycle it ended is closed in the same step. These pin what is recorded, what is
 * refused and - above all - that a refusal leaves nothing behind.
 */
describe("recording a share-out done on a phone", () => {
  let groupId: string;
  let a: string;
  let b: string;
  let c: string;
  let otherGroupMember: string;
  let groupCookies: string[];
  let agentCookies: string[];
  let adminCookies: string[];
  let counter = 0;

  const fundId = async (type: FundType) =>
    (await prisma.fundAccount.findFirstOrThrow({ where: { groupId, type } })).id;

  const entry = async (
    memberId: string,
    type: LedgerEntryType,
    amountCents: number,
    direction: "CREDIT" | "DEBIT",
    fund: FundType
  ) => {
    const fundAccountId = await fundId(fund);
    return prisma.$transaction((tx) =>
      appendLedgerEntry(tx, {
        groupId,
        memberId,
        fundAccountId,
        type,
        amountCents,
        direction,
        description: `Test ${type}`
      })
    );
  };

  /**
   * A cycle the server holds in full:
   *   shares  A 5,000.00  B 3,000.00  C 2,000.00  = 10,000.00
   *   A borrowed 2,000.00; the loan is 40 days old, so with 10% for the one-month
   *   term it now owes 2,200.00
   *   welfare (social) fund 300.00
   */
  async function arrangeCycle(options: { withoutSharesOf?: string } = {}) {
    await prisma.ledgerEntry.deleteMany({ where: { groupId } });
    await prisma.loan.deleteMany({ where: { groupId } });
    await prisma.meeting.deleteMany({ where: { groupId } });
    await prisma.cycle.deleteMany({ where: { groupId } });
    await prisma.group.update({ where: { id: groupId }, data: { cycleNumber: 1 } });
    await prisma.fundAccount.updateMany({ where: { groupId }, data: { balanceCents: 0 } });
    await prisma.groupPolicy.upsert({
      where: { groupId },
      create: { groupId, defaultLoanTermMonths: 1, loanInterestRateBps: 1000 },
      update: { defaultLoanTermMonths: 1, loanInterestRateBps: 1000 }
    });

    for (const [member, amount] of [
      [a, 500_000],
      [b, 300_000],
      [c, 200_000]
    ] as const) {
      if (member === options.withoutSharesOf) continue;
      await entry(member, "SHARE_PURCHASE", amount, "CREDIT", "INTERNAL_LOAN");
    }
    const loan = await entry(a, "INTERNAL_LOAN_DISBURSEMENT", 200_000, "DEBIT", "INTERNAL_LOAN");
    const aged = new Date(Date.now() - 40 * day);
    const due = new Date(aged);
    due.setMonth(due.getMonth() + 1);
    await prisma.loan.update({ where: { disbursementEntryId: loan.id }, data: { disbursedAt: aged, dueAt: due } });
    await entry(a, "SOCIAL_CONTRIBUTION", 30_000, "CREDIT", "SOCIAL");
    // A real meeting in the cycle, as a phone's twin would be.
    await prisma.meeting.create({
      data: {
        groupId,
        cycleId: (await activeCycle()).id,
        title: "Meeting #1",
        scheduledAt: new Date(Date.now() - 41 * day),
        status: "SCHEDULED"
      }
    });
  }

  const activeCycle = () => prisma.cycle.findFirstOrThrow({ where: { groupId, status: "ACTIVE" } });

  /** What the phone worked out for that cycle: pool 10,200.00, welfare 300.00 split three ways. */
  const phoneLines = () => [
    { memberId: a, shareCents: 500_000, grossPayoutCents: 510_000, welfarePayoutCents: 10_000, loanOffsetCents: 220_000, netPayoutCents: 300_000 },
    { memberId: b, shareCents: 300_000, grossPayoutCents: 306_000, welfarePayoutCents: 10_000, loanOffsetCents: 0, netPayoutCents: 316_000 },
    { memberId: c, shareCents: 200_000, grossPayoutCents: 204_000, welfarePayoutCents: 10_000, loanOffsetCents: 0, netPayoutCents: 214_000 }
  ];

  const send = (
    cookies: string[],
    body: Record<string, unknown>,
    targetGroupId = () => groupId
  ) => request(app).post(`/api/v1/groups/${targetGroupId()}/share-outs`).set("Cookie", cookies).send(body);

  const newBody = (overrides: Record<string, unknown> = {}) => ({
    shareOutId: `phone-share-out-${++counter}`,
    cycleNumber: 1,
    lines: phoneLines(),
    ...overrides
  });

  const snapshot = async () => ({
    entries: await prisma.ledgerEntry.count({ where: { groupId } }),
    loanFund: (await prisma.fundAccount.findFirstOrThrow({ where: { groupId, type: "INTERNAL_LOAN" } })).balanceCents,
    social: (await prisma.fundAccount.findFirstOrThrow({ where: { groupId, type: "SOCIAL" } })).balanceCents,
    cycles: await prisma.cycle.count({ where: { groupId } }),
    active: (await prisma.cycle.findFirst({ where: { groupId, status: "ACTIVE" } }))?.number
  });

  beforeAll(async () => {
    await seedDatabase();
    const user = await prisma.user.findFirstOrThrow({ where: { role: "GROUP_ACCOUNT" } });
    groupId = user.groupId!;
    groupCookies = await cookiesFor("GROUP_ACCOUNT");
    agentCookies = await cookiesFor("VILLAGE_AGENT");
    adminCookies = await cookiesFor("IWL_ADMIN");

    await prisma.member.deleteMany({ where: { groupId } });
    const make = async (fullName: string, phone: string, inGroup = groupId) =>
      (await prisma.member.create({ data: { groupId: inGroup, fullName, phone, status: "ACTIVE" } })).id;
    a = await make("Alice Achieng", "254789100001");
    b = await make("Brian Bett", "254789100002");
    c = await make("Carol Chebet", "254789100003");
    const other = await prisma.group.findFirstOrThrow({ where: { id: { not: groupId } } });
    otherGroupMember = await make("Someone Elsewhere", "254789100009", other.id);
  }, 60000);

  beforeEach(async () => {
    await arrangeCycle();
  });

  it("records the settlement, the payouts and the welfare split, and closes the cycle", async () => {
    const body = newBody();
    const response = await send(groupCookies, body).expect(201);
    const data = response.body.data;

    expect(data.replayed).toBe(false);
    expect(data.closed.number).toBe(1);
    expect(data.opened.number).toBe(2);
    // one settlement + three payouts + three welfare shares
    expect(data.entries).toBe(7);
    expect(data.totalNetPaidCents).toBe(300_000 + 316_000 + 214_000);

    // The loan fund took in the settled loan and paid every gross payout: empty.
    // The welfare fund paid its 300.00 out.
    const after = await snapshot();
    expect(after.loanFund).toBe(0);
    expect(after.social).toBe(0);
    expect(after.active).toBe(2);

    const payouts = await prisma.ledgerEntry.findMany({
      where: { groupId, type: "SHARE_OUT_PAYOUT" },
      orderBy: { amountCents: "desc" }
    });
    expect(payouts.map((entry) => entry.amountCents)).toEqual([510_000, 306_000, 204_000]);
    expect(payouts.every((entry) => entry.direction === "DEBIT")).toBe(true);

    // The entries read on the console as the close of the last meeting.
    const meeting = await prisma.meeting.findFirstOrThrow({ where: { groupId, title: "Meeting #1" } });
    expect(payouts.every((entry) => entry.meetingId === meeting.id)).toBe(true);

    // The loan the member settled is closed, with nothing left owing.
    const loan = await prisma.loan.findFirstOrThrow({ where: { groupId, memberId: a } });
    expect(loan.status).toBe("REPAID");

    // The cycle that was ended is the one the entries belong to, and it says so.
    const closed = await prisma.cycle.findFirstOrThrow({ where: { groupId, number: 1 } });
    expect(closed.status).toBe("CLOSED");
    expect(closed.closedByShareOutId).toBe(body.shareOutId);
    expect(payouts.every((entry) => entry.cycleId === closed.id)).toBe(true);
    expect((await prisma.group.findUniqueOrThrow({ where: { id: groupId } })).cycleNumber).toBe(2);
  });

  it("answers a retry with what was already recorded and writes nothing more", async () => {
    const body = newBody();
    await send(groupCookies, body).expect(201);
    const once = await snapshot();

    const again = await send(groupCookies, body).expect(200);
    expect(again.body.data.replayed).toBe(true);
    expect(again.body.data.closed.number).toBe(1);
    expect(again.body.data.opened.number).toBe(2);
    expect(await snapshot()).toEqual(once);
  });

  it("refuses a share-out for a cycle the online record has already closed, and writes nothing", async () => {
    await send(groupCookies, newBody()).expect(201);
    const before = await snapshot();

    // The same cycle again under a different id: this is what a console share-out
    // followed by a phone share-out looks like. Recording it would pay twice.
    const response = await send(groupCookies, newBody()).expect(409);
    expect(response.body.error.code).toBe("SHARE_OUT_CYCLE_CLOSED");
    expect(response.body.error.message).toMatch(/already on Cycle 2/);
    expect(await snapshot()).toEqual(before);
  });

  it("refuses a cycle the online record has not reached yet", async () => {
    const response = await send(groupCookies, newBody({ cycleNumber: 3 })).expect(409);
    expect(response.body.error.code).toBe("SHARE_OUT_CYCLE_AHEAD");
    expect(response.body.error.message).toMatch(/Send the earlier cycle first/);
  });

  it("refuses when the online share purchases are not the phone's, naming who differs", async () => {
    await arrangeCycle({ withoutSharesOf: c });
    const before = await snapshot();

    const response = await send(groupCookies, newBody()).expect(409);
    expect(response.body.error.code).toBe("SHARE_OUT_OUT_OF_STEP");
    expect(response.body.error.message).toMatch(/Carol Chebet: phone KES 2,000\.00, online KES 0\.00/);
    expect(await snapshot()).toEqual(before);
  });

  it("records it anyway when told to, if the money is there to pay it", async () => {
    // The phone counted 2,000.00 of Carol's shares the server never received, so
    // the online loan fund is short by exactly that and cannot meet the payouts.
    await arrangeCycle({ withoutSharesOf: c });
    const short = await send(groupCookies, newBody({ force: true })).expect(409);
    expect(short.body.error.code).toBe("SHARE_OUT_FUND_SHORT");

    // Once the fund does hold it (however that came about), forcing succeeds.
    await prisma.fundAccount.updateMany({
      where: { groupId, type: "INTERNAL_LOAN" },
      data: { balanceCents: { increment: 200_000 } }
    });
    const forced = await send(groupCookies, newBody({ force: true })).expect(201);
    expect(forced.body.data.closed.number).toBe(1);
  });

  it("leaves nothing behind when the loan fund cannot cover the payouts", async () => {
    // 1.00 too much for the fund to pay.
    const lines = phoneLines();
    lines[1] = { ...lines[1]!, grossPayoutCents: 306_000 + 100_000, netPayoutCents: 416_000 };
    const before = await snapshot();

    const response = await send(groupCookies, newBody({ lines, force: true })).expect(409);
    expect(response.body.error.code).toBe("SHARE_OUT_FUND_SHORT");
    // Not even the settlement that was posted first stayed.
    expect(await snapshot()).toEqual(before);
  });

  it("does not close the cycle, or keep the payouts, while a meeting is open on the console", async () => {
    await prisma.meeting.create({
      data: {
        groupId,
        cycleId: (await activeCycle()).id,
        title: "Running now",
        scheduledAt: new Date(),
        status: "IN_PROGRESS",
        openedAt: new Date()
      }
    });
    const before = await snapshot();

    const response = await send(groupCookies, newBody()).expect(409);
    expect(response.body.error.code).toBe("CYCLE_HAS_OPEN_MEETINGS");
    expect(await snapshot()).toEqual(before);
  });

  it("refuses a payout that does not add up", async () => {
    const lines = phoneLines();
    lines[0] = { ...lines[0]!, netPayoutCents: 300_001 };
    const before = await snapshot();

    const response = await send(groupCookies, newBody({ lines })).expect(400);
    expect(response.body.error.code).toBe("SHARE_OUT_ARITHMETIC");
    expect(await snapshot()).toEqual(before);
  });

  it("refuses a member who is not in this group", async () => {
    const lines = [...phoneLines(), { memberId: otherGroupMember, shareCents: 0, grossPayoutCents: 0, welfarePayoutCents: 0, loanOffsetCents: 0, netPayoutCents: 0 }];
    const response = await send(groupCookies, newBody({ lines })).expect(404);
    expect(response.body.error.code).toBe("MEMBER_NOT_FOUND");
  });

  it("refuses the same member twice", async () => {
    const lines = [...phoneLines(), phoneLines()[0]!];
    const response = await send(groupCookies, newBody({ lines })).expect(400);
    expect(response.body.error.code).toBe("SHARE_OUT_DUPLICATE_MEMBER");
  });

  it("does not let a village agent end a group's cycle", async () => {
    const before = await snapshot();
    const response = await send(agentCookies, newBody());
    expect([403, 404]).toContain(response.status);
    expect(await snapshot()).toEqual(before);
  });

  it("lets a platform admin record it", async () => {
    await send(adminCookies, newBody()).expect(201);
  });

  it("records a member who ends up owing the group without paying them out", async () => {
    // A owes 2,200.00 but is owed only 1,000.00: nets to -1,200.00, a debt to the group.
    const lines = [
      { memberId: a, shareCents: 500_000, grossPayoutCents: 100_000, welfarePayoutCents: 0, loanOffsetCents: 220_000, netPayoutCents: -120_000 },
      { memberId: b, shareCents: 300_000, grossPayoutCents: 500_000, welfarePayoutCents: 0, loanOffsetCents: 0, netPayoutCents: 500_000 },
      { memberId: c, shareCents: 200_000, grossPayoutCents: 420_000, welfarePayoutCents: 0, loanOffsetCents: 0, netPayoutCents: 420_000 }
    ];
    const response = await send(groupCookies, newBody({ lines })).expect(201);
    expect(response.body.data.membersOwing).toEqual([{ memberId: a, owedCents: 120_000 }]);
    // Only the cash that actually leaves is counted as paid.
    expect(response.body.data.totalNetPaidCents).toBe(920_000);
    expect((await snapshot()).loanFund).toBe(0);
  });

  describe("the checks on their own", () => {
    it("finds a line whose net is not payout + welfare - loan settled", () => {
      const good = phoneLines();
      expect(lineArithmeticProblems(good)).toEqual([]);
      expect(lineArithmeticProblems([{ ...good[0]!, netPayoutCents: 1 }])).toHaveLength(1);
    });

    it("compares what each member put in on the phone and online", () => {
      const phone = new Map([["a", 500], ["b", 300]]);
      expect(shareDifferences(phone, new Map([["a", 500], ["b", 300]]))).toEqual([]);
      expect(shareDifferences(phone, new Map([["a", 500]]))).toEqual([{ memberId: "b", phoneCents: 300, onlineCents: 0 }]);
      // Someone the phone does not know about is a difference too.
      expect(shareDifferences(phone, new Map([["a", 500], ["b", 300], ["z", 50]]))).toEqual([
        { memberId: "z", phoneCents: 0, onlineCents: 50 }
      ]);
    });
  });
});
