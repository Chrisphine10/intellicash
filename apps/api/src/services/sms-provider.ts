import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";
import { decryptCredentials } from "./integration-credentials";
import {
  bongaSmsRequiredCredentialKeys,
  combineBongaSmsCredentials,
  type SmsProvider
} from "./sms-service";

/**
 * Which SMS provider to send through, and with what credentials.
 *
 * Lifted out of `admin-sms-service` when automatic member notifications became
 * a second caller. One implementation, so a broadcast an admin types and a
 * meeting summary the system sends can never disagree about which account is
 * live — which would show up as half the group's messages arriving from a
 * different sender ID.
 */

/** Preference order. Bonga is the live account; Africa's Talking is a fallback. */
export const smsProviders = ["BONGA_SMS", "AFRICAS_TALKING"] as const satisfies SmsProvider[];

const africasTalkingCredentialKeys = [
  "AFRICASTALKING_USERNAME",
  "AFRICASTALKING_API_KEY",
  "AFRICASTALKING_SENDER_ID"
];

export function isSmsProvider(provider: string): provider is SmsProvider {
  return provider === "BONGA_SMS" || provider === "AFRICAS_TALKING";
}

export function requiredCredentialKeys(provider: SmsProvider) {
  return provider === "BONGA_SMS" ? [...bongaSmsRequiredCredentialKeys] : africasTalkingCredentialKeys;
}

export async function credentialsFor(provider: SmsProvider) {
  const config = await prisma.integrationConfig.findUnique({
    where: { provider },
    select: { credentialsJson: true }
  });
  const storedCredentials = decryptCredentials(config?.credentialsJson);

  if (provider === "BONGA_SMS") return combineBongaSmsCredentials(storedCredentials);

  return Object.fromEntries(
    africasTalkingCredentialKeys
      .map((key) => [key, storedCredentials[key] || process.env[key]])
      .filter((entry): entry is [string, string] => typeof entry[1] === "string" && entry[1].length > 0)
  );
}

export interface ResolvedSmsIntegration {
  provider: SmsProvider;
  credentials: Record<string, string>;
}

/**
 * The first configured provider, or null.
 *
 * Returns null rather than throwing so an automatic send can decline quietly.
 * A meeting seal has already committed by the time notifications are dispatched
 * and must not be reported as failed because nobody has set up SMS.
 */
export async function findSmsIntegration(
  provider?: SmsProvider
): Promise<ResolvedSmsIntegration | null> {
  const candidates = provider ? [provider] : smsProviders;

  for (const candidate of candidates) {
    const credentials = await credentialsFor(candidate);
    const configured = requiredCredentialKeys(candidate).every(
      (key) => credentials[key] || process.env[key]
    );
    if (configured) return { provider: candidate, credentials };
  }

  return null;
}

/** The same resolution, for a request an operator is waiting on. */
export async function resolveSmsProvider(provider?: SmsProvider): Promise<ResolvedSmsIntegration> {
  const integration = await findSmsIntegration(provider);
  if (!integration) {
    throw new ApiHttpError(
      400,
      "SMS_PROVIDER_NOT_CONFIGURED",
      "No configured SMS provider is ready for broadcasts."
    );
  }
  return integration;
}
