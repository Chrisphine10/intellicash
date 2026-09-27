# Payment & settlement layer (build 1, Sep 2026)

This build covers the fee engine, verification, ledger posting, reconciliation and settlement, for M-Pesa (Daraja) and Paystack. KCB BUNI is the next build.

## The rule

The member names what the **group** should receive. The IWL platform fee and the provider's charge are added on top. The provider collects the total. The server confirms the payment with the provider, then posts **only the group amount** to the group's ledger. It then settles that amount to the group, unless the money already landed in the group's own account.

```
quote  →  charge  →  provider says paid  →  server asks provider  →  VERIFIED  →  LEDGER_POSTED  →  settled
                                                      ↘ mismatch → HELD (nothing posted; an admin decides)
```

For every payment:

- `amountCents = groupAmountCents + platformFeeCents + providerFeeCents`
- the rules used to reach those figures are frozen in `feeSnapshotJson`.

## Where things live

| Concern | Code |
|---|---|
| Fees (pure) | `apps/api/src/services/fee-engine.ts` |
| Collection mode, quotes | `apps/api/src/services/payment-settings-service.ts` |
| Verify → post, exactly once | `apps/api/src/services/group-payment-service.ts` |
| Settlement, destinations | `apps/api/src/services/settlement-service.ts` |
| Group and member routes | `apps/api/src/routes/group-payments.ts` |
| Admin routes | `apps/api/src/routes/payment-admin.ts` (IWL admins only) |
| Callbacks | `apps/api/src/routes/payments.ts` |
| Web: fees | `/dashboard/settings/payments` |
| Web: group payments | `/dashboard/groups/:id/payment-providers` |
| Web: reconciliation | `/dashboard/payments/reconciliation` |

## Switches (env)

| Variable | Default | Meaning |
|---|---|---|
| `ENABLE_PAYMENT_NETWORK_CALLS` | false | Off = mock gateway. Nothing is sent to Safaricom or Paystack. |
| `ENABLE_AUTOMATED_SETTLEMENT` | false | Off = settlement batches are built but never paid out. |
| `SETTLEMENT_AUTO_MAX_CENTS` | 5,000,000 | A batch above this waits for an admin's approval. |
| `SETTLEMENT_DESTINATION_COOLOFF_HOURS` | 24 | No payouts to a newly approved account for this long. |
| `PAYMENT_QUOTE_TTL_SECONDS` | 600 | How long a quote stays valid. |

Fee rules are seeded **inactive**. With no active rule, payments carry no fees, which is exactly how they behaved before this build.

Once any fee rule is on, a phone older than the fee-aware release gets `426 APP_UPDATE_REQUIRED` and cannot pay online. It cannot show the charges, so it must not be allowed to collect them. Ship the new phone build before switching fees on.

## What a member can pay, and where it lands

Every kind of payment is booked by the ledger's own rule table (`ledgerRuleFor` in `routes/groups.ts`). The same table decides meetings, web entries and online payments, so they can never disagree.

| Payment | Ledger type | Fund | Checked before charging |
|---|---|---|---|
| Shares | SHARE_PURCHASE | INTERNAL_LOAN (loan fund) | a whole number of shares at the group's share value |
| Welfare (social fund) | SOCIAL_CONTRIBUTION | SOCIAL | equals the group's social-fund amount |
| Fine | FINE_COLLECTION | SOCIAL | above zero |
| Loan repayment | LOAN_REPAYMENT | INTERNAL_LOAN | the member owes something, and the payment is not more than that |

Every payment belongs to a member (`MEMBER_REQUIRED`).

**Who can start one:**
- **The group, on the phone:** from the meeting's own actions, each with Cash / M-Pesa / Paystack.
  - Buy Shares
  - Social Fund (switching a member to paid asks how they paid)
  - Record Fine
  - Repayment
- **The group, on the web:** from the group's Payments page ("Request a payment from a member").
- **A member:** from their passbook ("Pay into my group"), when the group allows it.

## Fund types: what the audit found and fixed (27 Sep 2026)

- **Every group holds all five funds:** INTERNAL_LOAN, SOCIAL, EXTERNAL_LOAN, GRANT and VSLF.
  - `import-flourish-onboarding.ts` created groups with **none**, so a group's first entry failed with FUND_ACCOUNT_NOT_FOUND.
  - The script now creates them, and the migration adds any missing ones at zero.
  - `resolveFundAccount` also creates a missing standard fund instead of failing. An account with no ledger rows holds zero, so no balance changes.
- **No "SAVINGS" fund exists.** Shares are held in the loan fund.
  - The expense-fund setting offered SAVINGS on the web and the phone. It now offers the real funds only, and reads an old SAVINGS choice as INTERNAL_LOAN.
  - The setting is still **not applied**: welfare expenses are always paid from SOCIAL. Whether expenses should be payable from the loan fund is a product decision.
- **The demo seed's ledger rows had no cycle.** The first new entry therefore opened a cycle, and cycle-scoped screens (the passbook) dropped all earlier history.
  - The seed now stamps its rows into the group's current cycle, as the production backfill did.
  - Its open meetings stay unstamped, so a share-out can still close the cycle.

## Payments nobody completed

- **Expiry:** every 15 minutes, a payment still waiting after 24 hours is checked with the provider once more, then marked EXPIRED.
- **Late money:** a success that arrives after expiry is still verified and posted. Expiry only means nobody confirmed it in time.
- **Paid but unconfirmed:** a payment the provider said was paid but that is not yet confirmed (SUCCESSFUL) is never expired.

## No double counting with the phone

The phone records the purchase in its own book and syncs it later. There is only ever one ledger entry per payment, whichever side records it first:

- **Server first** (the usual case): the phone's entry is linked to the existing one.
  - The phone sends `groupPaymentId`, and the server answers with the entry it already posted.
  - Phones already in the field (2.6.x) send no payment id, but they put the receipt or the payment id in `externalReference`. That identifies the same payment.
- **Phone first**: the phone's entry is written and claims the payment (`GroupPayment.ledgerEntryId`, which is unique). When the payment is verified, nothing new is posted.

A payment that completes after the phone stopped waiting is still posted by the server. The meeting hub then shows it under "online payments not yet in this book", so the phone can add it with its id.

## Settlement

- Settlement applies to SYSTEM-mode payments only.
- **Settlement account.** The group side proposes the account. A **different** IWL admin must approve it; the server refuses the proposer, with `SAME_PERSON_APPROVAL`. Approving a new account retires the previous one, and the cool-off period then applies.
- **Batching.** The loop runs every 15 minutes. It builds one settlement per group from payments that are posted and not yet settled.
- **Payout rail.** M-Pesa phone numbers are paid by B2C. Paybills and tills are paid by B2B (`BusinessPayBill` or `BusinessBuyGoods`). Bank and mobile-money accounts are paid by Paystack transfer to a recipient created when the account is approved.
- **Timeouts are never retried.** A timeout or an unanswered request becomes **UNKNOWN**. It is resolved only by asking the provider (Paystack transfer verify, or the Daraja Transaction Status query) or by an admin with the receipt. B2C and B2B are not idempotent, so resending could pay the group twice.
- **Failures can be retried.** A definite failure can be sent again from the reconciliation screen.

### Before switching `ENABLE_AUTOMATED_SETTLEMENT` on

1. **Clearance.** Confirm that holding and paying out members' money through an IWL account is cleared by the regulator (CBK / payment-service-provider rules). This is a business decision, not a code one.
2. **Initiator credentials.** Daraja B2C/B2B needs `MPESA_INITIATOR_NAME` and `MPESA_SECURITY_CREDENTIAL` for IWL's shortcode, with the B2C/B2B products enabled.
3. **Funding.** The B2C/B2B utility account must be funded from the collection account; Safaricom calls this "funds movement". An unfunded payout fails definitively and can be retried. The build does **not** check the float before paying.
4. **Paystack transfers.** These need transfers enabled on the Paystack account. If Paystack asks for an OTP on transfers, switch that off for API use.

## Own-account groups

When a group collects into its own Daraja or Paystack account, nothing is settled. The IWL fee inside that money is recorded as **RECEIVABLE**, meaning the group owes it, and the reconciliation totals show it. A Paystack subaccount/split would collect it at source; that is not built yet.

## Deploying

- **Migration.** `20260926190000_payment_settlement_layer` rebuilds `GroupPayment`, adding columns and backfilling them. Existing completed payments become `COMPLETED_LEGACY`: the phone posted them, and the server never re-posts or settles them.
- **Rehearsal.** Rehearse the migration on a copy of production first (see `MIGRATION_REHEARSAL_2026-09-25.md`).
- **Data guard.** The data guard must stay OK afterwards.
