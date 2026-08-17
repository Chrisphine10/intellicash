/**
 * What a data subject may take away, and what may be erased.
 *
 * Pure: no I/O, no Prisma. The decision about which fields survive an erasure
 * request is the part with legal weight, so it lives where it can be read and
 * tested on its own rather than being implicit in a delete statement.
 *
 * Kenya Data Protection Act, 2019 — ss. 26, 38, 40.
 */

export const DATA_SUBJECT_CONTRACT_VERSION = "1.0.0";

/**
 * Why a field survives an erasure request.
 *
 * Erasure under the Act is not unconditional, and in a savings group it cannot
 * be: a member's ledger entries are half of somebody else's balance. Deleting
 * them would corrupt the group's books and harm other data subjects, which is
 * a worse privacy outcome than retaining a pseudonymous row.
 */
export type RetentionGround =
  /** The group's financial record. Other members' balances depend on it. */
  | "FINANCIAL_RECORD"
  /** Proves who did what. Deleting it defeats the audit trail's purpose. */
  | "AUDIT_INTEGRITY"
  /** Needed to keep the row addressable after identity is stripped. */
  | "PSEUDONYMOUS_KEY";

export interface ErasureField {
  field: string;
  /** What replaces it. `null` clears the column outright. */
  replacement: string | null;
}

export interface ErasurePlan {
  /** Identity and contact data, removed or replaced. */
  erase: ErasureField[];
  /** Kept, each with the ground for keeping it. */
  retain: { entity: string; ground: RetentionGround; note: string }[];
}

/**
 * Plans an erasure for one member.
 *
 * The shape is deliberate: **strip the identity, keep the arithmetic.** After
 * this a ledger row still reconciles and an auditor can still follow who
 * approved what, but nothing in the record says who the person was, how to
 * reach them, or which national ID they hold.
 *
 * `fullName` becomes a stable pseudonym rather than blank. A blank name breaks
 * every screen that renders a member list, and a group reading "  " next to a
 * balance will assume the software has lost their record.
 */
export function planMemberErasure(memberId: string): ErasurePlan {
  const pseudonym = `Erased member ${memberId.slice(-6)}`;

  return {
    erase: [
      { field: "fullName", replacement: pseudonym },
      { field: "phone", replacement: "" },
      { field: "nationalIdHash", replacement: null },
      // The meeting keys. Leaving them would let someone who later recovered
      // the PIN still act as this member.
      { field: "pinHash", replacement: null },
      { field: "currentOtpHash", replacement: null }
    ],
    retain: [
      {
        entity: "LedgerEntry",
        ground: "FINANCIAL_RECORD",
        note: "A member's contributions and repayments are part of the group's books; removing them would change other members' balances."
      },
      {
        entity: "Loan",
        ground: "FINANCIAL_RECORD",
        note: "An outstanding or repaid loan is a record between the member and the group, not solely about the member."
      },
      {
        entity: "AuditEvent",
        ground: "AUDIT_INTEGRITY",
        note: "Who approved and recorded what. An audit trail that can be edited by its subject is not an audit trail."
      },
      {
        entity: "Member.id",
        ground: "PSEUDONYMOUS_KEY",
        note: "Retained so the financial rows above stay joined to something. It identifies a row, not a person."
      }
    ]
  };
}

/**
 * Everything held about one member, named by relation, for an access or
 * portability request.
 *
 * Listed here rather than assembled ad hoc in the route so that adding a table
 * which references Member has an obvious place to be declared — the failure
 * mode for a subject access request is silently omitting something.
 */
export const MEMBER_PERSONAL_DATA_RELATIONS = [
  "attendance",
  "ledgerEntries",
  "loans",
  "keySubmissions",
  "pinDeliveries",
  "groupPayments",
  "pollVotes",
  "pollCandidacies",
  "welfareExpenses",
  "roleAssignments",
  "membershipLinks"
] as const;

/**
 * Fields never included in an export.
 *
 * A subject access request returns the person's data to the person. It must not
 * hand back a credential: a PIN hash of a four-digit secret is recoverable, and
 * exporting it would turn a privacy right into a way to harvest keys.
 */
export const EXPORT_EXCLUDED_FIELDS = [
  "pinHash",
  "currentOtpHash",
  "nationalIdHash"
] as const;

export function stripSecrets<T extends Record<string, unknown>>(row: T): Partial<T> {
  const copy: Record<string, unknown> = { ...row };
  for (const field of EXPORT_EXCLUDED_FIELDS) delete copy[field];
  // Anything that looks like a credential, even if added later and not listed.
  for (const key of Object.keys(copy)) {
    if (/hash|secret|ciphertext|token/i.test(key)) delete copy[key];
  }
  return copy as Partial<T>;
}
