/**
 * Payment gateway integration (M-Pesa Daraja and Paystack).
 *
 * Initiates inbound payments (STK push, checkout) and outbound payouts, and
 * completes or fails them from provider callbacks. Credentials resolve group-
 * first then platform-default, so a group with its own M-Pesa till settles
 * into their account rather than the platform's.
 */

import { createHash, createHmac, randomUUID, timingSafeEqual } from "node:crypto";
import { env } from "../config/env";
import { ApiHttpError } from "../lib/http";
import { releaseHold, settleHeldDebit } from "./wallet-service";
import { prisma } from "../lib/prisma";
import { decryptCredentials } from "./integration-credentials";

export type PaymentProvider = "MPESA_DARAJA" | "PAYSTACK" | "INTERNAL";
export type PaymentTransactionType = "DEPOSIT" | "WITHDRAWAL" | "INVESTMENT" | "DONATION";

interface IncomingPaymentInput {
  provider: Exclude<PaymentProvider, "INTERNAL">;
  /**
   * When set, this group's own gateway credentials are preferred over the
   * platform's, so the money lands in the group's account.
   */
  groupId?: string | null;

  amountCents: number;
  internalReference: string;
  customerEmail?: string | null;
  customerName?: string | null;
  phoneNumber?: string | null;
  description: string;
  metadata?: Record<string, unknown>;
  /** Where Paystack returns the payer after checkout. Defaults to the partner portal. */
  returnUrl?: string | null;
}

interface PayoutInput {
  provider: Exclude<PaymentProvider, "INTERNAL">;
  /**
   * When set, this group's own gateway credentials are preferred over the
   * platform's, so the money lands in the group's account.
   */
  groupId?: string | null;

  amountCents: number;
  internalReference: string;
  phoneNumber?: string | null;
  recipientCode?: string | null;
  description: string;
  /**
   * For M-Pesa: PHONE pays a person (B2C, the default); PAYBILL and TILL pay
   * a business (B2B). Settlements to a group's own paybill or till use B2B.
   */
  mpesaPayoutKind?: "PHONE" | "PAYBILL" | "TILL";
  /** B2B: the receiving shortcode (paybill or till number). */
  receiverShortcode?: string | null;
  /** B2B to a paybill: the account number at that paybill. */
  accountReference?: string | null;
}

interface GatewayResult {
  providerReference: string;
  checkoutUrl?: string | null;
  accessCode?: string | null;
  metadata: Record<string, unknown>;
}

const providerKeys: Record<Exclude<PaymentProvider, "INTERNAL">, string[]> = {
  MPESA_DARAJA: [
    "MPESA_CONSUMER_KEY",
    "MPESA_CONSUMER_SECRET",
    "MPESA_SHORTCODE",
    "MPESA_PASSKEY",
    "MPESA_CALLBACK_URL",
    "MPESA_INITIATOR_NAME",
    "MPESA_SECURITY_CREDENTIAL",
    "MPESA_B2C_RESULT_URL",
    "MPESA_B2C_TIMEOUT_URL"
  ],
  PAYSTACK: ["PAYSTACK_SECRET_KEY", "PAYSTACK_PUBLIC_KEY"]
};

/**
 * Keys that are carried through but are NOT required.
 *
 * `MPESA_ENVIRONMENT` cannot be required: every group already configured would
 * become "incomplete" the moment it was added, and collections would start
 * failing for groups that had changed nothing.
 */
const optionalProviderKeys: Record<Exclude<PaymentProvider, "INTERNAL">, string[]> = {
  MPESA_DARAJA: ["MPESA_ENVIRONMENT"],
  PAYSTACK: []
};

/**
 * Which Safaricom host a group's Daraja credentials belong to.
 *
 * Until 2 Aug 2026 every Daraja call was hardcoded to sandbox.safaricom.co.ke,
 * so a group holding a REAL Daraja account could not transact at all — live
 * credentials presented to the sandbox are simply rejected. That made the
 * whole per-group provider feature untestable in production.
 *
 * Defaults to SANDBOX deliberately. A wrong guess in this direction fails
 * loudly at authentication; the opposite default would quietly send a
 * misconfigured group's members' money through the live rails.
 */
const MPESA_HOSTS = {
  SANDBOX: "https://sandbox.safaricom.co.ke",
  LIVE: "https://api.safaricom.co.ke"
} as const;

export type MpesaEnvironment = keyof typeof MPESA_HOSTS;

export function mpesaEnvironment(credentials: Record<string, string>): MpesaEnvironment {
  const raw = (credentials.MPESA_ENVIRONMENT ?? "").trim().toUpperCase();
  // PRODUCTION is what Safaricom's own portal calls it, so accept both rather
  // than silently dropping a group onto sandbox because of a synonym.
  if (raw === "LIVE" || raw === "PRODUCTION") return "LIVE";
  return "SANDBOX";
}

export function mpesaBaseUrl(credentials: Record<string, string>) {
  return MPESA_HOSTS[mpesaEnvironment(credentials)];
}

function metadataJson(value: unknown) {
  return JSON.stringify(value ?? {});
}

function asWholeShillings(amountCents: number) {
  return Math.max(1, Math.round(amountCents / 100));
}

function timestamp() {
  const date = new Date();
  const pad = (value: number) => value.toString().padStart(2, "0");
  return [
    date.getFullYear(),
    pad(date.getMonth() + 1),
    pad(date.getDate()),
    pad(date.getHours()),
    pad(date.getMinutes()),
    pad(date.getSeconds())
  ].join("");
}

function callbackUrl(path: string) {
  return `${env.API_PUBLIC_URL.replace(/\/$/, "")}${path}`;
}

function combineCredentials(
  provider: Exclude<PaymentProvider, "INTERNAL">,
  storedCredentials: Record<string, string>
) {
  const credentials: Record<string, string> = {};
  for (const key of [...providerKeys[provider], ...optionalProviderKeys[provider]]) {
    const value = storedCredentials[key] || process.env[key];
    if (value) credentials[key] = value;
  }
  return credentials;
}

/**
 * Resolve gateway credentials for a payment.
 *
 * Order: the GROUP's own configuration, then the platform's, then process env.
 * A group that has published its own till keeps members' money in its own
 * account; a group with no configuration silently uses the platform default,
 * so adding this changed nothing for existing groups.
 *
 * Only keys the group actually set override the platform - a group may supply
 * a shortcode and passkey while still using the platform's callback URLs.
 */
export async function credentialsFor(
  provider: Exclude<PaymentProvider, "INTERNAL">,
  groupId?: string | null
) {
  const platformConfig = await prisma.integrationConfig.findUnique({ where: { provider } });
  const stored = decryptCredentials(platformConfig?.credentialsJson);

  if (groupId) {
    const groupConfig = await prisma.groupIntegrationConfig.findUnique({
      where: { groupId_provider: { groupId, provider } }
    });
    // A disabled row means "deliberately not using our own" — fall back rather
    // than failing, so switching off cannot strand a group mid-collection.
    if (groupConfig?.enabled) {
      Object.assign(stored, decryptCredentials(groupConfig.credentialsJson));
    }
  }

  return combineCredentials(provider, stored);
}

function missingKeys(provider: Exclude<PaymentProvider, "INTERNAL">, credentials: Record<string, string>) {
  return providerKeys[provider].filter((key) => !credentials[key]);
}

function assertNetworkCredentials(
  provider: Exclude<PaymentProvider, "INTERNAL">,
  credentials: Record<string, string>
) {
  const missing = missingKeys(provider, credentials);
  if (missing.length > 0) {
    throw new ApiHttpError(
      400,
      "PAYMENT_PROVIDER_NOT_CONFIGURED",
      `${provider} payment credentials are incomplete.`,
      { missing }
    );
  }
}

/**
 * Daraja access tokens, cached per credential. Keyed on a hash of the consumer
 * key AND host, never on the provider name: with per-group credentials a
 * name-keyed cache hands one group's token to another group's collection —
 * money into the wrong account.
 */
const mpesaTokenCache = new Map<string, { token: string; expiresAt: number }>();

function mpesaTokenCacheKey(credentials: Record<string, string>) {
  return createHash("sha256")
    .update(`${mpesaBaseUrl(credentials)}|${credentials.MPESA_CONSUMER_KEY ?? ""}|${credentials.MPESA_CONSUMER_SECRET ?? ""}`)
    .digest("hex")
    .slice(0, 24);
}

async function mpesaToken(credentials: Record<string, string>) {
  const key = credentials.MPESA_CONSUMER_KEY;
  const secret = credentials.MPESA_CONSUMER_SECRET;
  if (!key || !secret) throw new ApiHttpError(400, "MPESA_NOT_CONFIGURED", "M-Pesa credentials are incomplete.");

  const cacheKey = mpesaTokenCacheKey(credentials);
  const cached = mpesaTokenCache.get(cacheKey);
  if (cached && cached.expiresAt - 30_000 > Date.now()) return cached.token;

  const auth = Buffer.from(`${key}:${secret}`).toString("base64");
  const response = await fetch(
    `${mpesaBaseUrl(credentials)}/oauth/v1/generate?grant_type=client_credentials`,
    { headers: { Authorization: `Basic ${auth}` } }
  );
  const payload = (await response.json().catch(() => null)) as
    | { access_token?: string; expires_in?: string | number }
    | null;

  if (!response.ok || !payload?.access_token) {
    throw new ApiHttpError(502, "MPESA_TOKEN_FAILED", "M-Pesa access token request failed.", payload);
  }

  const lifetimeSeconds = Number(payload.expires_in) > 0 ? Number(payload.expires_in) : 3599;
  mpesaTokenCache.set(cacheKey, { token: payload.access_token, expiresAt: Date.now() + lifetimeSeconds * 1000 });
  return payload.access_token;
}

export function createPaymentReference(prefix: string) {
  return `${prefix}-${Date.now()}-${randomUUID().slice(0, 8)}`.toUpperCase();
}

export async function initiateIncomingPayment(input: IncomingPaymentInput): Promise<GatewayResult> {
  if (!env.ENABLE_PAYMENT_NETWORK_CALLS) {
    return {
      providerReference: `mock-${input.internalReference}`,
      checkoutUrl:
        input.provider === "PAYSTACK"
          ? `https://checkout.paystack.com/mock-${input.internalReference.toLowerCase()}`
          : null,
      accessCode: input.provider === "PAYSTACK" ? `mock-${input.internalReference}` : null,
      metadata: {
        mode: "mock",
        message:
          input.provider === "MPESA_DARAJA"
            ? "M-Pesa STK Push queued in local mode."
            : "Paystack checkout initialized in local mode."
      }
    };
  }

  const credentials = await credentialsFor(input.provider, input.groupId);
  assertNetworkCredentials(input.provider, credentials);

  if (input.provider === "PAYSTACK") {
    const response = await fetch("https://api.paystack.co/transaction/initialize", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${credentials.PAYSTACK_SECRET_KEY}`,
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        amount: input.amountCents,
        email: input.customerEmail,
        currency: "KES",
        reference: input.internalReference,
        callback_url: input.returnUrl || `${env.WEB_ORIGIN.replace(/\/$/, "")}/partners`,
        metadata: {
          ...input.metadata,
          customerName: input.customerName,
          description: input.description
        }
      })
    });
    const payload = (await response.json().catch(() => null)) as
      | { status?: boolean; data?: { reference?: string; authorization_url?: string; access_code?: string } }
      | null;

    if (!response.ok || !payload?.status || !payload.data?.reference) {
      throw new ApiHttpError(502, "PAYSTACK_INITIALIZE_FAILED", "Paystack checkout initialization failed.", payload);
    }

    return {
      providerReference: payload.data.reference,
      checkoutUrl: payload.data.authorization_url ?? null,
      accessCode: payload.data.access_code ?? null,
      metadata: payload as Record<string, unknown>
    };
  }

  const shortcode = credentials.MPESA_SHORTCODE;
  const passkey = credentials.MPESA_PASSKEY;
  const phone = input.phoneNumber;
  if (!shortcode || !passkey || !phone) {
    throw new ApiHttpError(400, "MPESA_PAYMENT_DETAILS_REQUIRED", "M-Pesa payments require shortcode, passkey, and phone number.");
  }

  const requestTimestamp = timestamp();
  const token = await mpesaToken(credentials);
  const response = await fetch(`${mpesaBaseUrl(credentials)}/mpesa/stkpush/v1/processrequest`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      BusinessShortCode: shortcode,
      Password: Buffer.from(`${shortcode}${passkey}${requestTimestamp}`).toString("base64"),
      Timestamp: requestTimestamp,
      TransactionType: "CustomerPayBillOnline",
      Amount: asWholeShillings(input.amountCents),
      PartyA: phone,
      PartyB: shortcode,
      PhoneNumber: phone,
      CallBackURL: credentials.MPESA_CALLBACK_URL || callbackUrl("/api/v1/payments/mpesa/stk-callback"),
      // Daraja's limits: AccountReference 12 characters, TransactionDesc 13.
      // Longer values are refused or cut by Safaricom; cut them here so the
      // member's statement shows something predictable. The full reference
      // is still what CheckoutRequestID is matched against.
      AccountReference: input.internalReference.replace(/[^A-Z0-9]/gi, "").slice(-12),
      TransactionDesc: input.description.slice(0, 13)
    })
  });
  const payload = (await response.json().catch(() => null)) as
    | { CheckoutRequestID?: string; MerchantRequestID?: string; ResponseCode?: string }
    | null;

  if (!response.ok || !payload?.CheckoutRequestID) {
    throw new ApiHttpError(502, "MPESA_STK_FAILED", "M-Pesa STK Push request failed.", payload);
  }

  return {
    providerReference: payload.CheckoutRequestID,
    metadata: payload as Record<string, unknown>
  };
}

export async function initiatePayout(input: PayoutInput): Promise<GatewayResult> {
  if (!env.ENABLE_PAYMENT_NETWORK_CALLS) {
    return {
      providerReference: `mock-${input.internalReference}`,
      metadata: {
        mode: "mock",
        message:
          input.provider === "MPESA_DARAJA"
            ? "M-Pesa B2C payout queued in local mode."
            : "Paystack transfer queued in local mode."
      }
    };
  }

  const credentials = await credentialsFor(input.provider, input.groupId);
  assertNetworkCredentials(input.provider, credentials);

  if (input.provider === "PAYSTACK") {
    if (!input.recipientCode) {
      throw new ApiHttpError(400, "PAYSTACK_RECIPIENT_REQUIRED", "Paystack withdrawals require a transfer recipient code.");
    }

    const response = await fetch("https://api.paystack.co/transfer", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${credentials.PAYSTACK_SECRET_KEY}`,
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        source: "balance",
        amount: input.amountCents,
        currency: "KES",
        reference: input.internalReference,
        recipient: input.recipientCode,
        reason: input.description
      })
    });
    const payload = (await response.json().catch(() => null)) as
      | { status?: boolean; data?: { reference?: string; transfer_code?: string } }
      | null;

    if (!response.ok || !payload?.status || !payload.data?.reference) {
      throw new ApiHttpError(502, "PAYSTACK_TRANSFER_FAILED", "Paystack transfer request failed.", payload);
    }

    return {
      providerReference: payload.data.reference,
      metadata: payload as Record<string, unknown>
    };
  }

  const shortcode = credentials.MPESA_SHORTCODE;
  if (input.mpesaPayoutKind === "PAYBILL" || input.mpesaPayoutKind === "TILL") {
    return initiateMpesaB2B(input, credentials);
  }
  const phone = input.phoneNumber;
  if (!shortcode || !phone) {
    throw new ApiHttpError(400, "MPESA_PAYOUT_DETAILS_REQUIRED", "M-Pesa payouts require shortcode and recipient phone number.");
  }

  const token = await mpesaToken(credentials);
  const response = await fetch(`${mpesaBaseUrl(credentials)}/mpesa/b2c/v1/paymentrequest`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      InitiatorName: credentials.MPESA_INITIATOR_NAME,
      SecurityCredential: credentials.MPESA_SECURITY_CREDENTIAL,
      CommandID: "BusinessPayment",
      Amount: asWholeShillings(input.amountCents),
      PartyA: shortcode,
      PartyB: phone,
      Remarks: input.description,
      QueueTimeOutURL: credentials.MPESA_B2C_TIMEOUT_URL || callbackUrl("/api/v1/payments/mpesa/b2c-timeout"),
      ResultURL: credentials.MPESA_B2C_RESULT_URL || callbackUrl("/api/v1/payments/mpesa/b2c-result"),
      Occasion: input.internalReference
    })
  });
  const payload = (await response.json().catch(() => null)) as
    | { ConversationID?: string; OriginatorConversationID?: string; ResponseCode?: string }
    | null;

  if (!response.ok || !payload?.ConversationID) {
    throw new ApiHttpError(502, "MPESA_B2C_FAILED", "M-Pesa B2C payout request failed.", payload);
  }

  return {
    providerReference: payload.ConversationID,
    metadata: payload as Record<string, unknown>
  };
}

export async function updateTransactionGatewayFields(
  transactionId: string,
  gateway: GatewayResult
) {
  return prisma.partnerWalletTransaction.update({
    where: { id: transactionId },
    data: {
      providerReference: gateway.providerReference,
      providerCheckoutUrl: gateway.checkoutUrl ?? null,
      providerAccessCode: gateway.accessCode ?? null,
      providerMetadataJson: metadataJson(gateway.metadata)
    }
  });
}

export async function completeIncomingTransaction(reference: string, metadata: Record<string, unknown> = {}) {
  return prisma.$transaction(async (tx) => {
    const transaction = await tx.partnerWalletTransaction.findFirst({
      where: {
        OR: [{ providerReference: reference }, { internalReference: reference }]
      }
    });

    if (!transaction || transaction.type === "WITHDRAWAL") return transaction;
    if (transaction.status === "COMPLETED") return transaction;
    if (transaction.status !== "PENDING") return transaction;

    if (transaction.type === "DEPOSIT" && transaction.walletId) {
      await tx.partnerWallet.update({
        where: { id: transaction.walletId },
        data: { balanceCents: { increment: transaction.amountCents } }
      });
    }

    return tx.partnerWalletTransaction.update({
      where: { id: transaction.id },
      data: {
        status: "COMPLETED",
        completedAt: new Date(),
        providerTransactionId: typeof metadata.providerTransactionId === "string" ? metadata.providerTransactionId : null,
        providerMetadataJson: metadataJson({
          previous: transaction.providerMetadataJson ? JSON.parse(transaction.providerMetadataJson) : {},
          callback: metadata
        })
      }
    });
  });
}

export async function failIncomingTransaction(reference: string, reason: string, metadata: Record<string, unknown> = {}) {
  return prisma.partnerWalletTransaction.updateMany({
    where: {
      OR: [{ providerReference: reference }, { internalReference: reference }],
      type: { in: ["DEPOSIT", "INVESTMENT", "DONATION"] },
      status: "PENDING"
    },
    data: {
      status: "FAILED",
      failureReason: reason,
      providerMetadataJson: metadataJson(metadata)
    }
  });
}

export async function completeWithdrawal(reference: string, metadata: Record<string, unknown> = {}) {
  return prisma.$transaction(async (tx) => {
    const transaction = await tx.partnerWalletTransaction.findFirst({
      where: {
        OR: [{ providerReference: reference }, { internalReference: reference }],
        type: "WITHDRAWAL"
      }
    });

    if (!transaction) return null;
    if (transaction.status === "COMPLETED") return transaction;
    if (!["PENDING", "APPROVED"].includes(transaction.status) || !transaction.walletId) return transaction;

    // Spend the held funds. Same operation as before, named — so the one place
    // that defines "settle a hold" is the one place that changes if it ever
    // needs to do more.
    await settleHeldDebit(tx, {
      walletId: transaction.walletId,
      amountCents: transaction.amountCents
    });

    return tx.partnerWalletTransaction.update({
      where: { id: transaction.id },
      data: {
        status: "COMPLETED",
        completedAt: new Date(),
        providerTransactionId: typeof metadata.providerTransactionId === "string" ? metadata.providerTransactionId : null,
        providerMetadataJson: metadataJson(metadata)
      }
    });
  });
}

export async function failWithdrawal(reference: string, reason: string, metadata: Record<string, unknown> = {}) {
  return prisma.$transaction(async (tx) => {
    const transaction = await tx.partnerWalletTransaction.findFirst({
      where: {
        OR: [{ providerReference: reference }, { internalReference: reference }],
        type: "WITHDRAWAL"
      }
    });

    if (!transaction) return null;
    if (!["PENDING", "APPROVED"].includes(transaction.status) || !transaction.walletId) return transaction;

    /*
     * `releaseHold`, not a bare decrement.
     *
     * It clamps at zero. A raw `{ decrement }` does not, and `walletAvailable`
     * is `max(0, balance - held)` — so a held that went negative would make
     * AVAILABLE exceed the real balance and let a partner commit money they do
     * not have. The clamped version existed and was tested; these two sites
     * hand-rolled the unclamped one.
     */
    await releaseHold(tx, {
      walletId: transaction.walletId,
      amountCents: transaction.amountCents
    });

    return tx.partnerWalletTransaction.update({
      where: { id: transaction.id },
      data: {
        status: "FAILED",
        failureReason: reason,
        providerMetadataJson: metadataJson(metadata)
      }
    });
  });
}

export async function rejectWithdrawal(transactionId: string, actorUserId: string | undefined, reason: string) {
  return prisma.$transaction(async (tx) => {
    const transaction = await tx.partnerWalletTransaction.findUnique({ where: { id: transactionId } });

    if (!transaction || transaction.type !== "WITHDRAWAL") {
      throw new ApiHttpError(404, "WITHDRAWAL_NOT_FOUND", "Withdrawal request does not exist.");
    }
    if (transaction.status !== "PENDING" || !transaction.walletId) {
      throw new ApiHttpError(400, "WITHDRAWAL_NOT_PENDING", "Only pending withdrawals can be rejected.");
    }

    /*
     * `releaseHold`, not a bare decrement.
     *
     * It clamps at zero. A raw `{ decrement }` does not, and `walletAvailable`
     * is `max(0, balance - held)` — so a held that went negative would make
     * AVAILABLE exceed the real balance and let a partner commit money they do
     * not have. The clamped version existed and was tested; these two sites
     * hand-rolled the unclamped one.
     */
    await releaseHold(tx, {
      walletId: transaction.walletId,
      amountCents: transaction.amountCents
    });

    return tx.partnerWalletTransaction.update({
      where: { id: transaction.id },
      data: {
        status: "REJECTED",
        approvedByUserId: actorUserId ?? null,
        approvedAt: new Date(),
        failureReason: reason
      }
    });
  });
}

/**
 * Paystack signs the exact bytes it sent. Pass the RAW body (captured by the
 * JSON parser's verify hook) whenever it is available: re-serialising the
 * parsed object can reorder keys or change number formatting and then a
 * genuine webhook fails the check. The object form is kept for callers that
 * have nothing else.
 */
export function verifyPaystackSignature(
  payload: unknown,
  signature: string | string[] | undefined,
  secret: string
) {
  if (!signature || Array.isArray(signature) || !secret) return false;

  const bytes =
    Buffer.isBuffer(payload) || typeof payload === "string" ? payload : JSON.stringify(payload);
  const expected = createHmac("sha512", secret).update(bytes).digest("hex");
  const left = Buffer.from(signature);
  const right = Buffer.from(expected);
  return left.length === right.length && timingSafeEqual(left, right);
}

export async function getPaystackSecret() {
  const credentials = await credentialsFor("PAYSTACK");
  return credentials.PAYSTACK_SECRET_KEY || process.env.PAYSTACK_SECRET_KEY || "";
}

export function walletAvailable(balanceCents: number, heldCents: number) {
  return Math.max(0, balanceCents - heldCents);
}

// ---------------------------------------------------------------------------
// Verification and settlement calls (payment & settlement layer, Sep 2026).
//
// Nothing a provider's CALLBACK says is taken on its own word: the callback
// URLs are public, so anyone who learns a reference could post "paid". Every
// success is confirmed by asking the provider directly, with the credentials
// that collected the money.
// ---------------------------------------------------------------------------

/** The Paystack secret that collected (or will collect) a payment. */
export async function paystackSecretFor(groupId?: string | null) {
  const credentials = await credentialsFor("PAYSTACK", groupId ?? null);
  return credentials.PAYSTACK_SECRET_KEY || "";
}

export interface ProviderCheck {
  /** false in mock mode: nothing was asked, the caller decides what to trust. */
  checked: boolean;
  status: "SUCCESS" | "FAILED" | "PENDING";
  amountCents?: number | null;
  currency?: string | null;
  providerTransactionId?: string | null;
  reason?: string | null;
  raw?: unknown;
}

/** GET /transaction/verify/:reference with the collecting account's key. */
export async function verifyPaystackTransaction(
  reference: string,
  groupId?: string | null
): Promise<ProviderCheck> {
  if (!env.ENABLE_PAYMENT_NETWORK_CALLS) return { checked: false, status: "SUCCESS" };

  const credentials = await credentialsFor("PAYSTACK", groupId ?? null);
  assertNetworkCredentials("PAYSTACK", credentials);
  const response = await fetch(
    `https://api.paystack.co/transaction/verify/${encodeURIComponent(reference)}`,
    { headers: { Authorization: `Bearer ${credentials.PAYSTACK_SECRET_KEY}` } }
  );
  const payload = (await response.json().catch(() => null)) as
    | {
        status?: boolean;
        data?: { status?: string; amount?: number; currency?: string; id?: number | string; gateway_response?: string };
      }
    | null;
  if (!response.ok || !payload?.status || !payload.data) {
    // The provider could not be asked. That is not a failure of the payment:
    // stay PENDING and let the next callback or poll try again.
    return { checked: true, status: "PENDING", reason: "Paystack could not be reached to confirm.", raw: payload };
  }
  const data = payload.data;
  const state = String(data.status);
  const status = state === "success" ? "SUCCESS" : ["failed", "abandoned", "reversed"].includes(state) ? "FAILED" : "PENDING";
  return {
    checked: true,
    status,
    amountCents: typeof data.amount === "number" ? data.amount : null,
    currency: data.currency ?? null,
    providerTransactionId: data.id != null ? String(data.id) : null,
    reason: data.gateway_response ?? null,
    raw: payload
  };
}

/**
 * STK Push Query: did the member approve the prompt? Daraja returns no amount
 * here, but the amount of an STK push is set by us, not by the payer, so the
 * status is what needs confirming.
 */
export async function queryMpesaStk(
  checkoutRequestId: string,
  groupId?: string | null
): Promise<ProviderCheck> {
  if (!env.ENABLE_PAYMENT_NETWORK_CALLS) return { checked: false, status: "SUCCESS" };

  const credentials = await credentialsFor("MPESA_DARAJA", groupId ?? null);
  assertNetworkCredentials("MPESA_DARAJA", credentials);
  const shortcode = credentials.MPESA_SHORTCODE ?? "";
  const requestTimestamp = timestamp();
  const token = await mpesaToken(credentials);
  const response = await fetch(`${mpesaBaseUrl(credentials)}/mpesa/stkpushquery/v1/query`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
    body: JSON.stringify({
      BusinessShortCode: shortcode,
      Password: Buffer.from(`${shortcode}${credentials.MPESA_PASSKEY ?? ""}${requestTimestamp}`).toString("base64"),
      Timestamp: requestTimestamp,
      CheckoutRequestID: checkoutRequestId
    })
  });
  const payload = (await response.json().catch(() => null)) as
    | { ResultCode?: string | number; ResultDesc?: string; errorCode?: string; errorMessage?: string }
    | null;
  // "The transaction is being processed" comes back as an error code.
  if (!payload || payload.errorCode === "500.001.1001" || payload.ResultCode === undefined) {
    return { checked: true, status: "PENDING", reason: payload?.errorMessage ?? payload?.ResultDesc ?? null, raw: payload };
  }
  return {
    checked: true,
    status: String(payload.ResultCode) === "0" ? "SUCCESS" : "FAILED",
    reason: payload.ResultDesc ?? null,
    raw: payload
  };
}

/** Daraja B2B: pay a paybill (BusinessPayBill) or a till (BusinessBuyGoods). */
async function initiateMpesaB2B(input: PayoutInput, credentials: Record<string, string>): Promise<GatewayResult> {
  const shortcode = credentials.MPESA_SHORTCODE;
  if (!shortcode || !input.receiverShortcode) {
    throw new ApiHttpError(
      400,
      "MPESA_PAYOUT_DETAILS_REQUIRED",
      "M-Pesa B2B payouts need our shortcode and the receiving paybill or till."
    );
  }
  const token = await mpesaToken(credentials);
  const paybill = input.mpesaPayoutKind === "PAYBILL";
  const response = await fetch(`${mpesaBaseUrl(credentials)}/mpesa/b2b/v1/paymentrequest`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
    body: JSON.stringify({
      Initiator: credentials.MPESA_INITIATOR_NAME,
      SecurityCredential: credentials.MPESA_SECURITY_CREDENTIAL,
      CommandID: paybill ? "BusinessPayBill" : "BusinessBuyGoods",
      SenderIdentifierType: "4",
      RecieverIdentifierType: paybill ? "4" : "2",
      Amount: asWholeShillings(input.amountCents),
      PartyA: shortcode,
      PartyB: input.receiverShortcode,
      AccountReference: (input.accountReference || input.internalReference).slice(0, 13),
      Remarks: input.description.slice(0, 100),
      QueueTimeOutURL: callbackUrl("/api/v1/payments/mpesa/b2b-timeout"),
      ResultURL: callbackUrl("/api/v1/payments/mpesa/b2b-result"),
      Occasion: input.internalReference
    })
  });
  const payload = (await response.json().catch(() => null)) as
    | { ConversationID?: string; OriginatorConversationID?: string; ResponseCode?: string }
    | null;
  if (!response.ok || !payload?.ConversationID) {
    throw new ApiHttpError(502, "MPESA_B2B_FAILED", "M-Pesa B2B payout request failed.", payload);
  }
  return { providerReference: payload.ConversationID, metadata: payload as Record<string, unknown> };
}

/**
 * Daraja Transaction Status. Asynchronous: the answer arrives at the result
 * URL. Used to resolve a payout whose outcome is UNKNOWN, never to retry it.
 */
export async function requestMpesaTransactionStatus(input: {
  transactionId: string;
  originatorConversationId?: string | null;
  occasion: string;
}) {
  if (!env.ENABLE_PAYMENT_NETWORK_CALLS) {
    return { requested: false, payload: { reason: "Payment network calls are off (mock mode)." } };
  }
  const credentials = await credentialsFor("MPESA_DARAJA", null);
  assertNetworkCredentials("MPESA_DARAJA", credentials);
  const token = await mpesaToken(credentials);
  const response = await fetch(`${mpesaBaseUrl(credentials)}/mpesa/transactionstatus/v1/query`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
    body: JSON.stringify({
      Initiator: credentials.MPESA_INITIATOR_NAME,
      SecurityCredential: credentials.MPESA_SECURITY_CREDENTIAL,
      CommandID: "TransactionStatusQuery",
      TransactionID: input.transactionId,
      OriginatorConversationID: input.originatorConversationId ?? undefined,
      PartyA: credentials.MPESA_SHORTCODE,
      IdentifierType: "4",
      ResultURL: callbackUrl("/api/v1/payments/mpesa/status-result"),
      QueueTimeOutURL: callbackUrl("/api/v1/payments/mpesa/status-timeout"),
      Remarks: "Settlement check",
      Occasion: input.occasion
    })
  });
  const payload = (await response.json().catch(() => null)) as Record<string, unknown> | null;
  return { requested: response.ok, payload };
}

/** Paystack: the current state of a transfer. */
export async function fetchPaystackTransfer(reference: string): Promise<ProviderCheck> {
  if (!env.ENABLE_PAYMENT_NETWORK_CALLS) return { checked: false, status: "PENDING" };
  const credentials = await credentialsFor("PAYSTACK", null);
  assertNetworkCredentials("PAYSTACK", credentials);
  const response = await fetch(`https://api.paystack.co/transfer/verify/${encodeURIComponent(reference)}`, {
    headers: { Authorization: `Bearer ${credentials.PAYSTACK_SECRET_KEY}` }
  });
  const payload = (await response.json().catch(() => null)) as
    | { status?: boolean; data?: { status?: string; amount?: number; transfer_code?: string; reason?: string } }
    | null;
  if (!response.ok || !payload?.status || !payload.data) {
    return { checked: true, status: "PENDING", reason: "Paystack could not be reached.", raw: payload };
  }
  const state = String(payload.data.status);
  return {
    checked: true,
    status: state === "success" ? "SUCCESS" : ["failed", "reversed", "rejected"].includes(state) ? "FAILED" : "PENDING",
    amountCents: payload.data.amount ?? null,
    providerTransactionId: payload.data.transfer_code ?? null,
    reason: payload.data.reason ?? state,
    raw: payload
  };
}

/**
 * A Paystack transfer recipient for a group's bank or mobile-money account,
 * created once when the destination is approved.
 */
export async function createPaystackTransferRecipient(input: {
  type: "PAYSTACK_BANK" | "PAYSTACK_MOBILE_MONEY";
  accountName: string;
  accountNumber: string;
  bankCode: string;
}) {
  if (!env.ENABLE_PAYMENT_NETWORK_CALLS) {
    return { recipientCode: `mock-RCP_${input.accountNumber.slice(-4)}`, accountName: input.accountName };
  }
  const credentials = await credentialsFor("PAYSTACK", null);
  assertNetworkCredentials("PAYSTACK", credentials);
  const response = await fetch("https://api.paystack.co/transferrecipient", {
    method: "POST",
    headers: { Authorization: `Bearer ${credentials.PAYSTACK_SECRET_KEY}`, "Content-Type": "application/json" },
    body: JSON.stringify({
      type: input.type === "PAYSTACK_MOBILE_MONEY" ? "mobile_money" : "kepss",
      name: input.accountName,
      account_number: input.accountNumber,
      bank_code: input.bankCode,
      currency: "KES"
    })
  });
  const payload = (await response.json().catch(() => null)) as
    | { status?: boolean; message?: string; data?: { recipient_code?: string; details?: { account_name?: string } } }
    | null;
  if (!response.ok || !payload?.status || !payload.data?.recipient_code) {
    throw new ApiHttpError(
      502,
      "PAYSTACK_RECIPIENT_FAILED",
      payload?.message ?? "Paystack could not register this account.",
      payload
    );
  }
  return {
    recipientCode: payload.data.recipient_code,
    accountName: payload.data.details?.account_name ?? input.accountName
  };
}
