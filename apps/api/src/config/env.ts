import "dotenv/config";
import { z } from "zod";

/**
 * Parses a boolean the way a .env file means it.
 *
 * `z.coerce.boolean()` is `Boolean(value)`, so ANY non-empty string is true —
 * including the string "false". That made these flags impossible to turn off
 * from the environment: `ENABLE_PAYMENT_NETWORK_CALLS=false` still enabled
 * live gateway calls, and the only way to disable one was to delete the line.
 */
function envBoolean(defaultValue: boolean) {
  return z
    .union([z.boolean(), z.string()])
    .default(defaultValue)
    .transform((value) => {
      if (typeof value === "boolean") return value;
      const normalized = value.trim().toLowerCase();
      if (["false", "0", "no", "off", ""].includes(normalized)) return false;
      if (["true", "1", "yes", "on"].includes(normalized)) return true;
      return defaultValue;
    });
}

const envSchema = z.object({
  NODE_ENV: z.string().default("development"),
  API_PORT: z.coerce.number().default(4000),
  /**
   * The default carries `connection_limit=1`, and that is the point of it.
   *
   * SQLite takes one writer at a time. Prisma's default pool is
   * `cpus * 2 + 1` connections, so on a two-core CI runner five of them queue
   * on the same file lock and a `create` inside the seed can sit past the
   * 10-second pool timeout — which surfaces as `P1008 Socket timeout` on a
   * test that passes every time locally. It failed a production deploy on
   * 28 Aug 2026, in `meeting-commitments`, having never failed on a
   * developer machine.
   *
   * One connection serialises the writes in the pool instead of in the file
   * lock, where they cannot time out. This is the default only: every
   * deployment sets DATABASE_URL explicitly, so this reaches CI, the test
   * suite and local development, and changes nothing in production.
   */
  DATABASE_URL: z.string().default("file:./dev.db?connection_limit=1"),
  /**
   * Where uploaded files live. Empty means "derive from DATABASE_URL" — see
   * `lib/uploads.ts`. Set this explicitly to put evidence on its own volume.
   */
  UPLOAD_ROOT: z.string().default(""),
  WEB_ORIGIN: z.string().default("http://localhost:3000"),
  API_PUBLIC_URL: z.string().default("http://localhost:4000"),
  SESSION_SECRET: z.string().default("development-session-secret-change-me"),
  ALLOW_SANDBOX_NETWORK_TESTS: envBoolean(false),
  ENABLE_PAYMENT_NETWORK_CALLS: envBoolean(false),
  ENABLE_SMS_NETWORK_CALLS: envBoolean(true),
  GOOGLE_MAPS_BROWSER_API_KEY: z.string().default(""),
  INTELLIAUDIT_LLM_PROVIDER: z.string().default("disabled"),
  INTELLIAUDIT_LLM_BASE_URL: z.string().default(""),
  INTELLIAUDIT_LLM_API_KEY: z.string().default(""),
  INTELLIAUDIT_LLM_MODEL: z.string().default(""),
  INTELLIAUDIT_ENABLE_CONNECTOR_NETWORK_CALLS: envBoolean(false)
});

export const env = envSchema.parse({
  ...process.env,
  API_PORT: process.env.API_PORT ?? process.env.PORT
});
