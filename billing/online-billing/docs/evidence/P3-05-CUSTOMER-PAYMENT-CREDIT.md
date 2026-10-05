# P3-05: Customer payment credit from overpayments

## Scope and status

- Task / phase: P3-05 online manual bank deposit and cleared check settlement.
- Status: Implemented in the current checkout; accountant and multi-role browser acceptance remain open.
- Date and contributor: 2026-10-05, Codex.
- Tested revision: Uncommitted feature files in `apps/api` and `apps/web`, including the `2026_10_05_030000_create_customer_payment_credits` migration. The checkout contains unrelated pre-existing dirty work.
- Dependencies checked: Posted receipt source identity, teller proof workflow, manual payment group, posted invoice balance, receipt reversal, statement, PPA, VIP aging, dashboard, and customer account permissions.
- Decision: The user selected automatic carry-forward against the combined amount at the next checkout and removed the proposed refund action. The approved [design](../superpowers/specs/2026-10-05-customer-overpayment-credit-design.md) and [plan](../superpowers/plans/2026-10-05-customer-overpayment-credit.md) define the boundary.

## Implementation and evidence

- The teller enters the full bank-confirmed cash total. Bill allocations remain capped at their selected balances. The excess is a separate confirmed receipt tender and creates one organization/customer/currency credit lot from the posted receipt. The source receipt keeps its historical cash and unapplied amounts.
- Immutable credit movements record creation, later bill applications, and unused-credit reversal. Checkout locks the customer, available lots, then invoices; applies oldest lots to the selected bill group; and stores gross, credit applied, and residual cash due on the payment group. Full coverage creates a settled credit-only group without bank instructions, proof, or another receipt.
- Portal, teller, and Admin views expose the credit distinctly from VIP borrowing credit. The read API enforces active customer linkage or `customer_accounts:view` within the actor's organization.
- Balance, correction, PPA, VIP aging, statement, dashboard, and collections calculations include credit application as settlement. Collections receipt cash totals are not increased by reuse of credit. The collections report exposes credit movement activity separately.
- Source receipt reversal consumes unused credit once and rejects reversal after its credit has paid a later bill. Invoice correction rejects a credit-settled bill. No refund endpoint or action was added.
- The migration adds credit lots/movements and payment-group snapshots. It was applied to the local development PostgreSQL database on 2026-10-05; no production database was changed. Rollback is guarded while credit data or credit-only groups exist.

| Check | Exact command or manual procedure | Environment / DB / device | Actual result |
| --- | --- | --- | --- |
| Focused financial regressions | `php artisan test --compact` with 12 payment, receipt, correction, statement, VIP, PPA, dashboard and collections test files | Disposable PostgreSQL `online_billing_test` | 83 tests, 1,183 assertions passed before the final credit-only status fix. |
| Final affected regression run | `php artisan test --compact` with the eight payment, statement, VIP, PPA and collections test files | Disposable PostgreSQL `online_billing_test` | 59 tests, 861 assertions passed after the final fix; this includes the check-deposit selection regression on a credit-only checkout. |
| Web type check | `pnpm.cmd exec vue-tsc --noEmit` with Node 24 | Local web workspace | Passed. |
| Web production build | `pnpm.cmd build` with Node 24 | Local web workspace | Passed, including Vue type check and Vite/PWA output. |
| Prettier | `pnpm.cmd exec prettier --check` on the four touched payment/customer Vue and TypeScript files | Local web workspace | Passed. |
| PHP style | `vendor/bin/pint --test --format agent` on seven new feature PHP files | Local API workspace | Passed. An earlier aggregate check reported pre-existing dirty style issues in three modified VIP/late-charge files; those files were not broadly reformatted. |
| Local schema | `php artisan migrate --force --no-ansi` | Local development PostgreSQL | Only `2026_10_05_030000_create_customer_payment_credits` ran successfully. |
| Admin browser | Open `/#/system/customers`, view a linked customer account after migration | Authenticated Admin in-app browser, local Vite `:3006` | Read-only Customer payment credit panel rendered with a zero balance for an account with no recorded credit; no browser console errors. |

## Acceptance and handoff

- Automated verification: Focused tests and web checks above passed. Tests use synthetic data; the test database is disposable.
- Database workflow: Teller posting, credit creation, checkout, statements, PPA, and reports were exercised by feature tests against PostgreSQL `online_billing_test`. The local development migration ran; no real bank deposit was posted.
- Browser / PDF / physical-print verification: Admin read-only panel was seen in a browser. Authenticated Customer and Teller UI, a real receipt artifact with excess, narrow/dark layouts, PDF, physical printer, Crystal Reports, and legacy desktop application were not exercised.
- Business or operational acceptance: Accountant review of receipt wording, credit liability, tax treatment, and general-ledger mapping is required before live financial use. No provider, bank, taxpayer, or production acceptance is claimed.
- Concurrency: Checkout uses PostgreSQL row locks in a stable order and focused tests check retries/stale selections. A simultaneous two-session checkout stress test was not run.
- Review: A fresh delegated review was attempted, but the reviewer could not start because this workspace is out of credits. The implementer reviewed the changed financial paths directly and fixed the credit-only check-clearance status found there.
- Pre-existing changes preserved: The checkout contained unrelated changes to announcements, role management, pricing, statements, VIP credit, document studio, and legacy Crystal files. They were left in place and were not staged as part of this feature.
- Next exact action: Complete a disposable authenticated Customer/Teller overpayment and next-checkout browser walkthrough, review receipt/PDF and accounting presentation with the business owner, then prepare a selective commit after separating overlapping unrelated hunks from this dirty checkout.
- `docs/PROGRESS.md` updated: Yes.
