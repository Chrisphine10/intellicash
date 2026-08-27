import fs from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";

/**
 * The FLOURISH onboarding pack, checked against the workbook it came from.
 *
 * The importer joins four sheets that spell the same group three different ways
 * — "Smart Coffee Self Help Group", "Smart Coffee SHG", and "Tupande Joy" for
 * "Tupande Joy Farmers' & Bee Keeping Group". An exact match dropped seven of
 * twenty-two historic visits without saying so, which is the failure worth
 * guarding: silently importing less than was asked for looks exactly like
 * importing everything.
 *
 * These assert the shape of the committed extract. The importer's own behaviour
 * — idempotency, ward tie-breaking, what it refuses to invent — was rehearsed
 * against a throwaway database before the production run.
 */
const pack = JSON.parse(
  fs.readFileSync(path.resolve(__dirname, "../prisma/data/flourish-onboarding.json"), "utf8")
) as {
  groups: { key: string; name: string; county: string; phase: string; ward: string }[];
  mentorshipVisits: { groupKey: string; visitDate: string; ward: string }[];
  groupEnterprises: { groupKey: string }[];
  notCarriedAcross: string[];
};

const GENERIC = new Set(["shg", "self", "help", "group", "sacco", "the"]);
const tokens = (key: string) =>
  new Set(key.split("-").filter((word) => word && !GENERIC.has(word)));

function resolve(candidate: string, ward?: string) {
  const keys = pack.groups.map((g) => g.key);
  if (keys.includes(candidate)) return candidate;
  const wanted = tokens(candidate);
  const matches = keys.filter((key) => [...wanted].every((w) => tokens(key).has(w)));
  if (matches.length === 1) return matches[0];
  if (ward) {
    const byWard = matches.filter(
      (key) =>
        (pack.groups.find((g) => g.key === key)?.ward ?? "").toLowerCase() === ward.toLowerCase()
    );
    if (byWard.length === 1) return byWard[0];
  }
  return null;
}

describe("the FLOURISH onboarding pack", () => {
  it("carries every group in the register", () => {
    expect(pack.groups).toHaveLength(40);
    expect(new Set(pack.groups.map((g) => g.key)).size).toBe(40);
  });

  it("resolves a county for every group", () => {
    // A group with no county cannot be given a code or scoped to a caseload.
    for (const group of pack.groups) {
      expect(group.county, `${group.name} has no county`).not.toBe("");
    }
  });

  it("does not claim an unassessed group is further along than it is", () => {
    // Kirinyaga's cohort has not been assessed. Filing them as INTENSIVE would
    // put them in the same bucket as groups that have had months of mentoring.
    const kirinyagaToAssess = pack.groups.filter(
      (g) => g.county === "Kirinyaga" && g.phase === "MOBILISATION"
    );
    expect(kirinyagaToAssess.length).toBeGreaterThanOrEqual(10);
  });

  it("matches every mentorship visit to a group", () => {
    // The regression this exists for: seven visits were being dropped because
    // the log abbreviates "Self Help Group" to "SHG".
    const unmatched = pack.mentorshipVisits.filter((v) => !resolve(v.groupKey, v.ward));
    expect(unmatched.map((v) => v.groupKey)).toEqual([]);
  });

  it("carries no footnote rows as visits", () => {
    // The sheet ends with two prose notes in the group-name column.
    for (const visit of pack.mentorshipVisits) {
      expect(visit.groupKey.length).toBeLessThan(60);
      expect(visit.visitDate).not.toBe("");
    }
  });

  it("reads every visit date, including the eight with no day", () => {
    // Eight rows carry only "Jul 2026". A stricter parser that rejected them
    // dropped eight real visits without a word, which is how this test earned
    // its place.
    const MONTHS = ["jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec"];
    let monthOnly = 0;
    for (const visit of pack.mentorshipVisits) {
      const text = visit.visitDate.trim();
      const full = /^(\d{1,2})\s+([A-Za-z]{3})[a-z]*\s+(\d{4})$/.exec(text);
      const partial = /^([A-Za-z]{3})[a-z]*\s+(\d{4})$/.exec(text);
      expect(full || partial, `unreadable date "${visit.visitDate}"`).toBeTruthy();
      const month = full ? full[2] : partial![1];
      expect(MONTHS).toContain((month as string).toLowerCase());
      if (!full) monthOnly += 1;
    }
    expect(monthOnly).toBe(8);
  });

  it("leaves a genuinely ambiguous enterprise unmatched rather than guessing", () => {
    // "Mwikuria" fits both "Mwikuria Self Help Group" and "Mwikuria Self Help
    // Group (II)", and that sheet carries no ward to separate them. Attaching a
    // business to the wrong group is worse than reporting it.
    const unmatched = pack.groupEnterprises.filter((e) => !resolve(e.groupKey));
    expect(unmatched.map((e) => e.groupKey)).toEqual(["mwikuria", "mwikuria"]);
  });

  it("records what it deliberately did not carry across", () => {
    // The workbook's blank sheets are blank by design. Anyone reading the
    // extract later must find that stated rather than assume data was lost.
    const said = pack.notCarriedAcross.join(" ").toLowerCase();
    for (const missing of ["opening balance", "pin", "member roster", "savings"]) {
      expect(said, `the pack should say ${missing} was not imported`).toContain(missing);
    }
  });
});
