import { describe, expect, it } from "vitest";

import {
  MARKET_REACH_LADDER,
  MEAL_INDICATORS,
  SMALL_SAMPLE_THRESHOLD,
  indicatorCatalogue,
  marketReachStep,
  mean,
  median,
  movementOf,
  pairedChange,
  share
} from "../src/domain/meal-indicators";

/**
 * The MEAL rules, as tests.
 *
 * These are not arithmetic checks. Each one pins a way that programme reporting
 * routinely states something untrue, and the arithmetic is only how the rule is
 * observed. A change here should be a deliberate decision about measurement,
 * not a tidy-up.
 */

function unit(unitId: string, first: number | null, last: number | null, comparable = true) {
  return { unitId, first, last, comparable };
}

describe("paired comparison", () => {
  it("ignores units that joined after the baseline", () => {
    // The failure this exists to prevent: five groups improved slightly, then
    // twenty high-scoring groups joined. Averaging everyone at each end reports
    // a large gain that no group actually made.
    const units = [
      unit("a", 40, 45),
      unit("b", 50, 55),
      ...Array.from({ length: 20 }, (_, index) => unit(`new${index}`, null, 90))
    ];

    const result = pairedChange("assessment.score", units);

    // Only the two groups present at both ends inform the change.
    expect(result.pairedUnits).toBe(2);
    expect(result.baseline).toBe(45);
    expect(result.latest).toBe(50);
    expect(result.change).toBe(5);

    // The twenty newcomers are still visible — as coverage, not as growth.
    expect(result.observedUnits).toBe(22);
  });

  it("counts a unit with one reading towards coverage and never towards change", () => {
    const result = pairedChange("assessment.score", [unit("a", 40, 60), unit("b", 30, null)]);

    expect(result.pairedUnits).toBe(1);
    expect(result.observedUnits).toBe(2);
    expect(result.baseline).toBe(40);
  });

  it("reports no baseline instead of a change of zero", () => {
    // These are different facts and a dashboard must not merge them: one says
    // nothing improved, the other says nothing has been measured twice.
    const result = pairedChange("assessment.score", [unit("a", null, 70), unit("b", null, 65)]);

    expect(result.change).toBeNull();
    expect(result.baseline).toBeNull();
    expect(result.pairedUnits).toBe(0);
    expect(result.notes.join(" ")).toMatch(/no baseline/i);
  });

  it("excludes units whose measurement changed underneath them", () => {
    // A group assessed on scorecard v1 and then v2 has two numbers that are not
    // the same measurement. Averaging them in reads a re-worded question as the
    // group improving.
    const result = pairedChange("assessment.score", [
      unit("a", 40, 50),
      unit("b", 30, 80, false),
      unit("c", 45, 55)
    ]);

    expect(result.pairedUnits).toBe(2);
    expect(result.excludedForComparability).toBe(1);
    expect(result.latest).toBe(52.5);
    expect(result.notes.join(" ")).toMatch(/measurement changed/i);
  });

  it("says so when every comparison was excluded", () => {
    const result = pairedChange("assessment.score", [unit("a", 30, 80, false)]);

    expect(result.pairedUnits).toBe(0);
    expect(result.change).toBeNull();
    expect(result.notes.join(" ")).toMatch(/excluded because the measurement changed/i);
  });

  it("does not divide by a baseline of zero", () => {
    // Revenue rising from nothing is real and worth reporting; "Infinity%
    // growth" is not, and it is what an unguarded division prints.
    const result = pairedChange("enterprise.revenue", [unit("a", 0, 500000)]);

    expect(result.change).toBe(500000);
    expect(result.percentChange).toBeNull();
  });

  it("splits movement into improved, flat and declined", () => {
    const result = pairedChange("assessment.score", [
      unit("a", 40, 50),
      unit("b", 60, 60),
      unit("c", 70, 55)
    ]);

    // The direction of travel matters more than the average, which can hide a
    // programme where half the groups are going backwards.
    expect(result.improved).toBe(1);
    expect(result.unchanged).toBe(1);
    expect(result.declined).toBe(1);
  });

  it("flags a sample too small to read as a trend", () => {
    const result = pairedChange("assessment.score", [unit("a", 40, 90)]);

    expect(result.isSmallSample).toBe(true);
    expect(result.notes.join(" ")).toMatch(/too few/i);
    // Still returned. Withholding it invites somebody to recompute it by hand
    // and lose the caveat on the way.
    expect(result.change).toBe(50);
  });

  it("stops flagging once the sample is large enough", () => {
    const units = Array.from({ length: SMALL_SAMPLE_THRESHOLD }, (_, index) =>
      unit(`g${index}`, 40, 50)
    );

    expect(pairedChange("assessment.score", units).isSmallSample).toBe(false);
  });

  it("warns when the figure describes a minority of those eligible", () => {
    const result = pairedChange("assessment.score", [unit("a", 40, 50), unit("b", 45, 55)], {
      eligibleUnits: 40
    });

    expect(result.coveragePercent).toBe(5);
    expect(result.notes.join(" ")).toMatch(/minority/i);
  });

  it("uses the median for money by default", () => {
    // One group with a milling machine must not set the typical figure.
    const units = [
      unit("a", 100, 100),
      unit("b", 200, 200),
      unit("c", 300, 300),
      unit("rich", 100, 900_000)
    ];

    expect(pairedChange("enterprise.revenue", units).latest).toBe(250);
    expect(pairedChange("enterprise.revenue", units, { aggregate: "MEAN" }).latest).toBe(225_150);
  });
});

describe("shares", () => {
  it("keeps the denominator attached", () => {
    const result = share("actions.closed", 13, 21);

    expect(result.percent).toBe(61.9);
    expect(result.denominator).toBe(21);
  });

  it("returns null rather than 0% when nothing has been measured", () => {
    // "0%" reads as failure. Nothing recorded is not failure.
    const result = share("actions.closed", 0, 0);

    expect(result.percent).toBeNull();
    expect(result.notes.join(" ")).toMatch(/not the same as zero/i);
  });

  it("flags a small denominator", () => {
    expect(share("needs.met", 1, 2).isSmallSample).toBe(true);
  });
});

describe("market reach", () => {
  it("is an ordered ladder so a wider market is a measurable step", () => {
    expect(marketReachStep("VILLAGE")).toBe(2);
    expect(marketReachStep("COUNTY")).toBe(5);
    expect(marketReachStep("COUNTY")! > marketReachStep("VILLAGE")!).toBe(true);
  });

  it("treats an absent or unknown reach as no reading, not as the bottom rung", () => {
    // Zero would place a group that was never asked below one selling within
    // the group, and would then count as improvement the moment it is asked.
    expect(marketReachStep(null)).toBeNull();
    expect(marketReachStep("MOON")).toBeNull();
  });

  it("measures a move up the ladder as a gain of steps", () => {
    const result = pairedChange("enterprise.marketReach", [
      { unitId: "a", first: marketReachStep("VILLAGE"), last: marketReachStep("COUNTY") }
    ]);

    expect(result.change).toBe(3);
  });

  it("has no duplicate rungs", () => {
    const keys = MARKET_REACH_LADDER.map((rung) => rung.key);
    expect(new Set(keys).size).toBe(keys.length);
  });
});

describe("direction", () => {
  it("reads a fall in days-to-meet as an improvement", () => {
    // The rule that a naive green-for-up chart gets backwards.
    expect(movementOf("needs.daysToMeet", -12)).toBe("IMPROVED");
    expect(movementOf("needs.daysToMeet", 12)).toBe("WORSENED");
  });

  it("reads a rise in score as an improvement", () => {
    expect(movementOf("assessment.score", 4)).toBe("IMPROVED");
    expect(movementOf("assessment.score", -4)).toBe("WORSENED");
  });

  it("refuses to judge a neutral or unknown indicator", () => {
    // More needs recorded is not good or bad on its own — it may mean better
    // listening or a worsening situation, and the dashboard should not guess.
    expect(movementOf("needs.raised", 30)).toBe("UNKNOWN");
    expect(movementOf("not.an.indicator", 1)).toBe("UNKNOWN");
    expect(movementOf("assessment.score", null)).toBe("UNKNOWN");
  });
});

describe("the indicator catalogue", () => {
  it("gives every indicator a definition and a direction", () => {
    // An indicator without a written definition gets redefined by whoever
    // reads it next, and two people then quote the same name for two things.
    for (const [key, definition] of Object.entries(MEAL_INDICATORS)) {
      expect(definition.key, `${key} key must match its map key`).toBe(key);
      expect(definition.definition.length, `${key} needs a definition`).toBeGreaterThan(20);
      expect(definition.name.length).toBeGreaterThan(0);
    }
  });

  it("names a denominator for every percentage", () => {
    for (const definition of Object.values(MEAL_INDICATORS)) {
      if (definition.unit === "PERCENT") {
        expect(definition.denominator, `${definition.key} is a % of what?`).not.toBe("");
      }
    }
  });

  it("orders the catalogue along the results chain", () => {
    const levels = indicatorCatalogue().map((definition) => definition.level);
    const firstOutcome = levels.indexOf("OUTCOME");
    const lastActivity = levels.lastIndexOf("ACTIVITY");

    expect(lastActivity).toBeLessThan(firstOutcome);
    expect(levels.at(-1)).toBe("DATA_QUALITY");
  });

  it("carries data-quality indicators, not only results", () => {
    // Without these the outcome figures have no stated reliability, and a
    // rating average built entirely from agents rating themselves looks the
    // same as one given by groups.
    const quality = indicatorCatalogue().filter((d) => d.level === "DATA_QUALITY");
    expect(quality.length).toBeGreaterThan(0);
    expect(quality.map((d) => d.key)).toContain("data.ratingProvenance");
  });
});

describe("statistics", () => {
  it("returns null for an empty set rather than zero", () => {
    expect(median([])).toBeNull();
    expect(mean([])).toBeNull();
  });

  it("takes the midpoint of an even-length set", () => {
    expect(median([1, 2, 3, 4])).toBe(2.5);
  });

  it("does not mutate its input", () => {
    const values = [3, 1, 2];
    median(values);
    expect(values).toEqual([3, 1, 2]);
  });
});
