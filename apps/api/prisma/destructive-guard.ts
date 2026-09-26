import { dirname, isAbsolute, relative, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));

/**
 * The one check every script that WIPES data runs before it starts.
 *
 * `seed.ts` deletes every table; nothing stopped it running against the live
 * database, and three routes lead there (`npm run db:seed:env`,
 * `run-with-service-env.sh prisma/seed.ts`, `seed-if-empty` outside
 * production). A script like this is refused:
 *
 *  - whenever NODE_ENV is production, and
 *  - whenever DATABASE_URL points at a SQLite file outside this package's
 *    `prisma/` folder, where the development and test databases live (a
 *    production copy, a backup, a file under /var/www),
 *
 * unless `--i-know-this-wipes-data` is on the command line — a person
 * deciding, never a default.
 */
export const WIPE_OVERRIDE_FLAG = "--i-know-this-wipes-data";

export function wipeRefusal(
  env: NodeJS.ProcessEnv = process.env,
  argv: string[] = process.argv,
  prismaDir: string = here
): string | null {
  if (argv.includes(WIPE_OVERRIDE_FLAG)) return null;
  if (env.NODE_ENV === "production") {
    return "NODE_ENV is production.";
  }
  const url = env.DATABASE_URL ?? "file:./dev.db";
  if (!url.startsWith("file:")) {
    return `DATABASE_URL is not a local SQLite file (${url.split(":")[0]}:).`;
  }
  const raw = url.slice("file:".length).split("?")[0] || "./dev.db";
  const file = isAbsolute(raw) || /^[A-Za-z]:[/\\]/.test(raw) ? resolve(raw) : resolve(prismaDir, raw);
  const inside = relative(prismaDir, file);
  if (inside.startsWith("..") || isAbsolute(inside)) {
    return `DATABASE_URL points outside apps/api/prisma (${file}).`;
  }
  return null;
}

export function assertSafeToWipe(what: string) {
  const reason = wipeRefusal();
  if (!reason) return;
  throw new Error(
    `Refusing to run ${what}: it deletes data, and ${reason} ` +
      `If this really is a throwaway database, run it again with ${WIPE_OVERRIDE_FLAG}.`
  );
}
