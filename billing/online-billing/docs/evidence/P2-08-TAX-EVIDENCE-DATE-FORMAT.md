# P2-08: Admin tax-evidence Period / Validity date format

## Customer My Tax Evidence follow-up — 2026-09-26

- Reused `formatDateRangeManila` for the customer withholding Period and ruling Validity columns on `/my-tax-evidence`; open-ended validity displays `Open ended`. Renewal alert end dates use `formatDateManila`. Stored dates and expiry calculations are unchanged.
- Tested uncommitted file: `apps/web/src/views/billing/tax-evidence/index.vue`. Prettier, `pnpm.cmd exec vue-tsc --noEmit`, and `pnpm.cmd build` passed with Node 24. No API/database workflow, authenticated browser, PDF, or printer check was run. Unrelated dirty work was preserved.

## Scope and status

- Task / phase: P2-08 Admin Tax Evidence Review UI. Display-only.
- Status: IN_PROGRESS (authenticated browser verified)
- Date and contributor: 2026-09-21, agent session
- Tested revision, or exact uncommitted files: uncommitted `apps/web/src/utils/date/formatDateTime.ts`, `apps/web/src/views/system/tax-evidence/index.vue`
- Dependencies checked: Period / Validity are calendar dates (`YYYY-MM-DD`), not datetimes.
- Decision IDs and approvals, where applicable: none. User asked to format Period and Validity on `/system/tax-evidence`.

## Implementation and evidence

- Legacy source paths/symbols and confidence: not required.
- Changed paths and resulting behavior:
  - Added `formatDateManila` and `formatDateRangeManila` for date-only Asia/Manila display.
  - Withholding **Period** and exemption **Validity** now show `Sep 01, 2026 → Sep 30, 2026` instead of raw `2026-09-01 → 2026-09-30`. Open-ended validity uses `Open ended`.
- Financial, numbering, permission, and transaction effects: none.
- API/schema/template compatibility and migration effects: none.

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Prettier | `pnpm.cmd exec prettier --write` on the two files | `apps/web` | PASS |
| Admin Period | Authenticated Administrator; `/#/system/tax-evidence`; Withholding filter APPROVED | Vite `localhost:3006`, Docker API, `online_billing_dev` | Period shows `Sep 01, 2026 → Sep 30, 2026` for Andres Shipping Corp. certificate `123123`. |
| Admin Validity | Same page; Non-VAT / Zero-rated filter APPROVED | same | Validity shows `Sep 01, 2026 → Sep 30, 2026` for ruling `123123`. |

## Acceptance and handoff

- Automated verification: Prettier. No new PHPUnit.
- Database workflow and database used: read-only `online_billing_dev` tax evidence rows.
- Browser / PDF / physical-print verification: desktop Admin Period + Validity. Narrow viewport not resized. PDF/print not in scope.
- Business or operational acceptance (who/date/evidence), if required: not obtained.
- Not tested / blockers: profile tax-evidence summary may still show raw dates; customer `/my-tax-evidence` was updated in the 2026-09-26 follow-up above.
- Pre-existing changes preserved: unrelated dirty tree left in place.
- Next exact task/action: authenticated customer `/my-tax-evidence` walkthrough. Do not commit unless asked.
- PROGRESS.md updated: yes
