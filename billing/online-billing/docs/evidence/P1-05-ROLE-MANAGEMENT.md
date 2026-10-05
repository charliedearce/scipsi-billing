# P1-05: Organization role management

## Scope and status

- Date: 2026-10-05.
- Status: Implemented in uncommitted files. Production migration and cross-role browser acceptance remain.
- Decision: The user selected editable custom roles and permissions. The four seeded built-in roles remain protected.

## Behavior

- System → Role reads actual roles and permission bundles, and allows an Administrator to create, edit, or delete an organization role.
- A role assigned to any user cannot be deleted. Edits and deletes require the current `lock_version`; writes and audit logs share a transaction.
- User role assignment now rejects roles owned by another organization.
- Custom roles supplement a built-in role for menu navigation. The existing menu shell still filters by built-in role names; API access uses effective permissions from every assigned role.

## Verification

| Check | Environment | Result |
| --- | --- | --- |
| `php artisan test tests/Feature/Identity/RolePermissionTest.php --stop-on-failure` | PostgreSQL `online_billing_test` | 7 passed, 66 assertions. |
| `php artisan test tests/Feature/Identity/UserManagementTest.php` | PostgreSQL `online_billing_test` | 9 passed, 55 assertions. |
| `vendor/bin/pint --dirty` | Local PHP | Passed; formatted focused test file. |
| `pnpm.cmd exec prettier --write src/api/roles.ts src/views/system/role/index.vue src/router/modules/system.ts` | Node 24 | Passed. |
| `pnpm.cmd exec vue-tsc --noEmit` and `pnpm.cmd build` | Node 24 | Passed. |
| Admin browser on `localhost:3006/#/system/role` | Local development DB | Listed four built-in roles; created and edited a disposable role; removed it afterward. |

Only `2026_10_05_020000_enable_role_management.php` was migrated in local development. No production DB, legacy SQL, physical print, or Crystal path was tested.
