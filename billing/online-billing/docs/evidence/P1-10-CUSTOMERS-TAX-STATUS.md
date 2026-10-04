# P1-10 / P2-08: Admin Customer Accounts UI + tax verification status

## Scope and status

- Task / phase: P1-10 (Customer Accounts Admin UI), P2-08 (tax evidence status surfacing)
- Status: AUTOMATED_VERIFIED (API + UI source); authenticated browser checked
- Date and contributor: 2026-09-22 (session continued from 2026-09-21)
- Tested revision, or exact uncommitted files:
  - `apps/api/app/Http/Controllers/Api/V1/CustomerAccountAdminController.php`
  - `apps/api/tests/Feature/Registration/CustomerAccountAdminControllerTest.php`
  - `apps/web/src/views/customer/accounts/index.vue`
- Dependencies checked: existing withholding / tax-exemption relations; Art Design Pro UI skill
- Decision IDs and approvals, where applicable: W32 identity safeguards unchanged; P2-08 review workflow unchanged

## Implementation and evidence

- Legacy source paths/symbols and confidence: N/A (online Admin workspace only)
- Changed paths and resulting behavior:
  - Admin customer list/show now include a `tax_verification` summary (`withholding` + `exemption`) with display status, pending/approved counts
  - Customer detail includes up to 8 recent request rows per track (certificate/ruling, status, period/validity)
  - `/system/customers` restyled with `page-content`, gray/theme tokens, `ArtSvgIcon`, compact row actions, status dialog, tax filter, and drawer tax section with link to Tax Evidence Review
- Financial, numbering, permission, and transaction effects: none; read-only tax status projection; identity/status update paths unchanged
- API/schema/template compatibility and migration effects: additive JSON fields only; no migration

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Focused PHPUnit (new tax status case) | `php vendor/bin/phpunit tests/Feature/Registration/CustomerAccountAdminControllerTest.php --filter test_admin_list_and_show_include_tax_verification_status` | Host PHPUnit / isolated PostgreSQL | passed: 1 test / 11 assertions |
| Full CustomerAccountAdminController suite | `php vendor/bin/phpunit tests/Feature/Registration/CustomerAccountAdminControllerTest.php` | Host PHPUnit / isolated PostgreSQL | passed: 13 tests / 59 assertions |
| Prettier | `pnpm.cmd exec prettier --write src/views/customer/accounts/index.vue` | apps/web | formatted |
| vue-tsc | `pnpm.cmd exec vue-tsc --noEmit` | apps/web | only pre-existing `src/store/modules/user.ts:86` TS2352 |
| Docker API controller sync | `docker cp ...CustomerAccountAdminController.php scipsi-online-billing-dev-api-1:/var/www/html/...` | Docker `api` container | copied; image rebuild still needed for durability |
| Authenticated Admin browser | Open `http://localhost:3007/#/system/customers`; search Andres; open detail | Vite `:3007`, Admin session | List shows 2307/EXEMPT APPROVED for Andres Shipping Corp.; drawer shows 1 request each with certificate/ruling `123123` and period/validity `Sep 01, 2026 → Sep 30, 2026` |

## Acceptance and handoff

- Automated verification: CustomerAccountAdminControllerTest 13/59 pass including tax verification payload
- Database workflow and database used: host PHPUnit PostgreSQL; Docker `online_billing_dev` for browser
- Browser / PDF / physical-print verification: authenticated Admin UI at `:3007`; no PDF/print
- Business or operational acceptance (who/date/evidence), if required: not required for this UI slice
- Not tested / blockers: production `pnpm build` still blocked by unrelated `user.ts:86`; Docker API image rebuild still outstanding
- Pre-existing changes preserved: unrelated dirty tree left untouched; no commit/push
- Next exact task/action: optional Docker API rebuild; continue next Admin UX / Phase 5 items as prioritized
- PROGRESS.md updated: yes
