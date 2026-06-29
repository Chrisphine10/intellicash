import request from "supertest";
import { beforeAll, describe, expect, it, afterAll } from "vitest";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

describe("admin-only management API access", () => {
  beforeAll(async () => {
    await seedDatabase();
  }, 30000);

  it("rejects non-admin access to integrations, API keys, and audit workspaces", async () => {
    const partner = await authenticatedAgent("partner@intellicash.co.ke");
    const lender = await authenticatedAgent("lender@intellicash.co.ke");
    const readOnly = await authenticatedAgent("readonly@intellicash.co.ke");

    await partner.get("/api/v1/integrations/health").expect(403);
    await partner.get("/api/v1/integrations/credentials").expect(403);
    await lender.get("/api/v1/api-keys").expect(403);
    await readOnly.get("/api/v1/audit/events").expect(403);
    await partner.get("/api/v1/intelliaudit/overview").expect(403);
  });

  it("allows admins to manage integrations, API keys, and audits", async () => {
    const admin = await authenticatedAgent();

    await admin.get("/api/v1/integrations/health").expect(200);
    await admin.get("/api/v1/integrations/credentials").expect(200);
    await admin.get("/api/v1/api-keys").expect(200);
    await admin.get("/api/v1/audit/events").expect(200);
    await admin.get("/api/v1/intelliaudit/overview").expect(200);
  });

  it("keeps authenticated Google Maps public config available for operational pages", async () => {
    const group = await authenticatedAgent("group@intellicash.co.ke");

    const config = await group.get("/api/v1/integrations/GOOGLE_MAPS/public-config").expect(200);
    expect(config.body.data.provider).toBe("GOOGLE_MAPS");
  });
});

async function authenticatedAgent(email = "admin@intellicash.co.ke") {
  const agent = request.agent(app);
  await agent
    .post("/api/v1/auth/login")
    .send({
      email,
      password: "IntellicashDemo#2026"
    })
    .expect(200);

  return agent;
}

afterAll(async () => {
  await prisma.$disconnect();
});
