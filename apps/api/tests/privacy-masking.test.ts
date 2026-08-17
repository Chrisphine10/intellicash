import { describe, expect, it } from "vitest";
import { maskEmail, maskPhone, redactUrlForLogs } from "../src/lib/privacy";

/**
 * A masked phone number must reveal the last three digits and nothing else.
 *
 * The only assertion that existed for this was `expect(phone).toContain("*")`,
 * which the previous implementation satisfied while showing nine digits of a
 * twelve-digit number: it kept the leading four — country code and operator
 * prefix, near-identical across a group — plus the trailing three. "Contains a
 * star" is not a privacy property.
 */
describe("maskPhone", () => {
  it("reveals only the last three digits", () => {
    expect(maskPhone("254720100102")).toBe("*********102");
    expect(maskPhone("254700000001")).toBe("*********001");
    expect(maskPhone("0722123456")).toBe("*******456");
  });

  it("hides the country code and operator prefix", () => {
    // The specific regression: these are what made the old mask reversible in
    // practice, because every member of a Kenyan group shares them.
    const masked = maskPhone("254720100102");
    expect(masked).not.toContain("254");
    expect(masked).not.toContain("2547");
    expect(masked.replace(/\*/g, "")).toHaveLength(3);
  });

  it("keeps the original length, so tables do not reflow", () => {
    for (const phone of ["254720100102", "0722123456", "+254720100102"]) {
      expect(maskPhone(phone)).toHaveLength(phone.length);
    }
  });

  it("reveals nothing when there is nothing to hide behind", () => {
    // Three digits or fewer would be shown in full by a naive "last three".
    expect(maskPhone("123")).toBe("***");
    expect(maskPhone("12")).toBe("**");
    expect(maskPhone("")).toBe("*");
  });

  it("never renders as almost-plaintext for a short value", () => {
    // A four-character value must not come out as one star and three digits.
    expect(maskPhone("1234").startsWith("****")).toBe(true);
  });

  it("trims before masking, so padding cannot leak a digit", () => {
    expect(maskPhone("  254720100102  ")).toBe("*********102");
  });
});

describe("the other masking helpers still hold", () => {
  it("masks an email local part but keeps the domain routable", () => {
    expect(maskEmail("grace.wanjiku@intellicash.co.ke")).toMatch(/^gr\*+@intellicash\.co\.ke$/);
    expect(maskEmail("not-an-email")).toBe("***");
  });

  it("redacts personal data out of logged query strings", () => {
    expect(redactUrlForLogs("/api/v1/members?phone=254720100102")).toBe(
      "/api/v1/members?phone=%5Bredacted%5D"
    );
    // A path with nothing sensitive is left exactly as it was, so traces stay
    // useful.
    expect(redactUrlForLogs("/api/v1/groups/abc/visits")).toBe("/api/v1/groups/abc/visits");
  });
});
