/**
 * How a group collects gateway payments, and what a payment will cost.
 *
 * Collection mode:
 * - SYSTEM: IWL's own gateway accounts collect the whole charge; the group's
 *   share is then settled to the group's approved destination.
 * - OWN_ACCOUNT: the group's own Daraja / Paystack credentials collect it, so
 *   the money lands with the group directly and nothing is settled. The IWL
 *   fee inside that money is owed by the group (RECEIVABLE).
 *
 * A group with no settings row keeps behaving exactly as before this layer:
 * OWN_ACCOUNT when it has enabled its own credentials for that provider,
 * SYSTEM otherwise.
 */

import type { Prisma, PrismaClient } from "@prisma/client";
import { env } from "../config/env";
import { signValue, verifySignedValue } from "../lib/crypto";
import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";
import { quoteFees, type FeeQuote, type FeeRuleInput, FeeConfigurationError } from "./fee-engine";

type Db = Prisma.TransactionClient | PrismaClient;

export const GATEWAY_PROVIDERS = ["MPESA_DARAJA", "PAYSTACK"] as const;
export type GatewayProvider = (typeof GATEWAY_PROVIDERS)[number];
export type CollectionMode = "SYSTEM" | "OWN_ACCOUNT";

/** Purposes the server can post to the ledger itself. */
export const POSTABLE_PURPOSES = {
  SHARE_PURCHASE: "SHARE_PURCHASE",
  /** The welfare (social) fund contribution. */
  SOCIAL_FUND: "SOCIAL_CONTRIBUTION",
  FINE: "FINE_COLLECTION",
  LOAN_REPAYMENT: "LOAN_REPAYMENT"
} as const;
export type PostablePurpose = keyof typeof POSTABLE_PURPOSES;

export function ledgerTypeForPurpose(purpose: string) {
  return (POSTABLE_PURPOSES as Record<string, string>)[purpose] ?? null;
}

export interface EffectivePaymentSettings {
  groupId: string;
  /** null = inferred per provider (no settings row). */
  collectionMode: CollectionMode | null;
  enabledProviders: GatewayProvider[];
  memberSelfPayEnabled: boolean;
  /** Providers the group has its own enabled credentials for. */
  ownCredentialProviders: GatewayProvider[];
  explicit: boolean;
}

function parseProviders(json: string | null | undefined): GatewayProvider[] {
  try {
    const parsed = JSON.parse(json ?? "[]") as unknown;
    if (!Array.isArray(parsed)) return [];
    return parsed.filter((value): value is GatewayProvider =>
      (GATEWAY_PROVIDERS as readonly string[]).includes(String(value))
    );
  } catch {
    return [];
  }
}

export async function paymentSettingsFor(groupId: string, db: Db = prisma): Promise<EffectivePaymentSettings> {
  const [row, configs] = await Promise.all([
    db.groupPaymentSettings.findUnique({ where: { groupId } }),
    db.groupIntegrationConfig.findMany({
      where: { groupId, enabled: true, provider: { in: [...GATEWAY_PROVIDERS] } },
      select: { provider: true }
    })
  ]);
  const ownCredentialProviders = configs.map((config) => config.provider as GatewayProvider);
  const enabled = parseProviders(row?.enabledProvidersJson);
  return {
    groupId,
    collectionMode: (row?.collectionMode as CollectionMode | undefined) ?? null,
    // No settings saved yet: every provider, as before settings existed. Once
    // the group (or an admin) saves, the list is exactly what they switched on
    // — empty means online payments are off. M-Pesa Classic is not a gateway
    // and is never switched here: the treasurer records its code by hand.
    enabledProviders: row ? enabled : [...GATEWAY_PROVIDERS],
    memberSelfPayEnabled: row?.memberSelfPayEnabled ?? false,
    ownCredentialProviders,
    explicit: Boolean(row)
  };
}

/** Which collection mode applies to one payment through one provider. */
export function collectionModeFor(settings: EffectivePaymentSettings, provider: GatewayProvider): CollectionMode {
  if (settings.collectionMode) return settings.collectionMode;
  return settings.ownCredentialProviders.includes(provider) ? "OWN_ACCOUNT" : "SYSTEM";
}

export function assertProviderUsable(settings: EffectivePaymentSettings, provider: GatewayProvider) {
  if (!settings.enabledProviders.includes(provider)) {
    throw new ApiHttpError(
      400,
      "PROVIDER_NOT_ENABLED",
      `${provider === "PAYSTACK" ? "Paystack" : "M-Pesa"} payments are switched off for this group.`
    );
  }
  const mode = collectionModeFor(settings, provider);
  if (mode === "OWN_ACCOUNT" && !settings.ownCredentialProviders.includes(provider)) {
    throw new ApiHttpError(
      400,
      "GROUP_PROVIDER_NOT_CONFIGURED",
      "This group collects into its own account but has not set up this provider. Add its details under Payment settings."
    );
  }
  return mode;
}

/** The credentials a payment in this mode is collected (and verified) with. */
export function credentialGroupId(mode: string, groupId: string) {
  return mode === "OWN_ACCOUNT" ? groupId : null;
}

// ---------------------------------------------------------------------------
// Fees
// ---------------------------------------------------------------------------

export async function activeFeeRules(db: Db = prisma): Promise<FeeRuleInput[]> {
  return db.feeRule.findMany({
    where: { active: true },
    select: {
      id: true,
      kind: true,
      provider: true,
      minCents: true,
      maxCents: true,
      fixedCents: true,
      percentBps: true,
      version: true
    }
  });
}

export async function feesAreActive(db: Db = prisma) {
  return (await db.feeRule.count({ where: { active: true } })) > 0;
}

export async function computeQuote(provider: GatewayProvider, groupAmountCents: number, db: Db = prisma): Promise<FeeQuote> {
  try {
    return quoteFees({ groupAmountCents, provider, rules: await activeFeeRules(db) });
  } catch (error) {
    if (error instanceof FeeConfigurationError) {
      throw new ApiHttpError(500, "FEE_CONFIGURATION_INVALID", error.message);
    }
    throw error;
  }
}

// ---------------------------------------------------------------------------
// Quote tokens
//
// A quote is what the member saw and agreed to. It is signed, so a phone
// cannot alter the figures, and it expires. At charge time the server
// recomputes the fees from the live rules and refuses if they moved — the
// member is never charged a total they were not shown.
// ---------------------------------------------------------------------------

export interface QuoteClaims {
  groupId: string;
  memberId: string | null;
  provider: GatewayProvider;
  purpose: string;
  groupAmountCents: number;
  platformFeeCents: number;
  providerFeeCents: number;
  totalCents: number;
  expiresAt: number;
}

export function signQuote(claims: Omit<QuoteClaims, "expiresAt">, now = Date.now()): { quoteId: string; expiresAt: string } {
  const full: QuoteClaims = { ...claims, expiresAt: now + env.PAYMENT_QUOTE_TTL_SECONDS * 1000 };
  const body = Buffer.from(JSON.stringify(full)).toString("base64url");
  return { quoteId: `${body}.${signValue(`quote:${body}`)}`, expiresAt: new Date(full.expiresAt).toISOString() };
}

export function readQuote(quoteId: string, now = Date.now()): QuoteClaims {
  const [body, signature] = quoteId.split(".");
  if (!body || !signature || !verifySignedValue(`quote:${body}`, signature)) {
    throw new ApiHttpError(400, "QUOTE_INVALID", "This payment quote is not valid. Get a new quote.");
  }
  const claims = JSON.parse(Buffer.from(body, "base64url").toString("utf8")) as QuoteClaims;
  if (claims.expiresAt < now) {
    throw new ApiHttpError(409, "QUOTE_EXPIRED", "This payment quote has expired. Get a new quote.");
  }
  return claims;
}
