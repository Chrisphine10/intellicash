import { describe, expect, it } from "vitest";
import { NextRequest } from "next/server";

import { middleware } from "@/middleware";

/**
 * The Content-Security-Policy the middleware stamps on a document request.
 *
 * Parsed into directive -> sources so a test can ask "is this URL allowed?"
 * rather than string-matching a policy that is free to be reordered.
 */
function policy(): Map<string, string[]> {
  const request = new NextRequest(new URL("http://localhost:3000/"));
  const header = middleware(request).headers.get("content-security-policy");

  expect(header, "middleware must set a CSP header").toBeTruthy();

  const directives = new Map<string, string[]>();

  for (const directive of (header ?? "").split(";")) {
    const tokens = directive.trim().split(/\s+/).filter(Boolean);
    const name = tokens.shift();
    if (name) directives.set(name, tokens);
  }

  return directives;
}

describe("content security policy", () => {
  /*
   * Programme covers, partner project covers and Intelli-Store product photos
   * are admin-supplied absolute URLs — the console has a field for each. When
   * img-src was `'self' data: blob:` every one of them was blocked, and
   * nothing said so: the console showed the URL saved and correct while the
   * public pages rendered an empty frame. A silent breakage of a shipped
   * feature is exactly what a policy test is for.
   */
  it("permits the remote images the console lets an administrator save", () => {
    const imgSrc = policy().get("img-src") ?? [];

    expect(imgSrc).toContain("https:");
    expect(imgSrc).toContain("'self'");
    // Store product photos are pasted as data URLs by some browsers, and the
    // camera capture path produces blobs.
    expect(imgSrc).toContain("data:");
    expect(imgSrc).toContain("blob:");
  });

  it("keeps plain http images out, so a mixed-content URL fails loudly", () => {
    expect(policy().get("img-src") ?? []).not.toContain("http:");
  });

  it("permits the Google Maps embed used by the meetings dashboard", () => {
    expect(policy().get("frame-src") ?? []).toContain("https://www.google.com");
  });

  /*
   * Visit evidence is fetched from the API host, so img-src has to permit
   * whatever connect-src permits. When they disagree the console loads the
   * attachment's metadata and then renders an empty frame for the picture.
   */
  it("permits images from wherever it permits API calls", () => {
    const current = policy();
    const imgSrc = current.get("img-src") ?? [];

    for (const source of current.get("connect-src") ?? []) {
      // A scheme source covers every host on it: `https:` permits any
      // https://… image, so the Google Maps hosts need no line of their own.
      const coveredByScheme = imgSrc.some((allowed) => allowed.endsWith(":") && source.startsWith(allowed));
      if (!coveredByScheme) expect(imgSrc).toContain(source);
    }
  });

  /*
   * The Maps JavaScript API fetches over XHR and draws in a blob: worker. With
   * only 'self' allowed the script loaded and every map stayed grey — the
   * production fault of 3 Oct 2026.
   */
  it("lets Google Maps fetch its data and run its worker", () => {
    const current = policy();
    const connect = current.get("connect-src") ?? [];
    for (const host of ["https://*.googleapis.com", "https://*.gstatic.com", "https://*.google.com"]) {
      expect(connect).toContain(host);
    }
    expect(current.get("worker-src") ?? []).toContain("blob:");
    expect(current.get("script-src") ?? []).toContain("https://maps.googleapis.com");
  });

  it("nonces scripts rather than allowing inline ones", () => {
    const scriptSrc = policy().get("script-src") ?? [];

    expect(scriptSrc.some((source) => source.startsWith("'nonce-"))).toBe(true);
    expect(scriptSrc).not.toContain("'unsafe-inline'");
  });

  it("issues a different nonce per request", () => {
    const first = policy().get("script-src") ?? [];
    const second = policy().get("script-src") ?? [];

    const nonceOf = (sources: string[]) =>
      sources.find((source) => source.startsWith("'nonce-"));

    expect(nonceOf(first)).not.toEqual(nonceOf(second));
  });
});
