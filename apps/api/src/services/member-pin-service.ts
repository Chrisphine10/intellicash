import { randomInt } from "node:crypto";
import bcrypt from "bcryptjs";
import type { Prisma } from "@prisma/client";
import { decryptJson, encryptJson } from "../lib/crypto";
import { maskPhone } from "../lib/privacy";
import { prisma } from "../lib/prisma";
import { decryptCredentials } from "./integration-credentials";
import {
  bongaSmsRequiredCredentialKeys,
  combineBongaSmsCredentials,
  renderSmsTemplate,
  sendSms,
  type SmsProvider
} from "./sms-service";

/**
 * A meeting PIN is four digits, chosen by the member on their phone.
 *
 * A generated one is now only a fallback — for a member who has not set theirs
 * yet — so it matches the length they will be asked to type.
 */
const memberPinLength = 4;

/**
 * One-time codes stay at six. They are single-use and time-boxed, so the
 * reasoning that made four acceptable for a PIN (three keys, a quorum, a
 * guessable-value guard) does not apply to them.
 */
const memberOtpLength = 6;
const memberOtpTtlMinutes = 15;
/**
 * Where a PIN or OTP goes when no provider is configured at all.
 *
 * Bonga, because it is the live account: a delivery queued before the
 * credentials were saved is then sendable by `sendQueuedMemberPinDelivery` the
 * moment they arrive. Pointing the fallback at a provider nobody has set up
 * stranded those rows permanently.
 */
const memberPinDeliveryProvider = "BONGA_SMS";
const memberPinSmsProviders = ["BONGA_SMS", "AFRICAS_TALKING"] as const;

/**
 * What each provider genuinely needs before it can send.
 *
 * Only the keys without a code-level fallback belong here. `BONGA_SMS_ENDPOINT`
 * and the two message templates all default in `sms-service`, and listing them
 * made `every()` fail for an account that was completely usable: an admin who
 * saved the four Bonga credentials and left the template boxes empty had their
 * group's OTPs quietly routed to Africa's Talking, which is not configured at
 * all, so no OTP was ever delivered.
 */
const memberPinSmsProviderEnv: Record<(typeof memberPinSmsProviders)[number], string[]> = {
  BONGA_SMS: [...bongaSmsRequiredCredentialKeys],
  AFRICAS_TALKING: [
    "AFRICASTALKING_USERNAME",
    "AFRICASTALKING_API_KEY",
    "AFRICASTALKING_SENDER_ID"
  ]
};
const memberPinDeliveryChannel = "SMS";
const memberPinDeliveryStatus = "QUEUED";
const defaultPinPurpose = "DEFAULT_PIN";
const currentOtpPurpose = "CURRENT_OTP";
const defaultPinTemplate =
  "Your Intelli Cash default meeting PIN is {pin}. Keep it private; it is saved on the mobile app for offline meeting unlock.";
const currentOtpTemplate =
  "Your Intelli Cash meeting OTP is {otp}. It expires in {ttlMinutes} minutes and is for online meeting unlock.";

export const memberPinDeliverySelect = {
  id: true,
  memberId: true,
  provider: true,
  channel: true,
  purpose: true,
  phone: true,
  status: true,
  messagePreview: true,
  sentAt: true,
  createdAt: true
} satisfies Prisma.MemberPinDeliverySelect;

export type MemberPinDeliveryPublic = Prisma.MemberPinDeliveryGetPayload<{
  select: typeof memberPinDeliverySelect;
}>;

type MemberPinTarget = {
  id: string;
  fullName: string;
  phone: string;
  pinSetAt?: Date | null;
};

export function generateMemberPin() {
  return randomInt(0, 10 ** memberPinLength).toString().padStart(memberPinLength, "0");
}

export function generateMemberOtp() {
  return randomInt(0, 10 ** memberOtpLength).toString().padStart(memberOtpLength, "0");
}

export function otpExpiresAt(from: Date) {
  return new Date(from.getTime() + memberOtpTtlMinutes * 60_000);
}

export { maskPhone };

export function serializeMemberPinDelivery(delivery: MemberPinDeliveryPublic) {
  return {
    ...delivery,
    phone: maskPhone(delivery.phone)
  };
}

function isSmsProvider(provider: string): provider is SmsProvider {
  return provider === "BONGA_SMS" || provider === "AFRICAS_TALKING";
}

async function resolveSmsCredentials(provider: SmsProvider) {
  const config = await prisma.integrationConfig.findUnique({
    where: { provider },
    select: { credentialsJson: true }
  });
  const storedCredentials = decryptCredentials(config?.credentialsJson);

  if (provider === "BONGA_SMS") {
    return combineBongaSmsCredentials(storedCredentials);
  }

  return Object.fromEntries(
    memberPinSmsProviderEnv[provider]
      .map((key) => [key, storedCredentials[key] || process.env[key]])
      .filter((entry): entry is [string, string] => typeof entry[1] === "string" && entry[1].length > 0)
  );
}

type MemberPinDeliveryPayload = {
  provider?: string;
  phone?: string;
  body?: string;
};

export async function sendQueuedMemberPinDelivery(
  deliveryId: string,
  dependencies: { fetch?: typeof fetch; networkEnabled?: boolean } = {}
) {
  const delivery = await prisma.memberPinDelivery.findUnique({
    where: { id: deliveryId },
    select: {
      ...memberPinDeliverySelect,
      messageCiphertext: true
    }
  });

  if (!delivery || delivery.status !== memberPinDeliveryStatus) {
    if (!delivery) return null;
    const { messageCiphertext: _messageCiphertext, ...publicDelivery } = delivery;
    return publicDelivery;
  }

  const payload = decryptJson<MemberPinDeliveryPayload>(delivery.messageCiphertext);
  const provider = isSmsProvider(delivery.provider) ? delivery.provider : null;
  const message = typeof payload.body === "string" ? payload.body : "";
  const phone = typeof payload.phone === "string" && payload.phone ? payload.phone : delivery.phone;

  if (!provider || !message) {
    const failed = await prisma.memberPinDelivery.update({
      where: { id: delivery.id },
      data: { status: "FAILED" },
      select: memberPinDeliverySelect
    });
    return failed;
  }

  const result = await sendSms(
    {
      provider,
      phone,
      message,
      credentials: await resolveSmsCredentials(provider)
    },
    dependencies
  );
  const updated = await prisma.memberPinDelivery.update({
    where: { id: delivery.id },
    data: {
      status: result.status,
      sentAt: result.status === "SENT" ? new Date() : null
    },
    select: memberPinDeliverySelect
  });

  return updated;
}

async function resolveMemberPinDeliveryProvider(tx: Prisma.TransactionClient) {
  const configs = await tx.integrationConfig.findMany({
    where: { provider: { in: [...memberPinSmsProviders] } },
    select: { provider: true, credentialsJson: true }
  });
  const configsByProvider = new Map(configs.map((config) => [config.provider, config]));

  for (const provider of memberPinSmsProviders) {
    const credentials = decryptCredentials(configsByProvider.get(provider)?.credentialsJson);
    const configured = memberPinSmsProviderEnv[provider].every((key) => process.env[key] || credentials[key]);

    if (configured) return provider;
  }

  return memberPinDeliveryProvider;
}

async function resolveMemberPinDeliveryIntegration(tx: Prisma.TransactionClient) {
  const provider = await resolveMemberPinDeliveryProvider(tx);
  const config = await tx.integrationConfig.findUnique({
    where: { provider },
    select: { credentialsJson: true }
  });
  const credentials = decryptCredentials(config?.credentialsJson);

  if (provider === "BONGA_SMS") {
    return {
      provider,
      credentials: combineBongaSmsCredentials(credentials)
    };
  }

  return { provider, credentials };
}

function messageTemplateFor(
  integration: { provider: string; credentials: Record<string, string> },
  purpose: typeof defaultPinPurpose | typeof currentOtpPurpose
) {
  if (integration.provider === "BONGA_SMS") {
    const templateKey =
      purpose === defaultPinPurpose ? "BONGA_SMS_DEFAULT_PIN_TEMPLATE" : "BONGA_SMS_OTP_TEMPLATE";
    const template = integration.credentials[templateKey];
    if (template?.trim()) return template.trim();
  }

  return purpose === defaultPinPurpose ? defaultPinTemplate : currentOtpTemplate;
}

export async function generateAndQueueMemberPin<TSelect extends Prisma.MemberSelect>(
  tx: Prisma.TransactionClient,
  member: MemberPinTarget,
  input: {
    requestedByUserId?: string | null;
    select: TSelect;
  }
) {
  const pin = generateMemberPin();
  const now = new Date();
  const integration = await resolveMemberPinDeliveryIntegration(tx);
  const provider = integration.provider;
  const messageBody = renderSmsTemplate(messageTemplateFor(integration, defaultPinPurpose), { pin });
  const pinHash = await bcrypt.hash(pin, 12);

  const updatedMember = await tx.member.update({
    where: { id: member.id },
    data: {
      pinHash,
      pinSetAt: member.pinSetAt ?? now,
      pinUpdatedAt: now
    },
    select: input.select
  });

  const delivery = await tx.memberPinDelivery.create({
    data: {
      memberId: member.id,
      requestedByUserId: input.requestedByUserId ?? null,
      provider,
      channel: memberPinDeliveryChannel,
      purpose: defaultPinPurpose,
      phone: member.phone,
      status: memberPinDeliveryStatus,
      messagePreview: `Default meeting PIN SMS queued to ${maskPhone(member.phone)}.`,
      messageCiphertext: encryptJson({
        provider,
        channel: memberPinDeliveryChannel,
        purpose: defaultPinPurpose,
        phone: member.phone,
        pin,
        body: messageBody,
        template: messageTemplateFor(integration, defaultPinPurpose),
        generatedAt: now.toISOString()
      })
    },
    select: memberPinDeliverySelect
  });

  return { member: updatedMember, delivery };
}

export async function generateAndQueueMemberOtp<TSelect extends Prisma.MemberSelect>(
  tx: Prisma.TransactionClient,
  member: MemberPinTarget,
  input: {
    requestedByUserId?: string | null;
    select: TSelect;
  }
) {
  const otp = generateMemberOtp();
  const now = new Date();
  const expiresAt = otpExpiresAt(now);
  const integration = await resolveMemberPinDeliveryIntegration(tx);
  const provider = integration.provider;
  const messageBody = renderSmsTemplate(messageTemplateFor(integration, currentOtpPurpose), {
    otp,
    ttlMinutes: memberOtpTtlMinutes
  });
  const otpHash = await bcrypt.hash(otp, 12);

  const updatedMember = await tx.member.update({
    where: { id: member.id },
    data: {
      currentOtpHash: otpHash,
      currentOtpIssuedAt: now,
      currentOtpExpiresAt: expiresAt
    },
    select: input.select
  });

  const delivery = await tx.memberPinDelivery.create({
    data: {
      memberId: member.id,
      requestedByUserId: input.requestedByUserId ?? null,
      provider,
      channel: memberPinDeliveryChannel,
      purpose: currentOtpPurpose,
      phone: member.phone,
      status: memberPinDeliveryStatus,
      messagePreview: `Meeting OTP SMS queued to ${maskPhone(member.phone)}.`,
      messageCiphertext: encryptJson({
        provider,
        channel: memberPinDeliveryChannel,
        purpose: currentOtpPurpose,
        phone: member.phone,
        body: messageBody,
        template: messageTemplateFor(integration, currentOtpPurpose),
        generatedAt: now.toISOString(),
        expiresAt: expiresAt.toISOString()
      })
    },
    select: memberPinDeliverySelect
  });

  return { member: updatedMember, delivery };
}
