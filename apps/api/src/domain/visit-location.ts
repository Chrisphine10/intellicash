import type { VisitLocationOutcome } from "@intellicash/shared";

/**
 * Decides whether a field visit happened where it says it did.
 *
 * Pure: no database, no clock, no I/O. Everything it needs is an argument, so
 * the rule can be reasoned about and tested on its own — the same split the
 * credit rating uses between `credit-rating-contract.ts` and its service.
 *
 * **The client's opinion is never taken.** The phone reports a coordinate and
 * an accuracy; the verdict is computed here. This is the deliberate contrast
 * with `Meeting.gpsCompliant`, which is a boolean the client asserts and
 * nothing on the server ever checks — a field that looks like evidence and
 * isn't.
 *
 * Distance alone is not the whole answer. A fix accurate to ±500 m that lands
 * 200 m away tells you nothing: the phone could be standing in the right place.
 * Treating that as "outside" would brand honest agents as liars, and treating
 * it as "inside" would let a fabricated visit through. It gets its own outcome
 * so a reviewer can see the difference.
 */

const EARTH_RADIUS_M = 6_371_008.8;

/** Beyond this, a fix is too vague to judge a geofence of typical size. */
export const LOW_ACCURACY_THRESHOLD_M = 200;

/** Used when a group has a location but no explicit radius. */
export const DEFAULT_GEOFENCE_RADIUS_M = 50;

export type LatLng = { latitude: number; longitude: number };

export type VisitLocationInput = {
  /** Where the device says it is. Null when no fix was obtained. */
  device: (LatLng & { accuracyM?: number | null }) | null;
  /** The group's registered meeting point. Null when never recorded. */
  group: LatLng | null;
  /** The group's allowed radius in metres. */
  radiusM?: number | null;
};

export type VisitLocationVerdict = {
  outcome: VisitLocationOutcome;
  /** Metres from the group's registered point, or null when not computable. */
  distanceM: number | null;
  withinGeofence: boolean;
  /** Non-fatal observations for a reviewer. */
  flags: string[];
};

function toRadians(degrees: number) {
  return (degrees * Math.PI) / 180;
}

/** Great-circle distance in metres. */
export function haversineMetres(a: LatLng, b: LatLng): number {
  const dLat = toRadians(b.latitude - a.latitude);
  const dLon = toRadians(b.longitude - a.longitude);
  const lat1 = toRadians(a.latitude);
  const lat2 = toRadians(b.latitude);

  const sinLat = Math.sin(dLat / 2);
  const sinLon = Math.sin(dLon / 2);
  const h = sinLat * sinLat + Math.cos(lat1) * Math.cos(lat2) * sinLon * sinLon;

  return 2 * EARTH_RADIUS_M * Math.asin(Math.min(1, Math.sqrt(h)));
}

/** Rejects the shapes that would otherwise silently become a point in the sea. */
export function isUsableCoordinate(value: LatLng | null | undefined): value is LatLng {
  if (!value) return false;
  const { latitude, longitude } = value;
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return false;
  if (latitude < -90 || latitude > 90) return false;
  if (longitude < -180 || longitude > 180) return false;
  // A device that has not got a fix commonly reports exactly 0,0 — a real
  // place in the Gulf of Guinea, and never where a Kenyan VSLA meets.
  if (latitude === 0 && longitude === 0) return false;
  return true;
}

export function adjudicateVisitLocation(input: VisitLocationInput): VisitLocationVerdict {
  const flags: string[] = [];
  const device = isUsableCoordinate(input.device) ? input.device : null;
  const group = isUsableCoordinate(input.group) ? input.group : null;

  if (!device) {
    // No fix is a fact about the phone, not an accusation. Recorded, not blocked:
    // a visit under a tin roof in a valley must still be filable.
    return { outcome: "NO_DEVICE_FIX", distanceM: null, withinGeofence: false, flags };
  }

  if (!group) {
    // Nothing to compare against. Register the group's location and future
    // visits become checkable; this one cannot be.
    flags.push("GROUP_LOCATION_UNKNOWN");
    return { outcome: "NO_GROUP_LOCATION", distanceM: null, withinGeofence: false, flags };
  }

  const distanceM = haversineMetres(device, group);
  const radiusM =
    typeof input.radiusM === "number" && Number.isFinite(input.radiusM) && input.radiusM > 0
      ? input.radiusM
      : DEFAULT_GEOFENCE_RADIUS_M;

  const accuracyM = input.device?.accuracyM;
  const accuracyIsPoor =
    typeof accuracyM === "number" && Number.isFinite(accuracyM) && accuracyM > LOW_ACCURACY_THRESHOLD_M;

  if (accuracyIsPoor) {
    // Too vague to convict or acquit. Keep the distance for the reviewer, but
    // do not let a ±500 m fix decide either way.
    flags.push("LOW_ACCURACY_FIX");
    return { outcome: "LOW_ACCURACY", distanceM, withinGeofence: false, flags };
  }

  // Allow the device's own margin of error, so a good fix at the edge of the
  // fence is not failed by a metre it cannot resolve.
  const tolerance = typeof accuracyM === "number" && Number.isFinite(accuracyM) && accuracyM > 0 ? accuracyM : 0;
  const withinGeofence = distanceM <= radiusM + tolerance;

  if (!withinGeofence) {
    flags.push("OUTSIDE_GEOFENCE");
    if (distanceM > 5_000) flags.push("FAR_FROM_GROUP");
  }

  return {
    outcome: withinGeofence ? "WITHIN_GEOFENCE" : "OUTSIDE_GEOFENCE",
    distanceM,
    withinGeofence,
    flags
  };
}
