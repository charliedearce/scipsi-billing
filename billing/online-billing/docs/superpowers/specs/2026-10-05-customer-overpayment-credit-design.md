# Customer overpayment credit and automatic bill deduction

Date: 2026-10-05

Status: Revised for user review

Scope: Online billing manual bank deposits and deposited checks; no change to the legacy desktop application.

## Decision

When a teller confirms that a customer's deposit exceeds the combined balance of the bills in that payment instruction, post the full confirmed amount once. Apply only what is due to those bills and carry the excess as customer payment credit. At the next checkout, automatically deduct available credit from the **combined amount due** for that customer's selected bills. No refund action is part of this feature.

This is confirmed customer money held for future bills, separate from VIP borrowing credit, withholding certificates, discounts, and invoice adjustments. Posted invoices and the source receipt remain immutable.

## Current system and boundary

- `ManualPaymentProofService::postingAllocations()` rejects teller-approved amounts above each selected bill balance. Approval calls `ReceiptPostingService::post()` using an idempotent proof source.
- `ReceiptPostingService` records received cash, bill allocations, and historical `unapplied_amount` on the receipt, but it has no spendable credit balance.
- Bill outstanding calculations currently sum posted receipt allocations. Credit application must be included consistently in payment selection, customer balances, PPA verification, statements, reports, and correction/reversal checks.
- An issued manual payment instruction keeps its original balance and deadline snapshot. Later credit activity must not rewrite it.

## Ledger and invariants

Add an organization- and customer-scoped payment-credit account in each currency, backed by immutable movements and one credit lot per source receipt. Credit creation references one posted receipt and equals only its confirmed cash excess. An application references the source credit lot and one posted target invoice. Source uniqueness and application idempotency prevent double creation or use. When several lots are available, apply the oldest source receipt first.

Available credit is created amount minus applied and valid reversal movements. Store amounts to two decimal places and use decimal arithmetic. Lock credit and invoice rows in a stable order in the application transaction. Reject negative balances, cross-customer/currency use, and amounts above a bill's current outstanding balance. Do not edit the source receipt's historical `unapplied_amount` when credit is later used. Show original excess and **current remaining credit** as different values.

Pending proof, uncleared checks, and disputed or unmatched deposits cannot create spendable credit. If bank ownership or customer identity is uncertain, retain the funds for reconciliation without assigning credit. Retried proof approval must not create credit twice. Historic unapplied receipts receive no automatic backfill.

## Teller approval of an overpayment

The teller enters the actual bank-confirmed deposit total. The receipt-posting input carries that total separately from bill allocations, which remain capped at the selected outstanding balances. The system calculates the excess; the teller cannot enter a different credit amount. Before approval, show **confirmed deposit**, **applied to bills**, and **customer credit created**. Reject inconsistent totals. Existing partial-payment and withholding rules remain in force; withholding never becomes cash credit.

Receipt posting, bill allocations, credit creation, proof approval, and audit commit as one logical transaction. Failure rolls all back. The source receipt and artifact show the original cash received, amount applied, and excess. Customer credit appears only after successful approval.

## Automatic future deduction

At the next customer payment checkout, recalculate available credit and posted bill balances from the server. Show one deduction against the aggregate selection: **total bills − credit applied = amount still to pay**. When the customer confirms checkout, apply same-currency credit immediately to the selected bills in invoice-ID order, up to each outstanding balance, using credit lots oldest first. The screen may show per-bill application details, but the amount requested from the customer is the aggregate remainder.

If credit fully covers the selected bills, settle them without another deposit and keep any unused credit for later bills. If cash remains due, issue a payment instruction for only that cash; its snapshot includes the completed credit applications and remaining cash. If the customer never deposits the cash remainder, the credit applications remain valid partial settlement and the rest remains unpaid. No posted invoice amount changes, and the original excess receipt is not counted as a second cash collection.

Credit application is a separate settlement fact linked to the source receipt and target invoice. Shared outstanding, PPA, and report projections count it as settlement with source provenance; cash collection totals do not count it again. A new fiscal document must not imply another cash collection. Accountant review of document, tax, and general-ledger presentation remains an acceptance gate before live financial use.

## Reversals and reconciliation

Block reversal of a source receipt while its credit has applications. An authorized correction must first reconcile the applications with explicit compensating entries; ordinary staff cannot delete or edit posted movements. Never silently recreate spendable credit from a reversed receipt. Reports distinguish original received cash, settlement from carried credit, and remaining customer credit liability.

## Access and UI

- Customer portal: available credit, source receipt, application history, and automatic deduction from the combined amount due at checkout.
- Teller proof review: actual confirmed deposit and calculated excess before posting.
- Admin/accounting: read-only organization-scoped credit ledger for reconciliation.
- Use existing Art Design Pro components and light/dark theme tokens. Label the balance **Customer payment credit** so it cannot be confused with VIP borrowing credit.

## Verification and acceptance

Focused API tests cover exact payments, overpayment, multi-bill deposits, withholding exclusion, pending/uncleared proof, duplicate/concurrent approval, aggregate deduction, full-credit checkout with remaining credit, partial credit plus cash, cross-customer/currency denial, simultaneous checkouts, reversal dependency, and idempotent retries. Verify that receipt cash totals, bill balances, and credit liability reconcile without counting money twice. Run relevant Vue typecheck/build and authenticated customer/teller/Admin browser checks in light and dark modes. Use only disposable PostgreSQL for financial workflow tests. Report accountant, browser, and production acceptance separately from automated tests.

## Exclusions

No refund feature, VIP borrowing-credit changes, invoice amount changes, historic unapplied-funds backfill, or production data migration. Exceptional returns of funds remain an external accounting matter; this software scope makes no claim about the payer's legal rights.
