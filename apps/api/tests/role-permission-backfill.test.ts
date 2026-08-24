import { beforeEach, describe, expect, it } from "vitest";
import { rolePermissions } from "@intellicash/shared";
import { prisma } from "../src/lib/prisma";
import {
  __resetRolePermissionBootstrapForTests,
  getRolePermissionMap,
  permissionsForRoleFromStore
} from "../src/services/role-permission-service";

/**
 * The failure this guards against is invisible in development and total in
 * production.
 *
 * `ensureRolePermissionTemplatesOnce` upserts every role with `update: {}`, so
 * an existing RolePermissionTemplate row is never rewritten. A permission added
 * to `rolePermissions` therefore reaches a fresh database (the row is created
 * with current defaults) but never reaches a database seeded before the change
 * — and `permissionsForRoleFromStore` reads the stored row, not the constant.
 *
 * The result: the feature works on every developer machine and 403s on the one
 * deployment that matters. These tests reproduce the production shape — rows
 * written before the permission existed — rather than the fresh-database shape.
 */

/** A template row as it existed before visits shipped. */
async function writePreVisitTemplates() {
  await prisma.rolePermissionTemplate.deleteMany({});
  await prisma.rolePermissionTemplate.createMany({
    data: [
      {
        role: "VILLAGE_AGENT",
        permissionsJson: JSON.stringify([
          "programmes:read",
          "village-agents:read",
          "groups:read",
          "members:read",
          "members:write",
          "meetings:read",
          "ledger:read",
          "votes:read",
          "store:read",
          "store:write",
          "analytics:read"
        ])
      },
      {
        role: "GROUP_ACCOUNT",
        permissionsJson: JSON.stringify([
          "programmes:read",
          "groups:read",
          "members:read",
          "members:write",
          "meetings:read",
          "meetings:write",
          "meeting-keys:write",
          "ledger:read",
          "ledger:write",
          "store:read",
          "store:write",
          "votes:read",
          "votes:write",
          "analytics:read"
        ])
      }
    ]
  });
  __resetRolePermissionBootstrapForTests();
}

describe("role permission backfill", () => {
  beforeEach(async () => {
    await writePreVisitTemplates();
  });

  it("gives an agent visits:write on a database seeded before visits existed", async () => {
    const granted = await permissionsForRoleFromStore("VILLAGE_AGENT");
    expect(granted).toContain("visits:write");
    expect(granted).toContain("visits:read");
  });

  it("keeps the agent away from rewriting a visit they submitted", async () => {
    const granted = await permissionsForRoleFromStore("VILLAGE_AGENT");
    expect(granted).not.toContain("visits:amend");
  });

  it("lets a group read visits made to it but not conduct them", async () => {
    const granted = await permissionsForRoleFromStore("GROUP_ACCOUNT");
    expect(granted).toContain("visits:read");
    expect(granted).not.toContain("visits:write");
  });

  it("leaves permissions the role never had alone", async () => {
    // Backfill adds only what `rolePermissions` says the role should hold. It
    // is not an amnesty that hands every role every new permission.
    const granted = await permissionsForRoleFromStore("VILLAGE_AGENT");
    expect(granted).not.toContain("ledger:write");
    expect(granted).not.toContain("meetings:write");
  });

  it("does not reinstate a permission an admin deliberately removed", async () => {
    // A batch is delivered once. Once any row mentions the batch, a later boot
    // must not quietly undo an administrator's decision to revoke it.
    await prisma.rolePermissionTemplate.update({
      where: { role: "VILLAGE_AGENT" },
      data: {
        permissionsJson: JSON.stringify([
          "groups:read",
          // visits:read present => the batch has been delivered...
          "visits:read"
          // ...and visits:write is absent because it was revoked on purpose.
        ])
      }
    });
    __resetRolePermissionBootstrapForTests();

    const granted = await permissionsForRoleFromStore("VILLAGE_AGENT");
    expect(granted).toContain("visits:read");
    expect(granted).not.toContain("visits:write");
  });

  it("still delivers the older api-keys batch it was written for", async () => {
    // The api-keys batch is the precedent this mechanism generalises. It must
    // keep working, or the bug it fixed comes back.
    const map = await getRolePermissionMap();
    expect(map.IWL_ADMIN).toContain("api-keys:write");
  });

  it("does not grant visits to roles that should not see them", async () => {
    // Visit records carry free text about named individuals and photographs of
    // their premises — materially wider than the ledger totals these roles read.
    const map = await getRolePermissionMap();
    expect(map.READ_ONLY).not.toContain("visits:read");
    expect(map.LENDER).not.toContain("visits:read");
    expect(map.MEMBER).not.toContain("visits:read");
  });

  it("matches the shared defaults for a fresh database", async () => {
    await prisma.rolePermissionTemplate.deleteMany({});
    __resetRolePermissionBootstrapForTests();

    const granted = await permissionsForRoleFromStore("VILLAGE_AGENT");
    expect([...granted].sort()).toEqual([...rolePermissions.VILLAGE_AGENT].sort());
  });
});

/**
 * A member casting their own ballot.
 *
 * `votes:write` sat in MEMBER's entry in `rolePermissions` while being absent
 * from every backfill batch, which is the exact shape this file exists to
 * catch: correct in the constant, missing from the stored row, so it worked in
 * development and 403'd in production.
 */
describe("members can vote on a database seeded before votes:write", () => {
  beforeEach(async () => {
    __resetRolePermissionBootstrapForTests();
    await prisma.rolePermissionTemplate.deleteMany({});
    await prisma.rolePermissionTemplate.create({
      data: {
        role: "MEMBER",
        // The production shape: read but not write.
        permissionsJson: JSON.stringify([
          "programmes:read",
          "groups:read",
          "members:read",
          "meetings:read",
          "ledger:read",
          "votes:read",
          "store:read",
          "analytics:read"
        ])
      }
    });
  });

  it("backfills votes:write so a member can cast a ballot", async () => {
    const granted = await permissionsForRoleFromStore("MEMBER");
    expect(granted).toContain("votes:write");
    expect(granted).toContain("votes:read");
  });

  it("leaves a role that already had it alone", async () => {
    // GROUP_ACCOUNT has held votes:write since the table shipped. Its grants
    // must not decide MEMBER's, which is the bug this batch exposed.
    const granted = await permissionsForRoleFromStore("GROUP_ACCOUNT");
    expect(granted).toContain("votes:write");
  });

  it("does not hand a member anything else while doing it", async () => {
    // Backfill adds what `rolePermissions` says the role should hold, not an
    // amnesty. A member must not gain the ability to move money.
    const granted = await permissionsForRoleFromStore("MEMBER");
    expect(granted).not.toContain("ledger:write");
    expect(granted).not.toContain("groups:write");
  });
});
