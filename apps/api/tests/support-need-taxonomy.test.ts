import fs from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";

import { SUPPORT_NEED_CATEGORIES, SUPPORT_NEED_TYPES } from "../src/domain/meal-indicators";

/**
 * The support-need vocabulary exists in two places, and they must agree.
 *
 * The migration INSERTs it, which covers production the moment it deploys. The
 * service upserts it, which covers every database built with `prisma db push` —
 * CI's, and any fresh developer one. Neither alone is enough: reference data
 * living only in a migration is missing wherever `db push` built the schema,
 * and reference data living only in code is missing until something boots.
 *
 * This was not a theory. The endpoint passed locally against a migrated
 * database and returned 400 on CI, because the table existed and was empty.
 */
const MIGRATION = fs.readFileSync(
  path.resolve(
    __dirname,
    "../prisma/migrations/20260825160000_group_enterprises_market_and_support_needs/migration.sql"
  ),
  "utf8"
);

describe("the support-need taxonomy", () => {
  it("has every key the migration inserts, and no others", () => {
    // The migration writes rows as ('snt-<key>', '<key>', ...).
    const inMigration = [...MIGRATION.matchAll(/\('snt-([a-z-]+)',\s*'([a-z-]+)'/g)].map(
      (match) => match[2] as string
    );

    expect(inMigration.length).toBeGreaterThan(20);
    expect([...inMigration].sort()).toEqual(SUPPORT_NEED_TYPES.map((type) => type.key).sort());
  });

  it("gives the migration and the code the same title for each key", () => {
    for (const type of SUPPORT_NEED_TYPES) {
      // A drift here is invisible: both environments would work, and two
      // deployments would label the same need differently in the same report.
      const escaped = type.title.replace(/'/g, "''");
      expect(
        MIGRATION.includes(`'${type.key}', '${escaped}'`),
        `migration is missing or renames "${type.key}" (${type.title})`
      ).toBe(true);
    }
  });

  it("uses the deterministic id in both", () => {
    // Same id in both, so a database that ran the migration and one that ran
    // the bootstrap end up with the same rows rather than two ids for one key.
    for (const type of SUPPORT_NEED_TYPES) {
      expect(MIGRATION).toContain(`'snt-${type.key}'`);
    }
  });

  it("puts every need in a known category", () => {
    for (const type of SUPPORT_NEED_TYPES) {
      expect(SUPPORT_NEED_CATEGORIES).toContain(type.category);
    }
  });

  it("has no duplicate keys", () => {
    const keys = SUPPORT_NEED_TYPES.map((type) => type.key);
    expect(new Set(keys).size).toBe(keys.length);
  });

  it("covers every category, so no group of needs is unrecordable", () => {
    const covered = new Set(SUPPORT_NEED_TYPES.map((type) => type.category));
    for (const category of SUPPORT_NEED_CATEGORIES) {
      expect(covered, `nothing can be recorded under ${category}`).toContain(category);
    }
  });
});
