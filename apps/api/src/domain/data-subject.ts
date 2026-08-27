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
 * Plans the closure of one login account.
 *
 * "Delete this user" cannot mean DELETE FROM User. Every relation pointing at
 * User is `onDelete: SetNull` — including `AuditEvent.actor` — so a real delete
 * would silently blank the actor on every audit record that person ever
 * created. The trail would still be there, still readable, and no longer able
 * to say who did any of it. In a system holding other people's savings that is
 * the worst possible outcome of a routine admin action.
 *
 * So the row survives as a pseudonymous key and the identity is stripped out of
 * it: the same shape as `planMemberErasure`, for the same reason.
 *
 * `passwordHash` is deliberately replaced rather than left. Status alone gates
 * login today, but a closed account whose credential still verifies is one
 * mistaken status flip away from being live again — and nobody would be
 * watching that account.
 *
 * `phone` is cleared rather than pseudonymised because it is UNIQUE and it is
 * how a person is identified at sign-in. Leaving a dead account holding a real
 * number means the human being cannot be registered again from that number.
 */
export function planUserAccountClosure(userId: string): ErasurePlan {
  const suffix = userId.slice(-6);

  return {
    erase: [
      { field: "name", replacement: `Closed account ${suffix}` },
      // Unique, so it needs a value rather than a blank — and a non-routable
      // one, so nothing can ever mail it by accident.
      { field: "email", replacement: `closed-${userId}@account.invalid` },
      { field: "phone", replacement: null },
      { field: "avatarUrl", replacement: null },
      { field: "passwordHash", replacement: "" }
    ],
    retain: [
      {
        entity: "AuditEvent",
        ground: "AUDIT_INTEGRITY",
        note: "Who approved, recorded and amended what. The actor link is SetNull, so deleting the row would erase accountability for every action this account ever took."
      },
      {
        entity: "GroupVisit.submittedByUserId",
        ground: "AUDIT_INTEGRITY",
        note: "Which agent stood with the group. A visit with no submitter cannot be verified after the fact."
      },
      {
        entity: "Attachment.uploadedByUserId",
        ground: "AUDIT_INTEGRITY",
        note: "Evidence is only evidence while it is attributable to whoever captured it."
      },
      {
        entity: "Member",
        ground: "FINANCIAL_RECORD",
        note: "Closing a login is not removing somebody from a group's roster. The member row, and the savings against it, belong to the group and survive. Erasing the person is a separate, narrower request — see planMemberErasure."
      },
      {
        entity: "User.id",
        ground: "PSEUDONYMOUS_KEY",
        note: "Retained so every row above stays joined to something. It identifies a row, not a person."
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
