import type { Prisma, PrismaClient } from "@prisma/client";
import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";

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
  options: { closedByUserId?: string | null; notes?: string | null } = {}
): Promise<CloseCycleResult> {
  return prisma.$transaction(async (tx) => {
    const current = await ensureActiveCycle(tx, groupId);

    const openMeetings = await tx.meeting.count({
      where: { cycleId: current.id, status: { in: ["SCHEDULED", "KEY_UNLOCK_PENDING", "IN_PROGRESS"] } }
    });
    if (openMeetings > 0) {
      throw new ApiHttpError(
        409,
        "CYCLE_HAS_OPEN_MEETINGS",
        `Cycle ${current.number} still has ${openMeetings} meeting(s) that are not sealed. Seal or cancel them before closing the cycle.`
      );
    }

    const now = new Date();
    await tx.cycle.update({
      where: { id: current.id },
      data: {
        status: CYCLE_CLOSED,
        closedAt: now,
        closedByUserId: options.closedByUserId ?? null,
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

    const archivedMeetings = await tx.meeting.count({ where: { cycleId: current.id } });

    return {
      closed: { id: current.id, number: current.number },
      opened: { id: opened.id, number: opened.number },
      archivedMeetings
    };
  });
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
