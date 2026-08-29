import { afterAll, beforeAll, describe, expect, it, vi } from "vitest";
import { prisma } from "../src/lib/prisma";
import { encryptCredentials } from "../src/services/integration-credentials";
import { notifySharePurchases, sendMeetingSummaries } from "../src/services/meeting-sms-service";

/**
 * Automatic member SMS, against real rows.
 *
 * Mocks would prove the wording and nothing else. What is actually at risk here
 * is the queries: which members are included, which totals are summed, and
 * whether a group that never asked for these messages is left alone. So this
 * builds a small group and reads it back through the real service.
 */

const GROUP_CODE = "SMS-TEST-GROUP";

let groupId = "";
let meetingId = "";
let maryId = "";
let otienoId = "";
let noPhoneId = "";

function acceptingBonga() {
  return vi.fn(async () =>
    new Response(
      JSON.stringify({ unique_id: "bonga-ref", status: 222, status_message: "sent" }),
      { status: 200 }
    )
  );
}

async function ledger(input: {
  memberId: string;
  type: string;
  amountCents: number;
  direction?: "CREDIT" | "DEBIT";
}) {
  return prisma.ledgerEntry.create({
    data: {
      groupId,
      meetingId,
      memberId: input.memberId,
      type: input.type,
      amountCents: input.amountCents,
      direction: input.direction ?? "CREDIT",
      description: `${input.type} for test`,
      signature: "test-signature"
    }
  });
}

async function setSmsPolicy(patch: {
  smsSharePurchaseEnabled?: boolean;
  smsMeetingSummaryEnabled?: boolean;
}) {
  await prisma.groupPolicy.upsert({
    where: { groupId },
    create: { groupId, ...patch },
    update: patch
  });
}

/**
 * Take the fixture down in dependency order.
 *
 * `LedgerEntry.group` is `onDelete: Restrict` deliberately: the ledger is not
 * something a cascade may quietly remove. A test that creates money has to
 * clear it up explicitly, which is the right amount of friction.
 */
async function removeFixture() {
  const existing = await prisma.group.findUnique({
    where: { code: GROUP_CODE },
    select: { id: true }
  });
  if (!existing) return;

  await prisma.smsBroadcast.deleteMany({ where: { targetGroupId: existing.id } });
  await prisma.ledgerEntry.deleteMany({ where: { groupId: existing.id } });
  await prisma.meeting.deleteMany({ where: { groupId: existing.id } });
  await prisma.groupPolicy.deleteMany({ where: { groupId: existing.id } });
  await prisma.member.deleteMany({ where: { groupId: existing.id } });
  await prisma.group.delete({ where: { id: existing.id } });
}

/** The bodies actually sent, newest broadcast first. */
async function lastBroadcast(kind: string) {
  return prisma.smsBroadcast.findFirst({
    where: { kind, targetGroupId: groupId },
    orderBy: { createdAt: "desc" },
    include: { recipients: { orderBy: { createdAt: "asc" } } }
  });
}

describe("automatic member SMS", () => {
  beforeAll(async () => {
    // `update: {}` here left the seeded row's empty credentials in place and
    // every send resolved to "no provider configured" -- the same shape as the
    // permission-template bug this codebase already documents.
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
      data: {
        name: "Karibu VSLA",
        code: GROUP_CODE,
        phase: "ACTIVE",
        county: "Kiambu"
      }
    });
    groupId = group.id;

    const meeting = await prisma.meeting.create({
      data: {
        groupId,
        title: "August meeting",
        status: "SEALED",
        scheduledAt: new Date("2026-08-29T07:00:00.000Z"),
        closedAt: new Date("2026-08-29T12:00:00.000Z")
      }
    });
    meetingId = meeting.id;

    const [mary, otieno, noPhone] = await Promise.all([
      prisma.member.create({
        data: { groupId, fullName: "Mary Wanjiku", phone: "0757255710", status: "ACTIVE" }
      }),
      prisma.member.create({
        data: { groupId, fullName: "Otieno Odhiambo", phone: "0722000111", status: "ACTIVE" }
      }),
      // Imported members routinely arrive like this.
      prisma.member.create({
        data: { groupId, fullName: "Grace Blank", phone: "", status: "ACTIVE" }
      })
    ]);
    maryId = mary.id;
    otienoId = otieno.id;
    noPhoneId = noPhone.id;

    // An inactive member must not be texted about a meeting they have left.
    await prisma.member.create({
      data: { groupId, fullName: "Left Group", phone: "0733444555", status: "EXITED" }
    });

    await ledger({ memberId: maryId, type: "SHARE_PURCHASE", amountCents: 50_000 });
    await ledger({ memberId: maryId, type: "SOCIAL_CONTRIBUTION", amountCents: 5_000 });
    await ledger({ memberId: noPhoneId, type: "SHARE_PURCHASE", amountCents: 20_000 });
  }, 30000);

  afterAll(async () => {
    await removeFixture();
  });

  it("sends nothing at all for a group that has not opted in", async () => {
    await setSmsPolicy({ smsSharePurchaseEnabled: false, smsMeetingSummaryEnabled: false });
    const fetcher = acceptingBonga();

    const purchase = await notifySharePurchases(
      [
        {
          id: "entry-1",
          groupId,
          memberId: maryId,
          meetingId,
          cycleId: null,
          type: "SHARE_PURCHASE",
          amountCents: 50_000,
          createdAt: new Date("2026-08-29T09:00:00.000Z")
        }
      ],
      {},
      { fetch: fetcher, networkEnabled: true }
    );
    const summary = await sendMeetingSummaries(meetingId, {}, { fetch: fetcher, networkEnabled: true });

    expect(purchase).toBeNull();
    expect(summary).toBeNull();
    expect(fetcher).not.toHaveBeenCalled();
  });

  it("confirms a share purchase with the member's running cycle total", async () => {
    await setSmsPolicy({ smsSharePurchaseEnabled: true });
    const fetcher = acceptingBonga();

    const result = await notifySharePurchases(
      [
        {
          id: "entry-1",
          groupId,
          memberId: maryId,
          meetingId,
          cycleId: null,
          type: "SHARE_PURCHASE",
          amountCents: 50_000,
          createdAt: new Date("2026-08-29T09:00:00.000Z")
        }
      ],
      {},
      { fetch: fetcher, networkEnabled: true }
    );

    expect(result?.sent).toBe(1);
    const broadcast = await lastBroadcast("SHARE_PURCHASE");
    expect(broadcast?.meetingId).toBe(meetingId);
    expect(broadcast?.recipients[0]?.message).toBe(
      "Mary: KES 500 shares recorded at Karibu VSLA on 29 Aug 2026. " +
        "Your shares this cycle: KES 500. Query? Ask your secretary."
    );
  });

  it("ignores entries that are not share purchases", async () => {
    await setSmsPolicy({ smsSharePurchaseEnabled: true });
    const fetcher = acceptingBonga();

    const result = await notifySharePurchases(
      [
        {
          id: "entry-2",
          groupId,
          memberId: maryId,
          meetingId,
          cycleId: null,
          type: "SOCIAL_CONTRIBUTION",
          amountCents: 5_000,
          createdAt: new Date()
        }
      ],
      {},
      { fetch: fetcher, networkEnabled: true }
    );

    expect(result).toBeNull();
    expect(fetcher).not.toHaveBeenCalled();
  });

  it("summarises the meeting to every active member, including those with nothing", async () => {
    await setSmsPolicy({ smsMeetingSummaryEnabled: true });
    const fetcher = acceptingBonga();

    const result = await sendMeetingSummaries(meetingId, {}, { fetch: fetcher, networkEnabled: true });

    // Three active members; the exited one is not one of them.
    expect(result?.attempted).toBe(2);
    expect(result?.sent).toBe(2);
    expect(result?.skipped).toBe(1);

    const broadcast = await lastBroadcast("MEETING_SUMMARY");
    const byName = new Map(broadcast?.recipients.map((row) => [row.memberName, row]) ?? []);
    expect(byName.size).toBe(3);
    expect(byName.has("Left Group")).toBe(false);

    expect(byName.get("Mary Wanjiku")?.message).toBe(
      "Mary: Karibu VSLA meeting of 29 Aug 2026 is closed. " +
        "You: shares KES 500, social KES 50. Query? Ask your secretary."
    );
    // The member who did nothing is told so — that is what catches an entry
    // posted against the wrong person.
    expect(byName.get("Otieno Odhiambo")?.message).toContain("nothing recorded for you");
  });

  it("records a member with no phone number as failed, with the reason", async () => {
    await setSmsPolicy({ smsMeetingSummaryEnabled: true });
    await sendMeetingSummaries(meetingId, {}, { fetch: acceptingBonga(), networkEnabled: true });

    const broadcast = await lastBroadcast("MEETING_SUMMARY");
    const blank = broadcast?.recipients.find((row) => row.memberName === "Grace Blank");

    expect(blank?.status).toBe("FAILED");
    expect(blank?.providerStatus).toBe("NO_PHONE");
    expect(blank?.providerMessage).toContain("no phone number");
  });

  it("survives a provider that throws, and records the failure", async () => {
    await setSmsPolicy({ smsMeetingSummaryEnabled: true });
    const fetcher = vi.fn(async () => {
      throw new Error("socket hang up");
    });

    // Sealing a meeting has already committed by this point. A provider
    // outage must not surface as an exception the caller has to handle.
    const result = await sendMeetingSummaries(meetingId, {}, { fetch: fetcher, networkEnabled: true });

    expect(result?.failed).toBe(2);
    expect(result?.sent).toBe(0);
    const broadcast = await lastBroadcast("MEETING_SUMMARY");
    expect(broadcast?.status).toBe("FAILED");
  });
});
