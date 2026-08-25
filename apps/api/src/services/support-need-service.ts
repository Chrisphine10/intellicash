import { prisma } from "../lib/prisma";
import { SUPPORT_NEED_TYPES } from "../domain/meal-indicators";

/**
 * Guarantees the support-need vocabulary exists, whatever built the database.
 *
 * The taxonomy is INSERTed by the migration that created the table, which
 * covers production. It does not cover any environment built with
 * `prisma db push` — which is how CI builds its test database, and how
 * `ensure-schema.ts` builds a fresh developer one. There the table is created
 * empty, the capture screen offers nothing to choose from, and every attempt to
 * record a need is refused as an unknown key.
 *
 * That is exactly how this was found: the endpoint passed locally against a
 * migrated database and returned 400 on CI.
 *
 * Same shape as `ensureRolePermissionTemplates` — memoised per process and
 * awaited at the point of use rather than at boot, so nothing has to remember
 * to call it and a cold start pays for it once.
 */
let bootstrap: Promise<void> | null = null;

export async function ensureSupportNeedTypes() {
  bootstrap ??= ensureSupportNeedTypesOnce();
  await bootstrap;
}

/**
 * Test-only: clears the once-per-process memo, so a test that empties the table
 * can watch it be rebuilt. Without this the bootstrap can only ever be observed
 * in whichever test happens to run first.
 */
export function __resetSupportNeedBootstrapForTests() {
  bootstrap = null;
}

async function ensureSupportNeedTypesOnce() {
  for (const [index, type] of SUPPORT_NEED_TYPES.entries()) {
    await prisma.supportNeedType.upsert({
      where: { key: type.key },
      // Deterministic id, matching the migration's, so an environment that ran
      // the migration and one that ran this end up with the same rows rather
      // than two ids for one key.
      create: {
        id: `snt-${type.key}`,
        key: type.key,
        title: type.title,
        category: type.category,
        position: (index + 1) * 10
      },
      // Title and category are corrected on every boot; `isActive` is NOT
      // touched. An administrator who retired a need meant it, and having it
      // silently come back at the next deploy would be worse than a stale
      // title.
      update: { title: type.title, category: type.category }
    });
  }
}
