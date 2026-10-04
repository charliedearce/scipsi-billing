# P1-10-ACCOUNT-NUMBER: Admin customer `account_number` field

## Scope and status

- Task / phase: P1-10 Customer Accounts admin (W32). Not walk-in shell numbering, not portal registration OTP, not archive activation.
- Status: IN_PROGRESS (create/edit verified in browser; Docker API copy is not durable until rebuild)
- Date and contributor: 2026-09-21, agent session
- Tested revision, or exact uncommitted files: uncommitted. `apps/api/app/Http/Controllers/Api/V1/CustomerAccountAdminController.php`, `apps/api/routes/api.php`, `apps/api/tests/Feature/Registration/CustomerAccountAdminControllerTest.php`, `apps/web/src/api/registration.ts`, `apps/web/src/views/customer/accounts/index.vue`
- Dependencies checked: W32 keeps portal users, customer business accounts, contacts and buyer profiles distinct. Admin create does not auto-link a portal user. Issued invoice/receipt snapshots are not rewritten when account number changes.
- Decision IDs and approvals, where applicable: user asked to add `account_number` on Admin customer management.

## Implementation and evidence

- Legacy source paths/symbols and confidence: `tbl_account` customer code (database-verified as the account key on bills/ORs). Online `customers.account_number` is the matching business-account identifier (already unique, 64 chars).
- Changed paths and resulting behavior:
  - `POST /api/v1/admin/customers` creates a business account with required `account_number` and company name, plus buyer profile v1. No portal user.
  - `PUT /api/v1/admin/customers/{id}` updates `account_number` / name, unique, audited, increments `lock_version`.
  - Admin Customer Accounts: **Add account** dialog, table **Edit**, and detail drawer all expose Account number.
- Financial, numbering, permission, and transaction effects: `customer_accounts:manage` required. No invoice, receipt, number-pool or portal-link mutation. Duplicate account numbers are rejected. Non-VIP omitted numbers become `11001-0000`; VIP cannot use that code.
- API/schema/template compatibility and migration effects: none. Column already existed. `customer_type=vip` is the existing VIP credit classification.

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| PHPUnit | `php artisan test --filter=CustomerAccountAdminControllerTest` | `apps/api`, PostgreSQL `online_billing_test` on `127.0.0.1:55432` | PASS 12 tests / 48 assertions (401/403, name required, non-VIP default `11001-0000`, VIP rejects `11001-0000` and missing number, VIP unique number, create+trim, duplicate, update, customer-role 403) |
| Prettier | `pnpm.cmd exec prettier --write` on the Vue/API client files | `apps/web` | formatted |
| Authenticated Admin create | System Settings → Customer Accounts → Add account (VIP off). Account number prefilled `11001-0000`; name WALK-IN Default Check; Create account. | Vite `http://localhost:3006/#/system/customers`, Docker API `:18000`, `online_billing_dev` | Dialog default `11001-0000`. Row listed as business, `11001-0000`, no portal user. |

## Acceptance and handoff

- Automated verification: focused PHPUnit 12/48. `vue-tsc --noEmit` still blocked by pre-existing `src/store/modules/user.ts:86` TS2352. Full production build not re-run.
- Database workflow and database used: `online_billing_test` for PHPUnit; `online_billing_dev` for the Admin create. No SQL Server write.
- Browser / PDF / physical-print verification: Admin Customer Accounts create + search. Edit-from-drawer save not re-clicked after create. PDF/print not in scope.
- Business or operational acceptance (who/date/evidence), if required: not obtained.
- Not tested / blockers: Docker API image rebuild (controller/routes were `docker cp`'d); changing an account number that already appears on a posted invoice snapshot; Teller access (Administrator-only page).
- Pre-existing changes preserved: unrelated dirty tree left in place.
- Next exact task/action: optional Edit save pass on an existing billed account. Do not commit unless asked. Rebuild the API image before relying on the Docker copy.
- PROGRESS.md updated: yes
