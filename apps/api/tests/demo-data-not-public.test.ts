import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * Demo data must not appear on the public site.
 *
 * The seeded partner and programme exist so the platform can be explored, but
 * `publicStatus: "ONGOING"` was the only filter the public endpoints applied —
 * so the sample project and its partner were listed on the landing page to
 * every visitor. Everything public hangs off a programme, so these check the
 * programme list, a programme fetched by slug, and the storefront.
 */
describe("demo data stays out of the public surfaces", () => {
  let demoProgrammeSlug: string;

  beforeAll(async () => {
    await seedDatabase();
    const demo = await prisma.programme.findFirstOrThrow({ where: { isDemo: true } });
    demoProgrammeSlug = demo.publicSlug ?? "";
  }, 180000);

  it("marks the seeded sample programme as demo", () => {
    // If the seed stops marking it, everything below passes for the wrong
    // reason — there would simply be no demo data to leak.
    expect(demoProgrammeSlug).toBeTruthy();
  });

  it("leaves it out of the public programme list", async () => {
    const response = await request(app).get("/api/v1/public/programmes").expect(200);
    const slugs = response.body.data.map((p: { publicSlug: string }) => p.publicSlug);
    expect(slugs).not.toContain(demoProgrammeSlug);
  });

  it("will not serve it by slug either", async () => {
    // Excluding it from the list but serving it on a direct link would leave
    // the data public to anyone who has the URL.
    await request(app)
      .get(`/api/v1/public/programmes/${encodeURIComponent(demoProgrammeSlug)}`)
      .expect(404);
  });

  it("keeps its partner off the public site with it", async () => {
    // A partner is only ever exposed publicly THROUGH a programme, which is why
    // one flag on the programme is enough.
    const response = await request(app).get("/api/v1/public/programmes").expect(200);
    const body = JSON.stringify(response.body);
    expect(body).not.toContain("FLOURISH VSLA Programme");
  });

  it("keeps its products and agents off the public storefront", async () => {
    const response = await request(app).get("/api/v1/public/intelli-store").expect(200);
    const body = JSON.stringify(response.body.data);
    expect(body).not.toContain(demoProgrammeSlug);
  });

  it("still shows it to someone signed in", async () => {
    // The point of demo data is that it can be explored once you are in. If
    // this fails the fix has gone too far and hidden it from everybody.
    const admin = demoAccounts.find((a) => a.role === "IWL_ADMIN")!;
    const login = await request(app)
      .post("/api/v1/auth/login")
      .send({ phone: admin.phone, password: demoPassword })
      .expect(200);
    const cookie = login.headers["set-cookie"];

    const response = await request(app)
      .get("/api/v1/programmes")
      .set("Cookie", Array.isArray(cookie) ? cookie : [cookie as unknown as string])
      .expect(200);
    expect(JSON.stringify(response.body)).toContain(demoProgrammeSlug);
  });
});
