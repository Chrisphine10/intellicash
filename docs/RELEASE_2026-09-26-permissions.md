# Permissions, the group page, report names, agents per group (26 Sep 2026, second release)

Released together with phone app **2.6.4 (build 27)**
(`intellicash_mobile/RELEASE_NOTES_2.6.4.md`).

It adds one migration, `20260926120000_group_agents`, which only adds a table
and fills it. It has two one-time data steps:
- a permission correction that runs by itself on start-up;
- a meeting-workflow backfill, run by hand after the deploy.

Both were rehearsed on a copy of production (see "Rehearsal").


## Live: 26 Sep 2026, 14:58 UTC (commit `163df8f`)

- **Deploy run 36249758755.**
  - **Attempt 1:** stopped before touching the server. The SSH handshake was
    reset (`Connection reset by peer`), and the rollback step was a no-op.
  - **Attempt 2:** every step green. Snapshot; the backup script installed;
    a verified backup (`/root/backups/intellicash-20260926-145713`); deploy
    and web rebuild; health; data guard; smoke tests; co-hosted sites
    unharmed.
- **Data guard: GUARD OK** after the migration and again after the backfill.
  - 152 of 152 ledger entries unchanged; every fund equal to its ledger.
  - 27 loans, 54 cycles and 200 members unchanged.
  - Migration `20260926120000_group_agents` applied, making 53 lead agent
    links.
- **Permission correction** ran on the first signed-in requests:
  - LENDER now holds `payments:write` only; the audit event records
    `removed: ["store:write"]`;
  - READ_ONLY had nothing to remove;
  - partners hold no writes.
- **Meeting-workflow backfill:**
  - The dry run listed the same 13 meetings as the rehearsal.
  - `--commit` completed 13, and a second run finds 0.
- **Live smoke test:**
  - `/health` returns 200;
  - the public pages have no sideways scroll, broken images or console
    errors at 390 and 1366 px.
- **Phone app 2.6.4 (27):** built and committed (`806f33a`, pushed). Upload
  `Downloads/IntelliCash-2.6.4-build27.aab` to Play (internal testing
  first). SHA-256 `c3b87dca…6062c5`.
## What people will notice

**Partners, lenders and read-only accounts see groups but cannot change them**
- **They never hold a write permission.** The server drops any write they
  hold, even when a permission row in the database or the console grants one.
  The console now refuses such a grant, and greys out the box with the reason.
- **The only exception is their own organisation's money:** partner wallet
  deposits and programme contributions (`payments:write`).
  - A lender holds it by default.
  - A partner holds it only if an admin grants it.
- **Lenders are now view-only over groups**, as decided on 26 Sep. They no
  longer file loan or store requests in a group's name (`store:write` is
  removed).
- **Group pages no longer show them forms or buttons.** The pages affected:
  - welfare, documents, business, members and votes;
  - visit action items;
  - join requests.

  Each page shows one line: "View only: you can see this group's records but
  not change them."
- **Welfare:** they see the amount, category and date, but not who received
  it or the note. The note is often an illness or a death.

**Who may act for a group.** Only the group's own account or a platform admin
may:
- appoint officials. An agent still adds members and corrects their details.
  An agent's phone that registers someone as an official now registers them as
  an ordinary member;
- issue a member's PIN, or create or reset a member's sign-in. Before, an agent
  could set a member's password, then sign in and vote as them;
- record a resolution, or open or close a poll. Members still vote;
- send meeting codes to members, or prepare a phone to open meetings offline;
- apply for an external loan in the group's name (a member no longer can);
- post a share-out on the web (the same rule as the phone's share-out).

Also:
- Join requests can be filed only by a member's own account.
- Field agents can see store requests, but vetting and financing them is an
  admin's job.
- A group or member login can never hold a platform permission, such as
  managing users, partners, programmes, agents, all groups or approvals.

**The group page** (`/dashboard/groups/:id`)
- **Top of the page:** four money cards from the group's statement:
  - savings this cycle;
  - loan fund cash;
  - loans outstanding, with PAR30;
  - social fund.

  They show the same figures as the financial reports. A partner viewing a
  group with fewer than 5 active members sees why the figures are withheld.
- **"About this group":**
  - county, sub-county, location and programme;
  - village agent, contact person and meeting day;
  - share value, phase and constitution.
- **Links:** the 14 header buttons are now grouped tiles (Money, People,
  Records), showing only the pages the viewer may open.
- **Labels:**
  - the subtitle has spaces ("IWL-KBU-0001 · Intensive · Cycle 1");
  - an unrated group reads "Credit score: not rated yet", not 0;
  - KYC, results and statuses read as words;
  - dates are spelled out;
  - ledger amounts say In or Out.
- **Loading:** one refused section (a lender cannot read meetings) no longer
  blanks the whole page; that card says so and the rest loads.
- **Phone (group account):** the group's pages scroll; before, they were cut
  off. Money shows two across so no figure breaks mid-number, on the dashboard
  too.

**Group dashboard:** its figure cards are now the group's money (the four
cards above), not counts. Members and meetings keep their cards below.

**Reports:** the "FtMA" names are gone.
- The tab is "Programme KPIs".
- The reports are "Programme VSLA KPIs by county", "Programme training and
  linkage KPIs" and "Programme FSC performance KPIs".
- Imported records are labelled "Programme workbook" in every source filter,
  where they used to show the raw code `FTMA_PERFORMANCE`.
- Stored values and table names are unchanged. Production holds no such data.

**Also in this release (built in a parallel session)**
- **Several village agents per group.**
  - A new `GroupAgent` table holds the links; one agent is the lead.
  - `Group.villageAgentId` still mirrors the lead for older phones.
  - The migration makes each group's current agent its lead link: 53 groups
    on production.
  - Every linked agent has the same access to the group.
- **"Shares" wording** on the phone and web where "savings" meant shares.
- **Meetings closed on a phone:**
  - They now report their workflow and unlock keys when they close.
  - Older ones get their workflow completed by the backfill below.
- **A cycle that has shares cannot be closed without its share-out:**
  `POST /groups/:id/cycles/close` answers 409 `SHARE_OUT_REQUIRED`. A cycle
  with no shares still closes.
- **One holder per office.** Appointing a new secretary steps the previous
  one down, and the history is kept.

## Rehearsal on a copy of production (26 Sep 2026)

The copy was taken read-only on the server with the `sqlite3` backup API,
downloaded, and its checksum matched. The server copy was deleted, and the
local copy was deleted after the rehearsal.

**Migration** (`migrate deploy`, migration files with Unix line endings):
- 30 → 31 migrations.
- **GUARD OK:**
  - 152 of 152 ledger entries unchanged;
  - every fund equal to its ledger;
  - 27 loans, 54 cycles and 200 members unchanged.
- No schema drift.
- 53 lead agent links for the 53 groups that have an agent.

**Meeting-workflow backfill** (`prisma/backfill-phone-meeting-workflow.ts`):
- The dry run finds 13 sealed phone meetings.
- `--commit` completes them, and a second run finds 0.
- The guard is still OK. No money moves.

**Permission correction:**
- The lender template ends with `payments:write` only. The audit event
  records `removed: ["store:write"]`.
- Read-only removes nothing.
- The rehearsal found that the generic prune step ran first and removed the
  lender's `store:write` without any record. The correction now runs before
  the prune, so the audit trail says what was removed.

## On deploy

A one-time correction removes stored write permissions from the lender and
read-only templates, and records what it removed as an audit event (marker
`oversight-view-only-2026-09`). On production today:
- the lender template holds `store:write`, which the correction removes;
- read-only holds no writes, so nothing is removed there.

After the deploy:
1. Confirm the audit event and read the templates (read-only).
2. Run the meeting-workflow backfill: a dry run, then `--commit`, using
   `bash apps/api/prisma/run-with-service-env.sh prisma/backfill-phone-meeting-workflow.ts`.
3. Run the data guard's `verify` again.

## Tests

| Check | Result |
|---|---|
| Typecheck, whole repository | clean |
| API: new `oversight-permissions.test.ts` | 12 of 12 |
| API: full suite on a database built from the migrations (final tree, both sessions) | 95 of 95 files, 992 of 992 tests |
| API: permission files rerun after the correction-order fix | 5 files, 41 of 41 |
| Web (vitest), including new group page, welfare and visit tests | 25 files, 209 of 209 |
| Web production build (`NEXT_PUBLIC_API_BASE_URL=/api/v1`) | succeeds |
| Phone (`flutter analyze`, `flutter test`) | no issues; 610 passed, 3 skipped |
| QA scenarios 01–03 on a local server | 342 checks, 0 failing (03 adds "only the group appoints officials / issues PINs") |
| Browser, each role (admin, partner, lender, group account, agent, member) at 1366 and 390 px | no sideways scroll; controls as described above |

## Verification

Run on 26 Sep 2026 against local servers and scratch copies of the test
database before the deploy (see "Live" above for production).
- The screenshots per role are in the session scratchpad.
- The scratch databases were deleted afterwards.

## Still open

- **The raw ledger still names members to partners.** `GET /groups/:id/ledger`
  returns member names and descriptions such as "Mary Njeri bought 5 shares".
  - The group page hides them.
  - The ledger page and the API do not.
  - Strip them for oversight roles, as the reports already do? This changes
    the ledger page for partners.
- **Phone app 2.6.3:**
  - It offers members "apply for an external loan" in the store. The server
    now answers with a plain refusal.
  - The store is off everywhere today; hide the button in the next build.
