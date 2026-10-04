# P2-02-SERIES-ADMIN: Admin document-series prefix management

## Scope and status

- Task / phase: P2-02 follow-up, W37.
- Status: IN_PROGRESS; source/build checks passed, authenticated workflow acceptance remains open.
- Date and contributor: 2026-09-24, Codex.
- Tested revision, or exact uncommitted files: uncommitted worktree.
- Dependencies checked: Existing `document_series.prefix`, `lock_version`, `document_numbers.formatted_number`, P2-02 allocator, audit event service, named permissions, and Admin routing.
- Decision IDs and approvals: W37, user-confirmed 2026-09-24. Prefix and no-prefix configuration is approved for future number allocations only. Accountant/taxpayer confirmation of actual fiscal series format and scope remains open.

## Implementation and evidence

- Legacy source paths/symbols and confidence: Target behavior is code-defined; no legacy numbering behavior or fiscal approval was inferred.
- Changed paths: Admin document-series API/controller and permission migration; permission catalog seed; Admin System route and Art Design Pro page; README/foundation decision, PHASES, PROGRESS, and this evidence file.
- Resulting behavior: Administrators can view scoped invoice/receipt series and change only the prefix. Empty prefix is an explicit option. Updates require a reason and current `lock_version`, are serialized against organization series, and append immutable before/after audit in the same transaction. Overlapping future ranges sharing the prefix and already-used matching visible numbers are rejected. Previous invoices, receipts, document-number rows, and PDF snapshots are not rewritten.
- Financial, numbering, permission, and transaction effects: The controller changes only `document_series.prefix` and increments its lock version; it does not allocate, consume, restore, or reset a number. `document_series:manage` gates both endpoints. Database uniqueness on `(organization_id, formatted_number)` remains the final issuance guard. The prefix-permission migration was applied only to local `online_billing_dev`; no series value/counter changed.
- API/schema/template compatibility and migration effects: No numbering schema change. One additive permission migration inserted the permission and attached it to the system Administrator role. Document Studio continues rendering the frozen issued number field.

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| PHP syntax | `php -l app/Http/Controllers/Api/V1/AdminDocumentSeriesController.php`; `php -l database/migrations/2026_09_24_100000_add_document_series_management_permission.php`; `php -l routes/api.php`; `php -l database/seeders/DatabaseSeeder.php` | Host PHP, online-billing | All reported no syntax errors. |
| PHP formatting | `vendor/bin/pint --test app/Http/Controllers/Api/V1/AdminDocumentSeriesController.php database/migrations/2026_09_24_100000_add_document_series_management_permission.php` | Host PHP | Pint passed. |
| Route registration | `php artisan route:list --path=admin/document-series --json` | Host Laravel API | GET and PUT routes registered behind active-user and `document_series:manage` middleware; PUT also has idempotency middleware. |
| Permission migration | `php artisan migrate --path=database/migrations/2026_09_24_100000_add_document_series_management_permission.php`; `php artisan migrate:status --path=database/migrations/2026_09_24_100000_add_document_series_management_permission.php`; `php artisan tinker --execute="echo \App\Models\Permission::where('name','document_series:manage')->first()->roles()->where('name','Administrator')->count();"` | Local PostgreSQL `online_billing_dev` via `127.0.0.1:55432` | Migration completed; status `Ran`. Read-only Tinker query confirmed one system Administrator role has the permission. No document-series data was changed. Initial read-only Tinker probes had PowerShell quote-loss parse errors; the corrected probe returned `1`. |
| Vue typecheck | `pnpm.cmd exec vue-tsc --noEmit` | Node/Vite project under `apps/web` | Passed. |
| Production build | `pnpm.cmd build` | Node/Vite project under `apps/web` | Passed; generated PWA bundle with 410 precache entries. Vite reported the existing mixed static/dynamic import warning for `store/modules/realtime.ts`. |
| Whitespace | `git diff --check` | Worktree | Passed; Git printed line-ending conversion warnings for already-dirty files. |
| Automated tests | PHPUnit/API feature tests | Not run | No PHPUnit or endpoint mutation test was run in this pass. |

## Acceptance and handoff

- Automated verification: PHP syntax, Pint, route registration, additive permission migration/query, Vue typecheck/build and diff check passed. Test suite not run.
- Database workflow and database used: Only the permission migration/query ran against local development database `online_billing_dev`. Prefix-update API workflow was not exercised; invoice/receipt numbering was untouched.
- Browser / PDF / physical-print verification: No authenticated browser session was available in the current computer-use inventory. No PDF, saved artifact, or physical print changed.
- Business or operational acceptance: Accountant/taxpayer must confirm the production series format/scope before operators apply a fiscal prefix change. The UI does not claim BIR series registration or approval.
- Not tested / blockers: Authenticated API authorization/update/collision behavior, rendered Admin interaction/responsive browser acceptance, PHPUnit regression tests, and business approval of exact fiscal values.
- Pre-existing changes preserved: Yes. Existing dirty edits in `README.md`, `docs/PROGRESS.md`, `apps/api/routes/api.php`, `apps/api/database/seeders/DatabaseSeeder.php`, `apps/web/src/router/modules/system.ts`, and unrelated work were retained; only focused additions were made.
- Next exact task/action: Run focused numbering authorization/collision/idempotency feature coverage against an isolated PostgreSQL test database and inspect `/system/document-series` in an authenticated Admin browser; keep actual fiscal prefixes unchanged until accountant/taxpayer confirmation.
- PROGRESS.md updated: Yes; `P2-02-SERIES-ADMIN` remains `IN_PROGRESS` pending these acceptance gates.
