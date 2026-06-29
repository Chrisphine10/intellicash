import { afterAll, beforeAll, describe, expect, it, vi } from "vitest";
import { seedDatabase } from "../prisma/seed";
import { prisma } from "../src/lib/prisma";
import { encryptCredentials } from "../src/services/integration-credentials";
import { createSmsBroadcast } from "../src/services/admin-sms-service";

describe("admin SMS broadcasts", () => {
  beforeAll(async () => {
    await seedDatabase();
    await prisma.integrationConfig.upsert({
      where: { provider: "BONGA_SMS" },
      create: {
        provider: "BONGA_SMS",
        displayName: "Bonga SMS",
        requiredEnvJson: JSON.stringify([
          "BONGA_SMS_CLIENT_ID",
          "BONGA_SMS_API_KEY",
          "BONGA_SMS_API_SECRET",
          "BONGA_SMS_SERVICE_ID"
        ]),
        credentialsJson: encryptCredentials({
          BONGA_SMS_CLIENT_ID: "1120",
          BONGA_SMS_API_KEY: "api-key-demo",
          BONGA_SMS_API_SECRET: "api-secret-demo",
          BONGA_SMS_SERVICE_ID: "5843",
          BONGA_SMS_ENDPOINT: "http://167.172.14.50:4002/v1/send-sms"
        })
      },
      update: {
        credentialsJson: encryptCredentials({
          BONGA_SMS_CLIENT_ID: "1120",
          BONGA_SMS_API_KEY: "api-key-demo",
          BONGA_SMS_API_SECRET: "api-secret-demo",
          BONGA_SMS_SERVICE_ID: "5843",
          BONGA_SMS_ENDPOINT: "http://167.172.14.50:4002/v1/send-sms"
        })
      }
    });
  }, 30000);

  it("marks an individual broadcast sent when Bonga accepts the SMS", async () => {
    const member = await prisma.member.findFirstOrThrow({
      where: { status: "ACTIVE" },
      select: { id: true }
    });
    const fetcher = vi.fn(async () =>
      new Response(JSON.stringify({ unique_id: "bonga-ref-1", status: 222, status_message: "Accepted" }), {
        status: 200
      })
    );

    const broadcast = await createSmsBroadcast(
      {
        targetType: "MEMBER",
        memberId: member.id,
        message: "This is a mocked Bonga admin SMS."
      },
      { fetch: fetcher, networkEnabled: true }
    );

    expect(fetcher).toHaveBeenCalledTimes(1);
    expect(broadcast.status).toBe("SENT");
    expect(broadcast.sentCount).toBe(1);
    expect(broadcast.failedCount).toBe(0);
    expect(broadcast.recipients[0]).toEqual(
      expect.objectContaining({
        status: "SENT",
        providerReference: "bonga-ref-1",
        providerStatus: "222"
      })
    );
  });
});

afterAll(async () => {
  await prisma.$disconnect();
});
