# P2-01-BILLING-GRID: Live draft billing items in legacy QTY/UNIT/SERVICE/CARGO/RATE/DISC/GROSS layout

## Scope and status

- Task / phase: P2-01 / P2-07 / P2-09 UI restyle (user-chosen slice #1 after P5-01 history search). Not live archive activation.
- Status: IN_PROGRESS (Walk-in authenticated save verified; Teller queue save not clicked — no active claim)
- Date and contributor: 2026-09-21, agent session
- Tested revision, or exact uncommitted files: uncommitted. `apps/web/src/components/business/InvoiceBillingItemsGrid.vue`, `apps/web/src/api/invoices.ts` (`BillingDraftLine` / `emptyBillingLine`), `apps/web/src/views/billing/walk-in/index.vue`, `apps/web/src/views/billing/teller-queue/index.vue`
- Dependencies checked: W06 forbids posting P5-01 archive rows as live invoices/customers/balances/numbers. Live GROSS/VAT/PPA/NET/DUE stay server-authoritative after Save. Current tariffs and new draft numbers only.
- Decision IDs and approvals, where applicable: user chose restyle over reviewed activation. No numbering, tax, settlement, or archive-import rule change.

## Implementation and evidence

- Legacy source paths/symbols and confidence: VB bill grid QTY/UNIT/SERVICE/CARGO/RATE/DISC/GROSS plus TOTAL/DISC/VAT/LESS PPA SHARE/NET/DUE (UI-inferred from the user’s screenshot of bill `0000061024` and the P5-01 Search history display). UNIT comes from tariff `unit_of_measure`; SERVICE is the effective tariff select filtered by bill route; CARGO is optional `description`.
- Changed paths and resulting behavior:
  - Shared `InvoiceBillingItemsGrid` on Walk-in Billing and Billing Request Queue.
  - Bill # header shows posted number, `invoice_number`, or `Draft #id`. Account is the walk-in/customer name.
  - Save sends `discount_amount` and `description`. GROSS and Computations render saved draft strings only (no `parseFloat` / client totals). GROSS stays blank until Save when the line’s `tariff_version_id` still matches the saved item.
- Financial, numbering, permission, and transaction effects: none. Save still uses existing draft update. No archive activation. Drafts #11 and #13 were not posted. Historical GROSS was not copied onto a live invoice.
- API/schema/template compatibility and migration effects: none. Existing invoice-item fields only.

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Prettier | `pnpm.cmd exec prettier --write` on the grid Vue | `apps/web` | PASS |
| Authenticated Walk-in save | New walk-in Layout Check Buyer; vessel ACCORD, voyage 71, IN, DOMESTIC, notes LAYOUT CHECK; Create invoice draft; QTY 9, ARR_DOM, cargo Standby time; Save draft totals. Did not Post. | Vite `:3006`, Docker API `:18000`, `online_billing_dev` | Draft #13. After Save: UNIT `REV_TON`, GROSS `1,185.97`, TOTAL `1,185.97`, DISC `0.00`, VAT `128.08`, LESS PPA SHARE `118.59`, NET `1,067.38`, DUE TO SCIPSI `1,314.05`, fuel surcharge note `56.47`. Toast “Draft totals saved.” |
| Teller queue live save | Open Billing Request Queue | Same | No waiting claim. Grid is wired in source; save/post not clicked. |
| Narrow viewport | Device-metrics override while Walk-in was open | Same | Stacked 4/8 layout (list above bill card). Override left unused black space; not treated as production mobile acceptance. |

## Acceptance and handoff

- Automated verification: not a new PHPUnit suite. Existing draft/calc tests unchanged. `vue-tsc --noEmit` still blocked by pre-existing `src/store/modules/user.ts:86` TS2352.
- Database workflow and database used: `online_billing_dev` for the Walk-in draft only. No SQL Server live import.
- Browser / PDF / physical-print verification: Walk-in Billing save and computations. Teller queue claim/save not exercised. PDF/print not in scope.
- Business or operational acceptance (who/date/evidence), if required: not obtained.
- Not tested / blockers: posting Draft #11 or #13; Teller queue under a live claim; reviewed archive activation (option 2); Docker API image rebuild still outstanding from the shipment header work.
- Pre-existing changes preserved: unrelated dirty tree left in place.
- Next exact task/action: optional Teller-queue pass when a request is claimed; option 2 remains a separate W06 design if requested. Do not post the layout-check drafts. Do not commit unless asked.
- PROGRESS.md updated: yes
