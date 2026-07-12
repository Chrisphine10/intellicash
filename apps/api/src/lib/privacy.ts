/**
 * Shared personal-data protection helpers.
 *
 * See DATA_PROTECTION.md at the repo root for the protocol these implement:
 * personal data is masked before it leaves a trust boundary (API responses to
 * lower-privilege audiences, log lines, provider messages) rather than at the
 * point of storage, so operational surfaces keep working with full data under
 * their existing role checks.
 */

export function maskPhone(phone: string) {
  const trimmed = phone.trim();
  if (trimmed.length <= 6) return trimmed.replace(/\d(?=\d{2})/g, "*");

  return `${trimmed.slice(0, 4)}${"*".repeat(Math.max(trimmed.length - 7, 3))}${trimmed.slice(-3)}`;
}

/**
 * Roles that run or directly support a group day-to-day and therefore
 * legitimately need member contact details (phone). Oversight roles —
 * PARTNER_OFFICER, LENDER, READ_ONLY — get member records for impact and
 * portfolio visibility, but their view of member phone numbers is masked.
 * MEMBER is included because member row-scoping already limits a member to
 * their own record, so this only ever reveals their own phone.
 */
const memberContactRoles = new Set(["IWL_ADMIN", "GROUP_ACCOUNT", "MEMBER"]);

export function canViewMemberContact(role?: string | null) {
  return role ? memberContactRoles.has(role) : false;
}

export function maskEmail(email: string) {
  const trimmed = email.trim();
  const atIndex = trimmed.indexOf("@");
  if (atIndex <= 0) return "***";

  const local = trimmed.slice(0, atIndex);
  const domain = trimmed.slice(atIndex + 1);
  const visible = local.slice(0, Math.min(2, local.length));

  return `${visible}${"*".repeat(Math.max(local.length - visible.length, 2))}@${domain}`;
}

/**
 * Query-string keys whose values are likely to carry personal data. Request
 * logs keep the path for traceability but must never persist these values.
 */
const sensitiveQueryKeys = new Set([
  "phone",
  "phonenumber",
  "msisdn",
  "email",
  "customeremail",
  "name",
  "customername",
  "fullname",
  "nationalid",
  "idnumber",
  "q",
  "query",
  "search"
]);

export function redactUrlForLogs(originalUrl: string) {
  const queryIndex = originalUrl.indexOf("?");
  if (queryIndex === -1) return originalUrl;

  const path = originalUrl.slice(0, queryIndex);
  const query = new URLSearchParams(originalUrl.slice(queryIndex + 1));
  let redacted = false;

  for (const key of query.keys()) {
    if (sensitiveQueryKeys.has(key.toLowerCase())) {
      query.set(key, "[redacted]");
      redacted = true;
    }
  }

  return redacted ? `${path}?${query.toString()}` : originalUrl;
}
