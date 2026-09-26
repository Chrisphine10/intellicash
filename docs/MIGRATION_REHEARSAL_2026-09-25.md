# Migration rehearsal on a copy of production: 25 September 2026

The three migrations waiting to go live were applied to a copy of the live
database. The copy was then checked against itself before the migrations and
against the version of the code running in production. This record holds
counts and ids only: no member names, phone numbers or amounts per person. The
copy has been deleted.

## Method

1. **Copy.** A consistent copy was taken on the server with Python's `sqlite3`
   backup API, never `cp` on a live file. It was downloaded, its checksum
   compared with the server's (match), and the server copy deleted. The service
   was not touched.
2. **Snapshot.** `prod-data-guard.py snapshot` was run on the copy.
3. **Migrate the way production does.** Command:
   `NODE_ENV=production PRISMA_SCHEMA_STRATEGY=migrate tsx prisma/ensure-schema.ts`,
   run against migration files with Unix line endings (the same checksums
   production holds). Then `seed-if-empty`, which printed "Seed skipped".
4. **Verify.**
   - `prod-data-guard.py verify --expect-migrations <all 30>`, with the
     untouched copy as the backup.
   - `prisma migrate diff --exit-code` against the schema.
5. **Old code against new code.** Production's commit `8e97915` ran over the
   unmigrated copy, and this release over the migrated copy. The same loan,
   passbook, restore-bundle, statement, consistency and sign-in figures were
   compared.
6. **Rollback.** Commit `8e97915` was started on the MIGRATED copy. A deploy
   rollback restores code, not the database, so this is the state a failed
   deploy leaves.

## Result

| Check | Result |
|---|---|
| Migrations applied | `20260924100000_meeting_schedule_reminders`, `20260924140000_programme_modules`, `20260924170000_group_rules` (27 → 30) |
| What they do | Add columns and one new table only. Nothing is dropped or rebuilt. Every new NOT NULL column has a default. |
| Data guard | **GUARD OK.** 128 of 128 ledger rows unchanged. Every fund equals its ledger. No record missing. |
| Fixed fields | 25 loans, 54 cycles and 136 members have unchanged group, member, principal and dates. |
| Schema drift | None (`migrate diff`: no difference). |
| Money figures (code-independent SQL) | Funds, member totals, group totals, loans and logins all identical before and after. |
| Restore bundles | Identical for all 8 groups with members. |
| Member sign-ins | All 12 member logins can still sign in. None is refused by the new per-group switch. |
| Rollback | Production code starts and answers `/health` on the migrated copy, and computes the same figures as on the unmigrated one. |

### Differences, each explained

- **One loan, demo group only: +KSh 600 interest.** Its disbursement is
  recorded in a meeting that closed on 4 July but reached the server on
  8 August. Interest now counts from the meeting (the rule for entries
  recorded offline), so one more month has completed.
- **Eight passbooks, demo group only: savings this cycle show KSh 0.** That
  group's first cycle was closed without a share-out, so the new cycle has no
  purchases yet. Cycles, not "since the last payout", define "this cycle"
  (the rule already used by the share-out code: `groups.ts`
  `currentCycleSharesWhere`). No real group has a cycle closed without a
  payout. See *Decision needed* below.

### Problems the rehearsal found, fixed before release

1. **A live group would have had its web entries refused.** Every group row
   carries the schema default of KSh 500 a share, and the new rule checks
   treated it as the group's choice. One real group saves in KSh 50 shares
   (15 of its entries would have been flagged, and web entries refused). The
   rules now come only from what a group actually set
   (`group-rules-service.ts`). A restored phone takes the share value its
   group's own history shows (`restore-bundle-service.ts`
   `shareValueFromHistory`).
2. **Phones never told the server their share rules** when an online loan
   policy already existed. The phone now fills in whatever the server lacks
   (`auto_sync_coordinator.dart` `syncGroupRules`).

## Decision needed (not changed)

A cycle closed on the console **without** a share-out does not carry its
members' shares into the next cycle's savings. The existing share-out code
treats such shares as already settled, so they cannot be paid twice. Only the
demo group is affected today. Before any real group closes a cycle that way,
decide whether "Close cycle" without a payout should be allowed at all.

## Repeating it

Follow `ops/README.md` → *Rehearsing a migration*. The scripts used are in the
session notes. They need nothing beyond the repository and Python.
