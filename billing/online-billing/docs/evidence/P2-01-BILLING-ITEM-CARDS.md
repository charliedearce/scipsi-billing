# P2-01: Billing item cards

## Scope and status

- Task / phase: P2-01 follow-up, bill-line entry layout
- Status: source and browser-checked; not committed
- Date and contributor: 2026-09-27
- Tested revision, or exact uncommitted files: `apps/web/src/components/business/InvoiceBillingItemsGrid.vue`
- Dependencies checked: server draft calculation is unchanged
- Decision IDs and approvals, where applicable: none

## Implementation and evidence

- Legacy source paths/symbols and confidence: none; presentation only
- Changed paths and resulting behavior: the shared billing-item editor used by Walk-in Billing, Billing Request Queue, and correction drafts now shows each line as a card. Service and cargo use the panel width. Quantity, unit, rate, discount, gross, and tax wrap onto the next row. Amounts still come from `POST /invoices/calculate`.
- Financial, numbering, permission, and transaction effects: none. The checked draft was not posted.
- API/schema/template compatibility and migration effects: none

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Vue typecheck | `pnpm.cmd exec vue-tsc --noEmit` in `apps/web` | Windows, Node used by the web app | Passed |
| Production build | `pnpm.cmd build` in `apps/web` | Windows | Passed, built in 32.14s |
| Browser | Open walk-in draft #13 (Layout Check Buyer) in dark mode | Vite `http://127.0.0.1:3006`, live `online_billing_dev` | Line card overflow was 0 at 396px and 690px. Visible fields: Arrastre, ARR_DOM, quantity 9.0000, REV_TON, rate 131.78, discount 0.00, gross 1,186.00, VATable. Add item created Item 2; Remove restored Item 1. Invoice was not posted. |

## Acceptance and handoff

- Automated verification: Vue typecheck and production build passed
- Database workflow and database used: existing walk-in draft #13 was displayed only
- Browser / PDF / physical-print verification: dark-mode browser check above; no PDF or print
- Business or operational acceptance (who/date/evidence), if required: not requested
- Not tested / blockers: Billing Request Queue and the correction dialog use the same component and were not opened in the browser
- Pre-existing changes preserved: yes
- Next exact task/action: none unless the teller queue column still feels tight in daily use
- PROGRESS.md updated: yes
