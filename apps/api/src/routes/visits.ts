import { Router } from "express";
import { z } from "zod";
import { groupVisitTypes } from "@intellicash/shared";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { visitPinRateLimit } from "../middleware/rate-limit";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import {
  PIN_LOCKOUT_MINUTES,
  amendGroupVisit,
  serializeVisit,
  setGroupVisitPin,
  submitGroupVisit,
  verifyGroupVisitPin
} from "../services/visit-service";

export const visitsRouter = Router();

/**
 * Field-agent visits to a group.
 *
 * Two rules shape every handler here:
 *
 * 1. **Scope before anything else.** `scopeGroupWhere` already restricts an
 *    agent to their own caseload, so a group outside it is a 404 rather than a
 *    403 — the house convention, and the right one: "forbidden" confirms the
 *    group exists to someone who should not know that.
 * 2. **The visited party owns the PIN.** An agent can verify it and cannot set
 *    it. An agent who could set it could attest to a visit they never made,
 *    which is the single thing this mechanism exists to prevent.
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

/**
 * Who may set a group's visit PIN.
 *
 * Explicitly refuses VILLAGE_AGENT even if a deployment has granted the
 * permission through the role-permission templates — the check is on the role,
 * not only on the permission string, because this particular separation is
 * what makes the whole attestation meaningful.
 */
function assertMaySetPin(user: AuthenticatedUser | undefined, groupId: string) {
  if (!user) throw new ApiHttpError(401, "UNAUTHENTICATED", "Authentication is required.");

  if (user.role === "VILLAGE_AGENT") {
    throw new ApiHttpError(
      403,
      "AGENT_CANNOT_SET_VISIT_PIN",
      "A field agent cannot set the PIN that confirms their own visit. Ask the group or an administrator."
    );
  }

  if (user.permissions.includes("groups:write")) return;
  if (
    user.role === "GROUP_ACCOUNT" &&
    user.groupId === groupId &&
    user.permissions.includes("group-pin:write")
  ) {
    return;
  }

  throw new ApiHttpError(
    403,
    "FORBIDDEN",
    "Only the group's own account or a platform admin may set the visit PIN."
  );
}

const pinSchema = z.object({
  // Four digits, entered on a numeric keypad. A string, not a number, because
  // 0042 is a valid PIN and JSON would hand us 42.
  pin: z.string().regex(/^\d{4}$/, "The visit PIN must be exactly 4 digits.")
});

visitsRouter.get(
  "/groups/:groupId/visit-pin",
  requireAuth("visits:read"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      const row = await prisma.groupVisitPin.findUnique({ where: { groupId: group.id } });
      // Status only. The hash never leaves the database.
      ok(res, {
        group: { id: group.id, name: group.name, code: group.code },
        configured: Boolean(row),
        setAt: row?.setAt.toISOString() ?? null,
        locked: Boolean(row?.lockedUntil && row.lockedUntil > new Date()),
        canSet:
          req.user?.role !== "VILLAGE_AGENT" &&
          (Boolean(req.user?.permissions.includes("groups:write")) ||
            (req.user?.role === "GROUP_ACCOUNT" && req.user?.groupId === group.id))
      });
    } catch (error) {
      next(error);
    }
  }
);

visitsRouter.put(
  "/groups/:groupId/visit-pin",
  requireAuth("visits:read"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      assertMaySetPin(req.user, group.id);
      const payload = pinSchema.parse(req.body);
      ok(res, await setGroupVisitPin({ groupId: group.id, pin: payload.pin, actorUserId: req.user?.id }));
    } catch (error) {
      next(error);
    }
  }
);

visitsRouter.post(
  "/groups/:groupId/visit-pin/verify",
  requireAuth("visits:write"),
  visitPinRateLimit,
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      const payload = pinSchema.parse(req.body);
      const result = await verifyGroupVisitPin({
        groupId: group.id,
        pin: payload.pin,
        actorUserId: req.user?.id
      });

      if (result.ok) {
        ok(res, { verified: true });
        return;
      }

      if (result.reason === "NOT_SET") {
        throw new ApiHttpError(
          409,
          "VISIT_PIN_NOT_SET",
          "This group has not set a visit PIN yet. Ask an official or an administrator to set one."
        );
      }
      if (result.reason === "LOCKED") {
        throw new ApiHttpError(
          423,
          "VISIT_PIN_LOCKED",
          `Too many wrong attempts. Try again in about ${PIN_LOCKOUT_MINUTES} minutes.`
        );
      }
      throw new ApiHttpError(401, "VISIT_PIN_INCORRECT", "That PIN is not correct.");
    } catch (error) {
      next(error);
    }
  }
);

const submitSchema = z.object({
  // Minted on the phone when the opening PIN passes and never regenerated.
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
        group: { select: { id: true, name: true, code: true } },
        revisions: { orderBy: { revision: "desc" } }
      }
    });
    if (!visit) throw new ApiHttpError(404, "VISIT_NOT_FOUND", "That visit does not exist.");

    ok(res, {
      visit: serializeVisit(visit),
      group: visit.group,
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
