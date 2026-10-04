# P2-07 extension: Multi-bill billing requests

## Scope and status

- Task / phase: P2-07 (Customer billing requests and fair teller queue) — multi-invoice extension
- Status: AUTOMATED_VERIFIED (browser acceptance open)
- Date and contributor: 2026-09-21 / agent
- Tested revision: uncommitted working tree (HEAD `92af63a` plus listed paths)
- Dependencies checked: existing P2-07 queue/bill-ready path; My Bills multi-select payment groups unchanged
- Decision rule (session): first linked posted invoice → `BILL_READY`; `complete=false` keeps teller claim for more drafts/posts; `complete=true` releases assignment

## Implementation and evidence

- Schema: `billing_request_invoices` join table with unique `invoice_id`, backfill from legacy `billing_requests.invoice_id`
- Models: `BillingRequestInvoice`; `BillingRequest.invoices` / `invoiceLinks`; legacy `invoice_id` remains primary/first ready
- Queue service: prepare additional drafts after partial ready; `completeBillReady(..., bool $complete)`; release/cancel respect posted links
- API: `POST .../mark-bill-ready` accepts optional `complete` (default true); customer/teller payloads include `invoices[]` / `invoice_count`
- Portal: My Bills source attachments resolve request via join or legacy FK
- Vue: teller **Post & add another** / **Post & finish claim**; customer Request Billing lists all linked invoices

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Queue suite | `php vendor/bin/phpunit --configuration=phpunit.xml tests/Feature/Billing/BillingRequestQueueTest.php --do-not-cache-result` | Host PHP / `online_billing_test` PostgreSQL | 13 tests / 112 assertions passed (includes `test_multi_bill_request_keeps_claim_until_complete_true`) |
| Migration | `docker compose exec api php artisan migrate --force --no-interaction` | Docker API + postgres | `2026_09_21_160000_create_billing_request_invoices_table` applied |
| Docker API rebuild | `docker compose --profile app build api` then `up -d api` | compose.dev | Image rebuilt; route `mark-bill-ready` present |
| Vue typecheck | `pnpm.cmd exec vue-tsc --noEmit` | `apps/web` | No new errors from this change; pre-existing `src/store/modules/user.ts:86` TS2352 remains |
| Prettier | `pnpm.cmd exec prettier --write` on changed billing Vue/API client files | `apps/web` | Pass |

## Acceptance and handoff

- Automated verification: queue suite 13/112 pass; Docker migrate + API rebuild done
- Database workflow and database used: host PHPUnit `online_billing_test`; Docker migrate on `online_billing_dev`
- Browser / PDF / physical-print verification: not run this session
- Business or operational acceptance: not claimed
- Not tested / blockers: authenticated teller multi-post and customer multi-invoice Pay UI in browser; full `pnpm build` blocked by unrelated user.ts TS2352
- Pre-existing changes preserved: unrelated legacy/P3/P5/chat work left untouched
- Next exact task/action: soft-refresh teller queue and customer Request Billing; post two invoices under one claim (`add another` then `finish`); confirm both appear on Request Billing and My Bills
- PROGRESS.md updated: yes
