// Scenario 7 - the financial reports agree with the ledger, and each role sees
// what it should.
//
// For every non-demo QA group, the current cycle's figures are recomputed here
// straight from the ledger rows (plain SQL-style sums, signed by direction) and
// compared with GET /reports/group/:id as an admin. Groups whose stored fund
// balances do not match their own ledger are listed - the report follows the
// ledger and flags them.
//
// Then the role checks: a partner's portfolio names no member, a group sees its
// own statement, a member statement is refused to a partner.
//
// Usage: node qa/07-reports.mjs

import { api, check, db, DEMO_PASSWORD, login, save, section, world } from "./lib.mjs";

const signed = (rows) => rows.reduce((sum, row) => sum + row.amountCents * (row.direction === "DEBIT" ? -1 : 1), 0);

section("Group statements match the ledger");
const admin = await login("admin@intellicash.co.ke", DEMO_PASSWORD);
const groups = await db.group.findMany({ where: { isDemo: false }, select: { id: true, name: true } });
const notReconciling = [];
let checked = 0;
for (const group of groups) {
  const cycle = await db.cycle.findFirst({ where: { groupId: group.id, status: "ACTIVE" }, orderBy: { number: "desc" } });
  if (!cycle) continue;
  const inCycle = { groupId: group.id, OR: [{ cycleId: cycle.id }, { cycleId: null, createdAt: { gte: cycle.startedAt } }] };
  const shares = signed(await db.ledgerEntry.findMany({ where: { ...inCycle, type: "SHARE_PURCHASE" }, select: { amountCents: true, direction: true } }));
  const fund = async (type) =>
    signed(await db.ledgerEntry.findMany({ where: { groupId: group.id, fundAccount: { type } }, select: { amountCents: true, direction: true } }));
  const loanFund = await fund("INTERNAL_LOAN");
  const socialFund = await fund("SOCIAL");
  const stored = await db.fundAccount.findMany({ where: { groupId: group.id, type: { in: ["INTERNAL_LOAN", "SOCIAL"] } } });
  const storedCash = stored.reduce((sum, account) => sum + account.balanceCents, 0);

  const response = await api(admin.cookie, "GET", `/reports/group/${group.id}`);
  const s = response.data?.statement;
  if (!s) {
    check(`stmt-${group.id}`, `${group.name}: statement returned`, 200, response.status);
    continue;
  }
  checked += 1;
  check(`stmt-shares-${group.id}`, `${group.name}: share capital this cycle`, shares, s.loanFund.sharesCents);
  check(`stmt-loanfund-${group.id}`, `${group.name}: loan fund cash`, loanFund, s.loanFund.closingCents);
  check(`stmt-social-${group.id}`, `${group.name}: social fund`, socialFund, s.socialFund.closingCents);
  check(
    `stmt-equity-${group.id}`,
    `${group.name}: equity = loan fund + owed`,
    s.loanFund.closingCents + s.loans.outstandingCents,
    s.equity.totalCents
  );
  const projected = s.memberRows.reduce((sum, row) => sum + row.projectedShareOutCents, 0);
  if (s.memberRows.some((row) => row.sharesCents > 0)) {
    check(`stmt-split-${group.id}`, `${group.name}: projected share-out adds up to equity`, Math.max(0, s.equity.totalCents), projected);
  }
  if (loanFund + socialFund !== storedCash) {
    notReconciling.push({ group: group.name, ledger: (loanFund + socialFund) / 100, stored: storedCash / 100 });
    check(`stmt-flag-${group.id}`, `${group.name}: flagged as not reconciling`, false, s.cash.reconciles);
  }
}
console.log(`\n${checked} group statements checked.`);
if (notReconciling.length) {
  console.log("Stored fund balances that do not match the ledger (the report follows the ledger):");
  for (const row of notReconciling) console.log(`  ${row.group}: ledger KES ${row.ledger}, stored KES ${row.stored}`);
}

section("Each role sees what it should");
const partner = await login("+254700000002", DEMO_PASSWORD);
const portfolio = await api(partner.cookie, "GET", "/reports/portfolio-financials");
check("role-partner-portfolio", "partner gets the portfolio", 200, portfolio.status);
const memberNames = (await db.member.findMany({ select: { fullName: true } })).map((m) => m.fullName).filter((n) => n.length > 5);
const leaked = memberNames.filter((name) => JSON.stringify(portfolio.body).includes(name));
check("role-partner-no-names", "partner portfolio names no member", [], leaked.slice(0, 5));
const someMember = await db.member.findFirst({ where: { group: { isDemo: false } }, select: { id: true } });
const refused = await api(partner.cookie, "GET", `/reports/member/${someMember.id}`);
check("role-partner-member-refused", "partner is refused a member statement", 403, refused.status);

const adminPortfolio = await api(admin.cookie, "GET", "/reports/portfolio-financials");
const adminIds = new Set(adminPortfolio.data.groups.map((g) => g.groupId));
const demoIds = (await db.group.findMany({ where: { isDemo: true }, select: { id: true } })).map((g) => g.id);
check("role-admin-no-demo", "admin portfolio leaves demo groups out", [], demoIds.filter((id) => adminIds.has(id)));
check("role-admin-all", "admin portfolio covers every real group with a cycle", true, adminPortfolio.data.groups.length >= checked);

if (world.meetingGroup) {
  const group = await login(world.meetingGroup.phone, world.meetingGroup.password);
  const own = await api(group.cookie, "GET", `/reports/group/${world.meetingGroup.id}`);
  check("role-group-own", "a group sees its own statement with members", true, own.status === 200 && own.data.statement.memberRows.length > 0);
  const portfolioForGroup = await api(group.cookie, "GET", "/reports/portfolio-financials");
  check("role-group-no-portfolio", "a group is not given the portfolio", 403, portfolioForGroup.status);
}

save();
await db.$disconnect();
