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

async function welfareBalance(groupId: string) {
  const fund = await prisma.fundAccount.findFirst({
    where: { groupId, type: "SOCIAL" },
    select: { balanceCents: true }
  });
  return fund!.balanceCents;
}

/**
 * The rule under test: welfare expenses are paid OUT of the welfare fund, and
 * what remains at cycle end is what gets shared out. So an expense must reduce
 * the fund — if it did not, the group would distribute money it had spent.
 */
describe("welfare expenses", () => {
  let groupId: string;
  let memberId: string;
  let meetingId: string;
  let cookies: string[];

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirst({ orderBy: { createdAt: "asc" } });
    groupId = group!.id;
    const member = await prisma.member.findFirst({ where: { groupId } });
    memberId = member!.id;
    cookies = await adminCookies();

    // Welfare is paid out DURING a meeting, so every case below needs one open.
    const meeting = await prisma.meeting.create({
      data: {
        groupId,
        title: "Welfare meeting",
        scheduledAt: new Date(),
        status: "IN_PROGRESS",
        openedAt: new Date()
      }
    });
    meetingId = meeting.id;
  }, 60000);

  it("reduces the welfare fund by the amount spent", async () => {
    const before = await welfareBalance(groupId);
    const amount = 15_000;

    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/welfare-expenses`)
      .set("Cookie", cookies)
      .send({
        amountCents: amount,
        category: "MEDICAL",
        payeeMemberId: memberId,
        note: "Clinic bill",
        meetingId
      })
      .expect(201);

    const after = await welfareBalance(groupId);
    expect(after).toBe(before - amount);
    // The response states the consequence, so a UI need not recompute it.
    expect(response.body.data.welfareBalanceCents).toBe(after);
  });

  it("writes a ledger entry as the money record, not just a note", async () => {
    const expense = await prisma.welfareExpense.findFirst({
      where: { groupId },
      orderBy: { createdAt: "desc" },
      include: { ledgerEntry: true }
    });

    expect(expense?.ledgerEntry.type).toBe("WELFARE_EXPENSE");
    expect(expense?.ledgerEntry.direction).toBe("DEBIT");
    // Signed and cycle-stamped by the shared path, not by this module.
    expect(expense?.ledgerEntry.signature).toBeTruthy();
    expect(expense?.ledgerEntry.cycleId).toBeTruthy();
    expect(expense?.cycleId).toBe(expense?.ledgerEntry.cycleId);
  });

  it("refuses to spend more welfare than the fund holds", async () => {
    const available = await welfareBalance(groupId);

    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/welfare-expenses`)
      .set("Cookie", cookies)
      .send({ amountCents: available + 50_000, category: "EMERGENCY", payeeName: "Hospital", meetingId })
      .expect(400);

    expect(response.body.error.code).toBe("INSUFFICIENT_WELFARE_FUND");
    expect(response.body.error.details.shortfallCents).toBe(50_000);
    // And nothing moved.
    expect(await welfareBalance(groupId)).toBe(available);
  });

  it("requires a payee, because welfare is paid to someone", async () => {
    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/welfare-expenses`)
      .set("Cookie", cookies)
      .send({ amountCents: 1_000, category: "OTHER", meetingId })
      .expect(400);

    expect(response.body.error.code).toBe("PAYEE_REQUIRED");
  });

  it("accepts a non-member payee, since welfare often goes to family or a hospital", async () => {
    const before = await welfareBalance(groupId);
    await request(app)
      .post(`/api/v1/groups/${groupId}/welfare-expenses`)
      .set("Cookie", cookies)
      .send({ amountCents: 2_000, category: "BEREAVEMENT", payeeName: "Njeri family", meetingId })
      .expect(201);

    expect(await welfareBalance(groupId)).toBe(before - 2_000);
  });

  it("rejects a payee who is not in this group", async () => {
    const outsider = await prisma.member.findFirst({ where: { groupId: { not: groupId } } });
    if (!outsider) return;

    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/welfare-expenses`)
      .set("Cookie", cookies)
      .send({ amountCents: 1_000, category: "OTHER", payeeMemberId: outsider.id, meetingId })
      .expect(404);

    expect(response.body.error.code).toBe("MEMBER_NOT_FOUND");
  });

  it("lists expenses with the balance that will actually be shared out", async () => {
    const response = await request(app)
      .get(`/api/v1/groups/${groupId}/welfare-expenses`)
      .set("Cookie", cookies)
      .expect(200);

    const { expenses, spentCents, welfareBalanceCents } = response.body.data;
    expect(expenses.length).toBeGreaterThanOrEqual(2);
    expect(spentCents).toBe(
      expenses.reduce((sum: number, e: { ledgerEntry: { amountCents: number } }) => sum + e.ledgerEntry.amountCents, 0)
    );
    expect(welfareBalanceCents).toBe(await welfareBalance(groupId));
  });
});
