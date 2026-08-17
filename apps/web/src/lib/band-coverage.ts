/**
 * Turns a band list into something that can be drawn.
 *
 * Bands must cover every score from 0 to the total with no gap and no overlap.
 * The server already refuses to publish otherwise, but until now the only way
 * an author saw a problem was a line of validation text naming a path — which
 * tells you that something is wrong, not *where* along the range.
 *
 * A gap is the dangerous one: it is invisible until a real assessment scores
 * into it, at which point the band comes back null and a group is shown no
 * verdict at all.
 *
 * Pure, so the arithmetic can be tested without rendering anything.
 */

export interface BandRange {
  key: string;
  label: string;
  minPoints: number;
  maxPoints: number;
}

export type CoverageKind = "band" | "gap" | "overlap";

export interface CoverageSegment {
  kind: CoverageKind;
  /** Inclusive, in points. */
  from: number;
  to: number;
  /** Percentage of the full range, for laying the strip out. */
  widthPercent: number;
  label: string;
}

/**
 * Walks 0..maxPoints and reports what covers each stretch.
 *
 * Works in whole points because the bands do: a score is compared with
 * `>= minPoints`, so the unit that matters is one point.
 */
export function bandCoverage(bands: BandRange[], maxPoints: number): CoverageSegment[] {
  if (maxPoints <= 0) return [];

  const sorted = [...bands].sort((a, b) => a.minPoints - b.minPoints);
  const segments: CoverageSegment[] = [];
  const width = (from: number, to: number) => ((to - from + 1) / (maxPoints + 1)) * 100;

  // How many bands claim each point. Cheap at this size, and it makes overlap
  // fall out of the same pass as gaps rather than needing its own comparison.
  const claims: number[] = new Array(maxPoints + 1).fill(0);
  const owner: string[] = new Array(maxPoints + 1).fill("");
  for (const band of sorted) {
    const from = Math.max(0, Math.floor(band.minPoints));
    const to = Math.min(maxPoints, Math.floor(band.maxPoints));
    for (let point = from; point <= to; point += 1) {
      claims[point] = (claims[point] ?? 0) + 1;
      const existing = owner[point] ?? "";
      owner[point] = existing ? `${existing} / ${band.label}` : band.label;
    }
  }

  let start = 0;
  const claimsAt = (point: number) => claims[point] ?? 0;
  const ownerAt = (point: number) => owner[point] ?? "";
  const kindAt = (point: number): CoverageKind =>
    claimsAt(point) === 0 ? "gap" : claimsAt(point) > 1 ? "overlap" : "band";

  for (let point = 1; point <= maxPoints + 1; point += 1) {
    const ended = point > maxPoints;
    const changed =
      !ended && (kindAt(point) !== kindAt(start) || ownerAt(point) !== ownerAt(start));
    if (!ended && !changed) continue;

    const to = point - 1;
    const kind = kindAt(start);
    segments.push({
      kind,
      from: start,
      to,
      widthPercent: width(start, to),
      label:
        kind === "gap"
          ? `No band for ${start}–${to}`
          : kind === "overlap"
            ? `${ownerAt(start)} overlap at ${start}–${to}`
            : ownerAt(start)
    });
    start = point;
  }

  return segments;
}

/** True when every point from 0 to maxPoints is claimed exactly once. */
export function coverageIsComplete(segments: CoverageSegment[]) {
  return segments.length > 0 && segments.every((segment) => segment.kind === "band");
}
