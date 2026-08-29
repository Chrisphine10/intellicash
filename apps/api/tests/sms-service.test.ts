import { describe, expect, it, vi } from "vitest";
import {
  isSendableSmsPhone,
  normalizeSmsPhone,
  renderSmsTemplate,
  sendBongaSms
} from "../src/services/sms-service";

describe("SMS service", () => {
  it("normalizes Kenyan mobile numbers for SMS delivery", () => {
    expect(normalizeSmsPhone("0757255710")).toBe("254757255710");
    expect(normalizeSmsPhone("+254 757 255 710")).toBe("254757255710");
  });

  it("renders admin-managed SMS templates with PIN and OTP placeholders", () => {
    expect(renderSmsTemplate("OTP {otp} expires in {ttlMinutes} minutes.", { otp: "123456", ttlMinutes: 15 })).toBe(
      "OTP 123456 expires in 15 minutes."
    );
    expect(renderSmsTemplate("PIN {pin}", { pin: "654321" })).toBe("PIN 654321");
  });

  it("sends Bonga SMS using the documented form-data fields", async () => {
    const fetchMock = vi.fn(async (_input: RequestInfo | URL, _init?: RequestInit) =>
      new Response(
        JSON.stringify({
          unique_id: 378008604,
          status_message: "sent",
          status: 222,
          error: null
        }),
        { status: 200, headers: { "content-type": "application/json" } }
      )
    );

    const result = await sendBongaSms(
      {
        phone: "0757255710",
        message: "Intelli Cash test OTP 123456.",
        credentials: {
          BONGA_SMS_CLIENT_ID: "1120",
          BONGA_SMS_API_KEY: "test-key",
          BONGA_SMS_API_SECRET: "test-secret",
          BONGA_SMS_SERVICE_ID: "5843"
        }
      },
      { fetch: fetchMock, networkEnabled: true }
    );

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const firstCall = fetchMock.mock.calls[0];
    if (!firstCall) throw new Error("Expected Bonga SMS fetch call.");
    const [url, init] = firstCall;
    const form = init?.body as FormData;
    expect(url).toBe("http://167.172.14.50:4002/v1/send-sms");
    expect(init?.method).toBe("POST");
    expect(form.get("apiClientID")).toBe("1120");
    expect(form.get("key")).toBe("test-key");
    expect(form.get("secret")).toBe("test-secret");
    expect(form.get("txtMessage")).toBe("Intelli Cash test OTP 123456.");
    expect(form.get("MSISDN")).toBe("254757255710");
    expect(form.get("serviceID")).toBe("5843");
    expect(result).toEqual(
      expect.objectContaining({
        status: "SENT",
        providerReference: "378008604"
      })
    );
  });

  it("keeps SMS queued when live network delivery is disabled", async () => {
    const fetchMock = vi.fn(async (_input: RequestInfo | URL, _init?: RequestInit) => new Response(null));

    const result = await sendBongaSms(
      {
        phone: "0757255710",
        message: "Intelli Cash queued test.",
        credentials: {
          BONGA_SMS_CLIENT_ID: "1120",
          BONGA_SMS_API_KEY: "test-key",
          BONGA_SMS_API_SECRET: "test-secret"
        }
      },
      { fetch: fetchMock, networkEnabled: false }
    );

    expect(fetchMock).not.toHaveBeenCalled();
    expect(result.status).toBe("QUEUED");
  });
});

describe("SMS phone validity", () => {
  it("accepts Kenyan mobile numbers in every form the app stores them", () => {
    expect(isSendableSmsPhone("0757255710")).toBe(true);
    expect(isSendableSmsPhone("+254 757 255 710")).toBe(true);
    expect(isSendableSmsPhone("254110255710")).toBe(true);
  });

  it("rejects what imported member rows actually contain", () => {
    // Spending a provider request on these bills the account and files a
    // failure that looks like a delivery problem rather than bad data.
    expect(isSendableSmsPhone("")).toBe(false);
    expect(isSendableSmsPhone("0757")).toBe(false);
    expect(isSendableSmsPhone("N/A")).toBe(false);
    expect(isSendableSmsPhone("254257255710")).toBe(false);
  });
});
