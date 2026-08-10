import { createHash } from "node:crypto";
import { createReadStream } from "node:fs";
import { unlink } from "node:fs/promises";
import { join, normalize, sep } from "node:path";
import { publicUploadUrl, uploadRoot } from "../lib/uploads";

/**
 * The one place that knows attachments are files on a disk.
 *
 * Everything else deals in a `storagePath` — a relative string like
 * `visit-photo/2026/08/1754-uuid.jpg`. That indirection is the whole point:
 * moving to object storage later means reimplementing this file and nothing
 * else. It is deliberately thin, because paying for a full storage abstraction
 * today would buy nothing — a single VPS with a mounted volume is the correct
 * answer at this scale.
 */

/**
 * Date-sharded so no directory grows without bound.
 *
 * A single flat folder with a year of field photographs in it is slow to list,
 * awkward to back up incrementally, and miserable to reason about when you need
 * to find what a particular month cost. `YYYY/MM` also makes retention a
 * directory operation rather than a query.
 */
export function attachmentDirectory(kind: string, at: Date = new Date()) {
  const year = at.getUTCFullYear();
  const month = String(at.getUTCMonth() + 1).padStart(2, "0");
  return `${kind}/${year}/${month}`;
}

export function attachmentStoragePath(kind: string, fileName: string, at: Date = new Date()) {
  return `${attachmentDirectory(kind, at)}/${fileName}`;
}

/**
 * Resolves a stored path to somewhere on disk, refusing anything that escapes
 * the upload root.
 *
 * The path comes from the database rather than a request, so this is a second
 * line rather than the first — but an attachment row is written from a client
 * request, and "the value was in the database" has never made a traversal safe.
 */
export function resolveAttachmentPath(storagePath: string) {
  const cleaned = storagePath.replace(/^[/\\]+/, "");
  const absolute = normalize(join(uploadRoot, cleaned));
  const root = normalize(uploadRoot);

  if (!absolute.startsWith(root.endsWith(sep) ? root : root + sep)) {
    throw new Error(`attachment path escapes the upload root: ${storagePath}`);
  }
  return absolute;
}

export function attachmentUrl(storagePath: string) {
  return publicUploadUrl(storagePath);
}

/**
 * The content hash of a stored file.
 *
 * Streamed rather than read whole: these are photographs, and holding several
 * in memory at once on a small VPS is how an upload endpoint becomes an outage.
 *
 * The hash is what makes a retried upload safe. A phone that loses its
 * connection mid-push retries, and without content identity the same
 * photograph lands twice under two names.
 */
export function hashFile(absolutePath: string): Promise<string> {
  return new Promise((resolve, reject) => {
    const hash = createHash("sha256");
    const stream = createReadStream(absolutePath);
    stream.on("error", reject);
    stream.on("data", (chunk) => hash.update(chunk));
    stream.on("end", () => resolve(hash.digest("hex")));
  });
}

/**
 * Deletes a stored file, ignoring one that is already gone.
 *
 * Used to reap an orphan — a file uploaded whose binding request never arrived.
 * "Already absent" is the desired end state, so it is not an error.
 */
export async function removeAttachmentFile(storagePath: string) {
  try {
    await unlink(resolveAttachmentPath(storagePath));
    return true;
  } catch {
    return false;
  }
}
