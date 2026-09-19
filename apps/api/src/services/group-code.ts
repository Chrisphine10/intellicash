import type { Prisma } from "@prisma/client";

/**
 * Group codes for groups created outside an import: `IWL-<county>-<random>`.
 *
 * Shared by the partner sign-up approval and mobile self sign-up, so a group
 * made either way is addressed the same way. Random rather than sequential:
 * a sequence would collide with imported codes and makes codes guessable.
 */
export function countyCode(county?: string | null) {
  const normalized = (county ?? "REG").replace(/[^a-z0-9]/gi, "").toUpperCase();
  return (normalized || "REG").slice(0, 3).padEnd(3, "X");
}

export async function generateGroupCode(tx: Prisma.TransactionClient, county?: string | null) {
  const prefix = `IWL-${countyCode(county)}`;

  for (let attempt = 0; attempt < 8; attempt += 1) {
    const suffix = Math.random().toString(36).slice(2, 8).toUpperCase();
    const code = `${prefix}-${suffix}`;
    const existing = await tx.group.findUnique({ where: { code }, select: { id: true } });
    if (!existing) return code;
  }

  return `${prefix}-${Date.now().toString(36).toUpperCase()}`;
}
