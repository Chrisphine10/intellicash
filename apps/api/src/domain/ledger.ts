import { hashPayload } from "../lib/crypto";

/**
 * The fields that make a ledger row a financial record.
 *
 * Changing any of these after the fact changes what the group's books say
 * happened. Everything not listed — `loanId`, for instance — is a back-link
 * added once the row's place in the story is known, and moves no money.
 */
const IMMUTABLE_LEDGER_FIELDS = [
  "groupId",
  "memberId",
  "meetingId",
  "cycleId",
  "fundAccountId",
  "type",
  "amountCents",
  "currency",
  "direction",
  "signature",
  "clientRequestId",
  "createdAt"
] as const;

/**
 * Enforces that a ledger row is written once and never rewritten.
 *
 * This existed and was called from nowhere. "Append-only" was a property the
 * code was described as having — in the privacy notice, in comments, in the
 * data protection audit — and nothing checked it. Meanwhile exactly one call
 * site does update a ledger row: attaching a repayment to the loan it settles.
 *
 * So the original all-or-nothing version could not be wired without breaking a
 * legitimate flow, which is presumably why it never was. It now expresses the
 * invariant that actually matters — the money and the parties never change —
 * and permits a back-link, which is the one thing that legitimately arrives
 * after the row does.
 */
export function assertAppendOnlyOperation(
  operation: "create" | "update" | "delete",
  changedFields?: readonly string[]
) {
  if (operation === "create") return;

  if (operation === "delete") {
    throw new Error("Financial ledger entries are append-only and cannot be deleted.");
  }

  /*
   * An update MUST say what it changes.
   *
   * Defaulting this to an empty list would mean a caller who forgets the second
   * argument gets a guard that runs, passes, and protects nothing — which is
   * the precise failure this whole exercise is about. Refusing is the only
   * answer that cannot be silently wrong.
   */
  if (changedFields === undefined) {
    throw new Error(
      "An update to a ledger entry must declare which fields it changes, so they can be checked."
    );
  }

  const financial = changedFields.filter((field) =>
    (IMMUTABLE_LEDGER_FIELDS as readonly string[]).includes(field)
  );
  if (financial.length > 0) {
    throw new Error(
      `Financial ledger entries are append-only. Cannot change: ${financial.join(", ")}.`
    );
  }
}

export function signLedgerEntry(payload: unknown) {
  return hashPayload(payload);
}
