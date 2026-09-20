// Scenario 5 - a phone shares out, and a new phone loads the group back.
//
// Seeds a small group (three members, one past meeting, one loan half repaid) on
// the QA server, with a group login, so the phone can:
//   1. "Load my group"  -> the history arrives (restore bundle),
//   2. record a meeting and share out -> the share-out reaches the server and the
//      cycle closes (POST /groups/:id/share-outs),
//   3. be wiped and load the group again -> the share-out shows in its history.
//
// The numbers are worked out here, by hand, so what the phone and the server end
// up holding can be checked against something other than the code under test.
//
//   past meeting:  Amina 5,000  Baraka 3,000  Chege 2,000 in shares (KES)
//                  Baraka borrows 1,000 (10 % a month, one month), repays 300
//   phone meeting: Amina buys 2 shares (1,000), Baraka 1 share (500)
//   share-out:     capital 11,500 + interest 100 = pool 11,600
//                  Baraka's loan (1,000 + 100 - 300) = 800 is settled from his payout
//
// Usage:  node qa/05-phone-share-out.mjs seed     -> prints the login to use on the phone
//         node qa/05-phone-share-out.mjs check    -> reads the server and prints what it holds

import { api, daysAgo, db, DEMO_PASSWORD, login, requestId, uniq, world, saveWorld } from "./lib.mjs";

const mode = process.argv[2] ?? "seed";

if (mode === "seed") {
  const run = uniq();
  const admin = await login("admin@intellicash.co.ke", DEMO_PASSWORD);
  const programmeId = (await api(admin.cookie, "GET", "/programmes")).data[0].id;
  const agentUser = await db.user.findFirstOrThrow({ where: { email: "agent@intellicash.co.ke" }, select: { villageAgentId: true } });

  const phone = `07${String(run).padStart(5, "0").slice(-5)}300`;
  const password = "QaShareOut#2026-x";
  const group = (
    await api(admin.cookie, "POST", "/groups", {
      name: `Kilimo Phone Circle ${run}`, code: `QA-P-${run}`, county: "Embu", phase: "INTENSIVE", programmeIds: [programmeId], villageAgentId: agentUser.villageAgentId
    })
  ).data;
  await api(admin.cookie, "POST", "/users", { name: `Kilimo Login ${run}`, email: `qa.phone.${run}@intellicash.test`, phone, password, role: "GROUP_ACCOUNT", groupId: group.id });
  const account = await login(phone, password);
  const cookie = account.cookie;
  await api(admin.cookie, "PUT", `/groups/${group.id}/policy`, { loanInterestRateBps: 1000, defaultLoanTermMonths: 1 });

  const members = {};
  for (const [i, name] of ["Amina Wekesa", "Baraka Mutua", "Chege Njoroge"].entries()) {
    const r = await api(cookie, "POST", `/groups/${group.id}/members`, { fullName: name, phone: `07${String(run).padStart(5, "0").slice(-5)}${String(310 + i).padStart(3, "0")}` });
    members[name.split(" ")[0]] = r.data.id;
  }
  const meeting = (await api(cookie, "POST", `/groups/${group.id}/meetings`, { title: "Past meeting", scheduledAt: daysAgo(45).toISOString() })).data.id;
  const post = (memberName, type, amountCents, label) =>
    api(cookie, "POST", `/groups/${group.id}/meetings/${meeting}/ledger/batch`, {
      entries: [{ memberId: members[memberName], type, amountCents, clientRequestId: requestId(`${label}-${memberName}`) }]
    });
  await post("Amina", "SHARE_PURCHASE", 500_000, "shr");
  await post("Baraka", "SHARE_PURCHASE", 300_000, "shr");
  await post("Chege", "SHARE_PURCHASE", 200_000, "shr");
  for (const name of ["Amina", "Baraka", "Chege"]) await post(name, "SOCIAL_CONTRIBUTION", 5_000, "soc");
  const loan = await post("Baraka", "INTERNAL_LOAN_DISBURSEMENT", 100_000, "loan");
  // 40 days old with a one-month term: a full month of interest (100.00) has accrued.
  const created = await db.loan.findFirstOrThrow({ where: { groupId: group.id } });
  await db.loan.update({ where: { id: created.id }, data: { disbursedAt: daysAgo(40), dueAt: daysAgo(10) } });
  await post("Baraka", "LOAN_REPAYMENT", 30_000, "rpy");
  // The cycle began before the (back-dated) meeting and loan: a loan disbursed before
  // its cycle started would belong to an earlier cycle, which is not this story.
  await db.cycle.updateMany({ where: { groupId: group.id }, data: { startedAt: daysAgo(50) } });

  Object.assign(world, { phoneGroup: { id: group.id, name: group.name, phone, password, members } });
  saveWorld();
  console.log(JSON.stringify({ group: group.name, login: phone, password, groupId: group.id, loanStatus: loan.status }, null, 2));
} else {
  const { phoneGroup } = world;
  const g = await db.group.findUniqueOrThrow({ where: { id: phoneGroup.id }, select: { name: true, cycleNumber: true } });
  const cycles = await db.cycle.findMany({ where: { groupId: phoneGroup.id }, orderBy: { number: "asc" }, select: { number: true, status: true, closedByShareOutId: true, notes: true } });
  const entries = await db.ledgerEntry.findMany({ where: { groupId: phoneGroup.id }, orderBy: { createdAt: "asc" }, include: { member: { select: { fullName: true } }, cycle: { select: { number: true } } } });
  const funds = await db.fundAccount.findMany({ where: { groupId: phoneGroup.id }, select: { type: true, balanceCents: true } });
  const loans = await db.loan.findMany({ where: { groupId: phoneGroup.id }, select: { principalCents: true, status: true } });
  const meetings = await db.meeting.findMany({ where: { groupId: phoneGroup.id }, orderBy: { scheduledAt: "asc" }, select: { title: true, status: true, cycle: { select: { number: true } }, _count: { select: { attendance: true, ledgerEntries: true } } } });
  console.log(g.name, "cycle", g.cycleNumber);
  console.log("cycles ", JSON.stringify(cycles));
  console.log("funds  ", funds.map((f) => `${f.type}=${(f.balanceCents / 100).toFixed(2)}`).join(" "));
  console.log("loans  ", JSON.stringify(loans));
  console.log("meetings");
  for (const m of meetings) console.log(`  ${m.title} ${m.status} cycle ${m.cycle?.number} attendance ${m._count.attendance} entries ${m._count.ledgerEntries}`);
  console.log("entries");
  for (const e of entries) console.log(`  c${e.cycle?.number} ${e.type.padEnd(28)} ${e.direction === "CREDIT" ? "+" : "-"}${(e.amountCents / 100).toFixed(2).padStart(10)}  ${e.member?.fullName ?? ""}  ${e.description}`);
}
await db.$disconnect();
