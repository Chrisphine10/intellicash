import { prisma } from "../src/lib/prisma";
import { findSmsIntegration } from "../src/services/sms-provider";
import { isSendableSmsPhone, normalizeSmsPhone, sendBongaSms } from "../src/services/sms-service";

/**
 * Sends ONE test message through the live Bonga credentials and prints exactly
 * what the provider answered.
 *
 * The provider answers HTTP 200 for success and failure alike; only its own
 * `status` (222 success, 666 error) says which. So this reports that, the
 * provider's message, and the remaining credit — the three things that tell you
 * whether SMS actually works.
 *
 *   TO=2547XXXXXXXX            required; the number to text
 *   SERVICE_ID=20642           optional; try a different service ID without
 *                              changing the saved one
 */

const mask = (phone: string) => `${"*".repeat(Math.max(0, phone.length - 3))}${phone.slice(-3)}`;

async function main() {
  const to = normalizeSmsPhone(process.env.TO ?? "");
  if (!isSendableSmsPhone(to)) {
    console.error("TO must be a Kenyan mobile number, e.g. 254712345678.");
    process.exitCode = 1;
    return;
  }

  const integration = await findSmsIntegration("BONGA_SMS");
  if (!integration) {
    console.error("No Bonga credentials resolve on this server. Run 'Configure Bonga SMS' first.");
    process.exitCode = 1;
    return;
  }

  const credentials = {
    ...integration.credentials,
    ...(process.env.SERVICE_ID ? { BONGA_SMS_SERVICE_ID: process.env.SERVICE_ID } : {})
  };

  const result = await sendBongaSms(
    {
      phone: to,
      message: "Intelli-Cash test message. SMS delivery is working.",
      credentials
    },
    { networkEnabled: true }
  );

  const body = (result as { responseBody?: Record<string, unknown> }).responseBody ?? {};
  console.log("\nBonga SMS test");
  console.log("  to              :", mask(to));
  console.log("  service ID used :", credentials.BONGA_SMS_SERVICE_ID ?? process.env.BONGA_SMS_SERVICE_ID ?? "(env)");
  console.log("  result          :", result.status);
  console.log("  provider status :", result.providerStatus ?? "(none)", "(222 = success, 666 = error)");
  console.log("  provider message:", result.providerMessage ?? "(none)");
  console.log("  message id      :", result.providerReference ?? "(none)");
  console.log("  credits left    :", body.credits ?? "(not reported)");

  if (result.status !== "SENT") process.exitCode = 1;
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(() => prisma.$disconnect());
