# P2-07: Customer queue wait estimate

## Scope and status

- Task / phase: P2-07 customer billing-request follow-up
- Status: AUTOMATED_VERIFIED for the estimate contract; queued-request browser acceptance remains open
- Date and contributor: 2026-09-29, implementation session
- Tested revision, or exact uncommitted files: uncommitted files listed below
- Dependencies checked: existing queue position, claim and first `BILL_READY` events, assignment heartbeat
- Decision IDs and approvals, where applicable: W22 rank remains an estimate, not a promised start time. No SMS change.

## Implementation and evidence

- Legacy source paths/symbols and confidence (inferred / database-verified / owner-confirmed): not a legacy VB path. Online queue timestamps are the source.
- Changed paths and resulting behavior:
  - `apps/api/app/Services/Billing/BillingQueueEstimateService.php` — median claim-to-first-bill minutes for the location, active teller count, wait and ready estimates
  - `apps/api/app/Models/BillingRequest.php` — `queue_estimate` on the lifecycle payload
  - `apps/api/app/Providers/AppServiceProvider.php` — request-scoped estimate service so a list does not repeat the sample query
  - `apps/api/tests/Feature/Billing/BillingRequestQueueTest.php` — four estimate cases
  - `apps/web/src/api/billingRequests.ts` — payload type
  - `apps/web/src/views/billing/requests/index.vue` — requests ahead, start estimate, and bill-ready estimate
- Financial, numbering, permission, and transaction effects: none. Claim order, tickets, and invoice posting are unchanged. The estimate is not sent by SMS.
- API/schema/template compatibility and migration effects: no migration. Customer and teller lifecycle payloads gain `queue_estimate`. It is null after the request leaves the queue or teller preparation.

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Estimate cases | `php artisan test --compact tests/Feature/Billing/BillingRequestQueueTest.php --filter=test_queue_estimate` | Host PHPUnit, PostgreSQL `online_billing_test` | Passed 4 tests / 24 assertions |
| Full queue file | `php artisan test --compact tests/Feature/Billing/BillingRequestQueueTest.php` | Same | 15 passed, 2 errors. Both errors are `InvoiceShipment` requiring vessel, voyage, notes, and route before posting in `test_prepare_draft_and_bill_ready_completion_links_posted_invoice_idempotently` and `test_multi_bill_request_keeps_claim_until_complete_true`. Those paths were not changed for this estimate. |
| PHP format | `vendor/bin/pint --dirty --format agent` | Host | Passed |
| Vue typecheck | `pnpm.cmd exec vue-tsc --noEmit` | `apps/web`, Node | Passed |
| Production build | `pnpm.cmd build` | `apps/web` | Passed, built in 33.10s |
| Browser | Customer Request Billing at `http://127.0.0.1:3006/#/my-billing-requests` | Local Vite and host API `:8000`, desktop width | Signed in as the seeded customer. Queue column shows an em dash for needs-correction and bill-ready rows. Opening the needs-correction request shows ticket and teller and does not show a queue estimate. No request was `QUEUED` or being prepared, so the ahead / minutes copy was not seen on screen. |

## Acceptance and handoff

- Automated verification: four new estimate tests passed. Pint, Vue typecheck, and production build passed.
- Database workflow and database used: PHPUnit `online_billing_test`. No migration.
- Browser / PDF / physical-print verification: browser check above. No PDF or print path.
- Business or operational acceptance (who/date/evidence), if required: not requested.
- Not tested / blockers: a live queued request with enough recent completed bills, so the on-screen “about N minutes” copy is unconfirmed. Narrow viewport was not rechecked after the drawer opened. The two pre-existing posting errors remain.
- Pre-existing changes preserved: yes.
- Next exact task/action: open a queued request on Request Billing and confirm “requests ahead”, start time, and bill-ready time.
- PROGRESS.md updated: yes
