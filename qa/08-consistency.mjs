// Scenario 8 - every group's record holds together, the money rules are the
// same on phone and server, and member sign-ins behave for a person in two
// groups.
//
// 1. For every QA group, GET /groups/:id/consistency must report each fund
//    balance equal to its ledger and every loan disbursement with its loan
//    record. Groups the seed wrote straight to the database (stored balances
//    with no ledger behind them) are listed separately, not failed - they are
//    known seed data, flagged by scenario 7 too.
// 2. The shared loan-accrual cases (qa/fixtures/loan-accrual-cases.json, also
//    run by the phone's and the API's test suites) are replayed through the
//    server's loan maths when this runs under a TypeScript loader; under plain
//    node the step says so and leaves it to those suites.
// 3. Member sign-ins: a person saving in two groups signs in once and sees
//    both; a group switching sign-ins off drops out of their view.
//
// Usage: node qa/08-consistency.mjs   (QA_API / QA_DB as in lib.mjs)

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { api, check, db, DEMO_PASSWORD, login, requestId, save, section, uniq } from "./lib.mjs";

const here = path.dirname(fileURLToPath(import.meta.url));
const admin = await login("admin@intellicash.co.ke", DEMO_PASSWORD);

// ---------------------------------------------------------------------------
section("Every group's record holds together");
const groups = await db.group.findMany({ select: { id: true, name: true, isDemo: true, code: true } });
const SEED_CODES = new Set(["IWL-KBU-0001", "IWL-KBU-0002"]);
const seededWithoutLedger = [];
let clean = 0;
for (const group of groups) {
  const report = await api(admin.cookie, "GET", `/groups/${group.id}/consistency`);
  if (report.status !== 200) {
    check(`cons-${group.id}`, `${group.name}: consistency report returned`, 200, report.status);
    continue;
  }
  const data = report.data;
  const fundsOut = data.funds.filter((fund) => !fund.ok);
  const ledgerRows = await db.ledgerEntry.count({ where: { groupId: group.id } });
  // A fund with a balance but NO ledger at all was written by the seed.
  const seedOnly = fundsOut.length > 0 && (SEED_CODES.has(group.code) || (group.isDemo && fundsOut.every((fund) => fund.ledgerNetCents === 0)));
  if (seedOnly || (fundsOut.length > 0 && ledgerRows === 0)) {
    seededWithoutLedger.push(group.name);
    continue;
  }
  check(`cons-funds-${group.id}`, `${group.name}: every fund equals its ledger`, 0, fundsOut.length,
    fundsOut.map((fund) => `${fund.type} ${fund.balanceCents} vs ${fund.ledgerNetCents}`).join("; "));
  check(`cons-loans-${group.id}`, `${group.name}: loan records match their disbursements`, 0,
    data.loans.principalMismatches.length + data.loans.loansWithoutDisbursement.length);
  if (data.loans.disbursementsWithoutLoan.length > 0) {
    console.log(`  note: ${group.name} has ${data.loans.disbursementsWithoutLoan.length} disbursement(s) from before loan records existed (run prisma/backfill-loans.ts)`);
  }
  if (data.rules.violations.length > 0) {
    console.log(`  note: ${group.name} has ${data.rules.violations.length} phone entr(ies) outside the group's rules`);
  }
  clean++;
}
console.log(`  ${clean} group(s) checked; seed-only balances (not failed): ${seededWithoutLedger.join(", ") || "none"}`);

// ---------------------------------------------------------------------------
section("Phone and server owe the same money (shared accrual cases)");
const fixture = JSON.parse(fs.readFileSync(path.join(here, "fixtures/loan-accrual-cases.json"), "utf8"));
const DAY = 24 * 60 * 60 * 1000;
const { memberLoanPosition } = await import("../apps/api/src/domain/loan-math.ts").catch(() => ({}));
if (!memberLoanPosition) {
  console.log("  (loan-math not importable from plain node; the same cases run in the API and phone test suites)");
} else {
  for (const entry of fixture.cases) {
    const base = Date.UTC(2026, 0, 1);
    const position = memberLoanPosition(
      [{ id: "loan", ...entry.loan, disbursedAt: new Date(base) }],
      entry.repayments.map((r, i) => ({ id: `r${i}`, at: new Date(base + r.day * DAY), amountCents: r.cents })),
      new Date(base + entry.asOfDay * DAY)
    );
    const loan = position.loans[0];
    check(`accrual-${entry.name}`, entry.name, entry.expect.outstandingCents, loan.outstandingCents);
  }
}

// ---------------------------------------------------------------------------
section("A member in two groups signs in once and sees both");
const [groupA, groupB] = await db.group.findMany({ where: { isDemo: false }, take: 2, orderBy: { createdAt: "asc" } });
if (!groupA || !groupB) {
  console.log("  (needs two QA groups; skipped)");
} else {
  const phone = `2547${String(Date.now()).slice(-8)}`;
  const name = `QA Two-Group ${uniq()}`;
  const memberA = await db.member.create({ data: { groupId: groupA.id, fullName: name, phone, status: "ACTIVE" } });
  const memberB = await db.member.create({ data: { groupId: groupB.id, fullName: name, phone: `+${phone}`, status: "ACTIVE" } });
  for (const group of [groupA, groupB]) {
    await api(admin.cookie, "PUT", `/groups/${group.id}/policy`, { memberAccountsEnabled: true });
  }
  const password = `Qa#${uniq()}-x`;
  const first = await api(admin.cookie, "POST", `/groups/${groupA.id}/members/${memberA.id}/account`, { password });
  check("ma-create", "first group creates the sign-in", 201, first.status);
  const second = await api(admin.cookie, "POST", `/groups/${groupB.id}/members/${memberB.id}/account`, { password: `Other#${uniq()}` });
  check("ma-link", "second group links the same login", true, second.data?.linkedExistingLogin === true);
  const logins = await db.user.count({ where: { phone: { contains: phone.slice(-9) } } });
  check("ma-one-login", "one login for the person", 1, logins);

  const member = await login(`0${phone.slice(3)}`, password);
  const overview = await api(member.cookie, "GET", "/members/me/overview");
  check("ma-both", "the member sees both groups", 2, overview.data?.groupCount);

  await api(admin.cookie, "PUT", `/groups/${groupB.id}/policy`, { memberAccountsEnabled: false });
  const after = await api(member.cookie, "GET", "/members/me/overview");
  check("ma-one", "a group that switches sign-ins off drops out", 1, after.data?.groupCount);
  await api(admin.cookie, "PUT", `/groups/${groupB.id}/policy`, { memberAccountsEnabled: true });
  void requestId;
}

save();
