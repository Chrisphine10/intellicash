import { Router } from "express";
import { z } from "zod";
import { groupVisitTypes } from "@intellicash/shared";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import { amendGroupVisit, serializeVisit, submitGroupVisit } from "../services/visit-service";

export const visitsRouter = Router();

/**
 * Field-agent visits to a group.
 *
 * **Scope before anything else.** `scopeGroupWhere` already restricts an agent
 * to their own caseload, so a group outside it is a 404 rather than a 403 — the
 * house convention, and the right one: "forbidden" confirms the group exists to
 * someone who should not know that.
 *
 * A visit used to open with a PIN held by the group. That is gone; see
 * `visit-service.ts` for what is left standing behind a visit record.
 */

async function loadGroupInScope(user: AuthenticatedUser | undefined, groupId: string) {
  const group = await prisma.group.findFirst({
    where: { AND: [{ id: groupId }, scopeGroupWhere(user)] },
    select: { id: true, name: true, code: true, gpsLatitude: true, gpsLongitude: true }
  });
  if (!group) {
    throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
  }
  return group;
}

const submitSchema = z.object({
  // Minted on the phone when the visit is opened and never regenerated.
  clientRequestId: z.string().min(8).max(120),
  visitType: z.enum(groupVisitTypes).default("FOLLOW_UP"),
  startedAt: z.string().datetime(),
  completedAt: z.string().datetime().optional(),
  location: z
    .object({
      latitude: z.number().min(-90).max(90),
      longitude: z.number().min(-180).max(180),
      accuracyM: z.number().nonnegative().optional(),
      capturedAt: z.string().datetime().optional()
    })
    .nullish(),
  // The agent's explanation when the location does not match — an explanation
  // channel, never evidence. The server's own haversine is the signal.
  locationNote: z.string().max(500).optional(),
  deviceId: z.string().max(120).optional(),
  notes: z.string().max(5000).optional()
});

visitsRouter.post("/groups/:groupId/visits", requireAuth("visits:write"), async (req, res, next) => {
  try {
    const group = await loadGroupInScope(req.user, req.params.groupId as string);
    const payload = submitSchema.parse(req.body);

    const result = await submitGroupVisit({
      groupId: group.id,
      clientRequestId: payload.clientRequestId,
      visitType: payload.visitType,
      startedAt: new Date(payload.startedAt),
      completedAt: payload.completedAt ? new Date(payload.completedAt) : null,
      device: payload.location
        ? {
            latitude: payload.location.latitude,
            longitude: payload.location.longitude,
            accuracyM: payload.location.accuracyM ?? null
          }
        : null,
      locationCapturedAt: payload.location?.capturedAt ? new Date(payload.location.capturedAt) : null,
      locationNote: payload.locationNote ?? null,
      deviceId: payload.deviceId ?? null,
      notes: payload.notes ?? null,
      villageAgentId: req.user?.villageAgentId ?? null,
      submittedByUserId: req.user?.id ?? null
    });

    // 200 on a repeat, not 201 and not 409. A phone that treated the retry as a
    // failure would keep retrying forever; this tells it the work is done.
    res.status(result.created ? 201 : 200);
    ok(res, { visit: serializeVisit(result.visit), created: result.created });
  } catch (error) {
    next(error);
  }
});

visitsRouter.get("/groups/:groupId/visits", requireAuth("visits:read"), async (req, res, next) => {
  try {
    const group = await loadGroupInScope(req.user, req.params.groupId as string);
    const visits = await prisma.groupVisit.findMany({
      where: { groupId: group.id },
      orderBy: { startedAt: "desc" },
      take: 100
    });
    ok(res, {
      group: { id: group.id, name: group.name, code: group.code },
      visits: visits.map(serializeVisit)
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Visits across every group the caller can see.
 *
 * The admin console needs one list rather than a request per group, and an
 * agent gets their own caseload from the same route — `scopeGroupWhere`
 * already narrows it, so there is no separate agent endpoint to keep in step.
 */
visitsRouter.get("/visits", requireAuth("visits:read"), async (req, res, next) => {
  try {
    const groupId = typeof req.query.groupId === "string" ? req.query.groupId : undefined;
    const outcome =
      typeof req.query.locationOutcome === "string" ? req.query.locationOutcome : undefined;

    const visits = await prisma.groupVisit.findMany({
      where: {
        AND: [
          { group: scopeGroupWhere(req.user) },
          ...(groupId ? [{ groupId }] : []),
          ...(outcome ? [{ locationOutcome: outcome }] : [])
        ]
      },
      orderBy: { startedAt: "desc" },
      take: 300,
      include: {
        group: { select: { id: true, name: true, code: true, county: true } },
        villageAgent: { select: { id: true, name: true } }
      }
    });

    ok(res, {
      visits: visits.map((visit) => ({
        ...serializeVisit(visit),
        group: visit.group,
        agent: visit.villageAgent
      }))
    });
  } catch (error) {
    next(error);
  }
});

visitsRouter.get("/visits/:visitId", requireAuth("visits:read"), async (req, res, next) => {
  try {
    const visit = await prisma.groupVisit.findFirst({
      where: {
        AND: [{ id: req.params.visitId as string }, { group: scopeGroupWhere(req.user) }]
      },
      include: {
        group: {
          select: {
            id: true,
            name: true,
            code: true,
            county: true,
            subCounty: true,
            location: true,
            phase: true
          }
        },
        // Who stood with the group. A visit that cannot name its agent cannot
        // be followed up, and the page had nowhere to read this from.
        villageAgent: { select: { id: true, name: true, phone: true } },
        submittedBy: { select: { id: true, name: true } },
        revisions: { orderBy: { revision: "desc" } }
      }
    });
    if (!visit) throw new ApiHttpError(404, "VISIT_NOT_FOUND", "That visit does not exist.");

    ok(res, {
      visit: serializeVisit(visit),
      group: visit.group,
      agent: visit.villageAgent,
      submittedBy: visit.submittedBy,
      revisions: visit.revisions.map((revision) => ({
        revision: revision.revision,
        reason: revision.reason,
        amendedByUserId: revision.amendedByUserId,
        createdAt: revision.createdAt.toISOString()
      }))
    });
  } catch (error) {
    next(error);
  }
});

/** The agent's own visit history across their caseload. */
visitsRouter.get("/agents/me/visits", requireAuth("visits:read"), async (req, res, next) => {
  try {
    if (!req.user?.villageAgentId) {
      throw new ApiHttpError(
        400,
        "NOT_AN_AGENT",
        "This account is not linked to a field agent."
      );
    }
    const visits = await prisma.groupVisit.findMany({
      where: { villageAgentId: req.user.villageAgentId },
      orderBy: { startedAt: "desc" },
      take: 200,
      include: { group: { select: { id: true, name: true, code: true } } }
    });
    ok(res, {
      visits: visits.map((visit) => ({ ...serializeVisit(visit), group: visit.group }))
    });
  } catch (error) {
    next(error);
  }
});

const amendSchema = z.object({
  reason: z.string().min(3).max(500),
  notes: z.string().max(5000).optional(),
  locationNote: z.string().max(500).optional(),
  visitType: z.enum(groupVisitTypes).optional()
});

visitsRouter.post("/visits/:visitId/amend", requireAuth("visits:amend"), async (req, res, next) => {
  try {
    const visit = await prisma.groupVisit.findFirst({
      where: { AND: [{ id: req.params.visitId as string }, { group: scopeGroupWhere(req.user) }] },
      select: { id: true }
    });
    if (!visit) throw new ApiHttpError(404, "VISIT_NOT_FOUND", "That visit does not exist.");

    const payload = amendSchema.parse(req.body);
    const updated = await amendGroupVisit({
      visitId: visit.id,
      reason: payload.reason,
      changes: {
        ...(payload.notes !== undefined ? { notes: payload.notes } : {}),
        ...(payload.locationNote !== undefined ? { locationNote: payload.locationNote } : {}),
        ...(payload.visitType !== undefined ? { visitType: payload.visitType } : {})
      },
      actorUserId: req.user?.id
    });
    ok(res, { visit: serializeVisit(updated) });
  } catch (error) {
    next(error);
  }
});
