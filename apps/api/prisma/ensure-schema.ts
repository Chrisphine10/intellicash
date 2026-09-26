// Read apps/api/.env the way Prisma itself does. Without it, a NODE_ENV or
// PRISMA_SCHEMA_STRATEGY kept only in .env was invisible here and the script
// chose `db push` while Prisma still used the .env DATABASE_URL.
import "dotenv/config";
import { existsSync, unlinkSync } from "node:fs";
import { join } from "node:path";
import { spawnSync } from "node:child_process";

/**
 * Brings the database in line with the schema.
 *
 * Production applies **migrations** (`prisma migrate deploy`): versioned,
 * reviewable before they run, and recorded so you can tell what a database has
 * had done to it. `db push` diffs the live schema and reshapes it in place —
 * fine while the only thing at stake is seed data, not once a group's ledger
 * lives there, because there is no review step and nothing to roll back to.
 *
 * Local development keeps `db push`, which is the point of it: iterate on the
 * schema without writing a migration for every change. Run
 * `npx prisma migrate dev --name <what-changed>` when the shape settles, and
 * commit the migration alongside the schema change.
 */

const force = process.argv.includes("--force");
const schemaDirectory = join(process.cwd(), "prisma");

/**
 * Whether [path] is a database built by migrations (it has migration
 * history). Such a database is only ever changed by migrations: `db push`
 * would reshape it with no record, and the next `migrate deploy` would find
 * a schema its history does not explain.
 */
async function hasMigrationHistory(path: string): Promise<boolean> {
  if (!existsSync(path)) return false;
  try {
    const { DatabaseSync } = await import("node:sqlite");
    const db = new DatabaseSync(path, { readOnly: true });
    try {
      const table = db
        .prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '_prisma_migrations'")
        .get();
      if (!table) return false;
      const row = db.prepare("SELECT COUNT(*) AS n FROM _prisma_migrations").get() as { n: number } | undefined;
      return (row?.n ?? 0) > 0;
    } finally {
      db.close();
    }
  } catch {
    // No node:sqlite (Node < 22) or an unreadable file: fall back to the
    // environment alone, as before.
    return false;
  }
}

/**
 * Migrations in production, push in development.
 *
 * Anything that is not explicitly a development run is treated as production:
 * the safe default is the reviewable path, not the destructive one.
 */
const explicitStrategy = process.env.PRISMA_SCHEMA_STRATEGY;
let useMigrations =
  explicitStrategy === "migrate" ||
  (process.env.NODE_ENV === "production" && explicitStrategy !== "push");

function sqliteFilePath(databaseUrl = process.env.DATABASE_URL) {
  if (!databaseUrl?.startsWith("file:")) return join(schemaDirectory, "dev.db");

  const rawPath = databaseUrl.slice("file:".length).split("?")[0] || "./dev.db";
  if (/^[/\\]/.test(rawPath) || /^[A-Za-z]:[/\\]/.test(rawPath)) return rawPath;

  return join(schemaDirectory, rawPath);
}

function run(args: string[], input?: string) {
  return spawnSync("prisma", args, {
    cwd: process.cwd(),
    encoding: "utf8",
    shell: true,
    env: process.env,
    ...(input === undefined ? {} : { input })
  });
}

function fail(result: ReturnType<typeof run>) {
  console.error(result.stderr || result.stdout);
  process.exit(result.status ?? 1);
}

const databasePath = sqliteFilePath();

if (!useMigrations && (await hasMigrationHistory(databasePath))) {
  if (explicitStrategy === "push") {
    console.error(
      `Refusing to db push ${databasePath}: it was built by migrations. ` +
        "Write a migration (npx prisma migrate dev --name <change>) instead."
    );
    process.exit(1);
  }
  console.log("This database was built by migrations; applying migrations, not db push.");
  useMigrations = true;
}

if (force && existsSync(databasePath)) {
  // Only ever a development convenience — `db:reset` wipes and re-seeds.
  if (useMigrations) {
    console.error(
      "Refusing to delete the database: --force is a development-only reset."
    );
    process.exit(1);
  }
  unlinkSync(databasePath);
}

if (useMigrations) {
  const deploy = run(["migrate", "deploy", "--schema", "prisma/schema.prisma"]);
  if (deploy.status !== 0) fail(deploy);
  console.log(deploy.stdout?.trim() || "Migrations applied.");
  process.exit(0);
}

if (existsSync(databasePath)) {
  const push = run([
    "db",
    "push",
    "--schema",
    "prisma/schema.prisma",
    "--skip-generate"
  ]);
  if (push.status !== 0) fail(push);
  console.log("SQLite schema updated.");
  process.exit(0);
}

// Fresh development database: build it straight from the schema.
const diff = run([
  "migrate",
  "diff",
  "--from-empty",
  "--to-schema-datamodel",
  "prisma/schema.prisma",
  "--script"
]);

if (diff.status !== 0 || !diff.stdout) fail(diff);

const execute = run(
  ["db", "execute", "--stdin", "--schema", "prisma/schema.prisma"],
  diff.stdout
);

if (execute.status !== 0) fail(execute);

console.log("SQLite schema bootstrapped.");
