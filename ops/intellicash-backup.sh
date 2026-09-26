#!/usr/bin/env bash
# Backs up IntelliCash: the ledger database, the uploaded photos and documents,
# and the deployed commit. Installed on the server as
# /usr/local/bin/intellicash-backup.sh (see ops/README.md).
#
#   intellicash-backup.sh            a deploy backup (what the deploy workflow runs)
#   intellicash-backup.sh daily      the nightly timer
#   intellicash-backup.sh weekly     the nightly timer on Sundays
#
# Each kind keeps its own history, so a run of deploys can never push out the
# last good nightly copy: 14 daily, 8 weekly, 10 deploy.
#
# A backup is only trusted once it is proved: the copy must pass SQLite's
# integrity check and hold every ledger row the live database had when it was
# taken. A copy that fails is kept for inspection, reported, and the script
# exits non-zero - it is never rotated in as "the latest".
set -euo pipefail

KIND="${1:-deploy}"
case "$KIND" in daily|weekly|deploy) ;; *) echo "usage: $0 [daily|weekly|deploy]" >&2; exit 2 ;; esac

DATA="${INTELLICASH_DATA:-/var/www/intellicash/data}"
APP="${INTELLICASH_APP:-/var/www/intellicash/app}"
ROOT="${INTELLICASH_BACKUPS:-/root/backups}"
DB="$DATA/intellicash.db"
TS="$(date -u +%Y%m%d-%H%M%S)"
# Deploy backups keep the historical name (the deploy workflow and the data
# guard look for intellicash-*); the others say what they are.
if [ "$KIND" = deploy ]; then DEST="$ROOT/intellicash-$TS"; else DEST="$ROOT/intellicash-$KIND-$TS"; fi

command -v sqlite3 >/dev/null 2>&1 || { echo "ERROR: sqlite3 is required (apt install sqlite3); a cp of a live database is not a backup" >&2; exit 1; }
[ -f "$DB" ] || { echo "ERROR: no database at $DB" >&2; exit 1; }

umask 077
mkdir -p "$DEST"
echo "$KIND" > "$DEST/KIND"

# 1. The database, through SQLite's online backup (safe while the app writes).
# Rows counted BEFORE the copy: the ledger is append-only, so the copy must
# hold at least this many.
LIVE_ROWS="$(sqlite3 "$DB" 'SELECT COUNT(*) FROM LedgerEntry;')"
sqlite3 "$DB" ".backup '$DEST/intellicash.db'"

# 2. Uploads: visit photos, avatars, documents. Not in the database, and gone
#    for good if the disk goes.
if [ -d "$DATA/uploads" ]; then
  tar --force-local -C "$DATA" -czf "$DEST/uploads.tar.gz" uploads
fi

# 3. What was running.
git -C "$APP" rev-parse HEAD > "$DEST/GIT_HEAD.txt" 2>/dev/null || echo unknown > "$DEST/GIT_HEAD.txt"
git -C "$APP" status --porcelain > "$DEST/dirty.txt" 2>/dev/null || true

# 4. Prove it.
INTEGRITY="$(sqlite3 "$DEST/intellicash.db" 'PRAGMA integrity_check;' | head -1)"
COPY_ROWS="$(sqlite3 "$DEST/intellicash.db" 'SELECT COUNT(*) FROM LedgerEntry;')"
UPLOADS_OK=yes
if [ -f "$DEST/uploads.tar.gz" ]; then tar --force-local -tzf "$DEST/uploads.tar.gz" >/dev/null || UPLOADS_OK=no; fi
( cd "$DEST" && sha256sum intellicash.db $( [ -f uploads.tar.gz ] && echo uploads.tar.gz ) > SHA256SUMS )

{
  echo "kind=$KIND"
  echo "taken_utc=$TS"
  echo "integrity=$INTEGRITY"
  echo "ledger_rows_live=$LIVE_ROWS"
  echo "ledger_rows_copy=$COPY_ROWS"
  echo "uploads_readable=$UPLOADS_OK"
} > "$DEST/VERIFIED.txt"

if [ "$INTEGRITY" != "ok" ] || [ "$COPY_ROWS" -lt "$LIVE_ROWS" ] || [ "$UPLOADS_OK" != yes ]; then
  echo "BACKUP FAILED VERIFICATION: $DEST" >&2
  cat "$DEST/VERIFIED.txt" >&2
  mv "$DEST" "$DEST.FAILED"
  exit 1
fi

ln -sfn "$DEST" "$ROOT/INTELLICASH_LATEST"

# 5. Retention, per kind. Never the one just written, never the latest link.
keep() {
  local kind="$1" count="$2" dir
  for dir in $(ls -1dt "$ROOT"/intellicash-* 2>/dev/null); do
    [ -d "$dir" ] || continue
    case "$dir" in *.FAILED) continue ;; esac
    local k=deploy
    [ -f "$dir/KIND" ] && k="$(cat "$dir/KIND")"
    [ "$k" = "$kind" ] || continue
    if [ "$count" -gt 0 ]; then count=$((count - 1)); continue; fi
    [ "$dir" = "$DEST" ] && continue
    [ "$(readlink -f "$ROOT/INTELLICASH_LATEST")" = "$(readlink -f "$dir")" ] && continue
    rm -rf -- "$dir"
  done
}
keep daily 14
keep weekly 8
keep deploy 10

echo "Backup written to $DEST"
cat "$DEST/VERIFIED.txt"
ls -l "$DEST"
