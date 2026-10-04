# P2-07 / W26: Unpaid linked bills View + Edit on teller queue

## Scope and status

- Task / phase: P2-07 queue UX + W26 unpaid issued-bill handling
- Status: SOURCE_IMPLEMENTED (browser smoke open; Docker API copy for settlement flags)
- Date: 2026-09-22
- Tested revision / uncommitted files:
  - `apps/api/app/Models/Invoice.php` (`hasPostedSettlement`)
  - `apps/api/app/Models/BillingRequest.php` (invoice payload settlement fields)
  - `apps/api/app/Http/Controllers/Api/V1/TellerQueueController.php` (eager-load allocations)
  - `apps/web/src/api/billingRequests.ts`
  - `apps/web/src/views/billing/teller-queue/index.vue`
  - `apps/web/src/views/payments/corrections/index.vue` (query prefill)
  - `README.md` W26, `docs/PROGRESS.md`
- Decision: User 2026-09-22 — on the linked SI list, view always; edit while unpaid; paid view-only. Issued edit is Correct bill (W26/W07), not direct overwrite of POSTED invoices.

## Behavior

| Settlement | View | Edit |
| --- | --- | --- |
| UNPAID (no POSTED receipt allocation) | PDF via artifacts download | Opens `/document-corrections` with document number; approval still does not rewrite fiscal facts until accountant matrix |
| PAID | PDF | Hidden |

Draft encode workspace still edits unposted drafts until Post.

## Not tested

- Authenticated browser click-through on SI-0000000011/12/13
- Docker durable image rebuild
- Correct-bill execution matrix (still gated)

## Next

Browser smoke on active claim with multiple linked SIs; `docker cp` API model/controller files for live Unpaid/Paid tags.
