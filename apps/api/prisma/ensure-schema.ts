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
 * Migrations in production, push in development.
 *
 * Anything that is not explicitly a development run is treated as production:
 * the safe default is the reviewable path, not the destructive one.
 */
const useMigrations =
  process.env.PRISMA_SCHEMA_STRATEGY === "migrate" ||
  (process.env.NODE_ENV === "production" &&
    process.env.PRISMA_SCHEMA_STRATEGY !== "push");

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
