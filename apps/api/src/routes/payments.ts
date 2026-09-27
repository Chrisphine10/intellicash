import { Router } from "express";
import { z } from "zod";
import { appendAuditEvent } from "../services/audit-service";
import { requireAuth } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import {
  completeIncomingTransaction,
  completeWithdrawal,
  createPaymentReference,
  failIncomingTransaction,
  failWithdrawal,
  initiateIncomingPayment,
  initiatePayout,
  paystackSecretFor,
  rejectWithdrawal,
  updateTransactionGatewayFields,
  verifyPaystackSignature,
  walletAvailable
} from "../services/payment-service";
import { completeGroupPayment, failGroupPayment } from "../services/group-payment-service";
import { credentialGroupId } from "../services/payment-settings-service";
import { completeSettlement, failSettlement, markSettlementUnknown } from "../services/settlement-service";
import { holdFunds } from "../services/wallet-service";

const router = Router();
const providerSchema = z.enum(["MPESA_DARAJA", "PAYSTACK"]);
const contributionTypeSchema = z.enum(["INVESTMENT", "DONATION"]);

const depositSchema = z.object({
  provider: providerSchema,
  amountCents: z.number().int().min(100),
  phoneNumber: z.string().min(7).optional()
});

const withdrawalSchema = z.object({
  provider: providerSchema,
  amountCents: z.number().int().min(100),
  payoutPhoneNumber: z.string().min(7).optional(),
  payoutRecipientCode: z.string().optional()
});

const contributionSchema = z.object({
  type: contributionTypeSchema,
  provider: providerSchema.optional(),
  source: z.enum(["WALLET", "DIRECT"]),
  amountCents: z.number().int().min(100),
  phoneNumber: z.string().min(7).optional()
});

const rejectionSchema = z.object({
  reason: z.string().min(2).default("Rejected by admin")
});

function requirePartnerAccount(user: Express.Request["user"]) {
  if (!user?.partnerId || !["PARTNER_OFFICER", "LENDER"].includes(user.role)) {
    throw new ApiHttpError(403, "PARTNER_ACCOUNT_REQUIRED", "Partner or lender account is required.");
  }
  return user.partnerId;
}

async function ensureWallet(partnerId: string) {
  return prisma.partnerWallet.upsert({
    where: { partnerId },
    create: { partnerId, currency: "KES" },
    update: {}
  });
}

function transactionInclude() {
  return {
    wallet: { include: { partner: true } },
    partner: true,
    programme: { include: { partner: true } }
  } as const;
}

router.get("/partner-wallet", requireAuth("payments:read"), async (req, res, next) => {
  try {
    const partnerId = requirePartnerAccount(req.user);
    const wallet = await ensureWallet(partnerId);
    ok(res, {
      ...wallet,
      availableCents: walletAvailable(wallet.balanceCents, wallet.heldCents)
    });
  } catch (error) {
    next(error);
  }
});

router.get("/partner-wallet/transactions", requireAuth("payments:read"), async (req, res, next) => {
  try {
    const partnerId = requirePartnerAccount(req.user);
    const transactions = await prisma.partnerWalletTransaction.findMany({
      where: { partnerId },
      orderBy: { createdAt: "desc" },
      include: transactionInclude()
    });

    ok(res, transactions);
  } catch (error) {
    next(error);
  }
});

router.post("/partner-wallet/deposits", requireAuth("payments:write"), async (req, res, next) => {
  try {
    const partnerId = requirePartnerAccount(req.user);
    const body = depositSchema.parse(req.body);
    const wallet = await ensureWallet(partnerId);
    const internalReference = createPaymentReference("DEP");
    const transaction = await prisma.partnerWalletTransaction.create({
      data: {
        walletId: wallet.id,
        partnerId,
        actorUserId: req.user?.id,
        type: "DEPOSIT",
        provider: body.provider,
        source: "DIRECT",
        status: "PENDING",
        amountCents: body.amountCents,
        currency: "KES",
        description: `${req.user?.partner?.name ?? "Partner"} wallet deposit`,
        customerName: req.user?.name,
        customerEmail: req.user?.email,
        phoneNumber: body.phoneNumber,
        internalReference
      }
    });

    const gateway = await initiateIncomingPayment({
      provider: body.provider,
      amountCents: body.amountCents,
      internalReference,
      customerEmail: req.user?.email,
      customerName: req.user?.name,
      phoneNumber: body.phoneNumber,
      description: "Intelli Cash partner wallet deposit",
      metadata: { walletId: wallet.id, partnerId, type: "DEPOSIT" }
    });
    const updated = await updateTransactionGatewayFields(transaction.id, gateway);

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "PAYMENT",
      entityId: updated.id,
      type: "PAYMENT_INITIATED",
      payload: updated
    });

    ok(res.status(201), updated);
  } catch (error) {
    next(error);
  }
});

router.post("/partner-wallet/withdrawals", requireAuth("payments:write"), async (req, res, next) => {
  try {
    const partnerId = requirePartnerAccount(req.user);
    const body = withdrawalSchema.parse(req.body);

    const internalReference = createPaymentReference("WDR");
    const transaction = await prisma.$transaction(async (tx) => {
      // Reserve funds atomically: the availability check and the hold are part
      // of the same transaction, closing the previous check-then-act race.
      const wallet = await holdFunds(tx, {
        partnerId,
        amountCents: body.amountCents,
        errorCode: "INSUFFICIENT_FUNDS",
        errorMessage: "Withdrawal exceeds available wallet balance."
      });

      if (body.provider === "MPESA_DARAJA" && !body.payoutPhoneNumber) {
        throw new ApiHttpError(400, "PAYOUT_PHONE_REQUIRED", "M-Pesa withdrawals require a recipient phone number.");
      }
      if (body.provider === "PAYSTACK" && !body.payoutRecipientCode) {
        throw new ApiHttpError(400, "PAYSTACK_RECIPIENT_REQUIRED", "Paystack withdrawals require a recipient code.");
      }

      return tx.partnerWalletTransaction.create({
        data: {
          walletId: wallet.id,
          partnerId,
          actorUserId: req.user?.id,
          type: "WITHDRAWAL",
          provider: body.provider,
          source: "WALLET",
          status: "PENDING",
          amountCents: body.amountCents,
          currency: "KES",
          description: `${req.user?.partner?.name ?? "Partner"} withdrawal request`,
          payoutPhoneNumber: body.payoutPhoneNumber,
          payoutRecipientCode: body.payoutRecipientCode,
          internalReference
        },
        include: transactionInclude()
      });
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "PAYMENT",
      entityId: transaction.id,
      type: "WITHDRAWAL_REQUESTED",
      payload: transaction
    });

    ok(res.status(201), transaction);
  } catch (error) {
    next(error);
  }
});

router.post("/programmes/:id/contributions", requireAuth("payments:write"), async (req, res, next) => {
  try {
    const partnerId = requirePartnerAccount(req.user);
    const programmeId = z.string().parse(req.params.id);
    const body = contributionSchema.parse(req.body);
    const programme = await prisma.programme.findFirst({
      where: { id: programmeId, publicStatus: "ONGOING" },
      select: {
        id: true,
        name: true,
        allowInvestments: true,
        allowDonations: true
      }
    });

    if (!programme) throw new ApiHttpError(404, "PROJECT_NOT_FOUND", "Public project does not exist.");
    if (body.type === "INVESTMENT" && !programme.allowInvestments) {
      throw new ApiHttpError(400, "INVESTMENTS_DISABLED", "This project is not accepting investments.");
    }
    if (body.type === "DONATION" && !programme.allowDonations) {
      throw new ApiHttpError(400, "DONATIONS_DISABLED", "This project is not accepting donations.");
    }

    const wallet = await ensureWallet(partnerId);
    if (body.source === "WALLET") {
      const available = walletAvailable(wallet.balanceCents, wallet.heldCents);
      if (available < body.amountCents) {
        throw new ApiHttpError(400, "INSUFFICIENT_FUNDS", "Contribution exceeds available wallet balance.");
      }

      const transaction = await prisma.$transaction(async (tx) => {
        await tx.partnerWallet.update({
          where: { id: wallet.id },
          data: { balanceCents: { decrement: body.amountCents } }
        });

        return tx.partnerWalletTransaction.create({
          data: {
            walletId: wallet.id,
            partnerId,
            programmeId: programme.id,
            actorUserId: req.user?.id,
            type: body.type,
            provider: "INTERNAL",
            source: "WALLET",
            status: "COMPLETED",
            amountCents: body.amountCents,
            currency: "KES",
            description: `${body.type.toLowerCase()} from wallet for ${programme.name}`,
            customerName: req.user?.name,
            customerEmail: req.user?.email,
            internalReference: createPaymentReference(body.type === "INVESTMENT" ? "INV" : "DON"),
            completedAt: new Date()
          },
          include: transactionInclude()
        });
      });

      await appendAuditEvent({
        actorUserId: req.user?.id,
        entityType: "PAYMENT",
        entityId: transaction.id,
        type: "PAYMENT_COMPLETED",
        payload: transaction
      });

      ok(res.status(201), transaction);
      return;
    }

    if (!body.provider) {
      throw new ApiHttpError(400, "PAYMENT_PROVIDER_REQUIRED", "Direct contributions require a provider.");
    }

    const internalReference = createPaymentReference(body.type === "INVESTMENT" ? "INV" : "DON");
    const transaction = await prisma.partnerWalletTransaction.create({
      data: {
        walletId: wallet.id,
        partnerId,
        programmeId: programme.id,
        actorUserId: req.user?.id,
        type: body.type,
        provider: body.provider,
        source: "DIRECT",
        status: "PENDING",
        amountCents: body.amountCents,
        currency: "KES",
        description: `${body.type.toLowerCase()} direct payment for ${programme.name}`,
        customerName: req.user?.name,
        customerEmail: req.user?.email,
        phoneNumber: body.phoneNumber,
        internalReference
      }
    });

    const gateway = await initiateIncomingPayment({
      provider: body.provider,
      amountCents: body.amountCents,
      internalReference,
      customerEmail: req.user?.email,
      customerName: req.user?.name,
      phoneNumber: body.phoneNumber,
      description: `${body.type.toLowerCase()} for ${programme.name}`,
      metadata: { programmeId: programme.id, partnerId, type: body.type }
    });
    const updated = await updateTransactionGatewayFields(transaction.id, gateway);

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "PAYMENT",
      entityId: updated.id,
      type: "PAYMENT_INITIATED",
      payload: updated
    });

    ok(res.status(201), updated);
  } catch (error) {
    next(error);
  }
});

router.get("/payment-requests", requireAuth("payments:approve"), async (_req, res, next) => {
  try {
    const requests = await prisma.partnerWalletTransaction.findMany({
      orderBy: { createdAt: "desc" },
      include: transactionInclude()
    });

    ok(res, requests);
  } catch (error) {
    next(error);
  }
});

router.post("/payment-requests/:id/approve-withdrawal", requireAuth("payments:approve"), async (req, res, next) => {
  try {
    const transactionId = z.string().parse(req.params.id);
    const existing = await prisma.partnerWalletTransaction.findUnique({
      where: { id: transactionId },
      include: transactionInclude()
    });

    if (!existing || existing.type !== "WITHDRAWAL") {
      throw new ApiHttpError(404, "WITHDRAWAL_NOT_FOUND", "Withdrawal request does not exist.");
    }
    if (existing.status !== "PENDING") {
      throw new ApiHttpError(400, "WITHDRAWAL_NOT_PENDING", "Only pending withdrawals can be approved.");
    }

    const approved = await prisma.partnerWalletTransaction.update({
      where: { id: existing.id },
      data: {
        status: "APPROVED",
        approvedAt: new Date(),
        approvedByUserId: req.user?.id
      }
    });

    try {
      const gateway = await initiatePayout({
        provider: approved.provider as "MPESA_DARAJA" | "PAYSTACK",
        amountCents: approved.amountCents,
        internalReference: approved.internalReference,
        phoneNumber: approved.payoutPhoneNumber,
        recipientCode: approved.payoutRecipientCode,
        description: approved.description ?? "Partner wallet withdrawal"
      });
      const updated = await updateTransactionGatewayFields(approved.id, gateway);

      await appendAuditEvent({
        actorUserId: req.user?.id,
        entityType: "PAYMENT",
        entityId: updated.id,
        type: "WITHDRAWAL_APPROVED",
        payload: updated
      });

      ok(res, updated);
    } catch (gatewayError) {
      await failWithdrawal(
        approved.internalReference,
        gatewayError instanceof Error ? gatewayError.message : "Payout initiation failed"
      );
      throw gatewayError;
    }
  } catch (error) {
    next(error);
  }
});

router.post("/payment-requests/:id/reject-withdrawal", requireAuth("payments:approve"), async (req, res, next) => {
  try {
    const body = rejectionSchema.parse(req.body);
    const transactionId = z.string().parse(req.params.id);
    const rejected = await rejectWithdrawal(transactionId, req.user?.id, body.reason);

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "PAYMENT",
      entityId: rejected.id,
      type: "WITHDRAWAL_REJECTED",
      payload: rejected
    });

    ok(res, rejected);
  } catch (error) {
    next(error);
  }
});

/**
 * Store a provider event once. The insert IS the check: `eventId` is unique,
 * so of two copies arriving together exactly one is created and the other
 * reads as a duplicate. (A find-then-create let both through the find.)
 */
async function storeWebhook(input: {
  provider: string;
  eventId: string;
  reference?: string | null;
  signatureValid?: boolean;
  payload: unknown;
}) {
  try {
    const event = await prisma.paymentWebhookEvent.create({
      data: {
        provider: input.provider,
        eventId: input.eventId,
        reference: input.reference,
        signatureValid: input.signatureValid ?? true,
        payloadJson: JSON.stringify(input.payload),
        processed: false
      }
    });
    return { event, duplicate: false };
  } catch (error) {
    if ((error as { code?: string }).code === "P2002") {
      const existing = await prisma.paymentWebhookEvent.findUnique({ where: { eventId: input.eventId } });
      if (existing) return { event: existing, duplicate: true };
    }
    throw error;
  }
}

async function markProcessed(eventId: string) {
  await prisma.paymentWebhookEvent.update({ where: { id: eventId }, data: { processed: true } });
}

function itemsToRecord<T extends { Name?: string; Key?: string; Value?: unknown }>(items: T[] | undefined) {
  return Object.fromEntries(
    (items ?? [])
      .map((item) => [item.Name ?? item.Key, item.Value] as const)
      .filter((pair): pair is readonly [string, unknown] => Boolean(pair[0]))
  );
}

router.post("/payments/mpesa/stk-callback", async (req, res, next) => {
  try {
    const payload = req.body as {
      Body?: {
        stkCallback?: {
          CheckoutRequestID?: string;
          ResultCode?: number;
          ResultDesc?: string;
          CallbackMetadata?: { Item?: Array<{ Name?: string; Value?: unknown }> };
        };
      };
    };
    const callback = payload.Body?.stkCallback;
    const reference = callback?.CheckoutRequestID;

    if (!reference) throw new ApiHttpError(400, "MPESA_REFERENCE_MISSING", "M-Pesa callback reference is missing.");

    const webhook = await storeWebhook({
      provider: "MPESA_DARAJA",
      eventId: `mpesa-stk-${reference}-${callback?.ResultCode ?? "unknown"}`,
      reference,
      payload
    });

    if (!webhook.duplicate) {
      const metadata = itemsToRecord(callback?.CallbackMetadata?.Item);

      if (callback?.ResultCode === 0) {
        const receipt = typeof metadata.MpesaReceiptNumber === "string" ? metadata.MpesaReceiptNumber : undefined;
        // The reference belongs to either a partner wallet transaction or a
        // group payment; the one that doesn't own it is a no-op.
        await completeIncomingTransaction(reference, { ...metadata, providerTransactionId: receipt });
        await completeGroupPayment(reference, {
          source: "MPESA_CALLBACK",
          // Daraja reports whole shillings.
          amountCents: typeof metadata.Amount === "number" ? Math.round(metadata.Amount * 100) : null,
          phoneNumber: metadata.PhoneNumber != null ? String(metadata.PhoneNumber) : null,
          providerTransactionId: receipt ?? null,
          raw: metadata
        });
      } else {
        const reason = callback?.ResultDesc ?? "M-Pesa payment failed.";
        await failIncomingTransaction(reference, reason, payload);
        await failGroupPayment(reference, reason, payload);
      }

      await markProcessed(webhook.event.id);
    }

    ok(res, { received: true });
  } catch (error) {
    next(error);
  }
});

/** B2C and B2B results share one shape; both serve wallets and settlements. */
async function handlePayoutResult(kind: "b2c" | "b2b", body: unknown) {
  const payload = body as {
    Result?: {
      ConversationID?: string;
      OriginatorConversationID?: string;
      ResultCode?: number | string;
      ResultDesc?: string;
      TransactionID?: string;
      ResultParameters?: { ResultParameter?: Array<{ Key?: string; Value?: unknown }> };
    };
  };
  const result = payload.Result;
  const reference = result?.ConversationID ?? result?.OriginatorConversationID;
  if (!reference) throw new ApiHttpError(400, "MPESA_REFERENCE_MISSING", "M-Pesa payout reference is missing.");

  const webhook = await storeWebhook({
    provider: "MPESA_DARAJA",
    eventId: `mpesa-${kind}-${reference}-${result?.ResultCode ?? "unknown"}`,
    reference,
    payload
  });
  if (webhook.duplicate) return;

  const metadata = itemsToRecord(result?.ResultParameters?.ResultParameter);
  const receipt =
    typeof metadata.TransactionReceipt === "string" ? metadata.TransactionReceipt : result?.TransactionID ?? null;

  if (String(result?.ResultCode) === "0") {
    if (kind === "b2c") await completeWithdrawal(reference, { ...metadata, providerTransactionId: receipt ?? undefined });
    await completeSettlement(reference, receipt, payload);
  } else {
    const reason = result?.ResultDesc ?? "M-Pesa payout failed.";
    if (kind === "b2c") await failWithdrawal(reference, reason, payload);
    await failSettlement(reference, reason, payload);
  }
  await markProcessed(webhook.event.id);
}

router.post("/payments/mpesa/b2c-result", async (req, res, next) => {
  try {
    await handlePayoutResult("b2c", req.body);
    ok(res, { received: true });
  } catch (error) {
    next(error);
  }
});

router.post("/payments/mpesa/b2b-result", async (req, res, next) => {
  try {
    await handlePayoutResult("b2b", req.body);
    ok(res, { received: true });
  } catch (error) {
    next(error);
  }
});

/**
 * A payout timed out in Safaricom's queue. For a partner withdrawal this was
 * already treated as a failure. For a settlement it is NOT: the money may
 * still have moved, so it becomes UNKNOWN and is checked, never re-sent.
 */
router.post("/payments/mpesa/b2c-timeout", async (req, res, next) => {
  try {
    const payload = req.body as { Result?: { ConversationID?: string; OriginatorConversationID?: string } };
    const reference = payload.Result?.ConversationID ?? payload.Result?.OriginatorConversationID;

    if (reference) {
      await failWithdrawal(reference, "M-Pesa payout timed out.", payload);
      await markSettlementUnknown(reference, "M-Pesa payout timed out in the queue.", payload);
    }

    ok(res, { received: true });
  } catch (error) {
    next(error);
  }
});

router.post("/payments/mpesa/b2b-timeout", async (req, res, next) => {
  try {
    const payload = req.body as { Result?: { ConversationID?: string; OriginatorConversationID?: string } };
    const reference = payload.Result?.ConversationID ?? payload.Result?.OriginatorConversationID;
    if (reference) await markSettlementUnknown(reference, "M-Pesa B2B payout timed out in the queue.", payload);
    ok(res, { received: true });
  } catch (error) {
    next(error);
  }
});

/**
 * Answer to a Transaction Status query sent for an UNKNOWN settlement. The
 * query's Occasion carries our settlement reference.
 */
router.post("/payments/mpesa/status-result", async (req, res, next) => {
  try {
    const payload = req.body as {
      Result?: {
        ResultCode?: number | string;
        ResultDesc?: string;
        ReferenceData?: { ReferenceItem?: { Key?: string; Value?: unknown } | Array<{ Key?: string; Value?: unknown }> };
        ResultParameters?: { ResultParameter?: Array<{ Key?: string; Value?: unknown }> };
      };
    };
    const result = payload.Result;
    const referenceItems = result?.ReferenceData?.ReferenceItem;
    const references = itemsToRecord(Array.isArray(referenceItems) ? referenceItems : referenceItems ? [referenceItems] : []);
    const parameters = itemsToRecord(result?.ResultParameters?.ResultParameter);
    const occasion = typeof references.Occasion === "string" ? references.Occasion : null;

    if (occasion) {
      await storeWebhook({
        provider: "MPESA_DARAJA",
        eventId: `mpesa-status-${occasion}-${Date.now()}`,
        reference: occasion,
        payload
      });
      const status = String(parameters.TransactionStatus ?? "").toLowerCase();
      const receipt = typeof parameters.ReceiptNo === "string" ? parameters.ReceiptNo : null;
      if (String(result?.ResultCode) === "0" && status === "completed") {
        await completeSettlement(occasion, receipt, payload);
      } else if (String(result?.ResultCode) === "0" && (status === "failed" || status === "cancelled")) {
        await failSettlement(occasion, `M-Pesa reports the payout ${status}.`, payload);
      }
      // Anything else leaves it UNKNOWN for a person.
    }
    ok(res, { received: true });
  } catch (error) {
    next(error);
  }
});

router.post("/payments/mpesa/status-timeout", (_req, res) => {
  ok(res, { received: true });
});

/**
 * Paystack webhooks. The payment is looked up BEFORE the signature is
 * checked, because the secret belongs to whoever collected the money: a
 * group collecting into its own Paystack account signs with its own key.
 * Looking the row up changes nothing; nothing settles until the signature
 * passes. The signature is computed over the raw bytes Paystack sent.
 */
router.post("/payments/paystack/webhook", async (req, res, next) => {
  try {
    const payload = req.body as {
      event?: string;
      data?: {
        id?: number | string;
        reference?: string;
        transfer_code?: string;
        status?: string;
        amount?: number;
        currency?: string;
        gateway_response?: string;
        reason?: string;
      };
    };
    const reference = payload.data?.reference ?? payload.data?.transfer_code;

    const owner = reference
      ? await prisma.groupPayment.findFirst({
          where: { OR: [{ providerReference: reference }, { internalReference: reference }] },
          select: { groupId: true, collectionMode: true }
        })
      : null;
    const secret = await paystackSecretFor(owner ? credentialGroupId(owner.collectionMode, owner.groupId) : null);
    const rawBody = (req as unknown as { rawBody?: Buffer }).rawBody;
    const signatureValid = verifyPaystackSignature(rawBody ?? req.body, req.headers["x-paystack-signature"], secret);

    if (!signatureValid) {
      throw new ApiHttpError(400, "PAYSTACK_SIGNATURE_INVALID", "Paystack webhook signature is invalid.");
    }
    if (!payload.event || !reference) {
      throw new ApiHttpError(400, "PAYSTACK_REFERENCE_MISSING", "Paystack webhook reference is missing.");
    }

    const webhook = await storeWebhook({
      provider: "PAYSTACK",
      eventId: `paystack-${payload.event}-${reference}`,
      reference,
      signatureValid,
      payload
    });

    if (!webhook.duplicate) {
      if (payload.event === "charge.success") {
        const providerTransactionId = payload.data?.id ? String(payload.data.id) : undefined;
        await completeIncomingTransaction(reference, {
          providerTransactionId,
          status: payload.data?.status,
          gatewayResponse: payload.data?.gateway_response
        });
        await completeGroupPayment(reference, {
          source: "PAYSTACK_WEBHOOK",
          amountCents: typeof payload.data?.amount === "number" ? payload.data.amount : null,
          providerTransactionId: providerTransactionId ?? null,
          raw: payload.data
        });
      } else if (payload.event === "charge.failed") {
        const reason = payload.data?.gateway_response ?? "Paystack payment failed.";
        await failIncomingTransaction(reference, reason, payload);
        await failGroupPayment(reference, reason, payload);
      } else if (payload.event === "transfer.success") {
        await completeWithdrawal(reference, {
          providerTransactionId: payload.data?.id ? String(payload.data.id) : undefined,
          status: payload.data?.status
        });
        await completeSettlement(reference, payload.data?.transfer_code ?? null, payload.data);
      } else if (payload.event === "transfer.failed" || payload.event === "transfer.reversed") {
        const reason = payload.data?.reason ?? payload.data?.gateway_response ?? payload.event;
        await failWithdrawal(reference, reason, payload);
        await failSettlement(reference, reason, payload.data);
      }

      await markProcessed(webhook.event.id);
    }

    ok(res, { received: true });
  } catch (error) {
    next(error);
  }
});

export { router as paymentsRouter };
