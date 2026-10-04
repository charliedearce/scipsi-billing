# P2-10 / P2-01: Import usable legacy tariff rates and lock RATE/DISC on encode

## Scope and status

- Task / phase: P2-10 (versioned tariffs) + P2-01 encode grid. Not P5-01 archive activation.
- Status: IN_PROGRESS (host PHPUnit + Docker seed + Walk-in save verified; Teller queue not re-encoded; API image not rebuilt)
- Date and contributor: 2026-09-21, agent session
- Tested revision, or exact uncommitted files: uncommitted. `apps/api/database/migrations/2026_09_21_210000_import_legacy_tariff_rate_components.php`, `apps/api/app/Services/Billing/LegacyTariffCatalog.php`, `apps/api/database/seeders/data/legacy-tariffs.json`, `apps/api/database/seeders/DatabaseSeeder.php`, `apps/api/app/Models/Tariff.php`, `apps/api/app/Services/Billing/InvoiceDraftService.php`, `apps/api/app/Http/Controllers/Api/V1/InvoiceDraftController.php`, `apps/api/app/Http/Controllers/Api/V1/AdminTariffController.php`, `apps/api/app/Http/Controllers/Api/V1/TariffController.php`, `apps/api/tests/Unit/Billing/LegacyTariffCatalogTest.php`, `apps/api/tests/Feature/Billing/LegacyTariffCatalogSeedTest.php`, `apps/api/tests/Feature/Invoices/InvoiceDraftLifecycleTest.php`, `apps/web/src/components/business/InvoiceBillingItemsGrid.vue`, `apps/web/src/api/invoices.ts`, `apps/web/src/api/pricing.ts`, `apps/web/src/views/billing/walk-in/index.vue`, `apps/web/src/views/billing/teller-queue/index.vue`
- Dependencies checked: W06 still forbids posting archive history as live invoices. W29 tax/PPA/fuel tags on imported rates copy the seeded ARR_DOM / STEV_DOM / ARR_FOR demo policy (code-inferred), not an accountant-approved matrix. Legacy `tbl_settings.discount` / XXXX unit-marker percent discount is not implemented; DISC is `0.00`.
- Decision IDs and approvals, where applicable: user asked to import all usable `tbl_tarrif` cells and auto-fill RATE/DISC from domestic/foreign × arrastre/stevedoring/other. Unique tariff identity is now `(organization_id, tariff_code, service_type, route_type)`.

## Implementation and evidence

- Legacy source paths/symbols and confidence: SQL Server `billing.tbl_tarrif` (database-verified, 204 cargos / 586 non-zero varchar rate cells). VB `Modules/BillingAddMod.vb` `vCargo()` looks up `t_code` then fills rate from `t_dom_arrastre` / `t_dom_stevedor` / `t_dom_other` / `t_for_arrastre` / `t_for_stevedor` / `t_for_other` using route + type; `t_scode` is the legacy service reference shown on CARGO.
- Changed paths and resulting behavior:
  - Each non-zero legacy cell becomes one `tariffs` + `tariff_versions` row (`tariff_code` = `t_code`, `legacy_t_scode` = `t_scode`).
  - Draft create/update always uses the effective version rate and `discount_amount` `0.00`; client `unit_rate` / `discount_amount` are ignored.
  - Encoder SERVICE is Arrastre / Stevedoring / Other; CARGO is searchable `t_code — t_desc · tscode`; RATE and DISC. are read-only.
- Financial, numbering, permission, and transaction effects: no posting, numbering, or archive activation. Draft #15 was saved and not posted. Historical GROSS was not copied.
- API/schema/template compatibility and migration effects: dropped unique `(organization_id, tariff_code)` and added `tariffs_org_code_service_route_unique`. Added nullable `legacy_t_scode`, `legacy_t_sname`, `cargo_class`. Testing seed skips the JSON (same pattern as vessels).

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Pint | `php vendor/bin/pint` on changed PHP | `apps/api` | PASS |
| Prettier | `pnpm.cmd exec prettier --write` on grid/API/walk-in/teller Vue | `apps/web` | PASS |
| PHPUnit | `php vendor/phpunit/phpunit/phpunit --filter "LegacyTariffCatalogTest\|LegacyTariffCatalogSeedTest\|InvoiceDraftLifecycleTest"` | host PHP, `online_billing_test` | 10 tests / 64 assertions PASS |
| Docker migrate | `docker compose exec -T api php artisan migrate --force --no-interaction` | `scipsi-online-billing-dev-api-1` + `online_billing_dev` | `2026_09_21_210000_import_legacy_tariff_rate_components` DONE |
| Docker seed | one-off `LegacyTariffCatalog->seedInto` of `legacy-tariffs.json` (script deleted after run) | same | `created=586 total=589` (3 demo + 586 imported) |
| Authenticated Walk-in save | New walk-in Tariff Import Check; ACCORD / 21 / IN / DOMESTIC / TARIFF IMPORT; Create draft; SERVICE Stevedoring; CARGO `CD01D — Docking & Undoc · 40005`; Save. Did not Post. | Vite `:3006`, Docker API `:18000`, `online_billing_dev` | Draft #15. UNIT `00GRT`, RATE `51.00`, DISC `0.00`, GROSS `51.00`. Computations: TOTAL `51.00`, DISC `0.00`, VAT `6.12`, LESS PPA SHARE `0.00`, NET `51.00`, DUE TO SCIPSI `57.12`. |
| Teller queue | Not opened for this slice | Same | Grid shares the same component; live claim encode not clicked. |

## Acceptance and handoff

- Automated verification: catalog expand/parse + seedInto + draft ignores client rate/discount + ambiguous `tariff_code` requires `service_type`. `vue-tsc --noEmit` still blocked by pre-existing `src/store/modules/user.ts:86` TS2352.
- Database workflow and database used: `online_billing_test` for PHPUnit; `online_billing_dev` for Docker seed and Draft #15. SQL Server was the export source only.
- Browser / PDF / physical-print verification: Walk-in Billing save and computations. Teller queue claim/save not exercised. PDF/print not in scope.
- Business or operational acceptance (who/date/evidence), if required: not obtained. Imported VAT/PPA/fuel tags remain code-inferred from demo seeds pending accountant review.
- Not tested / blockers: posting Draft #15 (or #11/#13/#14); Teller queue under a live claim; XXXX/% discount; fuel-multiplier conversion; Docker API image rebuild (files were `docker cp` + migrate/seed).
- Pre-existing changes preserved: unrelated dirty tree left in place, including the pre-existing BillingRequestQueueTest `BillingRequestInvoice` class miss.
- Next exact task/action: optional Teller-queue encode with an imported cargo; accountant tariff/PPA/fuel matrix still open. Do not post the layout-check or tariff-import drafts. Do not commit unless asked.
- PROGRESS.md updated: yes
