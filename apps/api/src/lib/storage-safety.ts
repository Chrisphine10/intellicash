/**
 * Refuses to serve a production deployment whose database will not survive a
 * restart.
 *
 * This platform holds VSLA savings ledgers. Render (and most container hosts)
 * give a service an ephemeral filesystem unless a disk is mounted, so a SQLite
 * file under `/tmp` — or a relative path inside the build — is wiped on every
 * deploy, restart, and free-tier spin-down. Groups would enter a cycle's
 * contributions and find them gone.
 *
 * Failing the deploy is the kinder outcome: a service that never starts gets
 * noticed, whereas silent data loss is discovered by a treasurer at a meeting.
 *
 * A demo or preview deployment can opt out with
 * `ALLOW_EPHEMERAL_DATABASE=true`, which is deliberately explicit — nobody
 * sets it by accident.
 */

/** Paths a host will not preserve across restarts. */
const EPHEMERAL_PREFIXES = ["/tmp/", "/var/tmp/", "/dev/shm/"];

export type StorageVerdict =
  | { safe: true }
  | { safe: false; reason: string };

export function assessDatabaseDurability(
  databaseUrl: string | undefined,
  options: { nodeEnv?: string; allowEphemeral?: boolean } = {}
): StorageVerdict {
  const nodeEnv = options.nodeEnv ?? process.env.NODE_ENV;
  if (nodeEnv !== "production") return { safe: true };
  if (options.allowEphemeral) return { safe: true };

  // A non-SQLite datasource is a managed database; durability is its problem.
  if (!databaseUrl?.startsWith("file:")) return { safe: true };

  const path = databaseUrl.slice("file:".length).split("?")[0] ?? "";
  if (!path) {
    return { safe: false, reason: "DATABASE_URL has no path." };
  }

  if (EPHEMERAL_PREFIXES.some((prefix) => path.startsWith(prefix))) {
    return {
      safe: false,
      reason:
        `DATABASE_URL points at ${path}, which the host wipes on every restart ` +
        "and redeploy. Mount a persistent disk and point DATABASE_URL at it."
    };
  }

  // A relative path lives inside the build directory, which is replaced
  // wholesale on the next deploy.
  const isAbsolute = path.startsWith("/") || /^[A-Za-z]:[/\\]/.test(path);
  if (!isAbsolute) {
    return {
      safe: false,
      reason:
        `DATABASE_URL is the relative path ${path}, which lives inside the ` +
        "build and is replaced on the next deploy. Use an absolute path on a " +
        "mounted disk."
    };
  }

  return { safe: true };
}

/** Throws when a production deployment would lose its data. */
export function assertDurableDatabase(
  databaseUrl = process.env.DATABASE_URL,
  options: { nodeEnv?: string; allowEphemeral?: boolean } = {}
) {
  const allowEphemeral =
    options.allowEphemeral ??
    ["1", "true", "yes", "on"].includes(
      (process.env.ALLOW_EPHEMERAL_DATABASE ?? "").trim().toLowerCase()
    );

  const verdict = assessDatabaseDurability(databaseUrl, { ...options, allowEphemeral });
  if (verdict.safe) return;

  throw new Error(
    `Refusing to start: this deployment would lose its data. ${verdict.reason} ` +
      "If this is a demo or preview that is meant to reset, set " +
      "ALLOW_EPHEMERAL_DATABASE=true to acknowledge it."
  );
}
