// Scenario 3 — who may do what, on the API (the UI can only be as strict as this).
//
// Expectations are computed from the ROLE TABLE in packages/shared (the
// documented intent) plus the account's own scope read straight from the
// database — never from what the API answered:
//
//   allowed  =  role holds the permission the operation needs
//               AND the target group is inside the account's scope
//
// Scope, per the platform's rules: IWL_ADMIN and READ_ONLY see every group; a
// GROUP_ACCOUNT its own group; a MEMBER the group they belong to; a
// VILLAGE_AGENT their caseload; a PARTNER_OFFICER the groups of their
// programmes; a LENDER the groups of programmes where their partner is linked
// as lender. Anonymous callers may do nothing.
//
// Every operation is tried against an own group and a foreign group, for every
// account. "Denied" is 401/403/404 (a 404 for a group that is not yours is the
// intended, non-leaking answer); anything else means the request reached the
// handler.
//
// Run:  npx tsx qa/03-permissions.mjs        (QA API on :4100, qa.db)

import { rolePermissions } from "@intellicash/shared";
import { api, check, db, DEMO_PASSWORD, login, requestId, save, section, uniq, world } from "./lib.mjs";

const run = uniq();
// 404 is how the API refuses a group that is not yours (it does not confirm the
// group exists). A 404 that names a missing fund, member or meeting is different:
// the request got past the access check and failed on its own data.
const PAST_ACCESS_404 = new Set(["FUND_ACCOUNT_NOT_FOUND", "MEMBER_NOT_FOUND", "MEETING_NOT_FOUND"]);
const isDenied = (r) => r.status === 401 || r.status === 403 || (r.status === 404 && !PAST_ACCESS_404.has(r.error?.code));

// ---- accounts ------------------------------------------------------------------------
const demo = {
  admin: { role: "IWL_ADMIN", identifier: "admin@intellicash.co.ke", password: DEMO_PASSWORD },
  partner: { role: "PARTNER_OFFICER", identifier: "partner@intellicash.co.ke", password: DEMO_PASSWORD },
  lender: { role: "LENDER", identifier: "lender@intellicash.co.ke", password: DEMO_PASSWORD },
  group: { role: "GROUP_ACCOUNT", identifier: "group@intellicash.co.ke", password: DEMO_PASSWORD },
  member: { role: "MEMBER", identifier: "member@intellicash.co.ke", password: DEMO_PASSWORD },
  agent: { role: "VILLAGE_AGENT", identifier: "agent@intellicash.co.ke", password: DEMO_PASSWORD },
  readonly: { role: "READ_ONLY", identifier: "readonly@intellicash.co.ke", password: DEMO_PASSWORD },
  qaGroupC: { role: "GROUP_ACCOUNT", identifier: world.cPhone, password: "QaCalcGroup#2026-x" }
};

const accounts = {};
for (const [key, a] of Object.entries(demo)) {
  const session = await login(a.identifier, a.password);
  const user = await db.user.findUniqueOrThrow({ where: { id: session.user.id }, select: { id: true, role: true, groupId: true, memberId: true, partnerId: true, villageAgentId: true, member: { select: { groupId: true } } } });
  accounts[key] = { ...a, cookie: session.cookie, user };
}

// ---- groups (all read from the database) ----------------------------------------------
const admin = accounts.admin;
const allGroups = await db.group.findMany({ select: { id: true, name: true, villageAgentId: true, programmeId: true, isDemo: true } });
const links = await db.programmeGroup.findMany({ select: { programmeId: true, groupId: true } });
const groupProgrammes = (groupId) => new Set([allGroups.find((g) => g.id === groupId)?.programmeId, ...links.filter((l) => l.groupId === groupId).map((l) => l.programmeId)].filter(Boolean));
const programmes = await db.programme.findMany({ select: { id: true, partnerId: true, partnerLinks: { select: { partnerId: true, role: true } } } });
const partnerProgrammes = (partnerId, lenderOnly) =>
  new Set(programmes.filter((p) => (lenderOnly ? p.partnerLinks.some((l) => l.partnerId === partnerId && l.role === "LENDER") : p.partnerId === partnerId || p.partnerLinks.some((l) => l.partnerId === partnerId))).map((p) => p.id));

function inScope(account, groupId) {
  const { role, user } = account;
  if (role === "IWL_ADMIN" || role === "READ_ONLY") return true;
  if (role === "GROUP_ACCOUNT") return user.groupId === groupId;
  if (role === "MEMBER") return user.member?.groupId === groupId;
  if (role === "VILLAGE_AGENT") return allGroups.find((g) => g.id === groupId)?.villageAgentId === user.villageAgentId;
  if (role === "PARTNER_OFFICER" || role === "LENDER") {
    const mine = partnerProgrammes(user.partnerId, role === "LENDER");
    return [...groupProgrammes(groupId)].some((p) => mine.has(p));
  }
  return false;
}

const has = (account, perm) => rolePermissions[account.role].includes(perm);
// Offices, PINs, resolutions and meeting keys belong to the group itself: its
// own account or a platform admin (account-scope.ts isGroupSteward).
const isSteward = (account, groupId) =>
  account.role === "IWL_ADMIN" || (account.role === "GROUP_ACCOUNT" && account.user.groupId === groupId);

// One fresh target group for the destructive operations, so nothing here can
// touch the demo groups' books.
const programmeId = (await api(admin.cookie, "GET", "/programmes")).data[0].id;
const target = (await api(admin.cookie, "POST", "/groups", { name: `QA Perm Target ${run}`, code: `QA-P-${run}`, county: "Embu", phase: "INTENSIVE", programmeIds: [programmeId] })).data;

// Per account: an OWN group and a FOREIGN group, chosen by the scope function
// (so the pairing is right whatever the seed looks like).
function pickGroups(account) {
  const groups = allGroups;
  const own = groups.find((g) => inScope(account, g.id) && g.id !== target.id && g.id !== world.groupC) ?? groups.find((g) => inScope(account, g.id));
  const foreign = groups.find((g) => !inScope(account, g.id));
  return { own, foreign };
}

// A meeting and a member exist in every group we touch (created by the admin).
const prepared = new Map();
async function prepare(groupId) {
  if (prepared.has(groupId)) return prepared.get(groupId);
  let meeting = (await db.meeting.findFirst({ where: { groupId }, orderBy: { createdAt: "desc" } }))?.id;
  if (!meeting) meeting = (await api(admin.cookie, "POST", `/groups/${groupId}/meetings`, { title: "QA prepared meeting", scheduledAt: new Date().toISOString() })).data.id;
  let member = (await db.member.findFirst({ where: { groupId, status: "ACTIVE" }, orderBy: { joinedAt: "asc" } }))?.id;
  if (!member) member = (await api(admin.cookie, "POST", `/groups/${groupId}/members`, { fullName: "QA Prepared Member", phone: `07${String(run).padStart(5, "0").slice(-5)}${String(Math.floor(Math.random() * 900) + 100)}` })).data.id;
  const social = (await db.fundAccount.findFirst({ where: { groupId, type: "SOCIAL" } }))?.id ?? null;
  const info = { meeting, member, social, name: allGroups.find((g) => g.id === groupId)?.name ?? "QA Perm Target" };
  prepared.set(groupId, info);
  return info;
}
// The target sits in the first programme (created with programmeIds), so record that
// link — otherwise the expectation would call it foreign to that programme's partner.
allGroups.push({ id: target.id, name: target.name, villageAgentId: null, programmeId, isDemo: false });
links.push({ programmeId, groupId: target.id });

// A group in a programme that belongs to a DIFFERENT partner — the foreign group for the
// partner and lender accounts (every seeded group sits in the seeded partner's programme).
const otherPartner = await db.partner.create({ data: { name: `QA Other Partner ${run}`, type: "NGO" } });
const otherProgramme = await db.programme.create({ data: { partnerId: otherPartner.id, name: `QA Other Programme ${run}` } });
const outsider = (await api(admin.cookie, "POST", "/groups", { name: `QA Outsider Group ${run}`, code: `QA-O-${run}`, county: "Embu", phase: "INTENSIVE", programmeIds: [otherProgramme.id] })).data;
allGroups.push({ id: outsider.id, name: outsider.name, villageAgentId: null, programmeId: otherProgramme.id, isDemo: false });
links.push({ programmeId: otherProgramme.id, groupId: outsider.id });
programmes.push({ id: otherProgramme.id, partnerId: otherPartner.id, partnerLinks: [] });

// ---- operations -----------------------------------------------------------------------
let phoneSeq = 0;
const nextPhone = () => `07${String(run).padStart(5, "0").slice(-5)}${String(500 + phoneSeq++).padStart(3, "0")}`;
const ops = [
  { id: "read-group", perm: "groups:read", call: (c, g) => api(c, "GET", `/groups/${g}`) },
  { id: "read-members", perm: "members:read", call: (c, g) => api(c, "GET", `/groups/${g}/members`) },
  { id: "read-ledger", perm: "ledger:read", call: (c, g) => api(c, "GET", `/groups/${g}/ledger`) },
  { id: "read-group-report", perm: "groups:read", call: (c, g) => api(c, "GET", `/reports/group/${g}`) },
  { id: "read-member-passbook", perm: "members:read", call: async (c, g) => api(c, "GET", `/reports/member/${(await prepare(g)).member}`) },
  { id: "preview-share-out", perm: "ledger:read", call: async (c, g) => api(c, "POST", `/groups/${g}/meetings/${(await prepare(g)).meeting}/share-out/preview`, { poolAmountCents: 100 }) },
  { id: "create-group", perm: "groups:write", group: false, call: (c) => api(c, "POST", "/groups", { name: `QA P-Group ${run}-${phoneSeq++}`, code: `QA-PG-${run}-${phoneSeq}`, county: "Embu", phase: "INTENSIVE", programmeIds: [programmeId] }) },
  { id: "update-group", perm: "groups:write", call: async (c, g) => api(c, "PATCH", `/groups/${g}`, { name: (await prepare(g)).name }) },
  { id: "add-member", perm: "members:write", call: (c, g) => api(c, "POST", `/groups/${g}/members`, { fullName: "QA Perm Member", phone: nextPhone() }) },
  { id: "add-official", perm: "members:write", steward: true, call: (c, g) => api(c, "POST", `/groups/${g}/members`, { fullName: "QA Perm Treasurer", phone: nextPhone(), role: "TREASURER" }) },
  { id: "issue-member-pin", perm: "members:write", steward: true, call: async (c, g) => api(c, "POST", `/groups/${g}/members/${(await prepare(g)).member}/pin`, {}) },
  { id: "edit-member", perm: "members:write", call: async (c, g) => api(c, "PATCH", `/groups/${g}/members/${(await prepare(g)).member}`, { status: "ACTIVE" }) },
  { id: "create-meeting", perm: "meetings:write", call: (c, g) => api(c, "POST", `/groups/${g}/meetings`, { title: "QA perm meeting", scheduledAt: new Date().toISOString() }) },
  { id: "post-ledger-batch", perm: "ledger:write", call: async (c, g) => { const p = await prepare(g); return api(c, "POST", `/groups/${g}/meetings/${p.meeting}/ledger/batch`, { entries: [{ memberId: p.member, type: "SOCIAL_CONTRIBUTION", amountCents: 100, clientRequestId: requestId("perm") }] }); } },
  { id: "post-ledger-direct", perm: "ledger:write", call: async (c, g) => { const p = await prepare(g); return api(c, "POST", `/groups/${g}/ledger`, { memberId: p.member, fundAccountId: p.social ?? "none", type: "SOCIAL_CONTRIBUTION", amountCents: 100, direction: "CREDIT", description: "QA perm direct", clientRequestId: requestId("permd") }); } },
  // Destructive: only ever pointed at the fresh target group (no shares bought, so it moves nothing).
  { id: "post-share-out", perm: "ledger:write", onlyTarget: true, call: async (c, g) => { const p = await prepare(g); return api(c, "POST", `/groups/${g}/meetings/${p.meeting}/share-out/post`, { poolAmountCents: 100, clientRequestPrefix: requestId("permso") }); } }
];

// ---- run the matrix ----------------------------------------------------------------------
section("P1  Every operation, every account, own group and foreign group");
const table = [];
const cellValue = (r) => (isDenied(r) ? `deny(${r.status})` : r.status >= 500 ? `ERR(${r.status})` : `ok(${r.status})`);

for (const [key, account] of Object.entries(accounts)) {
  const { own, foreign } = pickGroups(account);
  for (const op of ops) {
    const kinds = op.group === false ? [["n/a", null]] : op.onlyTarget ? [["target", target.id]] : [["own", own?.id], ["foreign", foreign?.id]];
    for (const [kind, groupId] of kinds) {
      if (kind !== "n/a" && !groupId) continue;
      // Destructive op against the target group: only meaningful for accounts that could reach it.
      // Member statements stay inside the group: oversight roles see
      // group-level figures only (reports.ts MEMBER_REPORT_NOT_AVAILABLE).
      const oversight = ["PARTNER_OFFICER", "LENDER", "READ_ONLY"].includes(account.role);
      // A member reads their OWN statement only, never a fellow member's
      // (memberScopeForUser). The prepared record is the group's earliest
      // member, which is the member's own only by chance of seed order.
      const someoneElses =
        op.id === "read-member-passbook" &&
        account.role === "MEMBER" &&
        (await prepare(groupId)).member !== account.user.memberId;
      const expectAllowed =
        has(account, op.perm) &&
        (kind === "n/a" || inScope(account, groupId)) &&
        !(op.id === "read-member-passbook" && oversight) &&
        (!op.steward || isSteward(account, groupId)) &&
        !someoneElses;
      const r = await op.call(account.cookie, groupId);
      const allowed = !isDenied(r);
      table.push({ account: key, role: account.role, op: op.id, kind, expectAllowed, allowed, status: r.status });
      check(`P1.${key}.${op.id}.${kind}`, `${key} (${account.role}) ${op.id} on ${kind} group: ${expectAllowed ? "may" : "may NOT"}`, expectAllowed, allowed, `got ${cellValue(r)}${r.error?.message ? ` — ${r.error.message}` : ""}`);
      if (r.status >= 500) check(`P1.${key}.${op.id}.${kind}.no500`, `${key} ${op.id} ${kind}: never a server error`, true, false, `HTTP ${r.status}`);
    }
  }
}

// ---- anonymous -----------------------------------------------------------------------------
section("P2  No session, no access");
for (const op of ops) {
  const groupId = target.id;
  const r = await op.call(undefined, groupId);
  check(`P2.${op.id}`, `anonymous ${op.id} is refused`, 401, r.status);
}

// ---- credentials & sessions --------------------------------------------------------------------
section("P3  A signed-out or forged session grants nothing");
const forged = await api(`${admin.cookie.split("=")[0]}=not-a-real-session`, "GET", "/groups");
check("P3.01", "a made-up session cookie is refused", true, isDenied(forged), `HTTP ${forged.status}`);

// ---- read-only really is read-only ----------------------------------------------------------------
section("P4  Read-only and reading roles cannot write anywhere");
const writers = table.filter((t) => ["readonly", "lender", "partner"].includes(t.account) && /^(create|update|add|edit|post)/.test(t.op));
check("P4.01", "no write succeeded for readonly / lender / partner accounts", [], writers.filter((t) => t.allowed).map((t) => `${t.account}:${t.op}:${t.kind}`));

// ---- report ------------------------------------------------------------------------------------------------
const cols = ["own", "foreign", "target", "n/a"];
console.log("\nmatrix (ok = reached the handler, deny = 401/403/404):");
for (const op of ops) {
  console.log(`\n  ${op.id}  [needs ${op.perm}]`);
  for (const key of Object.keys(accounts)) {
    const row = cols.map((k) => { const t = table.find((x) => x.account === key && x.op === op.id && x.kind === k); return t ? `${k}:${t.allowed ? "OK " : "-- "}${t.allowed === t.expectAllowed ? "" : "  <== UNEXPECTED"}` : ""; }).filter(Boolean).join("   ");
    console.log(`    ${key.padEnd(9)} ${row}`);
  }
}

// A member reads their own statement (and, above, never a fellow member's).
if (accounts.member?.user.memberId) {
  const ownStatement = await api(accounts.member.cookie, "GET", `/reports/member/${accounts.member.user.memberId}`);
  check("P1.member.own-statement", "a member reads their own statement", 200, ownStatement.status);
}

save();
await db.$disconnect();
