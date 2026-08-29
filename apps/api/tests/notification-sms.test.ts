import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from "vitest";
import { buildSystemNotificationSms } from "../src/domain/notification-catalogue";
import { prisma } from "../src/lib/prisma";
import { encryptCredentials } from "../src/services/integration-credentials";
import { sendNotificationSms } from "../src/services/notification-service";

/**
 * System notifications going out as SMS.
 *
 * The seam is `notification-service`, so these exercise it rather than any one
 * call site: what matters is that anything put in the console bell also reaches
 * a phone, that a category can be switched off, and that one handset is not
 * texted twice for the same event.
 */

const GROUP_CODE = "NOTIF-SMS-GROUP";
const EMAIL_PREFIX = "notif-sms-";

let withPhoneId = "";
let memberLoginId = "";
let noPhoneId = "";
let sharedHandsetId = "";

function acceptingBonga() {
  return vi.fn(async () =>
    new Response(JSON.stringify({ unique_id: "ref", status: 222, status_message: "sent" }), {
      status: 200
    })
  );
}

async function removeFixture() {
  await prisma.smsBroadcast.deleteMany({ where: { kind: "SYSTEM_NOTIFICATION" } });
  await prisma.user.deleteMany({ where: { email: { startsWith: EMAIL_PREFIX } } });
  const group = await prisma.group.findUnique({ where: { code: GROUP_CODE }, select: { id: true } });
  if (!group) return;
  await prisma.member.deleteMany({ where: { groupId: group.id } });
  await prisma.group.delete({ where: { id: group.id } });
}

async function lastSystemBroadcast() {
  return prisma.smsBroadcast.findFirst({
    where: { kind: "SYSTEM_NOTIFICATION" },
    orderBy: { createdAt: "desc" },
    include: { recipients: { orderBy: { createdAt: "asc" } } }
  });
}

describe("system notifications by SMS", () => {
  beforeAll(async () => {
    const credentialsJson = encryptCredentials({
      BONGA_SMS_CLIENT_ID: "1120",
      BONGA_SMS_API_KEY: "api-key-demo",
      BONGA_SMS_API_SECRET: "api-secret-demo",
      BONGA_SMS_SERVICE_ID: "5843",
      BONGA_SMS_ENDPOINT: "http://example.invalid/v1/send-sms"
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

    await removeFixture();
    const group = await prisma.group.create({
      data: { name: "Karibu VSLA", code: GROUP_CODE, phase: "ACTIVE", county: "Kiambu" }
    });

    // A member login whose USER row carries no phone. The member row does, and
    // that is the number the group actually knows them by.
    const member = await prisma.member.create({
      data: { groupId: group.id, fullName: "Mary Wanjiku", phone: "0757255710", status: "ACTIVE" }
    });

    const [withPhone, memberLogin, noPhone, sharedHandset] = await Promise.all([
      prisma.user.create({
        data: {
          name: "Grace Secretary",
          email: `${EMAIL_PREFIX}secretary@example.com`,
          phone: "0722000111",
          passwordHash: "x",
          role: "GROUP_ACCOUNT",
          groupId: group.id
        }
      }),
      prisma.user.create({
        data: {
          name: "Mary Wanjiku",
          email: `${EMAIL_PREFIX}member@example.com`,
          passwordHash: "x",
          role: "MEMBER",
          groupId: group.id,
          memberId: member.id
        }
      }),
      prisma.user.create({
        data: {
          name: "Paper Only",
          email: `${EMAIL_PREFIX}nophone@example.com`,
          passwordHash: "x",
          role: "MEMBER",
          groupId: group.id
        }
      }),
      // Same handset as Grace, written the other way round. Households share.
      prisma.user.create({
        data: {
          name: "Grace Husband",
          email: `${EMAIL_PREFIX}shared@example.com`,
          phone: "+254722000111",
          passwordHash: "x",
          role: "MEMBER",
          groupId: group.id
        }
      })
    ]);

    withPhoneId = withPhone.id;
    memberLoginId = memberLogin.id;
    noPhoneId = noPhone.id;
    sharedHandsetId = sharedHandset.id;
  }, 30000);

  afterEach(async () => {
    await prisma.notificationSmsSetting.deleteMany({});
  });

  afterAll(async () => {
    await removeFixture();
  });

  describe("wording", () => {
    it("keeps the title, because a body is not always self-contained", () => {
      expect(
        buildSystemNotificationSms(
          "You are now in Karibu VSLA",
          "Your savings already recorded with the group are now in your passbook."
        )
      ).toBe(
        "You are now in Karibu VSLA: Your savings already recorded with the group " +
          "are now in your passbook."
      );
    });

    it("does not stutter when the body already opens with the title", () => {
      expect(buildSystemNotificationSms("Meeting is active", "Meeting is active now.")).toBe(
        "Meeting is active now."
      );
    });

    it("survives an empty half", () => {
      expect(buildSystemNotificationSms("Request declined.", "")).toBe("Request declined.");
      expect(buildSystemNotificationSms("", "The group declined.")).toBe("The group declined.");
    });
  });

  it("texts a notification with no stored setting, because the default is on", async () => {
    const fetcher = acceptingBonga();

    await sendNotificationSms(
      [
        {
          userId: withPhoneId,
          title: "Someone wants to join",
          body: "Mary Wanjiku has asked to join Karibu VSLA.",
          type: "GROUP_JOIN_REQUESTED"
        }
      ],
      { fetch: fetcher, networkEnabled: true }
    );

    const broadcast = await lastSystemBroadcast();
    expect(broadcast?.recipients).toHaveLength(1);
    expect(broadcast?.recipients[0]?.status).toBe("SENT");
    expect(broadcast?.recipients[0]?.message).toBe(
      "Someone wants to join: Mary Wanjiku has asked to join Karibu VSLA."
    );
  });

  it("falls back to the member's phone when the login has none", async () => {
    await sendNotificationSms(
      [
        {
          userId: memberLoginId,
          title: "Meeting is active",
          body: "August meeting has started.",
          type: "MEETING_ACTIVE"
        }
      ],
      { fetch: acceptingBonga(), networkEnabled: true }
    );

    const broadcast = await lastSystemBroadcast();
    expect(broadcast?.recipients[0]?.phone).toBe("0757255710");
    expect(broadcast?.recipients[0]?.status).toBe("SENT");
  });

  it("texts one handset once, however many logins share it", async () => {
    await sendNotificationSms(
      [
        { userId: withPhoneId, title: "Meeting is active", body: "It started.", type: "MEETING_ACTIVE" },
        { userId: sharedHandsetId, title: "Meeting is active", body: "It started.", type: "MEETING_ACTIVE" }
      ],
      { fetch: acceptingBonga(), networkEnabled: true }
    );

    // 0722000111 and +254722000111 are the same phone written two ways.
    const broadcast = await lastSystemBroadcast();
    expect(broadcast?.recipients).toHaveLength(1);
  });

  it("records a user with no reachable number instead of dropping them", async () => {
    await sendNotificationSms(
      [
        {
          userId: noPhoneId,
          title: "Meeting is active",
          body: "It started.",
          type: "MEETING_ACTIVE"
        }
      ],
      { fetch: acceptingBonga(), networkEnabled: true }
    );

    const broadcast = await lastSystemBroadcast();
    expect(broadcast?.recipients[0]?.status).toBe("FAILED");
    expect(broadcast?.recipients[0]?.providerStatus).toBe("NO_PHONE");
  });

  it("sends nothing for a category that has been switched off", async () => {
    await prisma.notificationSmsSetting.create({
      data: { type: "STORE_REQUEST_SUBMITTED", smsEnabled: false }
    });
    const fetcher = acceptingBonga();
    const before = await prisma.smsBroadcast.count({ where: { kind: "SYSTEM_NOTIFICATION" } });

    await sendNotificationSms(
      [
        {
          userId: withPhoneId,
          title: "Store request submitted",
          body: "Solar lamp is waiting for programme review.",
          type: "STORE_REQUEST_SUBMITTED"
        }
      ],
      { fetch: fetcher, networkEnabled: true }
    );

    expect(fetcher).not.toHaveBeenCalled();
    expect(await prisma.smsBroadcast.count({ where: { kind: "SYSTEM_NOTIFICATION" } })).toBe(before);
  });

  it("switches off one category without touching another", async () => {
    await prisma.notificationSmsSetting.create({
      data: { type: "MEETING_ACTIVE", smsEnabled: false }
    });
    const fetcher = acceptingBonga();

    await sendNotificationSms(
      [
        { userId: withPhoneId, title: "Meeting is active", body: "It started.", type: "MEETING_ACTIVE" },
        {
          userId: withPhoneId,
          title: "You are now in Karibu VSLA",
          body: "You have been added to the group.",
          type: "GROUP_JOIN_APPROVED"
        }
      ],
      { fetch: fetcher, networkEnabled: true }
    );

    expect(fetcher).toHaveBeenCalledTimes(1);
    const broadcast = await lastSystemBroadcast();
    expect(broadcast?.recipients[0]?.message).toContain("You are now in Karibu VSLA");
  });

  it("honours an explicit console-only notification", async () => {
    const fetcher = acceptingBonga();

    await sendNotificationSms(
      [
        {
          userId: withPhoneId,
          title: "Quiet notice",
          body: "This one stays in the bell.",
          type: "INFO",
          sms: false
        }
      ],
      { fetch: fetcher, networkEnabled: true }
    );

    expect(fetcher).not.toHaveBeenCalled();
  });
});
