# P2-07: Shared request progress timeline

## Scope and status

- Task / phase: P2-07 customer billing-request follow-up (first pass of the request → bill → payment → receipt tracking review; user chose "finding the status of a request" as the first delay to fix)
- Status: AUTOMATED_VERIFIED; browser checked on existing local data; Posted-tab Part paid tag not re-seen after the last change
- Date and contributor: 2026-09-30, implementation session
- Tested revision, or exact uncommitted files: uncommitted files listed below
- Dependencies checked: existing request statuses, `lifecycleTimeline()`, `billing_request_events`, `cateringTeller()`, linked invoice settlement, `portalBillDetail`
- Decision IDs and approvals, where applicable: W22 (original priority kept after correction), W25 (paid follows a posted receipt, not an uploaded proof). No numbering, money, settlement or cancellation rule changed.

## Implementation and evidence

- Legacy source paths/symbols and confidence: not a legacy VB path. Online request statuses and timestamps are the source.
- Changed paths and resulting behavior:
  - `apps/api/app/Models/BillingRequest.php` — read-only `progress()` projection (state, current step, owner, required action, next step, six steps with stored times) added to `withLifecyclePayload()`; `withCustomerLifecyclePayload()` drops `events`, `internal_notes`, `draft_invoice_id`, `draft_invoice`, `assignment_heartbeat_at`. Paid means every non-replaced linked bill has a posted receipt. A `BILLING_IN_PROGRESS` request with an already-posted bill shows Bill ready as current ("post the next bill").
  - `apps/api/app/Http/Controllers/Api/V1/CustomerBillingRequestController.php` — index, show, remove-document, submit, resubmit and cancel now return the customer payload; index/show eager-load posted receipt allocations for linked invoices.
  - `apps/api/app/Services/Billing/ManualPaymentProofService.php` — `portalBillDetail` returns `request_progress`; the owning request falls back to the multi-bill link table when the bill is not the request's primary `invoice_id`.
  - `apps/api/tests/Feature/Billing/BillingRequestQueueTest.php` — two new progress tests, a multi-bill progress assertion, and shipment fields in the two posting fixtures that were failing on the shipment rule.
  - `apps/web/src/components/business/BillingRequestProgress.vue` — new shared panel and compact row form using gray/theme/semantic tokens and `ArtSvgIcon`.
  - `apps/web/src/api/billingRequests.ts`, `apps/web/src/api/payments.ts` — `BillingRequestProgress` type, `progress`, `request_progress`.
  - `apps/web/src/views/billing/requests/index.vue` — Now column; detail drawer panel replaces the Requested/Bill approved/Paid block.
  - `apps/web/src/views/billing/teller-queue/index.vue` — panel above the open request; Now column on Waiting and Posted; Posted payment tag shows Part paid when only some bills have a receipt.
  - `apps/web/src/views/payments/customer/index.vue` — bill detail shows the request panel when a request is linked, else the old timestamps.
- Financial, numbering, permission, and transaction effects: none. The projection reads stored state only; no write path, status, table or migration was added. Posted invoices are unchanged (asserted `POSTED` after the paid check); proof upload still does not show Paid.
- API/schema/template compatibility and migration effects: no migration. Staff payloads gain `progress`. Customer billing-request responses lose staff-only keys listed above (no customer screen used them). Submit/resubmit/cancel customer responses are now the lifecycle array rather than the raw model; existing keys are kept. `portalBillDetail.billing_request_id` is now filled for secondary bills of a multi-bill request.

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Queue file | `php artisan test --compact tests/Feature/Billing/BillingRequestQueueTest.php tests/Feature/Payments/ManualPaymentProofWorkflowTest.php` | Host PHPUnit, PostgreSQL `online_billing_test` | Passed 29 tests / 357 assertions (includes the two posting tests that previously errored on the shipment rule) |
| Adjacent flows | `php artisan test --compact tests/Feature/WalkIn/WalkInBillingAndClaimsTest.php tests/Feature/Corrections/DocumentCorrectionRequestTest.php` | Same | Passed 29 tests / 173 assertions |
| Chat | `php artisan test --compact tests/Feature/Realtime/ConversationBillingChatTest.php` (run with the proof suite) | Same | 1 failure, `test_find_or_create_for_billing_request_is_idempotent` asserting teller participant in `ConversationService`; that service was not changed here |
| PHP format | `vendor/bin/pint --dirty --format agent` | Host | Passed |
| Vue typecheck | `pnpm.cmd exec vue-tsc --noEmit` | `apps/web` | Passed |
| Production build | `pnpm.cmd build` | `apps/web` | Passed |
| Browser, customer | Request Billing list and drawer, My Bills detail, 390px width | Vite `:3006`, host API `:8000`, seeded customer | Now column showed "Next available teller", "Teller Maria Santos", "Customer · Pay the bill in My Bills", and completed rows. Drawer for REQ-202609-I0CNM1 showed Complete with all six step times. SI-0000000020 detail showed the request panel with Paid not complete. At 390px, `scrollWidth` 390 = `innerWidth` 390. |
| Browser, teller/admin | Billing Request Queue | Same, seeded teller and admin | Admin Waiting and Posted lists showed the Now column. Teller open claim Ticket #1009 (two posted bills plus a draft) showed "With Teller Maria Santos · Post the next bill, then finish with this customer" with Bill ready current. Nothing was claimed, posted or released. |

## Acceptance and handoff

- Automated verification: as above.
- Database workflow and database used: PHPUnit `online_billing_test`; local dev database read only through the browser.
- Browser / PDF / physical-print verification: browser as above; dark theme only. No PDF or print path changed.
- Business or operational acceptance: not requested.
- Not tested / blockers: the Posted-tab Part paid tag after its final change; light theme; a needs-correction request on screen (covered by test only).
- Pre-existing changes preserved: yes.
- Next exact task/action: second pass from the review — measure wait-for-teller, handling time, correction rounds, bill-to-instruction, proof review and funds-to-receipt from existing timestamps, then choose the next handoff.
- PROGRESS.md updated: yes
