# Customer overpayment credit and refund completion

Date: 2026-10-05

Status: Proposed for user review
Scope: Online billing manual bank deposits and deposited checks; no change to the legacy desktop application

## Decision

When a teller confirms that a customer's bank deposit exceeds the balance of the bills selected in that payment instruction, post the full confirmed amount once. Apply only the amount due to the selected bills and carry the excess as that customer's available payment credit. Use the available credit automatically against the same customer's future posted bills at their next payment checkout. An authorized staff member may record that some or all of the **remaining** credit was refunded outside the system. The application records that completion, but neither sends the refund nor records its payment method.

This is a customer payment credit from confirmed cash, separate from VIP borrowing credit, withholding certificates, discounts, and invoice adjustments. Posted invoices and the original receipt remain immutable.

## Current system and boundary

- `ManualPaymentProofService::postingAllocations()` currently rejects a teller-approved amount above an item's selected balance. The approval command ultimately calls `ReceiptPostingService::post()` with an idempotent proof source.
- `ReceiptPostingService` already records received cash, invoice allocations, and the historical `unapplied_amount` on the immutable receipt. It does not maintain a spendable balance or a disposition for the unapplied amount.
- Bill outstanding calculations currently sum posted receipt allocations. A new credit application must be included consistently in payment selection, customer balances, PPA verification, statements, reports, and correction/reversal checks. A balance must never be reduced merely because an excess receipt exists.
- Existing manual payment instructions snapshot selected full balances and their deadline. Changing the displayed amount due must preserve those snapshots and never rewrite an already issued instruction.

## Ledger and invariants

Add an organization- and customer-scoped payment-credit account in each currency, backed by immutable movements and one credit lot per source receipt. A credit creation references exactly one posted source receipt and equals only its confirmed cash excess. A credit application references one posted invoice and the source credit lot. A refund-completion movement references the source credit lot and the staff actor. Movement uniqueness and source receipt uniqueness make retries harmless. When several lots are available, apply the oldest source receipt first.

Available credit is derived from created amount minus applied, refunded, and valid reversal movements. Store amounts to two decimal places and use decimal arithmetic. Lock the customer credit account and affected rows in a stable order inside the same database transaction as each application or refund completion. Reject negative balances, cross-customer or cross-currency use, and application above the invoice's then-current outstanding amount. Do not edit the original receipt's historical `unapplied_amount` when the credit is later used or refunded. Expose both the original excess and its **current remaining credit** with distinct labels.

Credit created from a pending proof, uncleared check, or disputed/unmatched deposit is unavailable. If bank ownership or customer identity cannot be established, retain the funds as a reconciliation exception rather than granting a spendable credit. Existing idempotent proof approval must never create credit twice.

## Teller approval of an overpayment

The teller enters the bank-confirmed total actually received and assigns at most each selected invoice's outstanding amount to it. The receipt-posting input carries the confirmed cash total separately from the capped bill allocations so the excess appears as received but unapplied cash on the source receipt. The system calculates the excess; the teller cannot choose a different excess amount. The screen shows **confirmed deposit**, **applied to bills**, and **customer credit created** before approval. An approval with inconsistent totals fails. Existing partial payment and withholding rules remain in force; withholding cannot become cash credit.

Receipt posting, invoice allocations, credit creation, proof approval, and audit events commit as one logical transaction. Failure rolls all of them back. The posted receipt and its document artifact continue to show the original received and applied amounts. The customer sees the new credit after approval.

## Automatic future use

At the next customer payment checkout, recalculate the authoritative available credit and posted bill balances under lock. When the customer confirms checkout, apply available same-currency credit immediately to selected bills in invoice-ID order, up to each remaining balance. Show the credit deduction and any cash still due before issuing new payment instructions. If credit fully covers the selected bills, settle them without requesting another deposit. If cash remains due, the new instruction asks only for that cash; its snapshot records the completed credit applications and cash remainder. If the customer never deposits the remaining cash, the credit applications remain valid partial settlement and the cash remainder remains unpaid. No posted invoice amount changes, and the original excess receipt is never counted as a second cash collection.

Credit application is a separate settlement fact linked to the source receipt and target invoice. Shared outstanding/PPA/report projections must count it as settlement, with provenance to the source receipt, while cash collection totals must not count it again. A new receipt or fiscal document must not imply a second cash collection; final document/tax presentation remains subject to accountant review before live financial use.

## Mark refunded

Provide an authorized Admin/accounting action labeled **Confirm refund completed** on available customer payment credit. Show the source receipt, customer, currency, original excess, prior applications/refunds, and remaining amount. Staff enters an amount no greater than the remaining balance and confirms that the money was already returned outside the application. The system records amount, actor, timestamp, and an audit event. It does not ask for or store a refund channel, check number, bank account, or external transaction reference. The confirmation is not a payment instruction or proof that the bank transfer cleared.

The action is idempotent and transactional. It cannot mark more than the current balance, race with a checkout application, or act on a reversed source receipt. A mistaken completion entry requires a separately authorized correction with its own audit history; ordinary staff cannot silently delete or edit it. The customer sees the credit decrease and a **Refund recorded** entry, clearly labeled as staff recorded.

## Reversals and reconciliation

Receipt reversal must not strand a credit already applied or marked refunded. Block source-receipt reversal while its credit has applications or refund completions; an authorized correction must first reconcile those movements with explicit compensating entries. Never silently recreate a refundable balance from a reversed receipt. Existing historical unapplied receipts are excluded from automatic credit creation until reconciled and assigned to a customer; no blind backfill.

Reports must distinguish original received cash, bill settlement from carried credit, available credit liability, and staff-recorded refunds. A refunded credit reduces the liability but does not rewrite the original receipt or pretend the application executed a bank refund.

## Access and UI

- Customer portal: available credit, source receipt, applications, refund-recorded entries, and next checkout deduction.
- Teller proof review: actual confirmed deposit and computed excess before posting. Teller cannot mark a refund merely by approving proof.
- Admin/accounting: scoped credit ledger and refund-completion control with a dedicated permission. API enforces organization scope and permission; UI visibility is supplementary.
- Use the existing Art Design Pro components and light/dark theme tokens. Keep customer and staff wording plain: **Customer payment credit**, not VIP credit.

## Verification and acceptance

Focused API tests cover exact payment, overpayment, multi-bill deposit, withholding exclusion, pending/uncleared proof, duplicate approval, concurrent approvals, automatic future application, full-credit checkout, partial credit plus cash, cross-customer/currency denial, simultaneous application/refund, over-refund rejection, receipt reversal dependency, and idempotent retry. Check that receipt cash totals and invoice balances reconcile without counting credit twice. Run the relevant Vue typecheck/build and authenticated customer/teller/Admin browser checks in light and dark modes. Use only the disposable PostgreSQL test database for financial workflow tests. Record accountant review of document, tax, and general-ledger presentation separately from automated proof; do not treat tests or a build as production financial acceptance.

## Exclusions

No automated bank refund, self-service customer refund request, refund method tracking, VIP borrowing-credit changes, invoice amount changes, historical unapplied-funds backfill, or production data migration in this change.
