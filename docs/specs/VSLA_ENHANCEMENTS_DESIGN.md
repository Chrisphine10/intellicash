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

## 8. Decisions (all resolved 30 Jul 2026)

1. **Interest** — FLAT MONTHLY ON THE ORIGINAL PRINCIPAL. Does not reduce as
   the member repays. Implemented and tested in `domain/loan-math.ts`.

2. **Fines and welfare at share-out — NET OFF THE PAYOUT, do not block.**
   An unpaid fine or welfare obligation never bars a member from share-out; it
   is deducted from what they receive. This makes the `shareOutRequires*`
   flags in `GroupPolicy` unnecessary — there is no eligibility gate to
   configure. What is configurable is the *deduction order*, not permission.
   A member may end with a negative payout, which is a debt to the group and
   already how the mobile share-out behaves.

3. **Outstanding loans — NET OFF AT SHARE-OUT. NO CARRY FORWARD.**
   `outstandingLoanHandling` collapses to DEDUCT. `CARRY_FORWARD` is dropped,
   so nothing populates `Loan.carriedFromLoanId` — leave the column unused
   rather than migrate it away, in case the policy changes; it costs nothing
   and dropping a column is a table rebuild.
   Consequence: a loan is never inherited by the next cycle. At share-out
   every loan is settled against the payout and closed, and a shortfall
   becomes a negative payout, not a new loan. This is simpler than the design
   originally assumed and REMOVES the cross-cycle interest question entirely.

---

## 9. Welfare fund: expenses and share-out residue (NEW)

Requested 30 Jul. Supersedes the narrower "#6 expenses from social fund".

**The rule: welfare expenses are paid out of the welfare fund, and what
remains at the end of the cycle is what gets shared.** The welfare fund is not
a separate pot that survives the cycle — it is spent down during the cycle and
distributed at share-out.

### Backend module

- **New ledger type `WELFARE_EXPENSE`**, routed by the existing
  `meetingLedgerRules` to the SOCIAL fund as a **DEBIT**. Reuses the whole
  existing path — signing, cycle stamping, and the overdraw guard, which
  already refuses an expense larger than the fund holds.
- **`WelfareExpense`** record alongside the ledger entry, carrying what a
  ledger line cannot: category, payee, approving member, and supporting note.
  Same relationship as `Loan` has to the ledger — a projection with context,
  never a second source of truth for the amount.
- **Endpoints** `GET/POST /groups/:id/welfare-expenses`, scoped and
  cycle-stamped like every other write.
- **Share-out** takes the welfare fund's CLOSING balance — contributions minus
  expenses — and distributes it. The existing share-out already splits welfare
  equally as an option; it must now read the post-expense balance rather than
  gross contributions.

### Ordering at share-out (the part to get right)

For each member, in this order:

1. Start with the pro-rata share of the savings/loan pool.
2. Add their share of the REMAINING welfare fund.
3. Deduct outstanding loan principal and interest.
4. Deduct unpaid fines and welfare obligations.

A negative result is a debt to the group, recorded and carried as such — not
suppressed to zero, and not converted into a loan.

### UI

- Web and mobile need a **Welfare** section listing expenses with a running
  balance, and an add-expense form. Mobile card order is already
  social fund → shares → fines → loans, so welfare expenses belong under the
  social fund card.
