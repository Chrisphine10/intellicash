import type { Prisma } from "@prisma/client";
import { ApiHttpError } from "../lib/http";

/**
 * Centralised, overdraw-safe partner-wallet operations.
 *
 * Every mutation uses Prisma atomic `increment`/`decrement` operators (never a
 * read-modify-write of an absolute value) so concurrent updates cannot lose
 * writes. Availability checks are performed inside the caller's interactive
 * transaction so the check and the reservation are part of the same atomic unit.
 *
 * NOTE: On SQLite (current deployment) interactive transactions are serialised,
 * so the check-then-increment is race-free. On PostgreSQL at READ COMMITTED a
 * narrow window remains; add `SELECT ... FOR UPDATE` (raw) there if migrated.
 */

type Tx = Prisma.TransactionClient;

export function availableCents(balanceCents: number, heldCents: number) {
  return Math.max(0, balanceCents - heldCents);
}

export async function ensureWallet(tx: Tx, partnerId: string) {
  return tx.partnerWallet.upsert({
    where: { partnerId },
    create: { partnerId, currency: "KES" },
    update: {}
  });
}

interface GuardOptions {
  errorCode?: string;
  errorMessage?: string;
}

function assertAvailable(
  balanceCents: number,
  heldCents: number,
  amountCents: number,
  options: GuardOptions
) {
  if (availableCents(balanceCents, heldCents) < amountCents) {
    throw new ApiHttpError(
      400,
      options.errorCode ?? "INSUFFICIENT_WALLET_FUNDS",
      options.errorMessage ?? "Wallet balance is insufficient for this operation."
    );
  }
}

/** Reserve funds (increment heldCents) after verifying availability in-transaction. */
export async function holdFunds(
  tx: Tx,
  params: { partnerId: string; amountCents: number } & GuardOptions
) {
  const wallet = await ensureWallet(tx, params.partnerId);
  assertAvailable(wallet.balanceCents, wallet.heldCents, params.amountCents, params);
  return tx.partnerWallet.update({
    where: { id: wallet.id },
    data: { heldCents: { increment: params.amountCents } }
  });
}

/** Release a previously placed hold, clamped so heldCents never goes negative. */
export async function releaseHold(tx: Tx, params: { walletId: string; amountCents: number }) {
  const wallet = await tx.partnerWallet.findUniqueOrThrow({ where: { id: params.walletId } });
  const nextHeld = Math.max(0, wallet.heldCents - params.amountCents);
  return tx.partnerWallet.update({
    where: { id: wallet.id },
    data: { heldCents: nextHeld }
  });
}

/** Spend held funds: reduce balance and release the matching hold together. */
export async function settleHeldDebit(tx: Tx, params: { walletId: string; amountCents: number }) {
  return tx.partnerWallet.update({
    where: { id: params.walletId },
    data: {
      balanceCents: { decrement: params.amountCents },
      heldCents: { decrement: params.amountCents }
    }
  });
}

/** Debit available balance directly (no prior hold); verifies availability in-transaction. */
export async function debitAvailable(
  tx: Tx,
  params: { partnerId: string; amountCents: number } & GuardOptions
) {
  const wallet = await ensureWallet(tx, params.partnerId);
  assertAvailable(wallet.balanceCents, wallet.heldCents, params.amountCents, params);
  const updated = await tx.partnerWallet.update({
    where: { id: wallet.id },
    data: { balanceCents: { decrement: params.amountCents } }
  });
  return { wallet: updated, walletId: updated.id };
}

/** Credit balance (e.g. loan repayment returned to a financier). */
export async function creditBalance(tx: Tx, params: { partnerId: string; amountCents: number }) {
  const wallet = await ensureWallet(tx, params.partnerId);
  const updated = await tx.partnerWallet.update({
    where: { id: wallet.id },
    data: { balanceCents: { increment: params.amountCents } }
  });
  return { wallet: updated, walletId: updated.id };
}

/** Persist a wallet transaction with consistent required fields. */
export async function recordWalletTransaction(
  tx: Tx,
  data: {
    walletId?: string | null;
    partnerId: string;
    programmeId?: string | null;
    actorUserId?: string | null;
    type: string;
    provider?: string;
    source?: string;
    status?: string;
    amountCents: number;
    description?: string | null;
    internalReference: string;
    providerReference?: string | null;
    completed?: boolean;
  }
) {
  return tx.partnerWalletTransaction.create({
    data: {
      walletId: data.walletId ?? null,
      partnerId: data.partnerId,
      programmeId: data.programmeId ?? null,
      actorUserId: data.actorUserId ?? null,
      type: data.type,
      provider: data.provider ?? "INTERNAL",
      source: data.source ?? "STORE_CREDIT",
      status: data.status ?? (data.completed === false ? "PENDING" : "COMPLETED"),
      amountCents: data.amountCents,
      description: data.description ?? null,
      internalReference: data.internalReference,
      providerReference: data.providerReference ?? null,
      completedAt: data.completed === false ? null : new Date()
    }
  });
}
