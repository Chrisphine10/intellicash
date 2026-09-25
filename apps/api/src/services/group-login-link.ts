/**
 * Every group login opens a group. No exceptions.
 *
 * A GROUP_ACCOUNT with no group is a front door to nothing: it signs in and
 * sees an empty app. This is the one rule that closes the gap for any that
 * exist or appear, applied when such a login signs in.
 */

import { fundTypes } from "@intellicash/shared";

import { prisma } from "../lib/prisma";
import { phoneTail, samePhone } from "../lib/phone";
import { appendAuditEvent } from "./audit-service";
import { generateGroupCode } from "./group-code";

/**
 * Every group login opens a group. No exceptions.
 *
 * A GROUP_ACCOUNT with no group is a front door to nothing: it signs in and
 * sees an empty app. Field sign-ups made nineteen of them before sign-up began
 * creating the group itself, and staff had to attach them by hand. This is the
 * one rule that closes the gap for any that exist or appear, applied when such
 * a login signs in and by the one-time repair script.
 *
 * In order, the first that fits:
 *
 * 1. **The champion's number.** Exactly one group records this login's phone
 *    as its contact — the same evidence the champion-linking screen uses.
 * 2. **The name.** Exactly one group has the same name once spelling noise is
 *    removed ("S H G", "SHG" and "Self Help Group" read alike; "(II)" does
 *    not, because it is what tells two real groups apart).
 * 3. **A new group.** Nothing matches, or more than one thing does. Guessing
 *    between two candidates would hand one group's books to another group's
 *    champion, so an ambiguous login gets its own group instead, marked with
 *    the candidates so staff can merge it.
 */

export type GroupLinkOutcome =
  | "NOT_A_GROUP_LOGIN"
  | "ALREADY_LINKED"
  /** Closed accounts cannot sign in; giving one a group would only add clutter. */
  | "CLOSED_SKIPPED"
  | "LINKED_BY_PHONE"
  | "LINKED_BY_NAME"
  | "GROUP_CREATED";

export interface GroupLinkResult {
  outcome: GroupLinkOutcome;
  groupId: string | null;
  groupName?: string;
  /** Names of groups that looked similar but could not be chosen between. */
  possibleDuplicates?: string[];
}

export const AUTO_GROUP_SOURCE = "AUTO_FOR_UNLINKED_LOGIN";

/** Group names compared without the noise that varies between who typed them. */
export function normaliseGroupName(name: string) {
  return name
    .toLowerCase()
    .replace(/self[\s-]*help[\s-]*group/g, "shg")
    .replace(/\bs\.?\s*h\.?\s*g\.?\b/g, "shg")
    .replace(/[^a-z0-9()]+/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

export async function ensureGroupForLogin(
  userId: string,
  options: { apply?: boolean; actorUserId?: string | null } = {}
): Promise<GroupLinkResult> {
  const apply = options.apply ?? true;
  const user = await prisma.user.findUnique({
    where: { id: userId },
    select: { id: true, name: true, phone: true, role: true, groupId: true, status: true }
  });
  if (!user || user.role !== "GROUP_ACCOUNT") return { outcome: "NOT_A_GROUP_LOGIN", groupId: null };
  if (user.groupId) return { outcome: "ALREADY_LINKED", groupId: user.groupId };
  if (user.status === "CLOSED") return { outcome: "CLOSED_SKIPPED", groupId: null };

  // 1. The champion's number.
  let byPhone: { id: string; name: string }[] = [];
  const tail = phoneTail(user.phone);
  if (tail.length >= 9) {
    byPhone = (
      await prisma.group.findMany({
        where: { contactPhone: { contains: tail }, isDemo: false },
        select: { id: true, name: true, contactPhone: true }
      })
    ).filter((group) => samePhone(group.contactPhone, user.phone));
  }
  if (byPhone.length === 1) {
    return link(user.id, byPhone[0]!, "LINKED_BY_PHONE", apply, options.actorUserId);
  }

  // 2. The name.
  const wanted = normaliseGroupName(user.name);
  const byName = (
    await prisma.group.findMany({ where: { isDemo: false }, select: { id: true, name: true } })
  ).filter((group) => normaliseGroupName(group.name) === wanted);
  // A name that only differs by "(II)" is a different group, and its existence
  // makes a bare-name match ambiguous rather than safe.
  const nearTwins = (
    await prisma.group.findMany({ where: { isDemo: false }, select: { name: true } })
  )
    .map((group) => group.name)
    .filter((name) => {
      const other = normaliseGroupName(name);
      return other !== wanted && other.replace(/\s*\([^)]*\)\s*/g, " ").trim() === wanted;
    });

  if (byPhone.length === 0 && byName.length === 1 && nearTwins.length === 0) {
    return link(user.id, byName[0]!, "LINKED_BY_NAME", apply, options.actorUserId);
  }

  // 3. A group of its own.
  const possibleDuplicates = [...byPhone.map((g) => g.name), ...byName.map((g) => g.name), ...nearTwins];
  if (!apply) {
    return { outcome: "GROUP_CREATED", groupId: null, groupName: user.name, possibleDuplicates };
  }

  const group = await prisma.$transaction(async (tx) => {
    const created = await tx.group.create({
      data: {
        name: user.name,
        code: await generateGroupCode(tx, null),
        phase: "MOBILISATION",
        county: "Not set",
        contactPhone: user.phone,
        sourceSystem: AUTO_GROUP_SOURCE,
        onboardingFeedback: possibleDuplicates.length
          ? `Created automatically for an unlinked group login. Possible duplicate of: ${[...new Set(possibleDuplicates)].join(", ")} — review and merge if so.`
          : "Created automatically for an unlinked group login. Set its county, programme and CBT.",
        fundAccounts: { create: fundTypes.map((type) => ({ type })) }
      },
      select: { id: true, name: true }
    });
    await tx.user.update({ where: { id: user.id }, data: { groupId: created.id } });
    return created;
  });

  await appendAuditEvent({
    actorUserId: options.actorUserId ?? null,
    entityType: "USER",
    entityId: user.id,
    type: "GROUP_LOGIN_AUTO_LINKED",
    payload: { outcome: "GROUP_CREATED", groupId: group.id, possibleDuplicates }
  });

  return { outcome: "GROUP_CREATED", groupId: group.id, groupName: group.name, possibleDuplicates };
}

async function link(
  userId: string,
  group: { id: string; name: string },
  outcome: "LINKED_BY_PHONE" | "LINKED_BY_NAME",
  apply: boolean,
  actorUserId?: string | null
): Promise<GroupLinkResult> {
  if (apply) {
    await prisma.user.update({ where: { id: userId }, data: { groupId: group.id } });
    await appendAuditEvent({
      actorUserId: actorUserId ?? null,
      entityType: "USER",
      entityId: userId,
      type: "GROUP_LOGIN_AUTO_LINKED",
      payload: { outcome, groupId: group.id }
    });
  }
  return { outcome, groupId: group.id, groupName: group.name };
}
