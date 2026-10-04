# P2-10: Admin tariff rate details

## Scope and status

- Task / phase: P2-10 Admin pricing workspace. No financial write.
- Status: IN_PROGRESS (authenticated browser view verified; production `vue-tsc` still blocked by unrelated `user.ts`)
- Date and contributor: 2026-09-21, agent session
- Tested revision, or exact uncommitted files: uncommitted `apps/web/src/views/system/tariffs/index.vue`
- Dependencies checked: W29 rates remain versioned server facts. This slice only exposes the existing published/effective version; it does not edit posted invoices.
- Decision IDs and approvals, where applicable: user could not see rate details because the catalogue action was only Add version.

## Implementation and evidence

- Legacy source paths/symbols and confidence: not a legacy UI copy. Admin catalogue now shows the stored `tariff_versions.rate`.
- Changed paths and resulting behavior:
  - Catalogue Rate column is visible next to Code.
  - **View rate** (or row click) opens a drawer with current rate, tax/PPA/fuel, effective dates, cargo code, and that tariff’s version history.
  - Search and pagination replace the previous dump of every version table under the list.
  - Catalogue is grouped by `tariff_code`: one cargo row with rate chips; expand shows the service/route cells. Pagination is by cargo code, not exploded cells.
- Financial, numbering, permission, and transaction effects: none. Add version / Publish paths are unchanged.
- API/schema/template compatibility and migration effects: none.

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Prettier | `pnpm.cmd exec prettier --write src/views/system/tariffs/index.vue` | `apps/web` | PASS |
| vue-tsc | `pnpm.cmd exec vue-tsc --noEmit` | `apps/web` | FAIL on pre-existing `src/store/modules/user.ts:86` TS2352 only |
| Admin grouped catalogue | Login Administrator; `/#/pricing/tariffs` | Vite `localhost:3007`, Docker API, `online_billing_dev` | **206 cargo codes · 589 rate cells**. `CD01D` is one row with Stevedoring/Other ₱51.00 tags. Search `CD01D` auto-expands nested Service/Route rates. Clicking a rate tag opens the details drawer. 390px still groups by code; rates need horizontal scroll. |

## Acceptance and handoff

- Automated verification: Prettier on the Vue file. `vue-tsc` still blocked by unrelated `user.ts`.
- Database workflow and database used: read-only `online_billing_dev` tariff catalogue (589 records).
- Browser / PDF / physical-print verification: authenticated Administrator desktop grouped catalogue + search expand; 390px shows the grouped code row. PDF/print not in scope.
- Business or operational acceptance (who/date/evidence), if required: not obtained.
- Not tested / blockers: Add version / Publish from the new drawer; light theme; production `pnpm.cmd build`.
- Pre-existing changes preserved: unrelated dirty tree left in place.
- Next exact task/action: optional Teller-queue encode with an imported cargo. Do not post layout-check drafts. Do not commit unless asked.
- PROGRESS.md updated: yes
