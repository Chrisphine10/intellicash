/**
 * What a group's document register says about one document.
 *
 * The stored shape deliberately keeps three separate facts apart:
 *
 *   - `presence`     — does the group hold it at all? An observation.
 *   - `verification` — is the copy on file accepted? A back-office judgement.
 *   - `expiresOn`    — a date.
 *
 * Readers want one chip. Producing that chip is this module's whole job, and it
 * is done at READ time so the three facts underneath stay independently
 * queryable. Storing the chip instead — the usual
 * Verified/Missing/Expired enum — collapses them, and the moment a certificate
 * expires it also stops recording that anyone ever verified it. "How many
 * verified certificates expire this quarter" then has no answer at all.
 *
 * Deriving it also means nothing goes stale. There is no nightly job flipping
 * documents to EXPIRED, and therefore no window in which the database disagrees
 * with the calendar.
 */

export const DOCUMENT_STATES = [
  "MISSING",
  "EXPIRED",
  "REJECTED",
  "VERIFIED",
  "UNVERIFIED"
] as const;

export type DocumentState = (typeof DOCUMENT_STATES)[number];

export interface DocumentFacts {
  presence: string;
  verification: string;
  expiresOn?: Date | null;
}

export interface DocumentStatus {
  state: DocumentState;
  /** The stored facts, unchanged — the chip never replaces them. */
  presence: string;
  verification: string;
  expiresOn: Date | null;
  /** Negative once past. Null when the document does not expire. */
  daysUntilExpiry: number | null;
  /** Expired, or close enough that somebody should be chasing it. */
  needsAttention: boolean;
  label: string;
}

/** Inside this window a document is worth chasing before it lapses. */
export const EXPIRY_WARNING_DAYS = 60;

const MS_PER_DAY = 24 * 60 * 60 * 1000;

/**
 * Resolves the single state a reader should see.
 *
 * Order matters. A document that is absent is MISSING whatever else is
 * recorded about it — an expiry date on a document nobody has is noise. A
 * REJECTED document outranks expiry too: "we looked at this and did not accept
 * it" is a stronger statement than "it has lapsed", and showing EXPIRED would
 * imply it was once good.
 */
export function documentStatus(facts: DocumentFacts, now: Date = new Date()): DocumentStatus {
  const expiresOn = facts.expiresOn ?? null;
  const daysUntilExpiry =
    expiresOn === null
      ? null
      : Math.floor((expiresOn.getTime() - now.getTime()) / MS_PER_DAY);

  const state = resolveState(facts, daysUntilExpiry);

  return {
    state,
    presence: facts.presence,
    verification: facts.verification,
    expiresOn,
    daysUntilExpiry,
    needsAttention:
      state === "MISSING" ||
      state === "EXPIRED" ||
      state === "REJECTED" ||
      (daysUntilExpiry !== null && daysUntilExpiry <= EXPIRY_WARNING_DAYS),
    label: LABELS[state]
  };
}

function resolveState(facts: DocumentFacts, daysUntilExpiry: number | null): DocumentState {
  if (facts.presence !== "PRESENT") return "MISSING";
  if (facts.verification === "REJECTED") return "REJECTED";

  // Expiry is only meaningful for a document that exists and was not refused.
  // Note this does NOT consult `verification`: an expired certificate that was
  // verified is still expired, and the fact that it was verified survives in
  // the column for anyone who asks.
  if (daysUntilExpiry !== null && daysUntilExpiry < 0) return "EXPIRED";

  return facts.verification === "VERIFIED" ? "VERIFIED" : "UNVERIFIED";
}

const LABELS: Record<DocumentState, string> = {
  MISSING: "Not held",
  EXPIRED: "Expired",
  REJECTED: "Not accepted",
  VERIFIED: "Verified",
  UNVERIFIED: "Awaiting check"
};

/**
 * How complete a group's register is.
 *
 * Counts VERIFIED only. A document that is merely present proves the group has
 * a piece of paper, not that anyone has looked at it — reporting those as
 * complete would make the register describe filing rather than compliance.
 */
export function registerSummary(statuses: readonly DocumentStatus[]) {
  const verified = statuses.filter((status) => status.state === "VERIFIED").length;
  return {
    total: statuses.length,
    verified,
    missing: statuses.filter((status) => status.state === "MISSING").length,
    expired: statuses.filter((status) => status.state === "EXPIRED").length,
    needsAttention: statuses.filter((status) => status.needsAttention).length,
    percentVerified:
      statuses.length === 0 ? 0 : Math.round((verified / statuses.length) * 100)
  };
}
