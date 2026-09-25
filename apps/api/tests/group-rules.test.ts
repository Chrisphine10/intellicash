import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { buildRestoreBundle } from "../src/services/restore-bundle-service";

const app = createApp();

async function signIn(identifier: { phone: string; password?: string }) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: identifier.phone, password: identifier.password ?? demoPassword });
  const cookie = response.headers["set-cookie"];
  return { response, cookies: Array.isArray(cookie) ? cookie : cookie ? [cookie as unknown as string] : [] };
}

const account = (role: string) => demoAccounts.find((candidate) => candidate.role === role)!;

/**
 * A group's own rules live on the server as well as its phone, loans keep the
 * interest type they were lent under, entries typed on the web keep to the
 * rules (phone syncs are never refused), and member sign-ins follow the
 * group's switch — one login per person, however many groups.
 */
describe("group rules, member sign-ins and consistency", () => {
  let admin: string[];
  let groupAccount: string[];
  let groupId: string;
  let otherGroupId: string;
  let meetingId: string;
  let saverId: string;
  let baselineLoanGaps: string[];

  beforeAll(async () => {
    await seedDatabase();
    admin = (await signIn({ phone: account("IWL_ADMIN").phone })).cookies;
    groupAccount = (await signIn({ phone: account("GROUP_ACCOUNT").phone })).cookies;
    const me = await request(app).get("/api/v1/auth/me").set("Cookie", groupAccount).expect(200);
    groupId = me.body.data.groupId;
    otherGroupId = (await prisma.group.findFirstOrThrow({ where: { id: { not: groupId } }, select: { id: true } })).id;

    const active = await prisma.cycle.findFirst({ where: { groupId, status: "ACTIVE" } });
    meetingId = (
      await prisma.meeting.create({
        data: { groupId, cycleId: active?.id ?? null, title: "Rules test", status: "IN_PROGRESS", scheduledAt: new Date() }
      })
    ).id;
    saverId = (
      await prisma.member.create({ data: { groupId, fullName: "Rule Tester", phone: "254789111001", status: "ACTIVE" } })
    ).id;
    // The seed sets some fund balances directly; bring every fund of this
    // group in step with its ledger so the consistency report starts clean.
    for (const fund of await prisma.fundAccount.findMany({ where: { groupId } })) {
      const rows = await prisma.ledgerEntry.findMany({ where: { fundAccountId: fund.id }, select: { amountCents: true, direction: true } });
      const net = rows.reduce((sum, row) => sum + (row.direction === "CREDIT" ? row.amountCents : -row.amountCents), 0);
      await prisma.fundAccount.update({ where: { id: fund.id }, data: { balanceCents: net } });
    }
    const baseline = await request(app).get(`/api/v1/groups/${groupId}/consistency`).set("Cookie", admin).expect(200);
    baselineLoanGaps = baseline.body.data.loans.disbursementsWithoutLoan;
  }, 90000);

  const batch = (entries: object[], source?: "WEB" | "PHONE") =>
    request(app)
      .post(`/api/v1/groups/${groupId}/meetings/${meetingId}/ledger/batch`)
      .set("Cookie", groupAccount)
      .send({ entries, ...(source ? { source } : {}) });

  describe("the group's rules", () => {
    it("are saved from the phone, with reducing balance and a raised rate cap", async () => {
      const saved = await request(app)
        .put(`/api/v1/groups/${groupId}/policy`)
        .set("Cookie", groupAccount)
        .send({
          loanInterestRateBps: 3000,
          defaultLoanTermMonths: 3,
          interestType: "REDUCING",
          shareValueCents: 20000,
          maxSharesPerMeeting: 5,
          socialFundCents: 5000,
          loanMultiplierBps: 30000
        })
        .expect(200);
      expect(saved.body.data.policy).toMatchObject({
        loanInterestRateBps: 3000,
        interestType: "REDUCING",
        shareValueCents: 20000,
        maxSharesPerMeeting: 5,
        socialFundCents: 5000,
        loanMultiplierBps: 30000
      });
      // The group row follows, so the console and every phone read one value.
      const group = await prisma.group.findUniqueOrThrow({ where: { id: groupId } });
      expect(group.shareValueCents).toBe(20000);
      expect(group.maxSharesPerMemberPerMeeting).toBe(5);
    });

    it("refuses a rate above 50% a month", async () => {
      await request(app)
        .put(`/api/v1/groups/${groupId}/policy`)
        .set("Cookie", groupAccount)
        .send({ loanInterestRateBps: 5001 })
        .expect(400);
    });

    it("travel to a restored phone", async () => {
      const bundle = await buildRestoreBundle(groupId);
      expect(bundle.policy).toMatchObject({
        interestType: "REDUCING",
        shareValueCents: 20000,
        maxSharesPerMeeting: 5,
        socialFundCents: 5000,
        loanMultiplierBps: 30000,
        loanInterestRateBps: 3000,
        defaultLoanTermMonths: 3
      });
    });
  });

  describe("entries typed on the web", () => {
    it("must be whole shares", async () => {
      const refused = await batch([{ type: "SHARE_PURCHASE", memberId: saverId, amountCents: 25000 }], "WEB").expect(422);
      expect(refused.body.error.code).toBe("GROUP_RULE_BROKEN");
    });

    it("may not exceed the most shares a member buys at one meeting", async () => {
      await batch([{ type: "SHARE_PURCHASE", memberId: saverId, amountCents: 80000 }], "WEB").expect(201);
      await batch([{ type: "SHARE_PURCHASE", memberId: saverId, amountCents: 40000 }], "WEB").expect(422);
    });

    it("pay the group's social fund amount", async () => {
      await batch([{ type: "SOCIAL_CONTRIBUTION", memberId: saverId, amountCents: 3000 }], "WEB").expect(422);
      await batch([{ type: "SOCIAL_CONTRIBUTION", memberId: saverId, amountCents: 5000 }], "WEB").expect(201);
    });

    it("may not lend more than the multiplier allows", async () => {
      // Saved 800.00 this meeting; 3x = 2,400.00.
      const refused = await batch([{ type: "INTERNAL_LOAN_DISBURSEMENT", memberId: saverId, amountCents: 250_000 }], "WEB").expect(422);
      expect(refused.body.error.message).toContain("may borrow up to");
      await batch([{ type: "INTERNAL_LOAN_DISBURSEMENT", memberId: saverId, amountCents: 100_000 }], "WEB").expect(201);
    });

    it("are checked on the console's own ledger route too", async () => {
      const fund = await prisma.fundAccount.findFirstOrThrow({ where: { groupId, type: "INTERNAL_LOAN" } });
      await request(app)
        .post(`/api/v1/groups/${groupId}/ledger`)
        .set("Cookie", admin)
        .send({ memberId: saverId, meetingId, fundAccountId: fund.id, type: "SHARE_PURCHASE", amountCents: 10001, direction: "CREDIT", description: "Typed on the console" })
        .expect(422);
    });
  });

  describe("a phone's sync", () => {
    it("is never refused, and a slip shows in the consistency report", async () => {
      const synced = await batch([{ type: "SHARE_PURCHASE", memberId: saverId, amountCents: 30000 }]).expect(201);
      const report = await request(app).get(`/api/v1/groups/${groupId}/consistency`).set("Cookie", admin).expect(200);
      // Every fund still equals its ledger after everything this suite wrote.
      expect(report.body.data.funds.every((fund: { ok: boolean }) => fund.ok), JSON.stringify(report.body.data.funds)).toBe(true);
      // No NEW loan gaps: the seed writes disbursements straight to the ledger
      // (no loan record), which the report rightly lists; nothing here adds to it.
      expect(report.body.data.loans.disbursementsWithoutLoan).toEqual(baselineLoanGaps);
      expect(report.body.data.loans.principalMismatches).toEqual([]);
      const flagged = report.body.data.rules.violations.map((violation: { entryId: string }) => violation.entryId);
      expect(flagged).toContain(synced.body.data[0].id);
    });
  });

  describe("loans", () => {
    it("record the terms the phone agreed, not the group's current default", async () => {
      const agreed = await batch([
        {
          type: "INTERNAL_LOAN_DISBURSEMENT",
          memberId: saverId,
          amountCents: 20_000,
          clientRequestId: `lnd-terms-${Date.now()}`,
          loan: { termMonths: 6, interestRateBps: 800, interestType: "FLAT" }
        }
      ]).expect(201);
      const loan = await prisma.loan.findUniqueOrThrow({ where: { disbursementEntryId: agreed.body.data[0].id } });
      expect(loan).toMatchObject({ termMonths: 6, interestRateBps: 800, interestType: "FLAT" });
    });

    it("keep the interest type they were lent under", async () => {
      // The 1,000.00 loan given out on the web under the group's own rules.
      const loan = await prisma.loan.findFirstOrThrow({ where: { memberId: saverId, principalCents: 100_000 } });
      expect(loan.interestType).toBe("REDUCING");
      expect(loan.interestRateBps).toBe(3000);
    });
  });

  describe("member sign-ins", () => {
    const phone = "254789111222";
    let memberHere: string;
    let memberThere: string;

    beforeAll(async () => {
      memberHere = (await prisma.member.create({ data: { groupId, fullName: "Two Groups Wanjiru", phone, status: "ACTIVE" } })).id;
      memberThere = (
        await prisma.member.create({ data: { groupId: otherGroupId, fullName: "Two Groups Wanjiru", phone: `+${phone}`, status: "ACTIVE" } })
      ).id;
    });

    it("are refused while the group has them off", async () => {
      await request(app).put(`/api/v1/groups/${groupId}/policy`).set("Cookie", groupAccount).send({ memberAccountsEnabled: false }).expect(200);
      const refused = await request(app)
        .post(`/api/v1/groups/${groupId}/members/${memberHere}/account`)
        .set("Cookie", groupAccount)
        .send({ password: "Starting#2026" })
        .expect(403);
      expect(refused.body.error.code).toBe("MEMBER_ACCOUNTS_OFF");
    });

    it("are created once switched on", async () => {
      await request(app).put(`/api/v1/groups/${groupId}/policy`).set("Cookie", groupAccount).send({ memberAccountsEnabled: true }).expect(200);
      const created = await request(app)
        .post(`/api/v1/groups/${groupId}/members/${memberHere}/account`)
        .set("Cookie", groupAccount)
        .send({ password: "Starting#2026" })
        .expect(201);
      expect(created.body.data.linkedExistingLogin).toBe(false);
    });

    it("link the same person's second group to the login they already have", async () => {
      await request(app).put(`/api/v1/groups/${otherGroupId}/policy`).set("Cookie", admin).send({ memberAccountsEnabled: true }).expect(200);
      const linked = await request(app)
        .post(`/api/v1/groups/${otherGroupId}/members/${memberThere}/account`)
        .set("Cookie", admin)
        .send({ password: "Ignored#2026" })
        .expect(200);
      expect(linked.body.data.linkedExistingLogin).toBe(true);
      const logins = await prisma.user.findMany({ where: { phone: { contains: "789111222" } } });
      expect(logins).toHaveLength(1);
    });

    it("show a member every group that allows it", async () => {
      const member = await signIn({ phone: `0${phone.slice(3)}`, password: "Starting#2026" });
      expect(member.response.status).toBe(200);
      const overview = await request(app).get("/api/v1/members/me/overview").set("Cookie", member.cookies).expect(200);
      expect(overview.body.data.groupCount).toBe(2);
    });

    it("drop a group that switches them off, and keep the other", async () => {
      await request(app).put(`/api/v1/groups/${otherGroupId}/policy`).set("Cookie", admin).send({ memberAccountsEnabled: false }).expect(200);
      const member = await signIn({ phone: phone, password: "Starting#2026" });
      expect(member.response.status).toBe(200);
      const overview = await request(app).get("/api/v1/members/me/overview").set("Cookie", member.cookies).expect(200);
      expect(overview.body.data.groupCount).toBe(1);
      expect(overview.body.data.groups[0].member.group.id).toBe(groupId);
    });

    it("list only the open groups as the member's memberships, and refuse switching to a closed one", async () => {
      const member = await signIn({ phone, password: "Starting#2026" });
      const list = await request(app).get("/api/v1/members/me/memberships").set("Cookie", member.cookies).expect(200);
      expect(list.body.data.map((membership: { groupId: string }) => membership.groupId)).toEqual([groupId]);
      await request(app)
        .post("/api/v1/members/me/active-membership")
        .set("Cookie", member.cookies)
        .send({ groupId: otherGroupId })
        .expect(404);
    });

    it("a group that never decided opts in by giving a member a sign-in", async () => {
      const { assertMayCreateMemberLogin, memberAccountsEnabledFor } = await import("../src/services/member-accounts-service");
      // A brand-new group: no policy row, no member logins — never decided.
      const undecided = await prisma.group.create({
        data: { name: "Never Decided Group", code: `IWL-TST-${Date.now()}`, phase: "FORMATION", county: "Kiambu" },
        select: { id: true }
      });
      expect(await memberAccountsEnabledFor(undecided.id)).toBe(false);
      await assertMayCreateMemberLogin(undecided.id, null);
      expect(await memberAccountsEnabledFor(undecided.id)).toBe(true);
    });

    it("refuse to sign in once every group has switched them off", async () => {
      await request(app).put(`/api/v1/groups/${groupId}/policy`).set("Cookie", groupAccount).send({ memberAccountsEnabled: false }).expect(200);
      const member = await signIn({ phone, password: "Starting#2026" });
      expect(member.response.status).toBe(403);
      expect(member.response.body.error.code).toBe("MEMBER_ACCOUNTS_OFF");
      await request(app).put(`/api/v1/groups/${groupId}/policy`).set("Cookie", groupAccount).send({ memberAccountsEnabled: true }).expect(200);
    });

    it("a shared login's password is the member's own, not one group's to reset", async () => {
      await request(app).put(`/api/v1/groups/${otherGroupId}/policy`).set("Cookie", admin).send({ memberAccountsEnabled: true }).expect(200);
      const refused = await request(app)
        .put(`/api/v1/groups/${groupId}/members/${memberHere}/account/password`)
        .set("Cookie", groupAccount)
        .send({ password: "NewStart#2026" })
        .expect(409);
      expect(refused.body.error.code).toBe("SHARED_LOGIN");
    });

    it("a group's own member can have a forgotten password reset", async () => {
      const soloId = (await prisma.member.create({ data: { groupId, fullName: "Solo Saver", phone: "254789111333", status: "ACTIVE" } })).id;
      await request(app).post(`/api/v1/groups/${groupId}/members/${soloId}/account`).set("Cookie", groupAccount).send({ password: "First#2026" }).expect(201);
      await request(app).put(`/api/v1/groups/${groupId}/members/${soloId}/account/password`).set("Cookie", groupAccount).send({ password: "Second#2026" }).expect(200);
      expect((await signIn({ phone: "254789111333", password: "First#2026" })).response.status).toBe(401);
      expect((await signIn({ phone: "254789111333", password: "Second#2026" })).response.status).toBe(200);
    });
  });
});
