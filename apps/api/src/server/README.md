# Server Services

This directory contains the service layer of the IntelliCash API. Each file is a focused module that owns one domain concern — scoping, credit rating, payments, SMS, visits, etc. Routes call services; services call Prisma and each other.

## Architecture

```
src/
├── server/
│   ├── index.ts          ← Public API: re-exports every service
│   └── README.md         ← This file
├── services/             ← One file per domain concern
│   ├── account-scope.ts
│   ├── audit-service.ts
│   ├── credit-rating-service.ts
│   ├── cycle-service.ts
│   ├── group-champion-service.ts
│   ├── group-code.ts
│   ├── group-login-link.ts
│   ├── group-payment-service.ts
│   ├── integration-credentials.ts
│   ├── intelliaudit-llm.ts
│   ├── loan-position-service.ts
│   ├── login-otp-service.ts
│   ├── meal-report.ts
│   ├── meeting-sms-service.ts
│   ├── member-passbook-service.ts
│   ├── member-pin-service.ts
│   ├── membership-service.ts
│   ├── notification-service.ts
│   ├── outbound-sms-service.ts
│   ├── payment-service.ts
│   ├── programme-performance-report.ts
│   ├── restore-bundle-service.ts
│   ├── role-permission-service.ts
│   ├── share-out-record-service.ts
│   ├── sms-provider.ts
│   ├── sms-service.ts
│   ├── storage-guard.ts
│   ├── support-need-service.ts
│   ├── village-agent-service.ts
│   ├── visit-assessment-service.ts
│   ├── visit-service.ts
│   ├── wallet-service.ts
│   ├── admin-sms-service.ts
│   ├── assessment-template-bootstrap.ts
│   └── attachment-storage.ts
├── routes/               ← HTTP handlers; call services
├── middleware/            ← Auth, rate limiting, tracing
├── lib/                   ← Prisma client, crypto, HTTP helpers
├── domain/                ← Pure business logic (no I/O)
└── config/                ← Environment configuration
```

## Service Categories

### Account & Authorization
- **account-scope.ts** — Permission boundaries. Every query starts here.
- **role-permission-service.ts** — Role-based permission templates.
- **membership-service.ts** — Multi-group membership reconciliation.

### Financial
- **wallet-service.ts** — Overdraw-safe partner wallet operations.
- **payment-service.ts** — M-Pesa Daraja & Paystack integration.
- **group-payment-service.ts** — Group-side payment completion hooks.
- **loan-position-service.ts** — Member loan position calculations.
- **member-passbook-service.ts** — Member passbook aggregation.
- **share-out-record-service.ts** — Cycle closing & fund distribution.

### Groups & Members
- **group-code.ts** — Group code generation (`IWL-<county>-<random>`).
- **group-login-link.ts** — Ensures every group login opens a group.
- **group-champion-service.ts** — Links a champion to a group account.
- **member-pin-service.ts** — Meeting PIN generation, delivery, verification.
- **login-otp-service.ts** — Phone + code sign-in.

### Cycles & Meetings
- **cycle-service.ts** — Saving cycle management (open/close).
- **meeting-sms-service.ts** — Meeting summary & share-purchase SMS.

### Visits & Assessments
- **visit-service.ts** — Field visit submission & amendment.
- **visit-assessment-service.ts** — Scorecard templates & scoring.

### Credit & Ratings
- **credit-rating-service.ts** — Credit rating facts & contract evaluation.

### SMS & Notifications
- **outbound-sms-service.ts** — System-initiated SMS dispatcher.
- **notification-service.ts** — Console notifications + SMS seam.
- **sms-service.ts** — Single SMS send through configured provider.
- **sms-provider.ts** — Provider selection & credentials.
- **admin-sms-service.ts** — Admin-typed SMS broadcasts.

### Programmes & Partners
- **village-agent-service.ts** — Agent programme assignments.
- **programme-performance-report.ts** — Partner performance pack.
- **meal-report.ts** — MEAL indicators report.

### IntelliAudit
- **intelliaudit-llm.ts** — External LLM integration.

### Storage & Infrastructure
- **attachment-storage.ts** — File storage abstraction.
- **storage-guard.ts** — Disk space monitoring.
- **integration-credentials.ts** — Credential encryption at rest.
- **audit-service.ts** — Append-only audit trail.
- **restore-bundle-service.ts** — Group data export/restore.

### Bootstrap & Seeds
- **assessment-template-bootstrap.ts** — Seeds default scorecard.
- **support-need-service.ts** — Seeds support-need taxonomy.

## Conventions

1. **One concern per file.** If a service grows to own two unrelated things, split it.
2. **No route logic in services.** Services don't know about HTTP; they take plain inputs and return plain outputs or throw `ApiHttpError`.
3. **Prisma is the only data access.** No raw SQL except where noted (e.g., atomic increments).
4. **Domain logic stays in `domain/`.** Services orchestrate; pure rules live in `domain/`.
5. **JSDoc on every service file.** The top-of-file comment explains *why* the service exists, not just what it does.
6. **Multi-tenancy via scoping.** Every read runs through `account-scope.ts`; never trust a client-supplied ID.

## Importing

```ts
// ✅ Import from the public API
import { accountScope, creditRating, wallet } from "./server";

// ❌ Don't reach into individual files
import { groupScopeForUser } from "../services/account-scope";
```

The `index.ts` re-export is the stable surface. Internal file paths can change without touching callers.
