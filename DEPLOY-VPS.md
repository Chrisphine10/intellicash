# IntelliCash on the HostPinnacle VPS (intellicash.co.ke)

Host: `78.159.126.22`, Ubuntu 24.04, nginx, Node 22.23.1 (system-wide).
Deployed 28 Jul 2026. **Routine deploys are automated — see "CI/CD" below.**
This document records the layout and the manual procedure behind it.

## This box is shared — three apps, one nginx

| Site | Stack | Port | Unit | Root |
|---|---|---|---|---|
| `hodi-admin.co.ke` | Django | unix socket | `hodi.service` | `/var/www/hodi` |
| `phinetech.co.ke` | Next.js | `127.0.0.1:3000` | `phinetech.service` | `/var/www/phinetech` |
| `intellicash.co.ke` | Node + Next.js | `127.0.0.1:4000` | `intellicash.service` | `/var/www/intellicash` |

**Hodi holds GBV case data.** Every rule below exists to keep it untouched:

- Never touch `/var/www/hodi`, its venv, database, `.env`, unit, or nginx block.
- IntelliCash runs as its own unprivileged user (`intellicash`, uid 108), never root.
- nginx changes go in a **new** file only. Always `nginx -t` before reloading, and
  only ever `nginx -s reload` (graceful) — never `restart`/`stop`, which drops Hodi.
- **Port 3000 is taken by phinetech.** IntelliCash uses **4000**. Check with
  `ss -tlnp` before assuming any port is free.
- Do not run `apt upgrade`.

## Branch history

`main` is the production branch. Until 28 Jul 2026 it held an unrelated
**Laravel** application (1,279 PHP files, no common ancestor with this code);
that is archived at the tag `archive/laravel-20260728` and the branch
`archive/laravel`, and nothing was deleted.

## Layout

- App: `/var/www/intellicash/app` (git checkout, branch `main`)
- Database: `/var/www/intellicash/data/intellicash.db` — outside any web root, `chmod 700` dir
- Env: `app/apps/api/.env`, `chmod 600`, owned by `intellicash`, git-ignored
- nginx: `/etc/nginx/sites-available/intellicash.co.ke`
- Backups: `/root/backups/intellicash-<ts>/`, newest symlinked as `INTELLICASH_LATEST`

## One process, not two

`scripts/render-server.ts` serves the API (under `/api/v1`) **and** the Next.js
app on a single port. So: one systemd unit, one nginx upstream, and
`NEXT_PUBLIC_API_BASE_URL` must be the **relative** `/api/v1`. An absolute URL
baked in at build time ships a UI whose every browser call fails.

## Content-Security-Policy (why the site once rendered blank)

`createApp()` mounts helmet. Its default `script-src 'self'` blocks the App
Router's inline `self.__next_f.push(...)` bootstrap scripts, React never
hydrates, and the page renders blank. That took the site down on 28 Jul 2026.

Now: `createApp({ servesWebApp: true })` (set only by `render-server.ts`)
disables helmet's CSP, and `apps/web/src/middleware.ts` issues a **per-request
nonce** instead, so `'unsafe-inline'` is not needed. Notes:

- **A nonce cannot be stamped into statically prerendered HTML**, so the root
  layout sets `export const dynamic = "force-dynamic"`. Removing that silently
  reverts the app to build-time HTML whose scripts carry no nonce - the page
  then loads looking correct while React never hydrates.
- Next nonces its own scripts but **not hand-written `<script>` tags**. The
  theme initialiser reads the nonce via `headers().get("x-nonce")`. Any new
  inline script must do the same or the CSP will block it.
- Two CSP headers are intersected by the browser, so helmet must not add a
  second one on the web path.
- `/api/v1` and `/health` keep their own strict `default-src 'none'`.

Verify after any change here - a header alone proves nothing:

```bash
curl -s -D - https://intellicash.co.ke/ -o /tmp/p.html | grep -i content-security-policy
# every <script> must carry the SAME nonce as the header:
grep -c 'nonce=' /tmp/p.html
```

## Binding

`render-server.ts` reads `HOST`, defaulting to `0.0.0.0` because Render requires
it. **On this VPS `.env` sets `HOST=127.0.0.1`.** Without it the app answers on
the public interface at `:4000` over plain http, bypassing nginx and TLS
completely — verified: it did exactly that before the fix.

## Environment

```
NODE_ENV=production
PRISMA_SCHEMA_STRATEGY=migrate          # migrate deploy, not db push
DATABASE_URL=file:/var/www/intellicash/data/intellicash.db
SESSION_SECRET=<openssl rand -hex 32>
TRUST_PROXY_HOPS=1                      # nginx forwards exactly one hop
PORT=4000                               # NOT 3000 — phinetech owns it
HOST=127.0.0.1                          # nginx is the only entrypoint
API_PUBLIC_URL=https://intellicash.co.ke
WEB_ORIGIN=https://intellicash.co.ke,https://www.intellicash.co.ke
ENABLE_PAYMENT_NETWORK_CALLS=false      # until real gateway credentials exist
ENABLE_SMS_NETWORK_CALLS=true           # see "SMS delivery" below — false means nothing is ever sent
INITIAL_ADMIN_EMAIL=admin@intellicash.co.ke
INITIAL_ADMIN_PASSWORD=<strong, >=12 chars>
```

`NODE_ENV=production` makes the first-run seed create **only** the permission
templates and the `INITIAL_ADMIN_*` account — no demo groups, no demo members.
Confirmed on this deploy: `[bootstrap] Created initial admin admin@intellicash.co.ke`
and nothing else. Leave the admin vars blank and it seeds no admin at all and
says so, rather than inventing a guessable one.

## SMS delivery (Bonga)

Two separate things have to be true before a member receives a text, and for a
while only the first one was visible anywhere:

1. **Credentials.** Either the `BONGA_SMS_*` variables below, or the same keys
   saved through Dashboard → Integrations → Bonga SMS (encrypted in
   `IntegrationConfig`, and preferred over the environment). The console path
   survives a redeploy and needs no shell.
2. **`ENABLE_SMS_NETWORK_CALLS=true`.** With it off, `sendBongaSms` returns
   before it ever calls the provider and every recipient is recorded `QUEUED`.
   This is an environment variable only — it cannot be set from the console, and
   it takes a restart.

The live account (Intelli-Wealth Limited, sender ID `INTELLIWLTH`):

```
BONGA_SMS_ENDPOINT="http://167.172.14.50:4002/v1/send-sms"
BONGA_SMS_CLIENT_ID=<apiClientID>
BONGA_SMS_API_KEY=<key>
BONGA_SMS_API_SECRET=<secret>
BONGA_SMS_SERVICE_ID=5843
```

The endpoint is plain HTTP on a bare IP with the secret in the request body, so
it must only ever be called server-side. Never from the phone or the browser.

A send is delivered when the provider answers `status: 222`; anything else is a
failure. Verify with one message to a number you hold, and read the response
rather than the HTTP code — the endpoint answers 200 for both:

```bash
curl -s -X POST http://167.172.14.50:4002/v1/send-sms -F apiClientID=... -F key=... -F secret=... -F serviceID=5843 -F MSISDN=2547XXXXXXXX -F txtMessage='test'
```

## Health endpoint

`GET /health` — **not** `/api/v1/health`, which returns the web app's 404 page.
`render.yaml` declares `healthCheckPath: /health`; use that everywhere.

```
{"data":{"status":"ok","service":"intellicash-api"},"meta":{"traceId":"..."}}
```

## CI/CD (the normal way to deploy)

Push to `main` → `.github/workflows/deploy-production.yml`:
backup → refuse dirty tree → `git reset --hard` → `npm ci` → build web →
`systemctl restart` → health check → **assert hodi and phinetech are still 200**
→ roll back the code on failure.

`.github/workflows/ci.yml` runs typecheck, tests and the web build on every push
and PR.

Required repo secrets: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`.

Two things the pipeline deliberately does **not** do:

- **It does not restore the database on rollback.** A forward migration may not
  be reversible, and silently reverting a ledger is worse than an outage.
  Backups are in `/root/backups/`; restore by hand, deliberately.
- **It does not touch Render.** Those services are untouched and keep their
  own separate database.

Use `npm ci`, never `npm install`, on the server: `install` rewrites
`package-lock.json`, and the dirty-tree guard then refuses the next deploy.

## Manual procedure (first install / disaster recovery)

```bash
# 1. user and directories
adduser --system --group --shell /bin/bash --home /var/www/intellicash intellicash
mkdir -p /var/www/intellicash/data
chown -R intellicash:intellicash /var/www/intellicash
chmod 700 /var/www/intellicash/data

# 2. code (node 22 is already installed system-wide)
sudo -u intellicash git clone -b main \
  https://github.com/Chrisphine10/intellicash.git /var/www/intellicash/app
git config --global --add safe.directory /var/www/intellicash/app   # root runs git here

# 3. build
sudo -u intellicash env HOME=/var/www/intellicash NEXT_PUBLIC_API_BASE_URL=/api/v1 \
  bash -lc 'cd /var/www/intellicash/app && npm ci --include=dev && npm run build -w @intellicash/web'

# 4. write apps/api/.env (see above), chmod 600, chown intellicash

# 5. systemd, then nginx, then certbot
systemctl enable --now intellicash.service
ln -sf /etc/nginx/sites-available/intellicash.co.ke /etc/nginx/sites-enabled/
nginx -t && nginx -s reload
certbot --nginx -d intellicash.co.ke -d www.intellicash.co.ke
```

## Verify

```bash
curl -s https://intellicash.co.ke/health                                  # {"status":"ok"}
curl -s -o /dev/null -w '%{http_code}\n' https://intellicash.co.ke/login  # 200
curl -s -o /dev/null -w 'hodi %{http_code}\n' https://hodi-admin.co.ke    # 200
curl -s -o /dev/null -w 'phine %{http_code}\n' https://phinetech.co.ke    # 200
curl -s -m 5 http://78.159.126.22:4000/health                             # must FAIL to connect
```

That last check is the one that proves the app is not exposed outside TLS.

## Mobile app

Bundle `IC_BASE_URL=https://intellicash.co.ke/api/v1` in the app `.env`, do
**not** set `IC_API_KEY` (release ignores it — an APK is a public artifact),
then `flutter build apk --release`.

## Rollback / removal

Everything is under `/var/www/intellicash` with one unit and one nginx file:

```bash
systemctl disable --now intellicash.service
rm /etc/nginx/sites-enabled/intellicash.co.ke
nginx -t && nginx -s reload
```

Hodi is never in the blast radius.
