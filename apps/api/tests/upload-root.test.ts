import { describe, expect, it } from "vitest";
import { dirname, isAbsolute, resolve, sep } from "node:path";
import { uploadsBesideDatabase } from "../src/lib/uploads";

/**
 * The upload root used to resolve inside the source tree. Field-visit evidence
 * cannot live there: a redeploy that replaces the working tree deletes it, and
 * a database backup does not include it, so a restore yields records pointing
 * at photographs that no longer exist.
 *
 * These assert the *property* that matters — uploads sit in the database's own
 * directory — rather than a literal path. Production is Linux and development
 * is Windows; a hardcoded POSIX string would only ever prove which machine ran
 * the suite.
 */
describe("upload root placement", () => {
  /** The directory the resolved upload root lives in, separator-agnostic. */
  function parentOf(uploads: string) {
    return dirname(uploads.replace(/[\\/]+$/, ""));
  }

  it("puts uploads in the same directory as an absolute database file", () => {
    // One backup of that directory then covers ledger and evidence together.
    const dbPath = resolve(sep, "var", "www", "intellicash", "data", "intellicash.db");
    const uploads = uploadsBesideDatabase(`file:${dbPath}`);

    expect(uploads).not.toBeNull();
    expect(parentOf(uploads!)).toBe(dirname(dbPath));
    expect(uploads!.replace(/[\\/]+$/, "").endsWith(`${sep}uploads`)).toBe(true);
  });

  it("resolves a relative development database against the working directory", () => {
    const uploads = uploadsBesideDatabase("file:./dev.db");

    expect(uploads).not.toBeNull();
    expect(isAbsolute(uploads!)).toBe(true);
    expect(parentOf(uploads!)).toBe(process.cwd());
  });

  it("ignores query parameters some drivers append", () => {
    const dbPath = resolve(sep, "srv", "data", "app.db");
    const withParams = uploadsBesideDatabase(`file:${dbPath}?connection_limit=1`);

    expect(withParams).toBe(uploadsBesideDatabase(`file:${dbPath}`));
    expect(withParams).not.toContain("?");
  });

  it("always ends with a separator so callers can concatenate safely", () => {
    const uploads = uploadsBesideDatabase("file:./dev.db")!;
    expect(uploads.endsWith("/") || uploads.endsWith("\\")).toBe(true);
  });

  it("declines a memory database rather than inventing a path", () => {
    expect(uploadsBesideDatabase("file::memory:")).toBeNull();
  });

  it("declines a non-file database url", () => {
    // A future Postgres deployment has no directory to sit beside; the caller
    // falls back rather than guessing.
    expect(uploadsBesideDatabase("postgresql://user@host:5432/db")).toBeNull();
    expect(uploadsBesideDatabase(undefined)).toBeNull();
    expect(uploadsBesideDatabase("")).toBeNull();
  });
});
