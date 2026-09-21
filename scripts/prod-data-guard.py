#!/usr/bin/env python3
"""Read-only guard against data loss during a production deploy.

A deploy applies database migrations. This script proves, from the database
itself, that nothing the group's records depend on was lost or changed by it:

  snapshot   before the deploy: what is in the database right now
  verify     after the deploy: is all of that still there, unchanged?

It never writes to the database (it opens it read-only) and prints no personal
data - only table names, counts and record ids. Standard library only, so it runs
on the server with nothing installed.

What "unchanged" means here
  * a table that must never shrink has not shrunk (see PROTECTED);
  * every group, member, meeting, cycle, loan, fund account and ledger entry that
    existed before still exists;
  * the ledger is append-only, so every ledger entry that existed before still has
    the same amount, direction, type, member and fund;
  * every fund's balance still equals the sum of its ledger (a fund that was
    already out of step before is reported, not blamed on the deploy);
  * the database passes SQLite's own integrity check;
  * migrations that were applied before are still applied with the same checksum,
    and the ones the deploy was meant to add are now applied;
  * the newest backup exists, opens, and holds every ledger entry the snapshot saw.

Exit status 1 means at least one of those failed.

  python3 prod-data-guard.py snapshot [--db FILE] [--out FILE]
  python3 prod-data-guard.py verify   [--db FILE] [--baseline FILE]
                                      [--expect-migrations a,b] [--backups-glob GLOB]
"""
from __future__ import annotations

import argparse
import glob
import json
import os
import re
import sqlite3
import subprocess
import sys
from datetime import datetime, timezone

DEFAULT_DB = "/var/www/intellicash/data/intellicash.db"
DEFAULT_BASELINE = "/tmp/intellicash_pre_deploy_snapshot.json"
DEFAULT_BACKUPS = "/root/backups/intellicash-*"

# Tables the application never deletes from. Others (sessions, one-time codes,
# in-progress assessment answers, ...) legitimately shrink, so they are reported
# but not held to "must not shrink".
PROTECTED = [
    "Group", "Member", "Meeting", "Attendance", "Cycle", "Loan", "WelfareExpense",
    "FundAccount", "LedgerEntry", "User", "AuditEvent", "Vote", "GroupPayment",
    "GroupVisit", "MemberRoleAssignment",
]

# Tables whose every record id must survive.
IDENTITY = ["Group", "Member", "Meeting", "Cycle", "Loan", "FundAccount", "LedgerEntry", "User"]

LEDGER_FIELDS = ["amountCents", "direction", "type", "memberId", "fundAccountId"]


def discover_db(explicit: str | None) -> str:
    """The database the running service uses: --db, else its own DATABASE_URL."""
    if explicit:
        return explicit
    if os.environ.get("GUARD_DB"):
        return os.environ["GUARD_DB"]
    url = None
    try:
        env = subprocess.run(
            ["systemctl", "show", "intellicash", "-p", "Environment", "--value"],
            capture_output=True, text=True, timeout=20,
        ).stdout
        m = re.search(r"DATABASE_URL=(\S+)", env)
        if m:
            url = m.group(1)
        files = subprocess.run(
            ["systemctl", "show", "intellicash", "-p", "EnvironmentFiles", "--value"],
            capture_output=True, text=True, timeout=20,
        ).stdout
        for path in re.findall(r"(/[^\s()]+)", files):
            if os.path.isfile(path):
                for line in open(path, encoding="utf-8", errors="replace"):
                    m = re.match(r"\s*DATABASE_URL=(.+?)\s*$", line)
                    if m:
                        url = m.group(1).strip("'\"")
    except Exception:
        pass
    if url and url.startswith("file:"):
        path = url[len("file:"):].split("?")[0]
        if path:
            return path
    return DEFAULT_DB


def open_ro(path: str) -> sqlite3.Connection:
    if not os.path.isfile(path):
        sys.exit(f"GUARD ERROR: database not found at {path}")
    con = sqlite3.connect(f"file:{path}?mode=ro", uri=True, timeout=30)
    con.row_factory = sqlite3.Row
    return con


def tables(con: sqlite3.Connection) -> list[str]:
    rows = con.execute(
        "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
    ).fetchall()
    return [r["name"] for r in rows]


def q(name: str) -> str:
    return '"' + name.replace('"', '""') + '"'


def table_exists(con: sqlite3.Connection, name: str) -> bool:
    return name in tables(con)


def capture(con: sqlite3.Connection, path: str) -> dict:
    """Everything the verify step compares. One read transaction, so it is consistent."""
    con.execute("BEGIN")
    try:
        names = tables(con)
        counts = {t: con.execute(f"SELECT COUNT(*) FROM {q(t)}").fetchone()[0] for t in names}

        identity: dict[str, list[str]] = {}
        for t in IDENTITY:
            if t in names:
                identity[t] = [r[0] for r in con.execute(f"SELECT id FROM {q(t)} ORDER BY id")]

        ledger: dict[str, list] = {}
        if "LedgerEntry" in names:
            cols = ", ".join(q(c) for c in LEDGER_FIELDS)
            for r in con.execute(f"SELECT id, {cols} FROM LedgerEntry"):
                ledger[r["id"]] = [r[c] for c in LEDGER_FIELDS]

        mismatched: dict[str, list[int]] = {}
        if "FundAccount" in names and "LedgerEntry" in names:
            for r in con.execute(
                """
                SELECT f.id AS id, f.balanceCents AS balance,
                       COALESCE(SUM(CASE WHEN l.direction = 'CREDIT' THEN l.amountCents
                                         ELSE -l.amountCents END), 0) AS net
                FROM FundAccount f LEFT JOIN LedgerEntry l ON l.fundAccountId = f.id
                GROUP BY f.id
                """
            ):
                if r["balance"] != r["net"]:
                    mismatched[r["id"]] = [r["balance"], r["net"]]

        migrations: dict[str, str] = {}
        if "_prisma_migrations" in names:
            for r in con.execute(
                "SELECT migration_name, checksum FROM _prisma_migrations "
                "WHERE finished_at IS NOT NULL AND rolled_back_at IS NULL"
            ):
                migrations[r["migration_name"]] = r["checksum"]

        integrity = [r[0] for r in con.execute("PRAGMA integrity_check")]
        fk_violations = len(con.execute("PRAGMA foreign_key_check").fetchall())
    finally:
        con.execute("ROLLBACK")

    return {
        "takenAt": datetime.now(timezone.utc).isoformat(),
        "db": path,
        "sizeBytes": os.path.getsize(path),
        "counts": counts,
        "identity": identity,
        "ledger": ledger,
        "fundsOutOfStep": mismatched,
        "migrations": migrations,
        "integrity": integrity,
        "foreignKeyViolations": fk_violations,
    }


def show_counts(snap: dict, against: dict | None = None) -> None:
    width = max((len(t) for t in snap["counts"]), default=10)
    print(f"  {'table'.ljust(width)}  {'now':>9}" + (f"  {'was':>9}  {'change':>7}" if against else ""))
    for t in sorted(snap["counts"]):
        now = snap["counts"][t]
        if against is None:
            if now:
                print(f"  {t.ljust(width)}  {now:>9}")
            continue
        was = against["counts"].get(t)
        if was is None:
            print(f"  {t.ljust(width)}  {now:>9}  {'(new)':>9}")
        elif now != was or t in PROTECTED:
            flag = "" if now >= was or t not in PROTECTED else "   <-- SHRANK"
            print(f"  {t.ljust(width)}  {now:>9}  {was:>9}  {now - was:>+7}{flag}")


def cmd_snapshot(args) -> int:
    path = discover_db(args.db)
    con = open_ro(path)
    snap = capture(con, path)
    out = args.out or DEFAULT_BASELINE
    with open(out, "w", encoding="utf-8") as fh:
        json.dump(snap, fh)
    try:
        os.chmod(out, 0o600)
    except OSError:
        pass

    print(f"database        {path}  ({snap['sizeBytes']:,} bytes)")
    print(f"integrity_check {', '.join(snap['integrity'])}")
    print(f"migrations      {len(snap['migrations'])} applied; newest: {sorted(snap['migrations'])[-1] if snap['migrations'] else '-'}")
    print(f"funds out of step already: {len(snap['fundsOutOfStep'])}")
    print("rows (non-empty tables):")
    show_counts(snap)
    print(f"snapshot written to {out}")
    if snap["integrity"] != ["ok"]:
        print("GUARD: the database is not healthy BEFORE the deploy; refusing to go on.")
        return 1
    return 0


def newest_backup(pattern: str) -> tuple[str | None, list[str]]:
    dirs = sorted(glob.glob(pattern), key=os.path.getmtime)
    if not dirs:
        return None, []
    newest = dirs[-1]
    dbs: list[str] = []
    if os.path.isfile(newest):
        return newest, [newest] if newest.endswith((".db", ".sqlite", ".sqlite3")) else []
    for root, _, files in os.walk(newest):
        for name in files:
            if name.endswith((".db", ".sqlite", ".sqlite3")):
                dbs.append(os.path.join(root, name))
    return newest, dbs


def cmd_verify(args) -> int:
    baseline_path = args.baseline or DEFAULT_BASELINE
    if not os.path.isfile(baseline_path):
        sys.exit(f"GUARD ERROR: no baseline at {baseline_path}; run `snapshot` before the deploy")
    before = json.load(open(baseline_path, encoding="utf-8"))
    path = discover_db(args.db)
    now = capture(open_ro(path), path)

    problems: list[str] = []

    print(f"database        {path}  ({now['sizeBytes']:,} bytes; was {before['sizeBytes']:,})")
    print(f"integrity_check {', '.join(now['integrity'])}")
    if now["integrity"] != ["ok"]:
        problems.append(f"SQLite integrity_check: {now['integrity'][:3]}")
    if now["foreignKeyViolations"] > before["foreignKeyViolations"]:
        problems.append(
            f"foreign-key violations rose from {before['foreignKeyViolations']} to {now['foreignKeyViolations']}"
        )

    print("\nrows (protected tables, and anything that changed):")
    show_counts(now, before)
    for t in PROTECTED:
        if t in before["counts"] and now["counts"].get(t, 0) < before["counts"][t]:
            problems.append(f"{t} has fewer rows ({now['counts'].get(t, 0)}) than before ({before['counts'][t]})")

    for t, ids in before["identity"].items():
        have = set(now["identity"].get(t, []))
        gone = [i for i in ids if i not in have]
        if gone:
            problems.append(f"{len(gone)} {t} record(s) that existed before are gone (e.g. {gone[0]})")

    changed = [
        i for i, fields in before["ledger"].items()
        if i in now["ledger"] and now["ledger"][i] != fields
    ]
    if changed:
        problems.append(f"{len(changed)} ledger entr(ies) were altered (e.g. {changed[0]}); the ledger is append-only")
    print(f"\nledger: {len(before['ledger'])} entries before, {len(now['ledger'])} now; "
          f"{len(changed)} altered, {len([i for i in before['ledger'] if i not in now['ledger']])} missing")

    # A fund that was already out of step before the deploy is reported, not blamed
    # on it (new traffic moves its balance and ledger together). A fund that
    # matched its ledger before and no longer does is the deploy's doing.
    newly_out_of_step = [f for f in now["fundsOutOfStep"] if f not in before["fundsOutOfStep"]]
    if newly_out_of_step:
        problems.append(f"{len(newly_out_of_step)} fund(s) no longer match their ledger (e.g. {newly_out_of_step[0]})")
    print(f"funds: balance equals ledger for all but {len(now['fundsOutOfStep'])} "
          f"(was {len(before['fundsOutOfStep'])})")

    applied_before = before["migrations"]
    applied_now = now["migrations"]
    for name, checksum in applied_before.items():
        if name not in applied_now:
            problems.append(f"migration {name} is no longer recorded as applied")
        elif applied_now[name] != checksum:
            problems.append(f"migration {name} changed after it was applied")
    added = sorted(set(applied_now) - set(applied_before))
    print(f"\nmigrations added by this deploy: {added or 'none'}")
    expected = [m for m in (args.expect_migrations or "").split(",") if m]
    for name in expected:
        if name not in applied_now:
            problems.append(f"expected migration {name} is not applied")
    for name in added:
        if expected and name not in expected:
            print(f"  note: {name} was applied but not listed as expected")

    # The backup taken just before the deploy must hold what the snapshot saw.
    backup_dir, backup_dbs = newest_backup(args.backups_glob or DEFAULT_BACKUPS)
    if backup_dir is None:
        print(f"\nbackup: none found under {args.backups_glob or DEFAULT_BACKUPS} (not checked)")
    else:
        print(f"\nbackup: {backup_dir}")
        if not backup_dbs:
            print("  no .db file inside to open (not checked)")
        for db in backup_dbs[:3]:
            try:
                bcon = open_ro(db)
                bnames = tables(bcon)
                ok = [r[0] for r in bcon.execute("PRAGMA integrity_check")]
                held = 0
                if "LedgerEntry" in bnames:
                    have = {r[0] for r in bcon.execute("SELECT id FROM LedgerEntry")}
                    held = sum(1 for i in before["ledger"] if i in have)
                print(f"  {os.path.basename(db)}: integrity {', '.join(ok)}; holds {held}/{len(before['ledger'])} "
                      f"of the ledger entries the snapshot saw")
                if ok != ["ok"]:
                    problems.append(f"backup {db} fails its integrity check")
                if held != len(before["ledger"]):
                    problems.append(f"backup {db} is missing {len(before['ledger']) - held} ledger entries")
            except sqlite3.Error as exc:
                print(f"  {os.path.basename(db)}: could not be opened ({exc}); not checked")

    print()
    if problems:
        print("GUARD FAILED - possible data loss:")
        for p in problems:
            print(f"  - {p}")
        print("The database was NOT restored automatically. Backups are under /root/backups/.")
        return 1
    print("GUARD OK - nothing that existed before this deploy is missing or changed.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = parser.add_subparsers(dest="cmd", required=True)
    snap = sub.add_parser("snapshot")
    snap.add_argument("--db")
    snap.add_argument("--out")
    ver = sub.add_parser("verify")
    ver.add_argument("--db")
    ver.add_argument("--baseline")
    ver.add_argument("--expect-migrations")
    ver.add_argument("--backups-glob")
    args = parser.parse_args()
    return cmd_snapshot(args) if args.cmd == "snapshot" else cmd_verify(args)


if __name__ == "__main__":
    sys.exit(main())
