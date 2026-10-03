import { createHash } from "node:crypto";
import { prisma } from "../lib/prisma";

/**
 * The development team's to-do list of things that went wrong: server errors,
 * errors the console or phone report, and data that does not add up.
 *
 * Recording never throws and never blocks the request that hit the problem —
 * a broken log must not turn one failure into two. Repeats of the same problem
 * fold into one row (`fingerprint`) and bump its count; a problem marked
 * RESOLVED that happens again is reopened, so a fix that did not hold shows.
 *
 * Context is scrubbed before it is stored: no passwords, tokens, PINs, OTPs or
 * phone numbers, and long values are cut. Group members and partners never
 * read this table.
 */

export const systemIssueSources = ["API", "WEB", "MOBILE", "DATA_QUALITY", "JOB"] as const;
export type SystemIssueSource = (typeof systemIssueSources)[number];
export const systemIssueSeverities = ["INFO", "WARNING", "ERROR", "CRITICAL"] as const;
export type SystemIssueSeverity = (typeof systemIssueSeverities)[number];
export const systemIssueStatuses = ["OPEN", "ACKNOWLEDGED", "RESOLVED", "IGNORED"] as const;
export type SystemIssueStatus = (typeof systemIssueStatuses)[number];

export type SystemIssueInput = {
  source: SystemIssueSource;
  severity: SystemIssueSeverity;
  category: string;
  title: string;
  detail?: string | null;
  entityType?: string | null;
  entityId?: string | null;
  groupId?: string | null;
  context?: Record<string, unknown>;
  traceId?: string | null;
  /** What makes two reports "the same problem". Defaults to source+category+title+entity. */
  fingerprintParts?: Array<string | null | undefined>;
};

const SECRET_KEY = /pass(word)?|secret|token|authori[sz]ation|cookie|pin|otp|api[-_]?key|credential|phone|msisdn/i;
const PHONE_LIKE = /(\+?254|0)[17]\d{8}\b/g;
const BEARER = /\b(bearer|basic)\s+[A-Za-z0-9._~+/=-]{8,}/gi;
const LONG_HEX = /\b[a-f0-9]{40,}\b/gi;

/** Strips what must never sit in a log: credentials and phone numbers. */
export function scrubText(value: string, max = 2000) {
  return value.replace(BEARER, "$1 [redacted]").replace(PHONE_LIKE, "[phone]").replace(LONG_HEX, "[redacted]").slice(0, max);
}

export function scrubContext(value: unknown, depth = 0): unknown {
  if (value === null || value === undefined) return value ?? null;
  if (typeof value === "string") return scrubText(value, 500);
  if (typeof value === "number" || typeof value === "boolean") return value;
  if (depth >= 3) return "[…]";
  if (Array.isArray(value)) return value.slice(0, 20).map((item) => scrubContext(item, depth + 1));
  if (typeof value === "object") {
    const out: Record<string, unknown> = {};
    for (const [key, item] of Object.entries(value as Record<string, unknown>).slice(0, 40)) {
      out[key] = SECRET_KEY.test(key) ? "[redacted]" : scrubContext(item, depth + 1);
    }
    return out;
  }
  return String(value).slice(0, 200);
}

export function issueFingerprint(parts: Array<string | null | undefined>) {
  return createHash("sha256").update(parts.map((part) => part ?? "").join("\u001f")).digest("hex").slice(0, 40);
}

/**
 * Turns volatile bits of a message (ids, numbers, quoted values) into
 * placeholders, so "Group cmx1… not found" and "Group cmx2… not found" are one
 * problem rather than two.
 */
export function normaliseMessage(message: string) {
  return message
    .replace(/\bc[a-z0-9]{20,}\b/g, ":id")
    .replace(/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/gi, ":uuid")
    .replace(/(["'`]).*?\1/g, "$1…$1")
    .replace(/\d+/g, "N")
    .slice(0, 300);
}

export async function recordSystemIssue(input: SystemIssueInput): Promise<string | null> {
  try {
    const title = scrubText(input.title, 300);
    const fingerprint = issueFingerprint(
      input.fingerprintParts ?? [input.source, input.category, normaliseMessage(title), input.entityType, input.entityId]
    );
    const detail = input.detail ? scrubText(input.detail, 8000) : null;
    const contextJson = JSON.stringify(scrubContext(input.context ?? {}));
    const now = new Date();

    const existing = await prisma.systemIssue.findUnique({ where: { fingerprint }, select: { id: true, status: true } });
    if (existing) {
      await prisma.systemIssue.update({
        where: { id: existing.id },
        data: {
          occurrences: { increment: 1 },
          lastSeenAt: now,
          lastTraceId: input.traceId ?? undefined,
          detail: detail ?? undefined,
          contextJson,
          // A fix that did not hold comes back to the top of the list.
          ...(existing.status === "RESOLVED" ? { status: "OPEN", resolvedAt: null } : {})
        }
      });
      return existing.id;
    }

    const created = await prisma.systemIssue.create({
      data: {
        fingerprint,
        source: input.source,
        severity: input.severity,
        category: input.category.slice(0, 80),
        title,
        detail,
        entityType: input.entityType ?? null,
        entityId: input.entityId ?? null,
        groupId: input.groupId ?? null,
        contextJson,
        lastTraceId: input.traceId ?? null,
        firstSeenAt: now,
        lastSeenAt: now
      },
      select: { id: true }
    });
    return created.id;
  } catch (error) {
    // Two requests creating the same new issue at once: the loser counts on the winner.
    if ((error as { code?: string }).code === "P2002") {
      return recordSystemIssue(input).catch(() => null);
    }
    console.error(JSON.stringify({ level: "error", event: "system_issue.record_failed", message: (error as Error)?.message }));
    return null;
  }
}

/** Fire-and-forget form for request paths: never awaited, never throws. */
export function reportSystemIssue(input: SystemIssueInput) {
  void recordSystemIssue(input);
}

/**
 * A needs assessment's failed checks ("loan portfolio is more than ten times
 * total savings") as data-quality issues: one per check per submission, for the
 * development and field teams to verify — never shown to the group or partner.
 * Safe to run again: a check already logged only bumps its count.
 */
export async function logNeedsAssessmentFlags(input: {
  needsAssessmentId: string;
  groupId: string;
  groupCode: string;
  assessedOn: Date | null;
  flags: string[];
  fieldOfficer?: string | null;
}) {
  let logged = 0;
  for (const flag of input.flags) {
    const id = await recordSystemIssue({
      source: "DATA_QUALITY",
      severity: "WARNING",
      category: "NEEDS_ASSESSMENT",
      title: `${input.groupCode}: ${flag}`,
      detail: `Baseline needs assessment${input.assessedOn ? ` of ${input.assessedOn.toISOString().slice(0, 10)}` : ""} — check with the field team${input.fieldOfficer ? ` (${input.fieldOfficer})` : ""} and correct the figure at source.`,
      entityType: "GroupNeedsAssessment",
      entityId: input.needsAssessmentId,
      groupId: input.groupId,
      context: { groupCode: input.groupCode, check: flag },
      fingerprintParts: ["DATA_QUALITY", "NEEDS_ASSESSMENT", input.needsAssessmentId, flag]
    });
    if (id) logged += 1;
  }
  return logged;
}
