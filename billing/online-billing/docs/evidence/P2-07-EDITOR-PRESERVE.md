# P2-07-EDITOR-PRESERVE: Encode form no longer wipes on queue refresh

## Scope and status

- Task / phase: P2-07 teller encode editor (bugfix). Walk-in / shipment guards included.
- Status: IN_PROGRESS (authenticated encode Refresh verified; 30s idle wait not separately timed)
- Date and contributor: 2026-09-21, agent session
- Tested revision, or exact uncommitted files: uncommitted `apps/web/src/views/billing/teller-queue/index.vue`, `apps/web/src/views/billing/walk-in/index.vue`, `apps/web/src/components/business/InvoiceShipmentFields.vue`
- Dependencies checked: queue claim/prepare/save/post APIs unchanged. Financial values remain server-authoritative after Save.
- Decision IDs and approvals, where applicable: none.

## Implementation and evidence

- Legacy source paths/symbols and confidence: not applicable (online teller queue).
- Changed paths and resulting behavior:
  - Queue `loadQueue` no longer re-opens the same active claim. Background/30s refresh updates summary only.
  - `openAssignment` keeps local vessel/voyage/notes/type/route/lines unless the draft id changes or the teller clicks Reload.
  - Route-change watch skips when tariff options are empty (both Walk-in and Teller).
  - Vessel pick fills typical route only when route is still empty.
- Financial, numbering, permission, and transaction effects: none. Ticket #1006 was claimed and a draft prepared for UI verification; invoice was not posted.
- API/schema/template compatibility and migration effects: none.

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Prettier | `pnpm.cmd exec prettier --write` on the three Vue files | `apps/web` | PASS |
| Walk-in Refresh | Draft #13 notes `LAYOUT CHECK STAY` then header Refresh | Vite `:3006` | Unsaved notes, voyage 71, qty 9 remained. Notes restored to `LAYOUT CHECK` without Save. |
| Teller encode Refresh | Claim next (#1006), Prepare draft (#14), ACCORD / 88 / IN / DOMESTIC, header Refresh | Same | Form stayed; no loading overlay wipe. Not posted. Claim left open for continued encoding. |

## Acceptance and handoff

- Automated verification: none (UI bugfix). `vue-tsc` still blocked by pre-existing `user.ts:86`.
- Database workflow and database used: `online_billing_dev` via Docker API. Draft #14 created; not posted.
- Browser / PDF / physical-print verification: authenticated desktop encode + Refresh. 30s interval not separately waited (same `loadQueue` path as header Refresh).
- Business or operational acceptance (who/date/evidence), if required: not obtained.
- Not tested / blockers: Reload (intentional server reload); post of Draft #14; narrow viewport.
- Pre-existing changes preserved: unrelated dirty tree left in place.
- Next exact task/action: teller can finish encoding Draft #14 on Ticket #1006, or Reload to discard unsaved ACCORD/88. No commit unless asked.
- PROGRESS.md updated: yes
