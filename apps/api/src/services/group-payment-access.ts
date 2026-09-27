import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError } from "../lib/http";

/**
 * Who may see and change a group's own online payments.
 *
 * - **See** (settings, provider status, settlement account, the payments
 *   themselves, which name members): `group-payments:read`, or `ledger:write`
 *   — whoever charges members must be able to follow their charges, and the
 *   phone's API keys carry `ledger:write` but predate the new permission.
 *   Partners, lenders and read-only viewers hold neither by default: they get
 *   group-level figures, never member-level payments.
 * - **Change** (providers on/off, where money is collected, passbook
 *   payments, the group's own provider accounts, its settlement account):
 *   `group-payments:configure`, AND either an IWL admin or THIS group's own
 *   account. The permission alone is not enough: a group account holds it for
 *   its own group, never for another. `groupPaymentsMayHold` already stops any
 *   other role holding it, whatever a template says; this is the second lock.
 */
export function maySeeGroupPayments(user: AuthenticatedUser | undefined) {
  if (!user) return false;
  return user.permissions.includes("group-payments:read") || user.permissions.includes("ledger:write");
}

export function assertMaySeeGroupPayments(user: AuthenticatedUser | undefined) {
  if (!user) throw new ApiHttpError(401, "UNAUTHENTICATED", "Please sign in to continue.");
  if (maySeeGroupPayments(user)) return;
  throw new ApiHttpError(
    403,
    "FORBIDDEN",
    "A group's online payments are visible to the group and to Intelli-Cash staff only."
  );
}

export function mayConfigureGroupPayments(user: AuthenticatedUser | undefined, groupId: string) {
  if (!user || !user.permissions.includes("group-payments:configure")) return false;
  if (user.role === "IWL_ADMIN") return true;
  return user.role === "GROUP_ACCOUNT" && user.groupId === groupId;
}

export function assertMayConfigureGroupPayments(user: AuthenticatedUser | undefined, groupId: string) {
  if (!user) throw new ApiHttpError(401, "UNAUTHENTICATED", "Please sign in to continue.");
  if (mayConfigureGroupPayments(user, groupId)) return;
  throw new ApiHttpError(
    403,
    "FORBIDDEN",
    "Only an IWL admin or the group's own account may change how this group is paid."
  );
}
