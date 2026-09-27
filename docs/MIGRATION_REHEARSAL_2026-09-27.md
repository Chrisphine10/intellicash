# Migration rehearsal on a copy of production: 27 September 2026

**Migration:** `20260926190000_payment_settlement_layer` (online payments and settlement).

**Production at the time:** commit `c470570`, with 31 migrations applied. The migration above is the only new one.

This record holds counts and ids only. The copy was deleted after the run, on the server and locally.

## Method

1. **Copy.** A consistent copy was taken on the server with SQLite's backup API, opening the live database read-only; the service was not touched. It was downloaded, and its checksum matched the server's (`2ae330fd…`). The server copy was then deleted.
2. **Snapshot.** `prod-data-guard.py snapshot` was run on the copy.
3. **Migrate the way production does.** `prisma migrate deploy` was run against the committed migration files with Unix line endings, which gives the same checksums production holds, plus the new migration.
4. **Verify.**
   - `prod-data-guard.py verify --expect-migrations <all 32>`, with the untouched copy as the backup.
   - `prisma migrate diff --exit-code` against the schema.
5. **Figures.** The new code computed every group's consistency report and every member's passbook summary on the copy, before and after migrating (`prisma/rehearsal-figures.ts`, read-only). The two runs were compared.
6. **Rollback.** Every table and column of the unmigrated copy was checked to still exist, with the same type, after migrating. A deploy rollback restores code, not the database, so production's code must still read a migrated database.
7. **Start.** The new server was started in production mode on the migrated copy. `/health` returned 200, and the new admin payment route refused an unauthenticated request with 401.

## Result

| Check | Result |
|---|---|
| Data guard | **GUARD OK.** 166 of 166 ledger entries are unchanged. Every fund equals its ledger. No record is missing. |
| Fixed fields | 27 loans, 54 cycles and 200 members keep the same group, member, principal and dates. |
| Schema drift | None. |
| Integrity | `integrity_check` ok; no foreign-key violations. |
| Fund accounts | 65 → 265. The 200 added are all at zero: the 40 groups that had none (FLOURISH import) now hold all five. |
| Fund totals | Unchanged: loan fund KSh 49,150, social fund KSh 28,020, other funds 0. |
| Money figures | 0 differences across 53 groups and 200 member passbooks. The only change is the new zero-balance funds appearing in the consistency report. |
| Existing payments | 1 row (PENDING, collected into the group's own account). It becomes `state` PROCESSING, with the group amount equal to the amount and no settlement. |
| Rollback | No column of any existing table was dropped or changed. |
| Server start | Healthy on the migrated copy. |

## Notes for the deploy

- **Old pending payment.** The pending payment is old. The settlement loop's stale-payment check will ask the provider about it once (if network calls are on), then mark it EXPIRED. A late confirmation would still be posted.
- **Deploy order.**
  1. Deploy the server (migration applies automatically).
  2. Release phone 2.7.0.
  3. Switch fee rules on.
  4. Automatic settlement stays off until regulatory clearance and Safaricom payout access.
