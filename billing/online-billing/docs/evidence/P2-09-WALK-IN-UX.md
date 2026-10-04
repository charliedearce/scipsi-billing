# P2-09: Walk-in quick create (name + notes)

## Scope and status

- Task / phase: P2-09 / Decision W25 walk-in UX refinement
- Status: AUTOMATED_PARTIAL (Vue typecheck; browser acceptance open)
- Date and contributor: 2026-09-21 / Auto
- Tested revision, or exact uncommitted files:
  - `apps/web/src/views/billing/walk-in/index.vue`
  - `docs/PROGRESS.md`
  - `docs/evidence/P2-09-WALK-IN-UX.md`
- Dependencies checked: blank TIN posting already allowed; claim does not rewrite issued buyer snapshot
- Decision IDs and approvals: W25 walk-in without portal account; user chose suggestion #2 (minimal name + notes create)

## Implementation and evidence

- Legacy source paths/symbols and confidence: N/A (online teller UX)
- Changed paths and resulting behavior:
  - Create drawer: required name + optional notes; TIN/address/contact collapsed under “More details”
  - Notes from create (or detail panel) passed to `POST .../invoice-draft`
  - Detail panel omits empty TIN/address and states claim ownership tagging does not rewrite the bill
- Financial, numbering, permission, and transaction effects: none (UI-only; posting rules unchanged)
- API/schema/template compatibility and migration effects: none

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Prettier | `pnpm.cmd exec prettier --write src/views/billing/walk-in/index.vue` | `apps/web` host | Formatted OK |
| Vue typecheck | `pnpm.cmd exec vue-tsc --noEmit` | `apps/web` host | Exit 0 |
| Name-only create API | `php vendor/bin/phpunit --filter test_walk_in_customer_can_be_created_with_name_only tests/Feature/WalkIn/WalkInBillingAndClaimsTest.php` | `apps/api` host / isolated PostgreSQL | 1 test / 9 assertions passed; fixes omitted `buyer_address` 500 |
| Docker API rebuild | `docker compose --profile app build api; docker compose --profile app up -d api` | local compose | Exit 0; container has SafeBroadcast + buyerAddress guards |
| Browser walk-in happy path | Teller → Walk-in Billing → New walk-in → name + notes → draft → post → Claim Bill | Pending user retry | Pending |

## Acceptance and handoff

- Automated verification: `vue-tsc --noEmit` pass
- Database workflow and database used: not exercised this session
- Browser / PDF / physical-print verification: browser not tested; print N/A
- Business or operational acceptance: pending teller try of quick create
- Not tested / blockers: authenticated browser, full `pnpm build` not required for this slim UX tweak
- Pre-existing changes preserved: unrelated legacy/API/web working-tree edits left untouched
- Next exact task/action: teller browser pass — create with name+notes only, post blank TIN, hand invoice number for claim
- PROGRESS.md updated: yes
