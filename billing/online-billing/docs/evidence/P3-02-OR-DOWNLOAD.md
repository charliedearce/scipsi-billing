# P3-02 / P3-05: Official Receipt (OR) PDF download

## Scope and status

- Task / phase: P3-02 (canonical receipt artifact access) + P3-05 (customer/teller payment surfaces)
- Status: AUTOMATED_VERIFIED for download API + portal flag; browser acceptance open
- Date and contributor: 2026-09-21
- Tested revision: uncommitted working tree (OR download slice)
- Dependencies checked: existing RECEIPT canonical artifact render on post; invoice artifact download pattern
- Decision IDs and approvals: digital PDF / browser download remains in scope (physical print deferred)

## Implementation and evidence

- Legacy source paths/symbols and confidence: online digital OR PDF (inferred from P3-02 posting); not a Crystal OR reprint path
- Changed paths and resulting behavior:
  - `apps/api/app/Http/Controllers/Api/V1/ReceiptArtifactController.php` — staff or owning customer streams canonical RENDERED OR PDF
  - `apps/api/routes/api.php` — `GET /api/v1/receipts/{id}/artifacts/download`
  - `apps/api/app/Services/Billing/ManualPaymentProofService.php` — `receipt_history[].pdf_available`
  - `apps/web/src/api/payments.ts` — `downloadPortalReceiptPdf`
  - Customer My Bills + bill detail + submission OR row: View / Download OR via `ProofViewerModal`
  - Teller proof detail: View / Download OR after posted receipt
- Financial, numbering, permission, and transaction effects: read-only artifact stream; no new numbering or settlement mutation; authorization mirrors invoice artifact (staff permissions or customer ownership / invoice access)
- API/schema/template compatibility and migration effects: additive `pdf_available` on portal receipt history; no migration

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| OR download + portal flag | `php vendor/bin/phpunit tests/Feature/Receipts/ReceiptPostingTest.php --filter=test_canonical_receipt_pdf_is_downloadable_by_staff_and_linked_customer` | Host PHPUnit / `online_billing_test` PostgreSQL | 1 test / 14 assertions passed |
| Docker API rebuild | `docker compose -f compose.yaml up -d --build api` | Local compose | Image rebuilt; api container started |
| Frontend format | `pnpm.cmd --dir apps/web exec prettier --write` on customer/teller payments + payments.ts | Host | Formatted |
| Typecheck | `pnpm.cmd --dir apps/web exec vue-tsc --noEmit` | Host | Pre-existing error in `legacy-imports/index.vue` (`@/utils/date/manila`); OR files not implicated |
| Browser View/Download OR | Authenticated Customer My Bills + Teller proof after approve | Not run this session | Pending |

## Acceptance and handoff

- Automated verification: focused OR download test passed (staff + linked customer PDF stream; portal `pdf_available`)
- Database workflow and database used: PHPUnit RefreshDatabase on local test PostgreSQL; no production DB
- Browser / PDF / physical-print verification: browser acceptance not run; physical print deferred
- Business or operational acceptance: not required for this digital download slice
- Not tested / blockers: authenticated browser download on Customer and Teller; full suite / production Vue build not re-run for this slice
- Pre-existing changes preserved: unrelated legacy/web/API dirty tree left untouched except OR download paths
- Next exact task/action: approve a proof in Teller UI, then use View / Download OR on Teller and Customer My Bills
- PROGRESS.md updated: yes
