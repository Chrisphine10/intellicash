import { Router } from "express";
import { z } from "zod";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import {
  decryptCredentials,
  encryptCredentials,
  sanitizeCredentials
} from "../services/integration-credentials";

export const groupPaymentProvidersRouter = Router();

/**
 * Per-group payment providers.
 *
 * A group with its own M-Pesa till or Paystack account configures it here, so
 * members' contributions settle into the GROUP's account rather than the
 * platform's. Groups that configure nothing keep using the platform defaults —
 * adding this changed nothing for existing groups.
 *
 * MPESA_CLASSIC is deliberately absent: it has no credentials because the
 * member reads the transaction code off their phone and types it in.
 */
const CONFIGURABLE_PROVIDERS = ["MPESA_DARAJA", "PAYSTACK"] as const;
type ConfigurableProvider = (typeof CONFIGURABLE_PROVIDERS)[number];

/**
 * Only these keys are accepted per group. Callback URLs stay platform-owned:
 * they must point back at this deployment, and letting a group set them would
 * let it redirect gateway confirmations somewhere we do not control.
 */
const PROVIDER_KEYS: Record<ConfigurableProvider, string[]> = {
  MPESA_DARAJA: [
    "MPESA_CONSUMER_KEY",
    "MPESA_CONSUMER_SECRET",
    "MPESA_SHORTCODE",
    "MPESA_PASSKEY",
    "MPESA_INITIATOR_NAME",
    "MPESA_SECURITY_CREDENTIAL",
    // SANDBOX (default) or LIVE. A group testing with Daraja sandbox keys and
    // a group running a real till hit different Safaricom hosts, so this has
    // to travel with the credentials rather than being a platform-wide switch.
    "MPESA_ENVIRONMENT"
  ],
  PAYSTACK: ["PAYSTACK_SECRET_KEY", "PAYSTACK_PUBLIC_KEY"]
};

/**
 * Values accepted for a provider key that is a mode rather than a secret.
 *
 * Rejected rather than silently normalised: a group typing "live " or "Live"
 * gets what it meant, but "lve" must fail visibly instead of quietly leaving
 * the group on sandbox and looking like a broken gateway later.
 */
const ENUM_KEYS: Record<string, string[]> = {
  MPESA_ENVIRONMENT: ["SANDBOX", "LIVE", "PRODUCTION"]
};

/**
 * Keys with a safe default, so their absence is not a misconfiguration.
 *
 * Listing MPESA_ENVIRONMENT under `missingKeys` would tell every already-working
 * group that its gateway is incomplete, which is both false and alarming.
 */
const OPTIONAL_KEYS = new Set(["MPESA_ENVIRONMENT"]);

/**
 * What will ACTUALLY happen when this group takes a payment.
 *
 * Derived from the credentials themselves rather than from a mode flag beside
 * them. A group can set mode LIVE and paste a `sk_test_` key; the flag would
 * say live, the money would go nowhere real, and nobody would know until a
 * member complained. Paystack's key prefix is the authority for Paystack, and
 * the environment key is the authority for Daraja.
 */
function effectiveTarget(provider: ConfigurableProvider, credentials: Record<string, string>) {
  if (provider === "MPESA_DARAJA") {
    const raw = (credentials.MPESA_ENVIRONMENT ?? "").trim().toUpperCase();
    const live = raw === "LIVE" || raw === "PRODUCTION";
    return {
      environment: live ? "LIVE" : "SANDBOX",
      host: live ? "https://api.safaricom.co.ke" : "https://sandbox.safaricom.co.ke",
      note: live
        ? "Real money. Payments reach the group's own M-Pesa till."
        : "Test only. Safaricom's sandbox — no real money moves."
    };
  }

  const secret = credentials.PAYSTACK_SECRET_KEY ?? "";
  if (!secret) {
    return { environment: "UNSET", host: "https://api.paystack.co", note: "No Paystack key set." };
  }
  const live = secret.startsWith("sk_live_");
  return {
    environment: live ? "LIVE" : "SANDBOX",
    host: "https://api.paystack.co",
    note: live
      ? "Real money. Read from the sk_live_ key itself, not a setting."
      : "Test key (sk_test_). No real money moves."
  };
}

/** Keys whose value must never leave the server, not even to an admin. */
const SECRET_KEYS = new Set([
  "MPESA_CONSUMER_SECRET",
  "MPESA_PASSKEY",
  "MPESA_SECURITY_CREDENTIAL",
  "PAYSTACK_SECRET_KEY"
]);

function isConfigurableProvider(value: string): value is ConfigurableProvider {
  return (CONFIGURABLE_PROVIDERS as readonly string[]).includes(value);
}

/**
 * Who may change where a group's money lands.
 *
 * Both an IWL_ADMIN and the group's own account may set this — the platform
 * supports groups that run their own till and groups the admin configures on
 * their behalf. Everyone else is refused, including village agents, who can
 * read a group but must never redirect its collections.
 *
 * Deliberately expressed as a role/scope check rather than a new permission
 * string: `ensureRolePermissionTemplates` upserts with `update: {}`, so a new
 * permission would never reach existing RolePermissionTemplate rows and the
 * check would silently pass for nobody.
 */
function assertMayConfigure(user: AuthenticatedUser | undefined, groupId: string) {
  if (!user) throw new ApiHttpError(401, "UNAUTHENTICATED", "Authentication is required.");
  if (user.permissions.includes("groups:write")) return;
  if (user.role === "GROUP_ACCOUNT" && user.groupId === groupId) return;

  throw new ApiHttpError(
    403,
    "FORBIDDEN",
    "Only a platform admin or the group's own account may change its payment provider."
  );
}

/** Confirms the group exists AND is visible to this caller. */
async function loadGroupInScope(user: AuthenticatedUser | undefined, groupId: string) {
  const group = await prisma.group.findFirst({
    where: { AND: [{ id: groupId }, scopeGroupWhere(user)] },
    select: { id: true, name: true, code: true }
  });
  if (!group) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
  return group;
}

/**
 * What a caller is allowed to see. Secrets are reported as configured or not —
 * never returned — so a leaked response cannot be replayed against the gateway.
 */
function presentConfig(
  provider: ConfigurableProvider,
  row: { enabled: boolean; mode: string; credentialsJson: string | null; credentialsUpdatedAt: Date | null; updatedByUserId: string | null } | null
) {
  const credentials = decryptCredentials(row?.credentialsJson);
  const keys = PROVIDER_KEYS[provider];

  return {
    provider,
    configured: Boolean(row) && keys.some((key) => credentials[key]),
    enabled: row?.enabled ?? false,
    mode: row?.mode ?? "SANDBOX",
    credentialsUpdatedAt: row?.credentialsUpdatedAt?.toISOString() ?? null,
    updatedByUserId: row?.updatedByUserId ?? null,
    // Non-secret values are echoed so an operator can confirm the till number;
    // secrets report presence only.
    values: Object.fromEntries(
      keys.map((key) => [
        key,
        credentials[key] ? (SECRET_KEYS.has(key) ? "__set__" : credentials[key]) : null
      ])
    ),
    missingKeys: keys.filter((key) => !credentials[key] && !OPTIONAL_KEYS.has(key)),
    effective: effectiveTarget(provider, credentials)
  };
}

groupPaymentProvidersRouter.get(
  "/groups/:groupId/payment-providers",
  requireAuth("groups:read"),
  async (req, res, next) => {
    try {
      const groupId = req.params.groupId as string;
      const group = await loadGroupInScope(req.user, groupId);

      const rows = await prisma.groupIntegrationConfig.findMany({ where: { groupId: group.id } });
      const byProvider = new Map(rows.map((row) => [row.provider, row]));

      ok(res, {
        group,
        providers: CONFIGURABLE_PROVIDERS.map((provider) =>
          presentConfig(provider, byProvider.get(provider) ?? null)
        ),
        // Stated explicitly so the UI can tell a group "you are currently using
        // the platform's account" rather than leaving it ambiguous.
        fallback: "Providers left unconfigured use the platform's own credentials.",
        canConfigure:
          Boolean(req.user?.permissions.includes("groups:write")) ||
          (req.user?.role === "GROUP_ACCOUNT" && req.user?.groupId === group.id)
      });
    } catch (error) {
      next(error);
    }
  }
);

const upsertSchema = z.object({
  enabled: z.boolean().optional(),
  mode: z.enum(["SANDBOX", "PRODUCTION"]).optional(),
  credentials: z.record(z.string()).default({})
});

groupPaymentProvidersRouter.put(
  "/groups/:groupId/payment-providers/:provider",
  requireAuth("groups:read"),
  async (req, res, next) => {
    try {
      const groupId = req.params.groupId as string;
      const provider = req.params.provider as string;

      if (!isConfigurableProvider(provider)) {
        throw new ApiHttpError(
          400,
          "PROVIDER_NOT_CONFIGURABLE",
          `Only ${CONFIGURABLE_PROVIDERS.join(" and ")} take credentials. M-Pesa Classic is entered by hand.`
        );
      }

      const group = await loadGroupInScope(req.user, groupId);
      assertMayConfigure(req.user, group.id);

      const body = upsertSchema.parse(req.body ?? {});
      const incoming = sanitizeCredentials(body.credentials, PROVIDER_KEYS[provider]);

      // Validate mode-style keys before anything is stored. An unrecognised
      // value would fall back to SANDBOX at transaction time, and the group
      // would look like it had gone live while nothing reached the real till.
      for (const [key, allowed] of Object.entries(ENUM_KEYS)) {
        const value = incoming[key];
        if (value === undefined) continue;
        const upper = value.toUpperCase();
        if (!allowed.includes(upper)) {
          throw new ApiHttpError(
            400,
            "INVALID_CREDENTIAL_VALUE",
            `${key} must be one of ${allowed.join(", ")}.`,
            { key, allowed, received: value }
          );
        }
        incoming[key] = upper;
      }

      const existingRow = await prisma.groupIntegrationConfig.findUnique({
        where: { groupId_provider: { groupId: group.id, provider } }
      });

      // Merge, so an operator can correct a shortcode without re-typing every
      // secret. Sending an empty string for a key clears it (sanitize drops
      // blanks, so callers clear via the DELETE route instead).
      const merged = { ...decryptCredentials(existingRow?.credentialsJson), ...incoming };

      const saved = await prisma.groupIntegrationConfig.upsert({
        where: { groupId_provider: { groupId: group.id, provider } },
        create: {
          groupId: group.id,
          provider,
          credentialsJson: encryptCredentials(merged),
          enabled: body.enabled ?? true,
          mode: body.mode ?? "SANDBOX",
          credentialsUpdatedAt: new Date(),
          updatedByUserId: req.user?.id ?? null
        },
        update: {
          credentialsJson: encryptCredentials(merged),
          ...(body.enabled === undefined ? {} : { enabled: body.enabled }),
          ...(body.mode === undefined ? {} : { mode: body.mode }),
          ...(Object.keys(incoming).length > 0 ? { credentialsUpdatedAt: new Date() } : {}),
          updatedByUserId: req.user?.id ?? null
        }
      });

      ok(res, presentConfig(provider, saved));
    } catch (error) {
      next(error);
    }
  }
);

groupPaymentProvidersRouter.delete(
  "/groups/:groupId/payment-providers/:provider",
  requireAuth("groups:read"),
  async (req, res, next) => {
    try {
      const groupId = req.params.groupId as string;
      const provider = req.params.provider as string;

      if (!isConfigurableProvider(provider)) {
        throw new ApiHttpError(400, "PROVIDER_NOT_CONFIGURABLE", "Unknown payment provider.");
      }

      const group = await loadGroupInScope(req.user, groupId);
      assertMayConfigure(req.user, group.id);

      await prisma.groupIntegrationConfig
        .delete({ where: { groupId_provider: { groupId: group.id, provider } } })
        .catch(() => undefined); // Already absent is the desired end state.

      // Reverting to the platform account is a money-routing change; say so
      // rather than returning an empty 204 the UI has to guess at.
      ok(res, {
        provider,
        configured: false,
        reverted: true,
        message: "This group now uses the platform's payment credentials."
      });
    } catch (error) {
      next(error);
    }
  }
);
