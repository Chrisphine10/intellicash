import { describe, expect, it } from "vitest";
import {
  DEFAULT_GEOFENCE_RADIUS_M,
  adjudicateVisitLocation,
  haversineMetres,
  isUsableCoordinate
} from "../src/domain/visit-location";

/**
 * The visit location verdict is what stops a field visit being filed from a
 * sofa, so it is worth pinning precisely. It is also the one place a wrong
 * answer accuses an honest agent of fraud, which is why "cannot tell" is a
 * first-class outcome rather than being folded into "outside".
 */

// A real pair: the Embu county offices and a point about 300 m away.
const embu = { latitude: -0.5389, longitude: 37.4575 };

describe("haversine", () => {
  it("is zero for the same point", () => {
    expect(haversineMetres(embu, embu)).toBe(0);
  });

  it("matches a known distance", () => {
    // Nairobi CBD to Mombasa: ~440 km. Tolerance is generous because the
    // assertion is "the formula is right", not "to the metre".
    const nairobi = { latitude: -1.2864, longitude: 36.8172 };
    const mombasa = { latitude: -4.0435, longitude: 39.6682 };
    const km = haversineMetres(nairobi, mombasa) / 1000;
    expect(km).toBeGreaterThan(430);
    expect(km).toBeLessThan(450);
  });

  it("is symmetric", () => {
    const a = { latitude: -0.5, longitude: 37.4 };
    const b = { latitude: -0.6, longitude: 37.5 };
    expect(haversineMetres(a, b)).toBeCloseTo(haversineMetres(b, a), 6);
  });

  it("handles a small offset at VSLA scale", () => {
    // ~0.001 degrees of latitude is about 111 m.
    const near = { latitude: embu.latitude + 0.001, longitude: embu.longitude };
    const metres = haversineMetres(embu, near);
    expect(metres).toBeGreaterThan(100);
    expect(metres).toBeLessThan(120);
  });
});

describe("coordinate sanity", () => {
  it("rejects null island", () => {
    // A device with no fix commonly reports exactly 0,0 — a real place in the
    // Gulf of Guinea, and never where a Kenyan VSLA meets. Treated as no fix.
    expect(isUsableCoordinate({ latitude: 0, longitude: 0 })).toBe(false);
  });

  it("rejects out-of-range and non-finite values", () => {
    expect(isUsableCoordinate({ latitude: 91, longitude: 0 })).toBe(false);
    expect(isUsableCoordinate({ latitude: 0, longitude: 181 })).toBe(false);
    expect(isUsableCoordinate({ latitude: Number.NaN, longitude: 37 })).toBe(false);
    expect(isUsableCoordinate(null)).toBe(false);
  });

  it("accepts a genuine southern-hemisphere coordinate", () => {
    expect(isUsableCoordinate(embu)).toBe(true);
  });
});

describe("visit location verdict", () => {
  it("passes a visit standing at the group's meeting place", () => {
    const verdict = adjudicateVisitLocation({
      device: { ...embu, accuracyM: 8 },
      group: embu,
      radiusM: 50
    });
    expect(verdict.outcome).toBe("WITHIN_GEOFENCE");
    expect(verdict.withinGeofence).toBe(true);
    expect(verdict.distanceM).toBeCloseTo(0, 3);
    expect(verdict.flags).toEqual([]);
  });

  it("fails a visit filed from far away", () => {
    const nairobi = { latitude: -1.2864, longitude: 36.8172 };
    const verdict = adjudicateVisitLocation({
      device: { ...nairobi, accuracyM: 10 },
      group: embu,
      radiusM: 50
    });
    expect(verdict.outcome).toBe("OUTSIDE_GEOFENCE");
    expect(verdict.withinGeofence).toBe(false);
    expect(verdict.flags).toContain("OUTSIDE_GEOFENCE");
    expect(verdict.flags).toContain("FAR_FROM_GROUP");
  });

  it("does not convict on a fix too vague to judge", () => {
    // ±500 m accuracy at 200 m distance: the phone could be standing in the
    // right place. Calling that "outside" would brand an honest agent a liar.
    const near = { latitude: embu.latitude + 0.0018, longitude: embu.longitude };
    const verdict = adjudicateVisitLocation({
      device: { ...near, accuracyM: 500 },
      group: embu,
      radiusM: 50
    });
    expect(verdict.outcome).toBe("LOW_ACCURACY");
    expect(verdict.withinGeofence).toBe(false);
    expect(verdict.distanceM).not.toBeNull();
    expect(verdict.flags).toContain("LOW_ACCURACY_FIX");
  });

  it("allows the device's own margin of error at the fence edge", () => {
    // 60 m away, fence 50 m, fix good to ±20 m. Within the fence once the
    // margin the device itself reports is allowed for.
    const edge = { latitude: embu.latitude + 0.00054, longitude: embu.longitude };
    const verdict = adjudicateVisitLocation({
      device: { ...edge, accuracyM: 20 },
      group: embu,
      radiusM: 50
    });
    expect(verdict.distanceM!).toBeGreaterThan(50);
    expect(verdict.withinGeofence).toBe(true);
    expect(verdict.outcome).toBe("WITHIN_GEOFENCE");
  });

  it("records a missing fix without blaming the agent", () => {
    // A meeting under a tin roof in a valley must still be filable.
    const verdict = adjudicateVisitLocation({ device: null, group: embu, radiusM: 50 });
    expect(verdict.outcome).toBe("NO_DEVICE_FIX");
    expect(verdict.distanceM).toBeNull();
    expect(verdict.flags).toEqual([]);
  });

  it("says so when the group has no registered location", () => {
    const verdict = adjudicateVisitLocation({
      device: { ...embu, accuracyM: 8 },
      group: null
    });
    expect(verdict.outcome).toBe("NO_GROUP_LOCATION");
    expect(verdict.withinGeofence).toBe(false);
    expect(verdict.flags).toContain("GROUP_LOCATION_UNKNOWN");
  });

  it("falls back to the default radius when none is set", () => {
    // 40 m away with no configured radius: inside the 50 m default.
    const near = { latitude: embu.latitude + 0.00036, longitude: embu.longitude };
    expect(
      adjudicateVisitLocation({ device: { ...near, accuracyM: 5 }, group: embu, radiusM: null })
        .withinGeofence
    ).toBe(true);
    expect(DEFAULT_GEOFENCE_RADIUS_M).toBe(50);
  });

  it("ignores a nonsensical radius rather than trusting it", () => {
    // A zero or negative radius would make every visit fail; treat it as unset.
    const verdict = adjudicateVisitLocation({
      device: { ...embu, accuracyM: 5 },
      group: embu,
      radiusM: 0
    });
    expect(verdict.withinGeofence).toBe(true);
  });

  it("treats a 0,0 device reading as no fix, not as the Gulf of Guinea", () => {
    const verdict = adjudicateVisitLocation({
      device: { latitude: 0, longitude: 0, accuracyM: 5 },
      group: embu,
      radiusM: 50
    });
    expect(verdict.outcome).toBe("NO_DEVICE_FIX");
  });
});
