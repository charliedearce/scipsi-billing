# P3-08 VIP repayment timestamp correction

Date: 2026-10-04. Uncommitted files tested: `apps/api/app/Services/Billing/VipCreditService.php` and `apps/api/tests/Feature/Credit/VipCreditWorkflowTest.php`.

## Defect and change

Laravel's application timezone is UTC. The repayment service passed `Carbon::now('Asia/Manila')` into PostgreSQL `timestamptz` fields through Eloquent. Laravel formatted the Carbon value without its offset; the UTC database session interpreted the Manila wall time as UTC. The customer portal therefore displayed `initial_submitted_at` eight hours ahead. The same path wrote proof, assignment, review and resubmission times.

The five repayment event write paths now use UTC instants. Manila business dates and policy cutoffs were not changed. The portal continues to format the returned ISO timestamp in the browser's local timezone.

## Verification

- Before the source fix, `php artisan test --filter=test_vip_repayment_submission_times_keep_the_actual_utc_instant --compact` failed at the first persisted timestamp assertion.
- After the fix, the targeted PostgreSQL test passed: 1 test / 16 assertions. It checks first submission, portal API serialization, teller assignment/review and resubmission while preserving original queue priority.
- `php artisan test tests/Feature/Credit/VipCreditWorkflowTest.php --compact` passed after the final API read assertions: 12 tests / 182 assertions on `online_billing_test`. PHP syntax checks and `git diff --check` passed.
- `docker compose --profile app up -d --no-deps --build api` failed during Composer dependency downloads (`curl error 18`, HTTP/2 stream closure from GitHub). Compose has no API container to update or smoke-test. The source fix is verified locally but is not deployed into Docker.
- Local development database `online_billing_dev` contained one approved repayment. Its first submission and proof time were exactly eight hours after its `created_at`; `reviewed_at` was exactly eight hours after `updated_at`. A guarded transaction adjusted only that row's submission/assignment/review timestamps and its one proof's created time. Readback confirmed the first submission and proof equal `created_at`, review equals `updated_at`, and assignment remains between them.

No production database, payment provider, financial amount, receipt or invoice was changed. The one-row repair does not assume that every historical record has this offset; any other environment needs a reviewed row-level reconciliation before repair. Browser acceptance was not run.
