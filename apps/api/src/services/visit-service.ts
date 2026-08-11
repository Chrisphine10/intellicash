import type { Prisma } from "@prisma/client";
import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";
import { adjudicateVisitLocation } from "../domain/visit-location";
import { appendAuditEvent } from "./audit-service";

/**
 * Visit submission.
 *
 * The rules that matter live here rather than in the route handler so the
 * route stays a thin translation of HTTP to domain, and so idempotency can be
 * tested without going through Express.
 *
 * A visit used to open with a 4-digit PIN held by the group — their attestation
 * that the agent was standing in front of them. That was removed on the owner's
 * instruction; PINs in this system belong to meetings. What remains as evidence
 * is the agent's authenticated session, the server-adjudicated GPS fix, the
 * device id and the timestamp. All four are the agent's own, so they establish
 * where and when, not that the group agreed — worth knowing when reading a
 * disputed visit.
 */

export type SubmitVisitInput = {
  groupId: string;
  clientRequestId: string;
  visitType: string;
  startedAt: Date;
  completedAt?: Date | null;
  device?: { latitude: number; longitude: number; accuracyM?: number | null } | null;
  locationCapturedAt?: Date | null;
  locationNote?: string | null;
  deviceId?: string | null;
  notes?: string | null;
  villageAgentId?: string | null;
  submittedByUserId?: string | null;
};

/**
 * Records a visit, or returns the one already recorded under the same
 * `clientRequestId`.
 *
 * Idempotency is the point. A phone in the field retries on every reconnect,
 * and a duplicate visit is not a cosmetic problem: it doubles a group's visit
 * count and corrupts coverage reporting. The repeat is answered with the
 * existing record and `created: false` — deliberately not a 409, because a
 * client that treats an error as failure would retry forever.
 */
export async function submitGroupVisit(input: SubmitVisitInput) {
  const existing = await prisma.groupVisit.findUnique({
    where: { clientRequestId: input.clientRequestId }
  });
  if (existing) {
    if (existing.groupId !== input.groupId) {
      // The same id cannot mean two different visits. This is a client bug, and
      // silently rewriting either record would be worse than refusing.
      throw new ApiHttpError(
        409,
        "VISIT_REQUEST_ID_REUSED",
        "That visit reference already belongs to a different group."
      );
    }
    return { visit: existing, created: false };
  }

  const group = await prisma.group.findUnique({
    where: { id: input.groupId },
    select: { id: true, gpsLatitude: true, gpsLongitude: true, gpsRadiusMeters: true }
  });
  if (!group) {
    throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
  }

  const verdict = adjudicateVisitLocation({
    device: input.device
      ? {
          latitude: input.device.latitude,
          longitude: input.device.longitude,
          accuracyM: input.device.accuracyM ?? null
        }
      : null,
    group:
      typeof group.gpsLatitude === "number" && typeof group.gpsLongitude === "number"
        ? { latitude: group.gpsLatitude, longitude: group.gpsLongitude }
        : null,
    radiusM: group.gpsRadiusMeters
  });

  const data: Prisma.GroupVisitUncheckedCreateInput = {
    groupId: input.groupId,
    villageAgentId: input.villageAgentId ?? null,
    submittedByUserId: input.submittedByUserId ?? null,
    clientRequestId: input.clientRequestId,
    visitType: input.visitType,
    status: "SUBMITTED",
    startedAt: input.startedAt,
    completedAt: input.completedAt ?? null,
    deviceLatitude: input.device?.latitude ?? null,
    deviceLongitude: input.device?.longitude ?? null,
    locationAccuracyM: input.device?.accuracyM ?? null,
    locationCapturedAt: input.locationCapturedAt ?? null,
    distanceFromGroupM: verdict.distanceM,
    locationOutcome: verdict.outcome,
    withinGeofence: verdict.withinGeofence,
    locationNote: input.locationNote ?? null,
    authenticityFlagsJson: JSON.stringify(verdict.flags),
    deviceId: input.deviceId ?? null,
    notes: input.notes ?? null
  };

  try {
    const visit = await prisma.groupVisit.create({ data });
    await appendAuditEvent({
      actorUserId: input.submittedByUserId ?? undefined,
      entityType: "GROUP_VISIT",
      entityId: visit.id,
      type: "GROUP_VISIT_SUBMITTED",
      payload: {
        groupId: visit.groupId,
        visitType: visit.visitType,
        locationOutcome: visit.locationOutcome,
        withinGeofence: visit.withinGeofence,
        distanceFromGroupM: visit.distanceFromGroupM
      }
    });
    return { visit, created: true };
  } catch (error) {
    // Two devices — or two retries racing — can pass the findUnique above at the
    // same moment. The unique index is the real guard; losing the race is a
    // success, not an error.
    if (isUniqueConstraintError(error)) {
      const winner = await prisma.groupVisit.findUnique({
        where: { clientRequestId: input.clientRequestId }
      });
      if (winner) return { visit: winner, created: false };
    }
    throw error;
  }
}

function isUniqueConstraintError(error: unknown) {
  return (
    typeof error === "object" &&
    error !== null &&
    (error as { code?: string }).code === "P2002"
  );
}

/**
 * Records a correction without destroying what was originally reported.
 */
export async function amendGroupVisit(options: {
  visitId: string;
  reason?: string | null;
  changes: { notes?: string | null; locationNote?: string | null; visitType?: string };
  actorUserId?: string | null;
}) {
  const { visitId, reason, changes, actorUserId } = options;

  // The snapshot and the update must land together or not at all — a revision
  // without its bump, or a bump without its snapshot, both lose the record of
  // what was originally reported.
  const { updated, fromRevision } = await prisma.$transaction(async (tx) => {
    const current = await tx.groupVisit.findUnique({ where: { id: visitId } });
    if (!current) {
      throw new ApiHttpError(404, "VISIT_NOT_FOUND", "That visit does not exist.");
    }

    await tx.groupVisitRevision.create({
      data: {
        visitId,
        revision: current.revision,
        snapshotJson: JSON.stringify(current),
        reason: reason ?? null,
        amendedByUserId: actorUserId ?? null
      }
    });

    const row = await tx.groupVisit.update({
      where: { id: visitId },
      data: {
        ...(changes.notes !== undefined ? { notes: changes.notes } : {}),
        ...(changes.locationNote !== undefined ? { locationNote: changes.locationNote } : {}),
        ...(changes.visitType !== undefined ? { visitType: changes.visitType } : {}),
        status: "AMENDED",
        revision: current.revision + 1
      }
    });

    return { updated: row, fromRevision: current.revision };
  });

  // Audited AFTER the commit, never inside it. `appendAuditEvent` uses the
  // global client, so on SQLite it waits on the write lock the transaction is
  // already holding — the transaction then expires and the amendment fails
  // even though nothing was wrong with it. Auditing outside is also the house
  // convention: an audit write failing must not roll back the business write.
  await appendAuditEvent({
    actorUserId: actorUserId ?? undefined,
    entityType: "GROUP_VISIT",
    entityId: visitId,
    type: "GROUP_VISIT_AMENDED",
    payload: { fromRevision, toRevision: updated.revision, reason: reason ?? null }
  });

  return updated;
}

/** The wire shape. */
export function serializeVisit(visit: {
  id: string;
  groupId: string;
  villageAgentId: string | null;
  clientRequestId: string;
  visitType: string;
  status: string;
  startedAt: Date;
  completedAt: Date | null;
  submittedAt: Date;
  deviceLatitude: number | null;
  deviceLongitude: number | null;
  locationAccuracyM: number | null;
  distanceFromGroupM: number | null;
  locationOutcome: string;
  withinGeofence: boolean;
  locationNote: string | null;
  authenticityFlagsJson: string;
  notes: string | null;
  revision: number;
}) {
  let flags: unknown = [];
  try {
    flags = JSON.parse(visit.authenticityFlagsJson);
  } catch {
    flags = [];
  }

  return {
    id: visit.id,
    groupId: visit.groupId,
    villageAgentId: visit.villageAgentId,
    clientRequestId: visit.clientRequestId,
    visitType: visit.visitType,
    status: visit.status,
    startedAt: visit.startedAt.toISOString(),
    completedAt: visit.completedAt?.toISOString() ?? null,
    submittedAt: visit.submittedAt.toISOString(),
    location: {
      latitude: visit.deviceLatitude,
      longitude: visit.deviceLongitude,
      accuracyM: visit.locationAccuracyM,
      distanceFromGroupM: visit.distanceFromGroupM,
      outcome: visit.locationOutcome,
      withinGeofence: visit.withinGeofence,
      note: visit.locationNote
    },
    authenticityFlags: Array.isArray(flags) ? flags : [],
    notes: visit.notes,
    revision: visit.revision
  };
}
