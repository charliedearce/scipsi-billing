# P2-01 / P2-10: Bill fuel vs dangerous cargo surcharge toggles

## Scope and status

- Task / phase: P2-01 draft calculation + P2-10 fuel policy consumption
- Status: AUTOMATED_VERIFIED (source + focused PHPUnit); browser smoke open
- Date: 2026-09-22
- Tested revision / uncommitted files:
  - `apps/api/database/migrations/2026_09_22_013000_add_invoice_surcharge_mode.php`
  - `apps/api/app/Services/Billing/InvoiceDraftService.php`
  - `apps/api/app/Http/Controllers/Api/V1/InvoiceDraftController.php`
  - `apps/api/app/Models/Invoice.php`
  - `apps/api/tests/Feature/Invoices/InvoiceDraftLifecycleTest.php`
  - `apps/web/src/components/business/InvoiceBillingItemsGrid.vue`
  - `apps/web/src/views/billing/walk-in/index.vue`
  - `apps/web/src/views/billing/teller-queue/index.vue`
  - `apps/web/src/api/invoices.ts`
- Dependencies: W29 fuel bands remain the fuel % source when Fuel is On
- Decision: User-required 2026-09-22 teller bill-level mutual exclusion (legacy FUEL SURCHARGE / DANGER radios). **Dangerous cargo scales unit rate** = original tariff rate × (percent/100); it is not an additive surcharge on base. Default UI % = 100 (same rate). Fuel and dangerous remain mutually exclusive.

## Behavior

| Mode | Fuel switch | Dangerous switch | Calculation |
| --- | --- | --- | --- |
| `FUEL` (default) | On | Off | W29 band % adjusts the rate to centavos and rounds the final gross to a whole peso; the difference from base gross is the separate fuel component; PPA is zero across the bill |
| `DANGEROUS_CARGO` | Off | On + % | Displayed rate = `input_rate × factor` rounded to centavos; gross = full-precision `input_rate × factor × quantity` rounded to centavos; `fuel_surcharge_amount = 0`; tariff PPA still applies |
| `NONE` | Off | Off | No fuel component even if tariff is fuel-applicable |

Only one of Fuel / Dangerous can be On. Stored fields: `surcharge_mode`, `dangerous_cargo_percent` (factor, e.g. `1.5000` = 150% of tariff). Snapshot keeps `input_rate` (original) and `effective_unit_rate`.

API accepts either a factor ≤ 10 (UI sends `1.5000`) or human percent points > 10 (e.g. `150` → `1.5000`). Max 1000% of tariff.

| Check | Command / procedure | Result |
| --- | --- | --- |
| Focused PHPUnit | `php vendor/phpunit/phpunit/phpunit --filter test_bill_surcharge_mode_fuel_off_and_dangerous_cargo_percent` (host `apps/api`) | **1 passed**, 15 assertions |
| Docker API | `docker cp` InvoiceDraftService + InvoiceDraftController; `optimize:clear` | DONE (not durable until image rebuild) |
| Prettier | changed Vue | not re-run |

## Not tested

- Authenticated teller browser toggle click-through / post
- PDF label wording for dangerous cargo vs fuel
- Accountant acceptance of dangerous cargo tax/PPA ordering

## Next

Browser smoke on Walk-in or Billing Request Queue; optional Document Studio label for dangerous cargo.

## 2026-09-25 VB fuel calculation parity

The user directed online Fuel mode to match the VB form's calculation and confirmed that selecting Fuel also turns off PPA for the whole bill. The published fuel band remains the percentage source. The server now rounds the displayed/saved fuel-adjusted unit rate to centavos, computes the final gross from full-precision quantity × tariff rate × (1 + band fraction), and rounds that gross to the nearest whole peso (half away from zero). The separate fuel component is the difference between rounded gross and the two-decimal base gross. All lines on a Fuel bill have zero PPA; their original tariff PPA classification and the suppression flag remain in the pricing snapshot. Dangerous cargo and None retain their existing calculations. The billing grid now explains whole-peso rounding and PPA suppression.

Changed paths in this follow-up: `apps/api/app/Services/Billing/DecimalCalculatorService.php`, `apps/api/app/Services/Billing/InvoiceDraftService.php`, `apps/api/tests/Feature/Billing/TariffAndFuelSurchargeTest.php`, `apps/api/tests/Feature/Invoices/InvoiceCalculationTest.php`, `apps/api/tests/Feature/Invoices/InvoiceDraftLifecycleTest.php`, `apps/api/tests/Feature/Invoices/InvoiceIssuanceArtifactTest.php`, `apps/web/src/components/business/InvoiceBillingItemsGrid.vue`, `README.md`, `docs/discovery/PRICING_RULES.md`, `docs/PROGRESS.md`, and this evidence file. These files remain uncommitted in a checkout with pre-existing unrelated changes.

Verification on host PHP 8.5 / isolated PostgreSQL `online_billing_test`:

- `php artisan test --compact tests/Feature/Billing/TariffAndFuelSurchargeTest.php tests/Feature/Invoices/InvoiceDraftLifecycleTest.php tests/Feature/Invoices/InvoicePostingTest.php`: 36 tests / 214 assertions passed.
- `php artisan test --compact tests/Feature/Invoices/InvoiceCalculationTest.php`: 6 tests / 40 assertions passed.
- `php artisan test --compact tests/Feature/Fiscal/FiscalInvoiceContractTest.php tests/Feature/Invoices/InvoiceIssuanceArtifactTest.php`: 13 tests / 95 assertions passed after the PPA-layout fixture explicitly selected `NONE` mode.
- The zero-percent band regression passed separately after its new fractional-base assertions. Focused Pint passed on the six changed PHP files. With Node 24 selected, `vue-tsc --noEmit` and `pnpm.cmd build` passed.

No authenticated browser review, issued PDF inspection, accountant approval, or production deployment was performed. Legacy VB and unrelated dirty files were left untouched. Next: review a Fuel draft and posted PDF in the authenticated teller UI, including a bill with a PPA-applicable tariff.

## 2026-09-25 VB dangerous cargo calculation parity

The user next directed online Dangerous cargo to match the VB source. The VB form computes gross from the unrounded tariff rate × factor × quantity, while its display formats adjusted rate and gross to two decimals. Online draft calculation now retains the full adjusted rate for gross, rounds displayed/saved rate to centavos, rounds gross to centavos, and derives VAT/PPA from that gross. Fuel's whole-peso rounding and bill-wide PPA suppression remain exclusive to Fuel. The calculation payload keeps the tariff rate, factor, unrounded adjusted rate and centavo-rounding method. The billing grid explains why displayed rate × quantity may differ by centavos from gross.

The screenshot-derived regression uses tariff rate 107.20, factor 0.92 and quantity 7. API preview and saved draft both show rate 98.62 and gross 690.37; VAT is 82.84 and total charge 773.21. Changed paths in this follow-up: `apps/api/app/Services/Billing/DecimalCalculatorService.php`, `apps/api/app/Services/Billing/InvoiceDraftService.php`, `apps/api/tests/Feature/Billing/TariffAndFuelSurchargeTest.php`, `apps/web/src/components/business/InvoiceBillingItemsGrid.vue`, `README.md`, `docs/discovery/PRICING_RULES.md`, `docs/PROGRESS.md`, and this evidence file. All remain uncommitted alongside pre-existing unrelated work.

`php artisan test --compact tests/Feature/Billing/TariffAndFuelSurchargeTest.php tests/Feature/Invoices/InvoiceDraftLifecycleTest.php tests/Feature/Invoices/InvoiceCalculationTest.php tests/Feature/Invoices/InvoicePostingTest.php` passed 43 tests / 265 assertions on host PHP 8.5 and isolated PostgreSQL `online_billing_test`. Fiscal contract and issuance artifact tests passed another 13 tests / 95 assertions. Focused Pint, Vue typecheck, production build and `git diff --check` passed. Authenticated browser, issued PDF and accountant acceptance remain open.
