import { NextResponse, type NextRequest } from "next/server";

/**
 * Per-request CSP nonce.
 *
 * The App Router emits inline bootstrap scripts (`self.__next_f.push(...)`)
 * that carry the RSC payload. Blocking them leaves the page blank — that
 * outage happened on 28 Jul 2026 when helmet's default `script-src 'self'`
 * applied to the Next-served routes.
 *
 * The stopgap was `'unsafe-inline'`, which permits ANY inline script including
 * one an attacker manages to inject. This replaces it: Next stamps this nonce
 * onto the scripts it emits, so only those specific tags run.
 *
 * Next reads the nonce out of the `Content-Security-Policy` header on the
 * REQUEST, which is why it is set on the forwarded request headers and not
 * just the response.
 */
function generateNonce(): string {
  // Edge runtime: no Buffer. crypto.getRandomValues + btoa are available.
  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);
  let binary = "";
  for (const byte of bytes) binary += String.fromCharCode(byte);
  return btoa(binary);
}

export function middleware(request: NextRequest) {
  const nonce = generateNonce();

  const csp = [
    "default-src 'self'",
    // 'strict-dynamic' lets the nonce'd bootstrap load the rest of the chunks,
    // so every later script inherits trust without whitelisting paths.
    `script-src 'self' 'nonce-${nonce}' 'strict-dynamic'`,
    // Next inlines critical CSS and the font loader emits style attributes;
    // there is no nonce hook for those, and inline CSS is not script execution.
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: blob:",
    "font-src 'self' https: data:",
    // The API is same-origin (/api/v1 on this very host).
    "connect-src 'self'",
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'self'",
    "upgrade-insecure-requests"
  ].join("; ");

  const requestHeaders = new Headers(request.headers);
  requestHeaders.set("x-nonce", nonce);
  requestHeaders.set("Content-Security-Policy", csp);

  const response = NextResponse.next({ request: { headers: requestHeaders } });
  response.headers.set("Content-Security-Policy", csp);
  return response;
}

export const config = {
  matcher: [
    /*
     * Documents only. Static assets are already immutable and hashed, and
     * adding a per-request header to them defeats caching for no benefit.
     * /api/v1 never reaches here — Express answers it before Next does.
     */
    {
      source:
        "/((?!_next/static|_next/image|favicon.ico|.*\\.(?:png|jpg|jpeg|gif|svg|ico|webp|woff|woff2|webmanifest|txt|xml)$).*)",
      missing: [
        { type: "header", key: "next-router-prefetch" },
        { type: "header", key: "purpose", value: "prefetch" }
      ]
    }
  ]
};
