import request from "supertest";
import bcrypt from "bcryptjs";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword, meetingStepLabels, meetingSteps } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();
const PIN = "246813";

/**
 * Money moves in front of the members it belongs to.
 *
 * Two rules a VSLA constitution takes for granted, and neither of which the
 * software enforced until 2 Aug 2026:
 *
 *  - welfare is paid out DURING a meeting, not from an office between them;
 *  - a meeting is closed by an OFFICIAL, who proves it is them.
 *
 * Sealing freezes the money and the minutes. Before this, any session holding
 * `meetings:write` could freeze a group's record with an empty request body —
 * no PIN, no named official, nothing tying the closure to a person who was in
 * the room.
 */
describe("meetings are where the money is approved", () => {
  let cookies: string[];
  let groupId: string;
  let officialId: string;
  let ordinaryMemberId: string;

  /**
   * An open meeting that has worked through its agenda, so the only thing
   * standing between it and a seal is the rule under test.
   */
  async function openMeeting(title: string, completeSteps = true) {
    const created = await prisma.meeting.create({
      data: { groupId, title, scheduledAt: new Date(), status: "IN_PROGRESS", openedAt: new Date() }
    });
    if (completeSteps) {
      await prisma.meetingStepRecord.createMany({
        data: meetingSteps.map((step) => ({
          meetingId: created.id,
          step,
          name: meetingStepLabels[step],
          status: "COMPLETED",
          completedAt: new Date()
        }))
      });
    }
    return created.id;
  }

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirstOrThrow({ orderBy: { createdAt: "asc" } });
    groupId = group.id;

    const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
    const login = await request(app)
      .post("/api/v1/auth/login")
      .send({ phone: admin.phone, password: demoPassword })
      .expect(200);
    const cookie = login.headers["set-cookie"];
    cookies = Array.isArray(cookie) ? cookie : [cookie as unknown as string];

    const pinHash = await bcrypt.hash(PIN, 12);
    const official = await prisma.member.create({
      data: {
        groupId,
        fullName: "Grace The Secretary",
        phone: "254790000101",
        status: "ACTIVE",
        role: "SECRETARY",
        pinHash
      }
    });
    officialId = official.id;

    const ordinary = await prisma.member.create({
      data: {
        groupId,
        fullName: "Peter Ordinary Member",
        phone: "254790000102",
        status: "ACTIVE",
        role: "MEMBER",
        pinHash
      }
    });
    ordinaryMemberId = ordinary.id;

    // Welfare money to spend.
    const social = await prisma.fundAccount.findFirstOrThrow({ where: { groupId, type: "SOCIAL" } });
    await prisma.fundAccount.update({
      where: { id: social.id },
      data: { balanceCents: 500_000 }
    });
  }, 60000);

  describe("welfare is paid out inside a meeting", () => {
    it("refuses an expense with no meeting at all", async () => {
      const response = await request(app)
        .post(`/api/v1/groups/${groupId}/welfare-expenses`)
        .set("Cookie", cookies)
        .send({ amountCents: 10_000, category: "MEDICAL", payeeName: "Kisumu Hospital" })
        .expect(400);

      expect(response.body.error.code).toBe("VALIDATION_ERROR");
    });

    it("refuses a meeting that belongs to another group", async () => {
      const other = await prisma.group.findFirstOrThrow({ where: { id: { not: groupId } } });
      const foreign = await prisma.meeting.create({
        data: {
          groupId: other.id,
          title: "Another group's meeting",
          scheduledAt: new Date(),
          status: "IN_PROGRESS"
        }
      });

      const response = await request(app)
        .post(`/api/v1/groups/${groupId}/welfare-expenses`)
        .set("Cookie", cookies)
        .send({
          amountCents: 10_000,
          category: "MEDICAL",
          payeeName: "Kisumu Hospital",
          meetingId: foreign.id
        })
        .expect(404);

      expect(response.body.error.code).toBe("MEETING_NOT_FOUND");
    });

    it("refuses a meeting that has already been sealed", async () => {
      // Attaching a payment to a closed meeting rewrites what the group has
      // already signed off.
      const sealed = await prisma.meeting.create({
        data: {
          groupId,
          title: "Last month's meeting",
          scheduledAt: new Date(),
          status: "SEALED",
          closedAt: new Date()
        }
      });

      const response = await request(app)
        .post(`/api/v1/groups/${groupId}/welfare-expenses`)
        .set("Cookie", cookies)
        .send({
          amountCents: 10_000,
          category: "BEREAVEMENT",
          payeeName: "A family",
          meetingId: sealed.id
        })
        .expect(409);

      expect(response.body.error.code).toBe("MEETING_NOT_OPEN");
    });

    it("allows it in an open meeting, and ties the record to that meeting", async () => {
      const meetingId = await openMeeting("Welfare meeting");

      const response = await request(app)
        .post(`/api/v1/groups/${groupId}/welfare-expenses`)
        .set("Cookie", cookies)
        .send({
          amountCents: 25_000,
          category: "MEDICAL",
          payeeName: "Kisumu County Hospital",
          meetingId
        })
        .expect(201);

      expect(response.body.data.expense.meetingId).toBe(meetingId);
      // The ledger line lands in the same meeting, so the minutes and the money
      // cannot drift apart.
      const entry = await prisma.ledgerEntry.findUniqueOrThrow({
        where: { id: response.body.data.expense.ledgerEntryId }
      });
      expect(entry.meetingId).toBe(meetingId);
    });
  });

  describe("closing a meeting needs an official's PIN", () => {
    // NOT async: supertest's Test is chainable, and wrapping it in a Promise
    // loses .expect().
    function seal(meetingId: string, body: Record<string, unknown>) {
      return request(app)
        .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/seal`)
        .set("Cookie", cookies)
        .send(body);
    }

    it("refuses to close with no key submission", async () => {
      const meetingId = await openMeeting("No PIN meeting");
      const response = await seal(meetingId, { minutes: "All agreed." }).expect(400);
      expect(response.body.error.code).toBe("VALIDATION_ERROR");

      // Still open, still editable — a refused seal must not half-close it.
      const meeting = await prisma.meeting.findUniqueOrThrow({ where: { id: meetingId } });
      expect(meeting.status).toBe("IN_PROGRESS");
    });

    it("refuses a wrong PIN", async () => {
      const meetingId = await openMeeting("Wrong PIN meeting");
      // 400 INVALID_MEMBER_CREDENTIAL is what the existing meeting-key path
      // already returns; sealing follows the same contract rather than
      // inventing a second way to say "that PIN is wrong".
      const response = await seal(meetingId, {
        keySubmission: { memberId: officialId, pin: "000000" }
      }).expect(400);
      expect(response.body.error.code).toBe("INVALID_MEMBER_CREDENTIAL");

      const meeting = await prisma.meeting.findUniqueOrThrow({ where: { id: meetingId } });
      expect(meeting.status).toBe("IN_PROGRESS");
      expect(meeting.sealedByMemberId).toBeNull();
    });

    it("refuses an ordinary member, however correct their PIN", async () => {
      // The rule is about who may close the record, not about who can type.
      const meetingId = await openMeeting("Ordinary member meeting");
      const response = await seal(meetingId, {
        keySubmission: { memberId: ordinaryMemberId, pin: PIN }
      }).expect(403);

      expect(response.body.error.code).toBe("OFFICIAL_REQUIRED");
      const meeting = await prisma.meeting.findUniqueOrThrow({ where: { id: meetingId } });
      expect(meeting.status).toBe("IN_PROGRESS");
    });

    it("closes when an official proves it is them, and records who", async () => {
      const meetingId = await openMeeting("Properly closed meeting");
      const response = await seal(meetingId, {
        minutes: "Welfare paid, share-out agreed.",
        keySubmission: { memberId: officialId, pin: PIN }
      }).expect(200);

      expect(response.body.data.status).toBe("SEALED");

      const meeting = await prisma.meeting.findUniqueOrThrow({ where: { id: meetingId } });
      // The audit fact the whole rule exists to create.
      expect(meeting.sealedByMemberId).toBe(officialId);
      expect(meeting.closedAt).not.toBeNull();
    });

    it("a sealed meeting cannot be sealed again", async () => {
      const meetingId = await openMeeting("Double seal meeting");
      await seal(meetingId, { keySubmission: { memberId: officialId, pin: PIN } }).expect(200);

      const response = await seal(meetingId, {
        keySubmission: { memberId: officialId, pin: PIN }
      }).expect(400);
      expect(response.body.error.code).toBe("MEETING_NOT_ACTIVE");
    });
  });
});
