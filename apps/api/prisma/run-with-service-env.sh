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
set -euo pipefail

SCRIPT="${1:?usage: run-with-service-env.sh prisma/<script>.ts}"
APP_API=/var/www/intellicash/app/apps/api

ENVFILE=$(mktemp)
chmod 600 "$ENVFILE"
trap 'rm -f "$ENVFILE"' EXIT

systemctl show intellicash -p Environment --value | tr ' ' '\n' | grep '=' >>"$ENVFILE" || true
for file in $(systemctl show intellicash -p EnvironmentFiles --value | sed 's/ (ignore_errors=[a-z]*)//g'); do
  [ -f "$file" ] && grep -Ev '^[[:space:]]*(#|$)' "$file" >>"$ENVFILE" || true
done
chown intellicash "$ENVFILE"

cd "$APP_API"
sudo -u intellicash env HOME=/var/www/intellicash \
  bash -c "set -a; . '$ENVFILE'; set +a; npx tsx '$SCRIPT'"
