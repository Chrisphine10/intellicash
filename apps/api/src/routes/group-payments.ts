import { Router } from "express";
import { z } from "zod";
import { appendAuditEvent } from "../services/audit-service";
import { requireAuth } from "../middleware/auth";
import { memberScopeForUser, scopeGroupWhere } from "../services/account-scope";
import { createPaymentReference, initiateIncomingPayment } from "../services/payment-service";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";

/**
 * Gateway payments into a VSLA group.
 *
 * Two of the app's money methods are automated and land here:
 *   - **M-Pesa** → a Daraja STK push; the member approves on their handset
 *     and the STK callback settles the payment.
 *   - **Paystack** → a hosted checkout link the payer opens.
 * "M-Pesa Classic" deliberately does NOT come through here: the member pays
 * on their own and the treasurer types the confirmation code onto the ledger
 * entry, which is why that method still asks for a reference.
 *
 * Gated on `ledger:write` rather than `payments:write`: this is a group's own
 * money movement, and it is the scope group accounts and the mobile
 * MOBILE_CORE keys already hold (`payments:write` is for partner wallets and
 * would not reach existing keys without re-minting them).
 */
const router = Router();

const providerSchema = z.enum(["MPESA_DARAJA", "PAYSTACK"]);
const purposeSchema = z.enum([
  "SHARE_PURCHASE",
  "SOCIAL_FUND",
  "LOAN_REPAYMENT",
  "FINE",
  "OTHER"
]);

const initiateSchema = z
  .object({
    provider: providerSchema,
    purpose: purposeSchema.default("SHARE_PURCHASE"),
    amountCents: z.number().int().min(100),
    memberId: z.string().min(1).optional(),
    meetingId: z.string().min(1).optional(),
    phoneNumber: z
      .string()
      .trim()
      .regex(/^(?:\+?254|0)?[17]\d{8}$/, "Enter a valid Kenyan phone number.")
      .optional(),
    customerEmail: z.string().trim().email().optional(),
    /// Lets a phone retry after a dropped response without paying twice.
    clientRequestId: z.string().trim().min(6).max(120).optional()
  })
  .refine((body) => body.provider !== "MPESA_DARAJA" || Boolean(body.phoneNumber), {
    message: "M-Pesa needs the phone number to prompt.",
    path: ["phoneNumber"]
  })
  .refine((body) => body.provider !== "PAYSTACK" || Boolean(body.customerEmail), {
    message: "Paystack needs an email address for the receipt.",
    path: ["customerEmail"]
  });

/** Daraja wants 2547XXXXXXXX. */
function toDarajaMsisdn(phone: string) {
  const digits = phone.replace(/[^0-9]/g, "");
  if (digits.startsWith("254")) return digits;
  if (digits.startsWith("0")) return `254${digits.slice(1)}`;
  return `254${digits}`;
}

const paymentSelect = {
  id: true,
  groupId: true,
  memberId: true,
  meetingId: true,
  purpose: true,
  provider: true,
  amountCents: true,
  currency: true,
  phoneNumber: true,
  status: true,
  internalReference: true,
  providerReference: true,
  providerTransactionId: true,
  checkoutUrl: true,
  failureReason: true,
  createdAt: true,
  completedAt: true,
  member: { select: { id: true, fullName: true } }
} as const;

router.post("/groups/:id/payments", requireAuth("ledger:write"), async (req, res, next) => {
  try {
    const groupId = String(req.params.id);
    const body = initiateSchema.parse(req.body);

    const group = await prisma.group.findFirst({
      where: scopeGroupWhere(req.user, { id: groupId }),
      select: { id: true, name: true }
    });
    if (!group) {
      throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside this account.");
    }

    // Replaying the same clientRequestId returns the in-flight or settled
    // payment rather than prompting the member's phone a second time. A
    // FAILED attempt is different: the member must be able to try again, so
    // release the id and let a fresh attempt take it.
    if (body.clientRequestId) {
      const existing = await prisma.groupPayment.findUnique({
        where: { clientRequestId: body.clientRequestId },
        select: { ...paymentSelect, status: true }
      });
      if (existing && (existing.status === "PENDING" || existing.status === "COMPLETED")) {
        ok(res, existing);
        return;
      }
      if (existing) {
        await prisma.groupPayment.update({
          where: { id: existing.id },
          data: { clientRequestId: null }
        });
      }
    }

    if (body.memberId) {
      const member = await prisma.member.findFirst({
        where: memberScopeForUser(req.user, { id: body.memberId, groupId: group.id }),
        select: { id: true }
      });
      if (!member) {
        throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist in this group.");
      }
    }
    if (body.meetingId) {
      const meeting = await prisma.meeting.findFirst({
        where: { id: body.meetingId, groupId: group.id },
        select: { id: true }
      });
      if (!meeting) {
        throw new ApiHttpError(404, "MEETING_NOT_FOUND", "Meeting does not exist in this group.");
      }
    }

    const internalReference = createPaymentReference(
      body.provider === "MPESA_DARAJA" ? "GMP" : "GPS"
    );
    const phoneNumber = body.phoneNumber ? toDarajaMsisdn(body.phoneNumber) : null;

    // Record the intent BEFORE calling the gateway, so a callback that beats
    // our own response still finds a row to settle.
    const created = await prisma.groupPayment.create({
      data: {
        groupId: group.id,
        memberId: body.memberId,
        meetingId: body.meetingId,
        purpose: body.purpose,
        provider: body.provider,
        amountCents: body.amountCents,
        phoneNumber,
        customerEmail: body.customerEmail,
        internalReference,
        clientRequestId: body.clientRequestId,
        status: "PENDING"
      },
      select: paymentSelect
    });

    let gateway;
    try {
      gateway = await initiateIncomingPayment({
        provider: body.provider,
        amountCents: body.amountCents,
        internalReference,
        phoneNumber,
        customerEmail: body.customerEmail,
        customerName: req.user?.name,
        description: `${body.purpose.replace(/_/g, " ").toLowerCase()} for ${group.name}`,
        metadata: { groupId: group.id, memberId: body.memberId, purpose: body.purpose }
      });
    } catch (error) {
      // The gateway refused — mark it failed so the row isn't left hanging.
      await prisma.groupPayment.update({
        where: { id: created.id },
        data: {
          status: "FAILED",
          failureReason: error instanceof Error ? error.message : "Payment could not be started."
        }
      });
      throw error;
    }

    const payment = await prisma.groupPayment.update({
      where: { id: created.id },
      data: {
        providerReference: gateway.providerReference,
        checkoutUrl: gateway.checkoutUrl ?? null,
        metadataJson: JSON.stringify(gateway.metadata ?? {})
      },
      select: paymentSelect
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "GROUP_PAYMENT",
      entityId: payment.id,
      type: "GROUP_PAYMENT_INITIATED",
      payload: payment
    });

    ok(res.status(201), payment);
  } catch (error) {
    next(error);
  }
});

router.get("/groups/:id/payments", requireAuth("ledger:read"), async (req, res, next) => {
  try {
    const groupId = String(req.params.id);
    const group = await prisma.group.findFirst({
      where: scopeGroupWhere(req.user, { id: groupId }),
      select: { id: true }
    });
    if (!group) {
      throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside this account.");
    }
    const payments = await prisma.groupPayment.findMany({
      where: { groupId: group.id },
      select: paymentSelect,
      orderBy: { createdAt: "desc" },
      take: 100
    });
    ok(res, payments);
  } catch (error) {
    next(error);
  }
});

/** Polled by the phone while the member approves the STK prompt. */
router.get("/groups/:id/payments/:paymentId", requireAuth("ledger:read"), async (req, res, next) => {
  try {
    const groupId = String(req.params.id);
    const group = await prisma.group.findFirst({
      where: scopeGroupWhere(req.user, { id: groupId }),
      select: { id: true }
    });
    if (!group) {
      throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside this account.");
    }
    const payment = await prisma.groupPayment.findFirst({
      where: { id: String(req.params.paymentId), groupId: group.id },
      select: paymentSelect
    });
    if (!payment) throw new ApiHttpError(404, "PAYMENT_NOT_FOUND", "Payment not found.");
    ok(res, payment);
  } catch (error) {
    next(error);
  }
});

export { router as groupPaymentsRouter };
