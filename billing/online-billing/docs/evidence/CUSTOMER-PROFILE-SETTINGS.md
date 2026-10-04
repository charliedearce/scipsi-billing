# Customer Profile Settings (W32 portal)

## Scope and status

- Task / phase: Customer portal profile settings (extends P1-10 / W32; tax request UX from P2-08)
- Status: `AUTOMATED_VERIFIED` (API + Vue typecheck); browser happy-path open
- Date: 2026-09-21
- Tested revision: uncommitted files listed below

## Changed paths

- `apps/api/database/migrations/2026_09_21_120000_add_avatar_private_file_id_to_users_table.php`
- `apps/api/app/Models/User.php`
- `apps/api/app/Http/Controllers/Api/V1/CustomerProfileController.php`
- `apps/api/routes/api.php`
- `apps/api/database/seeders/DatabaseSeeder.php` (`PROFILE_AVATAR` document type)
- `apps/api/tests/Feature/Registration/CustomerPortalProfileSettingsTest.php`
- `apps/web/src/api/portalProfile.ts`
- `apps/web/src/views/customer/profile/index.vue`
- `apps/web/src/router/modules/payments.ts` (`/my-profile`)
- `apps/web/src/components/core/layouts/art-header-bar/widget/ArtUserMenu.vue`
- `apps/web/src/api/documentRequirements.ts` (`PROFILE_AVATAR` purpose)

## Behavior

- Customer **Profile Settings** (`/my-profile`): edit portal name; upload private avatar; change password; submit company/buyer updates as `pending_review` versions; request withholding (2307) and VAT-exempt/zero-rated verification via existing tax-evidence APIs.
- Email/mobile remain read-only (OTP contact change out of scope).
- Issued invoices/ORs are never rewritten by profile or tax requests.

## Checks

| Check | Command / procedure | Result |
| --- | --- | --- |
| Migration | `php artisan migrate --force` | PASS (`avatar_private_file_id`) |
| Feature tests | `php artisan test --filter="CustomerPortalProfileSettingsTest\|CustomerBuyerProfileTest"` | PASS 7 tests / 38 assertions |
| Pint | `vendor/bin/pint --dirty` | PASS |
| Vue typecheck | `pnpm exec vue-tsc --noEmit` | PASS |
| Browser | Customer login → Profile Settings | Pending (captcha/manual) |

## Next

- Manual Customer browser acceptance: name, password, avatar, buyer pending review, tax request drawers.
- Docker API rebuild + migrate/seed for `PROFILE_AVATAR` on shared compose DB.
