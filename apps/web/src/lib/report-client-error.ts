import { API_BASE_URL } from "./api";

/**
 * Sends an error this screen hit to the development team's issue log
 * (/system-issues/report). Best effort: it never throws, never retries, and
 * the same message is sent once per page load — a render loop must not flood
 * the log. Only signed-in sessions are accepted by the API; anything else is
 * dropped quietly.
 */
const sent = new Set<string>();

export function reportClientError(input: { message: string; stack?: string | null; digest?: string | null; category?: string }) {
  try {
    if (typeof window === "undefined" || process.env.NODE_ENV === "test") return;
    const message = (input.message || "Unknown error").slice(0, 500);
    const key = `${input.category ?? ""}|${message}`;
    if (sent.has(key) || sent.size > 20) return;
    sent.add(key);
    // The route, not the full URL: query strings can carry tokens.
    const screen = window.location.pathname.slice(0, 200);
    void fetch(`${API_BASE_URL}/system-issues/report`, {
      method: "POST",
      credentials: "include",
      keepalive: true,
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        source: "WEB",
        severity: "ERROR",
        category: input.category ?? "CLIENT_ERROR",
        message,
        ...(input.stack ? { stack: input.stack.slice(0, 8000) } : {}),
        ...(input.digest ? { digest: input.digest.slice(0, 100) } : {}),
        screen
      })
    }).catch(() => undefined);
  } catch {
    // Reporting must never become the second failure.
  }
}

/** Uncaught errors and rejected promises anywhere in the console. Returns a remover. */
export function listenForClientErrors() {
  if (typeof window === "undefined") return () => undefined;
  const onError = (event: ErrorEvent) => {
    // Script and image load failures from extensions carry no error object; skip them.
    if (!event.error) return;
    reportClientError({ message: event.message, stack: (event.error as Error)?.stack, category: "UNCAUGHT_ERROR" });
  };
  const onRejection = (event: PromiseRejectionEvent) => {
    const reason = event.reason as { message?: string; stack?: string; status?: number } | undefined;
    // An API refusal (4xx) or offline request is shown inline by the page; the
    // server already logs its own 5xx. Only code faults are worth a report.
    if (reason && typeof reason.status === "number") return;
    reportClientError({ message: reason?.message ?? String(event.reason), stack: reason?.stack, category: "UNHANDLED_REJECTION" });
  };
  window.addEventListener("error", onError);
  window.addEventListener("unhandledrejection", onRejection);
  return () => {
    window.removeEventListener("error", onError);
    window.removeEventListener("unhandledrejection", onRejection);
  };
}
