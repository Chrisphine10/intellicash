const LOCAL_API_BASE_URL = "http://localhost:4000/api/v1";

function normalizeApiBaseUrl(value: string) {
  return value.replace(/\/$/, "");
}

/**
 * Where the browser should call the API.
 *
 * Same-origin `/api/v1` everywhere, because that is the shape the Content
 * Security Policy in `middleware.ts` assumes: it sets `connect-src 'self'`, so
 * a page served from one origin cannot fetch from another.
 *
 * This used to force `http://localhost:4000` whenever the hostname was
 * localhost, which quietly could not work — the CSP is applied in development
 * too, so the browser blocked every dashboard fetch and each page showed
 * "Network request failed". Production was unaffected, which is why it went
 * unnoticed: there the API really is on this host.
 *
 * A split setup — web and API on different ports — is still supported, but has
 * to be declared with `NEXT_PUBLIC_API_BASE_URL`. The middleware reads the same
 * variable and widens `connect-src` to match, so the two cannot disagree.
 */
function fallbackApiBaseUrl() {
  if (typeof window !== "undefined") return `${window.location.origin}/api/v1`;

  // Server-side render and build: no window, and nothing is fetched from a
  // browser yet. The local default keeps `next build` working.
  return LOCAL_API_BASE_URL;
}

export const API_BASE_URL = normalizeApiBaseUrl(
  process.env.NEXT_PUBLIC_API_BASE_URL ?? fallbackApiBaseUrl()
);

/**
 * Resolves a server-relative API path (`/api/v1/attachments/x/file`) to
 * something the browser can load.
 *
 * Visit evidence is served by the API behind a scope check, and the API returns
 * the path relative so that a same-origin deployment — which is every real one
 * — needs no configuration. A split development setup puts the API on another
 * port, where a bare path would hit the Next server instead. Prefixing with the
 * configured API origin covers both without the API having to know which it is.
 */
export function evidenceSrc(pathname: string) {
  if (!pathname.startsWith("/")) return pathname;

  try {
    return new URL(pathname, new URL(API_BASE_URL).origin).toString();
  } catch {
    // A relative API_BASE_URL means same-origin, where the path already works.
    return pathname;
  }
}

export class ApiClientError extends Error {
  status: number;
  code: string;
  traceId?: string;
  details?: unknown;
  path?: string;
  method?: string;

  constructor(options: {
    status: number;
    code: string;
    message: string;
    traceId?: string;
    details?: unknown;
    path?: string;
    method?: string;
  }) {
    super(friendlyErrorMessage(options));
    this.name = "ApiClientError";
    this.status = options.status;
    this.code = options.code;
    this.traceId = options.traceId;
    this.details = options.details;
    this.path = options.path;
    this.method = options.method;
  }
}

type ApiPayload<T = unknown> = {
  data?: T;
  error?: {
    code?: string;
    message?: string;
    details?: unknown;
    traceId?: string;
  };
};

function createClientTraceId() {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
    return crypto.randomUUID();
  }

  return `web-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

/**
 * What a person reads when something fails.
 *
 * Every error used to end in "(Trace ID: 5f32dfcb-4d19-4545-a145-1693e167f8e5)"
 * — on a wrong password, a missing field, anything. That is a support tool, not
 * a message: it made routine mistakes look like crashes. The trace ID is still
 * on the error object (and sent to the log), and a short reference is shown
 * only where support genuinely needs it — when the fault is ours or the network.
 *
 * The server's message is used when it has written one for people. A few codes
 * whose server text is generic, and anything with no text at all, get wording
 * here instead.
 */
const GENERIC_SERVER_TEXT = new Set([
  "",
  "API request failed",
  "Request validation failed.",
  "Invalid credentials.",
  "An unexpected server error occurred.",
  "Internal Server Error"
]);

const BY_CODE: Record<string, string> = {
  INVALID_CREDENTIALS:
    "That phone number (or email) and password do not match an account. Check them and try again, or sign in with a code sent by SMS.",
  VALIDATION_ERROR: "Some details are missing or not valid. Check the form and try again.",
  UNAUTHENTICATED: "Please sign in to continue. If you were signed in, your session has ended.",
  FORBIDDEN: "Your account does not have access to this. Ask an administrator if you need it.",
  RATE_LIMITED: "Too many attempts. Wait a few minutes and try again.",
  NOT_FOUND: "We could not find that. It may have been removed, or the link is out of date."
};

function byStatus(status: number) {
  if (status === 0) return "We could not reach IntelliCash. Check your internet connection and try again.";
  if (status === 401) return BY_CODE.UNAUTHENTICATED;
  if (status === 403) return BY_CODE.FORBIDDEN;
  if (status === 404) return BY_CODE.NOT_FOUND;
  if (status === 409) return "This conflicts with something that already exists. Refresh the page and check.";
  if (status === 413) return "That file is too large to upload.";
  if (status === 429) return BY_CODE.RATE_LIMITED;
  if (status >= 500) return "Something went wrong on our side. Please try again in a moment.";
  return "That did not work. Check the details and try again.";
}

export function friendlyErrorMessage(options: { status: number; code: string; message: string; traceId?: string }) {
  const serverText = (options.message ?? "").trim();
  const base = !GENERIC_SERVER_TEXT.has(serverText)
    ? serverText
    : (BY_CODE[options.code] ?? byStatus(options.status));

  // A reference only helps when support has to look something up: our fault,
  // or a request that never arrived. Never on a wrong password or empty field.
  const needsReference = options.status === 0 || options.status >= 500;
  return needsReference && options.traceId ? `${base} (Reference: ${options.traceId.slice(0, 8)})` : base;
}

function requestMethod(init: RequestInit) {
  return (init.method ?? "GET").toUpperCase();
}

function buildHeaders(initHeaders?: HeadersInit, includeJsonContentType = true) {
  const headers = new Headers(initHeaders);
  const traceId = headers.get("X-Request-Id") ?? createClientTraceId();

  headers.set("X-Request-Id", traceId);
  if (includeJsonContentType && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  return { headers, traceId };
}

async function readPayload<T>(response: Response) {
  return (await response.json().catch(() => null)) as ApiPayload<T> | null;
}

function responseTraceId(response: Response, payload: ApiPayload | null, fallbackTraceId: string) {
  return payload?.error?.traceId ?? response.headers.get("X-Request-Id") ?? fallbackTraceId;
}

function logClientApiError(error: ApiClientError) {
  if (process.env.NODE_ENV === "test") return;

  // 401s are expected, routine control flow here: every dashboard page (and
  // the shell) probes /auth/me on mount to decide whether to redirect to
  // /login, so an unauthenticated visit always produces one. Logging it as a
  // console.error trips Next's dev-mode red overlay for something that isn't
  // a bug. Genuine failures still throw ApiClientError and are handled by
  // the caller (inline error state or a login redirect) either way.
  if (error.status === 401) return;

  // A 4xx is the server answering a question - "enter a valid phone number",
  // "not found", "already exists" - and the page shows it inline. A request
  // that never arrived (status 0) is the phone or laptop being offline, or the
  // API not running: also shown inline, and not a fault in this code. Only a
  // 5xx is logged at error level. Both used to raise Next's red "1 Issue"
  // overlay, which printed the details as "{}".
  //
  // One readable line first (the overlay and a pasted report keep it), the
  // details after it for the browser console.
  const reference = error.traceId ? ` ref ${error.traceId.slice(0, 8)}` : "";
  const what = `${error.method ?? "GET"} ${error.path ?? ""}`.trim();
  const line =
    error.status === 0
      ? `[intellicash-api] ${what}: could not reach the API at ${API_BASE_URL}` +
        `${error.details ? ` (${String(error.details)})` : ""}.${reference}`
      : `[intellicash-api] ${what}: ${error.status} ${error.code}.${reference}`;
  const log = error.status >= 500 ? console.error : console.warn;
  log(line, { status: error.status, code: error.code, traceId: error.traceId, details: error.details });
}

function createResponseError(
  response: Response,
  payload: ApiPayload | null,
  fallbackTraceId: string,
  path: string,
  method: string,
  fallbackCode: string,
  fallbackMessage: string
) {
  const traceId = responseTraceId(response, payload, fallbackTraceId);
  return new ApiClientError({
    status: response.status,
    code: payload?.error?.code ?? fallbackCode,
    message: payload?.error?.message ?? fallbackMessage,
    details: payload?.error?.details,
    traceId,
    path,
    method
  });
}

function createNetworkError(error: unknown, traceId: string, path: string, method: string) {
  return new ApiClientError({
    status: 0,
    code: "NETWORK_ERROR",
    message: "",
    details: error instanceof Error ? error.message : String(error),
    traceId,
    path,
    method
  });
}

export async function apiFetch<T>(path: string, init: RequestInit = {}): Promise<T> {
  const method = requestMethod(init);
  const { headers, traceId } = buildHeaders(init.headers);
  let response: Response;

  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      ...init,
      credentials: "include",
      headers
    });
  } catch (error) {
    const clientError = createNetworkError(error, traceId, path, method);
    logClientApiError(clientError);
    throw clientError;
  }

  const payload = await readPayload<T>(response);

  if (!response.ok) {
    const clientError = createResponseError(
      response,
      payload,
      traceId,
      path,
      method,
      "API_ERROR",
      "API request failed"
    );
    logClientApiError(clientError);
    throw clientError;
  }

  return payload?.data as T;
}

export interface UploadedFile {
  kind: string;
  url: string;
  path: string;
  fileName: string;
  mimeType: string;
  size: number;
}

export async function uploadFile(kind: "avatar" | "image" | "file" | "store-image", file: File): Promise<UploadedFile> {
  const body = new FormData();
  body.append("file", file);

  const path = `/uploads/${kind}`;
  const method = "POST";
  const { headers, traceId } = buildHeaders(undefined, false);
  let response: Response;

  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method,
      credentials: "include",
      headers,
      body
    });
  } catch (error) {
    const clientError = createNetworkError(error, traceId, path, method);
    logClientApiError(clientError);
    throw clientError;
  }

  const payload = await readPayload<UploadedFile>(response);

  if (!response.ok) {
    const clientError = createResponseError(
      response,
      payload,
      traceId,
      path,
      method,
      "UPLOAD_ERROR",
      "File upload failed"
    );
    logClientApiError(clientError);
    throw clientError;
  }

  return payload?.data as UploadedFile;
}

export function formatKes(cents: number) {
  return new Intl.NumberFormat("en-KE", {
    style: "currency",
    currency: "KES",
    maximumFractionDigits: 0
  }).format(cents / 100);
}

/**
 * Dates, formatted the same way everywhere.
 *
 * 23 places called `toLocaleDateString()` with no locale, so the console showed
 * whatever the reader's browser felt like. On a US-locale machine that renders
 * 14 August 2026 as "8/14/2026", which a Kenyan reader parses as 8 April. Ten
 * other places passed "en-KE" and got "14/08/2026". Same product, three
 * formats, one of them actively misleading.
 *
 * The month is spelled, so there is no order to get wrong. `undefined` and an
 * unparseable value both give an em dash rather than "Invalid Date" -- a field
 * that was never filled in is ordinary, and should look ordinary.
 */
const DATE_FORMAT = new Intl.DateTimeFormat("en-GB", {
  day: "numeric",
  month: "short",
  year: "numeric"
});

const DATE_TIME_FORMAT = new Intl.DateTimeFormat("en-GB", {
  day: "numeric",
  month: "short",
  year: "numeric",
  hour: "2-digit",
  minute: "2-digit",
  hour12: false
});

function parseDate(value: string | number | Date | null | undefined) {
  if (value === null || value === undefined || value === "") return null;
  const date = value instanceof Date ? value : new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

/** `14 Aug 2026`, or an em dash when there is no date. */
export function formatDate(value: string | number | Date | null | undefined) {
  const date = parseDate(value);
  return date ? DATE_FORMAT.format(date) : "—";
}

/** `14 Aug 2026, 11:20`, or an em dash when there is no date. */
export function formatDateTime(value: string | number | Date | null | undefined) {
  const date = parseDate(value);
  return date ? DATE_TIME_FORMAT.format(date) : "—";
}

export function humanizeEnum(value: string) {
  return value
    .split("_")
    .map((part) => part.slice(0, 1) + part.slice(1).toLowerCase())
    .join(" ");
}
