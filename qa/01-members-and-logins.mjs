// Scenario 1 — test data, and the member/login model.
//
// A member must be able to exist on the server WITHOUT a login, and a member
// WITH a login must be the same underlying record. Every step is checked
// against the database, not just the API answer.

import { api, check, daysAgo, db, DEMO_PASSWORD, kes, login, requestId, save, saveWorld, section, uniq, world } from "./lib.mjs";

const run = uniq();
const phone = (n) => `07${String(run).padStart(5, "0").slice(-5)}${String(n).padStart(3, "0")}`; // unique per run
const P = { groupA: phone(1), m1: phone(11), m2: phone(12), m3: phone(13), m4: phone(14), m5: phone(15), m7: phone(17), groupB: phone(2), b1: phone(21), b2: phone(22) };
const CANON = (p) => `254${p.slice(1)}`;

// ---- admin session -------------------------------------------------------------
const admin = await login("admin@intellicash.co.ke", DEMO_PASSWORD);
const programmes = await api(admin.cookie, "GET", "/programmes");
const programmeId = programmes.data[0].id;
const agentUser = await db.user.findFirstOrThrow({ where: { email: "agent@intellicash.co.ke" }, select: { villageAgentId: true } });

// ============================================================================
section("S1  Test data: accounts, groups, members");
// ============================================================================

// Group A signs itself up, as the field team does from the app.
const regA = await api(undefined, "POST", "/auth/register", {
  accountType: "GROUP", name: `QA Umoja Savers ${run}`, phone: P.groupA, password: "QaGroupA#2026", county: "Embu"
});
check("S1.01", "group self sign-up succeeds", 201, regA.status);
const groupA = regA.data.groupId;
const groupAAccount = { cookie: regA.cookie, userId: regA.data.id };
check("S1.02", "sign-up created a GROUP linked to the login (never an orphan login)", true, Boolean(groupA));
const dbGroupA = await db.group.findUnique({ where: { id: groupA }, include: { fundAccounts: true } });
check("S1.03", "group row exists with the champion's number and its fund accounts", true,
  dbGroupA?.contactPhone === CANON(P.groupA) && dbGroupA.fundAccounts.length > 0);

// Attach group A to the programme and the demo agent (staff step).
const attach = await api(admin.cookie, "PATCH", `/groups/${groupA}`, {
  programmeIds: [programmeId], villageAgentId: agentUser.villageAgentId, meetingDay: "Wednesday", shareValueCents: 50000
});
check("S1.04", "admin assigns the new group to a programme and a CBT", 200, attach.status);

// Group B created by an admin, with its own group login.
const codeB = `QA-B-${run}`;
const createB = await api(admin.cookie, "POST", "/groups", {
  name: `QA Kilimo Traders ${run}`, code: codeB, county: "Kiambu", phase: "MOBILISATION", programmeIds: [programmeId],
  villageAgentId: agentUser.villageAgentId
});
check("S1.05", "admin creates a group", 201, createB.status, createB.error?.message ?? "");
const groupB = createB.data?.id;
const userB = await api(admin.cookie, "POST", "/users", {
  name: `QA Kilimo Login ${run}`, email: `qa.groupb.${run}@intellicash.test`, phone: P.groupB, password: "QaGroupB#2026-x", role: "GROUP_ACCOUNT", groupId: groupB
});
check("S1.06", "admin creates the group login for group B", 201, userB.status, userB.error?.message ?? "");
const groupBAccount = await login(P.groupB, "QaGroupB#2026-x");
check("S1.07", "group B login signs in by phone and lands on group B", groupB, groupBAccount.user.groupId);

// Policy for group A: 10% a month, 3-month term.
const pol = await api(admin.cookie, "PUT", `/groups/${groupA}/policy`, { loanInterestRateBps: 1000, defaultLoanTermMonths: 3 });
check("S1.08", "group A policy set to 10% flat monthly, 3 months", 200, pol.status, pol.error?.message ?? "");

// Members of group A, entered by the group account — none has a login yet.
async function addMember(cookie, groupId, fullName, phoneNo, role) {
  const r = await api(cookie, "POST", `/groups/${groupId}/members`, { fullName, phone: phoneNo, ...(role ? { role } : {}) });
  return r;
}
const m = {};
for (const [key, name, ph] of [["m1", "Achieng Otieno", P.m1], ["m2", "Baraka Mwangi", P.m2], ["m3", "Cynthia Wanjiru", P.m3], ["m4", "Dennis Kiprop", P.m4], ["m5", "Esther Naliaka", P.m5]]) {
  const r = await addMember(groupAAccount.cookie, groupA, name, ph);
  check(`S1.${key}`, `group account adds member ${name}`, 201, r.status, r.error?.message ?? "");
  m[key] = r.data?.id;
}
const sync6 = await api(groupAAccount.cookie, "POST", `/groups/${groupA}/members/sync`, { fullName: "Farida Name-Only" });
check("S1.m6", "a member with NO phone number can be created (as the app's sync sends them)", 201, sync6.status);
m.m6 = sync6.data?.id;

const b1 = await addMember(groupBAccount.cookie, groupB, "Kamau Bee One", P.b1);
const b2 = await addMember(groupBAccount.cookie, groupB, "Wairimu Bee Two", P.b2);
m.b1 = b1.data?.id; m.b2 = b2.data?.id;
check("S1.b", "group B has two members", [201, 201], [b1.status, b2.status]);

// ============================================================================
section("S2  A member WITHOUT a login is a complete server-side member");
// ============================================================================
for (const key of ["m2", "m4", "m5", "m6"]) {
  const userRows = await db.user.count({ where: { memberId: m[key] } });
  const links = await db.userMembership.count({ where: { memberId: m[key] } });
  check(`S2.${key}.nologin`, `${key}: exists as a member and has no login or membership link`, [1, 0, 0],
    [await db.member.count({ where: { id: m[key], groupId: groupA } }), userRows, links]);
}
const m6row = await db.member.findUnique({ where: { id: m.m6 }, select: { phone: true, status: true } });
check("S2.m6.phone", "the name-only member is stored active with an empty phone (not a made-up number)", { phone: "", status: "ACTIVE" }, m6row);

// Money can be recorded against a member with no login.
const meetingRes = await api(groupAAccount.cookie, "POST", `/groups/${groupA}/meetings`, { title: "QA Meeting 1", scheduledAt: daysAgo(70).toISOString() });
check("S2.meeting", "group account creates a meeting", 201, meetingRes.status, meetingRes.error?.message ?? "");
const meeting1 = meetingRes.data.id;
world.meeting1 = meeting1;

const post = (memberId, type, amountCents, label) =>
  api(groupAAccount.cookie, "POST", `/groups/${groupA}/meetings/${meeting1}/ledger/batch`, {
    entries: [{ memberId, type, amountCents, clientRequestId: requestId(label) }]
  });

// m2 (no login): share purchase KES 1,500 + social KES 200.
const r1 = await post(m.m2, "SHARE_PURCHASE", 150000, "m2-share");
const r2 = await post(m.m2, "SOCIAL_CONTRIBUTION", 20000, "m2-social");
check("S2.tx", "share and social entries are accepted for a member with no login", [201, 201], [r1.status, r2.status]);
const pb2 = await api(groupAAccount.cookie, "GET", `/reports/member/${m.m2}`);
check("S2.passbook", "the group can read that member's passbook: shares 1,500.00 and social 200.00",
  { shares: 150000, social: 20000, paidIn: 170000 },
  { shares: pb2.data?.summary?.sharesCents, social: pb2.data?.summary?.socialCents, paidIn: pb2.data?.summary?.totalPaidInCents });
const nameOnly = await post(m.m6, "SHARE_PURCHASE", 50000, "m6-share");
check("S2.m6.tx", "money can be recorded for the name-only member too", 201, nameOnly.status);
const roster = await api(groupAAccount.cookie, "GET", `/groups/${groupA}/members`);
check("S2.roster", "all six members are on the roster, with and without logins", 6, roster.data?.length);
const dbEntry = await db.ledgerEntry.findFirst({ where: { memberId: m.m2, type: "SHARE_PURCHASE" } });
check("S2.db", "database ledger row: right member, group, type, amount, direction", { memberId: m.m2, groupId: groupA, type: "SHARE_PURCHASE", amountCents: 150000, direction: "CREDIT" },
  dbEntry && { memberId: dbEntry.memberId, groupId: dbEntry.groupId, type: dbEntry.type, amountCents: dbEntry.amountCents, direction: dbEntry.direction });

// A share purchase for m1 BEFORE m1 has a login (history must survive).
await post(m.m1, "SHARE_PURCHASE", 100000, "m1-share-before");
const m1Before = await api(groupAAccount.cookie, "GET", `/reports/member/${m.m1}`);

// ============================================================================
section("S3  A member WITH a login is the same underlying record");
// ============================================================================
const memberCountBefore = await db.member.count({ where: { groupId: groupA } });
const loginM1 = await api(admin.cookie, "POST", "/users", {
  name: "Achieng Otieno", email: `qa.m1.${run}@intellicash.test`, phone: P.m1, password: "QaMemberM1#2026-x", role: "MEMBER", memberId: m.m1
});
check("S3.01", "admin creates a login for an existing member", 201, loginM1.status, loginM1.error?.message ?? "");
check("S3.02", "no duplicate member was created by making the login", memberCountBefore, await db.member.count({ where: { groupId: groupA } }));
const u1 = await db.user.findFirst({ where: { email: `qa.m1.${run}@intellicash.test` }, select: { id: true, memberId: true, groupId: true } });
check("S3.03", "login points at the original member row and group", { memberId: m.m1, groupId: groupA }, u1 && { memberId: u1.memberId, groupId: u1.groupId });
check("S3.04", "membership link row exists", 1, await db.userMembership.count({ where: { userId: u1.id, memberId: m.m1 } }));
const m1Login = await login(P.m1, "QaMemberM1#2026-x");
check("S3.05", "the member signs in by phone and the session carries their member id", m.m1, m1Login.user.memberId);
const me = await api(m1Login.cookie, "GET", "/members/me");
const strip = (o) => o && { summary: o.summary, totals: o.totals, attendance: o.attendance, loans: o.loans };
check("S3.06", "the member's own passbook equals the group's view of them (history kept)",
  JSON.stringify(strip(m1Before.data)), JSON.stringify(strip(me.data)));
check("S3.07", "…and shows the share bought before the login existed (KES 1,000.00)", 100000, me.data?.summary?.sharesCents);
const peek = await api(m1Login.cookie, "GET", `/reports/member/${m.m2}`);
check("S3.08", "a member cannot read another member's passbook (API, not just the UI)", true, [401, 403, 404].includes(peek.status), `status ${peek.status}`);
const dup = await api(admin.cookie, "POST", "/users", {
  name: "Someone Else", email: `qa.dup.${run}@intellicash.test`, password: "QaDuplicate#2026-x", role: "MEMBER", memberId: m.m1
});
check("S3.09", "a second login for the same member is refused", 409, dup.status);
const sameMember = await api(groupAAccount.cookie, "POST", `/groups/${groupA}/members`, { fullName: "Achieng Again", phone: P.m1 });
check("S3.10", "adding a second member on the same phone in the same group is refused", 409, sameMember.status);

// ---- login exists first, member association later --------------------------------
section("S4  Login first, member association later (join request)");
const regM3 = await api(undefined, "POST", "/auth/register", { accountType: "MEMBER", name: "Cynthia Wanjiru", phone: P.m3, password: "QaMemberM3#2026" });
check("S4.01", "member self sign-up creates a login with NO member record", [201, null], [regM3.status, regM3.data?.memberId ?? null]);
const meBefore = await api(regM3.cookie, "GET", "/members/me");
check("S4.02", "before joining, /members/me says so instead of showing anything", 400, meBefore.status, meBefore.error?.message ?? "");
const codeA = dbGroupA.code;
const jr = await api(regM3.cookie, "POST", "/members/me/join-requests", { groupCode: codeA });
check("S4.03", "the member asks to join with the group code", true, [200, 201].includes(jr.status), jr.error?.message ?? `status ${jr.status}`);
const reqId = jr.data?.id;
const noConfirm = await api(groupAAccount.cookie, "POST", `/groups/${groupA}/join-requests/${reqId}/decision`, { decision: "APPROVE" });
check("S4.04", "approving a phone that is already on the roster demands explicit confirmation", "CONFIRM_EXISTING_MEMBER", noConfirm.error?.code);
const rosterBefore = await db.member.count({ where: { groupId: groupA } });
const confirmed = await api(groupAAccount.cookie, "POST", `/groups/${groupA}/join-requests/${reqId}/decision`, { decision: "APPROVE", confirmMemberId: m.m3 });
check("S4.05", "approval with the confirmed member attaches the login to the EXISTING member", 200, confirmed.status, confirmed.error?.message ?? "");
check("S4.06", "no duplicate member was created", rosterBefore, await db.member.count({ where: { groupId: groupA } }));
const u3 = await db.user.findUnique({ where: { id: regM3.data.id }, select: { memberId: true, groupId: true } });
check("S4.07", "the login now points at the original member row", { memberId: m.m3, groupId: groupA }, u3 && { memberId: u3.memberId, groupId: u3.groupId });
const me3 = await api(regM3.cookie, "GET", "/members/me");
check("S4.08", "the member can now open their passbook", 200, me3.status, me3.error?.message ?? "");

// A brand-new person: login first, and no roster entry yet -> approval creates exactly one.
const regM7 = await api(undefined, "POST", "/auth/register", { accountType: "MEMBER", name: "Grace Newcomer", phone: P.m7, password: "QaMemberM7#2026" });
const jr7 = await api(regM7.cookie, "POST", "/members/me/join-requests", { groupCode: codeA });
const rosterBefore7 = await db.member.count({ where: { groupId: groupA } });
const ok7 = await api(groupAAccount.cookie, "POST", `/groups/${groupA}/join-requests/${jr7.data?.id}/decision`, { decision: "APPROVE" });
check("S4.09", "a person with no roster entry is approved", 200, ok7.status, ok7.error?.message ?? "");
check("S4.10", "exactly one new member row was created", rosterBefore7 + 1, await db.member.count({ where: { groupId: groupA } }));
const again = await api(groupAAccount.cookie, "POST", `/groups/${groupA}/join-requests/${jr7.data?.id}/decision`, { decision: "APPROVE" });
check("S4.11", "approving the same request twice is refused (no double member)", 409, again.status);
m.m7 = (await db.user.findUnique({ where: { id: regM7.data.id }, select: { memberId: true } }))?.memberId;

// ---- deactivate / close a login; the member must survive ----------------------------
section("S5  Removing or deactivating a login never removes the member");
const ledgerBefore = await db.ledgerEntry.count({ where: { memberId: m.m1 } });
const totalsBefore = strip((await api(groupAAccount.cookie, "GET", `/reports/member/${m.m1}`)).data);
const suspend = await api(admin.cookie, "PATCH", `/users/${u1.id}`, { status: "SUSPENDED" });
check("S5.01", "admin suspends the login", 200, suspend.status, suspend.error?.message ?? "");
const refused = await api(undefined, "POST", "/auth/login", { phone: P.m1, password: "QaMemberM1#2026-x" });
check("S5.02", "a suspended login cannot sign in, and is told why", ["ACCOUNT_NOT_ACTIVE", 403], [refused.error?.code, refused.status]);
check("S5.03", "member row and history untouched by suspension", [1, ledgerBefore], [await db.member.count({ where: { id: m.m1 } }), await db.ledgerEntry.count({ where: { memberId: m.m1 } })]);
await api(admin.cookie, "PATCH", `/users/${u1.id}`, { status: "ACTIVE" });
const closeRes = await api(admin.cookie, "DELETE", `/users/${u1.id}`, { confirmEmail: `qa.m1.${run}@intellicash.test`, reason: "QA closure" });
check("S5.04", "admin closes the login", 200, closeRes.status, closeRes.error?.message ?? "");
const afterClose = await db.user.findUnique({ where: { id: u1.id }, select: { status: true, memberId: true, phone: true } });
check("S5.05", "the closed login is stripped of identity and detached", { status: "CLOSED", memberId: null, phone: null }, afterClose);
check("S5.06", "the member row still exists in its group", 1, await db.member.count({ where: { id: m.m1, groupId: groupA } }));
check("S5.07", "every transaction is still attached to the member", ledgerBefore, await db.ledgerEntry.count({ where: { memberId: m.m1 } }));
const totalsAfter = strip((await api(groupAAccount.cookie, "GET", `/reports/member/${m.m1}`)).data);
check("S5.08", "the group's view of the member is unchanged", JSON.stringify(totalsBefore), JSON.stringify(totalsAfter));
const oldSession = await api(m1Login.cookie, "GET", "/members/me");
check("S5.09", "the old session no longer works", 401, oldSession.status);
const relogin = await api(admin.cookie, "POST", "/users", {
  name: "Achieng Otieno", email: `qa.m1b.${run}@intellicash.test`, phone: P.m1, password: "QaMemberM1b#2026-x", role: "MEMBER", memberId: m.m1
});
check("S5.10", "a fresh login can be created for the same member afterwards", 201, relogin.status, relogin.error?.message ?? "");
check("S5.11", "…still one member row, not two", 1, await db.member.count({ where: { groupId: groupA, phone: CANON(P.m1) } }));
const m1Again = await login(P.m1, "QaMemberM1b#2026-x");
const me1b = await api(m1Again.cookie, "GET", "/members/me");
check("S5.12", "the new login reads the same history", JSON.stringify(totalsBefore), JSON.stringify(strip(me1b.data)));

Object.assign(world, { run, groupA, groupB, codeA, codeB, meeting1, members: m, phones: P, groupAAccount, groupBCookie: groupBAccount.cookie, programmeId });
world.credentials = {
  admin: ["admin@intellicash.co.ke", DEMO_PASSWORD],
  groupA: [P.groupA, "QaGroupA#2026"], groupB: [P.groupB, "QaGroupB#2026-x"],
  m1: [P.m1, "QaMemberM1b#2026-x"], m3: [P.m3, "QaMemberM3#2026"], m7: [P.m7, "QaMemberM7#2026"]
};
saveWorld();
save();
await db.$disconnect();
