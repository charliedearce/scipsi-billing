# P2-01-SHIPMENT: Required bill vessel, voyage, notes, type and route

## Scope and status

- Task / phase: P2-01 draft header extension (also used by P2-07 teller queue and P2-09 walk-in)
- Status: IN_PROGRESS (implementation + automated checks + authenticated walk-in draft create; Docker image rebuild and posting not done)
- Date and contributor: 2026-09-21, agent session
- Tested revision, or exact uncommitted files: uncommitted. Core paths: `apps/api/app/Services/Billing/InvoiceShipment.php`, `Vessel.php`, `VesselController.php`, `InvoiceDraftService.php`, `InvoicePostingService.php`, `WalkInBillingService.php`, `WalkInBillingController.php`, `InvoiceDraftController.php`, `TariffController.php`, `routes/api.php`, migration `2026_09_21_180000_create_vessels_and_invoice_shipment_fields.php`, `DatabaseSeeder.php` + `database/seeders/data/vessels.json`, `tests/TestCase.php`, `tests/Feature/Invoices/InvoiceShipmentTest.php`, `apps/web/src/components/business/InvoiceShipmentFields.vue`, `apps/web/src/api/vessels.ts`, `apps/web/src/api/invoices.ts`, `apps/web/src/api/walkInBilling.ts`, `apps/web/src/views/billing/walk-in/index.vue`, `apps/web/src/views/billing/teller-queue/index.vue`
- Dependencies checked: legacy bill header uses searchable `tbl_vessel`, numeric voyage, IN/OUT, Domestic/Foreign driving tariff rates. Online tariffs already `DOMESTIC`/`FOREIGN`.
- Decision IDs and approvals, where applicable: no numbering/tax/settlement rule change. One bill route must match every line tariff `route_type`.

## Implementation and evidence

- Legacy source paths/symbols and confidence: `frmBillings` vessel autocomplete from `tbl_vessel` (database-verified 951 names exported to `vessels.json`); `bt_voyage` numeric, `bt_type` I/O, `bt_route` D/F selecting `t_dom_*` vs `t_for_*` (code-inferred from VB modules, aligned with existing online tariff `route_type`).
- Changed paths and resulting behavior:
  - PostgreSQL `vessels` master (org-scoped, searchable). Invoice stores `vessel_id` plus frozen `vessel_name`, `voyage` (1–10 digits), `notes` (alphanumeric plus space `. , ' / -`), `movement_type` IN/OUT, `route_type` DOMESTIC/FOREIGN.
  - HTTP draft create/update require those fields. Posting refuses an incomplete header. Mixed domestic/foreign lines on one bill are rejected.
  - Walk-in create-draft now requires the same header. Queue prepare can still create an empty draft; save/post require the header.
  - Teller Walk-in Billing and Billing Request Queue show a searchable vessel select; tariff options filter to the selected route.
- Financial, numbering, permission, and transaction effects: no number consumption on draft create. `GET /api/v1/vessels` uses `billing:draft`. Vessel name on the invoice is a snapshot and is not recalculated later.
- API/schema/template compatibility and migration effects: `online_billing_dev` applied `2026_09_21_180000_create_vessels_and_invoice_shipment_fields`. Seed loaded 951 vessels. Docker API received a live `docker cp` of `app/` and `routes/api.php` (not durable until image rebuild).

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| InvoiceShipment + draft/post/walk-in/tax/pricing | `php vendor/phpunit/phpunit/phpunit` on InvoiceShipmentTest, InvoiceDraftLifecycleTest, WalkInBillingAndClaimsTest, TaxEvidenceVerificationTest, TariffAndFuelSurchargeTest, InvoicePostingTest, then InvoiceIssuanceArtifactTest, BuyerProfileValidationTest, FiscalInvoiceContractTest | Host PHPUnit, `online_billing_test` on 127.0.0.1:55432 | PASS 87 tests / 450 assertions (70+17) |
| Pint | `vendor\bin\pint --dirty` | Host | Ran; also formatted unrelated already-dirty PHP. Do not treat as a full-tree format. |
| vue-tsc | `pnpm.cmd exec vue-tsc --noEmit` | `apps/web` | FAIL pre-existing `src/store/modules/user.ts:86` TS2352; no new errors on shipment files |
| Prettier | `pnpm.cmd exec prettier --write` on changed Vue/TS shipment files | `apps/web` | PASS |
| Dev migrate + seed | `php artisan migrate --force`; `php artisan db:seed --force` | Host artisan, `online_billing_dev` | Vessels migration DONE; 951 vessels |
| Authenticated walk-in draft | Open Walk-in Billing, search `HOND`, select HONDURAS, voyage `102`, IN, Domestic, notes `Test notes 102`, Create invoice draft | Vite `:3006`, Docker API `:18000`, `online_billing_dev` | Draft #11 stored with vessel snapshot. Tariff list showed only ARR_DOM / STEV_DOM. Not posted. |

## Acceptance and handoff

- Automated verification: focused shipment/draft/post suites above. Full repository `test-all.ps1` not re-run.
- Database workflow and database used: `online_billing_test` for PHPUnit; `online_billing_dev` for migrate/seed and the browser draft.
- Browser / PDF / physical-print verification: authenticated Walk-in Billing draft create and domestic tariff filter. Teller queue save/post not re-clicked in this session. PDF/print not in scope.
- Business or operational acceptance (who/date/evidence), if required: not obtained.
- Not tested / blockers: Docker API image rebuild; posting the new draft; teller-queue prepare/save under a live claim; full suite; `vue-tsc` blocked by unrelated `user.ts`.
- Pre-existing changes preserved: unrelated dirty tree (P5-01, multi-bill, chat, payments) left in place.
- Next exact task/action: rebuild Docker API so the vessel route survives container recreate; optional teller-queue browser pass; do not post live SQL import.
- PROGRESS.md updated: yes
