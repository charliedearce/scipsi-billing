# P2-01: Multi-line cargos on the same bill (COF20 + COF40)

## Scope and status

- Task / phase: P2-01 billing item grid (teller / walk-in)
- Status: SOURCE_FIXED (API already OK); browser smoke open
- Date: 2026-09-22
- Tested revision / uncommitted files:
  - `apps/web/src/api/invoices.ts` (`client_key`, `newBillingLineKey`)
  - `apps/web/src/components/business/InvoiceBillingItemsGrid.vue`
- Decision: User reported COF20 and COF40 could not both appear on one bill. API `POST /invoices/calculate` already returns two lines for Arrastre DOMESTIC COF20+COF40 (200, item_count=2). Defect was frontend row/select identity.

## Cause

ElTable had no `row-key`, and CARGO `ElSelect` bound `v-model` to shared `tariff_code` string values. Element Plus reused select state across rows so a second cargo selection collided with the first.

## Fix

- Each `BillingDraftLine` gets a stable `client_key` (not sent to API).
- ElTable uses `row-key="client_key"`; SERVICE/CARGO selects are keyed per line.
- CARGO select binds to unique `tariff_version_id` (option value = effective version id); `tariff_code` stays synced for payloads/display labels.

## Checks

| Check | Result |
| --- | --- |
| API probe COF20+COF40 calculate | 200, two items, rates 994.0000 / 1986.5000 |
| Prettier on changed web files | PASS |
| Authenticated browser two-line encode | open |

## Next

Browser smoke: Walk-in or Teller queue, Arrastre, add COF20 then COF40, confirm both rows stay selected and totals show two lines.
