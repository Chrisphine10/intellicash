/**
 * Whether a group's members may sign in to see their own savings.
 *
 * The group decides, in Edit group set-up on its phone
 * (`GroupPolicy.memberAccountsEnabled`). A group that has never decided keeps
 * what it has: ON if any of its members already signs in, OFF otherwise. So
 * switching this feature on for the platform locks no one out who could sign
 * in yesterday, and a new group starts closed until it opts in.
 */

import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";

export async function memberAccountsEnabledFor(groupId: string): Promise<boolean> {
  return (await groupsWithMemberAccounts([groupId])).has(groupId);
}

/** The subset of [groupIds] whose members may sign in. */
export async function groupsWithMemberAccounts(groupIds: string[]): Promise<Set<string>> {
  const ids = [...new Set(groupIds)];
  if (ids.length === 0) return new Set();

  const policies = await prisma.groupPolicy.findMany({
    where: { groupId: { in: ids } },
    select: { groupId: true, memberAccountsEnabled: true }
  });
  const decided = new Map(policies.map((policy) => [policy.groupId, policy.memberAccountsEnabled]));
  const on = new Set(ids.filter((id) => decided.get(id) === true));

  const undecided = ids.filter((id) => decided.get(id) === null || decided.get(id) === undefined);
  if (undecided.length > 0) {
    const withLogins = await prisma.userMembership.findMany({
      where: { groupId: { in: undecided }, user: { role: "MEMBER" } },
      select: { groupId: true },
      distinct: ["groupId"]
    });
    for (const row of withLogins) on.add(row.groupId);
    const pointed = await prisma.user.findMany({
      where: { role: "MEMBER", groupId: { in: undecided } },
      select: { groupId: true },
      distinct: ["groupId"]
    });
    for (const row of pointed) if (row.groupId) on.add(row.groupId);
  }
  return on;
}

/**
 * The member records a MEMBER login may open, one per group, limited to the
 * groups that allow member sign-ins. Oldest link first.
 */
export async function membershipsFor(userId: string): Promise<Array<{ memberId: string; groupId: string }>> {
  const [links, user] = await Promise.all([
    prisma.userMembership.findMany({
      where: { userId },
      orderBy: { createdAt: "asc" },
      select: { memberId: true, groupId: true }
    }),
    prisma.user.findUnique({ where: { id: userId }, select: { memberId: true } })
  ]);
  const all = [...links];
  // A login bound straight to a member row (older accounts, the seed) counts
  // too; its group is the member's own, not whatever the login points at.
  if (user?.memberId && !all.some((link) => link.memberId === user.memberId)) {
    const member = await prisma.member.findUnique({ where: { id: user.memberId }, select: { groupId: true } });
    if (member) all.push({ memberId: user.memberId, groupId: member.groupId });
  }
  return all;
}

export async function visibleMembershipsFor(userId: string): Promise<Array<{ memberId: string; groupId: string }>> {
  const all = await membershipsFor(userId);
  const open = await groupsWithMemberAccounts(all.map((link) => link.groupId));
  return all.filter((link) => open.has(link.groupId));
}

/**
 * The group officials are giving a member a sign-in. A group that switched
 * member sign-ins OFF is refused; one that never decided is taken to have
 * decided now (creating a member's login IS opting in — which is also how a
 * phone released before the switch existed keeps working).
 */
export async function assertMayCreateMemberLogin(groupId: string, actorUserId: string | null) {
  const policy = await prisma.groupPolicy.findUnique({ where: { groupId }, select: { memberAccountsEnabled: true } });
  if (policy?.memberAccountsEnabled === false) {
    throw new ApiHttpError(
      403,
      "MEMBER_ACCOUNTS_OFF",
      "Member sign-ins are switched off for this group. Turn them on in Edit group set-up first."
    );
  }
  if (policy?.memberAccountsEnabled !== true) {
    await prisma.groupPolicy.upsert({
      where: { groupId },
      create: { groupId, memberAccountsEnabled: true, updatedByUserId: actorUserId },
      update: { memberAccountsEnabled: true }
    });
  }
}

/**
 * Refuses a MEMBER login when every group it belongs to has switched member
 * sign-ins off. Other roles, and members with at least one open group, pass.
 */
export async function assertMemberMaySignIn(user: { id: string; role: string }) {
  if (user.role !== "MEMBER") return;
  const all = await membershipsFor(user.id);
  // Someone who has not joined a group yet signs in to find one.
  if (all.length === 0) return;
  const open = await groupsWithMemberAccounts(all.map((link) => link.groupId));
  if (all.some((link) => open.has(link.groupId))) return;
  throw new ApiHttpError(
    403,
    "MEMBER_ACCOUNTS_OFF",
    "Your group has switched member sign-ins off. Ask your group's officials if you need to see your savings."
  );
}
