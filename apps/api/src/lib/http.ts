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

  // The database refusing a write is usually the person's input colliding with
  // something that exists - a phone number or code already in use - which they
  // can fix. It used to reach them as "Something went wrong on our side".
  const known = knownDatabaseRefusal(error);
  if (known) {
    logApiFailure(known.status, known.code, known.message, traceId);
    return res.status(known.status).json({
      error: { code: known.code, message: known.message, ...(traceId ? { traceId } : {}) }
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

const FIELD_WORDS: Record<string, string> = {
  phone: "phone number",
  email: "email address",
  code: "code",
  name: "name",
  nationalIdHash: "national ID",
  clientRequestId: "request",
  slug: "web address"
};

/**
 * Prisma's refusals that mean something to a person, in their words. Duck-typed
 * on `code` so this file does not have to import the Prisma runtime.
 *   P2002 unique constraint  -> 409 ALREADY_EXISTS, naming the field
 *   P2025 record not found   -> 404 NOT_FOUND
 *   P2003 still referenced   -> 409 IN_USE
 */
export function knownDatabaseRefusal(error: unknown): { status: number; code: string; message: string } | null {
  if (!error || typeof error !== "object") return null;
  const { code, meta } = error as { code?: unknown; meta?: { target?: unknown } };
  if (typeof code !== "string" || !/^P\d{4}$/.test(code)) return null;
  if (code === "P2002") {
    const target = meta?.target;
    const fields = (Array.isArray(target) ? target : typeof target === "string" ? [target] : [])
      .map((field) => String(field))
      .map((field) => FIELD_WORDS[field] ?? null)
      .filter((word): word is string => Boolean(word));
    const what = fields.length > 0 ? `this ${fields.join(" and ")}` : "these details";
    return {
      status: 409,
      code: "ALREADY_EXISTS",
      message: `Something with ${what} is already recorded. Use a different one, or open the existing record.`
    };
  }
  if (code === "P2025") {
    return { status: 404, code: "NOT_FOUND", message: "We could not find that. It may have been removed, or the page is out of date." };
  }
  if (code === "P2003") {
    return {
      status: 409,
      code: "IN_USE",
      message: "This is still linked to other records, so it cannot be changed or removed yet."
    };
  }
  return null;
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
