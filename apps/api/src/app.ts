import cors from "cors";
import express from "express";
import helmet from "helmet";
import { ZodError } from "zod";
import { env } from "./config/env";
import { analyticsRouter } from "./routes/analytics";
import { adminRouter } from "./routes/admin";
import { apiKeysRouter } from "./routes/api-keys";
import { auditRouter } from "./routes/audit";
import { authRouter } from "./routes/auth";
import { cyclesRouter } from "./routes/cycles";
import { externalLoansRouter } from "./routes/external-loans";
import { groupPaymentsRouter } from "./routes/group-payments";
import { groupPolicyRouter } from "./routes/group-policy";
import { visitsRouter } from "./routes/visits";
import { assessmentsRouter } from "./routes/assessments";
import { attachmentsRouter } from "./routes/attachments";
import { dataSubjectRouter } from "./routes/data-subject";
import { documentsRouter } from "./routes/documents";
import { mentorshipRouter } from "./routes/mentorship";
import { visitReportsRouter } from "./routes/visit-reports";
import { businessProfileRouter } from "./routes/business-profile";
import { groupPaymentProvidersRouter } from "./routes/group-payment-providers";
import { groupJoinRouter } from "./routes/group-join";
import { groupsRouter } from "./routes/groups";
import { memberRolesRouter } from "./routes/member-roles";
import { integrationsRouter } from "./routes/integrations";
import { intelliStoreRouter } from "./routes/intelli-store";
import { intelliAuditRouter } from "./routes/intelliaudit";
import { notificationsRouter } from "./routes/notifications";
import { partnerPortalRouter } from "./routes/partner-portal";
import { paymentsRouter } from "./routes/payments";
import { pollsRouter } from "./routes/polls";
import { reportsRouter } from "./routes/reports";
import { smsBroadcastsRouter } from "./routes/sms-broadcasts";
import { uploadsRouter } from "./routes/uploads";
import { webhooksRouter } from "./routes/webhooks";
import { welfareExpensesRouter } from "./routes/welfare-expenses";
import { ApiHttpError, fail, ok } from "./lib/http";
import { ensureUploadDirectory, uploadRoot } from "./lib/uploads";
import { requestTracingMiddleware } from "./middleware/request-tracing";

function isAllowedCorsOrigin(origin: string) {
  const configuredOrigins = env.WEB_ORIGIN.split(",")
    .map((value) => value.trim())
    .filter(Boolean);
  const isConfiguredOrigin = configuredOrigins.includes(origin);
  const isLocalDevOrigin =
    env.NODE_ENV !== "production" &&
    /^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(origin);

  return isConfiguredOrigin || isLocalDevOrigin;
}

export function createApp(
  options: { includeNotFoundHandler?: boolean; servesWebApp?: boolean } = {}
) {
  const app = express();
  ensureUploadDirectory();

  // Behind a hosting proxy, `req.ip` is the proxy unless Express is told how
  // many hops to trust — which makes request logs and audit trails record the
  // wrong client. It stays OFF unless declared: switching it on when there is
  // no proxy in front lets any caller spoof their address with a header.
  const trustedProxyHops = Number(process.env.TRUST_PROXY_HOPS ?? "0");
  if (Number.isInteger(trustedProxyHops) && trustedProxyHops > 0) {
    app.set("trust proxy", trustedProxyHops);
  }

  // When this process also serves the Next.js app (scripts/render-server.ts),
  // helmet's default `script-src 'self'` blocks the App Router's inline
  // `self.__next_f.push(...)` bootstrap scripts. React then receives no RSC
  // payload, cannot hydrate, and the page renders BLANK — server HTML is
  // correct, the browser just discards it. That is not a theoretical risk: it
  // took intellicash.co.ke down until 28 Jul 2026.
  //
  // API-only processes (src/server.ts) keep the strict default, since nothing
  // there serves HTML.
  app.use(
    helmet({
      crossOriginResourcePolicy: { policy: "cross-origin" },
      // When Next serves the HTML, apps/web/src/middleware.ts issues a
      // per-request nonce and sets the CSP itself. A browser given two CSP
      // headers enforces the INTERSECTION, so a second policy here would
      // narrow the nonce policy back down and blank the page again. Helmet's
      // other headers (HSTS, COOP, frame options, nosniff) still apply.
      contentSecurityPolicy: options.servesWebApp ? false : undefined
    })
  );

  if (options.servesWebApp) {
    // JSON endpoints are not documents, so the nonce policy is irrelevant to
    // them — but leaving them with no CSP at all would be a downgrade from the
    // API-only process. Lock them down completely instead.
    app.use(
      ["/api/v1", "/health"],
      helmet.contentSecurityPolicy({
        useDefaults: false,
        directives: {
          "default-src": ["'none'"],
          "frame-ancestors": ["'none'"],
          "base-uri": ["'none'"],
          "form-action": ["'none'"]
        }
      })
    );
  }
  app.use(requestTracingMiddleware);
  app.use(
    cors({
      origin(origin, callback) {
        if (!origin || isAllowedCorsOrigin(origin)) {
          callback(null, true);
          return;
        }

        callback(null, false);
      },
      credentials: true
    })
  );
  app.use(express.json({ limit: "5mb" }));

  app.get("/health", (_req, res) => {
    ok(res, { status: "ok", service: "intellicash-api" });
  });

  app.use("/uploads", express.static(uploadRoot, { maxAge: "7d" }));

  app.use("/api/v1/auth", authRouter);
  // BEFORE uploadsRouter, deliberately. That router owns `/uploads/:kind` and
  // answers 404 for any kind it does not know, so it would swallow
  // `/uploads/visit-photo` — which needs its own size limit, its own
  // date-sharded destination and the disk-fill guard. Express matches in
  // registration order, so the specific path has to be mounted first.
  app.use("/api/v1", dataSubjectRouter);
  app.use("/api/v1", attachmentsRouter);
  app.use("/api/v1", uploadsRouter);
  app.use("/api/v1", apiKeysRouter);
  app.use("/api/v1", adminRouter);
  app.use("/api/v1", groupsRouter);
  app.use("/api/v1", visitsRouter);
  app.use("/api/v1", assessmentsRouter);
  app.use("/api/v1", documentsRouter);
  app.use("/api/v1", mentorshipRouter);
  app.use("/api/v1", visitReportsRouter);
  app.use("/api/v1", businessProfileRouter);
  app.use("/api/v1", analyticsRouter);
  app.use("/api/v1", reportsRouter);
  app.use("/api/v1", auditRouter);
  app.use("/api/v1", intelliAuditRouter);
  app.use("/api/v1", integrationsRouter);
  app.use("/api/v1", notificationsRouter);
  app.use("/api/v1", intelliStoreRouter);
  app.use("/api/v1", externalLoansRouter);
  app.use("/api/v1", groupPaymentsRouter);
  app.use("/api/v1", groupPaymentProvidersRouter);
  app.use("/api/v1", cyclesRouter);
  app.use("/api/v1", groupPolicyRouter);
  app.use("/api/v1", memberRolesRouter);
  app.use("/api/v1", welfareExpensesRouter);
  app.use("/api/v1", partnerPortalRouter);
  app.use("/api/v1", paymentsRouter);
  app.use("/api/v1", pollsRouter);
  app.use("/api/v1", groupJoinRouter);
  app.use("/api/v1", smsBroadcastsRouter);
  app.use("/api/v1", webhooksRouter);

  if (options.includeNotFoundHandler ?? true) {
    app.use((_req, _res, next) => {
      next(new ApiHttpError(404, "NOT_FOUND", "Route not found."));
    });
  }

  app.use((error: unknown, _req: express.Request, res: express.Response, _next: express.NextFunction) => {
    if (error instanceof ZodError) {
      return fail(
        res,
        new ApiHttpError(400, "VALIDATION_ERROR", "Request validation failed.", error.flatten())
      );
    }

    return fail(res, error);
  });

  return app;
}
