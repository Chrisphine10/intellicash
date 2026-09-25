/**
 * Connects a group's digital champion to the group's EXISTING account.
 *
 * Why this exists: groups were onboarded centrally, most without a phone on
 * record. In the field, the champion's number matched no account, so signing
 * up made a brand-new group login attached to no group at all — a second, empty
 * front door while the real record sat unopened.
 */

import bcrypt from "bcryptjs";
import { randomBytes } from "node:crypto";

import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";
import { normalisePhone, phoneTail, samePhone } from "../lib/phone";
import { isSendableSmsPhone } from "./sms-service";

/**
 * Connects a group's digital champion to the group's EXISTING account.
 *
 * Why this exists: groups were onboarded centrally, most without a phone on
 * record. In the field, the champion's number matched no account, so signing
 * up made a brand-new group login attached to no group at all — a second, empty
 * front door while the real record sat unopened. Nineteen of those were on
 * production before anyone noticed.
 *
 * The outcomes, decided by who already holds the number:
 *
 * - **Nobody** → the number goes onto the group's own login. The champion signs
 *   in with a texted code; no password needed.
 * - **An orphan group login** (a group account with no group — exactly what the
 *   field sign-ups produced) → that login is attached to this group. The
 *   champion keeps the password they already know, and it now opens the real
 *   book. Nothing is deleted.
 * - **This group's login already** → nothing to do.
 * - **Anyone else** (a member, an agent, another group) → refused. Moving the
 *   number would lock that person out, and a sign-in code sent to it would
 *   open somebody else's account.
 */

export type ChampionLinkOutcome =
  | "PHONE_ATTACHED"
  | "LOGIN_CREATED"
  | "EXISTING_LOGIN_LINKED"
  | "ALREADY_LINKED";

export interface LinkChampionInput {
  groupId: string;
  championName?: string | null;
  phone: string;
}

export interface LinkChampionResult {
  outcome: ChampionLinkOutcome;
  groupId: string;
  groupName: string;
  userId: string;
  phone: string;
  /** Set when an orphan login was attached, for the audit trail. */
  linkedOrphanName?: string;
}

async function findUserOnPhone(phone: string) {
  const candidates = await prisma.user.findMany({
    where: { phone: { contains: phoneTail(phone) } },
    select: { id: true, name: true, phone: true, role: true, groupId: true, memberId: true, villageAgentId: true }
  });
  return candidates.find((candidate) => samePhone(candidate.phone, phone)) ?? null;
}

export async function linkGroupChampion(input: LinkChampionInput): Promise<LinkChampionResult> {
  const phone = normalisePhone(input.phone);
  // A code is texted to this number, so it has to be one that can receive one.
  if (!phone || !isSendableSmsPhone(phone)) {
    throw new ApiHttpError(400, "PHONE_INVALID", "Enter a Kenyan mobile number, like 0712 345 678.");
  }

  const group = await prisma.group.findUnique({
    where: { id: input.groupId },
    select: { id: true, name: true, code: true, contactPersonName: true }
  });
  if (!group) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");

  const holder = await findUserOnPhone(phone);
  const championName = input.championName?.trim() || group.contactPersonName || null;

  const result = await prisma.$transaction(async (tx) => {
    let outcome: ChampionLinkOutcome;
    let userId: string;
    let linkedOrphanName: string | undefined;

    if (holder) {
      if (holder.role === "GROUP_ACCOUNT" && holder.groupId === group.id) {
        outcome = "ALREADY_LINKED";
        userId = holder.id;
      } else if (holder.role === "GROUP_ACCOUNT" && !holder.groupId && !holder.memberId && !holder.villageAgentId) {
        // The field sign-up case. Attach, never delete: the champion's password
        // keeps working and now opens the real group.
        await tx.user.update({ where: { id: holder.id }, data: { groupId: group.id } });
        outcome = "EXISTING_LOGIN_LINKED";
        userId = holder.id;
        linkedOrphanName = holder.name;
      } else {
        throw new ApiHttpError(
          409,
          "PHONE_ALREADY_HAS_ACCOUNT",
          holder.role === "GROUP_ACCOUNT"
            ? "That number already signs in to a different group. Use another number for this group's champion."
            : "That number already belongs to a member or agent account. Use another number for this group's champion."
        );
      }
    } else {
      const login = await tx.user.findFirst({
        where: { groupId: group.id, role: "GROUP_ACCOUNT" },
        orderBy: { createdAt: "asc" },
        select: { id: true, phone: true }
      });

      // The number goes onto the group's existing login ONLY when that login has
      // no usable number of its own — the centrally-onboarded shell this was
      // written for. A group that signed itself up has one: the person who
      // registered it. Overwriting it (as this used to) locked the registrant
      // out of their own account the moment they named a champion, from inside
      // the very app they were signed in to. They get a login of their own.
      const ownNumber = Boolean(login?.phone && isSendableSmsPhone(login.phone));

      if (login && !ownNumber) {
        await tx.user.update({ where: { id: login.id }, data: { phone } });
        outcome = "PHONE_ATTACHED";
        userId = login.id;
      } else {
        // The champion's own login. The password is random and never shown: they
        // get in with a texted code, and can set a password from a reset. Named
        // by phone (not group code) so a second champion, later, cannot collide.
        const created = await tx.user.create({
          data: {
            name: group.name,
            email: login
              ? `${phone}@accounts.intellicash.app`
              : `${group.code.toLowerCase()}@groups.intellicash.co.ke`,
            phone,
            passwordHash: await bcrypt.hash(randomBytes(24).toString("base64url"), 12),
            role: "GROUP_ACCOUNT",
            groupId: group.id
          },
          select: { id: true }
        });
        outcome = "LOGIN_CREATED";
        userId = created.id;
      }
    }

    await tx.group.update({
      where: { id: group.id },
      data: { contactPhone: phone, ...(championName ? { contactPersonName: championName } : {}) }
    });

    return { outcome, userId, linkedOrphanName };
  });

  return { ...result, groupId: group.id, groupName: group.name, phone };
}
