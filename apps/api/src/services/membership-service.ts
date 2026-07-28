import type { Prisma } from "@prisma/client";
import { prisma } from "../lib/prisma";

/**
 * Multi-group membership.
 *
 * A person can belong to several VSLAs at once, which is normal here: someone
 * saves with a women's group, a church group and a market-traders group in the
 * same season. `UserMembership` records all of them.
 *
 * `User.memberId` / `User.groupId` still point at whichever membership is
 * currently in view, and every permission check in account-scope.ts resolves
 * against that pair. That is deliberate: a member looking at one group's
 * passbook should be scoped to exactly that group, and it keeps the switch to
 * multi-group from rewriting security-critical scoping code.
 */

export type MembershipSummary = {
  membershipId: string;
  memberId: string;
  groupId: string;
  groupName: string;
  groupCode: string | null;
  memberName: string;
  isActive: boolean;
  joinedAt: string;
};

/**
 * Brings an account's "membership currently in view" back into line with the
 * groups it actually belongs to.
 *
 * Two cases, both of which otherwise strand a real member:
 *  - Accounts created before multi-group support hold their one membership
 *    only on `User.memberId`; promote it to a `UserMembership` row.
 *  - The group being viewed removed them, so `User.memberId` cascaded to null
 *    while their other memberships survived; adopt one of those.
 */
export async function reconcileMembership(userId: string) {
  const user = await prisma.user.findUnique({
    where: { id: userId },
    select: { role: true, memberId: true, groupId: true, member: { select: { id: true, groupId: true } } }
  });
  if (!user) return;
  // Only member accounts have a membership "in view". Repointing any other
  // role would move `User.groupId`, which group-side scoping resolves against.
  if (user.role !== "MEMBER") return;

  // The group they were viewing took them off its roster: the Member row is
  // gone and `User.memberId` cascaded to null, but memberships in OTHER groups
  // are untouched. Without adopting one of those the person is told they
  // belong to no group at all — and cannot rejoin, because they are already a
  // member. Move them to a group they really are in.
  if (!user.memberId) {
    const fallback = await prisma.userMembership.findFirst({
      where: { userId },
      orderBy: { createdAt: "asc" },
      select: { memberId: true, groupId: true }
    });
    if (fallback) {
      await prisma.user.update({
        where: { id: userId },
        data: { memberId: fallback.memberId, groupId: fallback.groupId }
      });
      return;
    }
    // No memberships left at all — drop the stale group pointer rather than
    // leave it aimed at a group they are no longer part of.
    if (user.groupId) {
      await prisma.user.update({ where: { id: userId }, data: { groupId: null } });
    }
    return;
  }

  if (!user.member) return;
  const existing = await prisma.userMembership.findUnique({
    where: { memberId: user.member.id },
    select: { userId: true }
  });
  // Someone else already holds this roster entry — promoting would be wrong,
  // and silently no-opping would leave this account showing no groups while
  // it still reads that member's ledger. Neither is safe to paper over.
  if (existing && existing.userId !== userId) {
    throw new MemberAlreadyLinkedError(user.member.id);
  }
  if (existing) return;
  await prisma.userMembership.create({
    data: { userId, memberId: user.member.id, groupId: user.member.groupId }
  });
}

export async function listMemberships(userId: string): Promise<MembershipSummary[]> {
  await reconcileMembership(userId);
  const [user, links] = await Promise.all([
    prisma.user.findUnique({ where: { id: userId }, select: { memberId: true } }),
    prisma.userMembership.findMany({
      where: { userId },
      orderBy: { createdAt: "asc" },
      select: {
        id: true,
        memberId: true,
        groupId: true,
        createdAt: true,
        member: { select: { fullName: true } },
        group: { select: { name: true, code: true } }
      }
    })
  ]);
  return links.map((link) => ({
    membershipId: link.id,
    memberId: link.memberId,
    groupId: link.groupId,
    groupName: link.group.name,
    groupCode: link.group.code,
    memberName: link.member.fullName,
    isActive: link.memberId === user?.memberId,
    joinedAt: link.createdAt.toISOString()
  }));
}

/**
 * Points the account at one of its memberships. Returns null when the group
 * isn't one this user actually belongs to — the caller must treat that as a
 * refusal, since this is what stops someone switching into a group's books by
 * guessing an id.
 */
export async function setActiveMembership(
  userId: string,
  groupId: string
): Promise<MembershipSummary | null> {
  // Switching the membership in view belongs to MEMBER accounts only. It
  // rewrites `User.groupId`, which is exactly what GROUP_ACCOUNT scoping
  // trusts — so for any other role this would hand out that group's books.
  // A leftover membership row (from an account whose role was changed) is
  // enough to reach this, so the role is checked here rather than only at the
  // route.
  const account = await prisma.user.findUnique({
    where: { id: userId },
    select: { role: true }
  });
  if (account?.role !== "MEMBER") return null;

  await reconcileMembership(userId);
  const link = await prisma.userMembership.findUnique({
    where: { userId_groupId: { userId, groupId } },
    select: { memberId: true }
  });
  if (!link) return null;
  await prisma.user.update({
    where: { id: userId },
    data: { memberId: link.memberId, groupId }
  });
  const all = await listMemberships(userId);
  return all.find((m) => m.groupId === groupId) ?? null;
}

/**
 * Records that a user belongs to a group, and makes it the active membership
 * when they had none. Safe to call twice for the same pair.
 */
/** Raised when a roster entry is already claimed by a different account. */
export class MemberAlreadyLinkedError extends Error {
  constructor(public readonly memberId: string) {
    super("That member is already linked to another account.");
    this.name = "MemberAlreadyLinkedError";
  }
}

/**
 * Records that a user belongs to a group, and makes it the active membership
 * when they had none.
 *
 * Pass `client` to join a transaction the caller already opened — approving a
 * join request has to link the member and mark the request decided as one
 * unit, and Prisma will not nest transactions.
 */
export async function linkMembership(
  userId: string,
  memberId: string,
  groupId: string,
  client?: Prisma.TransactionClient
) {
  const run = async (tx: Prisma.TransactionClient) => {
    // A Member row is one person in one group, so it belongs to exactly one
    // account. Silently moving it — which an upsert alone would do — detaches
    // whoever held it from their own savings, so refuse and let the caller
    // say so.
    const claimed = await tx.userMembership.findUnique({
      where: { memberId },
      select: { userId: true }
    });
    if (claimed && claimed.userId !== userId) {
      throw new MemberAlreadyLinkedError(memberId);
    }

    // A link row is not the only way a member gets claimed: the admin user
    // routes and the seed bind `User.memberId` directly without one. Missing
    // that would either blow up on the unique constraint or, for someone who
    // already has another group, quietly strand the original account.
    const pointedAt = await tx.user.findFirst({
      where: { memberId, NOT: { id: userId } },
      select: { id: true }
    });
    if (pointedAt) {
      throw new MemberAlreadyLinkedError(memberId);
    }

    await tx.userMembership.upsert({
      where: { memberId },
      update: { userId, groupId },
      create: { userId, memberId, groupId }
    });

    const user = await tx.user.findUnique({
      where: { id: userId },
      select: { memberId: true }
    });
    // First group they've joined — put them in it rather than leaving the app
    // with nothing selected.
    if (!user?.memberId) {
      await tx.user.update({ where: { id: userId }, data: { memberId, groupId } });
    }
  };

  // The link and the active pointer must not be able to disagree:
  // User.memberId is unique, so without one transaction a failure on the
  // second write would leave the first committed.
  if (client) return run(client);
  await prisma.$transaction(run);
}
