# Operations: backups and restore

Everything here protects one thing: a group's money record. Production keeps it
in one SQLite file, `/var/www/intellicash/data/intellicash.db`, with uploaded
photos and documents beside it in `/var/www/intellicash/data/uploads/`.

## What runs

| When | What | Kept |
|---|---|---|
| Every deploy | `intellicash-backup.sh` (deploy kind), before any code changes | last 10 |
| Nightly 02:45 | `intellicash-backup.timer` → `intellicash-backup.sh daily` | last 14 |
| Sunday 02:45 | the same timer, `weekly` | last 8 |
| On this PC, when you schedule it | `ops/pull-backup.ps1` copies the newest verified backup off the server | last 30 |

Each backup folder (`/root/backups/intellicash-*`) holds:

- `intellicash.db`: SQLite's online `.backup`, safe while the app writes.
- `uploads.tar.gz`: the uploads folder.
- `GIT_HEAD.txt` and `dirty.txt`: what was deployed.
- `SHA256SUMS` and `VERIFIED.txt`: proof the copy was checked.

A backup only becomes `INTELLICASH_LATEST` once it is proved:
- it passes `PRAGMA integrity_check`;
- it holds at least as many ledger rows as the live database had when it started;
- the uploads archive reads back.

A copy that fails is renamed `*.FAILED` and the script exits non-zero. A deploy
then stops before touching anything.

## Installing (once, on the server)

```bash
install -m 755 ops/intellicash-backup.sh /usr/local/bin/intellicash-backup.sh
install -m 644 ops/intellicash-backup.service ops/intellicash-backup.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now intellicash-backup.timer
systemctl start intellicash-backup.service   # one now, to prove it
journalctl -u intellicash-backup.service -n 30
```

## Restoring

Restore deliberately, never as a reflex. A restore throws away everything
recorded after the backup was taken.

1. Pick the backup and check it:
   ```bash
   B=/root/backups/intellicash-daily-YYYYMMDD-HHMMSS
   cat $B/VERIFIED.txt
   (cd $B && sha256sum -c SHA256SUMS)
   ```
2. Keep what is live now, even if it is damaged:
   ```bash
   systemctl stop intellicash.service
   /usr/local/bin/intellicash-backup.sh || true
   cp -a /var/www/intellicash/data/intellicash.db /root/backups/pre-restore-$(date -u +%Y%m%d-%H%M%S).db
   ```
3. Put the backup back:
   ```bash
   install -o intellicash -g intellicash -m 644 $B/intellicash.db /var/www/intellicash/data/intellicash.db
   tar -C /var/www/intellicash/data -xzf $B/uploads.tar.gz
   chown -R intellicash:intellicash /var/www/intellicash/data/uploads
   ```
4. Run the code that matches it. If `GIT_HEAD.txt` is older than the deployed
   code, the service applies any newer migrations on start. That is normal: the
   rehearsal proves they only add.
   ```bash
   systemctl start intellicash.service
   curl -s https://intellicash.co.ke/health
   # The ledger now holds what the backup held.
   sqlite3 /var/www/intellicash/data/intellicash.db 'SELECT COUNT(*) FROM LedgerEntry;'
   grep ledger_rows_copy $B/VERIFIED.txt
   ```
5. Find what the restore took away. Phones do NOT re-send a meeting the server
   already confirmed: anything synced after the backup is still on the phone
   that recorded it, but no longer on the server. List it from the pre-restore
   copy saved in step 2:
   ```bash
   P=/root/backups/pre-restore-*.db    # the copy from step 2
   T=$(grep taken_utc $B/VERIFIED.txt | cut -d= -f2)   # e.g. 20260925-024500
   sqlite3 $P "SELECT g.name, COUNT(*), SUM(l.amountCents)/100.0
               FROM LedgerEntry l JOIN \"Group\" g ON g.id = l.groupId
               WHERE l.createdAt > strftime('%s', substr('$T',1,4)||'-'||substr('$T',5,2)||'-'||substr('$T',7,2)||' '||substr('$T',10,2)||':'||substr('$T',12,2)||':'||substr('$T',14,2)) * 1000
               GROUP BY g.id;"
   ```
   Each group listed must have those meetings re-entered, or recovered from
   the pre-restore copy row by row. Never restore without doing this.

## Rehearsing a migration (before every deploy that adds one)

See `docs/MIGRATION_REHEARSAL_2026-09-25.md` for the method and the last result:
1. Make a consistent copy with Python's `sqlite3` backup API.
2. Take a snapshot with `prod-data-guard.py snapshot --db`.
3. Run `NODE_ENV=production PRISMA_SCHEMA_STRATEGY=migrate tsx prisma/ensure-schema.ts`, using Unix line endings.
4. Check with `prod-data-guard.py verify` and `prisma migrate diff --exit-code`.
5. Run the old and new code over both copies and compare their figures.

Delete the copy afterwards: it holds members' personal data.
