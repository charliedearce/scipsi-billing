# P1-12: Bulletin Board

## Scope and status

- Task / phase: P1-12 / W34, in-app announcements.
- Status: Implemented in uncommitted files; role and history browser acceptance remains.
- Date and contributor: 2026-10-05, Codex.
- Tested revision: uncommitted announcement API/service/routes, announcement audience test, web announcement API/router/new Bulletin Board page, README and announcement documentation.
- Dependencies checked: existing W34 audience/version model, active banner feed, Customer/Teller/PPA route roles, Art Design Pro UI conventions.
- Decision: user approved a shared Current/Past board for Customer, Teller and PPA users after the 2026-10-04 design proposal.

## Implementation and evidence

- No legacy VB workflow changed; online announcement scope is backed by existing Laravel audience rules.
- `GET /api/v1/announcements/bulletin-board` reuses the active-feed organization, role and location checks. It includes dismissed current versions and expired current versions, and labels each `is_current`.
- The new page lists applicable notices without changing user state. Admin authoring remains under its existing route.
- Financial, numbering, transaction, schema and print behavior: unchanged. The new endpoint is read-only and uses the existing `announcements:view` permission.

| Check | Exact command or procedure | Environment / DB / device | Actual result |
| --- | --- | --- | --- |
| Regression red phase | `php artisan test tests/Feature/Announcements/AnnouncementAudienceTest.php --filter=bulletin_board` | Laravel / PostgreSQL `online_billing_test` | Failed 404 before adding endpoint, as expected. |
| Focused API | `php artisan test tests/Feature/Announcements` | Laravel / PostgreSQL `online_billing_test` | Passed 20 tests, 119 assertions after draft/scheduled/retired and cross-organization checks. |
| Final role check | `php artisan test tests/Feature/Announcements/AnnouncementAudienceTest.php --filter=bulletin_board` | Laravel / PostgreSQL `online_billing_test` | Passed 1 test, 19 assertions after adding PPA endpoint check. |
| PHP format | `php vendor/bin/pint --dirty` | Local PHP | Passed. |
| Web format | `pnpm.cmd exec prettier --write src/api/announcements.ts src/router/modules/inbox.ts src/views/communication/bulletin-board/index.vue` | Node 24 / local | Passed. |
| Web typecheck | `pnpm.cmd exec vue-tsc --noEmit` | Node 24 / local | Passed. |
| Web build | `pnpm.cmd build` | Node 24 / local | Passed; Vite emitted PWA assets. |
| Browser | Opened `http://localhost:3006/#/bulletin-board`, switched Current/Past, checked 390px viewport and horizontal scroll width | Authenticated Customer / local Vite and API | Dismissed Important notice appeared under Current; Past empty state rendered; page width equaled 390px viewport. Existing Vite process required restart to discover the newly added Vue file. |

## Acceptance and handoff

- Automated verification: focused announcement suite, final role check, format, typecheck and build as above.
- Database workflow: PostgreSQL `online_billing_test` through Laravel `RefreshDatabase`; no production database write.
- Browser: Customer only; Teller/PPA and a live expired notice not exercised.
- PDF, physical print and Crystal Reports: not applicable; not tested.
- Business acceptance: board design approved in conversation; rendered role acceptance remains.
- Pre-existing working-tree changes in fuel surcharge, banner, notifications, VIP credit and Admin navigation preserved.
- Next: verify Teller/PPA sessions and a disposable expired notice in browser, then accept P1-12 role/history presentation.
