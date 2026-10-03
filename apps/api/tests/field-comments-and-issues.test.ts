import request from "supertest";
import { afterAll, beforeAll, describe, expect, it, vi } from "vitest";
import { demoAccounts, demoPassword, seesFieldComments } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { recordSystemIssue, scrubContext, scrubText } from "../src/services/system-issue-service";

const app = createApp();
const RUN = Date.now().toString(36).toUpperCase();

async function signIn(role: string) {
  const account = demoAccounts.find((candidate) => candidate.role === role)!;
  const response = await request(app).post("/api/v1/auth/login").send({ phone: account.phone, password: demoPassword }).expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

async function waitFor<T>(read: () => Promise<T | null>, attempts = 40): Promise<T | null> {
  for (let i = 0; i < attempts; i += 1) {
    const value = await read();
    if (value) return value;
    await new Promise((resolve) => setTimeout(resolve, 50));
  }
  return null;
}

let groupId: string;
let visitId: string;

beforeAll(async () => {
  const partnerAccount = demoAccounts.find((account) => account.role === "PARTNER_OFFICER")!;
  const partnerUser = await prisma.user.findFirstOrThrow({ where: { phone: partnerAccount.phone } });
  const programme = await prisma.programme.create({ data: { partnerId: partnerUser.partnerId!, name: `Comments test ${RUN}` } });
  const group = await prisma.group.create({
    data: { name: `Comments ${RUN}`, code: `IWL-TST-${RUN}-C`, phase: "MOBILISATION", county: "Embu", programmeId: programme.id }
  });
  groupId = group.id;
  const visit = await prisma.groupVisit.create({
    data: {
      groupId,
      clientRequestId: `comments-${RUN}`,
      visitType: "FOLLOW_UP",
      startedAt: new Date(),
      notes: `Treasurer seemed unsure ${RUN}`,
      locationNote: `Met at the chief's camp ${RUN}`
    }
  });
  visitId = visit.id;
  await prisma.visitMentorshipSession.create({
    data: { visitId, topicKeySnapshot: "governance", topicTitleSnapshot: "Governance", notes: `Elections overdue ${RUN}` }
  });
  await prisma.visitActionItem.create({
    data: { visitId, groupId, title: "Hold elections", detail: `Chair refuses to step down ${RUN}`, owner: "Secretary" }
  });
}, 120000);

afterAll(async () => {
  await prisma.systemIssue.deleteMany({ where: { OR: [{ groupId }, { title: { contains: RUN } }] } });
  const group = await prisma.group.findUnique({ where: { id: groupId }, select: { programmeId: true } });
  await prisma.groupVisit.deleteMany({ where: { groupId } });
  await prisma.group.deleteMany({ where: { id: groupId } });
  if (group?.programmeId) await prisma.programme.deleteMany({ where: { id: group.programmeId } });
});

describe("field comments are for IWL staff and the CBTs who visit", () => {
  it("only admins and agents see them", () => {
    expect(seesFieldComments("IWL_ADMIN")).toBe(true);
    expect(seesFieldComments("VILLAGE_AGENT")).toBe(true);
    for (const role of ["PARTNER_OFFICER", "LENDER", "READ_ONLY", "GROUP_ACCOUNT", "MEMBER", null]) {
      expect(seesFieldComments(role)).toBe(false);
    }
  });

  it("a partner reads the visit, its coaching and its action items without the comments", async () => {
    const partner = await signIn("PARTNER_OFFICER");
    const visits = await request(app).get(`/api/v1/groups/${groupId}/visits`).set("Cookie", partner).expect(200);
    const one = await request(app).get(`/api/v1/visits/${visitId}`).set("Cookie", partner).expect(200);
    const mentorship = await request(app).get(`/api/v1/visits/${visitId}/mentorship`).set("Cookie", partner).expect(200);
    const items = await request(app).get(`/api/v1/groups/${groupId}/action-items`).set("Cookie", partner).expect(200);

    const everything = JSON.stringify([visits.body, one.body, mentorship.body, items.body]);
    expect(everything).not.toContain(`unsure ${RUN}`);
    expect(everything).not.toContain(`chief's camp ${RUN}`);
    expect(everything).not.toContain(`Elections overdue ${RUN}`);
    expect(everything).not.toContain(`step down ${RUN}`);
    // The facts stay: the visit happened, what was coached, what was agreed.
    expect(everything).toContain("Governance");
    expect(everything).toContain("Hold elections");
  });

  it("an IWL admin still reads them", async () => {
    const admin = await signIn("IWL_ADMIN");
    const one = await request(app).get(`/api/v1/visits/${visitId}`).set("Cookie", admin).expect(200);
    const mentorship = await request(app).get(`/api/v1/visits/${visitId}/mentorship`).set("Cookie", admin).expect(200);
    const items = await request(app).get(`/api/v1/groups/${groupId}/action-items`).set("Cookie", admin).expect(200);
    const everything = JSON.stringify([one.body, mentorship.body, items.body]);
    expect(everything).toContain(`unsure ${RUN}`);
    expect(everything).toContain(`Elections overdue ${RUN}`);
    expect(everything).toContain(`step down ${RUN}`);
  });
});

describe("the system issue log", () => {
  it("scrubs credentials and phone numbers before storing anything", () => {
    expect(scrubText("call 0712345678 or +254712345678 with Bearer abcdefghijklmnop")).toBe("call [phone] or [phone] with Bearer [redacted]");
    expect(scrubContext({ password: "x", nested: { otpCode: "1234", route: "/groups" } })).toEqual({
      password: "[redacted]",
      nested: { otpCode: "[redacted]", route: "/groups" }
    });
  });

  it("folds repeats into one row and reopens a resolved problem that comes back", async () => {
    const input = { source: "JOB" as const, severity: "ERROR" as const, category: "TEST", title: `Nightly job failed ${RUN}` };
    const id = await recordSystemIssue(input);
    expect(await recordSystemIssue(input)).toBe(id);
    let row = await prisma.systemIssue.findUniqueOrThrow({ where: { id: id! } });
    expect(row.occurrences).toBe(2);

    await prisma.systemIssue.update({ where: { id: id! }, data: { status: "RESOLVED", resolvedAt: new Date() } });
    await recordSystemIssue(input);
    row = await prisma.systemIssue.findUniqueOrThrow({ where: { id: id! } });
    expect(row).toMatchObject({ status: "OPEN", occurrences: 3, resolvedAt: null });
  });

  it("logs a server fault with its route, and the person only sees 'try again'", async () => {
    const admin = await signIn("IWL_ADMIN");
    const spy = vi.spyOn(prisma.groupNeedsAssessment, "findMany").mockRejectedValueOnce(new Error(`Disk on fire ${RUN}`));
    const response = await request(app).get(`/api/v1/groups/${groupId}/needs-assessments`).set("Cookie", admin).expect(500);
    spy.mockRestore();
    expect(response.body.error.code).toBe("INTERNAL_ERROR");
    expect(response.body.error.message).not.toContain(RUN);

    const issue = await waitFor(() => prisma.systemIssue.findFirst({ where: { title: { contains: `Disk on fire ${RUN}` } } }));
    expect(issue).toMatchObject({ source: "API", category: "INTERNAL_ERROR", lastTraceId: response.body.error.traceId });
    expect(issue!.title).toContain("/groups/:groupId/needs-assessments");
  });

  it("takes reports from a signed-in screen, and only IWL admins read and work the list", async () => {
    const partner = await signIn("PARTNER_OFFICER");
    await request(app)
      .post("/api/v1/system-issues/report")
      .set("Cookie", partner)
      .send({ source: "WEB", message: `Cannot read properties of undefined ${RUN}`, screen: "/dashboard/groups/cabcdefghijklmnopqrstuvw" })
      .expect(200);
    await request(app).post("/api/v1/system-issues/report").send({ source: "WEB", message: "anonymous" }).expect(401);
    await request(app).get("/api/v1/system-issues").set("Cookie", partner).expect(403);

    const admin = await signIn("IWL_ADMIN");
    const list = await request(app).get("/api/v1/system-issues?source=WEB").set("Cookie", admin).expect(200);
    const reported = (list.body.data as Array<{ id: string; title: string; context: Record<string, unknown> }>).find((row) =>
      row.title.includes(`undefined ${RUN}`)
    )!;
    expect(reported.title).toContain("/dashboard/groups/:id");
    expect(reported.context.role).toBe("PARTNER_OFFICER");

    await request(app).patch(`/api/v1/system-issues/${reported.id}`).set("Cookie", admin).send({ status: "RESOLVED" }).expect(400);
    const done = await request(app)
      .patch(`/api/v1/system-issues/${reported.id}`)
      .set("Cookie", admin)
      .send({ status: "RESOLVED", note: "Guarded the empty response" })
      .expect(200);
    expect(done.body.data).toMatchObject({ status: "RESOLVED", resolutionNote: "Guarded the empty response" });
    await prisma.systemIssue.delete({ where: { id: reported.id } });
  });
});
