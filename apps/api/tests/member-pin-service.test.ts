import { afterEach, describe, expect, it, vi } from "vitest";
import { encryptJson } from "../src/lib/crypto";

const prismaMocks = vi.hoisted(() => ({
  deliveryFindUnique: vi.fn(),
  deliveryUpdate: vi.fn(),
  configFindUnique: vi.fn()
}));

vi.mock("../src/lib/prisma", () => ({
  prisma: {
    memberPinDelivery: {
      findUnique: prismaMocks.deliveryFindUnique,
      update: prismaMocks.deliveryUpdate
    },
    integrationConfig: {
      findUnique: prismaMocks.configFindUnique
    }
  }
}));

import { sendQueuedMemberPinDelivery } from "../src/services/member-pin-service";

describe("member PIN delivery", () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it("marks queued Bonga OTP SMS deliveries as sent using admin-stored integration credentials", async () => {
    const delivery = {
      id: "delivery-1",
      memberId: "member-1",
      provider: "BONGA_SMS",
      channel: "SMS",
      purpose: "CURRENT_OTP",
      phone: "0757255710",
      status: "QUEUED",
      messagePreview: "Meeting OTP SMS queued.",
      sentAt: null,
      createdAt: new Date("2026-06-09T08:00:00.000Z"),
      messageCiphertext: encryptJson({
        provider: "BONGA_SMS",
        channel: "SMS",
        purpose: "CURRENT_OTP",
        phone: "0757255710",
        body: "Your Intelli Cash meeting OTP is 123456.",
        generatedAt: new Date("2026-06-09T08:00:00.000Z").toISOString()
      })
    };
    prismaMocks.deliveryFindUnique.mockResolvedValue(delivery);
    prismaMocks.configFindUnique.mockResolvedValue({
      credentialsJson: encryptJson({
        BONGA_SMS_CLIENT_ID: "1120",
        BONGA_SMS_API_KEY: "test-key",
        BONGA_SMS_API_SECRET: "test-secret",
        BONGA_SMS_SERVICE_ID: "5843",
        BONGA_SMS_ENDPOINT: "http://167.172.14.50:4002/v1/send-sms"
      })
    });
    prismaMocks.deliveryUpdate.mockImplementation(async ({ data }: { data: { status: string; sentAt: Date | null } }) => ({
      id: delivery.id,
      memberId: delivery.memberId,
      provider: delivery.provider,
      channel: delivery.channel,
      purpose: delivery.purpose,
      phone: delivery.phone,
      status: data.status,
      messagePreview: delivery.messagePreview,
      sentAt: data.sentAt,
      createdAt: delivery.createdAt
    }));
    const fetchMock = vi.fn(async (_input: RequestInfo | URL, _init?: RequestInit) =>
      new Response(
        JSON.stringify({
          unique_id: 378008604,
          status_message: "sent",
          status: 222,
          error: null
        }),
        { status: 200, headers: { "content-type": "application/json" } }
      )
    );

    const sent = await sendQueuedMemberPinDelivery(delivery.id, {
      fetch: fetchMock,
      networkEnabled: true
    });

    expect(prismaMocks.configFindUnique).toHaveBeenCalledWith({
      where: { provider: "BONGA_SMS" },
      select: { credentialsJson: true }
    });
    expect(prismaMocks.deliveryUpdate).toHaveBeenCalledWith(
      expect.objectContaining({
        where: { id: delivery.id },
        data: expect.objectContaining({
          status: "SENT",
          sentAt: expect.any(Date)
        })
      })
    );
    expect(sent).toEqual(
      expect.objectContaining({
        id: delivery.id,
        status: "SENT",
        sentAt: expect.any(Date)
      })
    );
  });
});
