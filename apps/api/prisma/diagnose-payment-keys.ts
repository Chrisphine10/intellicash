import { prisma } from "../src/lib/prisma";
import { env } from "../src/config/env";
import { decryptCredentials } from "../src/services/integration-credentials";

/**
 * Read-only: which Paystack / M-Pesa credentials are stored, what kind they
 * are, and whether Paystack accepts the secret key. Never prints a key —
 * only its kind (test/live) and Paystack's yes/no.
 *
 *   bash prisma/run-with-service-env.sh prisma/diagnose-payment-keys.ts
 */

function kind(secret: string | undefined) {
  if (!secret) return "missing";
  if (secret.startsWith("sk_test_")) return "TEST secret";
  if (secret.startsWith("sk_live_")) return "LIVE secret";
  return "unrecognised";
}

async function paystackAccepts(secret: string | undefined) {
  if (!secret) return "not checked (no key)";
  try {
    const response = await fetch("https://api.paystack.co/balance", { headers: { Authorization: `Bearer ${secret}` } });
    const body = (await response.json().catch(() => null)) as { status?: boolean; message?: string } | null;
    return `${response.status} ${body?.status ? "accepted" : `refused: ${body?.message ?? "no message"}`}`;
  } catch (error) {
    return `unreachable: ${error instanceof Error ? error.message : String(error)}`;
  }
}

async function main() {
  console.log(`ENABLE_PAYMENT_NETWORK_CALLS=${env.ENABLE_PAYMENT_NETWORK_CALLS}`);

  const platform = await prisma.integrationConfig.findMany({ where: { provider: { in: ["PAYSTACK", "MPESA_DARAJA"] } } });
  for (const row of platform) {
    const creds = decryptCredentials(row.credentialsJson);
    if (row.provider === "PAYSTACK") {
      console.log(
        `platform PAYSTACK: enabled=${row.enabled} mode=${row.mode} secret=${kind(creds.PAYSTACK_SECRET_KEY)} ` +
          `public=${creds.PAYSTACK_PUBLIC_KEY ? creds.PAYSTACK_PUBLIC_KEY.slice(0, 8) + "…" : "missing"} ` +
          `paystack=${await paystackAccepts(creds.PAYSTACK_SECRET_KEY)}`
      );
    } else {
      const present = ["MPESA_CONSUMER_KEY", "MPESA_CONSUMER_SECRET", "MPESA_SHORTCODE", "MPESA_PASSKEY"].filter((k) => creds[k]);
      console.log(`platform MPESA_DARAJA: enabled=${row.enabled} mode=${row.mode} fields set: ${present.length}/4`);
    }
  }
  console.log(`env PAYSTACK secret=${kind(process.env.PAYSTACK_SECRET_KEY)}`);

  const groups = await prisma.groupIntegrationConfig.findMany({
    where: { provider: { in: ["PAYSTACK", "MPESA_DARAJA"] } },
    include: { group: { select: { code: true } } }
  });
  for (const row of groups) {
    const creds = decryptCredentials(row.credentialsJson);
    if (row.provider === "PAYSTACK") {
      console.log(
        `group ${row.group.code} PAYSTACK: enabled=${row.enabled} secret=${kind(creds.PAYSTACK_SECRET_KEY)} ` +
          `paystack=${await paystackAccepts(creds.PAYSTACK_SECRET_KEY)}`
      );
    } else {
      const present = ["MPESA_CONSUMER_KEY", "MPESA_CONSUMER_SECRET", "MPESA_SHORTCODE", "MPESA_PASSKEY"].filter((k) => creds[k]);
      console.log(`group ${row.group.code} MPESA_DARAJA: enabled=${row.enabled} fields set: ${present.length}/4`);
    }
  }
  const settings = await prisma.groupPaymentSettings.findMany({ include: { group: { select: { code: true } } } });
  for (const row of settings) {
    console.log(`settings ${row.group.code}: mode=${row.collectionMode} providers=${row.enabledProvidersJson} selfPay=${row.memberSelfPayEnabled}`);
  }
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(() => prisma.$disconnect());
