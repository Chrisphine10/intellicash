import bcrypt from "bcryptjs";
import type { Prisma } from "@prisma/client";
import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";
import { adjudicateVisitLocation } from "../domain/visit-location";
import { appendAuditEvent } from "./audit-service";

/**
 * Group visit PINs and visit submission.
 *
 * The rules that matter live here rather than in the route handler so the
 * route stays a thin translation of HTTP to domain, and so idempotency and
 * lockout can be tested without going through Express.
 */

/** Same cost as member PINs — see member-pin-service.ts. */
const PIN_COST = 12;

/** Consecutive failures before the group's PIN locks. */
export const PIN_LOCKOUT_THRESHOLD = 5;

/** How long a lockout lasts. Long enough to defeat a script, short enough that
 * a genuine visit is not abandoned. */
export const PIN_LOCKOUT_MINUTES = 15;

export const VISIT_PIN_PATTERN = /^\d{4}$/;

export function isValidVisitPin(pin: string) {
  return VISIT_PIN_PATTERN.test(pin);
}

/**
 * PINs a keypad produces but nobody should be able to set.
 *
 * A group talked through setting a PIN over the phone will otherwise reach for
 * 1234 every time, and the PIN is the only thing standing between a real visit
 * and one typed up at home.
 */
const FORBIDDEN_PINS = new Set([
  "0000", "1111", "2222", "3333", "4444", "5555", "6666", "7777", "8888", "9999",
  "1234", "2345", "3456", "4567", "5678", "6789", "0123", "9876", "4321"
]);

export function isGuessableVisitPin(pin: string) {
  return FORBIDDEN_PINS.has(pin);
}

export async function setGroupVisitPin(options: {
  groupId: string;
  pin: string;
  actorUserId?: string | null;
}) {
  const { groupId, pin, actorUserId } = options;

  if (!isValidVisitPin(pin)) {
    throw new ApiHttpError(400, "INVALID_VISIT_PIN", "The visit PIN must be exactly 4 digits.");
  }
  if (isGuessableVisitPin(pin)) {
    throw new ApiHttpError(
      400,
      "GUESSABLE_VISIT_PIN",
      "Choose a less obvious PIN — not repeated digits or a simple run."
    );
  }

  const pinHash = await bcrypt.hash(pin, PIN_COST);
  const row = await prisma.groupVisitPin.upsert({
    where: { groupId },
    create: { groupId, pinHash, setByUserId: actorUserId ?? null },
    // Setting a new PIN clears any lockout: the group has demonstrably regained
    // control of it, so continuing to punish the previous guessing is pointless.
    update: {
      pinHash,
      setByUserId: actorUserId ?? null,
      setAt: new Date(),
      failedCount: 0,
      lockedUntil: null,
      lastFailedAt: null
    }
  });

  await appendAuditEvent({
    actorUserId: actorUserId ?? undefined,
    entityType: "GROUP",
    entityId: groupId,
    type: "GROUP_VISIT_PIN_SET",
    // Never the PIN, and never the hash.
    payload: { groupId, setAt: row.setAt.toISOString() }
  });

  return { groupId, setAt: row.setAt.toISOString(), configured: true };
}

export type VisitPinVerification =
  | { ok: true }
  | { ok: false; reason: "NOT_SET" | "LOCKED"; retryAfterSeconds?: number }
  | { ok: false; reason: "WRONG"; attemptsRemaining: number };

/**
 * Checks a group's visit PIN and maintains the lockout counter.
 *
 * The counter lives in the database rather than in memory so a restart — or a
 * second instance — cannot be used to reset it.
 */
export async function verifyGroupVisitPin(options: {
  groupId: string;
  pin: string;
  actorUserId?: string | null;
  now?: Date;
}): Promise<VisitPinVerification> {
  const { groupId, pin, actorUserId } = options;
  const now = options.now ?? new Date();

  const row = await prisma.groupVisitPin.findUnique({ where: { groupId } });
  if (!row) return { ok: false, reason: "NOT_SET" };

  if (row.lockedUntil && row.lockedUntil > now) {
    return {
      ok: false,
      reason: "LOCKED",
      retryAfterSeconds: Math.ceil((row.lockedUntil.getTime() - now.getTime()) / 1000)
    };
  }

  const matches = isValidVisitPin(pin) && (await bcrypt.compare(pin, row.pinHash));

  if (matches) {
    if (row.failedCount > 0 || row.lockedUntil) {
      await prisma.groupVisitPin.update({
        where: { groupId },
        data: { failedCount: 0, lockedUntil: null }
      });
    }
    return { ok: true };
  }

  const failedCount = row.failedCount + 1;
  const locks = failedCount >= PIN_LOCKOUT_THRESHOLD;
  await prisma.groupVisitPin.update({
    where: { groupId },
    data: {
      failedCount: locks ? 0 : failedCount,
      lastFailedAt: now,
      lockedUntil: locks ? new Date(now.getTime() + PIN_LOCKOUT_MINUTES * 60_000) : null
    }
  });

  await appendAuditEvent({
    actorUserId: actorUserId ?? undefined,
    entityType: "GROUP",
    entityId: groupId,
    type: "GROUP_VISIT_PIN_VERIFY_FAILED",
    payload: { groupId, failedCount, locked: locks }
  });

  if (locks) {
    return { ok: false, reason: "LOCKED", retryAfterSeconds: PIN_LOCKOUT_MINUTES * 60 };
  }
  return { ok: false, reason: "WRONG", attemptsRemaining: PIN_LOCKOUT_THRESHOLD - failedCount };
}

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

/** The wire shape. Note the absence of anything derived from the PIN. */
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
