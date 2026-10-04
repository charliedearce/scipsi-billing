# P2-07-QUEUE-UX: Teller Billing Request Queue layout

## Scope and status

- Task / phase: P2-07 Vue workstation UX (no API/rule change)
- Status: IN_PROGRESS (authenticated idle + posted tracking verified; live Claim next not clicked)
- Date and contributor: 2026-09-21, agent session
- Tested revision, or exact uncommitted files: uncommitted `apps/web/src/views/billing/teller-queue/index.vue`
- Dependencies checked: fair-queue claim remains oldest-first. Correction, release, cancel, prepare/save/post paths unchanged.
- Decision IDs and approvals, where applicable: none. Layout/copy only.

## Implementation and evidence

- Legacy source paths/symbols and confidence: not applicable (online teller queue).
- Changed paths and resulting behavior:
  - Removed the slate gradient hero and clipped stacked tables.
  - Idle view: compact stats, a “next to claim” card, Waiting / Posted tabs, customer-first posted table with invoice tags, Paid/Unpaid, and Open.
  - Claimed/tracking view: customer + ticket + next step in plain language; files vs bill columns; correction/release/cancel under More; tracking shows posted invoice numbers with Copy.
- Financial, numbering, permission, and transaction effects: none.
- API/schema/template compatibility and migration effects: none.

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Prettier | `pnpm.cmd exec prettier --write src/views/billing/teller-queue/index.vue` | `apps/web` | PASS |
| Authenticated idle queue | Open `/billing-request-queue` | Vite `:3006`, Docker API `:18000` | Waiting 1 / Posted 4. Next card: Ticket #1006 Andres Shipping Corp. Waiting table readable. Did not claim. |
| Posted tracking | Posted tab → Open first row | Same | BILL READY header, file preview, invoice SI-0000000009 with Copy, Back to queue. |

## Acceptance and handoff

- Automated verification: none (UI-only). `vue-tsc` still blocked by pre-existing `user.ts:86`.
- Database workflow and database used: `online_billing_dev` read of existing queue rows.
- Browser / PDF / physical-print verification: desktop authenticated idle + tracking. Claim/prepare/post not clicked. Narrow viewport not fully accepted (stats wrap to 2×2 under xl).
- Business or operational acceptance (who/date/evidence), if required: not obtained.
- Not tested / blockers: live Claim next / encode / post; claimed More menu.
- Pre-existing changes preserved: unrelated dirty tree left in place.
- Next exact task/action: optional live claim pass when the teller is ready. No commit unless asked.
- PROGRESS.md updated: yes
