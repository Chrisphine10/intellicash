import { prisma } from "../lib/prisma";
import { DEFAULT_TEMPLATE_FAMILY } from "./visit-assessment-service";
import { seedAssessmentTemplateV1 } from "../../prisma/seed-assessment-template-v1";

/**
 * Makes sure a scorecard exists for a field agent to fill in.
 *
 * The v1 question set was only ever published by running
 * `seed-assessment-template-v1.ts` by hand, or by a test. No migration writes
 * it and `seed.ts` does not call it — and the seed does not run on production
 * anyway. So on production there was no published template at all, and
 * `GET /assessment-templates/current` answered every agent with
 * `NO_PUBLISHED_TEMPLATE`. The assessment step of a visit was unreachable for
 * everybody, on the phone and in the console alike, and it looked like a
 * permissions problem rather than an empty table.
 *
 * The same shape of fault as the support-need taxonomy: reference data that
 * exists only if somebody remembers to run a script. Fixed the same way —
 * memoised, awaited at the point of use, so nothing has to remember.
 *
 * Deliberately conservative. It publishes ONLY when the family has no template
 * whatsoever. If an administrator has a draft in progress, or has published
 * their own version, this does nothing: seeding a second v1 would collide on
 * `@@unique([familyKey, version])`, and republishing over somebody's work would
 * be worse than the bug.
 */
let bootstrap: Promise<void> | null = null;

export async function ensureAssessmentTemplate() {
  bootstrap ??= ensureAssessmentTemplateOnce();
  await bootstrap;
}

/**
 * Test-only: clears the once-per-process memo so a test can empty the tables
 * and watch the bootstrap run again.
 */
export function __resetAssessmentTemplateBootstrapForTests() {
  bootstrap = null;
}

async function ensureAssessmentTemplateOnce() {
  const existing = await prisma.assessmentTemplate.findFirst({
    where: { familyKey: DEFAULT_TEMPLATE_FAMILY },
    select: { id: true }
  });

  // Anything at all in this family — draft included — means a human is in
  // charge of it and this must keep its hands off.
  if (existing) return;

  await seedAssessmentTemplateV1(prisma);
}
