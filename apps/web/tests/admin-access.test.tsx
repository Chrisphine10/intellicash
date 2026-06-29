import { describe, expect, it } from "vitest";
import { roles, rolePermissions, type Permission, type Role } from "@intellicash/shared";
import { getNavigationItemsForRole } from "../src/lib/navigation";

const adminOnlyPermissions: Permission[] = [
  "audit:read",
  "intelliaudit:read",
  "intelliaudit:write",
  "intelliaudit:approve",
  "evidence:write",
  "connectors:sync",
  "reports:approve",
  "integrations:read",
  "integrations:write",
  "integrations:test",
  "api-keys:read",
  "api-keys:write",
  "webhooks:write"
];

const adminOnlyNavigation = new Set([
  "/dashboard/audit",
  "/dashboard/intelliaudit",
  "/dashboard/integrations",
  "/dashboard/api-docs",
  "/dashboard/settings"
]);

describe("admin-only management access", () => {
  it("keeps integration, API, and audit permissions reserved for IWL admins", () => {
    for (const role of roles) {
      const permissions = rolePermissions[role];

      if (role === "IWL_ADMIN") {
        expect(permissions).toEqual(expect.arrayContaining(adminOnlyPermissions));
        continue;
      }

      for (const permission of adminOnlyPermissions) {
        expect(permissions, `${role} should not include ${permission}`).not.toContain(permission);
      }
    }
  });

  it("only shows integration, API, and audit management routes to admins", () => {
    for (const role of roles) {
      const hrefs = getNavigationItemsForRole(role).map((item) => item.href);

      for (const href of adminOnlyNavigation) {
        if (role === "IWL_ADMIN") {
          expect(hrefs, `${role} should see ${href}`).toContain(href);
        } else {
          expect(hrefs, `${role} should not see ${href}`).not.toContain(href);
        }
      }
    }
  });
});
