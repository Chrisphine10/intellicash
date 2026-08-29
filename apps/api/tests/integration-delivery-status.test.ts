import { afterEach, describe, expect, it, vi } from "vitest";
import { integrationAdapters } from "../src/domain/integrations";

/**
 * Bonga went to production with correct credentials and
 * `ENABLE_SMS_NETWORK_CALLS=false`. The console showed the provider as "Ready",
 * every send short-circuited to QUEUED, and no member was ever texted. Nothing
 * in the system said so.
 *
 * `configured` and `deliveryEnabled` are two different facts. These pin the
 * second one, because it is the one that was missing.
 */
const bongaCredentials = {
  BONGA_SMS_CLIENT_ID: "1120",
  BONGA_SMS_API_KEY: "test-key",
  BONGA_SMS_API_SECRET: "test-secret",
  BONGA_SMS_SERVICE_ID: "5843",
  BONGA_SMS_ENDPOINT: "http://example.invalid/v1/send-sms",
  BONGA_SMS_DEFAULT_PIN_TEMPLATE: "PIN {pin}",
  BONGA_SMS_OTP_TEMPLATE: "OTP {otp}"
};

// `smsNetworkCallsEnabled` refuses outright under NODE_ENV=test so the suite can
// never text the seed data. These cases are about the environment variable, so
// they have to step outside that guard deliberately.
function withSmsNetwork(enabled: boolean) {
  vi.stubEnv("NODE_ENV", "production");
  vi.stubEnv("ENABLE_SMS_NETWORK_CALLS", enabled ? "true" : "false");
}

afterEach(() => {
  vi.unstubAllEnvs();
});

describe("integration delivery status", () => {
  it("reports Bonga as configured but not delivering when the switch is off", () => {
    withSmsNetwork(false);

    const status = integrationAdapters.BONGA_SMS.buildStatus(bongaCredentials);

    expect(status.configured).toBe(true);
    expect(status.deliveryEnabled).toBe(false);
    expect(status.deliveryNote).toContain("ENABLE_SMS_NETWORK_CALLS");
  });

  it("reports Bonga as delivering once the switch is on", () => {
    withSmsNetwork(true);

    const status = integrationAdapters.BONGA_SMS.buildStatus(bongaCredentials);

    expect(status.configured).toBe(true);
    expect(status.deliveryEnabled).toBe(true);
    expect(status.deliveryNote).toBeNull();
  });

  it("fails the connection test rather than calling switched-off credentials healthy", async () => {
    withSmsNetwork(false);

    const result = await integrationAdapters.BONGA_SMS.test(bongaCredentials);

    expect(result.ok).toBe(false);
    expect(result.message).toContain("ENABLE_SMS_NETWORK_CALLS");
  });

  it("leaves providers without a kill switch reported as delivering", () => {
    withSmsNetwork(false);

    const status = integrationAdapters.GOOGLE_MAPS.buildStatus({
      GOOGLE_MAPS_BROWSER_API_KEY: "test"
    });

    expect(status.deliveryEnabled).toBe(true);
    expect(status.deliveryNote).toBeNull();
  });
});
