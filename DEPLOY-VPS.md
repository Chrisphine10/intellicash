# Deploying IntelliCash to intellicash.co.ke (HostPinnacle VPS)

Target: `78.159.126.22`, Ubuntu + nginx 1.24, root access.
Domain: `intellicash.co.ke` — already points here, HostPinnacle nameservers,
MX set. No site of its own yet (requests fall through to Hodi's vhost and 400).

**This box also runs Hodi (`hodi-admin.co.ke`), which holds GBV case data.**
Every step below is scoped to the new app. The Hodi-safety rules are not
optional — read them first.

---

## Hodi-safety rules (hold these the whole way through)

- **Never** touch `/home/hodi` (or whatever Hodi's home is), its virtualenv,
  its database, its `.env`, its systemd unit, or its nginx server block.
- IntelliCash runs as its **own unprivileged user** (`intellicash`), never
  root, with no read access to Hodi's files.
- nginx changes go in a **new** file only. Always `sudo nginx -t` before
  reloading, and only ever `sudo nginx -s reload` (graceful) — never
  `restart`/`stop`, which would interrupt Hodi.
- Node is installed **per-user via nvm**, so nothing system-wide changes and
  Hodi's Python stack is untouched. Do **not** run `apt upgrade`.
- All new systemd units are prefixed `intellicash-` so they can never be
  confused with Hodi's.

---

## 0. Go / no-go: does the box have headroom?

```bash
free -h && nproc && df -h /
```

You're adding a Node API + Next.js SSR + a database next to Django. Rough floor:
Next SSR ~250-400 MB resident, the API ~100-150 MB. If free RAM after Hodi is
under ~600 MB, stop here — put IntelliCash on its own small VPS instead. A
compromise of the money app then cannot reach Hodi's data at all, which is the
cleaner answer regardless.

---

## 1. A dedicated, unprivileged user

```bash
sudo adduser --system --group --shell /bin/bash --home /home/intellicash intellicash
sudo mkdir -p /home/intellicash/data          # database lives here, outside any web root
sudo chown -R intellicash:intellicash /home/intellicash
sudo chmod 700 /home/intellicash/data
```

Everything from here runs as that user: `sudo -u intellicash -i`.

---

## 2. Node 22 via nvm (per-user, no system change)

```bash
sudo -u intellicash -i
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
. ~/.nvm/nvm.sh
nvm install 22
nvm alias default 22
node -v          # expect v22.x — the platform requires >= 22.12
which node       # note this path; the systemd unit needs it
```

---

## 3. Get the code and build

```bash
# still as the intellicash user
cd /home/intellicash
git clone <your-repo-url> app
cd app
npm install --include=dev
npm run db:generate -w @intellicash/api
npm run build -w @intellicash/web
```

---

## 4. Environment

Create `/home/intellicash/app/apps/api/.env` (owned by intellicash, `chmod 600`):

```
NODE_ENV=production
# Migrations, not db push — reviewable, recorded, no silent reshaping of a
# database that now holds real ledgers.
PRISMA_SCHEMA_STRATEGY=migrate
# Persistent, outside any web-served directory.
DATABASE_URL=file:/home/intellicash/data/intellicash.db
SESSION_SECRET=<paste a long random value: `openssl rand -hex 32`>
# nginx terminates TLS and forwards one hop.
TRUST_PROXY_HOPS=1
API_PUBLIC_URL=https://intellicash.co.ke
WEB_ORIGIN=https://intellicash.co.ke
# Turn these on only with real credentials in hand.
ENABLE_PAYMENT_NETWORK_CALLS=false
ENABLE_SMS_NETWORK_CALLS=false

# The first admin, created on the empty database. NEVER the demo account —
# `NODE_ENV=production` makes the first-run seed skip the demo data entirely
# and create only these plus the permission templates. Password >= 12 chars.
INITIAL_ADMIN_EMAIL=you@intellicash.co.ke
INITIAL_ADMIN_PASSWORD=<a strong password you choose>
INITIAL_ADMIN_NAME=Platform Admin
INITIAL_ADMIN_PHONE=0722000000
```

`npm run start` (below) applies the schema and seeds on first run. Because
`NODE_ENV=production`, that first-run seed creates ONLY the permission
templates and the admin above — **not** the demo accounts. Verified: a fresh
production database comes up with one real admin, zero demo groups, and no
`admin@intellicash.co.ke`. If you leave the two `INITIAL_ADMIN_*` blank it
seeds no admin at all and says so loudly, rather than inventing a guessable
one — so set them before first start.

To apply the schema by hand ahead of time (optional; `start` does it too):

```bash
cd /home/intellicash/app
npm run db:push:env -w @intellicash/api   # runs `prisma migrate deploy`
```

---

## 5. One systemd unit (hardened, run as intellicash)

`scripts/render-server.ts` is a **single process** that serves the API (under
`/api/v1`) and the Next.js web app together on one port. So this is **one**
unit, not two, and nginx proxies to one port. `npm run start` runs it and,
because `PRISMA_SCHEMA_STRATEGY=migrate`, applies migrations first, then seeds
if empty (production-safe, per step 4).

`sudo nano /etc/systemd/system/intellicash.service` — replace the ExecStart
node path with the `.nvm/versions/node/vXX.XX.X` from step 2:

```ini
[Unit]
Description=IntelliCash (API + web)
After=network.target

[Service]
Type=simple
User=intellicash
Group=intellicash
WorkingDirectory=/home/intellicash/app
# The port render-server binds; nginx proxies here.
Environment=PORT=3000
Environment=NODE_ENV=production
# Load the app's env file (DATABASE_URL, SESSION_SECRET, INITIAL_ADMIN_*, …).
EnvironmentFile=/home/intellicash/app/apps/api/.env
ExecStart=/home/intellicash/.nvm/versions/node/vXX.XX.X/bin/npm run start
Restart=on-failure
RestartSec=5

# Isolation — this app cannot see the rest of the box, including Hodi.
ProtectSystem=strict
ProtectHome=read-only
ReadWritePaths=/home/intellicash/data /home/intellicash/app
PrivateTmp=yes
NoNewPrivileges=yes
ProtectKernelTunables=yes
ProtectControlGroups=yes

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now intellicash
sudo systemctl status intellicash          # confirm it's listening on :3000
journalctl -u intellicash -n 40 --no-pager # first-run: migrations + "Created initial admin"
```

---

## 6. nginx — a NEW server block, Hodi's untouched

`sudo nano /etc/nginx/sites-available/intellicash.co.ke`:

```nginx
server {
    listen 80;
    server_name intellicash.co.ke www.intellicash.co.ke;
    # certbot fills in the 443 block and the redirect in the next step.

    # One upstream — render-server serves both the API and the web app.
    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/intellicash.co.ke /etc/nginx/sites-enabled/
sudo nginx -t          # MUST pass before you reload
sudo nginx -s reload   # graceful — Hodi keeps serving
```

---

## 7. TLS — its own certificate

```bash
sudo certbot --nginx -d intellicash.co.ke -d www.intellicash.co.ke
```

certbot only edits the `intellicash.co.ke` server block. Afterwards
`https://intellicash.co.ke` should present its **own** cert, not Hodi's.

---

## 8. Verify (and prove Hodi is unharmed)

```bash
curl -s https://intellicash.co.ke/api/v1/health          # {"status":"ok"}
curl -s -o /dev/null -w "%{http_code}\n" https://intellicash.co.ke/login
curl -s -o /dev/null -w "hodi %{http_code}\n" https://hodi-admin.co.ke   # still 200
```

Then in a browser: sign in, record a contribution, restart the API
(`sudo systemctl restart intellicash-api`), and confirm the data survived —
that is the check that catches an ephemeral database.

---

## Mobile app

Bundle `IC_BASE_URL=https://intellicash.co.ke/api/v1` in the app's `.env`
(https, real host — the release build refuses localhost/plain-http), do **not**
set `IC_API_KEY`, then `flutter build apk --release`.

---

## Rollback

IntelliCash is entirely under `/home/intellicash` with its own unit and one
nginx file. To remove it: `systemctl disable --now intellicash`, delete the
nginx symlink, `nginx -s reload`. Hodi is never in the blast radius.

---

## Why production is safe to launch (what was verified)

- **No demo admin reaches production.** With `NODE_ENV=production` the first-run
  seed skips the demo dataset and creates only the permission templates plus
  the `INITIAL_ADMIN_*` account. Proven on a throwaway production database:
  one real admin, seven permission templates, zero demo groups/members, and no
  `admin@intellicash.co.ke`. A blank/weak admin password is refused, not
  silently accepted.
- **Schema via migrations.** `migrate deploy` applies the recorded baseline
  (all 68 tables) — reviewable, no in-place reshaping of a live ledger.
- **Durable storage enforced.** `render-server.ts` calls
  `assertDurableDatabase()` at startup and refuses an ephemeral path, so the
  database cannot silently be somewhere that gets wiped.
- **The mobile release** refuses a localhost / plain-http `IC_BASE_URL`, so a
  build pointed at a laptop cannot ship.
