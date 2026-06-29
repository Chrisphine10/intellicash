import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { prisma } from "../src/lib/prisma";
import {
  availableCents,
  creditBalance,
  debitAvailable,
  ensureWallet,
  holdFunds,
  recordWalletTransaction,
  releaseHold,
  settleHeldDebit
} from "../src/services/wallet-service";

const partnerId = "test-wallet-partner";

async function cleanup() {
  await prisma.partnerWalletTransaction.deleteMany({ where: { partnerId } });
  await prisma.partnerWallet.deleteMany({ where: { partnerId } });
  await prisma.partner.deleteMany({ where: { id: partnerId } });
}

describe("wallet-service", () => {
  beforeAll(async () => {
    await cleanup();
    await prisma.partner.create({
      data: { id: partnerId, name: "Wallet Test Partner", type: "INVESTOR" }
    });
  });

  afterAll(cleanup);

  it("computes available balance net of holds and never goes negative", () => {
    expect(availableCents(1000, 300)).toBe(700);
    expect(availableCents(100, 300)).toBe(0);
  });

  it("ensures a wallet and credits then debits the balance", async () => {
    await prisma.$transaction((tx) => ensureWallet(tx, partnerId));
    await prisma.$transaction((tx) => creditBalance(tx, { partnerId, amountCents: 50_000 }));
    await prisma.$transaction((tx) => debitAvailable(tx, { partnerId, amountCents: 20_000 }));

    const wallet = await prisma.partnerWallet.findUniqueOrThrow({ where: { partnerId } });
    expect(wallet.balanceCents).toBe(30_000);
  });

  it("rejects a debit beyond available balance and rolls back", async () => {
    await expect(
      prisma.$transaction((tx) =>
        debitAvailable(tx, {
          partnerId,
          amountCents: 999_999,
          errorCode: "STORE_CREDIT_INSUFFICIENT_CAPITAL"
        })
      )
    ).rejects.toMatchObject({ code: "STORE_CREDIT_INSUFFICIENT_CAPITAL" });

    const wallet = await prisma.partnerWallet.findUniqueOrThrow({ where: { partnerId } });
    expect(wallet.balanceCents).toBe(30_000); // unchanged
  });

  it("holds within available, refuses over-hold, and settles held debits", async () => {
    await prisma.$transaction((tx) => holdFunds(tx, { partnerId, amountCents: 10_000 }));
    let wallet = await prisma.partnerWallet.findUniqueOrThrow({ where: { partnerId } });
    expect(wallet.heldCents).toBe(10_000);
    expect(availableCents(wallet.balanceCents, wallet.heldCents)).toBe(20_000);

    await expect(
      prisma.$transaction((tx) => holdFunds(tx, { partnerId, amountCents: 999_999 }))
    ).rejects.toMatchObject({ code: "INSUFFICIENT_WALLET_FUNDS" });

    await prisma.$transaction((tx) =>
      settleHeldDebit(tx, { walletId: wallet.id, amountCents: 10_000 })
    );
    wallet = await prisma.partnerWallet.findUniqueOrThrow({ where: { partnerId } });
    expect(wallet.balanceCents).toBe(20_000);
    expect(wallet.heldCents).toBe(0);
  });

  it("clamps hold release so heldCents never goes negative", async () => {
    const wallet = await prisma.partnerWallet.findUniqueOrThrow({ where: { partnerId } });
    await prisma.$transaction((tx) =>
      releaseHold(tx, { walletId: wallet.id, amountCents: 5_000 })
    );
    const after = await prisma.partnerWallet.findUniqueOrThrow({ where: { partnerId } });
    expect(after.heldCents).toBe(0);
  });

  it("records wallet transactions with consistent defaults", async () => {
    const ref = `TESTREF-${Date.now()}`;
    await prisma.$transaction((tx) =>
      recordWalletTransaction(tx, {
        partnerId,
        type: "STORE_CREDIT_FINANCING",
        amountCents: 12_345,
        internalReference: ref
      })
    );

    const txn = await prisma.partnerWalletTransaction.findUniqueOrThrow({
      where: { internalReference: ref }
    });
    expect(txn.type).toBe("STORE_CREDIT_FINANCING");
    expect(txn.provider).toBe("INTERNAL");
    expect(txn.source).toBe("STORE_CREDIT");
    expect(txn.status).toBe("COMPLETED");
    expect(txn.amountCents).toBe(12_345);
  });
});
