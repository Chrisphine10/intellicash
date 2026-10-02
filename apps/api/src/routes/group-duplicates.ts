import { Router } from "express";
import { requireAdmin, requireAuth } from "../middleware/auth";
import { ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { groupNameKey, groupNameSimilarity } from "../services/group-login-link";

export const groupDuplicatesRouter = Router();

/** Shares of the shorter name's distinctive words that a pair must have in common. */
const SIMILARITY_THRESHOLD = 0.75;

/**
 * Groups that are probably the same group registered twice — most often an
 * imported register entry and the same group signing itself up on a phone.
 *
 * Advisory only. "Kariguri Wendani Women" and "Kiamuvia Wendani Women" are two
 * groups, so nothing here blocks or merges; it puts the pairs in front of an
 * admin with what each record holds, which is what decides the merge.
 */
groupDuplicatesRouter.get("/group-duplicates", requireAuth("groups:read"), requireAdmin, async (_req, res, next) => {
  try {
    const groups = await prisma.group.findMany({
      where: { isDemo: false },
      select: {
        id: true,
        name: true,
        code: true,
        county: true,
        sourceSystem: true,
        createdAt: true,
        _count: { select: { members: true, meetings: true, ledgerEntries: true, visits: true } }
      },
      orderBy: { createdAt: "asc" }
    });

    const pairs: Array<{
      similarity: number;
      sameKey: boolean;
      groups: typeof groups;
    }> = [];
    for (let i = 0; i < groups.length; i += 1) {
      for (let j = i + 1; j < groups.length; j += 1) {
        const a = groups[i]!;
        const b = groups[j]!;
        const sameKey = groupNameKey(a.name) !== "" && groupNameKey(a.name) === groupNameKey(b.name);
        const similarity = sameKey ? 1 : groupNameSimilarity(a.name, b.name);
        if (similarity >= SIMILARITY_THRESHOLD) pairs.push({ similarity, sameKey, groups: [a, b] });
      }
    }
    pairs.sort((x, y) => y.similarity - x.similarity);
    ok(res, { threshold: SIMILARITY_THRESHOLD, pairs });
  } catch (error) {
    next(error);
  }
});
