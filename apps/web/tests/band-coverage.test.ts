import { describe, expect, it } from "vitest";
import { bandCoverage, coverageIsComplete } from "../src/lib/band-coverage";

/**
 * The band strip exists to make one specific failure visible: a score with no
 * band. The server refuses to publish a template with a gap, but an author
 * previously only saw a line of validation text naming a path — which says
 * something is wrong, not where along the range.
 */
const bands = [
  { key: "weak", label: "Weak", minPoints: 0, maxPoints: 22 },
  { key: "fair", label: "Fair", minPoints: 23, maxPoints: 45 },
  { key: "good", label: "Good", minPoints: 46, maxPoints: 68 },
  { key: "excellent", label: "Excellent", minPoints: 69, maxPoints: 92 }
];

describe("bandCoverage", () => {
  it("reports one segment per band when the range is covered exactly", () => {
    const segments = bandCoverage(bands, 92);
    expect(segments).toHaveLength(4);
    expect(segments.every((s) => s.kind === "band")).toBe(true);
    expect(coverageIsComplete(segments)).toBe(true);
  });

  it("adds up to the whole range", () => {
    const total = bandCoverage(bands, 92).reduce((sum, s) => sum + s.widthPercent, 0);
    expect(total).toBeCloseTo(100, 5);
  });

  it("finds a gap, and says exactly where it is", () => {
    // 23 is claimed by nobody — the score that would come back unbanded.
    const withGap = bands.map((b) => (b.key === "fair" ? { ...b, minPoints: 24 } : b));
    const segments = bandCoverage(withGap, 92);
    const gap = segments.find((s) => s.kind === "gap");

    expect(gap).toBeDefined();
    expect(gap!.from).toBe(23);
    expect(gap!.to).toBe(23);
    expect(gap!.label).toBe("No band for 23–23");
    expect(coverageIsComplete(segments)).toBe(false);
  });

  it("finds an overlap and names both bands", () => {
    const overlapping = bands.map((b) => (b.key === "fair" ? { ...b, minPoints: 20 } : b));
    const segments = bandCoverage(overlapping, 92);
    const overlap = segments.find((s) => s.kind === "overlap");

    expect(overlap).toBeDefined();
    expect(overlap!.from).toBe(20);
    expect(overlap!.to).toBe(22);
    expect(overlap!.label).toContain("Weak");
    expect(overlap!.label).toContain("Fair");
  });

  it("treats a band that stops short of the total as a trailing gap", () => {
    // The commonest authoring mistake: add a question, the total moves, the
    // top band no longer reaches it.
    const segments = bandCoverage(bands, 95);
    const gap = segments.find((s) => s.kind === "gap");
    expect(gap!.from).toBe(93);
    expect(gap!.to).toBe(95);
  });

  it("returns nothing to draw when there is no range yet", () => {
    // A brand-new draft with no questions has a total of 0.
    expect(bandCoverage(bands, 0)).toEqual([]);
    expect(coverageIsComplete([])).toBe(false);
  });

  it("does not run off the end when a band overshoots the total", () => {
    const segments = bandCoverage([{ key: "all", label: "All", minPoints: 0, maxPoints: 500 }], 10);
    expect(segments).toHaveLength(1);
    expect(segments[0]!.to).toBe(10);
    expect(segments[0]!.widthPercent).toBeCloseTo(100, 5);
  });
});
