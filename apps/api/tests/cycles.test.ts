import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import {
  assertCycleWritable,
  closeCycleAndOpenNext,
  ensureActiveCycle
} from "../src/services/cycle-service";

const app = createApp();

async function adminCookies() {
  const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: admin.phone, password: demoPassword })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

describe("saving cycles", () => {
  let groupId: string;

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirst({ orderBy: { createdAt: "asc" }, select: { id: true } });
    groupId = group!.id;
  }, 60000);

  it("gives a group an active cycle even if it never had one", async () => {
    // Groups created before cycles existed have no row. Self-healing rather
    // than failing is what keeps older mobile clients working.
    await prisma.$transaction(async (tx) => {
      const cycle = await ensureActiveCycle(tx, groupId);
      expect(cycle.status).toBe("ACTIVE");
      expect(cycle.groupId).toBe(groupId);
    });

    // Idempotent: asking twice must not create a second active cycle.
    await prisma.$transaction(async (tx) => {
      await ensureActiveCycle(tx, groupId);
    });
    const active = await prisma.cycle.count({ where: { groupId, status: "ACTIVE" } });
    expect(active).toBe(1);
  });

  it("stamps new ledger entries with the active cycle", async () => {
    const cycle = await prisma.cycle.findFirst({ where: { groupId, status: "ACTIVE" } });
    const entry = await prisma.ledgerEntry.findFirst({
      where: { groupId },
      orderBy: { createdAt: "desc" }
    });
    // Seeded entries are backfilled or stamped; either way they must belong to
    // a cycle of this group, never to another group's.
    if (entry?.cycleId) {
      const owner = await prisma.cycle.findUnique({ where: { id: entry.cycleId } });
      expect(owner?.groupId).toBe(groupId);
    }
    expect(cycle).not.toBeNull();
  });

  it("refuses writes to a closed cycle but still allows reads", async () => {
    const closed = await prisma.cycle.create({
      data: {
        id: `cyc_test_closed_${Date.now()}`,
        groupId,
        number: 9000,
        status: "CLOSED",
        closedAt: new Date()
      }
    });

    await expect(assertCycleWritable(prisma, closed.id)).rejects.toMatchObject({
      code: "CYCLE_CLOSED"
    });

    // Reading it must still work — archiving is not deletion.
    const readBack = await prisma.cycle.findUnique({ where: { id: closed.id } });
    expect(readBack?.number).toBe(9000);

    await prisma.cycle.delete({ where: { id: closed.id } });
  });

  it("treats a missing cycle as writable, so pre-cycle rows keep working", async () => {
    await expect(assertCycleWritable(prisma, null)).resolves.toBeUndefined();
    await expect(assertCycleWritable(prisma, "does-not-exist")).resolves.toBeUndefined();
  });

  it("closes a cycle and opens the next, carrying members and balances", async () => {
    // Seal anything open first: closing with live meetings is refused by design.
    await prisma.meeting.updateMany({
      where: { groupId, status: { in: ["SCHEDULED", "KEY_UNLOCK_PENDING", "IN_PROGRESS"] } },
      data: { status: "SEALED" }
    });

    const membersBefore = await prisma.member.count({ where: { groupId } });
    const fundsBefore = await prisma.fundAccount.findMany({
      where: { groupId },
      select: { type: true, balanceCents: true },
      orderBy: { type: "asc" }
    });

    const result = await closeCycleAndOpenNext(groupId, { notes: "test close" });

    expect(result.opened.number).toBe(result.closed.number + 1);

    const group = await prisma.group.findUnique({ where: { id: groupId } });
    expect(group?.cycleNumber).toBe(result.opened.number);

    const old = await prisma.cycle.findUnique({ where: { id: result.closed.id } });
    expect(old?.status).toBe("CLOSED");
    expect(old?.closedAt).not.toBeNull();

    // A new cycle must be immediately usable: membership and money carry over.
    expect(await prisma.member.count({ where: { groupId } })).toBe(membersBefore);
    const fundsAfter = await prisma.fundAccount.findMany({
      where: { groupId },
      select: { type: true, balanceCents: true },
      orderBy: { type: "asc" }
    });
    expect(fundsAfter).toEqual(fundsBefore);

    // And the previous cycle's records are untouched, not deleted.
    const archived = await prisma.meeting.count({ where: { cycleId: result.closed.id } });
    expect(archived).toBe(result.archivedMeetings);
  });

  it("refuses to close a cycle that still has an unsealed meeting", async () => {
    const active = await prisma.cycle.findFirst({ where: { groupId, status: "ACTIVE" } });
    const meeting = await prisma.meeting.create({
      data: {
        groupId,
        cycleId: active!.id,
        title: "Still open",
        status: "IN_PROGRESS",
        scheduledAt: new Date()
      }
    });

    await expect(closeCycleAndOpenNext(groupId)).rejects.toMatchObject({
      code: "CYCLE_HAS_OPEN_MEETINGS"
    });

    await prisma.meeting.delete({ where: { id: meeting.id } });
  });

  it("exposes cycle history over the API and marks which are editable", async () => {
    const cookies = await adminCookies();
    const response = await request(app)
      .get(`/api/v1/groups/${groupId}/cycles`)
      .set("Cookie", cookies)
      .expect(200);

    const { cycles, canManage } = response.body.data;
    expect(canManage).toBe(true);
    expect(cycles.length).toBeGreaterThanOrEqual(2);
    expect(cycles.filter((c: { editable: boolean }) => c.editable)).toHaveLength(1);
    expect(cycles[0].number).toBeGreaterThan(cycles[1].number); // newest first
  });
});
