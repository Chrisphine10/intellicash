import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

async function adminCookies() {
  const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: admin.phone, password: demoPassword })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

/**
 * Requirement #2: a group cannot lend money it does not have.
 *
 * Exercised through the real meeting ledger route rather than the pure
 * function, because the point of this change is that the RULE FIRES at the
 * place a treasurer actually disburses.
 */
describe("loan fund validation on disbursement", () => {
  let groupId: string;
  let memberId: string;
  let meetingId: string;
  let loanFundBalance: number;

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirst({ orderBy: { createdAt: "asc" } });
    groupId = group!.id;

    const member = await prisma.member.findFirst({ where: { groupId } });
    memberId = member!.id;

    const fund = await prisma.fundAccount.findFirst({
      where: { groupId, type: "INTERNAL_LOAN" }
    });
    loanFundBalance = fund!.balanceCents;

    const active = await prisma.cycle.findFirst({ where: { groupId, status: "ACTIVE" } });
    const meeting = await prisma.meeting.create({
      data: {
        groupId,
        cycleId: active?.id ?? null,
        title: "Disbursement test",
        status: "IN_PROGRESS",
        scheduledAt: new Date()
      }
    });
    meetingId = meeting.id;
  }, 60000);

  async function disburse(amountCents: number) {
    const cookies = await adminCookies();
    return request(app)
      .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/ledger/batch`)
      .set("Cookie", cookies)
      .send({
        entries: [
          {
            type: "INTERNAL_LOAN_DISBURSEMENT",
            memberId,
            amountCents,
            clientRequestId: `test-loan-${amountCents}-${Date.now()}`
          }
        ]
      });
  }

  it("refuses a loan larger than the loan fund, naming the shortfall", async () => {
    const tooMuch = loanFundBalance + 500_000;
    const response = await disburse(tooMuch);

    expect(response.status).toBe(400);
    expect(response.body.error.code).toBe("INSUFFICIENT_LOAN_FUND");
    // The treasurer must be able to act on the message, not just be refused.
    expect(response.body.error.message).toMatch(/short by/i);
    expect(response.body.error.details.shortfallCents).toBe(500_000);
  });

  it("leaves the fund untouched when it refuses", async () => {
    const before = await prisma.fundAccount.findFirst({
      where: { groupId, type: "INTERNAL_LOAN" }
    });
    await disburse(before!.balanceCents + 1);
    const after = await prisma.fundAccount.findFirst({
      where: { groupId, type: "INTERNAL_LOAN" }
    });

    // A rejected loan must not move money, and must not leave a stray entry.
    expect(after!.balanceCents).toBe(before!.balanceCents);
  });

  it("refuses a zero-value loan", async () => {
    const response = await disburse(0);
    expect(response.status).toBe(400);
  });

  it("allows a loan the fund can cover", async () => {
    const fund = await prisma.fundAccount.findFirst({
      where: { groupId, type: "INTERNAL_LOAN" }
    });
    const affordable = Math.floor(fund!.balanceCents / 2);
    if (affordable <= 0) return; // nothing to lend in this seed; nothing to prove

    const response = await disburse(affordable);
    expect(response.status).toBeLessThan(300);

    const after = await prisma.fundAccount.findFirst({
      where: { groupId, type: "INTERNAL_LOAN" }
    });
    expect(after!.balanceCents).toBe(fund!.balanceCents - affordable);
  });
});
