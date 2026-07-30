# Enhanced VSLA Features — Technical Design

Status: **for approval, no code written yet**
Date: 30 Jul 2026
Covers: the 13 requested enhancements, designed against the code as it exists
today rather than against an assumed architecture.

---

## 1. Audit: what is already there

Verified directly in `apps/api/prisma/schema.prisma`, the API routes, the web
app and `intellicash_mobile/lib`. Three requested items are premised on things
that are not true of this system.

| # | Requested | Reality |
|---|---|---|
| 1 | "Replace the Digitization Focal Point" | **No such feature exists** anywhere. This is an *add*, not a replace. `Group` already has `county` (required), `subCounty`, `location`, `gpsLatitude`, `gpsLongitude`, `gpsRadiusMeters`. |
| 2/3 | Reject disbursement beyond available funds | **Already enforced.** `appendLedgerEntry` refuses any debit that would take a fund negative — `INSUFFICIENT_FUND_BALANCE`. The gap is *loan-specific* validation, which needs Keystone A. |
| 12 | Remove member limits | **No limit exists.** No `maxMembers`, no count check, no DB constraint in backend, web, shared or mobile. Nothing to remove; only pagination/indexing work remains. |

Also already present and reusable:

- `FundAccount` per group and type, `@@unique([groupId, type])`, with
  `balanceCents` maintained transactionally.
- `LedgerEntry` with `type`, `direction`, `fundAccountId`, and hash signing via
  `signLedgerEntry` — the existing accounting record.
- `meetingLedgerRules`, which already maps each entry type to a fund and
  direction (e.g. `SHARE_PURCHASE → INTERNAL_LOAN/CREDIT`).
- `Group.cycleNumber`, `shareValueCents`, `maxSharesPerMemberPerMeeting`,
  `constitutionVersion`.
- A working share-out in mobile that already **nets outstanding loans off
  payouts and permits a negative balance** (member owes the group) — which is
  most of requirement #8 already.
- `GroupIntegrationConfig` (added 28 Jul) — the precedent for per-group
  configuration and encrypted per-group settings.

### The two keystones

**Keystone A — there is no backend `Loan` entity.**
Internal loans exist only as `LedgerEntry` rows
(`INTERNAL_LOAN_DISBURSEMENT`, `LOAN_REPAYMENT`). The server has no principal,
term, due date, interest or outstanding balance. The **mobile app does** have a
full `Loan` model with `dueDate`. The halves disagree.
Blocks **#2 (properly), #5, #7, #9**.

**Keystone B — there is no `Cycle` entity.**
`cycleNumber` is a bare `Int` on `Group`. `Meeting` and `LedgerEntry` have no
`cycleId`, and `Meeting` has no archived state, so historical immutability
cannot be enforced.
Blocks **#10, #11, #13**.

---

## 2. Keystone A — `Loan`, derived from the ledger

**Decision: the ledger remains the source of truth.** `Loan` is a reconcilable
projection over ledger entries, not a parallel accounting record. This is the
safest option for a database that already holds real balances: if `Loan` and
the ledger ever disagree, the ledger wins and the projection is rebuilt.

```prisma
model Loan {
  id                String   @id @default(cuid())
  groupId           String
  memberId          String
  cycleId           String?              // Keystone B
  principalCents    Int
  interestRateBps   Int      @default(0)
  termMonths        Int                  // #5, defaulted from group config
  disbursedAt       DateTime
  dueAt             DateTime
  status            String   @default("ACTIVE") // ACTIVE|REPAID|CARRIED_FORWARD|WRITTEN_OFF
  carriedFromLoanId String?              // #9 carry-forward chain
  disbursementEntryId String? @unique    // the ledger row that created it
  createdAt         DateTime @default(now())
  updatedAt         DateTime @updatedAt

  @@index([groupId, status])
  @@index([memberId])
}
```

Repayments are **not** a new table: they are the existing `LOAN_REPAYMENT`
ledger entries, gaining a nullable `loanId` so a repayment can be attributed to
a specific loan. Entries without a `loanId` (all historical ones) still count
toward the member's outstanding total, so nothing is lost during backfill.

**Derived, never stored:**
`outstanding = principal + accruedInterest − Σ repayments`. Storing it would
create a second truth that can drift from the ledger.

### Backfill

One idempotent migration script, dry-run first:

1. For every `INTERNAL_LOAN_DISBURSEMENT` entry with no `Loan`, create one:
   `principalCents` = entry amount, `disbursedAt` = entry timestamp,
   `termMonths` = the group's configured default, `dueAt` = derived,
   `disbursementEntryId` = the entry.
2. Attribute `LOAN_REPAYMENT` entries to the member's oldest open loan (FIFO),
   which is standard VSLA practice; leave `loanId` null when ambiguous.
3. Emit a reconciliation report: per group, Σ loan principal vs Σ disbursement
   entries. **Any mismatch aborts the migration.**

Because `disbursementEntryId` is unique, re-running is safe.

### Mobile reconciliation

Mobile keeps its local `Loan` model — it must work offline. The sync layer maps
local loans to server loans through the existing `id_map` table, exactly as
meetings and members already do. Mobile remains authoritative for offline
groups; the server becomes authoritative once a group is cloud-bound.

---

## 3. Keystone B — `Cycle`

```prisma
model Cycle {
  id          String    @id @default(cuid())
  groupId     String
  number      Int
  startedAt   DateTime
  closedAt    DateTime?
  status      String    @default("ACTIVE") // ACTIVE|CLOSED
  shareOutId  String?
  @@unique([groupId, number])
}
```

`Meeting`, `LedgerEntry` and `Loan` gain a nullable `cycleId`, backfilled to the
group's current cycle. Nullable, so existing rows and older clients keep working
— that is what preserves backward compatibility.

**Immutability (#10, #13)** is enforced in one place, not scattered through
routes: a guard in the ledger and meeting write paths that refuses any write
whose `cycleId` belongs to a `CLOSED` cycle —
`CYCLE_CLOSED` / `MEETING_ARCHIVED`. Reads are never restricted, so history and
reports stay fully available.

**New cycle (#10, #11)** is a single transactional operation: close the current
cycle, create the next, roll `Group.cycleNumber`, and carry forward membership
and roles. Members, roles and leadership can then be edited freely in the new
cycle without touching closed-cycle rows, because those rows are pinned to the
old `cycleId`.

---

## 4. Per-group configuration (#5, #6, #8, #9)

Rather than four ad-hoc columns, one typed settings record per group, following
the `GroupIntegrationConfig` precedent:

```prisma
model GroupPolicy {
  groupId                    String  @id
  defaultLoanTermMonths      Int     @default(1)   // #5
  expenseFundType            String  @default("SOCIAL") // #6
  shareOutRequiresContributions Boolean @default(true)  // #8
  shareOutRequiresWelfare       Boolean @default(true)  // #8
  shareOutRequiresFines         Boolean @default(true)  // #8
  outstandingLoanHandling    String  @default("DEDUCT") // #9 DEDUCT|CARRY_FORWARD|MANUAL
  updatedByUserId            String?
}
```

Defaults reproduce today's behaviour exactly, so groups that never touch this
see no change. **#8 explicitly does not include outstanding loans in the
eligibility gate** — per the requirement, share-out must work with active loans.

---

## 5. Feature-by-feature

| # | Feature | Depends on | Work |
|---|---|---|---|
| 1 | Group location | — | Add `ward`, `googlePlaceId`, `formattedAddress`; keep existing county/GPS fields. Places Autocomplete + map picker in web and mobile; reverse geocode on pin; manual entry fallback when GPS is denied. |
| 2 | Loan fund validation | A | Check requested principal ≤ `INTERNAL_LOAN` fund balance *before* disbursement; disable the approve action in UI. Fund-level guard already exists as backstop. |
| 3 | Bank/cash validation | — | Already enforced by `INSUFFICIENT_FUND_BALANCE`. Add explicit account selection + pre-flight check and a clearer error. |
| 4 | Secretary role management | B | New `MemberRoleAssignment` (memberId, role, cycleId, assignedAt, endedAt). Never mutate history; end the old assignment and open a new one. |
| 5 | Configurable loan period | A + policy | `GroupPolicy.defaultLoanTermMonths` (default 1); per-loan override. |
| 6 | Expenses from Social Fund | policy | New `EXPENSE` ledger type, defaulting to `SOCIAL` via `expenseFundType`, routed through the existing `meetingLedgerRules` mechanism. |
| 7 | Member reports | A | Extend `/reports/member/:id` with loans, outstanding, interest, welfare, share-out history. PDF exists in mobile; add Excel + print for web. |
| 8 | Share-out eligibility | policy | Configurable gate; loans deliberately excluded. |
| 9 | Outstanding loan handling | A + B | `DEDUCT` (today's behaviour), `CARRY_FORWARD` (new loan in next cycle via `carriedFromLoanId`), `MANUAL`. |
| 10 | Meeting archiving | B | Closed-cycle meetings become read-only; reads unaffected. |
| 11 | Member/role management | B | Editable in the active cycle; historical rows pinned to closed cycles. |
| 12 | Unlimited members | — | Nothing to remove. Add cursor pagination and indexes on `Member(groupId)`, `LedgerEntry(groupId, createdAt)`. |
| 13 | Cycle management | B | Delivered by Keystone B. |

---

## 6. Testing

- **Backfill**: reconciliation assertion (Σ loans = Σ disbursements) on a copy
  of production data before it runs anywhere real.
- **Immutability**: writes against a closed cycle must fail; reads must succeed.
- **Share-out with an active loan** must succeed under every
  `outstandingLoanHandling` value — this is the requirement most likely to
  regress silently.
- **Policy defaults** must reproduce current behaviour exactly, proven by
  running the existing suite unchanged against a group with no `GroupPolicy`.
- Extend the existing 211 API / 74 web / 178 mobile tests rather than starting
  a parallel suite.

---

## 7. Sequencing

1. **Keystone B (Cycle)** — additive, no derived data, lowest risk.
2. **Keystone A (Loan)** — the backfill; needs B for `cycleId`.
3. `GroupPolicy` + #5, #6, #8.
4. #2, #9 — the money rules, once loans exist.
5. #4, #11 — role history.
6. #1 location, #7 reports, #12 pagination — independent, parallelisable.

Rough order of magnitude: **4–6 weeks** of focused work including tests, web and
mobile, not a single change.

---

## 8. Open questions

1. ~~**Interest model.**~~ **DECIDED 30 Jul 2026: flat monthly interest on the
   original principal.** So
   `interest = principal x rateBps/10000 x elapsedMonths`, computed on the
   principal throughout — it does NOT reduce as the member repays. Interest is
   derived like outstanding is, never stored.
2. **Google Maps billing.** Places Autocomplete is a paid API; a key with
   referrer restrictions and a quota cap is needed. Which account is billed?
3. **Fines and welfare at share-out** — must an unpaid fine block share-out, or
   be netted off the payout like a loan? #8 says configurable; the *default*
   needs deciding.
4. **`CARRY_FORWARD` semantics** — does a carried loan keep accruing interest
   across the cycle boundary, or freeze at the share-out date?
