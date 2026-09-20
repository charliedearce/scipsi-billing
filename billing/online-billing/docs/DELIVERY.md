# Delivery, migration, and acceptance plan

Status: PLANNING, 2026-09-17. No web implementation or migration has been performed by this documentation change.

Design approval 2026-09-19: the user accepted W20-W35, including [lifecycle](discovery/CUSTOMER_SERVICE_LIFECYCLE.md), [Document Studio](discovery/DOCUMENT_STUDIO.md), [tariff/fuel rules](discovery/PRICING_RULES.md), [VIP late charges](discovery/VIP_CREDIT.md), [transactional SMS](discovery/SMS_NOTIFICATIONS.md), [customer registration](discovery/CUSTOMER_REGISTRATION.md), [PWA readiness](discovery/PWA_READINESS.md), [announcements](discovery/ANNOUNCEMENTS.md) and [document history](discovery/DOCUMENT_HISTORY.md). The acceptance contract below reflects those choices; exact taxpayer/accounting, privacy, security and provider inputs remain reviewed separately. The existing P1-01 scaffold is retained; this documentation revision adds no runtime implementation or phase acceptance.

## 1. Phases and exit gates

The executable task breakdown is in [PHASES.md](PHASES.md); [PROGRESS.md](PROGRESS.md) is the single current status ledger. Task completion requires recorded evidence, not just a completed document or scaffold.

| Phase | Deliverables | Exit evidence |
| --- | --- | --- |
| 0. Discovery and rule capture | Source SQL schema/procedures/triggers; workflow inventory; sanitized examples; printer and template inventory; decision log | Source mapping reviewed; unresolved rules named; disposable SQL Server restore available |
| 1. Foundation | Vue/TypeScript + Laravel API skeleton, PostgreSQL migrations, sessions/policies, user/role administration, typed application settings, CI, OpenAPI, structured audit, document revision/history foundation, required customer registration/buyer/contact/OTP, SkySMS template/delivery, PWA shell/update safety and in-app announcement foundations | Repeatable fresh setup; direct API authorization, scope, CSRF, privilege-management, session-revocation, registration/OTP/PWA/announcement, settings, history permissions/redaction and fake-provider notification tests pass |
| 2. Invoice and digital document proof | Draft/items, decimal calculator, versioned tariff/tax/PPA/fuel rules, series/register, atomic posting with immutable buyer snapshots, idempotency, Admin Document Studio sales-invoice service/NSCL/PPA layout proof and committed request/invoice/tax SMS intents | Competing posts, buyer/pricing/SMS boundary and retry tests pass; server-rendered PDF/browser artifact proof accepted; Studio/renderer approach selected. Physical printer alignment is conditional and deferred |
| 3. Receipts and corrections | Full/partial allocations, tenders/withholding, collection-receipt/OR Studio output, approval execution, reversal policy, customer payment portal, gateway/proof collection, teller review, PPA verification, VIP late-charge ledger and committed settlement/VIP SMS delivery/status reconciliation | Concurrent cross-channel allocations, receipt reversal, late-charge and notification reconciliation pass; four-role acceptance and gateway/SMS provider sandbox evidence; authorized business rules recorded |
| 4. Operational parity | Master data, periods/backdates, statements, transmittals, reports/exports, template administration and communications operations | Every retained desktop workflow mapped to web acceptance evidence; totals agree with fixtures; delivery/suppression/unknown-status operations are observable |
| 5. Migration rehearsal | Restartable imports, exception queue, record mapping, financial reconciliation, backup/restore drill | All included records reconciled; every exclusion/adjustment documented; cutover timings measured |
| 6. Pilot and cutover | Operator training, pilot, release runbook, support/monitoring, final import | Business acceptance, operational recovery evidence, and explicit cutover approval |
| Separate integration gate | Electronic-invoice adapter after verified final contract | Mapping/validation, retries, duplicate prevention, and provider reconciliation tested |

No calendar estimate is committed until Phase 0 establishes record volumes, report complexity, required historical coverage and, if physical printing is enabled, printer constraints. Each phase may contain small implementation tasks, but financial and digital artifact acceptance cannot be replaced by compilation or screenshots; physical printer acceptance is a separate conditional gate.

W19 adds [BIR compliance readiness](BIR_COMPLIANCE.md): P0-06 establishes final-source/applicability inputs; P2-06 is a core fiscal issuance gate; P4-05 prepares accounting/review outputs. P6-02 requires current taxpayer-specific review and evidence for system behavior. BIR registration/approval tracking is explicitly outside the product. Electronic issuance readiness and active electronic sales reporting are distinct; the separate integration gate does not make ordinary invoice compliance optional.

## 2. Acceptance matrix

| Area | Required scenarios and expected result |
| --- | --- |
| Calculation parity | Domestic/foreign, VAT/PPA on/off, discounts, danger/fuel, `XXXX`, CITW, fractional quantity, boundary values; compare intermediate and final decimals against verified source fixtures |
| Tariff classification | Mixed VATable/exempt/zero-rated, PPA-applicable/non-applicable and fuel-applicable/non-applicable lines use captured tariff/rule versions; no bill-wide checkbox side effect, generic tax boolean or post-issue reclassification |
| Fuel surcharge | Published price source/unit/currency, minimum-inclusive/maximum-exclusive bands, zero band, scheduled Asia/Manila activation, no overlap/gap/ambiguous match, stale draft/post conflict and decimal basis/rounding; no valid source/range blocks posting instead of silently using zero |
| Periods | Days 25/26, December, year change, permitted backdate, closed period, timezone; retain known legacy behavior unless a recorded decision replaces it |
| Atomic posting | Fail after each staged write and before commit; no partial posted document, number, allocation, audit, or outbox remains |
| Idempotency | Same key/same body returns same result; same key/changed body conflicts; lost response/retry and simultaneous same-key calls create one document |
| Numbering | Two clients/series exhaustion/expired reservation/void number/imported number collision; enforce uniqueness and approved reuse policy |
| Draft conflicts | Two operators edit/post same draft; stale expected version rejected without overwriting the newer document |
| Receipt integrity | Full/partial, two concurrent partial payments, wrong customer, canceled invoice, cash/check, withholding, insufficient tender; only valid current balances settle |
| Corrections | Receipt reversal twice, invoice with active allocations, stale approval, self-approval, expired backdate; no duplicate release of balances or unauthorized mutation |
| Print routing | Normal, PPA, NSCL1/2/3, mixed items, lowercase/space prefixes, NSCL with PPA; saved rule selects the expected whole-document layout |
| Print fidelity | Current scope: blank/long/multiple-page items, fonts, amount-in-words, digital PDF pagination and server/browser artifact fidelity. Preprinted margins, printer scaling and physical samples are deferred until physical printing is enabled |
| Template history | Publish new layout after issuance; old artifact unchanged; missing/corrupt layout diagnosed; failed render retried without financial repost |
| Document history and audit | Draft edit creates an attributable before/after revision; stale editor is rejected; posted invoice/receipt/payment facts remain immutable; corrections/reversals link originals; approval, artifact, print, access and rejection events are searchable; sensitive values are redacted and history cannot be edited/deleted through Admin |
| Document Studio | Draft/preview/validate/publish/activate/retire workflow, protected fiscal fields/legends, scoped/effective routing, assets, service/NSCL/PPA and collection-receipt contracts, unauthorized/stale/ambiguous activation, server-PDF parity and no layout-side calculation |
| Access | Direct forbidden API calls, cross-location ID/list/export/file access, session expiry, CSRF, approval permission; no unintended data or action access |
| User administration | Invite/activate/suspend/reactivate, role and location assignment, effective-permission preview, session revocation, self-escalation and last-admin protection; historical actor remains attributable |
| Settings | Authorized organization/location update, invalid type/key/scope, forbidden deployment secret, stale version, override precedence, cache refresh and audit; posted documents remain unchanged |
| Registration, buyer profile and OTP | Required full name/company/e-mail/mobile, normalized/shared-contact collision/no enumeration, verified e-mail/mobile, purpose-bound expiry/replay/resend/attempt controls, redacted logs and no company/account authority from OTP. Company/contact/profile edits affect future documents only; applicable fiscal buyer fields come from P0-06. |
| PWA | Manifest/install/update/offline/reconnect behavior across clean and existing sessions; only static shell cacheable, no protected API/auth/CSRF/OTP/artifact/customer cache, no offline command/background financial replay, safe session/account switch purge and release compatibility. Installation does not request Web Push permission. |
| In-app announcements | Scoped draft/schedule/publish/expiry/retire/revision, immutable published content/safe rendering, all-active/role/location audience authorization, per-user seen/acknowledge/dismiss, after-commit Reverb/API recovery, audit and no recipient enumeration/SMS/e-mail/push/financial side effect. |
| Requirements and uploads | Required/optional types, service/location versions, incomplete/quarantined files, replaced reviewed files and changed requirement sets; only complete safe submissions auto-enter teller queue, with retained evidence versions |
| Request queue | Stable transaction/ticket independent of fiscal numbers; two tellers/duplicate submission, oldest-eligible selection, cross-day carryover and disconnected assignment recovery; corrected request retains original priority without preempting active work |
| Tax evidence | Separate withholding eligibility/certificates and tax-specific exemptions; wrong party/period/type, validity/expiry/revocation, mixed taxes and late evidence; no discount flag, double certificate use, automatic paid status or retroactive invoice change |
| Walk-in claims | Bill created without login; registration/number entry requires a distinct claim-purpose verified contact/code or staff review before disclosure; no reuse of registration OTP, buyer rewrite, guessed-number disclosure or accidental account-wide authority |
| Issued-unpaid correction | Approved W26 draft edit versus linked Correct bill; use accountant-reviewed fiscal correction documents/tax effects. Issued corrections retain original number/PDF/history and reconcile active checkout, proof, check, withholding and credit dependencies; late confirmation cannot settle a stale/replaced invoice silently |
| Customer portal | Customer A cannot look up customer B's bill, download their evidence or create a payment against it; preserve number zeroes; authenticated lookup uses verified account links |
| Proof review | Uploaded proof remains pending; two tellers/repeated approval create one receipt; reject/resubmit retains evidence; duplicate transfer/manual-source detection, invalid upload quarantine, and atomic rollback |
| Gateway | Enable/disable and in-flight transitions; spoofed return URL, invalid signature, wrong amount/currency/account/environment, duplicate/reordered/delayed events, lost checkout response and gateway/manual race; retain confirmed unapplied funds for reconciliation |
| Payment routing and timers | Approved aggregate gross remaining balance before current-group withholding/excluding fees; strict less-than. With sample PHP 10,000, only 9,999.99 qualifies, not 10,000.00/10,000.01, two 6,000 bills or 10,100 minus 200 withholding. Reject partial-entry regular checkout; retain actual partial funds. Snapshot instruction-start deadline; retry/reopen does not reset it. On-time proof/late review, resubmission deadline and late funds never change invoice/credit due dates |
| Check clearance | Deposit evidence, pending check, clearance and dishonor are distinct; approved W24 requires cleared funds before final settlement/receipt application and PPA paid status. Reconcile actual funds and any provisional acknowledgment under accountant-reviewed rules |
| Realtime, chat and notifications | Scoped queue/bill/verification/balance updates refresh authoritative API data; silent refresh is distinct from alerts and never overwrites unsaved edits. Durable messages/unread state, private channels, staff-note isolation, revoked sessions, disconnected/reconnected Reverb and duplicate/stale events; no leaked files or chat-driven financial approvals |
| Transactional SMS | Verified/current contact and account authority; preference/purpose outcome; draft/preview/publish/activate safe template versions; no URLs/protected details/marketing; committed local intent/outbox before worker send; rate limits/backoff; pending/queued/sent/failed/unknown provider states; no blind timeout retry; known-failure resend audit; scoped dashboard and no SMS state altering invoice/payment/receipt/credit/PPA/fiscal result |
| PPA verification | Assigned scope only; pending/partial/reversed bills never appear fully paid active; current balance/receipt agrees across all workspaces; PPA cannot post or approve payment proofs |
| VIP credit | Own eligible invoices only, active account policy, simultaneous users at limit, repeated/batch charge, profile suspension; charge creates no second receivable or payment receipt; due dates/terms stay captured |
| VIP repayment | Bank upload with gateway on/off, permitted non-teller reviewer, explicit multi-invoice amounts, partial/stale/duplicate transfer, review race and receipt reversal; pending proof leaves exposure unchanged and approval posts once |
| VIP aging | Due date and 1/30/31/60/61/90/91-day boundaries, later/backdated payments and reversals, original due dates, unclassified imported terms and currency separation; buckets reconcile to as-of outstanding and PPA distinguishes on-credit from paid |
| VIP late charges | Captured policy eligibility/effectivity, grace/band/cadence/cap/rounding boundary, Manila-date cutoff, non-compounding principal-only base, scheduled retry/concurrent run, timely-pending-proof/hold, payment/reversal/correction/dispute, waiver separation and principal-versus-charge allocation/reporting. No automatic tax/fiscal document treatment without reviewed matrix |
| Credit/PPA settings | Admin default/client override precedence, permissions, stale edits, policy-change races, grace without re-aging, preserved invoice terms, zero/unlimited limits, limit reduction below exposure, both PPA modes and continued repayment while credit blocked |
| Jobs | Crash before/after delivery, duplicate event, payment-provider/SkySMS timeout, worker restart, rate-limit/credit failure and status-sync delay; effects deduplicated or reconciled and failures observable |
| Reports | Invoice/receipt/statement/transmittal totals by account/date/period/status reconcile; pagination/filtering/export scopes agree |
| Recovery | Restore PostgreSQL and artifacts, replay pending outbox safely, reconcile issued numbers; measured recovery meets agreed objectives |
| Fiscal invoices | Reviewed taxpayer/branch/series and tax profile, applicable mandatory issuer/buyer fields/legends, normal/NSCL/PPA protected layouts, immutable buyer snapshot, extractable payload/PDF totals, cash/credit issuance; Admin cannot bypass fiscal rules or rewrite issued snapshots |
| Fiscal collection and history | VIP charge, later partial/manual/proof/gateway collection and corrections do not create duplicate sales; pre-EOPT imported receivables retain reviewed tax provenance; no automatic historic e-submission |
| Fiscal review package | Taxpayer/fiscal profile inputs, accountant-reviewed samples and books/GL interface, reconcilable exports, audit/retention holds and restore; final external protocol/acknowledgment and required timing tested when applicable. Registration/approval outcome is not tracked by the app |

Use real PostgreSQL for transaction/locking tests, not SQLite substitutes. Use isolated test databases and synthetic or sanitized fixtures. Keep unit tests for pure rules, integration tests for posting/races, and browser tests for operator journeys. Preserve physical printer acceptance as separate future evidence; it is deferred from the current online-only scope.

## 3. SQL Server to PostgreSQL migration

### Inventory before mapping

Script tables, types, defaults, keys, indexes, triggers, procedures, views, and permissions using approved read-only access or a restored copy. Do not export credentials. Establish source row counts, date ranges, database encoding/collation, decimal scales, time assumptions, and report outputs. The repository's typed dataset is only a report projection.

Inspect orphan items/allocations, duplicate printable numbers, number-pool overlaps with issued documents, inconsistent status values, canceled/partial receipt balances, malformed dates, missing customers, and historical deletion gaps. Quarantine exceptions with source keys and reason; do not run legacy duplicate cleanup or silently invent missing transactions.

### Initial mapping candidates

| Source | Target | Review requirement |
| --- | --- | --- |
| `tbl_account`, `tbl_service`, `tbl_tarrif`, bank/vessel | Master data and versioned pricing | Preserve codes, route components, and historical snapshots |
| `tbl_settings` | Reviewed organization/location baseline plus domain-owned configuration | Classify every field; do not import credentials or turn financial/number/date controls into untyped generic settings |
| `tbl_bill_trans`, `tbl_item_trans` | invoices, invoice_items, document_snapshots | Reconcile line/header amounts, dates, period/reference, VAT/PPA flags and status |
| `tbl_or_trans`, `tbl_orbill_trans` | receipts, tenders, payment_allocations, provenance | Verify actual cash/withholding/discount and remaining-balance meaning; do not copy a stale last-row balance as authority |
| `tbl_bill_no`, `tbl_or_no` | document_series/register | Classify available/reserved/issued/void without colliding with imported history |
| `tbl_reqbill`, `tbl_reqor`, `tbl_datesreq`, `tbl_logs` | approval history, audit archive | Preserve source strings/actors; historical approval does not become a fresh executable request |
| SOA and yellow/white transmittal tables | statement/transmittal snapshots and memberships | Preserve as-of meaning and source membership; validate active/canceled inclusion |
| `tbl_users` | users and reviewed memberships | Require activation/reset; do not retain fallback credentials or plain passwords |
| `.repx`, `.rpt`, procedure-generated output | versioned recreated templates and report specifications | Validate field mapping/formatting; no assumption of automatic import compatibility |

Keep a unique `(source_system, source_table, source_primary_key)` map to target records and import batch ID. Where the source lacks a reliable key, define a stable extraction identity after schema review. Preserve raw legacy statuses: `FALSE`/`TRUE`, `N`/`Y`, `F`/`P` are mapped explicitly, never guessed from truthiness. Legacy print flags indicate historical request state, not proven paper delivery.

Historical imports must not fabricate live posting events, portal users/links, verified e-mail/mobile contacts, OTP/claim challenges, SMS/e-mail/announcements, print, or e-invoice submissions. Record imported buyer/document snapshots as historical and distinguish missing historical artifacts from newly rendered representations.

W19 additionally requires original fiscal document type/date, taxpayer identity, tax regime and prior reporting provenance where available. Do not bulk-convert historical ORs into sales invoices. Accountant review must resolve pre-EOPT service receivables and missing fiscal evidence before dependent collection/tax mappings are accepted; neither importing nor later collecting a balance automatically represents a new sale. See BIR-11 in [BIR readiness](BIR_COMPLIANCE.md).

### Rehearsal and reconciliation

1. Extract a consistent source snapshot to restricted staging and record extract time/hash/counts.
2. Transform with explicit code/date/decimal/status maps; validate every exception.
3. Load in dependency order, retaining source identity and rerun protection. Verify source and target totals independently.
4. Compare counts and amount sums by customer, document type, active/canceled status, date, and period; reconcile balances per invoice and account, tender totals, withholding, and number availability.
5. Retain source/target checksums or row comparisons, differences, approved resolutions, batch log, and elapsed times. Distinguish source anomalies from import defects.
6. Repeat a full migration and a restore rehearsal using the same scripts before approving cutover.

### Cutover and rollback

Run a read-only/shadow pilot or isolated synthetic pilot first. Do not let independent legacy and web applications issue overlapping numbers or settle the same invoices. Default cutover: freeze legacy writes, complete backup/final consistent extract, import, reconcile, obtain operational signoff, switch users, then keep the old database read-only for reference.

Before new web transactions exist, rollback can restore the prior deployment/source under the runbook. Once web transactions have been posted, simply switching back loses financial history; freeze writes and reconcile/export those transactions through an approved recovery procedure. Recovery must include numbers, allocations, artifacts, and outbox effects. A deployment rollback alone is not a financial rollback.

## 4. Open decisions and owners

Owner labels describe required roles, not people already assigned.

W20-W35's approved choices are recorded in the lifecycle, Studio, pricing, VIP, SMS, registration, PWA, announcement and document-history contracts and are not open decisions. Remaining rows distinguish actual setup values and domain-specific review from that approval; neither prevents independent design work. No initial numeric threshold/hours/fuel rate/late-charge rate, taxpayer tax rate, OTP/SkySMS production switch, PWA cache policy, announcement publishing policy or document-history retention/tamper-evidence policy was activated by this revision.

| Remaining decision or setup input | Current boundary / unresolved detail | Owner / latest gate |
| --- | --- | --- |
| Single company, multiple branches, or SaaS? | One organization; explicitly scoped locations | Business owner / schema freeze |
| Allow additional custom roles? | Customer, PPA user, Teller, Administrator confirmed (W15); stable permission catalog with protected baseline role boundaries | Business/security owner / Phase 1 |
| Customer enrollment and bill-account links? | W25 approves verified contact/one-time code or teller-reviewed claim, not number-only disclosure. Define accepted identity evidence and verified delivery channels; invoice claim alone cannot grant full-account/delegated authority | Operations / P1-06, P2-09 and P3-05 |
| PPA lookup scope and formal clearance? | Assigned verification scope with current paid/unpaid/partial state and audit; cargo release/clearance is separate | Operations/PPA owner / P3-07 |
| Gateway/provider and exception collection rules? | Provider, fee accounting, excess/refunds/disputes remain undecided. W23 approves combined gross remaining balances before this group's withholding/excluding fees, strict less-than and full-balance initial regular checkout. Partial-entry checkout or additional daily/original-invoice limits would require a future policy change | Operations/accounting / P3-01, P3-06 and P3-10 |
| Tax evidence applicability? | Separate withholding certificates from agent eligibility; tax-specific exemption validity/coverage and reviewed base/rate. Confirm exact documents, reviewer grants, late-certificate treatment and transaction-specific tax rules | Accountant / P0-06, P2-08 and P3-01 |
| Initial payment/review settings? | W24 approves instruction-start hours, separate review/clearance clocks, correction deadline and retained late funds. Set actual threshold, hours, reminders, correction window, receiving accounts and service targets in Admin; do not hardcode sample values | Admin setup / operational payment activation; controls in P3-10 |
| Staff dispatch scope and overrides? | W22 approves oldest-eligible non-preemptive processing, separate worklists and original correction priority. Assign scoped staff/override permissions; no inactivity-based priority forfeiture or automatic VIP priority | Operations / P2-07 |
| Payment-proof matching and review? | Teller matches received funds/reference; proposed different reviewer for staff-submitted proof; duplicate source checked across manual and uploaded routes | Operations/accounting / P3-05 |
| Initial credit settings values and account scope? | W18 confirms Admin Settings controls for limits, terms/date origin and overdue restrictions; choose initial values during setup; shared account scope still needs definition | Admin setup / operational credit activation |
| VIP bill selection and repayment allocation? | Proposed full remaining balance per charge and all-or-nothing selection; explicit per-invoice repayment amounts, partial/multi-bill support and one verified transfer identity | Operations/accounting / P3-08 |
| Credit aging cutoff and formal release? | P3-09 engineering uses an Asia/Manila calendar as-of date: credit-charge business date includes debt and posted receipt business date applies settlement, while recorded timestamps remain visible. Accounting/PPA owner must accept this cutoff or specify backdating/reversal deviations; PPA qualifying-credit acceptance is versioned and independent of paid status. Formal release action remains separate | Accounting/PPA owner / P3-09 acceptance |
| Which settings allow location overrides? | Deny overrides by default; allow only registry-approved non-financial keys | Business/operations owner / Phase 1 |
| Preserve physical number pools? Scope/reset of numbering? | Transactional register, preserve ten digits, no reuse after posting | Operations/accounting / Phase 2 |
| Exact fiscal correction and broader reversal policy | W26 approves editable drafts and linked correction of issued unpaid invoices with preserved originals/in-flight reconciliation. Accountant must define applicable cancellation/replacement/adjustment documents, tax effects and broader receipt-reversal rules | Accounting/business owner / P2 issuance review and P3-03 |
| Document Studio renderer and physical profiles | W28 requires a central controlled Studio and server-canonical PDF. The JSON/Dompdf digital renderer is selected and verified for the current online scope; paper/printer profiles, stock overlays and any local silent-print agent are deferred until device printing is enabled. Fiscal binding/visibility rules remain mandatory regardless of renderer | Engineering/operations/accountant / P2-03/P2-04 now; P0-04/P2-05/P3-02 when physical output is enabled |
| Tariff/fuel policy inputs | W29 approves versioned tariff tags and scheduled fuel-price bands. Define source/grade/unit/currency, scope, update cadence, allowed calculation basis, rounding/truncation and tax/PPA/fuel ordering; published changes apply prospectively | Operations/accounting / P0-03, P0-06, P2-10 |
| VIP late-charge contract and fiscal treatment | W30 approves a separate configurable, non-compounding-by-default late-charge lifecycle. Define customer contract/notice, eligibility, bands/rates/caps/cadence, allocation priority, hold/waiver/reversal, tax/withholding/GL treatment and fiscal document/series/template | Business/accounting/tax owner / P0-06, P3-11, P4-05 |
| SkySMS transactional notification activation | W31 selects SkySMS for eligible Customer/VIP events but does not prove account ownership, sender/carrier behavior, normal-send idempotency/correlation, status/webhook semantics, character/credit rules, data processing/retention, DPO purpose/preference/opt-out policy or production readiness. No marketing/bulk/two-way/OTP scope | Operations/security/DPO/provider owner / P0-05, P1-09, P2-11, P3-12 and P6-02 |
| Registration, buyer profile and OTP activation | W32 requires full name/company/e-mail/mobile but does not resolve individual-buyer naming, exact fiscal buyer fields, legal/company authority, e-mail provider, OTP provider/correlation/resend/retention, rate limits, recovery or shared-contact/delegated authority policy | Product/security/accounting owner / P0-05, P0-06, P1-10, P2-06, P2-09, P3-02 and P6-02 |
| PWA readiness and update policy | W33 is safe installable-shell planning, not offline billing. Confirm branding/icons/browser matrix, service-worker cache/update/rollback policy, permitted local recovery, privacy telemetry and any separately approved Web Push scope | Product/operations/security owner / P1-11 and P6-02 |
| In-app announcements | W34 confirms in-app maintenance/important-information notices. Confirm critical-message approval/retention, all-active versus role/location audience policy, support wording, emergency maintenance process and any separately approved external escalation | Operations/product/security owner / P1-12 and P6-02 |
| Offline operation required? | Online posting only; drafts may recover after reconnect | Operations / Phase 1 |
| Hosting, availability, backup retention, RPO/RTO | Managed PostgreSQL/private storage; numbers to be measured and agreed | Operations / pilot |
| Required printer types and silent printing? | PDF/browser dialog is current scope; an optional local agent/device profile is a later decision only if physical printing is enabled | Cashiers/operations / deferred P2-05 |
| Custom designer or DevExpress reporting backend? | Focused JSON designer subject to fidelity/cost prototype | Engineering and operators / Phase 2 |
| Historical data/report coverage and exceptions? | Full retained transaction history where available; explicit exception register | Accounting / Phase 5 |
| Legacy formula/period deviations? | Preserve verified behavior; proposed corrections separately approved | Accounting / Phase 0 and affected phase |
| Taxpayer profile, fiscal invoice fields/tax rules and electronic obligations? | W19 official baseline exists; applicable values and exact legal matrix need taxpayer/accountant evidence, not draft attachments or legacy behavior. Registration/approval workflow is external | Accountant/taxpayer with relevant BIR office / P0-06, P2-06 and P6-02; final reporting contract / EI-01 |
| Books/GL scope and records retention? | Billing is not a complete GL; agree reconciled accounting interface and applicable books outputs; retention based on filing anchors plus holds, not invoice age alone | Accountant/records custodian / P4-05 and P5-03 |
| Provisional check acknowledgment and exceptions? | W24 approves final settlement/receipt application only after funds clear. Review provisional acknowledgment form/accounting, dishonor, excess-funds and refund handling; deposit-proof acceptance alone cannot mark paid | Accounting / P3-01 and P3-10 |

Unresolved decisions block only their dependent implementation. For example, calculation fixtures, source mapping and digital document artifacts can progress while printer selection remains deferred.
W17/W30 confirm VIP credit, aging, bank-proof repayment, approved late-charge capability and monitoring by authorized staff. W31 adds only post-commit, provider-reported Customer/VIP SMS; delivery does not change the shared settlement or credit ledger. W32 requires the same explicit user/account/contact authority before a VIP portal user can see/charge an account; OTP does not grant borrowing capacity. The contracts and phase mapping are in [VIP_CREDIT.md](discovery/VIP_CREDIT.md), [SMS_NOTIFICATIONS.md](discovery/SMS_NOTIFICATIONS.md) and [CUSTOMER_REGISTRATION.md](discovery/CUSTOMER_REGISTRATION.md). Phase 3 acceptance now includes P3-08/P3-09/P3-11/P3-12; credit bank repayment is available independently of gateway enablement. Credit-account historical mapping joins Phase 5 reconciliation and does not create a second debt for imported invoices, fabricate historical finance charges or send historical SMS.

## 5. AI implementation handoff

Future implementation agents must read this package and the legacy docs before starting. The web project root is `online-billing/` inside the legacy repository. Follow the web-specific [AGENTS.md](../AGENTS.md), resume from [PROGRESS.md](PROGRESS.md), and use [PHASES.md](PHASES.md) task IDs. Parent-root instructions describe the legacy VB application.

For each work item, record:

- Objective and applicable decision IDs; whether it is parity or an approved behavior change.
- Legacy event/module/table/report evidence and source confidence.
- Proposed endpoint/schema/UI changes and transaction/lock/idempotency boundaries.
- Calculation/status/number effects and template contract compatibility.
- Tests and acceptance evidence, open issues, and next actionable step.

Keep an implementation status per work item in PROGRESS.md: `PLANNED`, `IN_PROGRESS`, `BLOCKED`, `DEFERRED`, `AUTOMATED_VERIFIED`, `PILOT_PENDING`, or `ACCEPTED`. `DEFERRED` means intentionally outside the current release scope and must be re-opened before enablement. Passing tests does not imply physical-print, migration, or business acceptance. Record configuration, test database, printer/paper when applicable, fixture version, and revision with evidence using the [task evidence template](../templates/TASK_EVIDENCE.md).

Never copy source credentials into the new project. Never treat inferred tables as confirmed DDL. Do not change truncation, number reuse, period cutoffs, or payment semantics under the label of refactoring. Keep architectural decisions and OpenAPI/schema docs updated with the code, and use small vertical tasks that can be independently reviewed.

## 6. Current evidence

- Reviewed existing README, architecture, database, and development documentation, plus current NSCL selection and calculation call sites.
- Confirmed the planning direction and documented proposed improvements; no target tables, API, UI, or renderer implemented.
- Original planning pass did not perform source SQL capture, live DB workflow, physical printing or regulatory review. Subsequent read-only discovery and official-source research are separately recorded in [PROGRESS.md](PROGRESS.md) and [P0-06 evidence](evidence/P0-06.md); no regulatory signoff or BIR registration is implied or tracked by the product.
- Existing desktop implementation edits, Crystal Report modifications, and project-file edits remain separate pre-existing work.
