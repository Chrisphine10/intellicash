#!/usr/bin/env bash
# Runs one prisma/*.ts script with exactly the environment the running
# `intellicash` service has — DATABASE_URL, SMS credentials, feature flags.
#
# A manual shell inherits none of that, which is how a one-off script ends up
# reading the wrong database or reporting SMS as unconfigured when the live
# service is sending fine. Reading the service's own definition avoids both.
#
# Usage (as root on the server):
#   bash apps/api/prisma/run-with-service-env.sh prisma/diagnose-group-access.ts
#   bash apps/api/prisma/run-with-service-env.sh prisma/link-orphan-group-logins.ts APPLY=1
#   bash apps/api/prisma/run-with-service-env.sh prisma/backfill-phone-meeting-status.ts --commit
# Extra KEY=VALUE arguments are passed to the script's environment; arguments
# starting with -- are passed to the script itself.
set -euo pipefail

SCRIPT="${1:?usage: run-with-service-env.sh prisma/<script>.ts [KEY=VALUE ...] [--flag ...]}"
shift
APP_API=/var/www/intellicash/app/apps/api

ENVFILE=$(mktemp)
chmod 600 "$ENVFILE"
trap 'rm -f "$ENVFILE"' EXIT

# Each KEY=VALUE is written single-quoted. systemd reads `KEY=two words` as one
# value; bash sourcing it unquoted ran "words" as a command ("Admin: command
# not found") and cut the value short.
quote_env() {
  local line key value
  while IFS= read -r line; do
    case "$line" in [A-Za-z_]*=*) ;; *) continue ;; esac
    key="${line%%=*}"
    value="${line#*=}"
    # Strip one pair of surrounding quotes, as systemd does.
    if [[ "$value" == \"*\" || "$value" == \'*\' ]]; then value="${value:1:${#value}-2}"; fi
    printf "%s='%s'\n" "$key" "${value//\'/\'\\\'\'}"
  done
}

systemctl show intellicash -p Environment --value | tr ' ' '\n' | grep '=' | quote_env >>"$ENVFILE" || true
for file in $(systemctl show intellicash -p EnvironmentFiles --value | sed 's/ (ignore_errors=[a-z]*)//g'); do
  [ -f "$file" ] && grep -Ev '^[[:space:]]*(#|$)' "$file" | quote_env >>"$ENVFILE" || true
done
SCRIPT_ARGS=()
for arg in "$@"; do
  case "$arg" in
    --*) SCRIPT_ARGS+=("$arg") ;;
    [A-Z_]*=*) printf '%s\n' "$arg" | quote_env >>"$ENVFILE" ;;
    *) echo "ignoring argument that is not KEY=VALUE or --flag: $arg" >&2 ;;
  esac
done
chown intellicash "$ENVFILE"

cd "$APP_API"
sudo -u intellicash env HOME=/var/www/intellicash \
  bash -c 'set -a; . "$0"; set +a; exec npx tsx "$@"' "$ENVFILE" "$SCRIPT" "${SCRIPT_ARGS[@]}"
