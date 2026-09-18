"use client";

import { useEffect } from "react";
import { AlertTriangle, RefreshCw } from "@/lib/theme-icons";

export default function AppError({
  error,
  reset
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  const traceId = error.digest ?? "client-route-error";

  useEffect(() => {
    console.error("[intellicash-route-error]", {
      traceId,
      message: error.message,
      stack: error.stack
    });
  }, [error, traceId]);

  return (
    <main className="app-error-page">
      <section className="app-error-card" aria-labelledby="app-error-title">
        <span className="app-error-icon" aria-hidden="true">
          <AlertTriangle size={24} />
        </span>
        <div>
          <p className="eyebrow">Something needs attention</p>
          <h1 id="app-error-title">This page could not finish loading.</h1>
          <p>Please try again. If it keeps happening, send the reference below to IntelliCash support.</p>
        </div>
        <div className="app-error-actions">
          <button type="button" className="primary-action" onClick={reset}>
            <RefreshCw size={16} />
            Try again
          </button>
          <span className="trace-pill">Reference: {traceId.slice(0, 8)}</span>
        </div>
      </section>
    </main>
  );
}
