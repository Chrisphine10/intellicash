import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { modulesForGroup } from "../src/services/module-service";

const app = createApp();

async function signIn(role: string) {
  const account = demoAccounts.find((candidate) => candidate.role === role)!;
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: account.phone, password: demoPassword })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

/**
 * Intelli-Store and Voting are switched on per programme, and both start off.
 *
 * The API refuses a switched-off module itself rather than trusting the UI to
 * hide it: phones already in the field keep calling the endpoints they know.
 * IWL admins are never refused, so they can prepare a module before it opens.
 */
describe("programme modules", () => {
  let groupId: string;
  let programmeIds: string[];
  let admin: string[];
  let groupAccount: string[];
  let partner: string[];

  async function setAll(data: { storeEnabled?: boolean; votingEnabled?: boolean }) {
    await prisma.programme.updateMany({ data });
  }

  beforeAll(async () => {
    await seedDatabase();
    admin = await signIn("IWL_ADMIN");
    groupAccount = await signIn("GROUP_ACCOUNT");
    partner = await signIn("PARTNER_OFFICER");

    const me = await request(app).get("/api/v1/auth/me").set("Cookie", groupAccount).expect(200);
    groupId = me.body.data.groupId;
    const group = await prisma.group.findUniqueOrThrow({
      where: { id: groupId },
      select: { programmeId: true, programmeLinks: { select: { programmeId: true } } }
    });
    programmeIds = [
      ...new Set([group.programmeId, ...group.programmeLinks.map((link) => link.programmeId)].filter(Boolean))
    ] as string[];
    expect(programmeIds.length).toBeGreaterThan(0);
  }, 60000);

  it("starts with both modules off on every programme", async () => {
    const programmes = await prisma.programme.findMany({ select: { storeEnabled: true, votingEnabled: true } });
    expect(programmes.every((programme) => !programme.storeEnabled && !programme.votingEnabled)).toBe(true);
  });

  describe("while switched off", () => {
    beforeAll(() => setAll({ storeEnabled: false, votingEnabled: false }));

    it("tells the group and the account that neither module is on", async () => {
      const group = await request(app).get(`/api/v1/groups/${groupId}`).set("Cookie", groupAccount).expect(200);
      expect(group.body.data.modules).toEqual({ store: false, voting: false });
      const me = await request(app).get("/api/v1/auth/me").set("Cookie", groupAccount).expect(200);
      expect(me.body.data.modules).toEqual({ store: false, voting: false });
    });

    it("refuses a group's polls and recorded votes", async () => {
      const list = await request(app).get(`/api/v1/groups/${groupId}/polls`).set("Cookie", groupAccount).expect(403);
      expect(list.body.error.code).toBe("MODULE_DISABLED");
      await request(app)
        .post(`/api/v1/groups/${groupId}/polls`)
        .set("Cookie", groupAccount)
        .send({ type: "DECISION", title: "Raise the share value?", options: [{ label: "Yes" }, { label: "No" }] })
        .expect(403);
    });

    it("refuses the store to a group account", async () => {
      const products = await request(app).get("/api/v1/intelli-store/products").set("Cookie", groupAccount).expect(403);
      expect(products.body.error.code).toBe("MODULE_DISABLED");
    });

    it("shows the public storefront nothing", async () => {
      const store = await request(app).get("/api/v1/public/intelli-store").expect(200);
      expect(store.body.data.products).toEqual([]);
      expect(store.body.data.agents).toEqual([]);
    });

    it("refuses a public store request for a programme with the store off", async () => {
      // A live public programme, so nothing but the switch can refuse it.
      await prisma.programme.update({ where: { id: programmeIds[0] }, data: { publicStatus: "ONGOING", isDemo: false } });
      const refused = await request(app)
        .post("/api/v1/public/intelli-store/booking-requests")
        .send({
          programmeId: programmeIds[0],
          serviceType: "Group onboarding",
          customerName: "Wanjiru",
          customerEmail: "wanjiru@example.org",
          phoneNumber: "254700111222",
          county: "Kiambu"
        });
      expect(refused.status, JSON.stringify(refused.body)).toBe(403);
      expect(refused.body.error.code).toBe("MODULE_DISABLED");
    });

    it("still lets an IWL admin prepare the store and look at polls", async () => {
      await request(app).get("/api/v1/intelli-store/products").set("Cookie", admin).expect(200);
      await request(app).get(`/api/v1/groups/${groupId}/polls`).set("Cookie", admin).expect(200);
      const me = await request(app).get("/api/v1/auth/me").set("Cookie", admin).expect(200);
      expect(me.body.data.modules).toEqual({ store: true, voting: true });
    });

    it("keeps recorded vote history readable", async () => {
      await request(app).get(`/api/v1/groups/${groupId}/votes`).set("Cookie", groupAccount).expect(200);
    });
  });

  describe("switching a module on", () => {
    it("is for IWL admins only", async () => {
      await request(app)
        .patch(`/api/v1/programmes/${programmeIds[0]}/modules`)
        .set("Cookie", partner)
        .send({ votingEnabled: true })
        .expect(403);
    });

    it("turns voting on for the programme's groups, and is audited", async () => {
      await setAll({ storeEnabled: false, votingEnabled: false });
      const saved = await request(app)
        .patch(`/api/v1/programmes/${programmeIds[0]}/modules`)
        .set("Cookie", admin)
        .send({ votingEnabled: true })
        .expect(200);
      expect(saved.body.data).toMatchObject({ votingEnabled: true, storeEnabled: false });

      await request(app).get(`/api/v1/groups/${groupId}/polls`).set("Cookie", groupAccount).expect(200);
      expect(await modulesForGroup(groupId)).toEqual({ store: false, voting: true });

      const audit = await prisma.auditEvent.findFirst({
        where: { entityType: "PROGRAMME", entityId: programmeIds[0], type: "PROGRAMME_UPDATED" },
        orderBy: { createdAt: "desc" }
      });
      expect(JSON.parse(audit!.payloadJson)).toMatchObject({ change: "modules", after: { votingEnabled: true } });
    });

    it("refuses an empty switch request", async () => {
      await request(app)
        .patch(`/api/v1/programmes/${programmeIds[0]}/modules`)
        .set("Cookie", admin)
        .send({})
        .expect(400);
    });

    it("opens the store to the group once its programme has it on", async () => {
      await setAll({ storeEnabled: false });
      await prisma.programme.update({ where: { id: programmeIds[0] }, data: { storeEnabled: true } });
      await request(app).get("/api/v1/intelli-store/products").set("Cookie", groupAccount).expect(200);
      const group = await request(app).get(`/api/v1/groups/${groupId}`).set("Cookie", groupAccount).expect(200);
      expect(group.body.data.modules.store).toBe(true);
    });

    it("a module on for some OTHER programme does not reach this group", async () => {
      await setAll({ storeEnabled: false, votingEnabled: false });
      const other = await prisma.programme.findFirst({ where: { id: { notIn: programmeIds } }, select: { id: true } });
      if (!other) return; // the seed has only this group's programmes
      await prisma.programme.update({ where: { id: other.id }, data: { storeEnabled: true, votingEnabled: true } });
      expect(await modulesForGroup(groupId)).toEqual({ store: false, voting: false });
    });
  });
});
