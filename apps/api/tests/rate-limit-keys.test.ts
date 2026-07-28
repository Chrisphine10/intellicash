import { describe, expect, it } from "vitest";
import { normalisePhone } from "../src/lib/phone";

/**
 * The limiters themselves are skipped under NODE_ENV=test — the suite drives
 * login and join requests hard on purpose, and throttling it would make every
 * other test flaky. What is worth pinning here is the KEY, because a weak key
 * is how a limiter gets bypassed without anything appearing to be wrong.
 *
 * The live behaviour (throttling after the limit, bystanders unaffected) was
 * verified against a running server; see the rate-limit middleware for why
 * nothing keys on IP.
 */
describe("rate limit keys", () => {
  it("puts every written form of one number in the same bucket", () => {
    // Otherwise an attacker alternates formats and multiplies their allowance:
    // five spellings would mean five times the password attempts.
    const forms = [
      "0700000205",
      "+254700000205",
      "254700000205",
      "+254 (0)700 000 205",
      "00254700000205"
    ];
    const keys = new Set(forms.map((form) => `login:${normalisePhone(form)}`));
    expect(keys.size).toBe(1);
  });

  it("keeps two different people in different buckets", () => {
    // Throttling one account must never lock out another member signing in
    // from the same handset or the same meeting hotspot.
    expect(`login:${normalisePhone("0700000205")}`).not.toBe(
      `login:${normalisePhone("0700000201")}`
    );
  });

  it("buckets malformed identifiers together rather than exempting them", () => {
    // An empty key must not become an unlimited allowance.
    const key = (value: string) => `login:${normalisePhone(value) || "unknown"}`;
    expect(key("")).toBe("login:unknown");
    expect(key("---")).toBe("login:unknown");
  });
});
