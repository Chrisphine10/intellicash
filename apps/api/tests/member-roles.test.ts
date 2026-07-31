import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

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

/**
 * The whole point of this table: a handover must not rewrite the past. If
 * reassigning the secretary erased who held it before, a meeting minuted last
 * year would appear to have been taken by whoever holds it today.
 */
describe("member role assignments", () => {
  let groupId: string;
  let first: string;
  let second: string;
  let cookies: string[];

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirst({ orderBy: { createdAt: "asc" } });
    groupId = group!.id;
    const members = await prisma.member.findMany({ where: { groupId }, take: 2 });
    first = members[0]!.id;
    second = members[1]!.id;
    cookies = await adminCookies();
    await prisma.memberRoleAssignment.deleteMany({ where: { groupId } });
  }, 60000);

  // NOT async: supertest's Test is chainable, and wrapping it in a Promise
  // loses .expect().
  function assign(memberId: string, role: string) {
    return request(app)
      .post(`/api/v1/groups/${groupId}/role-assignments`)
      .set("Cookie", cookies)
      .send({ memberId, role });
  }

  it("records who holds an office", async () => {
    const response = await assign(first, "SECRETARY").expect(201);
    expect(response.body.data.assignment.role).toBe("SECRETARY");
    expect(response.body.data.assignment.endedAt).toBeNull();

    const member = await prisma.member.findUnique({ where: { id: first } });
    expect(member?.role).toBe("SECRETARY");
  });

  it("handing over ENDS the previous term instead of deleting it", async () => {
    const response = await assign(second, "SECRETARY").expect(201);
    // The message must say the old term is kept, since "replaced" reads as
    // "erased" to whoever clicks it.
    expect(response.body.data.message).toMatch(/ended, not deleted/i);

    const all = await prisma.memberRoleAssignment.findMany({
      where: { groupId, role: "SECRETARY" },
      orderBy: { startedAt: "asc" }
    });
    expect(all).toHaveLength(2);

    const previous = all.find((a) => a.memberId === first);
    expect(previous?.endedAt).not.toBeNull();
    const current = all.find((a) => a.memberId === second);
    expect(current?.endedAt).toBeNull();
  });

  it("leaves exactly one secretary in office", async () => {
    const open = await prisma.memberRoleAssignment.count({
      where: { groupId, role: "SECRETARY", endedAt: null }
    });
    expect(open).toBe(1);

    // Member.role is kept in step for everything that still reads it.
    const demoted = await prisma.member.findUnique({ where: { id: first } });
    expect(demoted?.role).toBe("MEMBER");
  });

  it("refuses to reappoint the member who already holds the office", async () => {
    const response = await assign(second, "SECRETARY").expect(409);
    expect(response.body.error.code).toBe("ALREADY_HOLDS_ROLE");
  });

  it("stamps the assignment with the cycle it happened in", async () => {
    // So a new cycle can reshuffle leadership without touching the closed
    // cycle's record of who was responsible.
    const current = await prisma.memberRoleAssignment.findFirst({
      where: { groupId, role: "SECRETARY", endedAt: null }
    });
    expect(current?.cycleId).toBeTruthy();
  });

  it("separates who is in office now from who held it before", async () => {
    const response = await request(app)
      .get(`/api/v1/groups/${groupId}/role-assignments`)
      .set("Cookie", cookies)
      .expect(200);

    const { current, history } = response.body.data;
    expect(current.every((a: { endedAt: string | null }) => a.endedAt === null)).toBe(true);
    expect(history.every((a: { endedAt: string | null }) => a.endedAt !== null)).toBe(true);
    expect(history.length).toBeGreaterThanOrEqual(1);
  });

  it("refuses to end a term twice", async () => {
    const current = await prisma.memberRoleAssignment.findFirst({
      where: { groupId, role: "SECRETARY", endedAt: null }
    });

    await request(app)
      .post(`/api/v1/groups/${groupId}/role-assignments/${current!.id}/end`)
      .set("Cookie", cookies)
      .expect(200);

    // Ending twice would move the date and misstate when the term closed.
    const again = await request(app)
      .post(`/api/v1/groups/${groupId}/role-assignments/${current!.id}/end`)
      .set("Cookie", cookies)
      .expect(409);
    expect(again.body.error.code).toBe("ALREADY_ENDED");
  });
});
