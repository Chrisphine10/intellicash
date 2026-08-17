import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { planMemberErasure, stripSecrets } from "../src/domain/data-subject";

const app = createApp();

/**
 * Data subject rights, Kenya DPA 2019.
 *
 * The privacy notice promised access and erasure long before anything
 * implemented them. These tests exist to keep the promise honest — in
 * particular that an export is complete but never hands back a credential, and
 * that an erasure removes the person without destroying the group's books.
 */
async function signIn(identifier: string, password = demoPassword) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: identifier, password })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

describe("the erasure plan", () => {
  it("strips identity and keeps the financial record", () => {
    const plan = planMemberErasure("cmabcdef123456");
    const erased = plan.erase.map((f) => f.field);

    expect(erased).toContain("phone");
    expect(erased).toContain("nationalIdHash");
    expect(erased).toContain("pinHash");

    const retained = plan.retain.map((r) => r.entity);
    expect(retained).toContain("LedgerEntry");
    expect(retained).toContain("AuditEvent");
  });

  it("replaces the name with a pseudonym rather than blanking it", () => {
    // A blank name breaks every member list, and a group seeing "  " next to a
    // balance will assume the software lost their record.
    const plan = planMemberErasure("cmabcdef123456");
    const name = plan.erase.find((f) => f.field === "fullName");
    expect(name?.replacement).toBe("Erased member 123456");
  });

  it("gives a ground for everything it keeps", () => {
    // Retention without a stated ground is what a regulator asks about.
    for (const item of planMemberErasure("m1").retain) {
      expect(item.ground).toBeTruthy();
      expect(item.note.length).toBeGreaterThan(20);
    }
  });
});

describe("stripSecrets", () => {
  it("removes credentials even when nobody remembered to list them", () => {
    const stripped = stripSecrets({
      id: "m1",
      fullName: "Mary Njeri",
      pinHash: "x",
      currentOtpHash: "y",
      nationalIdHash: "z",
      messageCiphertext: "c",
      someNewApiToken: "t"
    });
    expect(stripped).toEqual({ id: "m1", fullName: "Mary Njeri" });
  });
});

describe("data subject endpoints", () => {
  let adminCookies: string[];
  let agentCookies: string[];
  let memberId: string;
  let memberName: string;

  beforeAll(async () => {
    await seedDatabase();
    const admin = demoAccounts.find((a) => a.role === "IWL_ADMIN")!;
    const agent = demoAccounts.find((a) => a.role === "VILLAGE_AGENT")!;
    adminCookies = await signIn(admin.phone);
    agentCookies = await signIn(agent.phone);

    const member = await prisma.member.findFirstOrThrow({ select: { id: true, fullName: true } });
    memberId = member.id;
    memberName = member.fullName;
  }, 180000);

  it("exports everything held about a member", async () => {
    const response = await request(app)
      .get(`/api/v1/members/${memberId}/personal-data`)
      .set("Cookie", adminCookies)
      .expect(200);

    expect(response.body.data.subject.id).toBe(memberId);
    expect(response.body.data.records).toHaveProperty("ledgerEntries");
    expect(response.body.data.records).toHaveProperty("attendance");
    expect(response.body.data.records).toHaveProperty("loans");
  });

  it("never returns a credential, even to the data subject", async () => {
    const response = await request(app)
      .get(`/api/v1/members/${memberId}/personal-data`)
      .set("Cookie", adminCookies)
      .expect(200);

    // A four-digit PIN hash is recoverable, so exporting it would turn a
    // privacy right into a way to harvest meeting keys.
    const body = JSON.stringify(response.body);
    expect(body).not.toContain("pinHash");
    expect(body).not.toContain("currentOtpHash");
    expect(body).not.toContain("nationalIdHash");
  });

  it("records the export, because the Act expects it to be answerable", async () => {
    const before = await prisma.auditEvent.count({ where: { type: "PERSONAL_DATA_EXPORTED" } });
    await request(app)
      .get(`/api/v1/members/${memberId}/personal-data`)
      .set("Cookie", adminCookies)
      .expect(200);
    const after = await prisma.auditEvent.count({ where: { type: "PERSONAL_DATA_EXPORTED" } });
    expect(after).toBe(before + 1);
  });

  it("refuses a field agent — they visit groups, they do not administer records", async () => {
    await request(app)
      .get(`/api/v1/members/${memberId}/personal-data`)
      .set("Cookie", agentCookies)
      .expect(403);
  });

  it("refuses an erasure whose confirmation does not match", async () => {
    const response = await request(app)
      .post(`/api/v1/members/${memberId}/erase`)
      .set("Cookie", adminCookies)
      .send({ confirmFullName: "Somebody Else" })
      .expect(400);
    expect(response.body.error.code).toBe("CONFIRMATION_MISMATCH");
  });

  it("erases the person and keeps the group's books", async () => {
    const ledgerBefore = await prisma.ledgerEntry.count({ where: { memberId } });

    const response = await request(app)
      .post(`/api/v1/members/${memberId}/erase`)
      .set("Cookie", adminCookies)
      .send({ confirmFullName: memberName, reason: "Member request" })
      .expect(200);

    expect(response.body.data.erased).toBe(true);

    const after = await prisma.member.findUniqueOrThrow({ where: { id: memberId } });
    expect(after.phone).toBe("");
    expect(after.nationalIdHash).toBeNull();
    expect(after.pinHash).toBeNull();
    expect(after.fullName).toMatch(/^Erased member /);

    // The whole point of the split: the arithmetic survives the person.
    expect(await prisma.ledgerEntry.count({ where: { memberId } })).toBe(ledgerBefore);
  });

  it("is idempotent, so a retry does not read as a failure", async () => {
    const response = await request(app)
      .post(`/api/v1/members/${memberId}/erase`)
      .set("Cookie", adminCookies)
      // Sent with the name the caller ORIGINALLY held, which is what a real
      // retry would carry — the record now shows a pseudonym.
      .send({ confirmFullName: memberName })
      .expect(200);
    expect(response.body.data.alreadyErased).toBe(true);
  });

  it("records the erasure without recording what was erased", async () => {
    const event = await prisma.auditEvent.findFirst({
      where: { type: "PERSONAL_DATA_ERASED", entityId: memberId },
      orderBy: { createdAt: "desc" }
    });
    expect(event).not.toBeNull();
    // Logging the values would defeat the erasure it is recording.
    expect(event!.payloadJson).not.toContain(memberName);
  });
});
