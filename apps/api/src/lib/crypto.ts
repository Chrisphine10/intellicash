import {
  createCipheriv,
  createDecipheriv,
  createHash,
  createHmac,
  pbkdf2Sync,
  randomBytes,
  timingSafeEqual
} from "node:crypto";
import { env } from "../config/env";

export function sha256(value: string) {
  return createHash("sha256").update(value).digest("hex");
}

/**
 * Work factor for [derivePinVerifier]. Matches the phone's `MeetingUnlock`,
 * because the phone is what recomputes it: the cost is paid on a handset once
 * per unlock attempt, not here.
 */
const PIN_VERIFIER_ITERATIONS = 30000;
const PIN_VERIFIER_PREFIX = "pbkdf2-sha256";

/**
 * A verifier a device can check a PIN against offline.
 *
 * Was a single `sha256(deviceId:memberId:pin)`. A meeting PIN is four digits,
 * so that is 10,000 candidates and a complete lookup table computes in
 * milliseconds — anyone who obtained a cached verifier blob recovered every
 * member's PIN, and those PINs are the three keys that open a meeting.
 *
 * The salt travels inside the returned string because the device has to
 * recompute the same value from the PIN a member types. Random per verifier,
 * so two members who choose the same PIN still produce different values.
 *
 * Format matches the phone's stored hash — `pbkdf2-sha256$iters$salt$hash` —
 * so there is one shape to parse rather than two.
 */
export function derivePinVerifier(deviceId: string, memberId: string, pin: string, salt?: string) {
  const saltValue = salt ?? randomBytes(16).toString("base64url");
  const derived = pbkdf2Sync(
    `${deviceId}:${memberId}:${pin}`,
    saltValue,
    PIN_VERIFIER_ITERATIONS,
    32,
    "sha256"
  ).toString("base64url");
  return `${PIN_VERIFIER_PREFIX}$${PIN_VERIFIER_ITERATIONS}$${saltValue}$${derived}`;
}

/**
 * Checks a PIN against a verifier this module produced.
 *
 * Not used by the server today — verifiers are emitted for devices to check
 * offline — but it is the definition of the format, and it is what lets the
 * format be tested rather than merely asserted.
 */
export function verifyPinVerifier(
  verifier: string,
  deviceId: string,
  memberId: string,
  pin: string
) {
  const parts = verifier.split("$");
  if (parts.length !== 4 || parts[0] !== PIN_VERIFIER_PREFIX) return false;
  const iterations = Number(parts[1]);
  const salt = parts[2];
  if (!Number.isInteger(iterations) || iterations <= 0 || !salt) return false;

  const expected = pbkdf2Sync(`${deviceId}:${memberId}:${pin}`, salt, iterations, 32, "sha256");
  const actual = Buffer.from(parts[3] ?? "", "base64url");
  return expected.length === actual.length && timingSafeEqual(expected, actual);
}

export function createOpaqueToken() {
  return randomBytes(32).toString("base64url");
}

export function signValue(value: string) {
  return createHmac("sha256", env.SESSION_SECRET).update(value).digest("base64url");
}

export function verifySignedValue(value: string, signature: string) {
  const expected = signValue(value);
  const left = Buffer.from(signature);
  const right = Buffer.from(expected);

  return left.length === right.length && timingSafeEqual(left, right);
}

export function hashPayload(payload: unknown) {
  return sha256(JSON.stringify(payload));
}

function credentialKey() {
  return createHash("sha256").update(env.SESSION_SECRET).digest();
}

export function encryptJson(payload: unknown) {
  const iv = randomBytes(12);
  const cipher = createCipheriv("aes-256-gcm", credentialKey(), iv);
  const plaintext = JSON.stringify(payload);
  const encrypted = Buffer.concat([cipher.update(plaintext, "utf8"), cipher.final()]);
  const tag = cipher.getAuthTag();

  return [
    "v1",
    iv.toString("base64url"),
    tag.toString("base64url"),
    encrypted.toString("base64url")
  ].join(".");
}

export function decryptJson<T>(ciphertext: string): T {
  const [version, ivText, tagText, encryptedText] = ciphertext.split(".");

  if (version !== "v1" || !ivText || !tagText || !encryptedText) {
    throw new Error("Unsupported encrypted payload format.");
  }

  const decipher = createDecipheriv(
    "aes-256-gcm",
    credentialKey(),
    Buffer.from(ivText, "base64url")
  );
  decipher.setAuthTag(Buffer.from(tagText, "base64url"));

  const decrypted = Buffer.concat([
    decipher.update(Buffer.from(encryptedText, "base64url")),
    decipher.final()
  ]);

  return JSON.parse(decrypted.toString("utf8")) as T;
}
