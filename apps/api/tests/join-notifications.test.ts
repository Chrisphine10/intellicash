import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

const TUJIJENGE = "IWL-KBU-0001";
const JOINER_PHONE = "0733444555";

async function login(email: string) {
  const agent = request.agent(app);
  await agent
    .post("/api/v1/auth/login")
    .send({ email, password: "IntellicashDemo#2026" })
    .expect(200);
  return agent;
}

async function notificationsFor(userId: string) {
  return prisma.notification.findMany({
    where: { userId },
    orderBy: { createdAt: "desc" },
    select: { title: true, body: true, href: true }
  });
}

/**
 * A request nobody is told about is a request nobody answers. The approval
 * flow only works if officials learn someone is waiting, and the person who
 * asked learns what was decided.
 */
describe("telling people about join requests", () => {
  let joiner: ReturnType<typeof request.agent>;
  let joinerUserId: string;
  let groupId: string;
  let officialUserId: string;

  beforeAll(async () => {
    await seedDatabase();

    const group = await prisma.group.findFirstOrThrow({ where: { code: TUJIJENGE } });
    groupId = group.id;
    const official = await prisma.user.findFirstOrThrow({
      where: { groupId, role: "GROUP_ACCOUNT" }
    });
    officialUserId = official.id;

    const existing = await prisma.user.findFirst({ where: { phone: JOINER_PHONE } });
    if (existing) {
      await prisma.userMembership.deleteMany({ where: { userId: existing.id } });
      await prisma.groupJoinRequest.deleteMany({ where: { userId: existing.id } });
      await prisma.notification.deleteMany({ where: { userId: existing.id } });
      await prisma.user.delete({ where: { id: existing.id } });
    }
    await prisma.notification.deleteMany({ where: { userId: officialUserId } });

    joiner = request.agent(app);
    const registered = await joiner
      .post("/api/v1/auth/register")
      .send({
        accountType: "MEMBER",
        name: "Peninah Cherono",
        phone: JOINER_PHONE,
        password: "JoinNotify#2026"
      })
      .expect(201);
    joinerUserId = registered.body.data.id;
  }, 60000);

  it("tells the group's officials that someone is waiting", async () => {
    await joiner
      .post("/api/v1/members/me/join-requests")
      .send({ groupCode: TUJIJENGE })
      .expect(200);

    const notices = await notificationsFor(officialUserId);
    const notice = notices.find((n) => n.title === "Someone wants to join");
    expect(notice).toBeTruthy();
    expect(notice?.body).toContain("Peninah Cherono");
    // Takes them straight to the queue rather than leaving them to find it.
    expect(notice?.href).toBe(`/dashboard/groups/${groupId}/join-requests`);
  });

  it("does not tell ordinary members who applied", async () => {
    // The queue holds the name and number of someone who is not a member yet,
    // and a member could not act on it anyway.
    const member = await prisma.user.findFirstOrThrow({
      where: { groupId, role: "MEMBER" }
    });
    const notices = await notificationsFor(member.id);
    expect(notices.some((n) => n.title === "Someone wants to join")).toBe(false);
  });

  it("tells the person who asked when they are accepted", async () => {
    const official = await login("group@intellicash.co.ke");
    const pending = await official.get(`/api/v1/groups/${groupId}/join-requests`).expect(200);
    const mine = pending.body.data.find((r: any) => r.phone === "254733444555");

    await official
      .post(`/api/v1/groups/${groupId}/join-requests/${mine.id}/decision`)
      .send({ decision: "APPROVE" })
      .expect(200);

    const notices = await notificationsFor(joinerUserId);
    const notice = notices.find((n) => n.title.startsWith("You are now in"));
    expect(notice).toBeTruthy();
    expect(notice?.title).toContain("Tujijenge");
  });

  it("tells the person why they were declined, when a reason was given", async () => {
    // A second group, so this run is independent of the approval above.
    const umoja = await prisma.group.findFirstOrThrow({ where: { code: "IWL-KBU-0002" } });
    await joiner
      .post("/api/v1/members/me/join-requests")
      .send({ groupCode: "IWL-KBU-0002" })
      .expect(200);

    const admin = await login("admin@intellicash.co.ke");
    const pending = await admin.get(`/api/v1/groups/${umoja.id}/join-requests`).expect(200);
    const mine = pending.body.data.find((r: any) => r.phone === "254733444555");

    await admin
      .post(`/api/v1/groups/${umoja.id}/join-requests/${mine.id}/decision`)
      .send({ decision: "REJECT", notes: "Come to a meeting first." })
      .expect(200);

    const notices = await notificationsFor(joinerUserId);
    const notice = notices.find((n) => n.title === "Your request was declined");
    expect(notice).toBeTruthy();
    expect(notice?.body).toContain("Come to a meeting first.");
  });
});
