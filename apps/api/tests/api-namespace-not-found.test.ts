import request from "supertest";
import { describe, expect, it } from "vitest";
import { createApp } from "../src/app";

/**
 * In production one process serves the API and the web app: whatever the API
 * does not match falls through to Next.js. Next waits for a request body, and
 * express.json() has already consumed it, so a POST with a JSON body to an
 * /api/v1 path that does not exist used to hang until the client gave up.
 *
 * The API's namespace must be answered by the API - even in that combined mode,
 * with the fall-through to the web app in place.
 */
describe("the API namespace in the combined (web + API) server", () => {
  const app = createApp({ includeNotFoundHandler: false, servesWebApp: true });
  // Stand-in for Next.js: it answers anything it is handed.
  app.all("*", (_req, res) => {
    res.status(200).send("the web app");
  });

  it("answers an unknown POST with a JSON body itself, promptly, instead of handing it on", async () => {
    const started = Date.now();
    const response = await request(app)
      .post("/api/v1/groups/none/does-not-exist")
      .send({ anything: true })
      .expect(404);

    expect(response.body.error.code).toBe("NOT_FOUND");
    expect(response.text).not.toContain("the web app");
    expect(Date.now() - started).toBeLessThan(2000);
  });

  it("answers an unknown GET the same way", async () => {
    const response = await request(app).get("/api/v1/nothing/here").expect(404);
    expect(response.body.error.code).toBe("NOT_FOUND");
  });

  it("still serves the web app for everything that is not the API", async () => {
    await request(app).get("/login").expect(200, "the web app");
    await request(app).get("/dashboard/groups").expect(200, "the web app");
  });

  it("leaves real API routes alone", async () => {
    // Refused for want of a session (401), not reported as missing (404).
    await request(app).post("/api/v1/groups/none/share-outs").send({}).expect(401);
    await request(app).get("/api/v1/groups/none/restore-bundle").expect(401);
    const health = await request(app).get("/health").expect(200);
    expect(health.body.data.status).toBe("ok");
  });
});
