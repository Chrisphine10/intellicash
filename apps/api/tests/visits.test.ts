import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { PIN_LOCKOUT_THRESHOLD } from "../src/services/visit-service";

const app = createApp();

/**
 * Field visits.
 *
 * The two properties worth the most here are idempotency and scope. A phone in
 * the field retries on every reconnect, so a submit that is not idempotent
 * quietly doubles a group's visit count and corrupts coverage reporting; and an
 * agent who can reach a group outside their caseload can read free text about
 * named individuals they have no business seeing.
 */

async function signIn(identifier: string, password = demoPassword) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: identifier, password })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

describe("group visits", () => {
  let adminCookies: string[];
  let agentCookies: string[];
  let groupCookies: string[];
  /** In the demo agent's caseload. */
  let agentGroupId: string;
  /** Outside it. */
  let otherGroupId: string;

  beforeAll(async () => {
    await seedDatabase();

    const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
    const agent = demoAccounts.find((account) => account.role === "VILLAGE_AGENT")!;
    const groupAccount = demoAccounts.find((account) => account.role === "GROUP_ACCOUNT")!;

    adminCookies = await signIn(admin.phone);
    agentCookies = await signIn(agent.phone);
    groupCookies = await signIn(groupAccount.phone);

    const agentUser = await prisma.user.findFirst({
      where: { role: "VILLAGE_AGENT" },
      select: { villageAgentId: true }
    });
    const caseload = await prisma.group.findMany({
      where: { villageAgentId: agentUser!.villageAgentId! },
      select: { id: true },
      orderBy: { createdAt: "asc" }
    });
    agentGroupId = caseload[0]!.id;

    // The seed puts every group in the demo agent's caseload, so the
    // out-of-scope case has to be created rather than found. Detaching one is
    // closer to reality than inventing a group: agents are reassigned, and a
    // group they no longer hold must disappear from their view immediately.
    const detached =
      (await prisma.group.findFirst({
        where: { villageAgentId: null },
        select: { id: true }
      })) ??
      (await prisma.group.update({
        where: { id: caseload.at(-1)!.id },
        data: { villageAgentId: null },
        select: { id: true }
      }));
    otherGroupId = detached.id;

    expect(otherGroupId).not.toBe(agentGroupId);

    // A registered meeting point, so the geofence has something to judge.
    await prisma.group.update({
      where: { id: agentGroupId },
      data: { gpsLatitude: -0.5389, gpsLongitude: 37.4575, gpsRadiusMeters: 50 }
    });
    await prisma.groupVisit.deleteMany({});
    await prisma.groupVisitPin.deleteMany({});
  }, 120000);

  describe("the visit PIN belongs to the group, not the visitor", () => {
    it("lets the group's own account set it", async () => {
      await request(app)
        .put(`/api/v1/groups/${agentGroupId}/visit-pin`)
        .set("Cookie", adminCookies)
        .send({ pin: "8317" })
        .expect(200);

      const status = await request(app)
        .get(`/api/v1/groups/${agentGroupId}/visit-pin`)
        .set("Cookie", adminCookies)
        .expect(200);
      expect(status.body.data.configured).toBe(true);
    });

    it("refuses to let the visiting agent set it", async () => {
      // The one rule that makes the attestation mean anything: an agent who
      // could set the PIN could confirm a visit they never made.
      const response = await request(app)
        .put(`/api/v1/groups/${agentGroupId}/visit-pin`)
        .set("Cookie", agentCookies)
        .send({ pin: "4821" })
        .expect(403);
      expect(response.body.error.code).toBe("AGENT_CANNOT_SET_VISIT_PIN");
    });

    it("never returns the PIN or its hash", async () => {
      const response = await request(app)
        .get(`/api/v1/groups/${agentGroupId}/visit-pin`)
        .set("Cookie", agentCookies)
        .expect(200);
      const body = JSON.stringify(response.body);
      expect(body).not.toContain("8317");
      expect(body).not.toContain("pinHash");
      expect(body).not.toContain("$2");
    });

    it("rejects a guessable PIN", async () => {
      // A group talked through this on the phone reaches for 1234 every time.
      const response = await request(app)
        .put(`/api/v1/groups/${agentGroupId}/visit-pin`)
        .set("Cookie", adminCookies)
        .send({ pin: "1234" })
        .expect(400);
      expect(response.body.error.code).toBe("GUESSABLE_VISIT_PIN");
    });

    it("rejects anything that is not four digits", async () => {
      for (const pin of ["123", "12345", "abcd", ""]) {
        await request(app)
          .put(`/api/v1/groups/${agentGroupId}/visit-pin`)
          .set("Cookie", adminCookies)
          .send({ pin })
          .expect(400);
      }
    });
  });

  describe("verifying the PIN", () => {
    it("accepts the right PIN", async () => {
      const response = await request(app)
        .post(`/api/v1/groups/${agentGroupId}/visit-pin/verify`)
        .set("Cookie", agentCookies)
        .send({ pin: "8317" })
        .expect(200);
      expect(response.body.data.verified).toBe(true);
    });

    it("locks after repeated wrong attempts, then unlocks on a new PIN", async () => {
      // Four digits is ten thousand combinations — walkable unattended without
      // a lockout. The counter lives in the database so a restart cannot clear
      // it.
      for (let attempt = 0; attempt < PIN_LOCKOUT_THRESHOLD - 1; attempt += 1) {
        await request(app)
          .post(`/api/v1/groups/${agentGroupId}/visit-pin/verify`)
          .set("Cookie", agentCookies)
          .send({ pin: "0001" })
          .expect(401);
      }

      const locked = await request(app)
        .post(`/api/v1/groups/${agentGroupId}/visit-pin/verify`)
        .set("Cookie", agentCookies)
        .send({ pin: "0001" })
        .expect(423);
      expect(locked.body.error.code).toBe("VISIT_PIN_LOCKED");

      // Even the right PIN is refused while locked.
      await request(app)
        .post(`/api/v1/groups/${agentGroupId}/visit-pin/verify`)
        .set("Cookie", agentCookies)
        .send({ pin: "8317" })
        .expect(423);

      // Setting a new PIN clears it: the group has demonstrably regained
      // control, so continuing to punish the earlier guessing helps nobody.
      await request(app)
        .put(`/api/v1/groups/${agentGroupId}/visit-pin`)
        .set("Cookie", adminCookies)
        .send({ pin: "8317" })
        .expect(200);
      await request(app)
        .post(`/api/v1/groups/${agentGroupId}/visit-pin/verify`)
        .set("Cookie", agentCookies)
        .send({ pin: "8317" })
        .expect(200);
    });

    it("says so when no PIN has been set rather than just failing", async () => {
      const response = await request(app)
        .post(`/api/v1/groups/${otherGroupId}/visit-pin/verify`)
        .set("Cookie", adminCookies)
        .send({ pin: "8317" })
        .expect(409);
      expect(response.body.error.code).toBe("VISIT_PIN_NOT_SET");
    });
  });

  describe("submitting a visit is idempotent", () => {
    const clientRequestId = "visit-11111111-2222-3333-4444-555555555555";

    it("creates the visit once", async () => {
      const response = await request(app)
        .post(`/api/v1/groups/${agentGroupId}/visits`)
        .set("Cookie", agentCookies)
        .send({
          clientRequestId,
          visitType: "FOLLOW_UP",
          startedAt: new Date().toISOString(),
          location: { latitude: -0.5389, longitude: 37.4575, accuracyM: 8 }
        })
        .expect(201);

      expect(response.body.data.created).toBe(true);
      expect(response.body.data.visit.location.withinGeofence).toBe(true);
      expect(response.body.data.visit.location.outcome).toBe("WITHIN_GEOFENCE");
    });

    it("returns the same visit on a retry, with 200 and not 409", async () => {
      // A 409 would read as failure to a phone that retries on reconnect, and
      // it would retry forever.
      const response = await request(app)
        .post(`/api/v1/groups/${agentGroupId}/visits`)
        .set("Cookie", agentCookies)
        .send({
          clientRequestId,
          visitType: "FOLLOW_UP",
          startedAt: new Date().toISOString()
        })
        .expect(200);

      expect(response.body.data.created).toBe(false);
      const count = await prisma.groupVisit.count({ where: { clientRequestId } });
      expect(count).toBe(1);
    });

    it("survives concurrent double submission", async () => {
      // Two retries racing can both pass the existence check; the unique index
      // is the real guard and losing the race is a success, not an error.
      const raceId = "visit-race-0000-1111-2222-333333333333";
      const send = () =>
        request(app)
          .post(`/api/v1/groups/${agentGroupId}/visits`)
          .set("Cookie", agentCookies)
          .send({ clientRequestId: raceId, startedAt: new Date().toISOString() });

      const [a, b] = await Promise.all([send(), send()]);
      expect([a.status, b.status].sort()).toEqual([200, 201]);
      expect(await prisma.groupVisit.count({ where: { clientRequestId: raceId } })).toBe(1);
    });

    it("refuses to let one reference mean two different groups", async () => {
      const response = await request(app)
        .post(`/api/v1/groups/${otherGroupId}/visits`)
        .set("Cookie", adminCookies)
        .send({ clientRequestId, startedAt: new Date().toISOString() })
        .expect(409);
      expect(response.body.error.code).toBe("VISIT_REQUEST_ID_REUSED");
    });
  });

  describe("the server decides where the visit happened", () => {
    it("marks a visit filed from far away as outside, whatever the client says", async () => {
      const response = await request(app)
        .post(`/api/v1/groups/${agentGroupId}/visits`)
        .set("Cookie", agentCookies)
        .send({
          clientRequestId: `visit-far-${Date.now()}`,
          startedAt: new Date().toISOString(),
          // Nairobi, ~120 km from the group's registered point.
          location: { latitude: -1.2864, longitude: 36.8172, accuracyM: 10 },
          withinGeofence: true,
          distanceFromGroupM: 0
        })
        .expect(201);

      expect(response.body.data.visit.location.withinGeofence).toBe(false);
      expect(response.body.data.visit.location.outcome).toBe("OUTSIDE_GEOFENCE");
      expect(response.body.data.visit.location.distanceFromGroupM).toBeGreaterThan(100_000);
      expect(response.body.data.visit.authenticityFlags).toContain("FAR_FROM_GROUP");
    });

    it("accepts a visit with no fix rather than blocking it", async () => {
      // A meeting under a tin roof in a valley must still be filable.
      const response = await request(app)
        .post(`/api/v1/groups/${agentGroupId}/visits`)
        .set("Cookie", agentCookies)
        .send({
          clientRequestId: `visit-nofix-${Date.now()}`,
          startedAt: new Date().toISOString(),
          locationNote: "No signal in the valley"
        })
        .expect(201);
      expect(response.body.data.visit.location.outcome).toBe("NO_DEVICE_FIX");
    });
  });

  describe("scope", () => {
    it("hides a group outside the agent's caseload behind a 404", async () => {
      // 404 and not 403: "forbidden" confirms the group exists to someone who
      // should not know that.
      await request(app)
        .post(`/api/v1/groups/${otherGroupId}/visits`)
        .set("Cookie", agentCookies)
        .send({ clientRequestId: `visit-scope-${Date.now()}`, startedAt: new Date().toISOString() })
        .expect(404);

      await request(app)
        .get(`/api/v1/groups/${otherGroupId}/visits`)
        .set("Cookie", agentCookies)
        .expect(404);
    });

    it("narrows the cross-group list to what the caller may see", async () => {
      // The admin console reads one list rather than a request per group. The
      // same route serves an agent, so the scope has to hold here too — this
      // is the endpoint most likely to leak another caseload's visits.
      const outsideVisit = await request(app)
        .post(`/api/v1/groups/${otherGroupId}/visits`)
        .set("Cookie", adminCookies)
        .send({ clientRequestId: `visit-other-${Date.now()}`, startedAt: new Date().toISOString() })
        .expect(201);

      const asAgent = await request(app)
        .get("/api/v1/visits")
        .set("Cookie", agentCookies)
        .expect(200);
      const agentIds = asAgent.body.data.visits.map((v: { id: string }) => v.id);
      expect(agentIds).not.toContain(outsideVisit.body.data.visit.id);

      const asAdmin = await request(app)
        .get("/api/v1/visits")
        .set("Cookie", adminCookies)
        .expect(200);
      const adminIds = asAdmin.body.data.visits.map((v: { id: string }) => v.id);
      expect(adminIds).toContain(outsideVisit.body.data.visit.id);
    });

    it("filters the cross-group list by group", async () => {
      const response = await request(app)
        .get(`/api/v1/visits?groupId=${agentGroupId}`)
        .set("Cookie", adminCookies)
        .expect(200);
      expect(response.body.data.visits.length).toBeGreaterThan(0);
      for (const visit of response.body.data.visits as { groupId: string }[]) {
        expect(visit.groupId).toBe(agentGroupId);
      }
    });

    it("lists an agent's own visits across their caseload", async () => {
      const response = await request(app)
        .get("/api/v1/agents/me/visits")
        .set("Cookie", agentCookies)
        .expect(200);
      expect(response.body.data.visits.length).toBeGreaterThan(0);
    });

    it("refuses a group account the write side", async () => {
      // The group is visited; it does not conduct visits.
      await request(app)
        .post(`/api/v1/groups/${agentGroupId}/visits`)
        .set("Cookie", groupCookies)
        .send({ clientRequestId: `visit-grp-${Date.now()}`, startedAt: new Date().toISOString() })
        .expect(403);
    });
  });

  describe("amendment", () => {
    it("keeps what was originally reported", async () => {
      const created = await request(app)
        .post(`/api/v1/groups/${agentGroupId}/visits`)
        .set("Cookie", agentCookies)
        .send({
          clientRequestId: `visit-amend-${Date.now()}`,
          startedAt: new Date().toISOString(),
          notes: "Attendance was thin"
        })
        .expect(201);

      const visitId = created.body.data.visit.id;

      await request(app)
        .post(`/api/v1/visits/${visitId}/amend`)
        .set("Cookie", adminCookies)
        .send({ reason: "Corrected after speaking to the secretary", notes: "Attendance was full" })
        .expect(200);

      const detail = await request(app)
        .get(`/api/v1/visits/${visitId}`)
        .set("Cookie", adminCookies)
        .expect(200);

      expect(detail.body.data.visit.notes).toBe("Attendance was full");
      expect(detail.body.data.visit.revision).toBe(2);
      expect(detail.body.data.revisions).toHaveLength(1);

      // The original text survives in the snapshot.
      const snapshot = await prisma.groupVisitRevision.findFirst({ where: { visitId } });
      expect(snapshot!.snapshotJson).toContain("Attendance was thin");
    });

    it("does not let the agent rewrite their own submitted visit", async () => {
      const visit = await prisma.groupVisit.findFirst({ where: { groupId: agentGroupId } });
      await request(app)
        .post(`/api/v1/visits/${visit!.id}/amend`)
        .set("Cookie", agentCookies)
        .send({ reason: "Changed my mind", notes: "Everything was fine" })
        .expect(403);
    });
  });
});
