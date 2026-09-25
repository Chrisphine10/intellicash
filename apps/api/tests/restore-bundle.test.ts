import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword, type FundType, type LedgerEntryType } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { appendLedgerEntry } from "../src/routes/groups";
import { closeCycleAndOpenNext } from "../src/services/cycle-service";

const app = createApp();

async function cookiesFor(role: "GROUP_ACCOUNT" | "VILLAGE_AGENT" | "IWL_ADMIN" | "MEMBER") {
  const account = demoAccounts.find((candidate) => candidate.role === role)!;
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: account.phone, password: demoPassword })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

/**
 * The snapshot a phone rebuilds a group's record book from. It has to be
 * complete (a loan without its repayments would put the wrong balance in front
 * of a treasurer), say which cycle everything belongs to, and never show one
 * group's money to another.
 */
describe("the restore bundle", () => {
  let groupId: string;
  let otherGroupId: string;
  let alice: string;
  let brian: string;
  let loanEntryId: string;
  let repaymentEntryId: string;
  let meetingId: string;

  const entry = async (
    forGroup: string,
    memberId: string,
    type: LedgerEntryType,
    amountCents: number,
    direction: "CREDIT" | "DEBIT",
    fund: FundType,
    meeting?: string
  ) => {
    const fundAccount = await prisma.fundAccount.findFirstOrThrow({ where: { groupId: forGroup, type: fund } });
    return prisma.$transaction((tx) =>
      appendLedgerEntry(tx, {
        groupId: forGroup,
        memberId,
        meetingId: meeting,
        fundAccountId: fundAccount.id,
        type,
        amountCents,
        direction,
        description: `Test ${type}`
      })
    );
  };

  beforeAll(async () => {
    await seedDatabase();
    const user = await prisma.user.findFirstOrThrow({ where: { role: "GROUP_ACCOUNT" } });
    groupId = user.groupId!;
    otherGroupId = (await prisma.group.findFirstOrThrow({ where: { id: { not: groupId } } })).id;

    // A clean group: two members, one meeting, a loan with a repayment, then a
    // cycle closed and a second one started.
    await prisma.ledgerEntry.deleteMany({ where: { groupId } });
    await prisma.loan.deleteMany({ where: { groupId } });
    await prisma.meeting.deleteMany({ where: { groupId } });
    await prisma.cycle.deleteMany({ where: { groupId } });
    await prisma.group.update({ where: { id: groupId }, data: { cycleNumber: 1 } });
    await prisma.fundAccount.updateMany({ where: { groupId }, data: { balanceCents: 0 } });
    await prisma.member.deleteMany({ where: { groupId } });
    await prisma.groupPolicy.upsert({
      where: { groupId },
      create: { groupId, defaultLoanTermMonths: 2, loanInterestRateBps: 500 },
      update: { defaultLoanTermMonths: 2, loanInterestRateBps: 500 }
    });
    alice = (await prisma.member.create({ data: { groupId, fullName: "Alice Achieng", phone: "254789200001", status: "ACTIVE" } })).id;
    brian = (await prisma.member.create({ data: { groupId, fullName: "Brian Bett", phone: "254789200002", status: "ACTIVE" } })).id;

    // Something belonging to ANOTHER group, which must never appear.
    const stranger = (await prisma.member.create({ data: { groupId: otherGroupId, fullName: "Stranger", phone: "254789200009", status: "ACTIVE" } })).id;
    await entry(otherGroupId, stranger, "SHARE_PURCHASE", 999_999, "CREDIT", "INTERNAL_LOAN");

    const cycle = await prisma.cycle.create({
      data: { id: `cyc_${groupId}_1`, groupId, number: 1, startedAt: new Date(Date.now() - 90 * 24 * 3600 * 1000), status: "ACTIVE" }
    });
    meetingId = (
      await prisma.meeting.create({
        data: { groupId, cycleId: cycle.id, title: "Meeting #1", scheduledAt: new Date(Date.now() - 60 * 24 * 3600 * 1000), status: "SEALED", closedAt: new Date(Date.now() - 60 * 24 * 3600 * 1000) }
      })
    ).id;
    await prisma.attendance.createMany({
      data: [
        { meetingId, memberId: alice, status: "PRESENT" },
        { meetingId, memberId: brian, status: "ABSENT" }
      ]
    });
    await entry(groupId, alice, "SHARE_PURCHASE", 500_000, "CREDIT", "INTERNAL_LOAN", meetingId);
    await entry(groupId, brian, "SHARE_PURCHASE", 300_000, "CREDIT", "INTERNAL_LOAN", meetingId);
    await entry(groupId, alice, "SOCIAL_CONTRIBUTION", 5_000, "CREDIT", "SOCIAL", meetingId);
    loanEntryId = (await entry(groupId, brian, "INTERNAL_LOAN_DISBURSEMENT", 200_000, "DEBIT", "INTERNAL_LOAN", meetingId)).id;
    repaymentEntryId = (await entry(groupId, brian, "LOAN_REPAYMENT", 50_000, "CREDIT", "INTERNAL_LOAN", meetingId)).id;

    await closeCycleAndOpenNext(groupId);
    // One entry in the new cycle.
    await entry(groupId, alice, "SHARE_PURCHASE", 100_000, "CREDIT", "INTERNAL_LOAN");
  }, 60000);

  const fetchBundle = (cookies: string[], forGroup = () => groupId) =>
    request(app).get(`/api/v1/groups/${forGroup()}/restore-bundle`).set("Cookie", cookies);

  it("holds the whole book: cycle, rules, meetings, attendance, entries and loans", async () => {
    const response = await fetchBundle(await cookiesFor("GROUP_ACCOUNT")).expect(200);
    const bundle = response.body.data;

    expect(bundle.group.id).toBe(groupId);
    expect(bundle.group.cycleNumber).toBe(2);
    // The line balances are drawn from: when the open cycle began, not when the group did.
    const active = await prisma.cycle.findFirstOrThrow({ where: { groupId, status: "ACTIVE" } });
    expect(bundle.group.cycleStartedAt).toBe(active.startedAt.toISOString());

    expect(bundle.policy).toMatchObject({ configured: true, loanInterestRateBps: 500, defaultLoanTermMonths: 2 });
    // The group's own rules travel too, so a restored phone computes exactly
    // what the old one did. Unset rules come back as the group row's share
    // settings (or null), never as invented defaults.
    expect(bundle.policy).toMatchObject({ interestType: "FLAT", socialFundCents: null, loanMultiplierBps: null });
    expect(typeof bundle.policy.shareValueCents).toBe("number");
    expect(typeof bundle.policy.maxSharesPerMeeting).toBe("number");

    expect(bundle.meetings).toHaveLength(1);
    expect(bundle.meetings[0]).toMatchObject({ id: meetingId, title: "Meeting #1", cycleNumber: 1 });
    expect(bundle.attendance).toEqual(
      expect.arrayContaining([
        { meetingId, memberId: alice, status: "PRESENT" },
        { meetingId, memberId: brian, status: "ABSENT" }
      ])
    );

    expect(bundle.entries).toHaveLength(6);
    const purchases = bundle.entries.filter((e: { type: string }) => e.type === "SHARE_PURCHASE");
    expect(purchases.map((e: { amountCents: number }) => e.amountCents).sort((a: number, b: number) => a - b)).toEqual([
      100_000, 300_000, 500_000
    ]);
    // Which cycle each row belongs to, so the phone can start this cycle's balances afresh.
    expect(purchases.find((e: { amountCents: number }) => e.amountCents === 100_000).cycleNumber).toBe(2);
    expect(purchases.find((e: { amountCents: number }) => e.amountCents === 500_000).cycleNumber).toBe(1);
  });

  it("says which loan each repayment paid, and which entry made each loan", async () => {
    const bundle = (await fetchBundle(await cookiesFor("GROUP_ACCOUNT")).expect(200)).body.data;

    expect(bundle.loans).toHaveLength(1);
    const loan = bundle.loans[0];
    expect(loan).toMatchObject({
      memberId: brian,
      principalCents: 200_000,
      interestRateBps: 500,
      termMonths: 2,
      status: "ACTIVE",
      disbursementEntryId: loanEntryId,
      cycleNumber: 1
    });
    const repayment = bundle.entries.find((e: { id: string }) => e.id === repaymentEntryId);
    expect(repayment.loanId).toBe(loan.id);
    expect(repayment.amountCents).toBe(50_000);
    expect(repayment.direction).toBe("CREDIT");
  });

  it("never carries another group's money", async () => {
    const bundle = (await fetchBundle(await cookiesFor("GROUP_ACCOUNT")).expect(200)).body.data;
    expect(JSON.stringify(bundle)).not.toContain("999999");
    expect(JSON.stringify(bundle)).not.toContain("Stranger");
  });

  it("is not for a member, or another group's account", async () => {
    await fetchBundle(await cookiesFor("MEMBER")).expect((res) => {
      expect([403, 404]).toContain(res.status);
    });
    // The group's own account, asking for a different group's book.
    const response = await fetchBundle(await cookiesFor("GROUP_ACCOUNT"), () => otherGroupId);
    expect([403, 404]).toContain(response.status);
  });

  it("is open to a platform admin", async () => {
    await fetchBundle(await cookiesFor("IWL_ADMIN")).expect(200);
  });
});
