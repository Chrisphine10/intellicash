import { describe, expect, it } from "vitest";
import { assessDatabaseDurability, assertDurableDatabase } from "../src/lib/storage-safety";

const PROD = { nodeEnv: "production" };

/**
 * The platform holds savings ledgers. A production service whose database file
 * sits on an ephemeral filesystem loses a cycle's contributions on the next
 * deploy, and nobody finds out until a treasurer opens the app at a meeting.
 */
describe("refusing a production database that will not survive a restart", () => {
  it("rejects a file under /tmp", () => {
    const verdict = assessDatabaseDurability("file:/tmp/intellicash.db", PROD);
    expect(verdict.safe).toBe(false);
    if (!verdict.safe) expect(verdict.reason).toContain("/tmp/intellicash.db");
  });

  it("rejects a relative path, which lives inside the build", () => {
    const verdict = assessDatabaseDurability("file:./dev.db", PROD);
    expect(verdict.safe).toBe(false);
    if (!verdict.safe) expect(verdict.reason).toContain("replaced on the next deploy");
  });

  it("accepts a file on a mounted disk", () => {
    expect(assessDatabaseDurability("file:/var/data/intellicash.db", PROD).safe).toBe(true);
  });

  it("leaves a managed database alone", () => {
    // Durability belongs to the provider, not to this check.
    expect(
      assessDatabaseDurability("postgresql://user:pw@host:5432/intellicash", PROD).safe
    ).toBe(true);
  });

  it("does not interfere outside production", () => {
    // Local development runs on ./dev.db and must stay unaffected.
    expect(assessDatabaseDurability("file:./dev.db", { nodeEnv: "development" }).safe).toBe(true);
    expect(assessDatabaseDurability("file:/tmp/x.db", { nodeEnv: "test" }).safe).toBe(true);
  });

  it("lets a demo opt out, but only deliberately", () => {
    expect(
      assessDatabaseDurability("file:/tmp/demo.db", { ...PROD, allowEphemeral: true }).safe
    ).toBe(true);
  });

  it("throws with an actionable message, naming the opt-out", () => {
    expect(() => assertDurableDatabase("file:/tmp/intellicash.db", PROD)).toThrowError(
      /ALLOW_EPHEMERAL_DATABASE/
    );
    expect(() =>
      assertDurableDatabase("file:/var/data/intellicash.db", PROD)
    ).not.toThrow();
  });
});
