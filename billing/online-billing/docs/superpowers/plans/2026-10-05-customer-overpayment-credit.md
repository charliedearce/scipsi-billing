# Customer Overpayment Credit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Carry teller-confirmed bank-deposit excess as customer payment credit and automatically deduct it from the combined amount due at the customer's next bill checkout.

**Architecture:** Keep the original receipt immutable. A new credit lot and append-only movement ledger record its excess and later bill applications. The existing manual instruction transaction applies credit before freezing the remaining cash request; all balance projections include credit applications without counting new cash.

**Tech Stack:** Laravel/PHP, PostgreSQL, BCMath, PHPUnit, Vue 3/TypeScript, Art Design Pro.

**Spec:** `docs/superpowers/specs/2026-10-05-customer-overpayment-credit-design.md`

## Global Constraints

- All amounts are two-decimal decimal strings; no float money calculations.
- Scope credit by organization, customer, and currency; never mix it with VIP borrowing credit or withholding.
- Posted invoices, source receipts, allocations, and document artifacts stay immutable; applications are separate settlement facts.
- Apply oldest source receipt first, then selected invoice ID; lock customer, credit lots, and invoices in that order.
- No refund UI, endpoint, movement, or method field. No historic unapplied-funds backfill.
- Use the disposable PostgreSQL test database. Preserve unrelated working-tree changes and report browser/accountant acceptance separately.

## Review Focus

1. A teller enters ₱1,050 confirmed cash for one ₱1,000 bill: the system posts ₱1,050 cash once, settles ₱1,000, creates exactly ₱50 credit, and a retry creates nothing new. Task 2 tests this.
2. Two checkout requests spend the same ₱50 concurrently: at most ₱50 applies in total. Task 3 tests this.
3. A ₱50 credit covers one bill and partly covers another: the bank instruction requests only the aggregate remainder and does not require proof for the covered bill. Task 3 tests this.
4. A source receipt with spent credit is targeted for reversal: execution rejects it without changing bill balances. Task 4 tests this.
5. A customer switches accounts or currencies: no credit from the first account/currency appears or applies to the second. Tasks 1 and 5 test this.

---

## File map

- `apps/api/database/migrations/2026_10_05_030000_create_customer_payment_credits.php`: credit lots, movement ledger, and payment-group cash/credit snapshot fields.
- `apps/api/app/Models/CustomerPaymentCredit.php`, `CustomerPaymentCreditMovement.php`: typed relations and fillable fields.
- `apps/api/app/Services/Billing/CustomerPaymentCreditService.php`: create, query, and apply credit under transaction locks; return per-invoice applications.
- `apps/api/app/Services/Billing/InvoiceSettlementService.php`: one authoritative receipt-plus-credit outstanding calculation used by command paths and projections.
- Existing `ReceiptPostingService`, `ManualPaymentProofService`, `PaymentPolicyService`, reversal/correction, statement/report/PPA/VIP services: integrate those two services without rewriting their other financial rules.
- Existing customer and teller Vue pages plus `apps/web/src/api/payments.ts`: show credit and the aggregate deduction using native Art Design Pro components.

### Task 1: Credit ledger and authoritative balance

**Files:** Create the migration, two models, `CustomerPaymentCreditService.php`, and `InvoiceSettlementService.php`; test in `apps/api/tests/Feature/Payments/CustomerPaymentCreditTest.php`.

**Interfaces:** `CustomerPaymentCreditService::createFromReceipt(Receipt $receipt, User $actor): ?CustomerPaymentCredit`; `availableForCustomer(int $organizationId, int $customerId, string $currency): string`; `lockAvailableLots(Customer $customer, string $currency): Collection`; `applyToLockedInvoices(User $actor, Customer $customer, Collection $lots, Collection $invoices, string $sourceKey): array{total:string,by_invoice:array<int,string>}`; `reverseUnusedForReceipt(Receipt $receipt, User $actor): void`; `applicationsForInvoices(array $invoiceIds): array<int,string>`. `InvoiceSettlementService::outstanding(Invoice $invoice): string` and `appliedForInvoices(array $invoiceIds): array<int,string>` include posted receipt allocations plus valid credit applications.

- [ ] **Step 1: Write failing tests** for creation from confirmed cash excess only, source-receipt uniqueness, available balance, oldest-lot use, cross-customer/currency rejection, and exact two-decimal arithmetic.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/Payments/CustomerPaymentCreditTest.php`; confirm the tests fail because the ledger/service is absent.
- [ ] **Step 3: Implement migration, models, and services** with foreign keys, a unique source receipt, unique movement source keys, indexed organization/customer/currency reads, and immutable `CREATED`/`APPLIED` entries. Sum signed entries for available balance under locked lot rows; do not add a redundant writable balance.
- [ ] **Step 4: Run** the focused test; require all cases to pass, then run `vendor/bin/pint --dirty --format agent` on the touched PHP files.
- [ ] **Step 5: Commit** only this task's migration, models, services, and test.

### Task 2: Confirm the full bank amount and create excess credit once

**Files:** Modify `apps/api/app/Http/Controllers/Api/V1/ManualPaymentProofController.php`, `apps/api/app/Services/Billing/ManualPaymentProofService.php`, `apps/api/app/Services/Billing/ReceiptPostingService.php`, and `apps/api/tests/Feature/Payments/ManualPaymentProofWorkflowTest.php`.

**Interfaces:** Teller approval accepts `confirmed_cash_total` as a decimal string. `ReceiptPostingService::post()` accepts an optional confirmed `unallocated_tender` for `MANUAL_PAYMENT_PROOF` only. `CustomerPaymentCreditService::createFromReceipt()` is called in the proof approval transaction after posting; the approval fingerprint includes the confirmed total.

- [ ] **Step 1: Write failing tests** for ₱1,050 cash against ₱1,000 selected bills, two-bill excess, zero excess, a reused bank reference, duplicate approval, uncleared check, and withholding that cannot create cash credit.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/Payments/ManualPaymentProofWorkflowTest.php`; confirm the new cases fail for the expected reason.
- [ ] **Step 3: Implement** receipt-level confirmed excess as one tender outside capped invoice allocations. Derive excess as confirmed cash total minus cash allocated to selected bills, reject negative/inconsistent totals, and create credit only from `cash_received_amount − cash_applied_amount` on a posted receipt. Preserve existing receipt source idempotency and bank-reference claim behavior.
- [ ] **Step 4: Run** the focused proof and receipt tests; require one receipt, one credit lot, exact amounts, and no changed issued artifact on retry. Run Pint on touched PHP.
- [ ] **Step 5: Commit** only this task's files.

### Task 3: Apply credit to the aggregate checkout and request only remaining cash

**Files:** Modify `apps/api/app/Services/Billing/PaymentPolicyService.php`, `ManualPaymentProofService.php`, `ReceiptPostingService.php`, `apps/api/app/Models/PaymentGroup.php`, `apps/api/app/Models/PaymentGroupItem.php`, `apps/api/app/Http/Controllers/Api/V1/PaymentPolicyController.php`, and `apps/api/tests/Feature/Payments/PaymentPolicyAndInstructionTest.php`.

**Interfaces:** `PaymentGroup` gains decimal `credit_applied_amount` and `cash_due_amount`, plus `CUSTOMER_CREDIT` route/payment method for a credit-only group. `gross_selected_amount` remains the pre-credit sum for the existing gateway-threshold rule. `PaymentGroupItem.requested_amount` is the residual cash balance per bill; fully covered bills have credit movements but no proof-required item. A fully covered selection returns a `SETTLED` credit-only group with no bank instruction or proof.

- [ ] **Step 1: Write failing tests** for combined ₱1,200 bills less ₱100 credit yielding ₱1,100 cash due, full coverage with leftover credit, multi-bill allocation order, concurrent double spend, stale bill version, idempotent retry, and account/currency isolation.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/Payments/PaymentPolicyAndInstructionTest.php`; confirm the new cases fail.
- [ ] **Step 3: Implement** one customer-lock → credit-lot-lock → invoice-lock transaction. Validate original full-balance selections, apply credit on confirmed checkout, then create either a residual manual group or a settled credit-only group. Keep the threshold comparison based on pre-credit gross for positive-cash groups; a fully covered group needs no provider route. Snapshot gross, credit, cash due, and per-bill applications; issue deadline/SMS only for a positive cash instruction. Update `ManualPaymentProofService` and `ReceiptPostingService` to use credit-inclusive outstanding when reviewing/posting residual cash.
- [ ] **Step 4: Run** checkout, proof, and receipt focused tests; require exact cash request, no extra receipt for credit-only settlement, and no duplicate application. Run Pint on touched PHP.
- [ ] **Step 5: Commit** only this task's files.

### Task 4: Reconcile all balances, reports, and reversals

**Files:** Modify `ManualPaymentProofService.php`, `ReceiptPostingService.php`, `ReceiptReversalService.php`, `DocumentCorrectionRequestService.php`, `InvoiceCorrectionExecutionService.php`, `AccountStatementService.php`, `VipCreditAgingService.php`, `VipCreditService.php`, `LateChargeAssessmentService.php`, `apps/api/app/Http/Controllers/Api/V1/DocumentCorrectionRequestController.php`, `apps/api/app/Services/Reporting/{AdminDashboardService,BillingCollectionsReportService,PpaShareReportService}.php`, and their existing focused tests.

**Interfaces:** Each outstanding/paid projection uses `InvoiceSettlementService` or an equivalent set-based query with the same posted-receipt-plus-credit definition. Credit entries retain source receipt and target invoice provenance; receipt cash totals stay unchanged.

- [ ] **Step 1: Write failing regression tests** for customer/teller/PPA balance agreement, statement as-of credit inclusion, dashboard and collections cash-versus-credit totals, VIP aging, correction eligibility, unused-credit removal on source reversal, and reversal rejection after credit use.
- [ ] **Step 2: Run** the affected payment, PPA, statement, reporting, correction, and reversal tests; confirm the new cases fail before integration.
- [ ] **Step 3: Replace each receipt-only outstanding or settlement check** with the shared definition; keep set-based summaries for multi-row reports. On reversal of a source with unused credit, append one `REVERSED` movement that consumes its remainder. Block source-receipt reversal with applied credit and block invoice correction while credit settlement exists. Preserve historical receipt values and artifacts.
- [ ] **Step 4: Run** those focused suites and Pint; require no double-counted cash and no negative outstanding balance.
- [ ] **Step 5: Commit** only this task's files.

### Task 5: Customer and teller UI, scoped credit reads

**Files:** Modify `apps/api/routes/api.php`, add `apps/api/app/Http/Controllers/Api/V1/CustomerPaymentCreditController.php`, modify `apps/web/src/api/payments.ts`, `apps/web/src/api/system-manage.ts`, `apps/web/src/views/payments/customer/index.vue`, `apps/web/src/views/payments/teller/index.vue`, `apps/web/src/views/customer/accounts/index.vue`, and the relevant API feature test. Read `.agents/skills/art-design-pro-ui/SKILL.md` and its project map before editing Vue.

**Interfaces:** `GET /api/v1/portal/customers/{customerId}/payment-credit` returns available amount and source/application history after active customer-link authorization. `GET /api/v1/admin/customers/{customerId}/payment-credit` returns the same read-only ledger with `customer_accounts:view` and organization scope; show it in the existing Customer Accounts detail drawer. The teller approval payload includes `confirmed_cash_total`; customer checkout response includes gross, credit applied, and cash due.

- [ ] **Step 1: Write failing API tests** for linked-customer access, other-customer denial, currency isolation, and one reconciled credit-history response.
- [ ] **Step 2: Run** the new API tests; confirm authorization and data cases fail.
- [ ] **Step 3: Implement the scoped read API** and adapt existing teller/customer surfaces. Show teller verified total/excess before approval; show customer available credit and `total bills − credit = cash due` before checkout, then use server-returned amounts after confirmation. Use existing Art Design Pro theme tokens and controls; no refund action.
- [ ] **Step 4: Run** API tests, `pnpm.cmd exec vue-tsc --noEmit`, and `pnpm.cmd build`; format only changed Vue/TS files with the repository Prettier command.
- [ ] **Step 5: Commit** only this task's API/UI/test files.

### Task 6: Cross-flow verification and handoff evidence

**Files:** Update existing `docs/PROGRESS.md` and `README.md`; create `docs/evidence/P3-05-CUSTOMER-PAYMENT-CREDIT.md` using `templates/TASK_EVIDENCE.md` as required by the repository instructions for a nontrivial implementation.

- [ ] **Step 1: Run** the focused API suites from Tasks 1–5 against disposable PostgreSQL, plus the Vue typecheck/build. Record exact commands, database, counts, and any failures.
- [ ] **Step 2: Exercise** authenticated customer, teller, and Admin browser paths at desktop and narrow widths in light/dark mode. Record which paths were actually seen; do not label source inspection as browser acceptance.
- [ ] **Step 3: Record** the accounting/document/GL review gate and any untested physical/Crystal/legacy paths separately from automated results in existing progress/evidence docs.
- [ ] **Step 4: Review** the branch diff for unrelated changes, credit double-counting, organization leaks, and deadlock-prone lock-order changes; fix any concrete findings and rerun the affected proof.
- [ ] **Step 5: Commit** the handoff documentation without staging unrelated dirty files.
