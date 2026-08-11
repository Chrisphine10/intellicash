import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword, groupDocumentTypes } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { documentStatus, registerSummary } from "../src/domain/group-document-state";

const app = createApp();
const DAY = 24 * 60 * 60 * 1000;

async function signIn(identifier: string, password = demoPassword) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: identifier, password })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

/**
 * The document register.
 *
 * Two properties carry the weight: expiry is DERIVED rather than stored, so it
 * cannot go stale and cannot erase the fact that something was verified; and an
 * agent records what they see but never signs it off.
 */
describe("group document state", () => {
  const now = new Date("2026-08-10T00:00:00Z");

  it("is MISSING when the group does not hold it, whatever else is recorded", () => {
    // An expiry date on a document nobody has is noise.
    const status = documentStatus(
      { presence: "MISSING", verification: "VERIFIED", expiresOn: new Date(now.getTime() - DAY) },
      now
    );
    expect(status.state).toBe("MISSING");
    expect(status.needsAttention).toBe(true);
  });

  it("derives EXPIRED from the date rather than storing it", () => {
    const status = documentStatus(
      { presence: "PRESENT", verification: "VERIFIED", expiresOn: new Date(now.getTime() - DAY) },
      now
    );
    expect(status.state).toBe("EXPIRED");
    // And crucially: the verification survives. This is what makes "how many
    // verified certificates expire this quarter" answerable at all.
    expect(status.verification).toBe("VERIFIED");
  });

  it("is VERIFIED right up to the expiry date", () => {
    const status = documentStatus(
      { presence: "PRESENT", verification: "VERIFIED", expiresOn: new Date(now.getTime() + DAY) },
      now
    );
    expect(status.state).toBe("VERIFIED");
    expect(status.daysUntilExpiry).toBe(1);
  });

  it("flags a document expiring soon before it lapses", () => {
    const status = documentStatus(
      { presence: "PRESENT", verification: "VERIFIED", expiresOn: new Date(now.getTime() + 30 * DAY) },
      now
    );
    expect(status.state).toBe("VERIFIED");
    expect(status.needsAttention).toBe(true);
  });

  it("ranks a rejection above expiry", () => {
    // "We looked at this and did not accept it" is a stronger statement than
    // "it lapsed" — EXPIRED would imply it was once good.
    const status = documentStatus(
      { presence: "PRESENT", verification: "REJECTED", expiresOn: new Date(now.getTime() - DAY) },
      now
    );
    expect(status.state).toBe("REJECTED");
  });

  it("handles a document that never expires", () => {
    const status = documentStatus(
      { presence: "PRESENT", verification: "UNVERIFIED", expiresOn: null },
      now
    );
    expect(status.state).toBe("UNVERIFIED");
    expect(status.daysUntilExpiry).toBeNull();
    expect(status.needsAttention).toBe(false);
  });

  it("counts only verified documents as complete", () => {
    // Merely holding a piece of paper is filing, not compliance.
    const summary = registerSummary([
      documentStatus({ presence: "PRESENT", verification: "VERIFIED" }, now),
      documentStatus({ presence: "PRESENT", verification: "UNVERIFIED" }, now),
      documentStatus({ presence: "MISSING", verification: "UNVERIFIED" }, now),
      documentStatus(
        { presence: "PRESENT", verification: "VERIFIED", expiresOn: new Date(now.getTime() - DAY) },
        now
      )
    ]);

    expect(summary.total).toBe(4);
    expect(summary.verified).toBe(1);
    expect(summary.missing).toBe(1);
    expect(summary.expired).toBe(1);
    expect(summary.percentVerified).toBe(25);
  });
});

describe("group documents API", () => {
  let adminCookies: string[];
  let agentCookies: string[];
  let groupId: string;

  beforeAll(async () => {
    await seedDatabase();
    await prisma.groupDocument.deleteMany({});

    const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
    const agent = demoAccounts.find((account) => account.role === "VILLAGE_AGENT")!;
    adminCookies = await signIn(admin.phone);
    agentCookies = await signIn(agent.phone);

    const agentUser = await prisma.user.findFirst({
      where: { role: "VILLAGE_AGENT" },
      select: { villageAgentId: true }
    });
    const group = await prisma.group.findFirst({
      where: { villageAgentId: agentUser!.villageAgentId! },
      select: { id: true }
    });
    groupId = group!.id;
  }, 180000);

  it("lists every document type, including ones never recorded", async () => {
    // A register that only lists what exists cannot show a gap, and the gap is
    // the entire point of having one.
    const response = await request(app)
      .get(`/api/v1/groups/${groupId}/documents`)
      .set("Cookie", adminCookies)
      .expect(200);

    expect(response.body.data.documents).toHaveLength(groupDocumentTypes.length);
    expect(response.body.data.documents.every((d: { state: string }) => d.state === "MISSING")).toBe(
      true
    );
    expect(response.body.data.summary.percentVerified).toBe(0);
  });

  it("lets an agent record that a document is held", async () => {
    const response = await request(app)
      .put(`/api/v1/groups/${groupId}/documents/REGISTRATION_CERTIFICATE`)
      .set("Cookie", agentCookies)
      .send({ presence: "PRESENT", expiresOn: "2027-01-31T00:00:00.000Z" })
      .expect(200);

    // Present but not yet checked — recording is not blessing.
    expect(response.body.data.state).toBe("UNVERIFIED");
    expect(response.body.data.presence).toBe("PRESENT");
  });

  it("refuses to let the agent verify their own evidence", async () => {
    // Separation of duties: the person who collected the
    // evidence cannot also sign it off.
    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/documents/REGISTRATION_CERTIFICATE/verify`)
      .set("Cookie", agentCookies)
      .send({ verification: "VERIFIED" })
      .expect(403);

    expect(response.body.error.code).toBe("AGENT_CANNOT_VERIFY_DOCUMENT");

    const row = await prisma.groupDocument.findFirstOrThrow({
      where: { groupId, documentType: "REGISTRATION_CERTIFICATE" }
    });
    expect(row.verification).toBe("UNVERIFIED");
  });

  it("lets a reviewer verify it", async () => {
    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/documents/REGISTRATION_CERTIFICATE/verify`)
      .set("Cookie", adminCookies)
      .send({ verification: "VERIFIED" })
      .expect(200);

    expect(response.body.data.state).toBe("VERIFIED");
    expect(response.body.data.verifiedAt).toBeTruthy();
  });

  it("cannot verify a document nobody has recorded as held", async () => {
    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/documents/BANK_MANDATE/verify`)
      .set("Cookie", adminCookies)
      .send({ verification: "VERIFIED" })
      .expect(400);

    expect(response.body.error.code).toBe("DOCUMENT_NOT_HELD");
  });

  it("shows a verified document as expired once its date passes, without losing the verification", async () => {
    await request(app)
      .put(`/api/v1/groups/${groupId}/documents/CONSTITUTION`)
      .set("Cookie", adminCookies)
      .send({ presence: "PRESENT", expiresOn: "2020-01-01T00:00:00.000Z" })
      .expect(200);
    await request(app)
      .post(`/api/v1/groups/${groupId}/documents/CONSTITUTION/verify`)
      .set("Cookie", adminCookies)
      .send({ verification: "VERIFIED" })
      .expect(200);

    const response = await request(app)
      .get(`/api/v1/groups/${groupId}/documents`)
      .set("Cookie", adminCookies)
      .expect(200);

    const constitution = response.body.data.documents.find(
      (d: { documentType: string }) => d.documentType === "CONSTITUTION"
    );
    expect(constitution.state).toBe("EXPIRED");
    // The stored judgement is intact underneath the derived chip.
    expect(constitution.verification).toBe("VERIFIED");
  });

  it("refuses a group outside the agent's caseload", async () => {
    const detached = await prisma.group.findFirst({
      where: { villageAgentId: null },
      select: { id: true }
    });
    const otherGroupId =
      detached?.id ??
      (
        await prisma.group.update({
          where: {
            id: (
              await prisma.group.findFirstOrThrow({
                where: { id: { not: groupId } },
                select: { id: true }
              })
            ).id
          },
          data: { villageAgentId: null },
          select: { id: true }
        })
      ).id;

    // 404, not 403 — "forbidden" confirms the group exists.
    await request(app)
      .get(`/api/v1/groups/${otherGroupId}/documents`)
      .set("Cookie", agentCookies)
      .expect(404);
  });

  it("refuses an unknown document type", async () => {
    await request(app)
      .put(`/api/v1/groups/${groupId}/documents/NOT_A_REAL_DOCUMENT`)
      .set("Cookie", adminCookies)
      .send({ presence: "PRESENT" })
      .expect(404);
  });
});
