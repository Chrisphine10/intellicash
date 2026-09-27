import { createHmac } from "node:crypto";
import type { Prisma } from "@prisma/client";
import request from "supertest";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { env } from "../src/config/env";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { buildSettlements, executeSettlement, runSettlementCycle } from "../src/services/settlement-service";
import { expireStalePayments } from "../src/services/group-payment-service";

const app = createApp();

async function signIn(role: string) {
  const account = demoAccounts.find((candidate) => candidate.role === role)!;
  const response = await request(app).post("/api/v1/auth/login").send({ phone: account.phone, password: demoPassword }).expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

function stkCallback(reference: string, amountShillings: number, receipt: string, resultCode = 0) {
  return {
    Body: {
      stkCallback: {
        CheckoutRequestID: reference,
        ResultCode: resultCode,
        ResultDesc: resultCode === 0 ? "Accepted" : "Request cancelled by user",
        CallbackMetadata: {
          Item: [
            { Name: "Amount", Value: amountShillings },
            { Name: "MpesaReceiptNumber", Value: receipt },
            { Name: "PhoneNumber", Value: 254712345678 }
          ]
        }
      }
    }
  };
}

/**
 * The payment & settlement layer end to end, in mock gateway mode:
 * the member names what the group receives, fees go on top, the server
 * verifies and posts ONLY the group amount, exactly once, and settlement pays
 * it out under the maker-checker, cool-off and never-retry-unknown rules.
 */
describe("payment and settlement layer", () => {
  let admin: string[];
  let groupAccount: string[];
  let partner: string[];
  let groupId: string;
  let memberId: string;
  let meetingId: string;
  let shareCents: number;
  const originalPaystackKey = process.env.PAYSTACK_SECRET_KEY;

  const ledgerCount = () => prisma.ledgerEntry.count({ where: { groupId } });

  async function quoteAndPay(provider: "MPESA_DARAJA" | "PAYSTACK", groupAmountCents: number, extra: Record<string, unknown> = {}) {
    const quote = await request(app)
      .post(`/api/v1/groups/${groupId}/payments/quote`)
      .set("Cookie", groupAccount)
      .send({ provider, purpose: "SHARE_PURCHASE", groupAmountCents, memberId, meetingId })
      .expect(200);
    const payment = await request(app)
      .post(`/api/v1/groups/${groupId}/payments`)
      .set("Cookie", groupAccount)
      .send({
        provider,
        purpose: "SHARE_PURCHASE",
        quoteId: quote.body.data.quoteId,
        memberId,
        meetingId,
        ...(provider === "MPESA_DARAJA" ? { phoneNumber: "0712345678" } : { customerEmail: "mary@example.com" }),
        ...extra
      })
      .expect(201);
    return { quote: quote.body.data, payment: payment.body.data };
  }

  beforeAll(async () => {
    await seedDatabase();
    admin = await signIn("IWL_ADMIN");
    groupAccount = await signIn("GROUP_ACCOUNT");
    partner = await signIn("PARTNER_OFFICER");
    const me = await request(app).get("/api/v1/auth/me").set("Cookie", groupAccount).expect(200);
    groupId = me.body.data.groupId;
    await prisma.groupIntegrationConfig.deleteMany({ where: { groupId } });
    const policy = await prisma.groupPolicy.findUnique({ where: { groupId } });
    shareCents = policy?.shareValueCents && policy.shareValueCents > 0 ? policy.shareValueCents : 10_000;
    memberId = (await prisma.member.findFirstOrThrow({ where: { groupId, status: "ACTIVE" }, select: { id: true } })).id;
    const active = await prisma.cycle.findFirst({ where: { groupId, status: "ACTIVE" } });
    meetingId = (
      await prisma.meeting.create({
        data: { groupId, cycleId: active?.id ?? null, title: "Payments test", status: "IN_PROGRESS", scheduledAt: new Date() }
      })
    ).id;
  }, 120_000);

  afterAll(() => {
    process.env.PAYSTACK_SECRET_KEY = originalPaystackKey;
  });

  it("serves an app that predates fees unchanged while no fee is switched on", async () => {
    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/payments`)
      .set("Cookie", groupAccount)
      .send({ provider: "MPESA_DARAJA", purpose: "SHARE_PURCHASE", amountCents: shareCents, memberId, phoneNumber: "0712345678" })
      .expect(201);
    expect(response.body.data).toMatchObject({
      amountCents: shareCents,
      groupAmountCents: shareCents,
      platformFeeCents: 0,
      providerFeeCents: 0,
      state: "PROCESSING",
      status: "PENDING"
    });
  });

  it("lets only an IWL admin configure fees", async () => {
    await request(app)
      .post("/api/v1/payment-admin/fee-rules")
      .set("Cookie", partner)
      .send({ kind: "PLATFORM", minCents: 0, fixedCents: 500, percentBps: 0 })
      .expect(403);
    await request(app)
      .post("/api/v1/payment-admin/fee-rules")
      .set("Cookie", admin)
      .send({ kind: "PLATFORM", minCents: 0, maxCents: null, fixedCents: 500, percentBps: 0 })
      .expect(201);
    await request(app)
      .post("/api/v1/payment-admin/fee-rules")
      .set("Cookie", admin)
      .send({ kind: "PROVIDER", provider: "PAYSTACK", minCents: 0, fixedCents: 0, percentBps: 150 })
      .expect(201);
    await request(app)
      .post("/api/v1/payment-admin/fee-rules")
      .set("Cookie", admin)
      .send({ kind: "PROVIDER", provider: "MPESA_DARAJA", minCents: 0, fixedCents: 700, percentBps: 0 })
      .expect(201);
  });

  it("refuses an app that cannot show fees once fees are on", async () => {
    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/payments`)
      .set("Cookie", groupAccount)
      .send({ provider: "MPESA_DARAJA", purpose: "SHARE_PURCHASE", amountCents: shareCents, memberId, phoneNumber: "0712345678" })
      .expect(426);
    expect(response.body.error.code).toBe("APP_UPDATE_REQUIRED");
  });

  it("quotes fees on top of the group amount and charges the quoted total", async () => {
    const { quote, payment } = await quoteAndPay("MPESA_DARAJA", shareCents * 2);
    expect(quote.groupAmountCents).toBe(shareCents * 2);
    expect(quote.platformFeeCents).toBe(500);
    expect(quote.totalCents).toBe(quote.groupAmountCents + quote.platformFeeCents + quote.providerFeeCents);
    expect(quote.totalCents % 100).toBe(0);
    expect(payment.amountCents).toBe(quote.totalCents);
    expect(payment.groupAmountCents).toBe(shareCents * 2);
  });

  it("refuses a quote that breaks the group's share rule", async () => {
    const policy = await prisma.groupPolicy.findUnique({ where: { groupId } });
    if (!policy?.shareValueCents) return; // no share rule on this group
    await request(app)
      .post(`/api/v1/groups/${groupId}/payments/quote`)
      .set("Cookie", groupAccount)
      .send({ provider: "MPESA_DARAJA", purpose: "SHARE_PURCHASE", groupAmountCents: shareCents + 1, memberId })
      .expect(422);
  });

  it("verifies, then posts ONLY the group amount, exactly once however often the callback comes", async () => {
    const { payment, quote } = await quoteAndPay("MPESA_DARAJA", shareCents);
    const before = await ledgerCount();
    const callback = stkCallback(payment.providerReference, quote.totalCents / 100, "RCP-ONCE-1");
    await request(app).post("/api/v1/payments/mpesa/stk-callback").send(callback).expect(200);
    await request(app).post("/api/v1/payments/mpesa/stk-callback").send(callback).expect(200);

    expect(await ledgerCount()).toBe(before + 1);
    const saved = await prisma.groupPayment.findUniqueOrThrow({ where: { id: payment.id } });
    expect(saved).toMatchObject({ state: "LEDGER_POSTED", status: "COMPLETED", settlementStatus: "PENDING", providerTransactionId: "RCP-ONCE-1" });
    const entry = await prisma.ledgerEntry.findUniqueOrThrow({ where: { id: saved.ledgerEntryId! }, include: { fundAccount: true } });
    expect(entry).toMatchObject({ type: "SHARE_PURCHASE", amountCents: shareCents, direction: "CREDIT", clientRequestId: `gp-${payment.id}` });
    expect(entry.fundAccount?.type).toBe("INTERNAL_LOAN");

    // The phone later syncs its own record of the same purchase — both a new
    // phone (payment id) and a 2.6.x phone (receipt in the reference).
    for (const link of [{ groupPaymentId: payment.id }, { externalReference: "RCP-ONCE-1" }]) {
      const batch = await request(app)
        .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/ledger/batch`)
        .set("Cookie", groupAccount)
        .send({
          entries: [{ memberId, type: "SHARE_PURCHASE", amountCents: shareCents, clientRequestId: `shr-${Math.random()}`, ...link }]
        })
        .expect(201);
      expect(batch.body.data[0].id).toBe(entry.id);
    }
    expect(await ledgerCount()).toBe(before + 1);
  });

  it("holds a payment whose amount does not match, and posts nothing", async () => {
    const { payment, quote } = await quoteAndPay("MPESA_DARAJA", shareCents);
    const before = await ledgerCount();
    await request(app)
      .post("/api/v1/payments/mpesa/stk-callback")
      .send(stkCallback(payment.providerReference, quote.totalCents / 100 - 1, "RCP-SHORT"))
      .expect(200);
    const saved = await prisma.groupPayment.findUniqueOrThrow({ where: { id: payment.id } });
    expect(saved.state).toBe("HELD");
    expect(saved.status).toBe("FAILED"); // old phones must not record it by hand
    expect(saved.ledgerEntryId).toBeNull();
    expect(await ledgerCount()).toBe(before);

    const poll = await request(app).get(`/api/v1/groups/${groupId}/payments/${payment.id}`).set("Cookie", groupAccount).expect(200);
    expect(poll.body.data.state).toBe("HELD");

    // An admin checks it with the provider and releases it: now it posts.
    await request(app)
      .post(`/api/v1/payment-admin/payments/${payment.id}/release`)
      .set("Cookie", admin)
      .send({ note: "Confirmed on the M-Pesa statement" })
      .expect(200);
    expect(await ledgerCount()).toBe(before + 1);
  });

  it("links the phone's entry when the phone records it before the callback", async () => {
    const { payment, quote } = await quoteAndPay("MPESA_DARAJA", shareCents);
    const before = await ledgerCount();
    const batch = await request(app)
      .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/ledger/batch`)
      .set("Cookie", groupAccount)
      .send({ entries: [{ memberId, type: "SHARE_PURCHASE", amountCents: shareCents, clientRequestId: `shr-early-${payment.id}`, groupPaymentId: payment.id }] })
      .expect(201);
    const linked = await prisma.groupPayment.findUniqueOrThrow({ where: { id: payment.id } });
    expect(linked.ledgerEntryId).toBe(batch.body.data[0].id);
    expect(linked.settlementStatus).toBe("NOT_REQUIRED"); // not settleable until verified

    await request(app)
      .post("/api/v1/payments/mpesa/stk-callback")
      .send(stkCallback(payment.providerReference, quote.totalCents / 100, "RCP-EARLY"))
      .expect(200);
    const saved = await prisma.groupPayment.findUniqueOrThrow({ where: { id: payment.id } });
    expect(saved).toMatchObject({ state: "LEDGER_POSTED", ledgerEntryId: batch.body.data[0].id, settlementStatus: "PENDING" });
    expect(await ledgerCount()).toBe(before + 1);
  });

  it("marks a cancelled prompt failed and posts nothing", async () => {
    const { payment } = await quoteAndPay("MPESA_DARAJA", shareCents);
    await request(app)
      .post("/api/v1/payments/mpesa/stk-callback")
      .send(stkCallback(payment.providerReference, 0, "", 1032))
      .expect(200);
    const saved = await prisma.groupPayment.findUniqueOrThrow({ where: { id: payment.id } });
    expect(saved).toMatchObject({ state: "CANCELLED", ledgerEntryId: null });
  });

  it("checks the Paystack signature over the raw bytes, with the collecting account's key", async () => {
    process.env.PAYSTACK_SECRET_KEY = "sk_test_platform_secret";
    const { payment, quote } = await quoteAndPay("PAYSTACK", shareCents);
    // Deliberately spaced: JSON.stringify of the parsed body would differ,
    // so this only passes when the raw bytes are what is signed.
    const raw = `{ "event": "charge.success",  "data": { "reference": "${payment.internalReference}", "amount": ${quote.totalCents}, "currency": "KES", "id": 998877, "status": "success" } }`;
    const sign = (secret: string) => createHmac("sha512", secret).update(raw).digest("hex");

    await request(app)
      .post("/api/v1/payments/paystack/webhook")
      .set("Content-Type", "application/json")
      .set("x-paystack-signature", sign("wrong-secret"))
      .send(raw)
      .expect(400);

    const before = await ledgerCount();
    for (let i = 0; i < 2; i += 1) {
      await request(app)
        .post("/api/v1/payments/paystack/webhook")
        .set("Content-Type", "application/json")
        .set("x-paystack-signature", sign("sk_test_platform_secret"))
        .send(raw)
        .expect(200);
    }
    const saved = await prisma.groupPayment.findUniqueOrThrow({ where: { id: payment.id } });
    expect(saved.state).toBe("LEDGER_POSTED");
    expect(saved.providerFeeCents).toBeGreaterThan(0);
    expect(await ledgerCount()).toBe(before + 1);
  });

  it("does not let a partner see group payment reconciliation", async () => {
    await request(app).get("/api/v1/payment-admin/reconciliation").set("Cookie", partner).expect(403);
    const report = await request(app).get("/api/v1/payment-admin/reconciliation").set("Cookie", admin).expect(200);
    const totals = report.body.data.totals;
    expect(totals.collectedCents).toBe(totals.groupFundsCents + totals.platformFeesCollectedCents + totals.platformFeesReceivableCents + totals.providerChargesCents);
    expect(report.body.data.exceptions.some((row: { reasons: string[] }) => row.reasons.includes("FEES_DO_NOT_ADD_UP"))).toBe(false);
  });

  describe("settlement", () => {
    let destinationId: string;

    it("refuses approval by the person who proposed the account", async () => {
      const proposed = await request(app)
        .post(`/api/v1/groups/${groupId}/settlement-destinations`)
        .set("Cookie", admin)
        .send({ type: "MPESA_PHONE", accountNumber: "0711000111", accountName: "Admin Proposed" })
        .expect(201);
      const refused = await request(app)
        .post(`/api/v1/settlement-destinations/${proposed.body.data.id}/approve`)
        .set("Cookie", admin)
        .expect(403);
      expect(refused.body.error.code).toBe("SAME_PERSON_APPROVAL");
    });

    it("pays nothing until an approved account has cooled off", async () => {
      const proposed = await request(app)
        .post(`/api/v1/groups/${groupId}/settlement-destinations`)
        .set("Cookie", groupAccount)
        .send({ type: "MPESA_PAYBILL", accountNumber: "522522", accountName: "Tujijenge VSLA", accountReference: "1234567" })
        .expect(201);
      destinationId = proposed.body.data.id;
      await request(app).post(`/api/v1/settlement-destinations/${destinationId}/approve`).set("Cookie", groupAccount).expect(403);
      await request(app).post(`/api/v1/settlement-destinations/${destinationId}/approve`).set("Cookie", admin).expect(200);

      const cooling = await buildSettlements();
      expect(cooling.skipped).toContainEqual({ groupId, reason: "DESTINATION_COOLING_OFF" });
      expect(await prisma.settlement.count({ where: { groupId } })).toBe(0);
    });

    it("batches a group's posted payments and pays them out", async () => {
      await prisma.settlementDestination.update({
        where: { id: destinationId },
        data: { verifiedAt: new Date(Date.now() - 25 * 3_600_000) }
      });
      const pending = await prisma.groupPayment.findMany({ where: { groupId, settlementStatus: "PENDING" } });
      expect(pending.length).toBeGreaterThan(0);
      const expected = pending.reduce((sum, payment) => sum + payment.groupAmountCents, 0);

      const built = await buildSettlements();
      const mine = built.created.find((row) => row.groupId === groupId)!;
      expect(mine.amountCents).toBe(expected); // the group amounts, never the fees
      expect(mine.status).toBe("QUEUED");

      // Automated settlement is off: the batch is built, not paid.
      const refused = await request(app).post(`/api/v1/payment-admin/settlements/${mine.settlementId}/pay`).set("Cookie", admin).expect(409);
      expect(refused.body.error.code).toBe("SETTLEMENT_DISABLED");
      expect((await runSettlementCycle()).paid).toHaveLength(0);

      const paid = await executeSettlement(mine.settlementId, { force: true });
      expect(paid?.status).toBe("SETTLED");
      const settled = await prisma.groupPayment.findMany({ where: { settlementId: mine.settlementId } });
      expect(settled.every((payment) => payment.settlementStatus === "SETTLED")).toBe(true);
    });

    it("waits for a sign-off above the automatic limit", async () => {
      const { payment, quote } = await quoteAndPay("MPESA_DARAJA", shareCents);
      await request(app)
        .post("/api/v1/payments/mpesa/stk-callback")
        .send(stkCallback(payment.providerReference, quote.totalCents / 100, "RCP-BIG"))
        .expect(200);
      const limit = env.SETTLEMENT_AUTO_MAX_CENTS;
      env.SETTLEMENT_AUTO_MAX_CENTS = 1;
      try {
        const built = await buildSettlements();
        const mine = built.created.find((row) => row.groupId === groupId)!;
        expect(mine.status).toBe("AWAITING_APPROVAL");
        await request(app).post(`/api/v1/payment-admin/settlements/${mine.settlementId}/approve`).set("Cookie", admin).expect(200);
        expect((await prisma.settlement.findUniqueOrThrow({ where: { id: mine.settlementId } })).status).toBe("QUEUED");
      } finally {
        env.SETTLEMENT_AUTO_MAX_CENTS = limit;
      }
    });

    it("parks a timed-out payout as UNKNOWN and never pays it again by itself", async () => {
      const settlement = await prisma.settlement.create({
        data: {
          groupId,
          destinationId,
          amountCents: 12_300,
          provider: "MPESA_DARAJA",
          internalReference: `STL-TEST-${Date.now()}`,
          providerReference: `AG_TEST_${Date.now()}`,
          status: "PROCESSING"
        }
      });
      await request(app)
        .post("/api/v1/payments/mpesa/b2b-timeout")
        .send({ Result: { ConversationID: settlement.providerReference } })
        .expect(200);
      expect((await prisma.settlement.findUniqueOrThrow({ where: { id: settlement.id } })).status).toBe("UNKNOWN");

      const attemptsBefore = await prisma.settlementAttempt.count({ where: { settlementId: settlement.id, result: "STARTED" } });
      const saved = env.ENABLE_AUTOMATED_SETTLEMENT;
      env.ENABLE_AUTOMATED_SETTLEMENT = true;
      try {
        await runSettlementCycle();
      } finally {
        env.ENABLE_AUTOMATED_SETTLEMENT = saved;
      }
      expect(await prisma.settlementAttempt.count({ where: { settlementId: settlement.id, result: "STARTED" } })).toBe(attemptsBefore);

      // The late result arrives: it settles.
      await request(app)
        .post("/api/v1/payments/mpesa/b2b-result")
        .send({
          Result: {
            ConversationID: settlement.providerReference,
            ResultCode: 0,
            ResultDesc: "The service request is processed successfully.",
            TransactionID: "SLATE12345"
          }
        })
        .expect(200);
      expect((await prisma.settlement.findUniqueOrThrow({ where: { id: settlement.id } })).status).toBe("SETTLED");
    });
  });

  describe("every kind of member payment lands in the right fund", () => {
    async function payFor(purpose: string, member: string, groupAmountCents: number, receipt: string) {
      const quote = await request(app)
        .post(`/api/v1/groups/${groupId}/payments/quote`)
        .set("Cookie", groupAccount)
        .send({ provider: "MPESA_DARAJA", purpose, groupAmountCents, memberId: member })
        .expect(200);
      const payment = await request(app)
        .post(`/api/v1/groups/${groupId}/payments`)
        .set("Cookie", groupAccount)
        .send({ provider: "MPESA_DARAJA", purpose, quoteId: quote.body.data.quoteId, memberId: member, phoneNumber: "0712345678" })
        .expect(201);
      await request(app)
        .post("/api/v1/payments/mpesa/stk-callback")
        .send(stkCallback(payment.body.data.providerReference, quote.body.data.totalCents / 100, receipt))
        .expect(200);
      const saved = await prisma.groupPayment.findUniqueOrThrow({ where: { id: payment.body.data.id } });
      const entry = await prisma.ledgerEntry.findUniqueOrThrow({ where: { id: saved.ledgerEntryId! }, include: { fundAccount: true } });
      return { saved, entry };
    }

    it("books the welfare (social) fund, a fine and a loan repayment by the ledger's own rules", async () => {
      const policy = await prisma.groupPolicy.findUnique({ where: { groupId } });
      const social = policy?.socialFundCents && policy.socialFundCents > 0 ? policy.socialFundCents : 5_000;

      const welfare = await payFor("SOCIAL_FUND", memberId, social, "RCP-SOC");
      expect(welfare.entry).toMatchObject({ type: "SOCIAL_CONTRIBUTION", direction: "CREDIT", amountCents: social });
      expect(welfare.entry.fundAccount?.type).toBe("SOCIAL");

      const fine = await payFor("FINE", memberId, 2_000, "RCP-FINE");
      expect(fine.entry).toMatchObject({ type: "FINE_COLLECTION", direction: "CREDIT", amountCents: 2_000 });
      expect(fine.entry.fundAccount?.type).toBe("SOCIAL");

      // A member who owes: lend in the meeting, the way every loan is recorded.
      await request(app)
        .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/ledger/batch`)
        .set("Cookie", groupAccount)
        .send({
          entries: [{
            memberId,
            type: "INTERNAL_LOAN_DISBURSEMENT",
            amountCents: 5_000,
            clientRequestId: `loan-${Date.now()}`,
            loan: { termMonths: 3, interestRateBps: 0, interestType: "FLAT" }
          }]
        })
        .expect(201);
      const context = await request(app)
        .get(`/api/v1/groups/${groupId}/payments/member-context/${memberId}`)
        .set("Cookie", groupAccount)
        .expect(200);
      const borrower = { id: memberId, owes: context.body.data.loanOutstandingCents as number };
      expect(borrower.owes).toBeGreaterThanOrEqual(5_000);

      const tooMuch = await request(app)
        .post(`/api/v1/groups/${groupId}/payments/quote`)
        .set("Cookie", groupAccount)
        .send({ provider: "MPESA_DARAJA", purpose: "LOAN_REPAYMENT", groupAmountCents: borrower.owes + 100, memberId: borrower.id })
        .expect(400);
      expect(tooMuch.body.error.code).toBe("REPAYMENT_TOO_LARGE");

      const repay = await payFor("LOAN_REPAYMENT", borrower.id, 100, "RCP-LOAN");
      expect(repay.entry).toMatchObject({ type: "LOAN_REPAYMENT", direction: "CREDIT", amountCents: 100, memberId: borrower.id });
      expect(repay.entry.fundAccount?.type).toBe("INTERNAL_LOAN");
      const after = await request(app)
        .get(`/api/v1/groups/${groupId}/payments/member-context/${borrower.id}`)
        .set("Cookie", groupAccount)
        .expect(200);
      expect(after.body.data.loanOutstandingCents).toBe(borrower.owes - 100);

      // The phone's own copy of the repayment is linked, not booked again.
      const before = await ledgerCount();
      const batch = await request(app)
        .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/ledger/batch`)
        .set("Cookie", groupAccount)
        .send({ entries: [{ memberId: borrower.id, type: "LOAN_REPAYMENT", amountCents: 100, clientRequestId: `rep-${repay.saved.id}`, groupPaymentId: repay.saved.id }] })
        .expect(201);
      expect(batch.body.data[0].id).toBe(repay.entry.id);
      expect(await ledgerCount()).toBe(before);
    });

    it("refuses a repayment from a member who owes nothing, and any payment with no member", async () => {
      const members = await prisma.member.findMany({ where: { groupId, status: "ACTIVE", id: { not: memberId } }, select: { id: true } });
      for (const candidate of members) {
        const context = await request(app)
          .get(`/api/v1/groups/${groupId}/payments/member-context/${candidate.id}`)
          .set("Cookie", groupAccount)
          .expect(200);
        if (context.body.data.loanOutstandingCents === 0) {
          const refused = await request(app)
            .post(`/api/v1/groups/${groupId}/payments/quote`)
            .set("Cookie", groupAccount)
            .send({ provider: "MPESA_DARAJA", purpose: "LOAN_REPAYMENT", groupAmountCents: 1_000, memberId: candidate.id })
            .expect(400);
          expect(refused.body.error.code).toBe("NO_LOAN_OUTSTANDING");
          break;
        }
      }
      const noMember = await request(app)
        .post(`/api/v1/groups/${groupId}/payments/quote`)
        .set("Cookie", groupAccount)
        .send({ provider: "MPESA_DARAJA", purpose: "FINE", groupAmountCents: 1_000 })
        .expect(400);
      expect(noMember.body.error.code).toBe("MEMBER_REQUIRED");
    });

    it("gives a group created without fund accounts the fund its first payment needs", async () => {
      // How the FLOURISH import used to create groups: no fund accounts at all.
      const template = await prisma.group.findUniqueOrThrow({ where: { id: groupId } });
      const { id: _id, code: _code, joinToken: _token, createdAt: _c, ...rest } = template;
      void _id; void _code; void _token; void _c;
      const bare = await prisma.group.create({
        data: { ...(rest as unknown as Prisma.GroupUncheckedCreateInput), code: `IWL-BARE-${Date.now()}` }
      });
      const member = await prisma.member.create({ data: { groupId: bare.id, fullName: "Bare Member", phone: "254711999001", status: "ACTIVE" } });
      expect(await prisma.fundAccount.count({ where: { groupId: bare.id } })).toBe(0);

      const quote = await request(app)
        .post(`/api/v1/groups/${bare.id}/payments/quote`)
        .set("Cookie", admin)
        .send({ provider: "MPESA_DARAJA", purpose: "FINE", groupAmountCents: 1_000, memberId: member.id })
        .expect(200);
      const payment = await request(app)
        .post(`/api/v1/groups/${bare.id}/payments`)
        .set("Cookie", admin)
        .send({ provider: "MPESA_DARAJA", purpose: "FINE", quoteId: quote.body.data.quoteId, memberId: member.id, phoneNumber: "0712345678" })
        .expect(201);
      await request(app)
        .post("/api/v1/payments/mpesa/stk-callback")
        .send(stkCallback(payment.body.data.providerReference, quote.body.data.totalCents / 100, "RCP-BARE"))
        .expect(200);
      const saved = await prisma.groupPayment.findUniqueOrThrow({ where: { id: payment.body.data.id } });
      expect(saved.state).toBe("LEDGER_POSTED");
      const social = await prisma.fundAccount.findUniqueOrThrow({ where: { groupId_type: { groupId: bare.id, type: "SOCIAL" } } });
      expect(social.balanceCents).toBe(1_000);
    });

    it("reads the old SAVINGS expense choice as the loan fund it always meant", async () => {
      const saved = await request(app)
        .put(`/api/v1/groups/${groupId}/policy`)
        .set("Cookie", admin)
        .send({ expenseFundType: "SAVINGS" })
        .expect(200);
      const policy = await request(app).get(`/api/v1/groups/${groupId}/policy`).set("Cookie", admin).expect(200);
      expect(policy.body.data.policy.expenseFundType).toBe("INTERNAL_LOAN");
      expect(saved.status).toBe(200);
      await request(app).put(`/api/v1/groups/${groupId}/policy`).set("Cookie", admin).send({ expenseFundType: "VSLF" }).expect(400);
    });
  });

  describe("switching M-Pesa and Paystack on and off for a group", () => {
    it("the group's own account and an admin can switch them; members and partners cannot", async () => {
      const put = (cookie: string[], enabledProviders: string[]) =>
        request(app)
          .put(`/api/v1/groups/${groupId}/payment-settings`)
          .set("Cookie", cookie)
          .send({ collectionMode: "SYSTEM", enabledProviders, memberSelfPayEnabled: false });
      const quote = (provider: string) =>
        request(app)
          .post(`/api/v1/groups/${groupId}/payments/quote`)
          .set("Cookie", groupAccount)
          .send({ provider, purpose: "SHARE_PURCHASE", groupAmountCents: shareCents, memberId });

      // The group switches M-Pesa off; Paystack still works.
      await put(groupAccount, ["PAYSTACK"]).expect(200);
      const off = await quote("MPESA_DARAJA").expect(400);
      expect(off.body.error.code).toBe("PROVIDER_NOT_ENABLED");
      await quote("PAYSTACK").expect(200);

      // Both off is allowed: no online payments at all.
      await put(groupAccount, []).expect(200);
      await quote("PAYSTACK").expect(400);
      const settings = await request(app).get(`/api/v1/groups/${groupId}/payment-settings`).set("Cookie", groupAccount).expect(200);
      expect(settings.body.data.settings.enabledProviders).toEqual([]);

      // Nobody else may touch the switches.
      await put(await signIn("MEMBER"), ["MPESA_DARAJA"]).expect(403);
      await put(partner, ["MPESA_DARAJA"]).expect((res) => expect([403, 404]).toContain(res.status));

      // An admin switches both back on.
      await put(admin, ["MPESA_DARAJA", "PAYSTACK"]).expect(200);
      await quote("MPESA_DARAJA").expect(200);
    });
  });

  describe("payments nobody completed", () => {
    it("expires an unanswered prompt after a day, yet still books it if the money arrives late", async () => {
      const { payment, quote } = await quoteAndPay("MPESA_DARAJA", shareCents);
      await prisma.groupPayment.update({ where: { id: payment.id }, data: { createdAt: new Date(Date.now() - 25 * 3_600_000) } });

      const outcome = await expireStalePayments();
      expect(outcome.expired).toBeGreaterThanOrEqual(1);
      const expired = await prisma.groupPayment.findUniqueOrThrow({ where: { id: payment.id } });
      expect(expired).toMatchObject({ state: "EXPIRED", status: "FAILED", ledgerEntryId: null });

      // The member did pay; the confirmation was just very late.
      const before = await ledgerCount();
      await request(app)
        .post("/api/v1/payments/mpesa/stk-callback")
        .send(stkCallback(payment.providerReference, quote.totalCents / 100, "RCP-LATE"))
        .expect(200);
      const late = await prisma.groupPayment.findUniqueOrThrow({ where: { id: payment.id } });
      expect(late.state).toBe("LEDGER_POSTED");
      expect(await ledgerCount()).toBe(before + 1);
    });

    it("never expires a payment the provider said was paid", async () => {
      const { payment } = await quoteAndPay("MPESA_DARAJA", shareCents);
      await prisma.groupPayment.update({
        where: { id: payment.id },
        data: { state: "SUCCESSFUL", createdAt: new Date(Date.now() - 48 * 3_600_000) }
      });
      await expireStalePayments();
      expect((await prisma.groupPayment.findUniqueOrThrow({ where: { id: payment.id } })).state).toBe("SUCCESSFUL");
    });
  });

  describe("members paying from their passbook", () => {
    it("is refused until the group switches it on", async () => {
      const member = await signIn("MEMBER");
      await request(app)
        .post("/api/v1/members/me/payments/quote")
        .set("Cookie", member)
        .send({ provider: "MPESA_DARAJA", purpose: "SHARE_PURCHASE", groupAmountCents: shareCents })
        .expect(403);

      const me = await request(app).get("/api/v1/auth/me").set("Cookie", member).expect(200);
      const memberGroupId = me.body.data.groupId as string;
      await prisma.groupIntegrationConfig.deleteMany({ where: { groupId: memberGroupId } });
      await request(app)
        .put(`/api/v1/groups/${memberGroupId}/payment-settings`)
        .set("Cookie", admin)
        .send({ collectionMode: "SYSTEM", enabledProviders: ["MPESA_DARAJA"], memberSelfPayEnabled: true })
        .expect(200);
      const policy = await prisma.groupPolicy.findUnique({ where: { groupId: memberGroupId } });
      const amount = policy?.shareValueCents && policy.shareValueCents > 0 ? policy.shareValueCents : 10_000;
      const quote = await request(app)
        .post("/api/v1/members/me/payments/quote")
        .set("Cookie", member)
        .send({ provider: "MPESA_DARAJA", purpose: "SHARE_PURCHASE", groupAmountCents: amount })
        .expect(200);
      expect(quote.body.data.totalCents).toBeGreaterThan(amount);
      // Paystack is not enabled for that group.
      await request(app)
        .post("/api/v1/members/me/payments/quote")
        .set("Cookie", member)
        .send({ provider: "PAYSTACK", purpose: "SHARE_PURCHASE", groupAmountCents: amount })
        .expect(400);
    });
  });
});
