export type SmsProvider = "BONGA_SMS" | "AFRICAS_TALKING";
export type SmsSendStatus = "QUEUED" | "SENT" | "FAILED";

export interface SmsSendResult {
  provider: SmsProvider;
  status: SmsSendStatus;
  providerReference?: string | null;
  providerStatus?: number | string | null;
  providerMessage?: string | null;
  responseBody?: unknown;
}

export interface SmsSendInput {
  provider: SmsProvider;
  phone: string;
  message: string;
  credentials?: Record<string, string>;
}

export interface BongaSmsInput {
  phone: string;
  message: string;
  credentials?: Record<string, string>;
}

interface SmsSendDependencies {
  fetch?: typeof fetch;
  networkEnabled?: boolean;
}

const bongaSmsEndpoint = "http://167.172.14.50:4002/v1/send-sms";
export const bongaSmsRequiredCredentialKeys = [
  "BONGA_SMS_CLIENT_ID",
  "BONGA_SMS_API_KEY",
  "BONGA_SMS_API_SECRET",
  "BONGA_SMS_SERVICE_ID"
] as const;

export const bongaSmsOptionalCredentialKeys = [
  "BONGA_SMS_ENDPOINT",
  "BONGA_SMS_DEFAULT_PIN_TEMPLATE",
  "BONGA_SMS_OTP_TEMPLATE"
] as const;

export const bongaSmsCredentialKeys = [
  ...bongaSmsRequiredCredentialKeys,
  ...bongaSmsOptionalCredentialKeys
] as const;

type BongaSmsResponse = {
  unique_id?: number | string | null;
  status?: number | string | null;
  status_message?: string | null;
  error?: unknown;
};

function envEnabled(value: string | undefined) {
  return ["1", "true", "yes", "on"].includes(String(value ?? "").trim().toLowerCase());
}

export function smsNetworkCallsEnabled() {
  if (process.env.ENABLE_SMS_NETWORK_CALLS) {
    return envEnabled(process.env.ENABLE_SMS_NETWORK_CALLS);
  }

  return process.env.NODE_ENV !== "test";
}

export function normalizeSmsPhone(phone: string) {
  const digits = phone.replace(/\D/g, "");

  if (/^0\d{9}$/.test(digits)) return `254${digits.slice(1)}`;
  if (/^7\d{8}$/.test(digits)) return `254${digits}`;
  if (/^254\d{9}$/.test(digits)) return digits;

  return digits;
}

export function combineBongaSmsCredentials(storedCredentials: Record<string, string> = {}) {
  const credentials: Record<string, string> = {};

  for (const key of bongaSmsCredentialKeys) {
    const value = storedCredentials[key] || process.env[key];
    if (value) credentials[key] = value;
  }

  return credentials;
}

export function missingBongaSmsCredentials(credentials: Record<string, string>) {
  return bongaSmsRequiredCredentialKeys.filter((key) => !credentials[key]);
}

export function isBongaSmsConfigured(credentials: Record<string, string>) {
  return missingBongaSmsCredentials(credentials).length === 0;
}

export function renderSmsTemplate(template: string, values: Record<string, string | number>) {
  return template.replace(/\{([A-Z0-9_]+)\}/gi, (match, key: string) => {
    const value = values[key] ?? values[key.toLowerCase()] ?? values[key.toUpperCase()];
    return value === undefined ? match : String(value);
  });
}

function bongaEndpoint(credentials: Record<string, string>) {
  return credentials.BONGA_SMS_ENDPOINT || process.env.BONGA_SMS_ENDPOINT || bongaSmsEndpoint;
}

async function parseBongaResponse(response: Response) {
  const text = await response.text();
  if (!text) return null;

  try {
    return JSON.parse(text) as BongaSmsResponse;
  } catch {
    return { status: response.status, status_message: text } satisfies BongaSmsResponse;
  }
}

export async function sendBongaSms(
  input: BongaSmsInput,
  dependencies: SmsSendDependencies = {}
): Promise<SmsSendResult> {
  const credentials = combineBongaSmsCredentials(input.credentials);
  const clientId = credentials.BONGA_SMS_CLIENT_ID;
  const apiKey = credentials.BONGA_SMS_API_KEY;
  const apiSecret = credentials.BONGA_SMS_API_SECRET;
  const serviceId = credentials.BONGA_SMS_SERVICE_ID;
  const missing = missingBongaSmsCredentials(credentials);

  if (
    missing.length > 0 ||
    !clientId ||
    !apiKey ||
    !apiSecret ||
    !serviceId ||
    !(dependencies.networkEnabled ?? smsNetworkCallsEnabled())
  ) {
    return {
      provider: "BONGA_SMS",
      status: "QUEUED",
      providerMessage:
        missing.length > 0
          ? `Bonga SMS credentials are incomplete: ${missing.join(", ")}.`
          : "SMS network delivery is disabled."
    };
  }

  const form = new FormData();
  form.set("apiClientID", clientId);
  form.set("key", apiKey);
  form.set("secret", apiSecret);
  form.set("txtMessage", input.message);
  form.set("MSISDN", normalizeSmsPhone(input.phone));
  form.set("serviceID", serviceId);

  try {
    const fetcher = dependencies.fetch ?? fetch;
    const response = await fetcher(bongaEndpoint(credentials), {
      method: "POST",
      body: form
    });
    const responseBody = await parseBongaResponse(response);
    const providerStatus = responseBody?.status ?? response.status;
    const sent = response.ok && Number(providerStatus) === 222;

    return {
      provider: "BONGA_SMS",
      status: sent ? "SENT" : "FAILED",
      providerReference: responseBody?.unique_id ? String(responseBody.unique_id) : null,
      providerStatus,
      providerMessage: responseBody?.status_message ?? response.statusText,
      responseBody
    };
  } catch (error) {
    return {
      provider: "BONGA_SMS",
      status: "FAILED",
      providerMessage: error instanceof Error ? error.message : "Bonga SMS request failed."
    };
  }
}

export async function sendSms(input: SmsSendInput, dependencies: SmsSendDependencies = {}) {
  if (input.provider === "BONGA_SMS") {
    return sendBongaSms(
      {
        phone: input.phone,
        message: input.message,
        credentials: input.credentials
      },
      dependencies
    );
  }

  return {
    provider: input.provider,
    status: "QUEUED",
    providerMessage: `${input.provider} live SMS sending is not implemented.`
  } satisfies SmsSendResult;
}
