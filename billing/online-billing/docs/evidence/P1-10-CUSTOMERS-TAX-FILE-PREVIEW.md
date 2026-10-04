# P1-10 / P2-08: Admin Customers tax verification file preview

## Scope and status

- Task / phase: P1-10 (Customer Accounts Admin UI), P2-08 (tax evidence attachment visibility)
- Status: AUTOMATED_VERIFIED (API + UI source); browser preview **verified** 2026-09-22 via Playwright against Vite `:3007` (Cursor `cursor-ide-browser` MCP could not retain tabs in this session)
- Date and contributor: 2026-09-22
- Tested revision, or exact uncommitted files:
  - `apps/api/app/Http/Controllers/Api/V1/CustomerAccountAdminController.php`
  - `apps/api/tests/Feature/Registration/CustomerAccountAdminControllerTest.php`
  - `apps/web/src/views/customer/accounts/index.vue`
  - `docs/PROGRESS.md`
  - `docs/evidence/P1-10-CUSTOMERS-TAX-FILE-PREVIEW.md`
  - `docs/evidence/p1-10-tax-file-preview-pw.png` (browser screenshot)
- Dependencies checked: existing Admin Tax Evidence Review View file path (`downloadPrivateFile` → `GET /api/v1/files/{id}/download`); Art Design Pro UI skill
- Decision IDs and approvals, where applicable: W32 identity safeguards unchanged; P2-08 review workflow unchanged; no financial writes

## Implementation and evidence

- Legacy source paths/symbols and confidence: N/A (online Admin workspace only)
- Changed paths and resulting behavior:
  - Admin customer show payload includes `private_file_id` and minimal `private_file.latest_version` metadata on each tax_verification request row (withholding + exemption)
  - Detail drawer adds tooltip **View file** on each request row and opens the same `DocumentPreviewPane` dialog pattern used on `/system/tax-evidence`
  - Binary preview/download reuses authenticated private-file download; no new storage/download endpoint
- Financial, numbering, permission, and transaction effects: none; read-only projection + existing file download auth
- API/schema/template compatibility and migration effects: additive JSON fields on detail only; list summary unchanged; no migration

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Focused PHPUnit | `php vendor/bin/phpunit tests/Feature/Registration/CustomerAccountAdminControllerTest.php --filter test_admin_list_and_show_include_tax_verification_status` | Host PHPUnit / isolated PostgreSQL | passed: 1 test / 13 assertions |
| Full CustomerAccountAdminController suite | `php vendor/bin/phpunit tests/Feature/Registration/CustomerAccountAdminControllerTest.php` | Host PHPUnit / isolated PostgreSQL | passed: 13 tests / 61 assertions |
| Prettier | `pnpm.cmd exec prettier --write src/views/customer/accounts/index.vue` | apps/web | unchanged (already formatted) |
| vue-tsc | `pnpm.cmd exec vue-tsc --noEmit` | apps/web | only pre-existing `src/store/modules/user.ts:86` TS2352 |
| Docker API controller sync | `docker cp ...CustomerAccountAdminController.php scipsi-online-billing-dev-api-1:/var/www/html/...` | Docker `api` container | copied (re-synced during browser verification); image rebuild still needed for durability |
| Admin detail + download API | Login `admin@scipsi.test` → `GET /api/v1/admin/customers?q=Andres%20Shipping` → show id `3` → `GET /api/v1/files/12/download` | Docker API `127.0.0.1:18000`, `online_billing_dev` | Detail withholding/exemption rows expose `private_file_id` 12 / 13 (`Screenshot 2026-06-01 100900.png`, `payment-proof.pdf`). Download file 12: HTTP 200, `image/png`, 28108 bytes, PNG magic `89 50 4E 47` |
| Authenticated Admin browser file preview | Playwright (Chrome channel) on `http://localhost:3007/#/system/customers`: search Andres Shipping → View detail → View file | Vite `:3007`, Admin token via `sys-v3.0.2-user` init script | Dialog opened titled `Screenshot 2026-06-01 100900.png`; image media rendered; Download control present; no error toast. Screenshot: [p1-10-tax-file-preview-pw.png](p1-10-tax-file-preview-pw.png). Cursor `cursor-ide-browser` create/navigate race left no stable tab (same class of failure as prior session). |

## Acceptance and handoff

- Automated verification: CustomerAccountAdminControllerTest 13/61 pass including `private_file_id` on detail requests
- Database workflow and database used: host PHPUnit PostgreSQL; Docker `online_billing_dev` for API runtime
- Browser / PDF / physical-print verification: Playwright click-through verified drawer View file → preview dialog with attachment; no PDF/print path
- Business or operational acceptance (who/date/evidence), if required: not required for this UI slice
- Not tested / blockers: interactive Cursor IDE browser MCP (tab does not survive between tool calls in this agent session); production `pnpm build` still blocked by unrelated `user.ts:86`; Docker API image rebuild still outstanding
- Pre-existing changes preserved: unrelated dirty tree left untouched; no commit/push; no View file wiring code changes this verification session
- Next exact task/action: optional durable Docker API image rebuild; continue unrelated phase work
- PROGRESS.md updated: yes
