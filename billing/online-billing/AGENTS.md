# Online billing contributor instructions

Applies to `online-billing/`. This project targets Vue 3 + TypeScript, Laravel API, and PostgreSQL. Parent instructions still govern work on the legacy application; legacy VB build commands do not validate web-only changes.

## Start every task

1. Read [README](README.md), [progress](docs/PROGRESS.md), [phase tasks](docs/PHASES.md), [architecture](docs/ARCHITECTURE.md), and the applicable acceptance/migration sections of [delivery](docs/DELIVERY.md).
2. Inspect `git status` and the actual files. Progress notes are a handoff, not proof that code or tests exist. Preserve unrelated edits, including legacy changes outside this folder.
3. Select the next eligible task from the active phase. Check dependencies and any outstanding decisions before implementation. Do not repeat accepted work unless a regression or changed requirement justifies it.
4. Trace the relevant legacy rules using the parent documentation and source. Label code-inferred behavior separately from verified database or business evidence.
5. For tax, invoice, numbering, template, credit, retention, migration or electronic-reporting work, read [BIR readiness](docs/BIR_COMPLIANCE.md). Verify applicable final official rules and the taxpayer/fiscal values needed in document output; draft attachments and legacy behavior are not proof of compliance. Do not build a BIR registration/approval tracker or conflate invoice issuance, collection and electronic sales reporting.

## Work by phase

- Use stable task IDs from PHASES.md in progress entries and validation records.
- Continue authorized tasks within the phase without repeatedly asking for permission. Seek a decision only when required information or business authority is missing; continue independent work when possible.
- A phase advances when its required tasks and exit evidence are accepted. Do not silently waive a database, physical-print, or business gate. A user-authorized overlap must be recorded with its scope and remaining dependencies.
- Planning recommendations are not automatically approved changes to numbering, monetary calculations, settlement, cancellation, or compliance rules.
- W20-W27 lifecycle improvements were explicitly approved as design on 2026-09-19. Use the approval table in `docs/discovery/CUSTOMER_SERVICE_LIFECYCLE.md` and current README; older proposal wording in historical evidence does not reopen those decisions. Keep actual Admin values, accountant-specific tax/correction inputs, implementation evidence and phase gates separate from this design approval.
- W28-W30 extend the approved design with the central Document Studio, tariff/fuel policies and VIP late charges. Read `docs/discovery/DOCUMENT_STUDIO.md`, `PRICING_RULES.md` and `VIP_CREDIT.md` before changing those areas. Layouts never calculate financial values; future tariff/fuel/late-charge settings are versioned financial policies, not generic settings. Do not convert the legacy fuel multiplier or manual Statement finance charge into a target rule without the recorded fixture/accountant gates.
- W31 selects SkySMS for planned Customer/VIP transactional SMS. Read `docs/discovery/SMS_NOTIFICATIONS.md` before changing notifications, contact preferences, templates, provider settings or delivery status. Create intents/outbox effects after the owning commit; do not let a provider response change financial state, call SkySMS under financial locks, expose API keys, treat `sent` as delivered, blindly retry an unknown normal send, or use the transactional module for bulk/marketing/two-way/OTP messaging.
- W32 requires full name, company / registered buyer name, email and mobile at customer registration. Read `docs/discovery/CUSTOMER_REGISTRATION.md` before changing registration, customer accounts, contacts, OTP, claims, buyer data or document bindings. Keep portal users, customer business accounts, verified contacts and versioned buyer profiles distinct; OTP proves phone possession only and is separate from W31 templates. Never auto-link on a matching name/phone/e-mail, expose claim existence, or let a later profile edit alter an issued invoice/receipt/OR/PDF snapshot.
- W33 is PWA readiness, interpreting the user's "WPA" request as Progressive Web App readiness unless corrected. Read `docs/discovery/PWA_READINESS.md` before adding manifests, service workers, browser storage, offline behavior, updates or push. Cache only the approved app shell; financial/API/auth/OTP/artifact/private-data paths remain network-only and server-authoritative. PWA installation does not authorize offline financial work, Web Push, SMS or cached customer data.
- W34 establishes durable Admin in-app announcements. Read `docs/discovery/ANNOUNCEMENTS.md` before changing global notices, banners, broadcasts or maintenance messages. Use a versioned, scoped, audited after-commit publication lifecycle and API recovery; do not turn an announcement into a generic setting, financial command, chat message, automatic SMS/email/push campaign, or a cacheable cross-user data source.
- Keep financial values server-authoritative and decimal; posting, numbering, allocations, audit, and outbox require the transaction boundaries in ARCHITECTURE.md.
- Treat [DOCUMENT_HISTORY.md](docs/discovery/DOCUMENT_HISTORY.md) as a cross-module contract: draft edits create attributable revisions, issued financial facts are immutable, corrections/reversals are linked actions, and audit/history records are append-only, scoped, redacted and retention-protected. Do not add a direct update/delete path for posted invoices, receipts, allocations or their artifacts.
- Keep credentials, customer exports, and sensitive test artifacts out of commits and handoff notes.

## Finish every implementation session

Update docs/PROGRESS.md with exact task IDs, changed paths, checks and outcomes, unresolved questions, and a concrete next step. Record the tested commit or identify the uncommitted files tested. Never claim a commit/push/deployment that did not occur.

For nontrivial task evidence, create `docs/evidence/<task-id>.md` using [the evidence template](templates/TASK_EVIDENCE.md). Record actual commands, environment/database, results, and manual acceptance separately. Do not invent test commands before the project is scaffolded.

Update decisions in README.md when resolved, including decision source/date and affected tasks. If a decision changes an earlier phase, reopen the relevant task and record why. Keep PHASES.md as the task specification and PROGRESS.md as the single current status ledger.
