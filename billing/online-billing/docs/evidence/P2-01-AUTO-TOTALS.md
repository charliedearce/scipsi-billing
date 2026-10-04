# P2-01-AUTO-TOTALS: Live server totals on teller encode (no Save draft totals)

## Scope and status

- Task / phase: P2-01 draft/calculation UI on Walk-in Billing and Billing Request Queue. Not a posting, numbering, or tax-rule change.
- Status: IN_PROGRESS (Walk-in live QTY change verified; Teller queue Save button removed, live line calc not re-run — claimed Draft #14 still has no saved route/tariff)
- Date and contributor: 2026-09-21, agent session
- Tested revision, or exact uncommitted files: uncommitted. `apps/web/src/api/invoices.ts`, `apps/web/src/components/business/InvoiceBillingItemsGrid.vue`, `apps/web/src/views/billing/walk-in/index.vue`, `apps/web/src/views/billing/teller-queue/index.vue`
- Dependencies checked: layouts never calculate money. `POST /api/v1/invoices/calculate` already existed (preview only, no persist, no `lock_version` bump). Post still `updateInvoiceDraft` then `postInvoiceDraft`. W06 archive activation not started.
- Decision IDs and approvals, where applicable: user asked to auto-compute instead of Save draft totals on the teller billing form. No numbering, settlement, or tax-rate change.

## Implementation and evidence

- Legacy source paths/symbols and confidence: VB `frmBilladd` recomputes line GROSS as quantity changes (UI-inferred). Online totals stay server-authoritative via the existing calculate endpoint, not client `parseFloat`.
- Changed paths and resulting behavior:
  - Shared grid calls `calculateInvoiceDraft` on a 400ms debounce (`maxWait` 1200ms) when customer, date, route, or payable lines change.
  - GROSS/Computations overlay the preview only when the response fingerprint matches the current lines; otherwise they stay `—`.
  - Save draft totals removed from Walk-in and Teller Queue. Helper text: “RATE and DISC. come from the tariff. Totals update as you encode.”
  - Queue work hint: “Totals update as you encode, then post.” Persist still happens on Post, not on each keystroke (avoids a document revision per debounce).
- Financial, numbering, permission, and transaction effects: calculate is preview-only. No invoice, number, or lock_version change until Post. Drafts #11/#13/#14/#15 were not posted.
- API/schema/template compatibility and migration effects: none. Existing `POST /api/v1/invoices/calculate` with `showErrorMessage: false` so line 422s stay in-grid helper text.

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| IDE lints | Cursor ReadLints on the four Vue/TS files | local | no linter errors |
| Authenticated Walk-in live totals | Open Walk-in Draft #15 (Tariff Import Check; ACCORD / 21 / IN / DOMESTIC; CD01D stevedoring). Confirm no Save draft totals. Change QTY 1 → 2; wait debounce. Restore QTY 1. Did not Post. | Vite `http://localhost:3006/#/walk-in-billing`, Docker API `:18000`, `online_billing_dev` | Helper “Totals update as you encode.” QTY 2: GROSS `102.00`, TOTAL `102.00`, DISC `0.00`, VAT `12.24`, NET `102.00`, DUE TO SCIPSI `114.24` (RATE stayed `51.00`). Restored QTY 1: GROSS `51.00` / DUE `57.12`. |
| Teller queue encode surface | Open `/billing-request-queue` with claimed Ticket #1006 / Draft #14. Did not Claim next. Did not Post. Reload clicked to confirm server draft. | Same Vite/API | Save draft totals absent. Actions are Add item / Post invoice & add another / Post invoice & finish. Hint “Totals update as you encode, then post.” Server draft still has empty voyage/route, so SERVICE/CARGO stay “Select route first” and Computations stay `—`. Live QTY calc not re-run on this claim. |

## Acceptance and handoff

- Automated verification: not a new PHPUnit suite. Existing calculate/draft tests unchanged. `vue-tsc --noEmit` still blocked by pre-existing `src/store/modules/user.ts:86` TS2352.
- Database workflow and database used: `online_billing_dev` read of Draft #15/#14 only. Calculate does not persist. No SQL Server live import.
- Browser / PDF / physical-print verification: Walk-in QTY change verified. Teller queue Save removal verified; queue live GROSS with a complete line not exercised. PDF/print not in scope.
- Business or operational acceptance (who/date/evidence), if required: not obtained.
- Not tested / blockers: posting any draft; live queue encode with vessel/route/tariff filled; reviewed archive activation; Docker API image rebuild still outstanding from tariff import.
- Pre-existing changes preserved: unrelated dirty tree left in place, including parent VB/report files.
- Next exact task/action: optional Teller-queue pass when Draft #14 has a saved route and cargo. Do not post Drafts #11/#13/#14/#15. Do not commit unless asked.
- PROGRESS.md updated: yes
