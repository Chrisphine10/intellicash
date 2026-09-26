/**
 * Who holds a group's offices — one definition for every path that changes it.
 *
 * `Member.role` answers "now"; `MemberRoleAssignment` keeps who held each
 * office and when. The role-assignments route, a member edit on the console,
 * a member registered from a phone and an election result all come through
 * here, so none of them can leave two chairpersons or an office with no
 * history (the member edit and create paths used to write `Member.role`
 * directly and did both).
 */

import type { MemberRoleAssignment, Prisma } from "@prisma/client";
import { ApiHttpError } from "../lib/http";
import { ensureActiveCycle } from "./cycle-service";

/** Offices only one member may hold at a time. */
export const SINGLETON_ROLES = new Set(["CHAIRPERSON", "SECRETARY", "TREASURER"]);

export interface AssignOfficeInput {
  groupId: string;
  memberId: string;
  role: string;
  byUserId?: string | null;
  note?: string | null;
  /**
   * The role-assignments route answers a repeat with 409 ALREADY_HOLDS_ROLE
   * (the phone reads that as "done"). Every other caller treats a repeat as
   * nothing to do.
   */
  refuseRepeat?: boolean;
}

export interface AssignOfficeResult {
  assignment: MemberRoleAssignment | null;
  member: { id: string; fullName: string; role: string };
  replaced: { fullName: string } | null;
  unchanged: boolean;
}

export async function assignOffice(tx: Prisma.TransactionClient, input: AssignOfficeInput): Promise<AssignOfficeResult> {
  const { groupId, role } = input;
  const member = await tx.member.findFirst({
    where: { id: input.memberId, groupId },
    select: { id: true, fullName: true, role: true }
  });
  if (!member) throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member is not in this group.");

  // Holding it already is not a second term. Checked against the history, and
  // against `Member.role` for offices given before history was kept.
  if (role !== "MEMBER") {
    const already = await tx.memberRoleAssignment.findFirst({
      where: { groupId, memberId: member.id, role, endedAt: null }
    });
    if (already) {
      if (input.refuseRepeat) {
        throw new ApiHttpError(409, "ALREADY_HOLDS_ROLE", `${member.fullName} already holds this office.`);
      }
      return { assignment: already, member, replaced: null, unchanged: true };
    }
  } else if (member.role === "MEMBER") {
    const open = await tx.memberRoleAssignment.count({ where: { groupId, memberId: member.id, endedAt: null } });
    if (open === 0) return { assignment: null, member, replaced: null, unchanged: true };
  }

  const now = new Date();
  // A member holds one office at a time: moving them ends the one they held.
  await tx.memberRoleAssignment.updateMany({
    where: { groupId, memberId: member.id, endedAt: null, role: { not: role } },
    data: { endedAt: now }
  });

  if (role === "MEMBER") {
    await tx.member.update({ where: { id: member.id }, data: { role: "MEMBER" } });
    return { assignment: null, member: { ...member, role: "MEMBER" }, replaced: null, unchanged: false };
  }

  let replaced: { fullName: string } | null = null;
  if (SINGLETON_ROLES.has(role)) {
    // End the incumbent's term rather than deleting it: the group must be able
    // to say who was secretary last March.
    const holders = await tx.memberRoleAssignment.findMany({
      where: { groupId, role, endedAt: null, memberId: { not: member.id } },
      include: { member: { select: { fullName: true } } }
    });
    if (holders.length > 0) {
      await tx.memberRoleAssignment.updateMany({
        where: { id: { in: holders.map((holder) => holder.id) } },
        data: { endedAt: now }
      });
      replaced = { fullName: holders[0]!.member.fullName };
    }
    // Also anyone holding it only through `Member.role` (no history row).
    const stale = await tx.member.findFirst({
      where: { groupId, role, id: { not: member.id } },
      select: { fullName: true }
    });
    if (stale && !replaced) replaced = { fullName: stale.fullName };
    await tx.member.updateMany({
      where: { groupId, role, id: { not: member.id } },
      data: { role: "MEMBER" }
    });
  }

  const cycle = await ensureActiveCycle(tx, groupId);
  const assignment = await tx.memberRoleAssignment.create({
    data: {
      groupId,
      memberId: member.id,
      cycleId: cycle.id,
      role,
      startedAt: now,
      assignedByUserId: input.byUserId ?? null,
      note: input.note ?? null
    }
  });
  await tx.member.update({ where: { id: member.id }, data: { role } });
  return { assignment, member: { ...member, role }, replaced, unchanged: false };
}
