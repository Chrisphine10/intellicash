import type { Prisma } from "@prisma/client";
import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";
import { decryptCredentials } from "./integration-credentials";
import { maskPhone } from "./member-pin-service";
import {
  bongaSmsRequiredCredentialKeys,
  combineBongaSmsCredentials,
  sendSms,
  type SmsProvider
} from "./sms-service";

const smsProviders = ["BONGA_SMS", "AFRICAS_TALKING"] as const satisfies SmsProvider[];
const africasTalkingCredentialKeys = [
  "AFRICASTALKING_USERNAME",
  "AFRICASTALKING_API_KEY",
  "AFRICASTALKING_SENDER_ID"
];

const broadcastInclude = {
  recipients: { orderBy: { createdAt: "asc" } }
} satisfies Prisma.SmsBroadcastInclude;

export type SmsBroadcastWithRecipients = Prisma.SmsBroadcastGetPayload<{
  include: typeof broadcastInclude;
}>;

type BroadcastTargetType = "GROUP" | "MEMBER";

export interface CreateSmsBroadcastInput {
  requestedByUserId?: string | null;
  targetType: BroadcastTargetType;
  groupId?: string | null;
  memberId?: string | null;
  message: string;
  provider?: SmsProvider;
}

interface SmsBroadcastDependencies {
  fetch?: typeof fetch;
  networkEnabled?: boolean;
}

type BroadcastRecipientTarget = {
  memberId: string;
  groupId: string;
  memberName: string;
  phone: string;
};

function isSmsProvider(provider: string): provider is SmsProvider {
  return provider === "BONGA_SMS" || provider === "AFRICAS_TALKING";
}

function broadcastStatus(counts: { recipientCount: number; queuedCount: number; sentCount: number; failedCount: number }) {
  if (counts.recipientCount === 0) return "FAILED";
  if (counts.sentCount === counts.recipientCount) return "SENT";
  if (counts.failedCount === counts.recipientCount) return "FAILED";
  if (counts.sentCount > 0 || counts.failedCount > 0) return "PARTIAL";
  return "QUEUED";
}

async function credentialsFor(provider: SmsProvider) {
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

function requiredCredentialKeys(provider: SmsProvider) {
  return provider === "BONGA_SMS" ? [...bongaSmsRequiredCredentialKeys] : africasTalkingCredentialKeys;
}

async function resolveSmsProvider(provider?: SmsProvider) {
  const providers = provider ? [provider] : smsProviders;

  for (const candidate of providers) {
    const credentials = await credentialsFor(candidate);
    const configured = requiredCredentialKeys(candidate).every((key) => credentials[key] || process.env[key]);
    if (configured) return { provider: candidate, credentials };
  }

  throw new ApiHttpError(400, "SMS_PROVIDER_NOT_CONFIGURED", "No configured SMS provider is ready for broadcasts.");
}

async function resolveRecipients(input: Pick<CreateSmsBroadcastInput, "targetType" | "groupId" | "memberId">) {
  if (input.targetType === "GROUP") {
    if (!input.groupId) {
      throw new ApiHttpError(400, "GROUP_REQUIRED", "Select a group for group SMS broadcasts.");
    }

    const group = await prisma.group.findUnique({
      where: { id: input.groupId },
      select: {
        id: true,
        members: {
          where: { status: "ACTIVE" },
          orderBy: { joinedAt: "asc" },
          select: { id: true, groupId: true, fullName: true, phone: true }
        }
      }
    });

    if (!group) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist.");
    return group.members.map((member) => ({
      memberId: member.id,
      groupId: member.groupId,
      memberName: member.fullName,
      phone: member.phone
    }));
  }

  if (!input.memberId) {
    throw new ApiHttpError(400, "MEMBER_REQUIRED", "Select a member for individual SMS broadcasts.");
  }

  const member = await prisma.member.findFirst({
    where: { id: input.memberId, status: "ACTIVE" },
    select: { id: true, groupId: true, fullName: true, phone: true }
  });

  if (!member) throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Active member does not exist.");
  return [
    {
      memberId: member.id,
      groupId: member.groupId,
      memberName: member.fullName,
      phone: member.phone
    }
  ];
}

export function serializeSmsBroadcast(broadcast: SmsBroadcastWithRecipients) {
  return {
    ...broadcast,
    recipients: broadcast.recipients.map((recipient) => ({
      ...recipient,
      phone: maskPhone(recipient.phone)
    }))
  };
}

export async function listSmsBroadcasts() {
  const broadcasts = await prisma.smsBroadcast.findMany({
    orderBy: { createdAt: "desc" },
    take: 20,
    include: {
      recipients: {
        orderBy: { createdAt: "asc" },
        take: 10
      }
    }
  });

  return broadcasts.map((broadcast) => serializeSmsBroadcast(broadcast));
}

export async function createSmsBroadcast(
  input: CreateSmsBroadcastInput,
  dependencies: SmsBroadcastDependencies = {}
) {
  const providerInput = input.provider && isSmsProvider(input.provider) ? input.provider : undefined;
  const integration = await resolveSmsProvider(providerInput);
  const recipients = await resolveRecipients(input);

  if (recipients.length === 0) {
    throw new ApiHttpError(400, "NO_SMS_RECIPIENTS", "No active members with phone numbers were found.");
  }

  const broadcast = await prisma.smsBroadcast.create({
    data: {
      requestedByUserId: input.requestedByUserId ?? null,
      targetType: input.targetType,
      targetGroupId: input.targetType === "GROUP" ? input.groupId ?? null : null,
      targetMemberId: input.targetType === "MEMBER" ? input.memberId ?? null : null,
      provider: integration.provider,
      message: input.message,
      status: "QUEUED",
      recipientCount: recipients.length,
      queuedCount: recipients.length,
      recipients: {
        create: recipients.map((recipient: BroadcastRecipientTarget) => ({
          memberId: recipient.memberId,
          groupId: recipient.groupId,
          memberName: recipient.memberName,
          phone: recipient.phone,
          provider: integration.provider,
          status: "QUEUED"
        }))
      }
    },
    include: broadcastInclude
  });

  const counts = {
    recipientCount: recipients.length,
    queuedCount: 0,
    sentCount: 0,
    failedCount: 0
  };

  for (const recipient of broadcast.recipients) {
    const result = await sendSms(
      {
        provider: integration.provider,
        phone: recipient.phone,
        message: input.message,
        credentials: integration.credentials
      },
      dependencies
    );

    if (result.status === "SENT") counts.sentCount += 1;
    else if (result.status === "FAILED") counts.failedCount += 1;
    else counts.queuedCount += 1;

    await prisma.smsBroadcastRecipient.update({
      where: { id: recipient.id },
      data: {
        status: result.status,
        providerReference: result.providerReference ?? null,
        providerStatus: result.providerStatus === undefined || result.providerStatus === null ? null : String(result.providerStatus),
        providerMessage: result.providerMessage ?? null,
        sentAt: result.status === "SENT" ? new Date() : null
      }
    });
  }

  const updated = await prisma.smsBroadcast.update({
    where: { id: broadcast.id },
    data: {
      status: broadcastStatus(counts),
      queuedCount: counts.queuedCount,
      sentCount: counts.sentCount,
      failedCount: counts.failedCount
    },
    include: broadcastInclude
  });

  return serializeSmsBroadcast(updated);
}
