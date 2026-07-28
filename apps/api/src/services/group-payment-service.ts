import { prisma } from "../lib/prisma";

/**
 * Completion hooks for money a member pays INTO a group through a payment
 * gateway (M-Pesa STK push or Paystack checkout).
 *
 * The gateway callbacks in `routes/payments.ts` serve two different ledgers:
 * partner wallet transactions and these group payments. A callback carries
 * only a reference, so each handler tries both — whichever owns the
 * reference updates, the other is a no-op.
 */

function metadataJson(value: unknown) {
  return JSON.stringify(value ?? {});
}

/** Matches on the gateway's reference or the one we generated. */
function referenceWhere(reference: string) {
  return {
    OR: [{ providerReference: reference }, { internalReference: reference }]
  };
}

export async function completeGroupPayment(
  reference: string,
  metadata: Record<string, unknown> = {}
) {
  const payment = await prisma.groupPayment.findFirst({
    where: referenceWhere(reference)
  });
  // Not a group payment (probably a partner wallet one), or already settled.
  if (!payment || payment.status !== "PENDING") return null;

  return prisma.groupPayment.update({
    where: { id: payment.id },
    data: {
      status: "COMPLETED",
      completedAt: new Date(),
      providerTransactionId:
        typeof metadata.providerTransactionId === "string"
          ? metadata.providerTransactionId
          : typeof metadata.MpesaReceiptNumber === "string"
            ? metadata.MpesaReceiptNumber
            : null,
      metadataJson: metadataJson({
        previous: payment.metadataJson ? JSON.parse(payment.metadataJson) : {},
        callback: metadata
      })
    }
  });
}

export async function failGroupPayment(
  reference: string,
  reason: string,
  metadata: Record<string, unknown> = {}
) {
  const payment = await prisma.groupPayment.findFirst({
    where: referenceWhere(reference)
  });
  if (!payment || payment.status !== "PENDING") return null;

  return prisma.groupPayment.update({
    where: { id: payment.id },
    data: {
      status: "FAILED",
      failureReason: reason,
      metadataJson: metadataJson({
        previous: payment.metadataJson ? JSON.parse(payment.metadataJson) : {},
        callback: metadata
      })
    }
  });
}
