import { pbkdf2Sync } from "node:crypto";
import { describe, expect, it } from "vitest";
import { derivePinVerifier, sha256, verifyPinVerifier } from "../src/lib/crypto";

/**
 * The verifier a device checks a PIN against when it is offline.
 *
 * It was `sha256(deviceId:memberId:pin)`. A meeting PIN is four digits, so a
 * complete lookup table over the 10,000 candidates computes in milliseconds:
 * anyone holding a cached verifier blob held every member's meeting key. These
 * tests pin the properties that stopped being true by accident.
 */
describe("derivePinVerifier", () => {
  const deviceId = "device-1";
  const memberId = "member-1";

  it("is no longer a bare SHA-256 digest", () => {
    const verifier = derivePinVerifier(deviceId, memberId, "0427");
    expect(verifier).not.toBe(sha256(`${deviceId}:${memberId}:0427`));
    expect(/^[0-9a-f]{64}$/.test(verifier)).toBe(false);
    expect(verifier.startsWith("pbkdf2-sha256$30000$")).toBe(true);
  });

  it("salts randomly, so two members choosing the same PIN differ", () => {
    expect(derivePinVerifier(deviceId, "a", "0427")).not.toBe(
      derivePinVerifier(deviceId, "b", "0427")
    );
    // And the same member twice, because the salt is per verifier.
    expect(derivePinVerifier(deviceId, memberId, "0427")).not.toBe(
      derivePinVerifier(deviceId, memberId, "0427")
    );
  });

  it("verifies the PIN it was built from, and nothing else", () => {
    const verifier = derivePinVerifier(deviceId, memberId, "0427");
    expect(verifyPinVerifier(verifier, deviceId, memberId, "0427")).toBe(true);
    expect(verifyPinVerifier(verifier, deviceId, memberId, "0428")).toBe(false);
    // Bound to the device and the member, not just the PIN.
    expect(verifyPinVerifier(verifier, "other-device", memberId, "0427")).toBe(false);
    expect(verifyPinVerifier(verifier, deviceId, "other-member", "0427")).toBe(false);
  });

  it("carries its salt, so a device can recompute without being told it", () => {
    const verifier = derivePinVerifier(deviceId, memberId, "0427");
    const [, iterations, salt] = verifier.split("$");
    expect(Number(iterations)).toBe(30000);
    expect(salt).toBeTruthy();
    // Rebuilding with the parsed salt reproduces it exactly — which is the
    // only reason offline checking works at all.
    expect(derivePinVerifier(deviceId, memberId, "0427", salt)).toBe(verifier);
  });

  it("agrees with Node's own PBKDF2 rather than only with itself", () => {
    const verifier = derivePinVerifier(deviceId, memberId, "0427");
    const [, iterations, salt, hash] = verifier.split("$");
    const expected = pbkdf2Sync(
      `${deviceId}:${memberId}:0427`,
      salt!,
      Number(iterations),
      32,
      "sha256"
    ).toString("base64url");
    expect(hash).toBe(expected);
  });

  it("refuses a malformed verifier instead of throwing", () => {
    for (const broken of ["", "not-a-verifier", "pbkdf2-sha256$", "pbkdf2-sha256$0$s$h", "sha1$1$s$h"]) {
      expect(verifyPinVerifier(broken, deviceId, memberId, "0427")).toBe(false);
    }
  });

  it("costs enough to make a full sweep of a 4-digit PIN unattractive", () => {
    // Not a benchmark — a floor. If someone drops the iteration count to make a
    // test faster, the whole point of the change is gone and this says so.
    const [, iterations] = derivePinVerifier(deviceId, memberId, "0427").split("$");
    expect(Number(iterations)).toBeGreaterThanOrEqual(10000);
  });
});
