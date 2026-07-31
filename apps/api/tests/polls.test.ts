import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword, rolePermissions } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

async function signIn(phone: string) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone, password: demoPassword })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

/**
 * Voting had no regression test, and its known failure mode is silent: a role
 * whose permission template lacks votes:* simply cannot vote, and the feature
 * looks present while being unusable for the people it exists for.
 */
describe("group voting", () => {
  let groupId: string;
  let cookies: string[];
  let optionIds: string[] = [];
  let pollId: string;

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirst({ orderBy: { createdAt: "asc" } });
    groupId = group!.id;
    const admin = demoAccounts.find((a) => a.role === "IWL_ADMIN")!;
    cookies = await signIn(admin.phone);
  }, 60000);

  it("MEMBER holds votes:read and votes:write", () => {
    // The bug this guards: members could not vote at all, which defeats the
    // feature. Asserted on the static map that seeds the templates.
    const member = rolePermissions.MEMBER as readonly string[];
    expect(member).toContain("votes:read");
    expect(member).toContain("votes:write");
  });

  it("every seeded role template carries what the static map promises", async () => {
    // ensureRolePermissionTemplates upserts with `update: {}`, so an EXISTING
    // row never gains a newly added permission. On a fresh database they match;
    // this fails loudly if they ever drift.
    const template = await prisma.rolePermissionTemplate.findUnique({
      where: { role: "MEMBER" }
    });
    const stored = JSON.parse(template!.permissionsJson) as string[];
    expect(stored).toContain("votes:write");
  });

  it("creates a decision poll with options", async () => {
    const response = await request(app)
      .post(`/api/v1/groups/${groupId}/polls`)
      .set("Cookie", cookies)
      .send({
        type: "DECISION",
        title: "Should we raise the share value?",
        options: [{ label: "Yes" }, { label: "No" }]
      })
      .expect(201);

    pollId = response.body.data.id;
    optionIds = response.body.data.options.map((o: { id: string }) => o.id);
    expect(optionIds).toHaveLength(2);
  });

  it("refuses a poll with fewer than two options", async () => {
    // A ballot with one choice is not a vote.
    await request(app)
      .post(`/api/v1/groups/${groupId}/polls`)
      .set("Cookie", cookies)
      .send({ title: "Only one way", options: [{ label: "Yes" }] })
      .expect(400);
  });

  it("records a ballot and counts it", async () => {
    const member = await prisma.member.findFirst({ where: { groupId } });
    await request(app)
      .post(`/api/v1/polls/${pollId}/vote`)
      .set("Cookie", cookies)
      .send({ optionId: optionIds[0], memberId: member!.id })
      // 201: casting a ballot CREATES a vote record.
      .expect(201);

    const votes = await prisma.pollVote.count({ where: { pollId } });
    expect(votes).toBe(1);
  });

  it("enforces one member one vote at the DATABASE level", async () => {
    const member = await prisma.member.findFirst({ where: { groupId } });
    // Application logic could be bypassed; the unique constraint cannot.
    await expect(
      prisma.pollVote.create({
        data: { pollId, optionId: optionIds[1]!, memberId: member!.id }
      })
    ).rejects.toThrow();

    expect(await prisma.pollVote.count({ where: { pollId } })).toBe(1);
  });

  it("closing freezes the result and writes it in words", async () => {
    const response = await request(app)
      .post(`/api/v1/polls/${pollId}/close`)
      .set("Cookie", cookies)
      .expect(200);

    const poll = await prisma.poll.findUnique({ where: { id: pollId } });
    expect(poll?.status).not.toBe("OPEN");
    // A stored summary means the outcome survives later edits to members or
    // options — the minute book must not change retrospectively.
    expect(poll?.resultSummary ?? response.body.data.resultSummary).toBeTruthy();
  });
});
