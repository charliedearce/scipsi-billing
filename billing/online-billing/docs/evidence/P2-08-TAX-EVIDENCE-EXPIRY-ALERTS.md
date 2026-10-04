# P2-08: Tax evidence expiry and renewal alerts

## Scope and status

- Task / phase: P2-08 (Customer tax-evidence verification), W20 / W31 notification surface
- Status: AUTOMATED_VERIFIED (scheduler + in-app alerts); Docker ops + customer browser smoke 2026-09-22
- Date and contributor: 2026-09-22
- Tested revision, or exact uncommitted files:
  - `apps/api/app/Services/Billing/TaxEvidenceExpiryService.php`
  - `apps/api/app/Console/Commands/ProcessTaxEvidenceExpiryCommand.php`
  - `apps/api/app/Services/Notifications/InAppNotificationPublisher.php` (`publishOnce`)
  - `apps/api/routes/console.php`
  - `apps/api/database/seeders/DatabaseSeeder.php` (`TAX_EVIDENCE_EXPIRED`)
  - `apps/api/app/Http/Controllers/Api/V1/NotificationPreferenceController.php`
  - `apps/api/tests/Feature/Tax/TaxEvidenceExpiryTest.php`
  - `apps/web/src/views/billing/tax-evidence/index.vue` (ISO date parse for approaching banner)
  - `apps/web/src/views/customer/profile/index.vue` (same date parse)
  - `apps/web/src/components/core/layouts/art-notification/index.vue`
- Dependencies checked: EXPIRED status already on withholding/exemption models; in-app type `TAX` already routes to `/my-tax-evidence` on the notifications page; W31 lists tax evidence expired as Send
- Decision IDs and approvals, where applicable: W20 tax evidence; W31 expired SMS (generic); not announcements (W34)

## Implementation and evidence

- Legacy source paths/symbols and confidence: online-only expiry schedule; no legacy VB auto-expire path claimed
- Changed paths and resulting behavior:
  - Daily Asia/Manila `tax:process-evidence-expiry` (01:30) marks APPROVED evidence EXPIRED when `period_to` / `valid_to` is before as-of
  - Creates durable in-app `TAX` notifications for approaching (<30 days, inclusive window through as-of+29) and expired, deduped by `data.dedupe_key`
  - SMS local intent only for `TAX_EVIDENCE_EXPIRED` (W31); no approaching SMS invented
  - Customer `/my-tax-evidence` and Profile tax section show renew callouts with CTA into existing submit drawers
  - Header notification panel deep-links TAX rows to `/my-tax-evidence`
- Financial, numbering, permission, and transaction effects: status transition APPROVED→EXPIRED + append-only tax evidence events only; no invoice/receipt/allocation rewrite
- API/schema/template compatibility and migration effects: seeded SMS template/policy `TAX_EVIDENCE_EXPIRED`; no DB migration

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Expiry / approaching unit suite | `php artisan test --compact tests/Feature/Tax/TaxEvidenceExpiryTest.php` | Host PHPUnit, RefreshDatabase PostgreSQL | 4 tests / 32 assertions passed |
| Pint | `vendor/bin/pint --dirty --format agent` | Host | Fixed TaxEvidenceExpiryService + TaxEvidenceExpiryTest imports |
| Vue typecheck | `pnpm.cmd exec vue-tsc --noEmit` | `apps/web` | Pre-existing `user.ts:86` TS2352 only; not introduced by this slice |
| Docker sync | `docker cp` service/command/publisher/`console.php`/seeder/preferences into `api`, `scheduler`, `worker` (mkdir Notifications on scheduler) | `scipsi-online-billing-dev-{api,scheduler,worker}-1` | Files present; no image rebuild |
| Schedule / command | `php artisan list` + `schedule:list` | api + scheduler containers | `tax:process-evidence-expiry` listed; daily 01:30 Asia/Manila (UTC 17:30) |
| SMS template seed | Targeted tinker `firstOrCreate` for `TAX_EVIDENCE_EXPIRED` template/version/policy | `online_billing_dev` | template_id/version_id/policy_id **19** created |
| Safe command smoke | `php artisan tax:process-evidence-expiry --as-of=2026-09-22 --organization=1` ×2 | Docker API + `online_billing_dev` | Run1: expired=0, approaching_wht=1, approaching_ex=1, notifications=2; Run2: all 0 (dedupe). Existing Andres APPROVED rows stay APPROVED (period/valid_to 2026-09-30) |
| Customer browser | Playwright Chrome, Vite `:3007`, `customer1@example.com`, `/#/my-tax-evidence` + `/#/my-profile` | Host Vite + Docker API | Banner **Tax evidence expires in less than 30 days** with 8-day 2307 + exemption lines and Update CTAs. Screenshot: [p2-08-tax-expiry-banner-pw.png](p2-08-tax-expiry-banner-pw.png). Smoke also needed a small `daysUntil` ISO-datetime fix (API returns `…T00:00:00.000000Z`) |

## Acceptance and handoff

- Automated verification: TaxEvidenceExpiryTest 4/32 pass
- Database workflow and database used: RefreshDatabase (unit) + Docker `online_billing_dev` approaching-only smoke (no EXPIRED mass flip)
- Browser / PDF / physical-print verification: Playwright customer banner on Tax Evidence + Profile; no PDF/print
- Business or operational acceptance: not claimed
- Not tested / blockers: Docker image rebuild still needed for durable API/scheduler files; authenticated Reverb wake for TAX expiry; production SkySMS send; EXPIRED renew-banner path (would require disposable fixture — not run against live Andres APPROVED rows)
- Pre-existing changes preserved: ACK/receipt and other dirty tree left untouched; no commit/push
- Next exact task/action: optional API/scheduler image rebuild; disposable EXPIRED fixture for renew-danger banner; format Period column dates away from raw ISO if desired
- PROGRESS.md updated: yes
