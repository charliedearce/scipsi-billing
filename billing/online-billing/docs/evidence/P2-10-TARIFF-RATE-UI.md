# P2-10: Admin tariff rate details

## Scope and status

- Task / phase: P2-10 Admin pricing workspace UX. Does not change tariff rates, uniqueness, or encode locking.
- Status: IN_PROGRESS (authenticated Admin browser verified; production Vue build still blocked by pre-existing `user.ts` TS2352)
- Date and contributor: 2026-09-21, agent session
- Tested revision, or exact uncommitted files: uncommitted `apps/web/src/views/system/tariffs/index.vue`
- Dependencies checked: catalogue already loads versions from `GET /api/v1/admin/tariffs`. Rates stay server-authoritative; this slice only shows them.
- Decision IDs and approvals, where applicable: W29 versioned tariffs. Same cargo code may appear once per service/route cell.

## Implementation and evidence

- Legacy source paths/symbols and confidence: not required. Online tariffs already store the imported rate on `tariff_versions.rate`.
- Changed paths and resulting behavior:
  - Tariff Catalogue shows **Rate** beside **Code**, with **View rate** (not only Add version).
  - Search and pagination (20 per page) replace the unusable 589-row dump / stacked version-history tables.
  - **View rate** opens a drawer with current rate, tax/PPA/fuel tags, effectivity, cargo code, and that tariff’s version history.
- Financial, numbering, permission, and transaction effects: none. No tariff, version, invoice or draft was written.
- API/schema/template compatibility and migration effects: none.

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Prettier | `pnpm.cmd exec prettier --write src/views/system/tariffs/index.vue` | `apps/web` | PASS |
| vue-tsc | `pnpm.cmd exec vue-tsc --noEmit` | `apps/web` | FAIL only pre-existing `src/store/modules/user.ts:86` TS2352 |
| Admin catalogue | Authenticated Administrator at `http://localhost:3006/#/pricing/tariffs` | Vite `:3006`, Docker API `:18000`, `online_billing_dev` | Rate column visible as PHP. Search `CD01D` → 2 of 589. Pagination present. |
| View rate drawer | Click **View rate** on CD01D OTHER / DOMESTIC | same | Drawer `Tariff rate — CD01D`, current rate `₱51.00`, status effective, tax VATABLE, cargo code `40005`, version history. No write. |

## Acceptance and handoff

- Automated verification: Prettier pass. `vue-tsc` still blocked by unrelated `user.ts`. `pnpm.cmd build` not claimed.
- Database workflow and database used: read-only `online_billing_dev` (589 tariffs).
- Browser / PDF / physical-print verification: desktop Admin `/pricing/tariffs` list + drawer. Narrow viewport not resized. PDF/print not in scope.
- Business or operational acceptance (who/date/evidence), if required: not obtained.
- Not tested / blockers: keepAlive may keep an old tab until it is closed; Docker API image rebuild still outstanding; no production build.
- Pre-existing changes preserved: unrelated dirty tree left in place.
- Next exact task/action: if the old Add-version-only list is still showing, close the Tariffs tab and reopen Pricing & Tariffs. Do not post drafts. Do not commit unless asked.
- PROGRESS.md updated: yes
