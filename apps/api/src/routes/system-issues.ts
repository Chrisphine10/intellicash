import { Router } from "express";
import { z } from "zod";
import { requireAdmin, requireAuth } from "../middleware/auth";
import { issueReportRateLimit } from "../middleware/rate-limit";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { appendAuditEvent } from "../services/audit-service";
import { traceIdFromResponse } from "../middleware/request-tracing";
import {
  normaliseMessage,
  recordSystemIssue,
  systemIssueSeverities,
  systemIssueSources,
  systemIssueStatuses
} from "../services/system-issue-service";

/**
 * The development team's issue log. Anyone signed in can report an error
 * their screen hit (the console and phone do this on their own); only IWL
 * admins read and work the list. Members and partners never see it.
 */
export const systemIssuesRouter = Router();

const reportSchema = z.object({
  source: z.enum(["WEB", "MOBILE"]),
  severity: z.enum(["WARNING", "ERROR", "CRITICAL"]).default("ERROR"),
  category: z.string().trim().min(1).max(80).default("CLIENT_ERROR"),
  message: z.string().trim().min(1).max(500),
  stack: z.string().max(8000).optional(),
  /** The screen's route pattern or path, e.g. /dashboard/groups/[id]. */
  screen: z.string().trim().max(200).optional(),
  appVersion: z.string().trim().max(40).optional(),
  digest: z.string().trim().max(100).optional()
});

systemIssuesRouter.post("/system-issues/report", requireAuth(), issueReportRateLimit, async (req, res, next) => {
  try {
    const body = reportSchema.parse(req.body);
    // Ids in a path make every page its own issue; collapse them.
    const screen = body.screen ? body.screen.replace(/\/c[a-z0-9]{20,}/g, "/:id").replace(/\?.*$/, "") : null;
    const id = await recordSystemIssue({
      source: body.source,
      severity: body.severity,
      category: body.category,
      title: `${screen ?? "(screen unknown)"}: ${body.message}`,
      detail: body.stack ?? null,
      traceId: traceIdFromResponse(res) ?? null,
      context: { screen, appVersion: body.appVersion ?? null, digest: body.digest ?? null, role: req.user?.role ?? null, userId: req.user?.id ?? null },
      fingerprintParts: [body.source, body.category, screen, normaliseMessage(body.message)]
    });
    ok(res, { recorded: Boolean(id) });
  } catch (error) {
    next(error);
  }
});

const listSchema = z.object({
  status: z.enum(systemIssueStatuses).optional(),
  source: z.enum(systemIssueSources).optional(),
  severity: z.enum(systemIssueSeverities).optional(),
  q: z.string().trim().max(100).optional(),
  limit: z.coerce.number().int().min(1).max(200).default(100)
});

function serialize(row: Awaited<ReturnType<typeof prisma.systemIssue.findFirstOrThrow>>) {
  const { contextJson, fingerprint: _fingerprint, ...rest } = row;
  void _fingerprint;
  return { ...rest, context: JSON.parse(contextJson) as Record<string, unknown> };
}

systemIssuesRouter.get("/system-issues", requireAuth(), requireAdmin, async (req, res, next) => {
  try {
    const query = listSchema.parse(req.query);
    const where = {
      ...(query.status ? { status: query.status } : { status: { in: ["OPEN", "ACKNOWLEDGED"] } }),
      ...(query.source ? { source: query.source } : {}),
      ...(query.severity ? { severity: query.severity } : {}),
      ...(query.q ? { OR: [{ title: { contains: query.q } }, { category: { contains: query.q } }] } : {})
    };
    const [rows, counts] = await Promise.all([
      prisma.systemIssue.findMany({ where, orderBy: [{ lastSeenAt: "desc" }], take: query.limit }),
      prisma.systemIssue.groupBy({ by: ["status", "source"], _count: true })
    ]);
    const groupIds = [...new Set(rows.map((row) => row.groupId).filter((id): id is string => Boolean(id)))];
    const groups = groupIds.length
      ? await prisma.group.findMany({ where: { id: { in: groupIds } }, select: { id: true, code: true, name: true } })
      : [];
    const byId = new Map(groups.map((group) => [group.id, group]));
    ok(
      res,
      rows.map((row) => ({ ...serialize(row), group: row.groupId ? byId.get(row.groupId) ?? null : null })),
      { counts: counts.map((count) => ({ status: count.status, source: count.source, count: count._count })) }
    );
  } catch (error) {
    next(error);
  }
});

const updateSchema = z.object({
  status: z.enum(systemIssueStatuses),
  note: z.string().trim().max(1000).optional()
});

systemIssuesRouter.patch("/system-issues/:id", requireAuth(), requireAdmin, async (req, res, next) => {
  try {
    const body = updateSchema.parse(req.body);
    const existing = await prisma.systemIssue.findUnique({ where: { id: String(req.params.id) } });
    if (!existing) throw new ApiHttpError(404, "ISSUE_NOT_FOUND", "That issue does not exist.");
    if (body.status === "RESOLVED" && !body.note) {
      throw new ApiHttpError(400, "RESOLUTION_NOTE_REQUIRED", "Say what fixed it, so the next person knows.");
    }
    const resolved = body.status === "RESOLVED";
    const updated = await prisma.systemIssue.update({
      where: { id: existing.id },
      data: {
        status: body.status,
        resolvedAt: resolved ? new Date() : null,
        resolvedById: resolved ? req.user!.id : null,
        ...(body.note !== undefined ? { resolutionNote: body.note } : {})
      }
    });
    await appendAuditEvent({
      type: "SYSTEM_ISSUE_UPDATED",
      actorUserId: req.user!.id,
      entityType: "SystemIssue",
      entityId: existing.id,
      payload: { from: existing.status, to: body.status, note: body.note ?? null }
    });
    ok(res, serialize(updated));
  } catch (error) {
    next(error);
  }
});
