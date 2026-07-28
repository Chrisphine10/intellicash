import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "./seed";
import { seedProductionBootstrap } from "./bootstrap-production";

/**
 * First-run seeding, chosen by environment.
 *
 * A fresh database in development or test gets the full demo dataset — it is
 * what the tests and the local walkthrough rely on. A fresh PRODUCTION
 * database must NOT: the demo seed ships an admin whose password is in the
 * source tree. Production gets only the permission templates and one real
 * admin from environment variables (see `bootstrap-production.ts`).
 *
 * `ALLOW_DEMO_SEED=true` forces the demo dataset even in production — for a
 * staging box meant to hold the demo, never for a live one.
 */
const isProduction = process.env.NODE_ENV === "production";
const forceDemo = process.env.ALLOW_DEMO_SEED === "true";

async function seedIfEmpty() {
  const userCount = await prisma.user.count();
  if (userCount > 0) {
    console.log(`Seed skipped; ${userCount} users already exist.`);
    return;
  }

  if (isProduction && !forceDemo) {
    await seedProductionBootstrap();
    return;
  }

  await seedDatabase();
  console.log("Demo seed data created for empty database.");
}

seedIfEmpty()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
