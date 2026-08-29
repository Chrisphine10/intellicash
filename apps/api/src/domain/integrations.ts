import type { IntegrationProvider } from "@intellicash/shared";
import { integrationProviders } from "@intellicash/shared";
import { env } from "../config/env";
import { smsNetworkCallsEnabled } from "../services/sms-service";

export interface IntegrationStatus {
  provider: IntegrationProvider;
  displayName: string;
  mode: "SANDBOX";
  configured: boolean;
  enabled: boolean;
  requiredEnv: string[];
  missingEnv: string[];
  envCredentialKeys: string[];
  storedCredentialKeys: string[];
  networkTestsAllowed: boolean;
  /**
   * Whether a stored credential would actually reach the provider.
   *
   * `configured` only says the keys are present. Production ran with
   * `ENABLE_SMS_NETWORK_CALLS=false`, so a correctly configured Bonga account
   * showed green here while `sendBongaSms` short-circuited every send to
   * QUEUED and nobody was ever texted. Credentials being right and delivery
   * being on are two different facts and the console has to show both.
   */
  deliveryEnabled: boolean;
  /** Why delivery is off, for the operator who has to fix it. */
  deliveryNote?: string | null;
  credentialsUpdatedAt?: string | null;
  lastCheckedAt?: string | null;
}

export interface IntegrationAdapter {
  provider: IntegrationProvider;
  displayName: string;
  requiredEnv: string[];
  sandboxBaseUrl?: string;
  delivery?: DeliverySwitch;
  buildStatus(credentials?: Record<string, string>, meta?: IntegrationStatusMeta): IntegrationStatus;
  test(
    credentials?: Record<string, string>,
    meta?: IntegrationStatusMeta
  ): Promise<{ ok: boolean; message: string; status: IntegrationStatus }>;
}

export interface IntegrationStatusMeta {
  credentialsUpdatedAt?: string | null;
  lastCheckedAt?: string | null;
}

/**
 * A named kill switch that stands between stored credentials and a real
 * outbound call. Evaluated per request rather than captured at module load,
 * because the tests flip the environment variable between cases.
 */
export interface DeliverySwitch {
  envKey: string;
  enabled: () => boolean;
}

const smsDelivery: DeliverySwitch = {
  envKey: "ENABLE_SMS_NETWORK_CALLS",
  enabled: smsNetworkCallsEnabled
};

const paymentDelivery: DeliverySwitch = {
  envKey: "ENABLE_PAYMENT_NETWORK_CALLS",
  enabled: () => env.ENABLE_PAYMENT_NETWORK_CALLS
};

class SandboxAdapter implements IntegrationAdapter {
  provider: IntegrationProvider;
  displayName: string;
  requiredEnv: string[];
  sandboxBaseUrl?: string;
  delivery?: DeliverySwitch;

  constructor(options: {
    provider: IntegrationProvider;
    displayName: string;
    requiredEnv: string[];
    sandboxBaseUrl?: string;
    delivery?: DeliverySwitch;
  }) {
    this.provider = options.provider;
    this.displayName = options.displayName;
    this.requiredEnv = options.requiredEnv;
    this.sandboxBaseUrl = options.sandboxBaseUrl;
    this.delivery = options.delivery;
  }

  buildStatus(credentials: Record<string, string> = {}, meta: IntegrationStatusMeta = {}): IntegrationStatus {
    const envCredentialKeys = this.requiredEnv.filter((key) => Boolean(process.env[key]));
    const storedCredentialKeys = this.requiredEnv.filter((key) => Boolean(credentials[key]));
    const missingEnv = this.requiredEnv.filter((key) => !process.env[key] && !credentials[key]);
    const deliveryEnabled = this.delivery ? this.delivery.enabled() : true;

    return {
      provider: this.provider,
      displayName: this.displayName,
      mode: "SANDBOX",
      configured: missingEnv.length === 0,
      enabled: true,
      requiredEnv: this.requiredEnv,
      missingEnv,
      envCredentialKeys,
      storedCredentialKeys,
      credentialsUpdatedAt: meta.credentialsUpdatedAt ?? null,
      lastCheckedAt: meta.lastCheckedAt ?? null,
      networkTestsAllowed: env.ALLOW_SANDBOX_NETWORK_TESTS,
      deliveryEnabled,
      deliveryNote:
        deliveryEnabled || !this.delivery
          ? null
          : `${this.delivery.envKey} is off, so nothing is delivered even with valid credentials.`
    };
  }

  async test(credentials: Record<string, string> = {}, meta: IntegrationStatusMeta = {}) {
    const status = this.buildStatus(credentials, meta);

    if (!status.configured) {
      return {
        ok: false,
        message: `${this.displayName} sandbox credentials are incomplete.`,
        status
      };
    }

    // Credentials that cannot leave the building are not a pass. Saying "ok"
    // here is what let a switched-off provider look healthy.
    if (!status.deliveryEnabled) {
      return {
        ok: false,
        message: `${this.displayName} credentials are stored, but ${status.deliveryNote}`,
        status
      };
    }

    if (!env.ALLOW_SANDBOX_NETWORK_TESTS) {
      return {
        ok: true,
        message:
          "Sandbox credentials are present. Network test skipped because ALLOW_SANDBOX_NETWORK_TESTS is false.",
        status
      };
    }

    const sandboxBaseUrl = this.sandboxBaseUrl ?? this.resolveCredentialBaseUrl(credentials);

    if (!sandboxBaseUrl) {
      return {
        ok: true,
        message: "Sandbox credentials are present. No safe network probe is configured.",
        status
      };
    }

    const response = await fetch(sandboxBaseUrl, { method: "GET" });
    return {
      ok: response.ok,
      message: `${this.displayName} sandbox responded with HTTP ${response.status}.`,
      status
    };
  }

  private resolveCredentialBaseUrl(credentials: Record<string, string>) {
    const baseUrlKey = this.requiredEnv.find((key) => key.endsWith("_BASE_URL"));
    return baseUrlKey ? credentials[baseUrlKey] : undefined;
  }
}

export const integrationAdapters: Record<IntegrationProvider, IntegrationAdapter> = {
  MPESA_DARAJA: new SandboxAdapter({
    provider: "MPESA_DARAJA",
    displayName: "M-Pesa Daraja",
    requiredEnv: [
      "MPESA_CONSUMER_KEY",
      "MPESA_CONSUMER_SECRET",
      "MPESA_SHORTCODE",
      "MPESA_PASSKEY",
      "MPESA_CALLBACK_URL",
      "MPESA_INITIATOR_NAME",
      "MPESA_SECURITY_CREDENTIAL",
      "MPESA_B2C_RESULT_URL",
      "MPESA_B2C_TIMEOUT_URL"
    ],
    sandboxBaseUrl: "https://sandbox.safaricom.co.ke",
    delivery: paymentDelivery
  }),
  AFRICAS_TALKING: new SandboxAdapter({
    provider: "AFRICAS_TALKING",
    displayName: "Africa's Talking",
    requiredEnv: [
      "AFRICASTALKING_USERNAME",
      "AFRICASTALKING_API_KEY",
      "AFRICASTALKING_SENDER_ID"
    ],
    sandboxBaseUrl: "https://api.sandbox.africastalking.com",
    delivery: smsDelivery
  }),
  BONGA_SMS: new SandboxAdapter({
    provider: "BONGA_SMS",
    displayName: "Bonga SMS",
    requiredEnv: [
      "BONGA_SMS_CLIENT_ID",
      "BONGA_SMS_API_KEY",
      "BONGA_SMS_API_SECRET",
      "BONGA_SMS_SERVICE_ID",
      "BONGA_SMS_ENDPOINT",
      "BONGA_SMS_DEFAULT_PIN_TEMPLATE",
      "BONGA_SMS_OTP_TEMPLATE"
    ],
    delivery: smsDelivery
  }),
  IPRS: new SandboxAdapter({
    provider: "IPRS",
    displayName: "IPRS KYC",
    requiredEnv: ["IPRS_BASE_URL", "IPRS_CLIENT_ID", "IPRS_CLIENT_SECRET"],
    sandboxBaseUrl: process.env.IPRS_BASE_URL
  }),
  KCB_BUNI: new SandboxAdapter({
    provider: "KCB_BUNI",
    displayName: "KCB Buni",
    requiredEnv: [
      "KCB_BUNI_BASE_URL",
      "KCB_BUNI_CLIENT_ID",
      "KCB_BUNI_CLIENT_SECRET",
      "KCB_BUNI_CALLBACK_URL"
    ],
    sandboxBaseUrl: process.env.KCB_BUNI_BASE_URL,
    delivery: paymentDelivery
  }),
  PAYSTACK: new SandboxAdapter({
    provider: "PAYSTACK",
    displayName: "Paystack",
    requiredEnv: ["PAYSTACK_SECRET_KEY", "PAYSTACK_PUBLIC_KEY"],
    sandboxBaseUrl: "https://api.paystack.co",
    delivery: paymentDelivery
  }),
  TRANSUNION_CRB: new SandboxAdapter({
    provider: "TRANSUNION_CRB",
    displayName: "TransUnion CRB",
    requiredEnv: [
      "TRANSUNION_BASE_URL",
      "TRANSUNION_CLIENT_ID",
      "TRANSUNION_CLIENT_SECRET"
    ],
    sandboxBaseUrl: process.env.TRANSUNION_BASE_URL
  }),
  MFARM: new SandboxAdapter({
    provider: "MFARM",
    displayName: "M-Farm",
    requiredEnv: ["MFARM_BASE_URL", "MFARM_API_KEY"],
    sandboxBaseUrl: process.env.MFARM_BASE_URL
  }),
  GOOGLE_MAPS: new SandboxAdapter({
    provider: "GOOGLE_MAPS",
    displayName: "Google Maps",
    requiredEnv: ["GOOGLE_MAPS_BROWSER_API_KEY"]
  })
};

export function getIntegrationAdapter(provider: string) {
  if (!integrationProviders.includes(provider as IntegrationProvider)) {
    return null;
  }

  return integrationAdapters[provider as IntegrationProvider];
}

export function getIntegrationHealth(
  credentialsByProvider: Partial<Record<IntegrationProvider, Record<string, string>>> = {},
  metaByProvider: Partial<Record<IntegrationProvider, IntegrationStatusMeta>> = {}
) {
  const statuses = integrationProviders.map((provider) =>
    integrationAdapters[provider].buildStatus(
      credentialsByProvider[provider] ?? {},
      metaByProvider[provider] ?? {}
    )
  );

  return {
    configured: statuses.filter((status) => status.configured).length,
    total: statuses.length,
    statuses
  };
}
