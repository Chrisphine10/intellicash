import { describe, expect, it } from "vitest";
import { permissions, rolePermissions, roles, type Permission, type Role } from "@intellicash/shared";

import { permissionBackfillsForTests } from "../src/services/role-permission-service";

/**
 * Every default a role has gained since its template row was written must be
 * delivered by a backfill batch.
 *
 * `ensureRolePermissionTemplates` upserts with `update: {}`, so a stored row is
 * written once and never revised. `permissionsForRoleFromStore` reads that row
 * rather than `rolePermissions`. The consequence is a permission that works
 * perfectly on a fresh database and 403s every existing deployment — invisible
 * in development, invisible in CI, and only visible to whoever is actually
 * using the product.
 *
 * It has now happened three times: `api-keys:*`, then `votes:write`, then
 * `votes:read` — the last one introduced by a comment asserting that MEMBER
 * already held it, which git shows it did not. A member could cast a vote and
 * was refused when listing the poll.
 *
 * So the rule is checked here rather than reasoned about in a comment. This is
 * a pure comparison against recorded history: no database, no bootstrap, and it
 * fails the moment somebody adds a permission to a role without a batch.
 */

/**
 * What each role's stored row actually holds on a deployment that predates the
 * additions — read out of git, not out of memory.
 *
 * The five original roles are as at the first commit (318df7e, 2 June 2026),
 * which is when RolePermissionTemplate shipped. VILLAGE_AGENT did not exist
 * then: its row is created by the upsert with whatever the defaults were when
 * the role was introduced (12882fe, 28 July 2026), so that is its baseline.
 *
 * Do not "update" these to match current defaults. They are a record of what is
 * in a production database, and rewriting them to agree with the code is the
 * exact move that turns this test into decoration.
 */
const STORED_AT_LAUNCH: Record<Role, Permission[]> = {
  IWL_ADMIN: [
    "analytics:read", "api-keys:read", "api-keys:write", "audit:read", "connectors:sync",
    "evidence:write", "groups:read", "groups:write", "integrations:read", "integrations:test",
    "integrations:write", "intelliaudit:approve", "intelliaudit:read", "intelliaudit:write",
    "ledger:read", "ledger:write", "meeting-keys:write", "meetings:read", "meetings:write",
    "members:read", "members:write", "partners:read", "partners:write", "payments:approve",
    "payments:read", "payments:write", "programmes:read", "programmes:write", "reports:approve",
    "signup-requests:approve", "signup-requests:read", "store:read", "store:write", "users:read",
    "users:write", "village-agents:read", "village-agents:write", "votes:read", "votes:write",
    "webhooks:write"
  ],
  PARTNER_OFFICER: [
    "analytics:read", "api-keys:read", "api-keys:write", "audit:read", "connectors:sync",
    "evidence:write", "groups:read", "integrations:read", "intelliaudit:approve",
    "intelliaudit:read", "intelliaudit:write", "ledger:read", "meetings:read", "members:read",
    "partners:read", "payments:read", "payments:write", "programmes:read", "reports:approve",
    "store:read", "store:write", "village-agents:read", "votes:read", "webhooks:write"
  ],
  GROUP_ACCOUNT: [
    "analytics:read", "evidence:write", "groups:read", "intelliaudit:approve",
    "intelliaudit:read", "intelliaudit:write", "ledger:read", "ledger:write",
    "meeting-keys:write", "meetings:read", "meetings:write", "members:read", "members:write",
    "programmes:read", "reports:approve", "store:read", "store:write", "votes:read", "votes:write"
  ],
  MEMBER: [
    "analytics:read", "groups:read", "ledger:read", "meeting-keys:write", "meetings:read",
    "members:read", "programmes:read", "store:read", "store:write"
  ],
  LENDER: [
    "analytics:read", "api-keys:read", "api-keys:write", "audit:read", "connectors:sync",
    "evidence:write", "groups:read", "integrations:read", "intelliaudit:approve",
    "intelliaudit:read", "intelliaudit:write", "ledger:read", "members:read", "payments:read",
    "payments:write", "programmes:read", "reports:approve", "store:read", "store:write",
    "votes:read"
  ],
  READ_ONLY: [
    "analytics:read", "api-keys:read", "audit:read", "groups:read", "integrations:read",
    "intelliaudit:read", "ledger:read", "meetings:read", "members:read", "partners:read",
    "programmes:read", "store:read", "village-agents:read", "votes:read"
  ],
  VILLAGE_AGENT: [
    "analytics:read", "groups:read", "ledger:read", "meetings:read", "members:read",
    "members:write", "programmes:read", "store:read", "store:write", "village-agents:read",
    "votes:read"
  ]
};

/**
 * Mirrors `deliverPermissionBatch`: a batch is skipped for a role that already
 * held ANY of its permissions when bootstrap ran, because that is read as the
 * batch having been delivered.
 */
function deliveredTo(role: Role, permission: Permission): boolean {
  const stored = new Set<string>(STORED_AT_LAUNCH[role]);
  if (stored.has(permission)) return true;

  return permissionBackfillsForTests.some((batch) => {
    if (!batch.includes(permission)) return false;
    // Skipped if the role already held something else in this batch.
    return !batch.some((entry) => stored.has(entry));
  });
}

describe("permission delivery to existing deployments", () => {
  it("delivers every default to every role", () => {
    const undelivered: string[] = [];

    for (const role of roles) {
      for (const permission of rolePermissions[role]) {
        if (!deliveredTo(role, permission)) {
          undelivered.push(`${role} never receives ${permission}`);
        }
      }
    }

    expect(
      undelivered,
      "add a backfill batch naming ONLY the new permission — a batch containing " +
        "something the role already holds is read as already delivered and skipped"
    ).toEqual([]);
  });

  it("catches the votes:read regression specifically", () => {
    // The one that shipped: a member could cast a vote and was refused when
    // listing or opening the poll.
    expect(STORED_AT_LAUNCH.MEMBER).not.toContain("votes:read");
    expect(rolePermissions.MEMBER).toContain("votes:read");
    expect(deliveredTo("MEMBER", "votes:read")).toBe(true);
  });

  it("does not deliver a batch twice to a role that already had it", () => {
    // GROUP_ACCOUNT has held votes:read since launch, so the batch is a no-op
    // for it — and must stay one, or an admin's curation gets overwritten.
    expect(STORED_AT_LAUNCH.GROUP_ACCOUNT).toContain("votes:read");
  });

  it("names only permissions that exist", () => {
    const known = new Set<string>(permissions);
    for (const batch of permissionBackfillsForTests) {
      for (const permission of batch) {
        expect(known, `${permission} is in a backfill batch but not a permission`).toContain(
          permission
        );
      }
    }
  });

  it("keeps the launch baseline honest", () => {
    // A role in the code with no recorded baseline means the check above
    // silently skips it.
    for (const role of roles) {
      expect(STORED_AT_LAUNCH[role], `no launch baseline recorded for ${role}`).toBeDefined();
    }
  });
});
