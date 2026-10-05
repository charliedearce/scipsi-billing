# P2-10: Tariff archive

## Scope and status

- Task / phase: P2-10 / W29, tariff management.
- Status: Implemented in uncommitted files; authenticated Admin browser acceptance remains.
- Date and contributor: 2026-10-05, Codex.
- Tested files: `apps/api/app/Http/Controllers/Api/V1/AdminTariffController.php`, `apps/api/app/Services/Billing/InvoiceDraftService.php`, `apps/api/app/Services/Billing/InvoicePostingService.php`, `apps/api/tests/Feature/Billing/TariffAndFuelSurchargeTest.php`, `apps/web/src/api/pricing.ts`, `apps/web/src/views/system/tariffs/index.vue`.
- Decision: user approved reasoned Archive rather than permanent deletion for wrong tariffs. Existing invoice pricing references and Admin history must remain.

## Implementation and evidence

- The existing tariff master `is_active` flag backs Archive/Restore. Availability changes require a reason; the tariff update and audit event share a database transaction.
- The Admin catalogue shows active tariff cells by default, switches to archived cells exclusively with Archived only, and prompts for a reason before Archive/Restore.
- New draft calculation rejects archived tariffs through both tariff-code and explicit-version selectors. Invoice posting locks the selected tariff masters and rejects an open draft whose tariff was archived before allocating a document number. The public tariff list already excludes inactive masters. Tariff versions and issued invoice pricing references are untouched.
- No migration or API route change. No legacy VB, numbering, settlement, print-template or posted-invoice write changed.

| Check | Exact command or procedure | Environment / DB / device | Actual result |
| --- | --- | --- | --- |
| Red phase | `php artisan test tests/Feature/Billing/TariffAndFuelSurchargeTest.php --filter=archiving_tariff` | Laravel / PostgreSQL `online_billing_test` | Failed 422 expectation because an archive without a reason returned 200. |
| Audit rollback red phase | `php artisan test tests/Feature/Billing/TariffAndFuelSurchargeTest.php --filter=tariff_archive_rolls_back` | Laravel / PostgreSQL `online_billing_test` | Failed because state remained archived after an audit failure. |
| Saved draft red/green | `php artisan test tests/Feature/Billing/TariffAndFuelSurchargeTest.php --filter=saved_draft_cannot_post` | Laravel / PostgreSQL `online_billing_test` | Failed because the draft posted after archive; passed after adding the posting guard. |
| Pricing and posting suites | `php artisan test tests/Feature/Billing/TariffAndFuelSurchargeTest.php tests/Feature/Invoices/InvoicePostingTest.php` | Laravel / PostgreSQL `online_billing_test` | Passed 35 tests, 218 assertions after saved-draft posting guard. |
| Neighboring invoice checks | `php artisan test tests/Feature/Invoices/InvoiceDraftLifecycleTest.php tests/Feature/Invoices/InvoiceCalculationTest.php tests/Feature/Invoices/TariffApiTest.php` | Laravel / PostgreSQL `online_billing_test` | Passed 15 tests, 128 assertions. |
| Web format | `pnpm.cmd exec prettier --write src/api/pricing.ts src/views/system/tariffs/index.vue` | Node 24 / local | Passed. |
| Web build | `pnpm.cmd build` | Node 24 / local | Passed, including `vue-tsc --noEmit`. |
| PHP format | `php vendor/bin/pint --test app/Http/Controllers/Api/V1/AdminTariffController.php app/Services/Billing/InvoiceDraftService.php app/Services/Billing/InvoicePostingService.php tests/Feature/Billing/TariffAndFuelSurchargeTest.php` | Local PHP | Passed. |
| Diff hygiene | `git diff --check` | Shared checkout | Passed; unrelated existing changes left intact. |

## Acceptance and handoff

- Automated verification: tariff/posting and neighboring invoice tests, PHP format, diff hygiene, Vue typecheck and production web build passed.
- Database workflow: `online_billing_test` through `RefreshDatabase`. Archive/Restore, public/Admin list, audit rollback, both new-draft selectors, saved-draft posting and posted-invoice snapshot preservation covered.
- Browser: authenticated Admin Archive/Restore dialog and responsive layout not yet exercised. The available browser session is a Customer.
- PDF, physical print and Crystal Reports: not changed or tested.
- Business acceptance: archive behavior approved in conversation; final live Admin interaction pending.
- Pre-existing unrelated edits, including Crystal reports, announcements, VIP credit and fuel work, preserved.
- Next: verify Admin Archive/Restore on a disposable tariff and recheck new draft selection and saved-draft posting; rate-version replacement is separate.
