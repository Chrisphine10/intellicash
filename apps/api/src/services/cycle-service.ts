/**
 * Saving cycles.
 *
 * `Group.cycleNumber` was only ever a label — nothing was scoped to it, so a
 * closed cycle could still be written to. A `Cycle` row now owns the meetings
 * and ledger entries of one cycle, and closing it makes those rows read-only
 * while leaving them fully visible to history and reports.
 *
 * Everything here is designed so a group that never starts a new cycle behaves
 * exactly as it did before.
 */

import type { Prisma, PrismaClient } from "@prisma/client";
import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";
import type { AuthenticatedUser } from "../middleware/auth";

/**
 * Saving cycles.
 *
 * `Group.cycleNumber` was only ever a label — nothing was scoped to it, so a
 * closed cycle could still be written to. A `Cycle` row now owns the meetings
 * and ledger entries of one cycle, and closing it makes those rows read-only
 * while leaving them fully visible to history and reports.
 *
 * Everything here is designed so a group that never starts a new cycle behaves
 * exactly as it did before.
 */

type Tx = Prisma.TransactionClient | PrismaClient;

export const CYCLE_ACTIVE = "ACTIVE";
export const CYCLE_CLOSED = "CLOSED";

/**
 * The cycle new writes belong to.
 *
 * Self-healing: a group whose cycle row is missing (created before this
 * feature, or by a client that knows nothing about cycles) gets one on first
 * use rather than failing. That is what keeps older mobile clients working.
 */
export async function ensureActiveCycle(tx: Tx, groupId: string) {
  const existing = await tx.cycle.findFirst({
    where: { groupId, status: CYCLE_ACTIVE },
    orderBy: { number: "desc" }
  });
  if (existing) return existing;

  const group = await tx.group.findUnique({
    where: { id: groupId },
    select: { id: true, cycleNumber: true, createdAt: true }
  });
  if (!group) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist.");

  return tx.cycle.create({
    data: {
      // Same derived id the backfill migration uses, so the two can never
      // create competing rows for the same group and number.
      id: `cyc_${group.id}_${group.cycleNumber}`,
      groupId: group.id,
      number: group.cycleNumber,
      startedAt: group.createdAt,
      status: CYCLE_ACTIVE
    }
  });
}

/**
 * Refuses a write to a closed cycle.
 *
 * Called from the ledger and meeting write paths rather than from each route,
 * so a new route cannot forget it. Reads never call this — history stays
 * readable, which is the whole point of archiving rather than deleting.
 */
export async function assertCycleWritable(tx: Tx, cycleId: string | null | undefined) {
  if (!cycleId) return; // Pre-cycle rows and older clients: nothing to enforce.

  const cycle = await tx.cycle.findUnique({
    where: { id: cycleId },
    select: { status: true, number: true, closedAt: true }
  });
  if (!cycle) return;

  if (cycle.status === CYCLE_CLOSED) {
    throw new ApiHttpError(
      409,
      "CYCLE_CLOSED",
      `Cycle ${cycle.number} was closed${
        cycle.closedAt ? ` on ${cycle.closedAt.toISOString().slice(0, 10)}` : ""
      } and cannot be changed. Its records stay available in history and reports.`
    );
  }
}

/** Same guard, addressed by meeting rather than cycle. */
export async function assertMeetingWritable(tx: Tx, meetingId: string) {
  const meeting = await tx.meeting.findUnique({
    where: { id: meetingId },
    select: { cycleId: true }
  });
  await assertCycleWritable(tx, meeting?.cycleId);
}

export interface CloseCycleResult {
  closed: { id: string; number: number };
  opened: { id: string; number: number };
  archivedMeetings: number;
}

/**
 * Ends the current cycle and opens the next.
 *
 * One transaction: close, open, roll `Group.cycleNumber`. If any part fails
 * nothing moves — a group left with two active cycles, or a rolled number and
 * no cycle, would be worse than the operation simply failing.
 *
 * Members, roles and balances are deliberately NOT touched. Carrying them
 * forward is what makes a new cycle usable immediately, and closed-cycle rows
 * stay pinned to the old cycle so editing membership afterwards cannot rewrite
 * history.
 */
export async function closeCycleAndOpenNext(
  groupId: string,
  options: CloseCycleOptions = {}
): Promise<CloseCycleResult> {
  return prisma.$transaction((tx) => closeCycleWithin(tx, groupId, options));
}

export interface CloseCycleOptions {
  closedByUserId?: string | null;
  notes?: string | null;
  /** Set when a phone's share-out ends the cycle; see `Cycle.closedByShareOutId`. */
  closedByShareOutId?: string | null;
  /**
   * The meeting the cycle ends in (a share-out reviewed on the web happens
   * inside an open meeting). It is sealed with the cycle instead of blocking
   * the close as "still open".
   */
  sealMeetingId?: string | null;
}

/**
 * Ledger rows in each group's CURRENT cycle, by the statement's rule: stamped
 * with the active cycle, or (older rows with no stamp) made since it began. A
 * group with no cycle rows at all is one cycle long. "Active cycle OR no
 * stamp" at any date — what the portfolio and group list used to count —
 * added every pre-cycle share ever bought to "savings this cycle".
 */
export async function activeCycleEntriesWhere(
  db: Prisma.TransactionClient | PrismaClient,
  groupIds: string[]
): Promise<Prisma.LedgerEntryWhereInput> {
  const cycles = await db.cycle.findMany({
    where: { groupId: { in: groupIds } },
    select: { id: true, groupId: true, status: true, startedAt: true }
  });
  const withCycles = new Set(cycles.map((cycle) => cycle.groupId));
  // A group whose cycles are all closed has nothing "this cycle".
  const active = cycles.filter((cycle) => cycle.status === "ACTIVE");
  return {
    groupId: { in: groupIds },
    OR: [
      { cycleId: { in: active.map((cycle) => cycle.id) } },
      ...active.map((cycle) => ({ groupId: cycle.groupId, cycleId: null, createdAt: { gte: cycle.startedAt } })),
      { groupId: { in: groupIds.filter((id) => !withCycles.has(id)) } }
    ]
  };
}

/**
 * The share purchases that belong to the cycle a share-out would end: stamped
 * with the active cycle, or (older rows with no stamp) made since the last
 * payout. One definition for the console's reviewed share-out and the phone's
 * recorded one, so the two never disagree about which cycle a share belongs
 * to. A cycle closed WITHOUT a payout does not carry its shares forward — they
 * were settled when it closed, and counting them again would pay them twice.
 */
export async function currentCycleSharesWhere(
  tx: Prisma.TransactionClient,
  groupId: string
): Promise<Prisma.LedgerEntryWhereInput> {
  const [lastShareOut, activeCycle] = await Promise.all([
    tx.ledgerEntry.findFirst({
      where: { groupId, type: "SHARE_OUT_PAYOUT" },
      orderBy: { createdAt: "desc" },
      select: { createdAt: true }
    }),
    tx.cycle.findFirst({
      where: { groupId, status: "ACTIVE" },
      orderBy: { number: "desc" },
      select: { id: true }
    })
  ]);
  const unstampedSinceLastPayout: Prisma.LedgerEntryWhereInput = {
    cycleId: null,
    ...(lastShareOut ? { createdAt: { gt: lastShareOut.createdAt } } : {})
  };
  return {
    groupId,
    type: "SHARE_PURCHASE",
    direction: "CREDIT",
    ...(activeCycle
      ? { OR: [{ cycleId: activeCycle.id }, unstampedSinceLastPayout] }
      : lastShareOut
        ? { createdAt: { gt: lastShareOut.createdAt } }
        : {})
  };
}

/**
 * The close itself, run inside a transaction the caller already holds.
 *
 * A share-out from a phone records its payouts and ends the cycle as ONE step:
 * if the cycle cannot be closed (a meeting is open on the console) the payouts
 * must not stay behind, so both have to share a transaction.
 */
export async function closeCycleWithin(
  tx: Prisma.TransactionClient,
  groupId: string,
  options: CloseCycleOptions = {}
): Promise<CloseCycleResult> {
  const current = await ensureActiveCycle(tx, groupId);

  // Only a meeting that is happening RIGHT NOW blocks the close — other than
  // the one the cycle is ending in, which is sealed with it.
  const openMeetings = await tx.meeting.count({
    where: {
      cycleId: current.id,
      status: { in: ["KEY_UNLOCK_PENDING", "IN_PROGRESS"] },
      ...(options.sealMeetingId ? { id: { not: options.sealMeetingId } } : {})
    }
  });
  if (openMeetings > 0) {
    throw new ApiHttpError(
      409,
      "CYCLE_HAS_OPEN_MEETINGS",
      `Cycle ${current.number} still has ${openMeetings} meeting(s) that are not sealed. Seal or cancel them before closing the cycle.`
    );
  }

  // A meeting kept on a phone never passes through the server's open and seal
  // steps: it stays SCHEDULED although attendance and money were recorded in
  // it. Blocking the close on those would make it impossible for any group
  // that keeps its book on a phone. So at close they are what they were —
  // HELD — and are sealed; a meeting that was only ever planned rolls forward
  // into the new cycle instead of being locked away unheld.
  const scheduled = await tx.meeting.findMany({
    where: { cycleId: current.id, status: "SCHEDULED" },
    select: { id: true, _count: { select: { attendance: true, ledgerEntries: true } } }
  });
  const heldIds = scheduled
    .filter((meeting) => meeting._count.attendance > 0 || meeting._count.ledgerEntries > 0)
    .map((meeting) => meeting.id);
  const plannedIds = scheduled
    .filter((meeting) => meeting._count.attendance === 0 && meeting._count.ledgerEntries === 0)
    .map((meeting) => meeting.id);

  const now = new Date();
  await tx.cycle.update({
    where: { id: current.id },
    data: {
      status: CYCLE_CLOSED,
      closedAt: now,
      closedByUserId: options.closedByUserId ?? null,
      closedByShareOutId: options.closedByShareOutId ?? null,
      notes: options.notes ?? null
    }
  });

  const nextNumber = current.number + 1;
  const opened = await tx.cycle.create({
    data: {
      id: `cyc_${groupId}_${nextNumber}`,
      groupId,
      number: nextNumber,
      startedAt: now,
      status: CYCLE_ACTIVE
    }
  });

  await tx.group.update({ where: { id: groupId }, data: { cycleNumber: nextNumber } });

  if (heldIds.length > 0) {
    await tx.meeting.updateMany({ where: { id: { in: heldIds } }, data: { status: "SEALED", closedAt: now } });
  }
  if (options.sealMeetingId) {
    await tx.meeting.updateMany({
      where: { id: options.sealMeetingId, cycleId: current.id, status: { notIn: ["SEALED", "CANCELLED"] } },
      data: { status: "SEALED", closedAt: now }
    });
  }
  if (plannedIds.length > 0) {
    await tx.meeting.updateMany({ where: { id: { in: plannedIds } }, data: { cycleId: opened.id } });
  }

  const archivedMeetings = await tx.meeting.count({ where: { cycleId: current.id } });

  return {
    closed: { id: current.id, number: current.number },
    opened: { id: opened.id, number: opened.number },
    archivedMeetings
  };
}

/**
 * Closing a cycle archives a whole cycle of records and starts a fresh one.
 * A platform admin, or the group's own account, may do it. A village agent may
 * read a group but must not end its cycle.
 *
 * A role/scope check rather than a new permission string, because
 * ensureRolePermissionTemplates upserts with `update: {}` - a new permission
 * would never reach existing template rows.
 */
export function assertMayManageCycles(user: AuthenticatedUser | undefined, groupId: string) {
  if (!user) throw new ApiHttpError(401, "UNAUTHENTICATED", "Please sign in to continue. If you were signed in, your session has ended.");
  if (user.permissions.includes("groups:write")) return;
  if (user.role === "GROUP_ACCOUNT" && user.groupId === groupId) return;

  throw new ApiHttpError(
    403,
    "FORBIDDEN",
    "Only a platform admin or the group's own account may close a cycle."
  );
}

/** Cycle history for a group, newest first. */
export async function listCycles(groupId: string) {
  const cycles = await prisma.cycle.findMany({
    where: { groupId },
    orderBy: { number: "desc" },
    include: { _count: { select: { meetings: true, ledgerEntries: true } } }
  });

  return cycles.map((cycle) => ({
    id: cycle.id,
    number: cycle.number,
    status: cycle.status,
    startedAt: cycle.startedAt.toISOString(),
    closedAt: cycle.closedAt?.toISOString() ?? null,
    notes: cycle.notes,
    meetings: cycle._count.meetings,
    ledgerEntries: cycle._count.ledgerEntries,
    // Stated rather than inferred, so a UI does not have to know the rule.
    editable: cycle.status === CYCLE_ACTIVE
  }));
}
