import { permissions, rolePermissions, roles, type Permission, type Role } from "@intellicash/shared";
import { isRole } from "../domain/authorization";
import { prisma } from "../lib/prisma";

const permissionSet = new Set<string>(permissions);
const protectedAdminPermissions: Permission[] = ["users:read", "users:write"];

/**
 * Permissions introduced after this table first shipped, oldest batch first.
 *
 * `ensureRolePermissionTemplatesOnce` upserts with `update: {}`, so a database
 * that already holds RolePermissionTemplate rows never sees a newly added
 * default: the row was written before the permission existed, and
 * `permissionsForRoleFromStore` reads that row rather than `rolePermissions`.
 * The effect is that a new permission works perfectly on a fresh database and
 * 403s every existing deployment — which is exactly what happened to
 * `api-keys:*`, and is why that batch is here.
 *
 * Delivery is decided PER ROLE. A batch used to be skipped entirely if any
 * stored row mentioned any of its permissions, which sounds conservative and is
 * wrong: it lets one role's grants decide another role's. `votes:write` belongs
 * to GROUP_ACCOUNT and MEMBER; group accounts have held it since the table
 * shipped, so a batch containing it was always "already delivered" and members
 * could never receive it. They could open a poll and were refused when they
 * voted.
 *
 * The tradeoff, stated plainly: a permission an admin deliberately removed from
 * a role that should hold it by default will come back once, the first time an
 * undelivered batch containing it is applied to that role. The previous
 * behaviour avoided that only by accident — via some unrelated role happening to
 * hold the same permission — and paid for it by not delivering at all.
 */
const permissionBackfills: readonly (readonly Permission[])[] = [
  ["api-keys:read", "api-keys:write"],
  // `group-pin:write` was in this batch until the visit PIN was removed. It
  // stays out rather than being deleted from history: the batch is keyed on
  // whether any of its permissions is already present, so shortening it does
  // not re-deliver it to a deployment that already took it.
  ["visits:read", "visits:write", "visits:amend"],
  ["assessment-templates:write"],
  ["documents:read", "documents:write"],
  /*
   * A member casting their own ballot. `votes:write` has been in MEMBER's
   * entry in `rolePermissions` for some time, but it was never in a backfill
   * batch — so every deployment whose template row predates it kept a MEMBER
   * list without it, and `permissionsForRoleFromStore` reads that row. Members
   * could open a poll and were refused when they voted.
   */
  // ONLY the new permission. Including `votes:read` — which MEMBER already
  // held — would make the batch look delivered for that very role and skip it.
  // A batch names what is being introduced, not the area it belongs to.
  ["votes:write"]
];
const adminOnlyPermissions = new Set<Permission>([
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
]);
let rolePermissionTemplateBootstrap: Promise<void> | null = null;

function readPermissionValues(value: string | null | undefined) {
  if (!value) return null;

  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? parsed.filter((permission): permission is Permission => permissionSet.has(permission)) : null;
  } catch {
    return null;
  }
}

function parsePermissions(value: string | null | undefined, role: Role): Permission[] {
  if (!value) return permissionsForRoleWithAdminReserve(role, rolePermissions[role]);

  const parsed = readPermissionValues(value);
  return permissionsForRoleWithAdminReserve(role, parsed ?? rolePermissions[role]);
}

export function normalizePermissionList(values: Permission[]) {
  return Array.from(new Set(values.filter((permission) => permissionSet.has(permission))));
}

function permissionsForRoleWithAdminReserve(role: Role, values: Permission[]) {
  const normalized = normalizePermissionList(values);
  if (role === "IWL_ADMIN") return normalized;
  return normalized.filter((permission) => !adminOnlyPermissions.has(permission));
}

export function validateRolePermissionUpdate(role: Role, values: Permission[]) {
  const normalized = normalizePermissionList(values);
  const restricted = role === "IWL_ADMIN" ? [] : normalized.filter((permission) => adminOnlyPermissions.has(permission));

  if (restricted.length > 0) {
    throw new Error(`Only IWL admin can hold ${restricted.join(", ")}.`);
  }

  if (role === "IWL_ADMIN") {
    const missing = protectedAdminPermissions.filter((permission) => !normalized.includes(permission));
    if (missing.length > 0) {
      throw new Error(`IWL admin must keep ${missing.join(", ")} so access control remains recoverable.`);
    }
  }

  return normalized;
}

export async function ensureRolePermissionTemplates() {
  rolePermissionTemplateBootstrap ??= ensureRolePermissionTemplatesOnce();
  await rolePermissionTemplateBootstrap;
}

/**
 * Test-only: clears the once-per-process memo so a test can rewrite the stored
 * templates and observe bootstrap again. Without this the backfill can only
 * ever be exercised in whichever test happens to run first.
 */
export function __resetRolePermissionBootstrapForTests() {
  rolePermissionTemplateBootstrap = null;
}

async function ensureRolePermissionTemplatesOnce() {
  // Snapshot BEFORE the upsert. Rows created by the upsert below carry current
  // defaults, so including them would make every batch look already-delivered.
  const rowsBeforeBootstrap = await prisma.rolePermissionTemplate.findMany();

  await Promise.all(
    roles.map((role) =>
      prisma.rolePermissionTemplate.upsert({
        where: { role },
        create: {
          role,
          permissionsJson: JSON.stringify(rolePermissions[role])
        },
        update: {}
      })
    )
  );

  await pruneReservedPermissionsFromNonAdminTemplates();

  // A database with no rows was just seeded with current defaults; there is
  // nothing older to bring forward.
  if (rowsBeforeBootstrap.length === 0) return;

  for (const batch of permissionBackfills) {
    await deliverPermissionBatch(rowsBeforeBootstrap, batch);
  }
}

/**
 * Adds a batch of newly introduced permissions to the stored templates, giving
 * each role only what `rolePermissions` says it should have.
 */
async function deliverPermissionBatch(
  rowsBeforeBootstrap: { role: string; permissionsJson: string }[],
  batch: readonly Permission[]
) {
  const batchSet = new Set<Permission>(batch);
  // What each role held BEFORE bootstrap. A row created by the upsert carries
  // current defaults, so judging delivery from it would mark every batch done.
  const storedBefore = new Map<string, Permission[]>(
    rowsBeforeBootstrap.map((row) => [row.role, (readPermissionValues(row.permissionsJson) ?? []) as Permission[]])
  );

  const rows = await prisma.rolePermissionTemplate.findMany();
  await Promise.all(
    rows.map((row) => {
      if (!isRole(row.role)) return null;
      const defaultsToAdd = rolePermissions[row.role].filter((permission) => batchSet.has(permission));
      if (defaultsToAdd.length === 0) return null;

      // Delivered for THIS role already? Then leave it alone — including any
      // subset an admin has since curated.
      const before = storedBefore.get(row.role);
      if (before && before.some((permission) => batchSet.has(permission))) return null;

      const stored = readPermissionValues(row.permissionsJson) ?? [];
      const merged = permissionsForRoleWithAdminReserve(row.role, [...stored, ...defaultsToAdd]);
      if (JSON.stringify(stored) === JSON.stringify(merged)) return null;

      return prisma.rolePermissionTemplate.update({
        where: { role: row.role },
        data: {
          permissionsJson: JSON.stringify(merged)
        }
      });
    })
  );
}

async function pruneReservedPermissionsFromNonAdminTemplates() {
  const rows = await prisma.rolePermissionTemplate.findMany();
  await Promise.all(
    rows.map((row) => {
      if (!isRole(row.role)) return null;
      const stored = readPermissionValues(row.permissionsJson);
      if (!stored) return null;
      const sanitized = permissionsForRoleWithAdminReserve(row.role, stored);
      if (JSON.stringify(stored) === JSON.stringify(sanitized)) return null;

      return prisma.rolePermissionTemplate.update({
        where: { role: row.role },
        data: {
          permissionsJson: JSON.stringify(sanitized)
        }
      });
    })
  );
}

export async function getRolePermissionMap(): Promise<Record<Role, Permission[]>> {
  await ensureRolePermissionTemplates();
  const rows = await prisma.rolePermissionTemplate.findMany();
  const rowMap = new Map(rows.map((row) => [row.role, row.permissionsJson]));

  const result = Object.fromEntries(
    roles.map((role) => [role, parsePermissions(rowMap.get(role), role)])
  ) as Record<Role, Permission[]>;

  return result;
}

export async function permissionsForRoleFromStore(role: string): Promise<Permission[]> {
  if (!isRole(role)) return [];
  await ensureRolePermissionTemplates();

  const row = await prisma.rolePermissionTemplate.findUnique({
    where: { role }
  });

  if (!row) {
    await prisma.rolePermissionTemplate.upsert({
      where: { role },
      create: {
        role,
        permissionsJson: JSON.stringify(rolePermissions[role])
      },
      update: {}
    });
    return rolePermissions[role];
  }

  return parsePermissions(row.permissionsJson, role);
}

export async function hasStoredPermission(role: string, permission: Permission) {
  const rolePermissionList = await permissionsForRoleFromStore(role);
  return rolePermissionList.includes(permission);
}

export async function updateRolePermissionTemplate(role: Role, values: Permission[]) {
  const normalized = validateRolePermissionUpdate(role, values);

  return prisma.rolePermissionTemplate.upsert({
    where: { role },
    create: {
      role,
      permissionsJson: JSON.stringify(normalized)
    },
    update: {
      permissionsJson: JSON.stringify(normalized)
    }
  });
}
