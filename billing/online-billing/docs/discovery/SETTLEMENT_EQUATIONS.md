# P3-01 Settlement equations

Status: `AUTOMATED_VERIFIED` for the pure calculation contract, 2026-09-20. This document defines the server-side monetary boundary used by gateway confirmation, manual-payment approval, check clearance, VIP repayment and receipt posting. It does not approve taxpayer-specific accounting treatment or create a receipt.

## Amount classes

- **Invoice total** is the posted invoice `total_charge_amount`. Existing invoice discounts, PPA components, fuel surcharge and VAT are already captured in the issued invoice. Settlement must not recalculate or rewrite them.
- **Confirmed cash** is verified cash, bank transfer, gateway money, or a cleared check. A browser return, uploaded image or uncleared check is not confirmed cash.
- **Approved withholding** is the amount from an approved certificate application with remaining certificate capacity. It is a settlement component, not a commercial discount and does not reduce invoice gross/VAT/tax snapshots.
- **Pending amounts** remain visible but do not reduce the invoice balance: pending proof, pending gateway confirmation and checks awaiting clearance. Rejected amounts do not settle anything.

## Calculation

All values use non-negative PHP decimal strings with at most two fractional digits and BCMath; no binary floating-point arithmetic is used.

```text
prior_confirmed = prior_cash + prior_approved_withholding
prior_applied = min(prior_confirmed, invoice_total)
balance_before_current = max(invoice_total - prior_applied, 0)

current_confirmed_cash = confirmed cash-like tenders + cleared checks
current_approved_withholding = approved certificate applications

cash_applied = min(current_confirmed_cash, balance_before_current)
balance_after_cash = max(balance_before_current - cash_applied, 0)
withholding_applied = min(current_approved_withholding, balance_after_cash)

current_applied = cash_applied + withholding_applied
applied_total = prior_applied + current_applied
balance_after = max(invoice_total - applied_total, 0)
overpayment = max(prior_confirmed + current_confirmed_cash
                   + current_approved_withholding - invoice_total, 0)
```

Cash is applied before withholding so an overpaid invoice does not consume a withholding certificate unnecessarily. Any confirmed surplus is returned as unapplied/overpayment for the later reconciliation policy; it is not silently discarded or used to change the invoice.

Settlement state is derived from confirmed applied amounts:

| State | Rule |
| --- | --- |
| `UNPAID` | Nothing is applied |
| `PARTIAL` | Some amount is applied and a positive balance remains |
| `PAID` | Balance is zero and no overpayment exists |
| `PAID_WITH_OVERPAYMENT` | Balance is zero and confirmed money exceeds the invoice |

Pending clearance remains a separate flag. A check in `PENDING_CLEARANCE` cannot produce `PAID`; the same check may settle only after it is represented as `CLEARED`. Pending withholding evidence cannot reduce the balance or consume certificate capacity.

## Certificate-capacity contract

Each current certificate may appear once per calculation. An approved application must satisfy:

```text
0 < requested_amount <= certificate_remaining_amount
certificate_remaining_after = certificate_remaining_amount - requested_amount
```

Duplicate certificate IDs and capacity overuse are rejected before a receipt action. P3-02 must re-read and lock the actual certificate rows before applying these values; this pure service does not provide concurrency protection.

## Checkout boundary

Regular-customer checkout continues to require the full current remaining balance under W23. `validateRegularCustomerCheckoutAmount()` rejects customer-entered partial checkout. This does not reject actual partial funds arriving through a verified manual, gateway or teller route; those funds are calculated as `PARTIAL` and remain available for reconciliation.

## P3-02 integration boundary

Receipt posting must lock and revalidate the source/payment identity, customer or credit account, affected invoice IDs in ascending order, and then withholding certificate IDs in ascending order, following the lifecycle lock contract. It must call this calculator against the locked current values, allocate only the returned applied amounts, consume certificate capacity once, and commit receipt/tender/allocation/audit/outbox rows atomically. Number allocation and collection-receipt/OR artifacts belong to P3-02.

This task deliberately does not decide refund/credit treatment, provider fee accounting, late-charge allocation, provisional check acknowledgments, tax-document treatment, or the exact BIR/accounting receipt form. Those remain explicit P3-01/P3-02/P3-10/P3-11 review inputs.
