import request from "supertest";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";

const app = createApp();

async function signIn(role: string) {
  const account = demoAccounts.find((candidate) => candidate.role === role)!;
  const response = await request(app).post("/api/v1/auth/login").send({ phone: account.phone, password: demoPassword }).expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

/**
 * Which map the console draws: Google Maps or OpenStreetMap, chosen by an
 * IWL admin. OpenStreetMap needs no key, so it is always available; Google
 * only while its integration is switched on and has a key.
 */
describe("map provider", () => {
  let before: { value: string } | null;
  let googleEnabled: boolean | null = null;

  beforeAll(async () => {
    before = await prisma.platformSetting.findUnique({ where: { key: "MAP_PROVIDER" } });
    const google = await prisma.integrationConfig.findUnique({ where: { provider: "GOOGLE_MAPS" } });
    googleEnabled = google?.enabled ?? null;
  });

  afterAll(async () => {
    if (before) {
      await prisma.platformSetting.update({ where: { key: "MAP_PROVIDER" }, data: { value: before.value } });
    } else {
      await prisma.platformSetting.deleteMany({ where: { key: "MAP_PROVIDER" } });
    }
    if (googleEnabled !== null) {
      await prisma.integrationConfig.update({ where: { provider: "GOOGLE_MAPS" }, data: { enabled: googleEnabled } });
    }
  });

  it("is readable by anyone signed in, and always offers OpenStreetMap", async () => {
    const response = await request(app).get("/api/v1/integrations/map-config").set("Cookie", await signIn("MEMBER")).expect(200);
    expect(["GOOGLE_MAPS", "OPENSTREETMAP"]).toContain(response.body.data.provider);
    expect(response.body.data.openStreetMap.tileUrl).toMatch(/^https:\/\/tile\.openstreetmap\.org\//);
    await request(app).get("/api/v1/integrations/map-config").expect(401);
  });

  it("is changed by an IWL admin only", async () => {
    const admin = await signIn("IWL_ADMIN");
    const saved = await request(app)
      .put("/api/v1/integrations/map-config")
      .set("Cookie", admin)
      .send({ provider: "OPENSTREETMAP" })
      .expect(200);
    expect(saved.body.data).toMatchObject({ provider: "OPENSTREETMAP", chosen: "OPENSTREETMAP" });
    expect(await prisma.auditEvent.count({ where: { type: "MAP_PROVIDER_CHANGED" } })).toBeGreaterThan(0);

    const partner = await signIn("PARTNER_OFFICER");
    await request(app).put("/api/v1/integrations/map-config").set("Cookie", partner).send({ provider: "OPENSTREETMAP" }).expect(403);
    await request(app).put("/api/v1/integrations/map-config").set("Cookie", admin).send({ provider: "BING" }).expect(400);
  });

  it("does not use or offer Google Maps while its integration is switched off", async () => {
    const admin = await signIn("IWL_ADMIN");
    // Reading the config creates the integration row if it is missing.
    await request(app).get("/api/v1/integrations/map-config").set("Cookie", admin).expect(200);
    await prisma.integrationConfig.update({ where: { provider: "GOOGLE_MAPS" }, data: { enabled: false } });

    const read = await request(app).get("/api/v1/integrations/map-config").set("Cookie", admin).expect(200);
    expect(read.body.data.google).toMatchObject({ configured: false, apiKey: null });
    expect(read.body.data.provider).toBe("OPENSTREETMAP");

    const refused = await request(app)
      .put("/api/v1/integrations/map-config")
      .set("Cookie", admin)
      .send({ provider: "GOOGLE_MAPS" })
      .expect(400);
    expect(refused.body.error.code).toBe("GOOGLE_MAPS_NOT_CONFIGURED");
  });
});
