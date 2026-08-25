import fs from "node:fs";
import path from "node:path";
import os from "node:os";
import { createRequire } from "node:module";
import { afterEach, beforeEach, describe, expect, it } from "vitest";

/**
 * The demo programme on production had no `publicSlug`.
 *
 * The first attempt at hiding demo data marked programmes by
 * `publicSlug = 'demo-programme'`, which is what the seed sets today. The row
 * on production predates that, so it was missed and stayed on the public
 * project list under "Demo Programme / Demo Programme Partner" — found by
 * reading the live endpoint after deploying, not by reasoning about the seed.
 *
 * This runs the follow-up migration against a database built to match what
 * production actually holds, including a real partner and programme that share
 * the demo names. Hiding a real programme from its own partner would be a worse
 * bug than the one being fixed, so that case is asserted, not assumed.
 */

const MIGRATION = path.resolve(
  __dirname,
  "../prisma/migrations/20260825130000_flag_demo_programme_without_slug/migration.sql"
);

/**
 * `node:sqlite` rather than a new dependency — this test wants a throwaway file
 * database and nothing more.
 *
 * Loaded through `createRequire` because Vite still does not recognise it as a
 * Node builtin: a static import gets rewritten to a bare `sqlite` specifier and
 * fails to resolve. The shape is declared here rather than leaned on from
 * @types/node so the typecheck does not depend on which version is installed.
 */
interface SqliteDatabase {
  exec(sql: string): void;
  prepare(sql: string): { get(): unknown; all(): unknown[] };
  close(): void;
}

const { DatabaseSync } = createRequire(__filename)("node:sqlite") as {
  DatabaseSync: new (filename: string) => SqliteDatabase;
};

let db: SqliteDatabase;
let file: string;

function statements(): string[] {
  const sql = fs.readFileSync(MIGRATION, "utf8");
  const body = sql
    .split("\n")
    .filter((line) => !line.trim().startsWith("--"))
    .join("\n");
  return body
    .split(";")
    .map((statement) => statement.trim())
    .filter(Boolean);
}

function run() {
  for (const statement of statements()) db.exec(statement);
}

/** One row, typed by the caller. */
function one<T>(sql: string): T {
  return db.prepare(sql).get() as T;
}

function flag(table: string, id: string): number {
  return one<{ isDemo: number }>(`SELECT isDemo FROM "${table}" WHERE id='${id}'`).isDemo;
}

beforeEach(() => {
  file = path.join(
    fs.mkdtempSync(path.join(os.tmpdir(), "demo-backfill-")),
    "test.db"
  );
  db = new DatabaseSync(file);
  db.exec(`
    CREATE TABLE "Partner" (id TEXT PRIMARY KEY, name TEXT, isDemo INTEGER DEFAULT 0);
    CREATE TABLE "Programme" (
      id TEXT PRIMARY KEY, partnerId TEXT, name TEXT,
      publicSlug TEXT UNIQUE, isDemo INTEGER DEFAULT 0,
      createdAt TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE "Group" (
      id TEXT PRIMARY KEY, programmeId TEXT, name TEXT, code TEXT, isDemo INTEGER DEFAULT 0
    );
    CREATE TABLE "VillageAgent" (id TEXT PRIMARY KEY, name TEXT, isDemo INTEGER DEFAULT 0);
    CREATE TABLE "VillageAgentProgramme" (villageAgentId TEXT, programmeId TEXT);
  `);

  // Production's shape: demo scaffolding with NO slug, beside real work.
  db.exec(`
    INSERT INTO "Partner" (id, name) VALUES
      ('pDemo', 'Demo Programme Partner'),
      ('pRFA',  'Rain Forest Alliance');
    INSERT INTO "Programme" (id, partnerId, name, publicSlug) VALUES
      ('progDemo',   'pDemo', 'Demo Programme',  NULL),
      ('progCoffee', 'pRFA',  'Brew the Coffee', NULL);
  `);
});

afterEach(() => {
  db.close();
  fs.rmSync(path.dirname(file), { recursive: true, force: true });
});

describe("flagging the demo programme that has no slug", () => {
  it("flags it, its partner and its groups", () => {
    db.exec(
      `INSERT INTO "Group" (id, programmeId, name, code, isDemo)
       VALUES ('gDemo', 'progDemo', 'Demo Test VSLA', 'IWL-DEMO-0001', 0)`
    );
    run();

    expect(flag("Programme", "progDemo")).toBe(1);
    expect(flag("Partner", "pDemo")).toBe(1);
    expect(flag("Group", "gDemo")).toBe(1);
  });

  it("leaves the real programme and partner alone", () => {
    run();

    expect(flag("Programme", "progCoffee")).toBe(0);
    expect(flag("Partner", "pRFA")).toBe(0);
  });

  it("does not hide a real programme that shares the demo names", () => {
    // The name is not the signal. A partner may legitimately run a programme
    // called "Demo Programme"; what marks the seed's scaffolding is that it has
    // no real groups on it.
    db.exec(`
      INSERT INTO "Partner" (id, name) VALUES ('pTrap', 'Demo Programme Partner');
      INSERT INTO "Programme" (id, partnerId, name) VALUES ('progTrap', 'pTrap', 'Demo Programme');
      INSERT INTO "Group" (id, programmeId, name, code, isDemo)
        VALUES ('gTrap', 'progTrap', 'Kiritiri Farmers', 'IWL-KBU-9001', 0);
    `);
    run();

    expect(flag("Programme", "progTrap")).toBe(0);
    expect(flag("Partner", "pTrap")).toBe(0);
  });

  it("gives the demo programme back the slug the seed looks it up by", () => {
    run();

    // Without this the seed's find-or-create would miss this row and create a
    // SECOND demo programme, unflagged, back on the public list.
    expect(
      one<{ publicSlug: string | null }>(
        `SELECT publicSlug FROM "Programme" WHERE id='progDemo'`
      ).publicSlug
    ).toBe("demo-programme");
  });

  it("does not fight another row already holding the slug", () => {
    // publicSlug is @unique. Claiming it blindly would throw and take the whole
    // migration — and the deploy — down.
    db.exec(`UPDATE "Programme" SET publicSlug='demo-programme' WHERE id='progCoffee'`);

    expect(() => run()).not.toThrow();
    expect(
      one<{ publicSlug: string | null }>(
        `SELECT publicSlug FROM "Programme" WHERE id='progDemo'`
      ).publicSlug
    ).toBeNull();
  });

  it("is safe to run twice", () => {
    run();
    expect(() => run()).not.toThrow();

    expect(one<{ n: number }>(`SELECT COUNT(*) AS n FROM "Programme" WHERE isDemo=1`).n).toBe(1);
  });

  it("flags an agent who only serves the demo programme", () => {
    db.exec(`
      INSERT INTO "VillageAgent" (id, name) VALUES ('aDemo', 'Grace Wanjiku'), ('aReal', 'Real Agent');
      INSERT INTO "VillageAgentProgramme" (villageAgentId, programmeId) VALUES
        ('aDemo', 'progDemo'),
        ('aReal', 'progDemo'), ('aReal', 'progCoffee');
    `);
    run();

    // An agent who also covers a real programme is a real agent. Flagging them
    // would erase their real caseload from every total.
    expect(flag("VillageAgent", "aDemo")).toBe(1);
    expect(flag("VillageAgent", "aReal")).toBe(0);
  });
});
