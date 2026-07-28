import bcrypt from "bcryptjs";
import { rolePermissions } from "@intellicash/shared";
import { prisma } from "../src/lib/prisma";
import { normalisePhone } from "../src/lib/phone";

/**
 * What a fresh PRODUCTION database needs to come up — and nothing more.
 *
 * The demo seed (`seed.ts`) creates `admin@intellicash.co.ke` with a password
 * that lives in the source tree. Running that against a live money system
 * would hand anyone who read the repo a platform-admin login, plus a scatter
 * of fake groups and ledgers. So production gets ONLY:
 *
 *   1. the role -> permission templates the auth layer reads (without these no
 *      account has any permissions and everyone is locked out), and
 *   2. one real admin, from environment variables the operator controls.
 *
 * If those variables are absent it refuses to invent an admin — a guessable
 * super-user is worse than an inconvenient one. It seeds the templates and
 * says loudly that an admin must be created before anyone can sign in.
 */
export async function seedProductionBootstrap() {
  // Idempotent: safe if some rows already exist.
  for (const [role, permissions] of Object.entries(rolePermissions)) {
    await prisma.rolePermissionTemplate.upsert({
      where: { role },
      update: { permissionsJson: JSON.stringify(permissions) },
      create: { role, permissionsJson: JSON.stringify(permissions) }
    });
  }
  console.log("Role permission templates ready.");

  const email = process.env.INITIAL_ADMIN_EMAIL?.trim();
  const password = process.env.INITIAL_ADMIN_PASSWORD;
  const name = process.env.INITIAL_ADMIN_NAME?.trim() || "Platform Admin";
  const rawPhone = process.env.INITIAL_ADMIN_PHONE?.trim();

  if (!email || !password) {
    console.warn(
      "\n[bootstrap] No INITIAL_ADMIN_EMAIL / INITIAL_ADMIN_PASSWORD set.\n" +
        "[bootstrap] No admin account was created, so nobody can sign in yet.\n" +
        "[bootstrap] Set both and restart, or create an admin directly in the database.\n"
    );
    return;
  }

  // A weak first admin on a financial system is the thing this whole file
  // exists to prevent — hold the same bar the admin user routes do.
  if (password.length < 12) {
    console.error(
      "[bootstrap] INITIAL_ADMIN_PASSWORD must be at least 12 characters. " +
        "No admin created."
    );
    process.exitCode = 1;
    return;
  }

  const existing = await prisma.user.findFirst({ where: { email } });
  if (existing) {
    console.log(`[bootstrap] Admin ${email} already exists; leaving it alone.`);
    return;
  }

  await prisma.user.create({
    data: {
      name,
      email,
      phone: rawPhone ? normalisePhone(rawPhone) : null,
      passwordHash: await bcrypt.hash(password, 12),
      role: "IWL_ADMIN",
      languagePreference: "ENGLISH",
      status: "ACTIVE"
    }
  });
  console.log(`[bootstrap] Created initial admin ${email}.`);
}
