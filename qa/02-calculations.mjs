// Scenario 2 — financial calculations, verified independently.
//
// A fresh group ("C") is created so the script owns every posting. It keeps its
// OWN running model of the two funds and of each member's position, computed
// from the postings it makes, and compares that model with what the API, the
// reports and the database say after each step.
//
// Business rules used for the expectations (from the product's own rules, not
// from reading the calculation code):
//   * shares, repayments, social contributions and fines are money IN;
//   * loans, welfare spending and share-out payouts are money OUT;
//   * loan interest is FLAT MONTHLY on the original principal, charged for
//     whole elapsed 30-day months, capped at the loan's term;
//   * repayments clear the OLDEST loan first;
//   * share-out: pro-rata of the pool by shares bought in the cycle, plus an
//     equal split of what remains in the welfare fund, minus what the member
//     still owes (principal + interest).

import { api, check, daysAgo, db, DEMO_PASSWORD, kes, login, requestId, save, saveWorld, section, uniq, world } from "./lib.mjs";

const run = uniq();
const admin = await login("admin@intellicash.co.ke", DEMO_PASSWORD);
const programmeId = (await api(admin.cookie, "GET", "/programmes")).data[0].id;
const agentUser = await db.user.findFirstOrThrow({ where: { email: "agent@intellicash.co.ke" }, select: { villageAgentId: true } });

// ---- group C with a login, six members, and a policy ------------------------------
const phoneOf = (n) => `07${String(run).padStart(5, "0").slice(-5)}${String(n).padStart(3, "0")}`;
const gc = await api(admin.cookie, "POST", "/groups", {
  name: `QA Calc Circle ${run}`, code: `QA-C-${run}`, county: "Embu", phase: "INTENSIVE", programmeIds: [programmeId], villageAgentId: agentUser.villageAgentId
});
const groupC = gc.data.id;
await api(admin.cookie, "POST", "/users", { name: `QA Calc Login ${run}`, email: `qa.calc.${run}@intellicash.test`, phone: phoneOf(50), password: "QaCalcGroup#2026-x", role: "GROUP_ACCOUNT", groupId: groupC });
const gcAccount = await login(phoneOf(50), "QaCalcGroup#2026-x");
const cookie = gcAccount.cookie;
await api(admin.cookie, "PUT", `/groups/${groupC}/policy`, { loanInterestRateBps: 1000, defaultLoanTermMonths: 3 });

const M = {};
for (const [i, name] of ["Calc One", "Calc Two", "Calc Three", "Calc Four", "Calc Five", "Calc Six"].entries()) {
  const r = await api(cookie, "POST", `/groups/${groupC}/members`, { fullName: name, phone: phoneOf(60 + i) });
  M[`c${i + 1}`] = r.data.id;
}
const mtg = (await api(cookie, "POST", `/groups/${groupC}/meetings`, { title: "QA Calc Meeting", scheduledAt: daysAgo(80).toISOString() })).data.id;

// ---- the model -----------------------------------------------------------------------
const RULE = {
  SHARE_PURCHASE: ["INTERNAL_LOAN", +1], LOAN_REPAYMENT: ["INTERNAL_LOAN", +1], INTERNAL_LOAN_DISBURSEMENT: ["INTERNAL_LOAN", -1],
  SOCIAL_CONTRIBUTION: ["SOCIAL", +1], FINE_COLLECTION: ["SOCIAL", +1], WELFARE_EXPENSE: ["SOCIAL", -1],
  SHARE_OUT_PAYOUT: ["INTERNAL_LOAN", -1], WELFARE_SHARE_OUT: ["SOCIAL", -1]
};
const model = { INTERNAL_LOAN: 0, SOCIAL: 0 };
const shares = {}; // member -> shares bought this cycle
const tally = (k, key, v) => { k[key] = (k[key] ?? 0) + v; };

async function post(member, type, amountCents, label) {
  const r = await api(cookie, "POST", `/groups/${groupC}/meetings/${mtg}/ledger/batch`, {
    entries: [{ memberId: M[member], type, amountCents, clientRequestId: requestId(`${label ?? type}-${member}`) }]
  });
  if (r.status === 201) {
    const [fund, sign] = RULE[type];
    model[fund] += sign * amountCents;
    if (type === "SHARE_PURCHASE") tally(shares, member, amountCents);
  }
  return r;
}
async function funds() {
  const rows = await db.fundAccount.findMany({ where: { groupId: groupC }, select: { type: true, balanceCents: true } });
  const by = Object.fromEntries(rows.map((r) => [r.type, r.balanceCents]));
  return { INTERNAL_LOAN: by.INTERNAL_LOAN ?? 0, SOCIAL: by.SOCIAL ?? 0 };
}
async function expectFunds(id, description, note = "") {
  check(id, description, model, await funds(), note);
}
const passbook = async (member) => (await api(cookie, "GET", `/reports/member/${M[member]}`)).data;

// ============================================================================
section("C1  Zero state");
// ============================================================================
await expectFunds("C1.01", "a new group's funds are zero");
const pb0 = await passbook("c1");
check("C1.02", "a member with no transactions has an all-zero passbook",
  { shares: 0, social: 0, fines: 0, loans: 0, outstanding: 0, interest: 0 },
  { shares: pb0.summary.sharesCents, social: pb0.summary.socialCents, fines: pb0.summary.finesCents, loans: pb0.summary.loansReceivedCents, outstanding: pb0.summary.loanOutstandingWithInterestCents, interest: pb0.summary.loanInterestCents });
const emptyPreview = await api(cookie, "POST", `/groups/${groupC}/meetings/${mtg}/share-out/preview`, { poolAmountCents: 100000 });
check("C1.03", "share-out preview with no shares bought does not crash and shares out nothing", [200, 0], [emptyPreview.status, emptyPreview.data?.rows?.length]);

// ============================================================================
section("C2  Savings, social fund and fines: one and many transactions");
// ============================================================================
await post("c1", "SHARE_PURCHASE", 100000);
await expectFunds("C2.01", "one share purchase moves the loan fund by exactly that amount");
await post("c2", "SHARE_PURCHASE", 150000);
await post("c3", "SHARE_PURCHASE", 25000);
await post("c3", "SHARE_PURCHASE", 50000);
await post("c4", "SHARE_PURCHASE", 100000);
await post("c5", "SHARE_PURCHASE", 50000);
await post("c1", "SOCIAL_CONTRIBUTION", 10000);
await post("c2", "SOCIAL_CONTRIBUTION", 5000);
await post("c6", "SOCIAL_CONTRIBUTION", 8000);
await post("c4", "FINE_COLLECTION", 3000);
await expectFunds("C2.02", "many transactions across members: both fund balances equal the model");
const pb3 = await passbook("c3");
check("C2.03", "two entries by one member add up in their passbook (250.00 + 500.00 = 750.00)", 75000, pb3.summary.sharesCents);
const pb4 = await passbook("c4");
check("C2.04", "fines are counted in total paid in: c4 shares 1,000.00 + fine 30.00", [100000, 3000, 103000], [pb4.summary.sharesCents, pb4.summary.finesCents, pb4.summary.totalPaidInCents]);
const rep = (await api(cookie, "GET", `/reports/group/${groupC}`)).data;
console.log("        group report keys:", Object.keys(rep ?? {}).join(","));

// ============================================================================
section("C3  Loan: disbursement, interest, partial repayment, overpayment");
// ============================================================================
const before = { ...model };
const l1 = await post("c4", "INTERNAL_LOAN_DISBURSEMENT", 200000, "loan1");
check("C3.01", "loan of 2,000.00 is accepted while the fund holds enough", 201, l1.status, l1.error?.message ?? "");
await expectFunds("C3.02", "disbursement reduces the loan fund by the loan");
const loan1 = await db.loan.findFirstOrThrow({ where: { groupId: groupC, memberId: M.c4 } });
check("C3.03", "the loan carries the group's agreed rate and term (10.00% a month, 3 months)", [1000, 3], [loan1.interestRateBps, loan1.termMonths]);
// Age the loan 65 days: two whole months have elapsed.
const d65 = daysAgo(65);
const due = new Date(d65); due.setMonth(due.getMonth() + 3);
await db.loan.update({ where: { id: loan1.id }, data: { disbursedAt: d65, dueAt: due } });
let pb = await passbook("c4");
check("C3.04", "flat interest after 65 days: 2,000.00 x 10% x 2 months = 400.00",
  { received: 200000, interest: 40000, owedWithInterest: 240000, ledgerOnly: 200000 },
  { received: pb.summary.loansReceivedCents, interest: pb.summary.loanInterestCents, owedWithInterest: pb.summary.loanOutstandingWithInterestCents, ledgerOnly: pb.summary.loanOutstandingCents });
await post("c4", "LOAN_REPAYMENT", 100000, "rep1");
await expectFunds("C3.05", "a partial repayment increases the loan fund");
pb = await passbook("c4");
check("C3.06", "after repaying 1,000.00 the member owes 1,400.00 (2,400.00 - 1,000.00)", [140000, "ACTIVE"], [pb.summary.loanOutstandingWithInterestCents, pb.loans[0].status]);
await post("c4", "LOAN_REPAYMENT", 200000, "rep2-over");
await expectFunds("C3.07", "an overpayment is recorded in the fund like any other repayment");
pb = await passbook("c4");
check("C3.08", "paying 3,000.00 against 2,400.00 owed: nothing outstanding, 600.00 overpaid, loan closed",
  { outstanding: 0, overpaid: 60000, status: "REPAID" },
  { outstanding: pb.summary.loanOutstandingWithInterestCents, overpaid: pb.loans[0].overpaidCents, status: pb.loans[0].status });

// ============================================================================
section("C4  Two loans: oldest first, and the interest cap at the term");
// ============================================================================
await post("c3", "INTERNAL_LOAN_DISBURSEMENT", 50000, "loanA");
await post("c3", "INTERNAL_LOAN_DISBURSEMENT", 30000, "loanB");
const c3loans = await db.loan.findMany({ where: { groupId: groupC, memberId: M.c3 }, orderBy: { createdAt: "asc" } });
const back = async (loan, days) => {
  const d = daysAgo(days); const dd = new Date(d); dd.setMonth(dd.getMonth() + loan.termMonths);
  await db.loan.update({ where: { id: loan.id }, data: { disbursedAt: d, dueAt: dd } });
};
await back(c3loans[0], 200); // 6 months elapsed, capped at the 3-month term
await back(c3loans[1], 10);  // no whole month yet
pb = await passbook("c3");
const byPrincipal = Object.fromEntries(pb.loans.map((l) => [l.principalCents, l]));
check("C4.01", "interest stops at the term: 500.00 x 10% x 3 months (not 6) = 150.00", 15000, byPrincipal[50000]?.interestCents);
check("C4.02", "a 10-day-old loan has charged no interest", 0, byPrincipal[30000]?.interestCents);
await post("c3", "LOAN_REPAYMENT", 40000, "rep-c3");
pb = await passbook("c3");
const byP = Object.fromEntries(pb.loans.map((l) => [l.principalCents, l]));
check("C4.03", "a 400.00 repayment clears the OLDEST loan first: it owes 250.00, the newer one still 300.00",
  { oldestRepaid: 40000, oldestOwes: 25000, newerRepaid: 0, newerOwes: 30000 },
  { oldestRepaid: byP[50000].repaidCents, oldestOwes: byP[50000].outstandingCents, newerRepaid: byP[30000].repaidCents, newerOwes: byP[30000].outstandingCents });
check("C4.04", "the member's total owed is 550.00 (250.00 + 300.00)", 55000, pb.summary.loanOutstandingWithInterestCents);
await expectFunds("C4.05", "fund after two loans and a repayment equals the model");

// ============================================================================
section("C4b  A settled loan stays settled as time passes");
// ============================================================================
await post("c5", "INTERNAL_LOAN_DISBURSEMENT", 100000, "loan-c5");
const c5loan = await db.loan.findFirstOrThrow({ where: { groupId: groupC, memberId: M.c5 } });
await back(c5loan, 65);          // two whole months: 1,000.00 + 200.00 = 1,200.00 owed
await post("c5", "LOAN_REPAYMENT", 120000, "settle-c5");
pb = await passbook("c5");
check("C4b.01", "repaying exactly what is owed closes the loan", [0, "REPAID"], [pb.summary.loanOutstandingWithInterestCents, pb.loans[0].status]);
// A month goes by: everything that has happened so far moves a month into the past
// (the loan and the repayment together — moving only the loan would change what it
// owed on the day it was paid, which is not what time passing does).
const c5repayment = await db.ledgerEntry.findFirstOrThrow({ where: { groupId: groupC, memberId: M.c5, type: "LOAN_REPAYMENT" } });
await back(c5loan, 95);
await db.ledgerEntry.update({ where: { id: c5repayment.id }, data: { createdAt: daysAgo(30) } });
pb = await passbook("c5");
check("C4b.02", "a month later the settled loan has NOT re-opened with new interest", [0, "REPAID"], [pb.summary.loanOutstandingWithInterestCents, pb.loans[0].status],
  "settled at 1,200.00; a third month of interest must not be added to a closed loan");

// ============================================================================
section("C5  Welfare and the fund guards");
// ============================================================================
const bigLoan = await post("c1", "INTERNAL_LOAN_DISBURSEMENT", model.INTERNAL_LOAN + 1, "toobig");
check("C5.01", "a loan of one cent more than the fund holds is refused, naming the shortfall", ["INSUFFICIENT_LOAN_FUND", 1], [bigLoan.error?.code, bigLoan.error?.details?.shortfallCents]);
await expectFunds("C5.02", "a refused loan changes nothing");
const zeroLoan = await post("c1", "INTERNAL_LOAN_DISBURSEMENT", 0, "zero");
check("C5.03", "a zero-value entry is rejected", 400, zeroLoan.status);
const negative = await post("c1", "SHARE_PURCHASE", -500, "neg");
check("C5.04", "a negative amount is rejected", 400, negative.status);
const decimal = await api(cookie, "POST", `/groups/${groupC}/meetings/${mtg}/ledger/batch`, { entries: [{ memberId: M.c1, type: "SHARE_PURCHASE", amountCents: 100.5, clientRequestId: requestId("dec") }] });
check("C5.05", "a fractional cent is rejected", 400, decimal.status);
await post("c2", "WELFARE_EXPENSE", 6000, "welfare");
await expectFunds("C5.06", "a welfare expense is paid from the social fund");
const overSpend = await post("c2", "WELFARE_EXPENSE", model.SOCIAL + 1, "welfare-over");
check("C5.07", "spending more welfare than the fund holds is refused", 400, overSpend.status, overSpend.error?.code ?? "");
await expectFunds("C5.08", "…and the fund is unchanged by the refusal");
const oneCent = await post("c5", "SHARE_PURCHASE", 1, "onecent");
check("C5.09", "a one-cent entry is accepted at the boundary", 201, oneCent.status);
await expectFunds("C5.10", "one cent moves the fund by exactly one cent");

// ============================================================================
section("C6  Retries and concurrency");
// ============================================================================
const dupId = requestId("dup");
const dupBody = { entries: [{ memberId: M.c2, type: "SOCIAL_CONTRIBUTION", amountCents: 7777, clientRequestId: dupId }] };
const first = await api(cookie, "POST", `/groups/${groupC}/meetings/${mtg}/ledger/batch`, dupBody);
model.SOCIAL += 7777;
const second = await api(cookie, "POST", `/groups/${groupC}/meetings/${mtg}/ledger/batch`, dupBody);
check("C6.01", "re-sending the same entry does not duplicate it", 1, await db.ledgerEntry.count({ where: { groupId: groupC, clientRequestId: dupId } }), `first ${first.status}, retry ${second.status}`);
await expectFunds("C6.02", "…and the fund moved once");
const raceId = requestId("race");
const raceBody = { entries: [{ memberId: M.c2, type: "SOCIAL_CONTRIBUTION", amountCents: 1234, clientRequestId: raceId }] };
const raced = await Promise.all(Array.from({ length: 6 }, () => api(cookie, "POST", `/groups/${groupC}/meetings/${mtg}/ledger/batch`, raceBody)));
model.SOCIAL += 1234;
check("C6.03", "six simultaneous identical requests create exactly one entry", 1, await db.ledgerEntry.count({ where: { groupId: groupC, clientRequestId: raceId } }), `statuses ${raced.map((r) => r.status).join(",")}`);
await expectFunds("C6.04", "…and the fund moved once");
const otherIds = await Promise.all(Array.from({ length: 8 }, (_, i) => post("c6", "SOCIAL_CONTRIBUTION", 100 + i, `par${i}`)));
check("C6.05", "eight simultaneous DIFFERENT entries all land", 8, otherIds.filter((r) => r.status === 201).length);
await expectFunds("C6.06", "…and the fund equals the model (no lost updates)");

// ============================================================================
section("C7  Share-out: pro-rata, welfare split, loans netted");
// ============================================================================
const positions = async () => {
  const out = {};
  for (const k of Object.keys(M)) { const p = await passbook(k); out[k] = { owed: p.summary.loanOutstandingWithInterestCents, loans: p.loans }; }
  return out;
};
const pre = await positions();
const POOL = model.INTERNAL_LOAN;
const eligible = Object.entries(shares).filter(([, v]) => v > 0).map(([k]) => k);
const totalShares = eligible.reduce((s, k) => s + shares[k], 0);
const preview = (await api(cookie, "POST", `/groups/${groupC}/meetings/${mtg}/share-out/preview`, { poolAmountCents: POOL })).data;
check("C7.01", "preview lists exactly the members who bought shares this cycle", eligible.map((k) => M[k]).sort(), preview.rows.map((r) => r.memberId).sort());
const rows = preview.rows;
const exact = (memberId, s) => Number((BigInt(POOL) * BigInt(s)) / BigInt(totalShares));
const byMember = Object.fromEntries(Object.entries(M).map(([k, v]) => [v, k]));
const lastRow = rows[rows.length - 1];
const others = rows.slice(0, -1);
// Largest remainder: every payout is the exact pro-rata share rounded down,
// or down plus one cent for the members with the largest remainders.
check("C7.02", "each member's pro-rata payout is floor(pool x their shares / all shares), or one cent more", true,
  rows.every((r) => {
    const over = r.payoutCents - exact(r.memberId, shares[byMember[r.memberId]]);
    return over === 0 || over === 1;
  }),
  rows.map((r) => `${r.payoutCents} vs ${exact(r.memberId, shares[byMember[r.memberId]])}`).join(", "));
void others;
check("C7.03", "the payouts add up to the pool exactly (every cent allocated)", POOL, rows.reduce((s, r) => s + r.payoutCents, 0));
const lastIdeal = exact(lastRow.memberId, shares[byMember[lastRow.memberId]]);
check("C7.04", "no member is more than one cent from their ideal share", true, lastRow.payoutCents - lastIdeal >= 0 && lastRow.payoutCents - lastIdeal <= 1, `ideal ${lastIdeal}, got ${lastRow.payoutCents}`);
const W = model.SOCIAL;
const base = Math.floor(W / rows.length), rem = W - base * rows.length;
check("C7.05", "welfare left in the fund is split EQUALLY (remainder cents to the earliest), not by shares",
  rows.map((_, i) => base + (i < rem ? 1 : 0)), rows.map((r) => r.welfareCents));
check("C7.06", "loans are netted at what is owed today, interest included",
  rows.map((r) => pre[byMember[r.memberId]].owed), rows.map((r) => r.loanOffsetCents));
check("C7.07", "net payout = payout + welfare - loans owed",
  rows.map((r) => r.payoutCents + r.welfareCents - r.loanOffsetCents), rows.map((r) => r.netPayoutCents));

const totalOwedBefore = Object.values(pre).reduce((s, p) => s + p.owed, 0);
const cashBefore = model.INTERNAL_LOAN + model.SOCIAL;
const postRes = await api(cookie, "POST", `/groups/${groupC}/meetings/${mtg}/share-out/post`, { poolAmountCents: POOL, clientRequestPrefix: requestId("so") });
check("C7.08", "share-out posts", 201, postRes.status, postRes.error?.message ?? "");
if (postRes.status === 201) {
  model.INTERNAL_LOAN += preview.rows.reduce((s, r) => s + r.loanOffsetCents, 0) - POOL;
  model.SOCIAL -= rows.reduce((s, r) => s + r.welfareCents, 0);
  await expectFunds("C7.09", "funds after share-out: pool and welfare paid out, netted loans paid in");
  const cashOut = rows.reduce((s, r) => s + Math.max(0, r.netPayoutCents), 0);
  check("C7.10", "cash conservation: cash in the box before = cash paid out + cash left (ledger funds)", cashBefore, cashOut + model.INTERNAL_LOAN + model.SOCIAL - (rows.reduce((s, r) => s + Math.max(0, -r.netPayoutCents), 0)),
    "members whose loan exceeds their entitlement would pay in; none here");
  const post = await positions();
  check("C7.11", "after share-out nobody is left owing what was netted (the documented rule: never carried forward)",
    Object.fromEntries(Object.keys(M).map((k) => [k, 0])), Object.fromEntries(Object.keys(M).map((k) => [k, post[k].owed])),
    "c3 owed 550.00 across two loans before share-out");
  const c3After = await db.loan.findMany({ where: { groupId: groupC, memberId: M.c3 }, select: { status: true } });
  check("C7.12", "the loan records agree: both of c3's loans are closed after the share-out nets them", ["REPAID", "REPAID"], c3After.map((l) => l.status));
  const payoutRowsBefore = await db.ledgerEntry.count({ where: { groupId: groupC, type: { in: ["SHARE_OUT_PAYOUT", "WELFARE_SHARE_OUT"] } } });
  const again = await api(cookie, "POST", `/groups/${groupC}/meetings/${mtg}/share-out/post`, { poolAmountCents: POOL });
  await expectFunds("C7.13", "a second share-out request moves no money", `status ${again.status}`);
  check("C7.14", "…and writes no further payout rows", payoutRowsBefore, await db.ledgerEntry.count({ where: { groupId: groupC, type: { in: ["SHARE_OUT_PAYOUT", "WELFARE_SHARE_OUT"] } } }));
}

Object.assign(world, { groupC, cCookie: cookie, cMembers: M, cMeeting: mtg, cPhone: phoneOf(50) });
saveWorld();
save();
await db.$disconnect();
