import { afterAll, beforeAll, describe, expect, it, vi } from "vitest";
import { prisma } from "../src/lib/prisma";
import { decryptJson } from "../src/lib/crypto";
import { encryptCredentials } from "../src/services/integration-credentials";
import { generateAndQueueMemberOtp } from "../src/services/member-pin-service";

/**
 * Which provider a meeting OTP is queued against.
 *
 * The check that picks one required EVERY Bonga key, message templates
 * included — and those have code-level defaults, so an admin who saved the four
 * real credentials and left the template boxes empty had their OTPs routed to
 * Africa's Talking, which nobody has configured at all. The delivery sat QUEUED
 * for ever and no member could unlock a meeting online.
 */

const GROUP_CODE = "OTP-ROUTING-GROUP";

let groupId = "";
let memberId = "";

async function removeFixture() {
  const existing = await prisma.group.findUnique({
    where: { code: GROUP_CODE },
    select: { id: true }
  });
  if (!existing) return;

  await prisma.memberPinDelivery.deleteMany({ where: { member: { groupId: existing.id } } });
  await prisma.member.deleteMany({ where: { groupId: existing.id } });
  await prisma.group.delete({ where: { id: existing.id } });
}

/**
 * A production box where Bonga was set up through the console alone.
 *
 * The endpoint and both templates are absent from the environment, because
 * DEPLOY-VPS records no BONGA_SMS_* variables — credentials go in encrypted via
 * Dashboard -> Integrations. A local `.env` that happens to carry the template
 * lines hides the whole failure, so the test clears them rather than trusting
 * whatever the developer's machine has.
 */
async function onAProductionLikeBox<T>(run: () => Promise<T>) {
  vi.stubEnv("BONGA_SMS_ENDPOINT", undefined);
  vi.stubEnv("BONGA_SMS_DEFAULT_PIN_TEMPLATE", undefined);
  vi.stubEnv("BONGA_SMS_OTP_TEMPLATE", undefined);
  try {
    return await run();
  } finally {
    vi.unstubAllEnvs();
  }
}

/** Exactly the four keys the provider itself requires, and nothing else. */
async function storeBongaCredentialsWithoutTemplates() {
  const credentialsJson = encryptCredentials({
    BONGA_SMS_CLIENT_ID: "1120",
    BONGA_SMS_API_KEY: "api-key-demo",
    BONGA_SMS_API_SECRET: "api-secret-demo",
    BONGA_SMS_SERVICE_ID: "5843"
  });
  await prisma.integrationConfig.upsert({
    where: { provider: "BONGA_SMS" },
    create: {
      provider: "BONGA_SMS",
      displayName: "Bonga SMS",
      requiredEnvJson: JSON.stringify(["BONGA_SMS_CLIENT_ID"]),
      credentialsJson
    },
    update: { credentialsJson }
  });
}

describe("meeting OTP provider routing", () => {
  beforeAll(async () => {
    await removeFixture();
    const group = await prisma.group.create({
      data: { name: "OTP Routing VSLA", code: GROUP_CODE, phase: "ACTIVE", county: "Kiambu" }
    });
    groupId = group.id;

    const member = await prisma.member.create({
      data: { groupId, fullName: "Mary Wanjiku", phone: "0757255710", status: "ACTIVE" }
    });
    memberId = member.id;

    await storeBongaCredentialsWithoutTemplates();
  }, 30000);

  afterAll(async () => {
    await removeFixture();
  });

  it("queues the OTP against Bonga when only the required credentials are stored", async () => {
    const { delivery } = await onAProductionLikeBox(() =>
      prisma.$transaction((tx) =>
        generateAndQueueMemberOtp(tx, { id: memberId, fullName: "Mary Wanjiku", phone: "0757255710" }, {
          select: { id: true }
        })
      )
    );

    expect(delivery.provider).toBe("BONGA_SMS");
    expect(delivery.purpose).toBe("CURRENT_OTP");
    expect(delivery.status).toBe("QUEUED");
  });

  it("still picks Bonga when a rival provider is configured and the templates are blank", async () => {
    // The isolating case. With Africa's Talking present, a Bonga account judged
    // "incomplete" because two OPTIONAL template keys are empty loses the
    // election outright, and the OTP is queued against a provider whose send
    // path is not implemented — QUEUED for ever, no member ever unlocks a
    // meeting. Without a rival configured the fallback hides this.
    const { delivery } = await onAProductionLikeBox(async () => {
      vi.stubEnv("AFRICASTALKING_USERNAME", "sandbox");
      vi.stubEnv("AFRICASTALKING_API_KEY", "at-key");
      vi.stubEnv("AFRICASTALKING_SENDER_ID", "INTELLIWLTH");

      return prisma.$transaction((tx) =>
        generateAndQueueMemberOtp(tx, { id: memberId, fullName: "Mary Wanjiku", phone: "0757255710" }, {
          select: { id: true }
        })
      );
    });

    expect(delivery.provider).toBe("BONGA_SMS");
  });

  it("falls back to the built-in OTP wording rather than sending an empty message", async () => {
    const { delivery } = await onAProductionLikeBox(() =>
      prisma.$transaction((tx) =>
        generateAndQueueMemberOtp(tx, { id: memberId, fullName: "Mary Wanjiku", phone: "0757255710" }, {
          select: { id: true }
        })
      )
    );

    const stored = await prisma.memberPinDelivery.findUniqueOrThrow({
      where: { id: delivery.id },
      select: { messageCiphertext: true }
    });
    const payload = decryptJson<{ body?: string }>(stored.messageCiphertext);

    expect(payload.body).toMatch(/meeting OTP is \d{6}/);
    expect(payload.body).toContain("15 minutes");
  });

  it("prefers an admin-authored template when one is stored", async () => {
    const credentialsJson = encryptCredentials({
      BONGA_SMS_CLIENT_ID: "1120",
      BONGA_SMS_API_KEY: "api-key-demo",
      BONGA_SMS_API_SECRET: "api-secret-demo",
      BONGA_SMS_SERVICE_ID: "5843",
      BONGA_SMS_OTP_TEMPLATE: "Kodi yako ni {otp}. Inaisha baada ya dakika {ttlMinutes}."
    });
    await prisma.integrationConfig.update({
      where: { provider: "BONGA_SMS" },
      data: { credentialsJson }
    });

    const { delivery } = await prisma.$transaction((tx) =>
      generateAndQueueMemberOtp(tx, { id: memberId, fullName: "Mary Wanjiku", phone: "0757255710" }, {
        select: { id: true }
      })
    );
    const stored = await prisma.memberPinDelivery.findUniqueOrThrow({
      where: { id: delivery.id },
      select: { messageCiphertext: true }
    });
    const payload = decryptJson<{ body?: string }>(stored.messageCiphertext);

    expect(payload.body).toMatch(/^Kodi yako ni \d{6}\. Inaisha baada ya dakika 15\.$/);

    await storeBongaCredentialsWithoutTemplates();
  });
});
