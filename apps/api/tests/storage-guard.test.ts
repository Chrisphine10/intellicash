import { describe, expect, it } from "vitest";
import {
  STORAGE_CRITICAL_FRACTION,
  STORAGE_LOW_FRACTION,
  checkUploadStorage,
  judgeStorage
} from "../src/services/storage-guard";

/**
 * The disk-fill guard.
 *
 * The decision is a pure function precisely so it can be tested: arranging an
 * actually-full disk in a test suite is not something anyone should have to do,
 * and a guard that is never exercised is a guard that does not work.
 */

const GB = 1024 * 1024 * 1024;

describe("judgeStorage", () => {
  it("accepts uploads on a healthy disk", () => {
    const verdict = judgeStorage(50 * GB, 100 * GB);

    expect(verdict.level).toBe("OK");
    expect(verdict.acceptsUploads).toBe(true);
    expect(verdict.freeFraction).toBeCloseTo(0.5);
  });

  it("stops uploads below the low threshold", () => {
    const verdict = judgeStorage(14 * GB, 100 * GB);

    expect(verdict.level).toBe("LOW");
    expect(verdict.acceptsUploads).toBe(false);
    // The message reaches a human, so it should say what to do about it.
    expect(verdict.message).toMatch(/once space is freed/);
  });

  it("escalates below the critical threshold", () => {
    // Not merely "no more photos" — at this point the SQLite file sharing the
    // volume is at risk, which is a different order of problem.
    const verdict = judgeStorage(4 * GB, 100 * GB);

    expect(verdict.level).toBe("CRITICAL");
    expect(verdict.acceptsUploads).toBe(false);
    expect(verdict.message).toMatch(/protect the database/);
  });

  it("treats the thresholds as inclusive lower bounds", () => {
    // Exactly at the threshold is still acceptable; a hair under is not.
    expect(judgeStorage(STORAGE_LOW_FRACTION * 100 * GB, 100 * GB).level).toBe("OK");
    expect(judgeStorage(STORAGE_LOW_FRACTION * 100 * GB - 1, 100 * GB).level).toBe("LOW");
    expect(judgeStorage(STORAGE_CRITICAL_FRACTION * 100 * GB, 100 * GB).level).toBe("LOW");
    expect(judgeStorage(STORAGE_CRITICAL_FRACTION * 100 * GB - 1, 100 * GB).level).toBe(
      "CRITICAL"
    );
  });

  it("allows uploads when the disk cannot be measured", () => {
    // Failing to measure is not evidence of being full. Refusing every upload
    // because statfs misbehaved would be a self-inflicted outage.
    for (const [free, total] of [
      [0, 0],
      [Number.NaN, 100 * GB],
      [10 * GB, Number.NaN],
      [10 * GB, -1]
    ]) {
      const verdict = judgeStorage(free as number, total as number);
      expect(verdict.acceptsUploads).toBe(true);
      expect(verdict.level).toBe("OK");
    }
  });

  it("clamps a nonsensical free-space reading rather than reporting over 100%", () => {
    const verdict = judgeStorage(200 * GB, 100 * GB);
    expect(verdict.freeFraction).toBe(1);
    expect(verdict.acceptsUploads).toBe(true);
  });

  it("never divides by zero", () => {
    expect(() => judgeStorage(0, 0)).not.toThrow();
    expect(judgeStorage(0, 0).freeFraction).toBe(1);
  });

  it("reports a real full disk as critical, not merely low", () => {
    const verdict = judgeStorage(0, 100 * GB);
    expect(verdict.level).toBe("CRITICAL");
    expect(verdict.acceptsUploads).toBe(false);
  });
});

describe("checkUploadStorage", () => {
  it("measures the real volume without throwing", async () => {
    const verdict = await checkUploadStorage();

    expect(["OK", "LOW", "CRITICAL"]).toContain(verdict.level);
    expect(typeof verdict.acceptsUploads).toBe("boolean");
  });

  it("survives a path that does not exist", async () => {
    // A misconfigured UPLOAD_ROOT must not take the API down on every request.
    const verdict = await checkUploadStorage("/definitely/not/a/real/path/xyzzy");

    expect(verdict.acceptsUploads).toBe(true);
    expect(verdict.level).toBe("OK");
  });
});
