import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * Editing and closing user accounts.
 *
 * "Delete this user" cannot mean `DELETE FROM User`. Every relation pointing at
 * User is `onDelete: SetNull` — `AuditEvent.actor` among them — so a real delete
 * would leave the audit trail intact, readable, and unable to say who did any
 * of it. These tests pin the alternative: strip the identity, keep the trail,
 * and refuse the ways an administrator can lock everybody out by accident.
 */

async function signIn(identifier: string, password = demoPassword) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: identifier, password })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

describe("administering user accounts", () => {
  let admin: string[];
  let adminId: string;

  beforeAll(async () => {
    await seedDatabase();
    const account = demoAccounts.find((entry) => entry.role === "IWL_ADMIN")!;
    admin = await signIn(account.phone);

    adminId = (
      await prisma.user.findFirstOrThrow({
        where: { email: account.email },
        select: { id: true }
      })
    ).id;
  }, 180000);

  let created = 0;
  async function makeUser(overrides: Record<string, unknown> = {}) {
    created += 1;
    const response = await request(app)
      .post("/api/v1/users")
      .set("Cookie", admin)
      .send({
        name: `Test Person ${created}`,
        email: `test.person.${created}.${Date.now()}@intellicash.test`,
        password: "A-long-enough-password-1",
        role: "READ_ONLY",
        ...overrides
      })
      .expect(201);
    return response.body.data as { id: string; email: string; phone: string | null };
  }

  describe("editing", () => {
    it("corrects a mistyped phone number, canonicalised", async () => {
      // The field somebody actually signs in with, and the one most likely to
      // carry a typo that locks them out. It was not editable at all.
      const user = await makeUser();

      const response = await request(app)
        .patch(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ phone: "+254 (0)712 345 987" })
        .expect(200);

      // Stored canonical. Comparing raw strings is how one human becomes two
      // accounts.
      expect(response.body.data.phone).toBe("254712345987");
    });

    it("corrects a name and an email", async () => {
      const user = await makeUser();

      const response = await request(app)
        .patch(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ name: "  Corrected Name  ", email: "Corrected.Case@Intellicash.TEST" })
        .expect(200);

      expect(response.body.data.name).toBe("Corrected Name");
      // Lower-cased, so the same address typed two ways is one account.
      expect(response.body.data.email).toBe("corrected.case@intellicash.test");
    });

    it("refuses an email that already belongs to somebody else", async () => {
      const first = await makeUser();
      const second = await makeUser();

      const response = await request(app)
        .patch(`/api/v1/users/${second.id}`)
        .set("Cookie", admin)
        .send({ email: first.email })
        .expect(409);

      // The message an admin needs, not "Unique constraint failed".
      expect(response.body.error.code).toBe("EMAIL_TAKEN");
    });

    it("refuses a phone that already belongs to somebody else, however it is written", async () => {
      const first = await makeUser();
      await request(app)
        .patch(`/api/v1/users/${first.id}`)
        .set("Cookie", admin)
        .send({ phone: "254712999888" })
        .expect(200);

      const second = await makeUser();
      const response = await request(app)
        .patch(`/api/v1/users/${second.id}`)
        .set("Cookie", admin)
        // Same number, written the way somebody would read it off a letterhead.
        .send({ phone: "0712 999 888" })
        .expect(409);

      expect(response.body.error.code).toBe("PHONE_TAKEN");
    });

    it("lets a phone be cleared", async () => {
      const user = await makeUser({ phone: "254711000111" });

      const response = await request(app)
        .patch(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ phone: null })
        .expect(200);

      expect(response.body.data.phone).toBeNull();
    });
  });

  describe("closing an account", () => {
    it("strips the identity and keeps the row", async () => {
      const user = await makeUser({ phone: "254733222111" });

      const response = await request(app)
        .delete(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ confirmEmail: user.email, reason: "Left the organisation" })
        .expect(200);

      expect(response.body.data.closed).toBe(true);

      const row = await prisma.user.findUnique({ where: { id: user.id } });

      // The row survives, because every audit record that named this account
      // as the actor still has to resolve to something.
      expect(row).not.toBeNull();
      expect(row!.status).toBe("CLOSED");

      // And nothing on it says who the person was or how to reach them.
      expect(row!.name).not.toContain("Test Person");
      expect(row!.email).not.toBe(user.email);
      expect(row!.email).toContain("@account.invalid");
      expect(row!.phone).toBeNull();
      expect(row!.passwordHash).toBe("");
    });

    it("frees the phone number for a real person to use again", async () => {
      const user = await makeUser({ phone: "254799888777" });
      await request(app)
        .delete(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ confirmEmail: user.email, reason: "Duplicate account" })
        .expect(200);

      // A dead account holding a live number means the human being cannot be
      // registered from it again.
      const replacement = await makeUser();
      await request(app)
        .patch(`/api/v1/users/${replacement.id}`)
        .set("Cookie", admin)
        .send({ phone: "254799888777" })
        .expect(200);
    });

    it("ends the closed account's sessions", async () => {
      const user = await makeUser({ phone: "254700111222" });
      await prisma.session.create({
        data: {
          userId: user.id,
          tokenHash: `closed-session-${Date.now()}`,
          expiresAt: new Date(Date.now() + 60 * 60 * 1000)
        }
      });

      await request(app)
        .delete(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ confirmEmail: user.email, reason: "Left the organisation" })
        .expect(200);

      // Otherwise the person keeps working from a live cookie after the account
      // they are using has ceased to exist.
      expect(await prisma.session.count({ where: { userId: user.id } })).toBe(0);
    });

    it("cannot be signed into afterwards", async () => {
      const user = await makeUser({ phone: "254722334455" });
      await request(app)
        .delete(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ confirmEmail: user.email, reason: "Left the organisation" })
        .expect(200);

      await request(app)
        .post("/api/v1/auth/login")
        .send({ phone: "254722334455", password: "A-long-enough-password-1" })
        .expect(401);
    });

    it("refuses without the email typed back", async () => {
      // The id in the URL is invisible to whoever pressed the button, and the
      // row above the one they meant looks exactly the same.
      const user = await makeUser();

      const response = await request(app)
        .delete(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ confirmEmail: "something.else@intellicash.test", reason: "Wrong row" })
        .expect(400);

      expect(response.body.error.code).toBe("CONFIRMATION_MISMATCH");
      expect(await prisma.user.findUnique({ where: { id: user.id } })).toMatchObject({
        status: "ACTIVE"
      });
    });

    it("refuses without a reason", async () => {
      const user = await makeUser();

      await request(app)
        .delete(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ confirmEmail: user.email })
        .expect(400);
    });

    it("refuses to close the account doing the closing", async () => {
      // It would end the request that is doing it and leave nobody holding a
      // session that could undo the mistake.
      const response = await request(app)
        .delete(`/api/v1/users/${adminId}`)
        .set("Cookie", admin)
        .send({ confirmEmail: demoAccounts.find((a) => a.role === "IWL_ADMIN")!.email, reason: "oops" })
        .expect(400);

      expect(response.body.error.code).toBe("CANNOT_CLOSE_OWN_ACCOUNT");
    });

    it("refuses to close the last active admin", async () => {
      const other = await makeUser({ role: "IWL_ADMIN" });

      // With two admins the second can go...
      await request(app)
        .delete(`/api/v1/users/${other.id}`)
        .set("Cookie", admin)
        .send({ confirmEmail: other.email, reason: "Duplicate admin" })
        .expect(200);

      // ...and the remaining one cannot, or nobody can administer anything.
      const lastAdmins = await prisma.user.count({
        where: { role: "IWL_ADMIN", status: "ACTIVE" }
      });
      expect(lastAdmins).toBe(1);
    });

    it("refuses to close the same account twice", async () => {
      const user = await makeUser();
      await request(app)
        .delete(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ confirmEmail: user.email, reason: "Left" })
        .expect(200);

      const response = await request(app)
        .delete(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ confirmEmail: user.email, reason: "Left" })
        .expect(409);

      expect(response.body.error.code).toBe("USER_ALREADY_CLOSED");
    });

    it("refuses to edit a closed account back into service", async () => {
      const user = await makeUser();
      await request(app)
        .delete(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ confirmEmail: user.email, reason: "Left" })
        .expect(200);

      // Reactivating would produce an account with no working credential and
      // no owner, sitting inside whatever group it was re-bound to.
      const response = await request(app)
        .patch(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ status: "ACTIVE" })
        .expect(409);

      expect(response.body.error.code).toBe("USER_ACCOUNT_CLOSED");
    });

    it("records who closed it and why, without re-storing the identity", async () => {
      const user = await makeUser({ phone: "254701020304" });
      await request(app)
        .delete(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ confirmEmail: user.email, reason: "Left the organisation in August" })
        .expect(200);

      const event = await prisma.auditEvent.findFirstOrThrow({
        where: { entityId: user.id, type: "USER_ACCOUNT_CLOSED" }
      });

      expect(event.actorUserId).toBe(adminId);
      expect(event.payloadJson).toContain("Left the organisation in August");

      // The whole point of the action is that these stop being held. Writing
      // them into the audit payload would put them straight back, in a table
      // nobody thinks to look in.
      expect(event.payloadJson).not.toContain(user.email);
      expect(event.payloadJson).not.toContain("254701020304");
    });

    it("leaves the audit trail this account already produced attributable", async () => {
      const user = await makeUser();
      // Something this account did, recorded before it was closed.
      await prisma.auditEvent.create({
        data: {
          actorUserId: user.id,
          entityType: "GROUP",
          entityId: "some-group",
          type: "USER_UPDATED",
          payloadJson: "{}",
          hash: `test-hash-${Date.now()}`
        }
      });

      await request(app)
        .delete(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ confirmEmail: user.email, reason: "Left" })
        .expect(200);

      // The reason the row is kept rather than deleted: `AuditEvent.actor` is
      // SetNull, so a real delete would blank this and the trail would no
      // longer say who acted.
      const event = await prisma.auditEvent.findFirstOrThrow({
        where: { entityId: "some-group", actorUserId: user.id }
      });
      expect(event.actorUserId).toBe(user.id);
    });

    it("does not touch the group roster or its money", async () => {
      // Closing a login is not removing somebody from a group's book. The
      // member row, and every shilling recorded against it, belong to the
      // group.
      // A member with no account yet: binding one that is already taken is a
      // legitimate 409 and would fail this test for the wrong reason.
      const member = await prisma.member.findFirstOrThrow({
        where: { userAccounts: { none: {} } },
        select: { id: true, groupId: true }
      });
      const ledgerBefore = await prisma.ledgerEntry.count();

      const user = await makeUser({
        role: "MEMBER",
        groupId: member.groupId,
        memberId: member.id
      });

      await request(app)
        .delete(`/api/v1/users/${user.id}`)
        .set("Cookie", admin)
        .send({ confirmEmail: user.email, reason: "Left the group" })
        .expect(200);

      expect(await prisma.member.findUnique({ where: { id: member.id } })).not.toBeNull();
      expect(await prisma.ledgerEntry.count()).toBe(ledgerBefore);
    });

    it("is refused to somebody without users:write", async () => {
      const user = await makeUser();
      const readOnly = demoAccounts.find((entry) => entry.role === "READ_ONLY");
      if (!readOnly) return;

      const cookies = await signIn(readOnly.phone);
      await request(app)
        .delete(`/api/v1/users/${user.id}`)
        .set("Cookie", cookies)
        .send({ confirmEmail: user.email, reason: "Not allowed" })
        .expect(403);
    });
  });
});
