import type { Response } from "express";
import type { ZodError } from "zod";
import { AMOUNT_TOO_LARGE_MESSAGE, isIntegerOverflow } from "../domain/money";
import { traceIdFromResponse } from "../middleware/request-tracing";

export class ApiHttpError extends Error {
  status: number;
  code: string;
  details?: unknown;

  constructor(status: number, code: string, message: string, details?: unknown) {
    super(message);
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

export function ok<T>(res: Response, data: T, meta?: Record<string, unknown>) {
  const traceId = traceIdFromResponse(res);
  const responseMeta = { ...(traceId ? { traceId } : {}), ...(meta ?? {}) };
  return res.json({ data, ...(Object.keys(responseMeta).length > 0 ? { meta: responseMeta } : {}) });
}

export function fail(res: Response, error: unknown) {
  const traceId = traceIdFromResponse(res);

  if (error instanceof ApiHttpError) {
    logApiFailure(error.status, error.code, error.message, traceId, error.details);
    return res.status(error.status).json({
      error: {
        code: error.code,
        message: error.message,
        ...(error.details ? { details: error.details } : {}),
        ...(traceId ? { traceId } : {})
      }
    });
  }

  // A figure too large for its column is the caller's input, not a fault on our
  // side — and the write it interrupted was rolled back, so nothing half-saved.
  if (isIntegerOverflow(error)) {
    logApiFailure(400, "AMOUNT_TOO_LARGE", AMOUNT_TOO_LARGE_MESSAGE, traceId);
    return res.status(400).json({
      error: { code: "AMOUNT_TOO_LARGE", message: AMOUNT_TOO_LARGE_MESSAGE, ...(traceId ? { traceId } : {}) }
    });
  }

  logApiFailure(500, "INTERNAL_ERROR", error instanceof Error ? error.message : "Unknown API error", traceId, error);
  return res.status(500).json({
    error: {
      code: "INTERNAL_ERROR",
      message: "Something went wrong on our side. Please try again in a moment.",
      ...(traceId ? { traceId } : {})
    }
  });
}

/**
 * "Request validation failed." told nobody which box to fix. Name the first
 * field and what is wrong with it, in words a form user recognises.
 */
export function validationMessage(error: ZodError) {
  const issue = error.issues[0];
  if (!issue) return "Some details are missing or not valid. Check the form and try again.";

  const field = [...issue.path].reverse().find((part): part is string => typeof part === "string");
  const label = field
    ? field
        .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
        .replace(/[_-]+/g, " ")
        .trim()
        .toLowerCase()
        // "programmeIds" reads as "Programme", not "Programme ids".
        .replace(/\s+ids?$/, "")
        // Money fields are named in cents; the person typed an amount.
        .replace(/\s+cents$/, "")
        .replace(/\b(id|pin|otp|gps|url|va|sms)\b/g, (word) => word.toUpperCase())
        .replace(/^./, (first) => first.toUpperCase())
    : null;

  const more = error.issues.length > 1 ? " Check the other fields too." : "";

  // A message somebody wrote for people (a full sentence ending in a full stop)
  // beats anything assembled from the field name — zod's own defaults never end
  // that way. "Amount cents That amount is too large…" would be worse than none.
  if (issue.code !== "invalid_type" && /^[A-Z].*\.$/.test(issue.message) && !/ Expected /.test(issue.message)) {
    return `${issue.message}${more}`;
  }

  const reason = issueInWords(issue);

  if (!label) return `Some details are not valid: ${reason}.${more}`;
  return `${label} ${reason}.${more}`;
}

/** Zod's defaults ("String must contain at least 3 character(s)") in plain words. */
function issueInWords(issue: ZodError["issues"][number]): string {
  switch (issue.code) {
    case "invalid_type":
      if (issue.received === "undefined" || issue.received === "null") return "is required";
      return issue.expected === "number" ? "must be a number" : `is not in the right format`;
    case "too_small":
      if (issue.type === "string") {
        return Number(issue.minimum) <= 1 ? "is required" : `must be at least ${issue.minimum} characters`;
      }
      if (issue.type === "array") return Number(issue.minimum) <= 1 ? "needs at least one item" : `needs at least ${issue.minimum} items`;
      return `must be at least ${issue.minimum}`;
    case "too_big":
      if (issue.type === "string") return `must be at most ${issue.maximum} characters`;
      if (issue.type === "array") return `can have at most ${issue.maximum} items`;
      return `must be at most ${issue.maximum}`;
    case "invalid_string":
      return issue.validation === "email" ? "is not a valid email address" : "is not in the right format";
    case "invalid_enum_value":
      return `must be one of: ${issue.options.join(", ")}`;
    case "invalid_date":
      return "is not a valid date";
    default:
      // A custom refine message was written for people already.
      return issue.message.replace(/\.$/, "").replace(/^./, (first) => (first === first.toUpperCase() ? `— ${first}` : first));
  }
}

function logApiFailure(status: number, code: string, message: string, traceId?: string, details?: unknown) {
  if (process.env.NODE_ENV === "test") return;

  const level = status >= 500 ? "error" : "warn";
  const payload = {
    level,
    event: "api.error",
    traceId,
    statusCode: status,
    code,
    message,
    ...(details instanceof Error
      ? { stack: details.stack }
      : status >= 500 && details
        ? { details: String(details) }
        : {})
  };
  const logLine = JSON.stringify(payload);
  if (level === "error") {
    console.error(logLine);
  } else {
    console.warn(logLine);
  }
}
