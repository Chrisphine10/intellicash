import { mkdirSync } from "node:fs";
import { dirname, isAbsolute, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { env } from "../config/env";

/**
 * Where uploaded files are written.
 *
 * This used to resolve to `apps/api/uploads/` — *inside the source tree*. That
 * is fine while the only uploads are avatars and product images, which can be
 * re-uploaded, and actively dangerous once field-visit evidence lands there: a
 * redeploy that replaces the working tree, or a stray `git clean -fdx`, takes
 * the photographs of a group's registration certificate with it. The files are
 * also invisible to whatever backs up the database, so a restore would bring
 * back records pointing at images that no longer exist.
 *
 * The default therefore follows the SQLite file: uploads sit beside it, so one
 * backup covers both and neither can be preserved while the other is lost. A
 * deployment that wants evidence on its own volume sets `UPLOAD_ROOT`.
 */
function resolveUploadRoot() {
  const configured = env.UPLOAD_ROOT.trim();
  if (configured) {
    return withTrailingSep(isAbsolute(configured) ? configured : resolve(process.cwd(), configured));
  }

  const beside = uploadsBesideDatabase(env.DATABASE_URL);
  if (beside) return beside;

  // No file-backed database (a memory URL, or a non-SQLite provider one day):
  // fall back to the historical in-repo location so development still works.
  return fileURLToPath(new URL("../../uploads/", import.meta.url));
}

/**
 * `file:./dev.db` → `<cwd>/uploads/`; `file:/var/www/app/data/x.db` →
 * `/var/www/app/data/uploads/`. Returns null for anything that is not a file
 * URL we can take a directory from.
 */
export function uploadsBesideDatabase(databaseUrl: string | undefined): string | null {
  if (!databaseUrl) return null;
  const trimmed = databaseUrl.trim();
  if (!trimmed.toLowerCase().startsWith("file:")) return null;

  const withoutScheme = trimmed.slice("file:".length).split("?")[0];
  if (!withoutScheme || withoutScheme.startsWith(":memory:")) return null;

  const absolute = isAbsolute(withoutScheme)
    ? withoutScheme
    : resolve(process.cwd(), withoutScheme);
  return withTrailingSep(resolve(dirname(absolute), "uploads"));
}

function withTrailingSep(path: string) {
  return path.endsWith("/") || path.endsWith("\\") ? path : `${path}/`;
}

export const uploadRoot = resolveUploadRoot();

export function ensureUploadDirectory(path = uploadRoot) {
  mkdirSync(path, { recursive: true });
}

export function publicUploadUrl(pathname: string) {
  return `${env.API_PUBLIC_URL.replace(/\/$/, "")}/uploads/${pathname.replace(/^\/+/, "")}`;
}
