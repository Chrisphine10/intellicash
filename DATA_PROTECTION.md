# Intelli-Cash Data Protection Protocol

Version 1.0 — July 2026. Applies to the whole platform (`apps/api`, `apps/web`).
Aligned with the Kenya Data Protection Act, 2019 (the platform operates in
Kenya: counties, M-Pesa rails, `intellicash.co.ke`). The public-facing summary
of this protocol is published at `/privacy` on the web app.

## 1. Data classification

| Class | Examples | Handling |
| --- | --- | --- |
| **Restricted** | Passwords, meeting PINs, OTPs, national IDs, provider API secrets | Never stored in plain text. Hash (bcrypt cost 12) or encrypt at rest. Never logged, never in API responses. |
| **Confidential (member PII)** | Member names, phones, attendance, savings/loan records, KYC status | Role-scoped access only. Never on public pages or `/public/*` API responses. Phones masked when displayed outside core group operations. |
| **Confidential (staff/partner PII)** | VA/CBT phones and emails, supplier and partner contact people | Authenticated, role-scoped endpoints only. Public surfaces show organization/agent **names and coverage areas** only. |
| **Internal** | Aggregated counts, programme funding totals, audit metadata | Authenticated dashboards; aggregate views may be public where the programme is public. |
| **Public** | Programme names/descriptions, product catalog, group names/codes/phase | Freely served by `/public/*` endpoints. |

## 2. PII inventory (Prisma schema)

- `Member` — fullName, phone, `nationalIdHash` (hashed), `pinHash`/OTP hashes (hashed)
- `User` — email, name, `passwordHash` (bcrypt)
- `VillageAgent` — name, phone, email, gender, county
- `StoreSupplier` / `Partner` / `PartnerSignupRequest` — contact people (name/phone/email)
- `StoreCreditRequest` / `AgentBookingRequest` / `PartnerWalletTransaction` — public-form buyer details (name, email, phone, county, group)
- `SmsBroadcastRecipient` / `MemberPinDelivery` — recipient phone; PIN SMS body stored encrypted (`messageCiphertext`)
- `Group` — contact person, GPS coordinates (meeting-location compliance)

Adding a new field to this list means updating this document and applying the
controls in §3 before merge.

## 3. Technical controls

### Implemented

1. **Hashing** — passwords, member PINs, OTPs (bcrypt, cost 12); national IDs stored only as `nationalIdHash`.
2. **Encryption at rest** — integration credentials (`IntegrationConfig`) and PIN/OTP SMS bodies (`messageCiphertext`) via `lib/crypto`.
3. **Masking at trust boundaries** (`apps/api/src/lib/privacy.ts`) — `maskPhone` / `maskEmail`; used by SMS broadcast serialization and PIN-delivery previews. New surfaces that show a phone/email to a lower-privilege audience must mask.
4. **Public API minimization** — `/public/intelli-store` serves agents, suppliers, and partners through explicit `select`s (name + coverage only, no phone/email/contact person). Public POST confirmations return the caller's own submission plus product/programme/agent **names only** — no embedded staff contact records.
5. **Log hygiene** — request logs carry method, redacted path (`redactUrlForLogs` strips phone/email/name/search query values), status, duration, trace ID, user ID. No request bodies. 401s are routine and not logged as client errors in the web app.
6. **Role-scoped rows** — every list endpoint filters by role scope (`*ScopeForUser` helpers); members see self, group accounts their group, partners their programmes, `IWL_ADMIN`/`READ_ONLY` all.
7. **Member contact masking by role** — the group roster (`GET /groups/:id/members`) returns member phone numbers in full only to operational roles (`IWL_ADMIN`, `GROUP_ACCOUNT`, and a member's own record); oversight roles (`PARTNER_OFFICER`, `LENDER`, `READ_ONLY`) receive masked phones via `serializeMember` + `canViewMemberContact` (`apps/api/src/lib/privacy.ts`). Member records embedded in meetings, attendance, ledger entries, and share-out previews (`nestedMemberSelect`) carry **name only, never phone**, for every role — so partner oversight of financial activity never surfaces member contact details.
8. **Session security** — httpOnly, SameSite=Lax cookies with TTL; append-only `AuditEvent` trail for sensitive actions.
9. **Consent capture (UI)** — public Intelli-Store checkout and VA/CBT booking forms require an explicit consent checkbox linking to `/privacy` before submission.
10. **Public privacy notice** — `/privacy` page (linked from the public footer) documents collection, purpose, protections, retention, and DPA-2019 rights.

### Policy (enforced by review, pending automation)

- **Retention** — public store/booking requests: 24 months after closure; server logs: 90 days; group financial records: life of group + statutory period. *Follow-up: scheduled anonymization job.*
- **Consent persistence** — consent is currently enforced client-side only. *Follow-up: `consentAt DateTime` on `StoreCreditRequest`/`AgentBookingRequest` (schema change — needs owner approval).*
- **Consent rollout** — group-registration, partner-signup, contact, and public-contribution forms need the same consent row as the store forms.
- **Rate limiting** on public POST endpoints and login. *Follow-up: express-rate-limit or equivalent.*

## 4. Developer rules

1. Never `include: { <model>: true }` for models carrying PII on a `/public/*` route — always an explicit `select`.
2. Never log request bodies, phones, emails, or IDs; log the trace ID and look the record up instead.
3. New PII fields: hash or encrypt if Restricted; add to §2; apply masking helpers at display boundaries.
4. Public POST endpoints return the submitter's own data + display names only.
5. Audit payloads may contain full records (admin-only surface), but must never be echoed back through public responses.

## 5. Data-subject rights (DPA 2019)

Requests arrive via `support@intellicash.co.ke`. Target response: 14 days.
Access/copy, correction, deletion (where no statutory retention applies), and
consent withdrawal. Verify the requester controls the phone/email on record
before disclosing anything. Group financial records are group property —
member-level deletion inside a group ledger requires General Assembly process
(governance model), but contact details can be corrected/anonymized.

## 6. Breach response

1. Contain (revoke sessions/keys, disable affected integration).
2. Assess scope via `AuditEvent` and request logs (trace IDs).
3. Notify the Data Protection Commissioner within 72 hours if personal data is
   affected (DPA 2019 §43), and affected subjects without undue delay.
4. Record the incident, root cause, and remediation in the project changelog.

## 7. Review cadence

Re-audit `/public/*` responses and the log pipeline whenever a route or model
changes shape; full protocol review quarterly or on any new integration.
