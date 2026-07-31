import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { POLICY_DEFAULTS, policyFor } from "../src/routes/group-policy";

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

describe("group policy", () => {
  let groupId: string;
  let cookies: string[];

  beforeAll(async () => {
    await seedDatabase();
    const group = await prisma.group.findFirst({ orderBy: { createdAt: "asc" } });
    groupId = group!.id;
    cookies = await adminCookies();
    await prisma.groupPolicy.deleteMany({});
  }, 60000);

  it("gives an unconfigured group the defaults rather than null", async () => {
    // The property that keeps every caller simple: there is no "unconfigured"
    // case to handle, so a missing policy cannot become a zero-month loan term.
    const policy = await policyFor(groupId);
    expect(policy.defaultLoanTermMonths).toBe(POLICY_DEFAULTS.defaultLoanTermMonths);
    expect(policy.expenseFundType).toBe(POLICY_DEFAULTS.expenseFundType);
    expect(policy.configured).toBe(false);
  });

  it("defaults reproduce the behaviour that existed before this table", () => {
    // A one-month term and welfare-funded expenses are what the system did
    // before GroupPolicy existed. If these ever change, every group that never
    // opened settings silently changes behaviour.
    expect(POLICY_DEFAULTS.defaultLoanTermMonths).toBe(1);
    expect(POLICY_DEFAULTS.expenseFundType).toBe("SOCIAL");
  });

  it("saves a group's own settings and reports it as configured", async () => {
    const response = await request(app)
      .put(`/api/v1/groups/${groupId}/policy`)
      .set("Cookie", cookies)
      .send({ defaultLoanTermMonths: 3 })
      .expect(200);

    expect(response.body.data.policy.defaultLoanTermMonths).toBe(3);
    expect(response.body.data.policy.configured).toBe(true);
    // Unspecified settings keep their default rather than being blanked.
    expect(response.body.data.policy.expenseFundType).toBe("SOCIAL");
    // The message must say what does NOT change, since repricing existing
    // loans is the thing a treasurer would fear. It now promises the RATE is
    // fixed too, not only the term — both are copied onto the Loan row at
    // disbursement, so neither can be changed after the fact.
    expect(response.body.data.message).toMatch(
      /existing loans keep the term and rate they were agreed with/i
    );
  });

  it("rejects a term of zero months", async () => {
    // A zero-month loan is due the instant it is made.
    await request(app)
      .put(`/api/v1/groups/${groupId}/policy`)
      .set("Cookie", cookies)
      .send({ defaultLoanTermMonths: 0 })
      .expect(400);
  });

  it("stores the group's monthly interest rate", async () => {
    const response = await request(app)
      .put(`/api/v1/groups/${groupId}/policy`)
      .set("Cookie", cookies)
      .send({ loanInterestRateBps: 1000 })
      .expect(200);
    expect(response.body.data.policy.loanInterestRateBps).toBe(1000);
    // Stated as a percentage, because a treasurer does not think in bps.
    expect(response.body.data.message).toMatch(/10\.00% a month/);
  });

  it("refuses a rate high enough to be a typo", async () => {
    // 10000 bps is 100% A MONTH. Someone entering "10000" meaning 10% would,
    // on a flat rate over a 12-month term, bill a member twelve times what
    // they borrowed.
    await request(app)
      .put(`/api/v1/groups/${groupId}/policy`)
      .set("Cookie", cookies)
      .send({ loanInterestRateBps: 10000 })
      .expect(400);
  });

  it("allows an interest-free group", async () => {
    // Plenty of VSLAs lend at no interest, so zero must be a legitimate
    // setting rather than read as "unset".
    const response = await request(app)
      .put(`/api/v1/groups/${groupId}/policy`)
      .set("Cookie", cookies)
      .send({ loanInterestRateBps: 0 })
      .expect(200);
    expect(response.body.data.policy.loanInterestRateBps).toBe(0);
  });

  it("rejects a fund type that is not a real fund", async () => {
    await request(app)
      .put(`/api/v1/groups/${groupId}/policy`)
      .set("Cookie", cookies)
      .send({ expenseFundType: "PETTY_CASH" })
      .expect(400);
  });

  it("rejects an empty update rather than silently doing nothing", async () => {
    const response = await request(app)
      .put(`/api/v1/groups/${groupId}/policy`)
      .set("Cookie", cookies)
      .send({})
      .expect(400);
    expect(response.body.error.code).toBe("NOTHING_TO_UPDATE");
  });

  it("reverting drops back to the defaults", async () => {
    await request(app)
      .delete(`/api/v1/groups/${groupId}/policy`)
      .set("Cookie", cookies)
      .expect(200);

    const policy = await policyFor(groupId);
    expect(policy.configured).toBe(false);
    expect(policy.defaultLoanTermMonths).toBe(POLICY_DEFAULTS.defaultLoanTermMonths);
  });

  it("exposes the policy and the defaults side by side", async () => {
    const response = await request(app)
      .get(`/api/v1/groups/${groupId}/policy`)
      .set("Cookie", cookies)
      .expect(200);

    expect(response.body.data.defaults).toEqual(POLICY_DEFAULTS);
    expect(response.body.data.canConfigure).toBe(true);
  });
});
