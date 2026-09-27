# Release notes: online payments (draft, 27 Sep 2026)

**Status: server LIVE on 27 Sep 2026 (main 9e6f49e; deploy GUARD OK, backup intellicash-20260927-175757). Phone AAB built (Downloads/IntelliCash-2.7.0-build28.aab, SHA-256 844d5a64…d813), not yet uploaded to Play.** Fee rules are inactive and automatic payouts are off. Phone: 2.7.0 (build 28), local database version 15.
Server: migration `20260926190000_payment_settlement_layer`.

## For groups and members

- **Online payments.** Members can pay by M-Pesa or Paystack for:
  - shares;
  - the welfare (social) fund;
  - fines;
  - loan repayments.

  The group receives exactly what the member meant to pay. Intelli-Cash's fee and the payment charge are shown and added on top before the member agrees (for example KSh 500 to the group + KSh 5 + KSh 7 = KSh 512).
- **From the meeting.** Each money action (Buy Shares, Social Fund, Record Fine and Repayment) shows the same payment card, "How is the member paying?", with four options:

  | Option | What happens |
  |---|---|
  | Cash | Recorded now. |
  | M-Pesa | A prompt on the member's phone. Nobody types a code; the receipt comes back from M-Pesa. |
  | M-Pesa Classic | The member paid the Paybill or Till themselves. The treasurer types the code from their SMS, which is checked for shape and sent to the server as the entry's reference. |
  | Paystack | Card or mobile money, by link. |

  - "Card" is no longer offered, because Paystack takes cards. Older records made with it still read.
  - Offline, M-Pesa and Paystack show but cannot be chosen ("Needs the group online"), so Cash and M-Pesa Classic still work.
  - There are no new buttons.
- **Switch M-Pesa and Paystack on or off per group.** The group's own account and IWL admins can do it on the web (group → Payments → Payment options) or on the phone (Settings → Payment providers). A switched-off provider still shows on the payment card, greyed out as "Switched off for this group", and the server refuses it too. Cash and M-Pesa Classic always work. Members and partners can see the switches but not change them.
- **Redesigned Payments page (web).** It opens with a status line: M-Pesa on/off, Paystack on/off, who collects, the settlement account, and passbook payments. Below that are four tabs:
  - Payment options;
  - Request a payment;
  - Settlement account;
  - Own provider accounts.
- **From the web.** The group can ask a member to pay from the Request a payment tab.
- **From the passbook.** Members can pay from their own passbook, if their group switches this on.
- **Late payments aren't lost.** A payment that completes after the phone stopped waiting is still added to the group's books. The meeting then offers to add it to the phone's book, once.
- **Logo buttons.** Payment methods show the M-Pesa and Paystack logos. The public site's hero also shows both.

## For IWL administrators

- **Settings → Payment fees:** IWL fee bands and provider charges, with a live "what a member would pay" calculator. Edits create new versions; old payments keep their fees.
- **Payments → Reconciliation:** collected money split into group money, IWL fees and provider charges, plus anything that needs a person:
  - held payments, which can be released or reversed with a written reason;
  - payments verified but not posted;
  - payouts that are unknown or failed.
- **Group → Payments:**
  - how the group collects: through Intelli-Cash, or into its own account;
  - which providers it uses, and whether members may pay from the passbook;
  - its settlement account (a second administrator must approve it, and payouts wait 24 hours after approval).
- **Automatic payouts are OFF** (`ENABLE_AUTOMATED_SETTLEMENT`). Batches are built for review only.

## Console consistency fixes

- **Form fields.** Fields on several pages were bare, unstyled browser inputs. They now match the rest of the console: the payment request, provider credentials, fee rules, group policy, and the From/To filters on Reconciliation and Payment fees.
- **Card spacing.** Cards on the group Payments and Share cycles pages sat edge to edge, because the grid class they used was never defined. It is now.
- **Sidebar highlight.** On Reconciliation the sidebar lit up both "Payments" and "Reconciliation". Only the most specific item lights up now.
- **Integrations setup panel.** Long field names overlapped in the setup panel; they now wrap.
- **Error and 404 pages.** Their buttons were unstyled links; they now use the standard buttons.

## Accuracy fixes found along the way

- **Missing fund accounts.** Groups imported through the FLOURISH onboarding script had no fund accounts, so their first entry failed. The migration adds the missing funds at zero, and posting now creates a missing standard fund instead of failing.
- **Non-existent SAVINGS fund.** The expense-fund setting offered a "SAVINGS"/"Shares fund" that doesn't exist; shares are held in the loan fund. It now offers only real funds.
- **Reconciliation dates.** The reconciliation date range used the UTC day, so it hid the evening's payments in Nairobi.
- **M-Pesa labels.** The M-Pesa account reference is now kept to Safaricom's 12-character limit.
- **Paystack signatures.** Paystack webhook signatures are now checked against the exact bytes received, using the key of whoever collected the payment.

## Rehearsed on a copy of production (27 Sep 2026)

See `docs/MIGRATION_REHEARSAL_2026-09-27.md`.
- **GUARD OK:** 166 of 166 ledger entries unchanged, no schema drift, 0 differences in any group's or member's figures.
- **Missing funds added:** the 40 groups with no fund accounts gain all five, at zero.
- **Rollback-safe:** the current production code can still read the migrated database.

## Before switching things on

1. **Deploy order.** Deploy the server, then ship phone 2.7.0.
2. **Fees.** Switch fee rules on only after the phone update is out. Older phones are asked to update before paying online, because they can't show the charges.
3. **Payouts.** Turn on automatic payouts only after:
   - regulatory clearance for holding members' money;
   - Safaricom B2C/B2B access for IWL's shortcode;
   - a funded payout account.
4. **Translations.** The new phone wording is translated into English and Swahili. Gikuyu, Luo and Kiembu show the Swahili text until a speaker translates it.
