# P3-02: Acknowledgement vs Official Receipt kind

## Scope and status

- Task / phase: P3-02 extension (collection receipt kinds); discovery contract in `docs/discovery/ACKNOWLEDGEMENT_RECEIPTS.md`
- Status: AUTOMATED_VERIFIED for the engineering slice; accountant/BIR acceptance open
- Date and contributor: 2026-09-22
- Tested revision: uncommitted working tree under `online-billing/` (no commit requested)
- Dependencies checked: existing P3-02 receipt posting, Document Studio `COLLECTION_RECEIPT`, P4-03 operational register, P4-02 white-receipt transmittal
- Decision IDs: user request for acknowledgement option; W24 provisional-check acknowledgment remains a separate open accountant item

## Implementation and evidence

- Legacy source paths/symbols and confidence: online rebuild code-inferred from current `ReceiptPostingService` / Studio / report paths; not a verified legacy VB acknowledgement document type
- Changed paths and resulting behavior:
  - `receipts.receipt_kind` + `counts_as_official_receipt`
  - separate `ACKNOWLEDGEMENT_RECEIPT` series / Studio kind / PDF
  - teller proof approve may choose Official vs Acknowledgement
  - operational register exposes kind; `Receipt::scopeOfficialFiscal()` and white transmittals exclude acknowledgements
- Financial effects: acknowledgement uses the same settlement calculator, allocations, withholding capacity and immutability rules as OR; only fiscal-OR labeling/numbering/export eligibility differ
- Numbering: Official continues on `COLLECTION_RECEIPT` (`CR-…`); Acknowledgement uses `ACKNOWLEDGEMENT_RECEIPT` (`ACK-…`)

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Receipt posting + ACK/OR kinds | `php artisan test --compact tests/Feature/Receipts/ReceiptPostingTest.php` | Local Laravel PHPUnit + RefreshDatabase PostgreSQL | 10 tests / 107 assertions passed |
| Billing & Collections Register CSV columns | `php artisan test --compact tests/Feature/Reports/BillingCollectionsReportTest.php` | Same | Passed (report suite) |
| Transmittal white eligibility (pre-existing date fixture) | `php artisan test --compact tests/Feature/Transmittals/TransmittalSnapshotTest.php` | Same | 2 failures on `as_of_date` “before or equal to today” — unrelated to receipt_kind (system date 2026-09-22 vs fixture date) |
| Pint | `vendor/bin/pint --dirty --format agent` | API tree | Passed |
| Vue typecheck | `pnpm.cmd exec vue-tsc --noEmit` | `apps/web` | Failed on pre-existing `src/store/modules/user.ts:86` TS2352; not introduced by this slice |
| Browser approve with ACK | Not run | — | Deferred; no fiscal smoke posting performed |

## Acceptance and handoff

- Automated verification: ACK posts with `ACK-` number, `counts_as_official_receipt=false`, ACK Studio snapshot, and is excluded from `officialFiscal()`; default remains Official/`CR-`
- Database workflow and database used: PHPUnit RefreshDatabase only
- Browser / PDF / physical-print verification: not run; physical print deferred by project scope
- Business or operational acceptance: not obtained; ATP/series registration, PPA paid semantics for ACK, and GL mapping remain open
- Not tested / blockers: live BIR export path does not exist yet; future EI/BIR registers must consume `officialFiscal()` / `counts_as_official_receipt`
- Pre-existing changes preserved: unrelated dirty-tree files left untouched; no commit/push
- Next exact task/action: accountant confirm PPA paid treatment and any statutory ACK wording; optionally wire VIP teller UI receipt-kind selector; add focused white-transmittal ACK exclusion test once date fixture is fixed
- PROGRESS.md updated: yes
