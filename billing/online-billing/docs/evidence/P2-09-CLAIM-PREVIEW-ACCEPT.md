# P2-09: Claim preview → accept + discrepancy chat

## Scope and status

- Task / phase: P2-09 / Decision W25 (claim lifecycle UX) + W27 bill-claim chat
- Status: AUTOMATED_VERIFIED (preview/accept/decline + claim chat); browser acceptance pending
- Date and contributor: 2026-09-21 / agent
- Tested revision: uncommitted working tree (claim preview/accept/chat slice)
- Dependencies checked: W25 no existence leak before verify; W27 chat non-financial; W32 claim does not rewrite issued buyer snapshot
- Decision IDs: W25, W27, W32

## Implementation and evidence

- Legacy source: code-inferred online target (no new legacy VB change)
- Changed behavior:
  - Identity verify (code or teller) → `PENDING_CUSTOMER_ACCEPTANCE` (no My Bills link yet)
  - Portal preview / accept / decline APIs; accept links walk-in and lands on My Bills
  - `POST /api/v1/conversations/for-bill-claim/{id}` opens chat with creating teller
  - Claim Bill UI: review invoice, Accept → `/my-bills`, Decline, Chat teller
  - Teller review: Verify identity (not immediate link); Approved filter includes pending acceptance
- Financial effects: ownership link only on customer accept; posted buyer snapshot unchanged
- API compatibility: new statuses/endpoints additive; chat context_type `bill_claim`

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Focused claim suite | `php artisan test --filter=WalkInBillingAndClaimsTest` | Host PHP → Docker PostgreSQL `online_billing_test` | 22 tests / 85 assertions passed |
| Vue typecheck + format | `pnpm.cmd exec vue-tsc --noEmit`; Prettier on changed files | `apps/web` | Passed |
| Vue production build | `pnpm.cmd build` | `apps/web` | Passed; PWA precache 407 entries |
| Docker API rebuild | `docker compose --profile app build api` + recreate api/worker | Local compose | Image rebuilt; containers restarted |
| API health | `Invoke-WebRequest http://127.0.0.1:18000/api/v1/health` | Docker API | HTTP 200 `{"status":"ok",...}` |
| Authenticated browser claim preview/accept/chat | Not run | — | Pending |
| Full `scripts/test-all.ps1` | Not run this slice | — | Pending |

## Acceptance and handoff

- Automated verification: focused WalkIn claim suite + Vue build passed
- Database workflow: PHPUnit isolated PostgreSQL only; no production/legacy write
- Browser / PDF / physical-print: browser claim UX not manually accepted; physical print deferred
- Not tested: two-browser live Reverb claim chat; full repository runner
- Pre-existing changes preserved: large unrelated online-billing working tree left untouched except this claim slice
- Next exact task/action: authenticated browser walkthrough — verify → preview → accept → My Bills; decline path; chat with creating teller
- PROGRESS.md updated: yes
