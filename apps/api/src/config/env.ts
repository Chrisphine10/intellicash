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
  DATABASE_URL: z.string().default("file:./dev.db"),
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
