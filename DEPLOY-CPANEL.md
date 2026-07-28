# Deploying IntelliCash to cPanel (intellicash.africa)

Target host: LiteSpeed + PHP 8.3, shared IP 199.188.201.16, home `/home/inteekue`.
`intellicash.africa` currently serves a **Laravel** app from `public/`.

Work top to bottom. Steps 0–2 are reversible; step 5 is not.

---

## 0. Back up what is already there

cPanel → Files → Backup → **Download a Full Account Backup**, then move the
archive **off the server**.

```bash
cd /home/inteekue
tar -czf laravel-backup-$(date +%F).tar.gz intellicash.africa/
grep -E '^DB_(DATABASE|USERNAME)' intellicash.africa/.env    # names for the dump
mysqldump -u USER -p DBNAME > laravel-db-$(date +%F).sql
```

Verify the archive opens before continuing. A backup nobody has restored is a
guess.

---

## 1. CONFIRMED: the Laravel app holds real group data

This is a **migration**, not a replacement. Step 5 is blocked until the import
below is built and reconciled. The two systems share no schema — nothing
transfers by copying files.

### 1a. Capture the source

```bash
# Full backup. Keep it. Do not share it — it holds members' names, phone
# numbers and financial records.
mysqldump -u USER -p --single-transaction --routines --triggers \
  --default-character-set=utf8mb4 DBNAME > laravel-full-$(date +%F).sql
gzip laravel-full-$(date +%F).sql

# Prove it restores. An untested dump is a hope, not a backup.
mysql -u USER -p -e "CREATE DATABASE restoretest;"
gunzip -c laravel-full-$(date +%F).sql.gz | mysql -u USER -p restoretest
mysql -u USER -p restoretest -e "SHOW TABLES;"
mysql -u USER -p -e "DROP DATABASE restoretest;"
```

Download it off the server.

### 1b. Structure only, for building the importer

```bash
mysqldump -u USER -p --no-data --skip-comments DBNAME > laravel-schema.sql
mysql -u USER -p DBNAME -e "SELECT table_name, table_rows \
  FROM information_schema.tables WHERE table_schema='DBNAME' \
  ORDER BY table_rows DESC;"
```

Schema and row counts are enough to write the mapping. The data is not needed
and should not leave the server except in the backup above.

### 1c. What the import has to satisfy

Money is the whole point of the system, so the bar is reconciliation, not
"it ran without errors":

- Every group, member, meeting, contribution, loan and repayment accounted for —
  migrated or explicitly listed as skipped, with a reason.
- **Per-group totals match the source exactly**: shares, social fund, fines,
  loans disbursed, loans repaid, outstanding. Not approximately.
- Member identity resolves through the canonical phone form
  (`apps/api/src/lib/phone.ts`), or one person becomes several accounts.
- Nobody ends up holding a roster entry that belongs to someone else — the
  `UserMembership` invariants still apply.
- A member who saves with two groups keeps both.

Run the import into a **copy** first, reconcile, and only then against the
target. Keep the Laravel database untouched throughout; it is the reference
you check against.

---

## 2. The gate: can this host run the platform?

```bash
ls -d /opt/alt/alt-nodejs*/root/usr/bin/node 2>/dev/null
ls /opt/cpanel/ea-nodejs*/bin/node 2>/dev/null
```

The platform requires **Node >= 22.12.0** (`package.json` → `engines`) and a
persistent process. Also check cPanel → Software for **Setup Node.js App**.

| Result | What it means |
| --- | --- |
| Node 22+ and Setup Node.js App present | Viable — continue |
| Node present but < 22.12 | Relax `engines`, re-run both test suites on that version, re-verify. Not free |
| Neither | This host cannot run the platform. Keep the API on Render with a persistent disk and point `intellicash.africa` at it by DNS |

---

## 3. Stage it on a subdomain first

Create `staging.intellicash.africa` (cPanel → Domains → Create A Domain) and
deploy there **while the Laravel app keeps serving**. Nothing below touches the
live site.

```bash
cd /home/inteekue/staging.intellicash.africa
git clone <repo> .            # or upload and extract
npm install --include=dev
npm run db:generate
npm run build:web
```

Setup Node.js App:

- Application root: `staging.intellicash.africa`
- Application URL: `staging.intellicash.africa`
- Startup file: `scripts/render-server.ts` via `npm start`
- Node version: 22.x

Environment variables (Setup Node.js App → Environment variables):

```
NODE_ENV=production
DATABASE_URL=file:/home/inteekue/intellicash-data/intellicash.db
SESSION_SECRET=<generate a long random value>
TRUST_PROXY_HOPS=1
API_PUBLIC_URL=https://staging.intellicash.africa
WEB_ORIGIN=https://staging.intellicash.africa
ENABLE_PAYMENT_NETWORK_CALLS=false
ENABLE_SMS_NETWORK_CALLS=false
```

`mkdir -p /home/inteekue/intellicash-data` first. Keep the database **outside**
the document root — anything under it is web-reachable.

The home directory persists across restarts here, unlike Render's `/tmp`, so
`assertDurableDatabase()` will accept this path. It refuses a relative path or
anything under `/tmp`; that guard is deliberate.

---

## 4. Verify staging before touching production

```bash
curl -s https://staging.intellicash.africa/health
curl -s -o /dev/null -w "%{http_code}\n" https://staging.intellicash.africa/login
```

Then by hand:

- Sign in as each of the seven roles
- A member with two groups sees both and can switch between them
- Sign out, and confirm the session is dead (not just cleared locally)
- Record a contribution, reopen it, confirm the figure persists
- **Restart the app from cPanel and confirm the data is still there** — this is
  the check that catches an ephemeral database

---

## 5. Cut over (irreversible)

**Blocked until the step 1 import is built and reconciled.** The Laravel
database holds live group records; cutting over without a verified migration
destroys them. Only after 0–4 are all green *and* the reconciliation in 1c
balances.

1. Put the Laravel app in maintenance mode.
2. Move it aside — `mv intellicash.africa intellicash.africa.laravel-<date>` —
   rather than deleting it. Disk is cheaper than regret.
3. Repoint `intellicash.africa` in Setup Node.js App to the new application root.
4. Update `API_PUBLIC_URL` and `WEB_ORIGIN` to the live domain, restart.
5. Re-run every check in step 4 against the live domain.

Rollback: move the Laravel directory back and repoint the domain. Keep it for at
least a full savings cycle.

---

## 6. Mobile release

The APK bundles `.env`, so whatever is in it ships to every phone.

```
IC_BASE_URL=https://intellicash.africa/api/v1
```

- Must be **https** and must not be localhost — `ApiConfig.releaseConfigProblem()`
  logs loudly at startup otherwise.
- Do **not** set `IC_API_KEY` for a release build. It is ignored in release
  anyway (an APK is a public artifact and anyone can read the key out of it),
  but leaving it out avoids shipping a live credential at all.

Then `flutter build apk --release`. The 59.5 MB APK built on 20 Jul 2026 has
development configuration baked in and must not be distributed.

---

## Known constraints

- **SQLite under concurrent writes.** Single-writer. Fine for a handful of
  groups; a busy meeting-day across many groups will contend. Postgres is the
  answer if this grows.
- **Schema reaches production via `prisma db push`**, not migrations — no review
  step and no rollback. Worth fixing before there is data you cannot lose.
- **Rate limits are in-memory**, so they are per process. Fine on one instance.

---

## Schema strategy (read before the first deploy)

Production applies **migrations**, not `db push`. The two differ in the one way
that matters once real ledgers exist:

- `prisma migrate deploy` runs the committed SQL in `prisma/migrations/`, in
  order, and records what it applied. Reviewable before it runs; auditable
  after.
- `db push` diffs the live database against the schema and reshapes it in place
  — no review, nothing to roll back to. Fine for seed data, not for a group's
  savings.

`ensure-schema.ts` chooses by environment: `NODE_ENV=production` (or
`PRISMA_SCHEMA_STRATEGY=migrate`) uses `migrate deploy`; anything else keeps
`db push` for local iteration. The safe path is the default — only an explicit
`PRISMA_SCHEMA_STRATEGY=push` opts a production box back into the destructive
one.

**Changing the schema after go-live:** edit `schema.prisma`, run
`npx prisma migrate dev --name <what-changed>` locally, commit the generated
migration alongside the schema change. The deploy applies it. Never hand-edit a
migration that has already run anywhere.

Verified: `migrate deploy` onto an empty database builds all 68 tables, records
the baseline, and the seed and full test suite run against it unchanged.
