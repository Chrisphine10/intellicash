// Scenario 4 — ledger integrity under hostile, awkward and extreme input.
//
// Each check states what a treasurer would be entitled to expect and compares
// it with the API response AND the database. A refusal that still changed a
// fund balance is a failure; so is an accepted request that changed it by the
// wrong amount.
//
// Run:  node qa/04-integrity.mjs        (QA API on :4100, qa.db)

import { api, check, daysAgo, db, DEMO_PASSWORD, login, requestId, save, section, uniq, world } from "./lib.mjs";

const run = uniq();
const admin = await login("admin@intellicash.co.ke", DEMO_PASSWORD);
const programmeId = (await api(admin.cookie, "GET", "/programmes")).data[0].id;
const phone = (n) => `07${String(run).padStart(5, "0").slice(-5)}${String(n).padStart(3, "0")}`;

async function newGroup(label, n) {
  const g = (await api(admin.cookie, "POST", "/groups", { name: `QA ${label} ${run}`, code: `QA-${n}-${run}`, county: "Embu", phase: "INTENSIVE", programmeIds: [programmeId] })).data;
  await api(admin.cookie, "POST", "/users", { name: `QA ${label} Login ${run}`, email: `qa.${label.toLowerCase()}.${run}@intellicash.test`, phone: phone(n), password: "QaIntegrity#2026-x", role: "GROUP_ACCOUNT", groupId: g.id });
  const session = await login(phone(n), "QaIntegrity#2026-x");
  const m1 = (await api(session.cookie, "POST", `/groups/${g.id}/members`, { fullName: `${label} One`, phone: phone(n + 10) })).data.id;
  const m2 = (await api(session.cookie, "POST", `/groups/${g.id}/members`, { fullName: `${label} Two`, phone: phone(n + 11) })).data.id;
  const meeting = (await api(session.cookie, "POST", `/groups/${g.id}/meetings`, { title: `${label} meeting`, scheduledAt: daysAgo(1).toISOString() })).data.id;
  const funds = Object.fromEntries((await db.fundAccount.findMany({ where: { groupId: g.id } })).map((f) => [f.type, f]));
  return { id: g.id, cookie: session.cookie, m1, m2, meeting, funds };
}
const balance = async (groupId, type) => (await db.fundAccount.findFirstOrThrow({ where: { groupId, type } })).balanceCents;
const ledgerCount = (groupId) => db.ledgerEntry.count({ where: { groupId } });
const batch = (g, entries) => api(g.cookie, "POST", `/groups/${g.id}/meetings/${g.meeting}/ledger/batch`, { entries });
const one = (g, type, amountCents, extra = {}) => batch(g, [{ memberId: g.m1, type, amountCents, clientRequestId: requestId(type), ...extra }]);
const direct = (g, body) => api(g.cookie, "POST", `/groups/${g.id}/ledger`, { memberId: g.m1, description: "QA direct entry", clientRequestId: requestId("direct"), ...body });

// ============================================================================
section("I1  The generic ledger route must not let a type, a direction and a fund disagree");
// ============================================================================
const D = await newGroup("Integrity", 70);
await one(D, "SHARE_PURCHASE", 500000); // give the loan fund something to lose
const startLoan = await balance(D.id, "INTERNAL_LOAN");
const startSocial = await balance(D.id, "SOCIAL");
const startRows = await ledgerCount(D.id);

const mismatches = [
  ["I1.01", "a share purchase booked as MONEY OUT of the loan fund", { type: "SHARE_PURCHASE", direction: "DEBIT", fundAccountId: D.funds.INTERNAL_LOAN.id, amountCents: 1000 }],
  ["I1.02", "a loan repayment booked as money OUT", { type: "LOAN_REPAYMENT", direction: "DEBIT", fundAccountId: D.funds.INTERNAL_LOAN.id, amountCents: 1000 }],
  ["I1.03", "a loan disbursement booked as money IN", { type: "INTERNAL_LOAN_DISBURSEMENT", direction: "CREDIT", fundAccountId: D.funds.INTERNAL_LOAN.id, amountCents: 1000 }],
  ["I1.04", "a social contribution paid into the LOAN fund", { type: "SOCIAL_CONTRIBUTION", direction: "CREDIT", fundAccountId: D.funds.INTERNAL_LOAN.id, amountCents: 1000 }],
  ["I1.05", "a welfare expense paid out of the LOAN fund", { type: "WELFARE_EXPENSE", direction: "DEBIT", fundAccountId: D.funds.INTERNAL_LOAN.id, amountCents: 1000 }],
  ["I1.06", "a welfare share-out taken from the LOAN fund", { type: "WELFARE_SHARE_OUT", direction: "DEBIT", fundAccountId: D.funds.INTERNAL_LOAN.id, amountCents: 1000 }]
];
for (const [id, description, body] of mismatches) {
  const r = await direct(D, body);
  check(id, `refused: ${description}`, "refused", r.status >= 400 && r.status < 500 ? "refused" : `accepted (${r.status})`, r.error?.message ?? "");
}
check("I1.07", "…and none of the refused requests moved a fund or wrote a row", { loan: startLoan, social: startSocial, rows: startRows }, { loan: await balance(D.id, "INTERNAL_LOAN"), social: await balance(D.id, "SOCIAL"), rows: await ledgerCount(D.id) });

const otherFund = world.groupC ? (await db.fundAccount.findFirst({ where: { groupId: world.groupC, type: "SOCIAL" } })) : null;
if (otherFund) {
  const before = otherFund.balanceCents;
  const r = await direct(D, { type: "SOCIAL_CONTRIBUTION", direction: "CREDIT", fundAccountId: otherFund.id, amountCents: 1000 });
  check("I1.08", "another group's fund account cannot be used from this group's route", [404, before], [r.status, (await db.fundAccount.findUniqueOrThrow({ where: { id: otherFund.id } })).balanceCents]);
}
const foreignMember = world.cMembers?.c1;
if (foreignMember) {
  const r = await direct(D, { memberId: foreignMember, type: "SOCIAL_CONTRIBUTION", direction: "CREDIT", fundAccountId: D.funds.SOCIAL.id, amountCents: 1000 });
  check("I1.09", "another group's member cannot be credited from this group", 404, r.status);
}
if (world.cMeeting) {
  const r = await direct(D, { meetingId: world.cMeeting, type: "SOCIAL_CONTRIBUTION", direction: "CREDIT", fundAccountId: D.funds.SOCIAL.id, amountCents: 1000 });
  check("I1.10", "another group's meeting cannot be attached to this group's entry", 404, r.status);
}
const good = await direct(D, { type: "SOCIAL_CONTRIBUTION", direction: "CREDIT", fundAccountId: D.funds.SOCIAL.id, amountCents: 2500 });
check("I1.11", "a consistent entry is accepted and moves the fund by exactly its amount", [201, startSocial + 2500], [good.status, await balance(D.id, "SOCIAL")]);

// ============================================================================
section("I2  Extreme and malformed amounts");
// ============================================================================
const E = await newGroup("Extremes", 90);
const MAX = 2147483647; // the largest whole number the database column holds
const rowsE = await ledgerCount(E.id);
const big = await one(E, "SHARE_PURCHASE", MAX);
check("I2.01", "the largest storable amount is accepted", [201, MAX], [big.status, await balance(E.id, "INTERNAL_LOAN")], big.error?.message ?? "");
const overflow = await one(E, "SHARE_PURCHASE", 1);
check("I2.02", "one more cent than the fund can hold is refused politely — not a 500", true, overflow.status >= 400 && overflow.status < 500, `HTTP ${overflow.status} ${overflow.error?.code ?? ""} ${overflow.error?.message ?? ""}`);
check("I2.03", "…and the fund is still exactly at the maximum", MAX, await balance(E.id, "INTERNAL_LOAN"));
const tooBig = await one(E, "SHARE_PURCHASE", MAX + 1);
check("I2.04", "an amount larger than the database can store is refused politely — not a 500", true, tooBig.status >= 400 && tooBig.status < 500, `HTTP ${tooBig.status} ${tooBig.error?.code ?? ""} ${tooBig.error?.message ?? ""}`);
const huge = await one(E, "SHARE_PURCHASE", 1e21);
check("I2.05", "a wildly large number (1e21) is refused politely", true, huge.status >= 400 && huge.status < 500, `HTTP ${huge.status} ${huge.error?.message ?? ""}`);
const asText = await batch(E, [{ memberId: E.m1, type: "SHARE_PURCHASE", amountCents: "100", clientRequestId: requestId("txt") }]);
check("I2.06", "an amount sent as text is refused", 400, asText.status);
const empty = await batch(E, []);
check("I2.07", "an empty batch is refused", 400, empty.status);
const many = await batch(E, Array.from({ length: 251 }, (_, i) => ({ memberId: E.m1, type: "SOCIAL_CONTRIBUTION", amountCents: 1, clientRequestId: requestId(`many${i}`) })));
check("I2.08", "a batch over the 250-entry limit is refused whole", 400, many.status);
check("I2.09", "…and none of it was written", rowsE + 1, await ledgerCount(E.id), "only the one accepted maximum entry");
// Atomicity: a batch whose LAST entry is bad must not commit the earlier good ones.
const socialBefore = await balance(E.id, "SOCIAL");
const mixed = await batch(E, [
  { memberId: E.m1, type: "SOCIAL_CONTRIBUTION", amountCents: 500, clientRequestId: requestId("mixA") },
  { memberId: E.m2, type: "SOCIAL_CONTRIBUTION", amountCents: 700, clientRequestId: requestId("mixB") },
  { memberId: "not-a-member", type: "SOCIAL_CONTRIBUTION", amountCents: 900, clientRequestId: requestId("mixC") }
]);
check("I2.10", "a batch with one bad entry is refused", true, mixed.status >= 400, `HTTP ${mixed.status}`);
check("I2.11", "…and the good entries in it were NOT committed (all or nothing)", socialBefore, await balance(E.id, "SOCIAL"));

// ============================================================================
section("I3  Concurrent loans cannot overdraw the loan fund");
// ============================================================================
const F = await newGroup("Race", 110);
await one(F, "SHARE_PURCHASE", 100000); // 1,000.00 in the loan fund
const race = await Promise.all(Array.from({ length: 6 }, (_, i) => one(F, "INTERNAL_LOAN_DISBURSEMENT", 30000, { clientRequestId: requestId(`race${i}`) })));
const accepted = race.filter((r) => r.status === 201).length;
check("I3.01", "six simultaneous 300.00 loans against 1,000.00: exactly three succeed", 3, accepted, race.map((r) => r.status).join(","));
check("I3.02", "…the fund holds the remaining 100.00, never negative", 10000, await balance(F.id, "INTERNAL_LOAN"));
check("I3.03", "…and exactly three loan records exist", 3, await db.loan.count({ where: { groupId: F.id } }));
const ledgerNet = (await db.ledgerEntry.findMany({ where: { groupId: F.id, fundAccount: { type: "INTERNAL_LOAN" } }, select: { direction: true, amountCents: true } })).reduce((s, e) => s + (e.direction === "CREDIT" ? e.amountCents : -e.amountCents), 0);
check("I3.04", "the fund balance equals the sum of its ledger entries (no lost update)", ledgerNet, await balance(F.id, "INTERNAL_LOAN"));

// ============================================================================
section("I4  The same request id must not silently change meaning");
// ============================================================================
const G = await newGroup("Replay", 130);
const key = requestId("replay");
const first = await batch(G, [{ memberId: G.m1, type: "SOCIAL_CONTRIBUTION", amountCents: 1000, clientRequestId: key }]);
const changed = await batch(G, [{ memberId: G.m1, type: "SOCIAL_CONTRIBUTION", amountCents: 9999, clientRequestId: key }]);
check("I4.01", "the first request is recorded once", [201, 1000], [first.status, await balance(G.id, "SOCIAL")]);
check("I4.02", "the same id with a DIFFERENT amount does not move money again", 1000, await balance(G.id, "SOCIAL"), `retry answered ${changed.status}`);
check("I4.03", "…and the retry is answered with the ORIGINAL entry, so the client can see what was really recorded", 1000, changed.data?.[0]?.amountCents ?? changed.data?.amountCents,
  "kept as a silent idempotent replay on purpose: a hard 409 would jam a phone's sync queue on one edited retry (see QA report, design notes)");

// ============================================================================
section("I5  Text in, text out: hostile and unusual strings are stored verbatim and never executed");
// ============================================================================
const H = await newGroup("Text", 150);
const weird = ["<script>alert(1)</script>", "Robert'); DROP TABLE Member;--", "Ünïcödé ñame 🚀", "  padded  "];
const stored = [];
for (const [i, name] of weird.entries()) {
  const r = await api(H.cookie, "POST", `/groups/${H.id}/members`, { fullName: name, phone: phone(180 + i) });
  stored.push([name.trim(), r.status === 201 ? (await db.member.findUniqueOrThrow({ where: { id: r.data.id } })).fullName : `HTTP ${r.status}`]);
}
check("I5.01", "names are stored exactly as entered (trimmed), script tags and quotes included", stored.map(([a]) => a), stored.map(([, b]) => b));
check("I5.02", "the tables are intact after an injection attempt", true, (await db.member.count()) > 0);
const long = await api(H.cookie, "POST", `/groups/${H.id}/members`, { fullName: "N".repeat(5000), phone: phone(190) });
check("I5.03", "a 5,000-character name is refused or bounded, not accepted whole", true, long.status >= 400 || (await db.member.findUniqueOrThrow({ where: { id: long.data.id } })).fullName.length <= 200, `HTTP ${long.status}`);

save();
await db.$disconnect();
