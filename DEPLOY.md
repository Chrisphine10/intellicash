# Deploying Intelli-Cash to Render

Intelli-Cash ships with a [`render.yaml`](render.yaml) Blueprint so the platform
can be deployed to [Render](https://render.com) with one click. This guide
explains the topology, the deploy flow, and the environment variables you must
supply yourself.

> Note: validate any change to `render.yaml` against the current
> [Render Blueprint spec](https://render.com/docs/blueprint-spec) — the field
> set (e.g. `preDeployCommand`, `envVars`, `disk`) evolves over time.

## Architecture

`scripts/render-server.ts` runs the Express API and the Next.js app **in one
Node process** (the API handles `/api/v1/*` and everything else falls through to
Next). The Blueprint defines two services:

| Service | Plan | Role | Database |
| --- | --- | --- | --- |
| `intelli-cash` | free | Combined web + API (`npm start` → `render-server.ts`). The user-facing app; the frontend calls its own API same-origin via `NEXT_PUBLIC_API_BASE_URL=/api/v1`. | SQLite at `/tmp` (ephemeral — see caveat) |
| `intelli-cash-api` | starter | API-only, for a persistent split deployment. | SQLite on a 1 GB persistent disk at `/var/data` |

**Pick one topology:**

- **Free demo (default):** deploy only `intelli-cash`. It re-seeds demo data on
  boot (`db:seed:if-empty`), so an ephemeral `/tmp` database is acceptable —
  data resets when the free instance spins down/redeploys.
- **Persistent / production:** use `intelli-cash-api` (paid Starter plan with a
  disk) as the API, host the web build separately, and point the web service's
  `NEXT_PUBLIC_API_BASE_URL` at the API service URL. For durable production data,
  prefer a managed **Render PostgreSQL** database over SQLite-on-disk (the schema
  and service layer are kept PostgreSQL-compatible).

## Deploy steps

1. Push this repository to GitHub/GitLab.
2. In the Render dashboard: **New → Blueprint**, and select the repo. Render
   reads `render.yaml` and provisions the service(s).
3. Fill in the secret environment variables below (marked `sync: false`, they
   are intentionally not committed). `SESSION_SECRET` is generated automatically.
4. Deploy. The build runs `npm install`, generates the Prisma client, and builds;
   the API service then runs `preDeployCommand` to seed if the DB is empty.
5. Verify: the API health check is `GET /health`; the app is served at the
   service URL.

## Environment variables you must set (`sync: false`)

These have no default and must be entered in the Render dashboard (or a synced
env group). Leave any unused integration blank — the app degrades gracefully and
those providers simply stay unconfigured.

- **Maps:** `GOOGLE_MAPS_BROWSER_API_KEY`, `NEXT_PUBLIC_GOOGLE_MAPS_API_KEY`
- **SMS — Africa's Talking:** `AFRICASTALKING_USERNAME`, `AFRICASTALKING_API_KEY`, `AFRICASTALKING_SENDER_ID`
- **SMS — Bonga:** `BONGA_SMS_CLIENT_ID`, `BONGA_SMS_API_KEY`, `BONGA_SMS_API_SECRET`, `BONGA_SMS_SERVICE_ID`, `BONGA_SMS_DEFAULT_PIN_TEMPLATE`, `BONGA_SMS_OTP_TEMPLATE`
- **Payments — M-Pesa:** `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_SHORTCODE`, `MPESA_PASSKEY`, `MPESA_INITIATOR_NAME`, `MPESA_SECURITY_CREDENTIAL`
- **Payments — Paystack:** `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY`
- **Banking — KCB Buni:** `KCB_BUNI_BASE_URL`, `KCB_BUNI_CLIENT_ID`, `KCB_BUNI_CLIENT_SECRET`
- **KYC — IPRS:** `IPRS_BASE_URL`, `IPRS_CLIENT_ID`, `IPRS_CLIENT_SECRET`
- **Credit — TransUnion:** `TRANSUNION_BASE_URL`, `TRANSUNION_CLIENT_ID`, `TRANSUNION_CLIENT_SECRET`
- **Market data — MFarm:** `MFARM_BASE_URL`, `MFARM_API_KEY`
- **IntelliAudit LLM (optional):** `INTELLIAUDIT_LLM_BASE_URL`, `INTELLIAUDIT_LLM_API_KEY`, `INTELLIAUDIT_LLM_MODEL` (leave `INTELLIAUDIT_LLM_PROVIDER=disabled` to keep it off)

Network calls to sandbox providers are gated by the `ENABLE_*_NETWORK_CALLS`
flags already set in the Blueprint. Callback URLs (M-Pesa, KCB Buni) are
pre-pointed at the API service host — update them if you rename the service.

## Caveats

- **Ephemeral database on free tier:** the `intelli-cash` free service stores
  SQLite in `/tmp`; Render free instances have no persistent disk, so data is not
  durable. This is fine for a demo (it re-seeds on boot) but not for production —
  move to a paid plan with a disk, or Render PostgreSQL.
- **`preDeployCommand` requires a paid plan.** It is configured on the Starter
  `intelli-cash-api` service; the free combined service instead seeds inside its
  start command (`start:render` → `db:seed:if-empty`).
- **Seeded admin login** after a fresh seed: `admin@intellicash.co.ke` /
  `IntellicashDemo#2026`. Change this before any real use.
