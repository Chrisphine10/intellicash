/**
 * Kenyan mobile numbers get written many ways — often by the same person on
 * different days, and often copied off a letterhead:
 *
 *   0712345678        +254712345678      254712345678
 *   712345678         +254 (0)712 345 678   00254 712 345 678
 *
 * Everything that identifies a person by phone must compare the canonical
 * form, or one human ends up as several records. In a savings app that is not
 * cosmetic: a member whose number fails to match the roster is handed a fresh
 * empty passbook instead of the savings already recorded against her name.
 */

/** Digits in a Kenyan subscriber number, after the 254 country code. */
const LOCAL_DIGITS = 9;

export function normalisePhone(phone: string | null | undefined) {
  let digits = (phone ?? "").replace(/\D/g, "");
  if (!digits) return "";

  // `00` is the international access code — the dialled equivalent of a `+`.
  if (digits.startsWith("00")) digits = digits.slice(2);

  if (digits.startsWith("254")) {
    let rest = digits.slice(3);
    // "+254 (0)712…" — the national trunk `0` is redundant after the country
    // code, but people write it constantly. Dropping it is safe because no
    // real Kenyan number has ten digits after 254.
    if (rest.startsWith("0")) rest = rest.slice(1);
    return rest.length === LOCAL_DIGITS ? `254${rest}` : digits;
  }

  if (digits.startsWith("0")) {
    const rest = digits.slice(1);
    return rest.length === LOCAL_DIGITS ? `254${rest}` : digits;
  }

  if (digits.length === LOCAL_DIGITS) return `254${digits}`;

  // Not recognisably Kenyan (a foreign number, or simply too short). Return
  // the digits unchanged rather than inventing a country code — two different
  // lines must never collapse into one.
  return digits;
}

/**
 * The last nine digits, which are the same however the number was written.
 * Useful as a cheap database filter before comparing canonical forms, since
 * existing rows may hold any of the formats above.
 */
export function phoneTail(phone: string | null | undefined) {
  const digits = (phone ?? "").replace(/\D/g, "");
  return digits.slice(-LOCAL_DIGITS);
}

/** True when two differently-written numbers belong to the same line. */
export function samePhone(a: string | null | undefined, b: string | null | undefined) {
  const left = normalisePhone(a);
  const right = normalisePhone(b);
  return left.length > 0 && left === right;
}

/**
 * Whether an entered string could be a phone number at all.
 *
 * Judges the digits, not the punctuation: people type `+254 712 345 678`,
 * `0712-345-678` and `+254 (0)712 345 678`, and rejecting those for their
 * spacing tells a non-technical member their own number is invalid.
 */
export function looksLikePhone(value: string | null | undefined) {
  const digits = (value ?? "").replace(/\D/g, "");
  return digits.length >= 9 && digits.length <= 15;
}
