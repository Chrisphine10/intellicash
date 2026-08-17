# Data protection: policy vs implementation

**Audited 17 Aug 2026 against the published privacy notice** (`apps/web/src/app/privacy/page.tsx`)
**and the Kenya Data Protection Act, 2019.**

Not legal advice. This is an engineering audit of whether the claims the
platform publishes are true in its code. A DPO or advocate still needs to review
the notice itself, the ODPC registration position, and the retention schedule.

## The policy exists

A public privacy notice is served at `/privacy`, names the Act, lists data
subject rights, and points to the ODPC. That is more than most projects this
size have. It makes **seven specific technical claims**, which is what makes it
auditable — and what makes overstating one a liability rather than a nicety.

## Verified as implemented

| Claim | Evidence |
|---|---|
| Passwords stored as strong one-way hashes | `bcrypt.hash(password, 12)` — `auth.ts:194`, `admin.ts:321` |
| Meeting PINs / OTPs hashed server-side | `bcrypt.compare` — `groups.ts:485,494,1149` |
| Access is role-scoped | `requireAuth(permission)` on every route; `scopeGroupWhere` narrows an agent to their caseload, and out-of-scope reads 404 rather than 403 |
| Financial records append-only with audit trail | `appendAuditEvent`, ledger append-only |
| National ID not stored in plain text | Column is `nationalIdHash`; no plaintext column exists |

## Gaps found

### 1. The offline meeting-PIN verifier is a fast hash — HIGH

The notice says PINs are stored "only as strong one-way hashes". Server-side
that is true (bcrypt). The **offline copy is not**:

- On the phone: `sha256('$memberId:$pin')` — `meeting_unlock.dart:31`
- In the cache the server ships to a device: `sha256('deviceId:memberId:pin')` — `groups.ts:1106`

SHA-256 is designed to be fast. A meeting PIN is now **four digits**, so the
entire keyspace is 10,000 candidates — a complete lookup table computes in
milliseconds. Anyone who obtains the phone's database file or a cached verifier
blob recovers every member's PIN, and those PINs are the three keys that open a
meeting.

This pre-dates the four-digit change (a million SHA-256 candidates is also
seconds), but shortening the PIN on 17 Aug 2026 made it 100× cheaper. The
salt helps only against cross-device reuse, not against enumeration.

**Fix:** use a slow KDF for the offline verifier too. On-device, PBKDF2 or
scrypt with a high iteration count; the cost is paid once per unlock attempt,
which is a human-speed operation. Until then, the notice's wording is wider
than the implementation.

### 2. `nationalIdHash` is an unsalted hash of a low-entropy identifier — HIGH

`sha256(value)` in `lib/crypto.ts` is plain and unsalted, and the hash is
computed client-side and accepted as a string (`groups.ts:96`).

A Kenyan national ID number is about eight digits. Around 100 million
candidates is minutes of laptop time, so the hash is reversible in practice.
Unsalted also means the same ID produces the same hash everywhere, so records
can be linked across groups and programmes without ever reversing anything.

Under the Act this is pseudonymised data, which is **still personal data** —
so the notice's "stored hashed, never in plain text" is literally accurate but
implies a protection that is not there. Needs a salted slow KDF, or a keyed
HMAC with the key held server-side.

### 3. No data-subject-rights mechanism — HIGH

The notice grants five rights: access, rectification, erasure, withdrawal of
consent, and complaint. There is **no endpoint, admin screen or documented
runbook** implementing any of them. Today an access or erasure request would be
handled by someone writing SQL by hand, if at all.

The Act gives a data subject the right to these and a controller a deadline to
respond. A promise on a public page with no mechanism behind it is the gap most
likely to be tested.

### 4. Retention is asserted, not enforced — MEDIUM

The notice says records are kept "for the life of the group plus statutory
retention periods". Nothing in the schema or any job enforces a retention
period. The only automatic deletion anywhere is the mobile outbox prune
(7 days, synced entries only).

Visit photos, GPS fixes and free-text notes about named individuals accumulate
with no expiry. Retention limitation is an obligation, not an aspiration.

### 5. Visit records are wider than the notice describes — MEDIUM

Field visits now capture GPS coordinates, device identifiers, photographs of
premises, and free-text coaching notes naming individuals. The "What we
collect" table pre-dates that work and does not mention location, photographs
or device identifiers.

A notice that does not describe the processing is not valid notice. This one
needs updating before the visits feature reaches real groups at scale.

### 6. No breach-notification runbook — MEDIUM

The Act requires notifying the ODPC within 72 hours of becoming aware of a
breach likely to cause harm. There is no runbook, no owner named, and no
contact route recorded. 72 hours is not long to invent a process in.

### 7. Worth confirming, outside the code

- **ODPC registration** as data controller and/or processor.
- **DPIA** — this is large-scale processing of financial data about
  identifiable individuals, plus location and photographs, which is the profile
  that normally triggers one.
- **Cross-border transfers** — SMS and payment providers process phone numbers
  and may sit outside Kenya.
- **Shared hosting** — this box also runs Hodi, which holds GBV case data.
  Two unrelated controllers on one host deserves an explicit look.

## Suggested order

1. Data-subject-rights endpoints (access + erasure) — the clearest legal exposure.
2. Re-hash the offline PIN verifier with a slow KDF — the clearest technical one.
3. Salt/HMAC the national ID hash, and re-hash existing rows.
4. Update the notice to cover GPS, photographs and device identifiers.
5. Write the retention schedule, then enforce it in a job.
6. Breach runbook with a named owner.
