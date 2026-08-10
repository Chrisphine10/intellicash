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

/**
 * The API origin to permit beyond 'self', or "" when there is none.
 *
 * Only an absolute, cross-origin `NEXT_PUBLIC_API_BASE_URL` widens the policy;
 * a relative value or one already on this host adds nothing. A malformed value
 * is ignored rather than throwing — a bad environment variable must not take
 * every page down with it.
 */
function extraConnectSrc() {
  const configured = process.env.NEXT_PUBLIC_API_BASE_URL?.trim();
  if (!configured || configured.startsWith("/")) return "";

  try {
    return ` ${new URL(configured).origin}`;
  } catch {
    return "";
  }
}

export function middleware(request: NextRequest) {
  const nonce = generateNonce();

  const csp = [
    "default-src 'self'",
    // 'strict-dynamic' lets the nonce'd bootstrap load the rest of the chunks,
    // so every later script inherits trust without whitelisting paths.
    //
    // 'unsafe-eval' is added in DEVELOPMENT ONLY. Next's dev server compiles
    // and hot-reloads modules through eval, so without it every dashboard page
    // dies on "Evaluating a string as JavaScript violates CSP" and sits on
    // "Loading workspace…" forever — the app is unusable locally. A production
    // build contains no eval, so the directive never reaches production and
    // the policy there is unchanged.
    `script-src 'self' 'nonce-${nonce}' 'strict-dynamic'${
      process.env.NODE_ENV === "production" ? "" : " 'unsafe-eval'"
    }`,
    // Next inlines critical CSS and the font loader emits style attributes;
    // there is no nonce hook for those, and inline CSS is not script execution.
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: blob:",
    "font-src 'self' https: data:",
    // The API is same-origin (/api/v1 on this very host) in every deployment.
    // A split setup — API on its own port during development — has to declare
    // itself with NEXT_PUBLIC_API_BASE_URL, and is then allowed here. Reading
    // the same variable `lib/api.ts` calls means the policy cannot forbid the
    // origin the client is about to use, which is precisely what happened
    // before: the client hardcoded :4000 on localhost while this said 'self',
    // so every dashboard fetch was blocked.
    `connect-src 'self'${extraConnectSrc()}`,
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
