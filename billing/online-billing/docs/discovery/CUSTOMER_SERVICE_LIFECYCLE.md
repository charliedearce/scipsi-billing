# Customer request, billing and collection lifecycle

Design approved: 2026-09-19. Applies to W20-W34 and the existing W13-W19 requirements. The user approved incorporating the lifecycle, Document Studio, tariff/fuel, VIP late-charge, transactional-SMS, registration/OTP, PWA and announcement improvements into the design. This is the accepted workflow baseline, not implemented behavior or accounting/BIR/privacy/security/provider acceptance. Current task status remains in [PROGRESS](../PROGRESS.md).

## 1. Approved lifecycle baseline and remaining inputs

The user requested customer withholding/exemption evidence review; administrator-defined billing document types; automatic teller queue entry after submission; a transaction reference and queue number; first-come-first-served processing with priority retained after document correction; bill-ready notifications; selection of bills for payment; an admin-configurable gateway amount threshold and manual-payment deadline in hours; automatic collection receipts following verified payment; walk-in billing and later account linking; editing unpaid bills; teller/customer chat using Laravel Reverb; billing/OR reports; VIP aging/history; read-only PPA payment verification; required customer registration full name/company/e-mail/mobile; PWA readiness; and Admin maintenance/important-information announcements.

Approval source: the user's instruction, "i like your lifecycle improvements lets implement it in our design", on 2026-09-19. The following choices supersede their earlier recommendation status; future contributors should implement this baseline without reopening settled choices merely because older session notes call them proposals.

| Decision | Approved design | Delivery tasks |
| --- | --- | --- |
| W20 | Separate withholding eligibility/certificate review from tax-specific exemption review; withholding is not a discount. Apply applicable exemption before invoice issuance; preserve history and reconcile late evidence. | P0-06, P2-08, P2-06, P3-01/P3-02 |
| W21 | Admin-defined, versioned billing requirements; private versioned files; automatic admission after complete safe submission. | P1-07, P2-07 |
| W22 | Separate billing, payment and tax worklists. Claim oldest eligible request; corrections retain original ticket/priority across days, without interrupting active work. | P2-07, P2-08, P3-05 |
| W23 | Regular-customer checkout selects full remaining balances. Compare their combined gross balance before withholding, excluding provider fees, strictly below the configured threshold; otherwise use manual payment. | P3-01, P3-05/P3-06, P3-10 |
| W24 | Manual deadline starts when bank instructions are issued. Separate submission, review and clearance clocks; retain late funds for reconciliation; check funds must clear before final settlement/receipt application. | P3-01/P3-02, P3-10 |
| W25 | Walk-in invoice without portal login; later number entry starts a verified-contact/one-time-code or teller-reviewed claim. Number alone grants no access and buyer snapshots never change. | P1-06, P2-09 |
| W26 | Drafts are directly editable; issued unpaid invoices use an explicit, audited Correct bill action with linked correction and preserved original number/PDF/history. Coordinate in-flight payments. | P2-01/P2-04, P3-03 |
| W27 | App-wide Reverb updates where useful, with durable API state, private authorization, after-commit delivery, reconnect recovery and separate silent refresh versus visible notifications/chat. | P1-08 and each event-producing workflow |
| W28 | Central Admin Document Studio designs sales-invoice/bill and collection-receipt/OR layouts through controlled drafts, validation, publication, activation and immutable artifacts. | P2-03/P2-04, P3-02, P4-04 |
| W29 | Tariff versions declare approved tax/PPA/fuel applicability; scheduled fuel-price bands create transparent snapshot surcharge components. | P0-03/P0-06, P2-10 |
| W30 | Eligible VIP credit debt can receive separate, policy-captured aging-based late charges; never rewrite the source invoice or use payment-instruction expiry alone. | P0-06, P3-11 |
| W31 | Eligible Customer/VIP transaction results may create a SkySMS post-commit delivery intent using verified contacts, preferences and versioned Admin templates; provider status never changes a financial state. | P1-09, P2-11, P3-12 |
| W32 | Registration requires full name, company / registered buyer name, email and mobile. Portal user, business account, verified contact and versioned buyer profile are separate; OTP is a dedicated security flow and issued documents retain immutable buyer/payer snapshots. | P1-10, P2-01/P2-06/P2-09, P3-02 |
| W33 | PWA readiness provides an installable shell and safe update/offline UI; it does not permit offline billing/payment/document work or cached protected data. | P1-11 and all online financial workflows |
| W34 | Admin can publish durable, audited, organization-scoped in-app maintenance/important-information announcements. Reverb wakes an API refresh; it does not create an SMS/push or financial event. | P1-12 |

Still required: accountant-reviewed tax evidence, rates/bases, fiscal correction documents and late-certificate accounting; gateway provider and bank-source matching; exact claim identity evidence and delegated/account-wide authority; Document Studio renderer/physical proof; tariff/fuel price sources, calculation ordering and rounding; VIP late-charge contract/fiscal treatment; SkySMS sender/status/idempotency/cost, provider-privacy and contact/preference decisions; registration email/OTP provider and fiscal-buyer matrix; PWA cache/update policy; and announcement audience/retention/critical-message policy. Initial thresholds, hours, review targets, credit terms, fuel/late-charge values and requirement types belong to Admin setup. The example PHP 10,000 is neither a default nor a statutory limit. Regular-customer partial checkout is deferred; the shared settlement engine must still represent partial actual collections and the separately planned VIP repayment flow. No additional daily limit, loss of queue priority, unrelated numbering-policy change or production activation is approved by this decision.

Use distinct lifecycles for requests, evidence, fiscal invoices, payment instructions, settlement, receipts and communication. A single status column cannot represent all of them.

## 2. Customer journey

1. Register/sign in and access a verified customer business account. Registration collects required full name, company / registered buyer name, email and mobile; the mobile OTP and email verification are separate from W31 SMS, and an active portal link is not created merely because data matches an existing business. A business account may exist before its portal users. See [customer registration](CUSTOMER_REGISTRATION.md).
2. Create a billing request, select a service, upload its configured requirements, and submit. Show missing/scanning files while incomplete. Once complete and safe, the server admits it automatically to the teller queue, assigns stable transaction/queue references, creates an in-app notification and, when W31 policy/contact conditions pass, an after-commit SMS intent.
3. Teller takes the oldest eligible request. Correct documents lead to billing; deficient documents are returned with specific reasons. Customer replaces the affected files and resubmits the same request with its original queue priority.
4. Teller prepares the bill using versioned tariffs, fuel surcharge and applicable approved tax treatment. Issue the applicable invoice and notify the customer when the bill and its artifact are available. W31 SMS is eligible only after that artifact is available; a PDF retry cannot issue another invoice or another customer event.
5. Customer sees invoice details, status, current balance, receipt history and permitted payment methods. Select one or more bills belonging to the same account and currency; VIPs can alternatively charge eligible bills to credit under their current account policy.
6. Server creates a payment group with explicit invoice allocations and determines the allowed route. Eligible gateway payments settle only after verified provider confirmation. Manual bank transfers/deposits require proof and an authorized teller's confirmation; deposited checks remain pending until funds clear.
7. The shared receipt action posts the verified settlement once, selects the published `COLLECTION_RECEIPT` Studio template, generates the collection receipt/OR artifact, updates balances and history, and sends a notification. W31 may send a customer/VIP SMS only for the committed, accurate settlement/receipt outcome; provider delivery does not prove financial delivery. Rejected evidence shows a reason and permits correction; rejection is not a reversal of money already received.
8. PPA reads current verified settlement, remaining due and receipt references. VIP credit remains separately identified as outstanding debt.

Transaction reference, queue ticket, invoice number, payment-group reference and receipt number are separate identifiers. A queue ticket must never consume a fiscal invoice/OR number. Display transaction and ticket references on the request, together with current status and people ahead when calculable; rank is an estimate, not a promised start time.

## 3. Billing requirements and file versions

Admin Settings > Billing Requirements manages named document types and versioned requirement sets by service and location where needed. Each type has a stable key, editable display name/help, required/optional rule, accepted formats, file count/size limits, effective dates, and relevant metadata fields. Administrators select from supported validators; naming a type "Tax Exemption" does not grant a tax benefit.

Separate four purposes: billing supporting documents, withholding certificates, exemption evidence, and bank-payment proof. They can share private storage, scanning and download controls, but have different reviewers, validation rules and financial effects.

Approved admission rule: a submitted request automatically queues once all required files are uploaded and pass technical checks. Saving an incomplete draft or uploading one file does not consume a queue place. Record submission time and admission time separately; prioritize admitted requests by original submission order. Scanning delays must not change that original order once admitted.

Snapshot the requirement-set version at initial submission. A later admin edit does not silently invalidate customers already waiting. Urgent legal changes use an explicit migration/review decision with a customer explanation.

File replacements create immutable versions, retaining prior files, reviewer decisions and notes. Bind each decision to the exact reviewed version; replacing an approved file triggers review of the replacement. Accepted unrelated files need not be uploaded again. A substantive change of service/account creates a new request or an explicit audited reassessment; it must not silently carry an old approval or priority.

Files remain private with scoped downloads, real content/type/size checks, quarantine/scanning and safe serving. Customer-facing correction notes are separate from internal staff notes. No permanent public file URLs or private documents in websocket payloads. Retention follows the applicable evidence policy and holds, not a customer's UI deletion alone.

## 4. Queue fairness and teller ownership

Use a billing-request queue separate from payment-verification and tax-review queues. A payment proof must not wait behind new billing encodings; each worklist has its own authorized staff and oldest-eligible ordering.

| Billing request state | Entry/action | Queue consequence |
| --- | --- | --- |
| DRAFT | Customer or teller prepares requirements | No service position yet |
| SUBMITTED_VALIDATING | Completeness and file safety checks | Original submitted time recorded; not yet claimable |
| QUEUED | Requirements technically accepted | Stable transaction/ticket assigned once; eligible for teller |
| IN_REVIEW | Teller atomically claims next eligible request | One active owner; other tellers cannot claim it |
| NEEDS_CORRECTION | Teller records field/file-specific customer note | Temporarily ineligible; original priority retained |
| QUEUED (resubmitted) | Customer sends a new version; checks pass | Return using original priority, not resubmission time |
| BILLING_IN_PROGRESS | Teller accepts requirements and prepares draft | Assignment retained; queue processing is not repeated |
| BILL_READY | Linked issued bill(s) ready for customer | Billing queue work complete; payment lifecycle is separate |
| CANCELLED / CLOSED | Explicit withdrawal/terminal decision with reason | Remove eligibility; preserve history and references |

Order candidates by original submitted time plus a server-assigned monotonic tie-breaker within the published organization/location service queue. Across-day carryover retains original order; a day-specific display number alone cannot implement fairness. Do not grant VIP queue priority merely because the customer has credit eligibility.

Example: tickets 41, 42 and 43 arrive in order. Ticket 41 needs correction. Teller serves 42 while 41 is waiting on the customer. If 41 returns while 42 is being processed, finish 42, then take 41 before still-waiting 43. Do not interrupt an active customer or leave a teller idle while an incomplete request blocks the head.

"Reject documents" normally maps to NEEDS_CORRECTION. Terminal rejection/cancellation is separate, reasoned and visible; resubmission of an ordinary correction is never treated as a new ticket. Notifications show exactly what to fix.

Atomic claim-next uses database locking, a single active assignment and an idempotent command. Tellers see the eligible list but cannot silently cherry-pick later requests. Authorized overrides/transfers record a reason and audit. A recoverable assignment lease/heartbeat permits reassignment after staff disconnects; expire ownership, not the customer's priority.

Track total elapsed time, eligible waiting time, time awaiting customer correction, handling time and correction rounds separately. Admin may configure reminders and escalation, but an inactivity rule that forfeits priority needs explicit business approval; no automatic priority reset is approved here.

## 5. Tax verification: two independent workflows

An admin's approval records review of evidence against applicable rules; it cannot create a legal exemption. This customer evidence workflow is in scope and is distinct from the excluded BIR registration/approval tracker.

### Withholding

Use "Withholding tax" or "Creditable withholding tax" in the UI and receipt breakdown, separately from commercial discounts. BIR Form 2307 identifies payor, payee, period, ATC, income payments and withheld amounts. The design therefore tracks certificates and their allocations rather than giving the customer an unlimited discount flag. [BIR Form 2307](https://bir-cdn.bir.gov.ph/local/pdf/2307%20Jan%202018%20ENCS%20v3.pdf)

Distinguish verified withholding-agent/profile eligibility from approval of a particular certificate and its supported amount. Whether the business requires agent evidence, Form 2307, or both is an accountant-reviewed document policy. Do not demand a new certificate before every billing request if the relevant evidence is issued later in the collection cycle.

Evidence review lifecycle: DRAFT -> PENDING_REVIEW -> APPROVED, NEEDS_CORRECTION or REJECTED; approved eligibility can subsequently expire, be superseded or be revoked for future use. Reviewer, source files, validity/coverage period, tax type, ATC, applicable base/rate, currency, supported amount and decision reason are recorded. Tax-review permission is separate from permission to verify bank payments; default exemption approver is Administrator, withholding approval can be delegated explicitly.

The server computes withholding using the applicable approved rule and base, compares it with the certificate and allocates the supported amount to explicit invoices/settlements. No default percentage is approved. Lock the certificate balance and invoice balances so the same certified amount cannot be applied twice across simultaneous or partial payments. One certificate may support several invoices only within its approved party/period/type/amount coverage.

Illustrative settlement only: a valid invoice balance of PHP 11,200, an approved applicable withholding amount of PHP 200 and confirmed bank/gateway money of PHP 11,000 settle PHP 11,200. The collection receipt shows PHP 11,000 money received and PHP 200 withholding separately; it does not claim PHP 11,200 arrived at the bank or reduce invoice VAT by PHP 200. This illustration specifies no tax rate or entitlement.

Pending/rejected evidence cannot reduce payable cash or mark a bill paid automatically. If money arrives before certificate approval, retain the confirmed funds and pending tax review; reconcile the residual under an approved accounting policy rather than lose the money, invent a discount or reuse a receipt number. Later evidence attaches through an audited reconciliation/adjustment; an issued receipt is not silently edited. Certificate receipt does not itself prove remittance to BIR.

### Exemption and zero-rating

Capture which tax is affected: VAT exemption, VAT zero-rating, or exemption from a particular withholding rule are different treatments. Required evidence, legal basis, effective dates, customer/branch identity and covered services/transactions must be reviewed. A customer description, VIP status, PPA label or non-VAT customer registration is not by itself a blanket exemption for every sale.

Apply applicable approved treatment in the invoice's line/tax calculation before issuance and capture the rule/evidence versions. Receipt output reflects the issued invoice and actual settlement. Keep taxable, VAT-exempt and zero-rated components distinct, including mixed invoices. RR 7-2024 section 3 requires the applicable invoice labels and breakdowns; it supports this invoice-first design, not an OR-only exemption switch. [RR 7-2024](https://bir-cdn.bir.gov.ph/BIR/pdf/RR%20No.%207-%202024.pdf)

Pending evidence can hold the affected draft for review, with escalation if statutory issuance timing is approaching. Do not defer required invoice issuance indefinitely. Approval received after issuance triggers review of an authorized linked correction if applicable; it never retroactively rewrites posted tax or older PDFs. Expiration/revocation blocks new use and flags impacted cases for review while retaining prior evidence.

## 6. Editing bills and claiming walk-in invoices

Approved W26 behavior: editable drafts plus an explicit "Correct bill" workflow for already-issued documents. Unpaid is a settlement status and does not establish that an invoice is still a draft.

| Situation | Approved workflow boundary |
| --- | --- |
| Draft/unissued bill | Teller edits with expected version; recalculate tax/tariff/fuel values and show change history |
| Issued unpaid invoice, no active payment | Authorized correction produces linked cancellation/replacement or adjustment under reviewed accounting rules; retain original number, PDF, reason and history |
| Active checkout, pending proof, check or allocated withholding | Hold new payment starts during correction; reconcile/cancel the specific in-flight intent where possible; bind every attempt to original versions and allocations |
| Partially/fully paid or VIP credit-assigned bill | Review dependent allocations and captured credit terms; use controlled linked correction/reversal, never ordinary overwrite |

W26 approves preservation and linked correction under W07/BIR-06; accounting must still define the applicable cancellation/replacement or adjustment document and its tax effects before accepting that implementation. This approval does not resolve every numbering, receipt-reversal or migration policy. An old gateway callback after correction must be retained as received funds needing reconciliation if it cannot be applied safely; never silently move it to the replacement invoice. Re-notify customers with an explicit version/change notice.

A teller can create a customer business record and invoice without a portal login, using the buyer information required for that invoice. Record the teller/source; do not create a fake user or generic customer for every walk-in.

After registration, entering a billing number starts a claim/link request. It does not immediately reveal a different customer's details. The approved verification routes are a **claim-purpose** one-time code supplied through an already verified eligible contact or teller-reviewed identity/account authority; a registration/mobile-change OTP cannot be reused for the claim. Define accepted identity evidence and delivery-channel verification during P2-09; until broader authority is verified, access is limited to the claimed invoice. Rate-limit attempts, keep unknown/unauthorized responses generic, and audit approval. Link the login to the existing account or expressly permitted invoice access; do not change the issued buyer identity. An invoice claim must not accidentally grant access to all company bills. Paid historical bills may also be linked for authorized history access.

## 7. Payment selection, routing and deadlines

Server-created payment groups contain invoice IDs, expected versions, requested per-invoice settlement, approved withholding allocations, requested cash, currency, payment-policy version and stable source identity. Group only one customer account/currency. Sum values server-side and show per-bill figures and one total before confirmation. Initial regular-customer checkout selects each bill's full remaining balance, including supported withholding as a separate settlement component; customer-entered partial checkout is deferred pending a separate policy. This does not discard actual partial bank collections, remove staff settlement controls or silently narrow the separately planned VIP repayment contract.

Admin Settings > Payments supports gateway enablement, amount threshold/currency, permitted manual methods/receiving accounts, instruction deadline hours, reminders, proof resubmission window and separate verification/clearing service targets. Threshold and deadline values are setup choices. Display the approved basis and strict less-than comparison; v1 does not expose arbitrary basis/comparator changes. Do not use an unrestricted formula editor.

Approved threshold basis: the combined current gross remaining balances of the selected bills, before applying withholding for this payment group and excluding provider fees. Already-posted settlements have already reduced those balances; do not count them again. This gives the threshold an invoice-balance basis independent of new settlement deductions. A net-cash or per-invoice threshold would be a future policy change, not an equivalent implementation of W23.

With a sample threshold of PHP 10,000 and strict "less than": PHP 9,999.99 is gateway-eligible when enabled, PHP 10,000.00 and above use manual bank payment. Two selected PHP 6,000 bills form a PHP 12,000 group. Gateway disabled sends all selections to the permitted manual path. VIP bank repayment remains available even when a group is gateway-eligible.

A threshold per group does not prevent paying separately selected smaller bills in separate groups. If the owner intends a per-customer/day limit or a mandatory manual route for each large original invoice, define that additional policy explicitly. Do not silently invent it. Partial-payment support must not let a customer bypass a full-invoice rule by splitting a large balance.

Freeze instruction policy/amount/deadline at creation. Validate current policy for new attempts; do not discard already-confirmed payments or shorten existing instructions when an admin changes settings.

Approved manual deadline: `expires_at = instructions_issued_at + configured_hours`, measured by server UTC and displayed in Asia/Manila. It starts when the customer confirms selected bills and receives bank instructions, not at first login or request upload. Retrying/reopening the same instruction does not reset its start or deadline. Distinguish instruction expiry, invoice due date, VIP credit due date and teller review SLA.

| Manual collection state | Financial effect |
| --- | --- |
| AWAITING_PAYMENT | Instructions/deadline issued; balance unchanged |
| PROOF_SUBMITTED / UNDER_REVIEW | Evidence queued with original payment-group reference; no paid status |
| NEEDS_CORRECTION | Reason shown; replacement proof version allowed without erasing received-funds history |
| AWAITING_CLEARANCE | Valid check deposit verified but funds not yet cleared; no final receipt application or paid status |
| CONFIRMED | Authoritative bank/provider confirmation persisted; eligible for receipt action |
| APPLIED | Receipt, cash/non-cash allocations and decision committed once |
| RECONCILIATION_REQUIRED | Actual money exists but duplicates, version changes or mismatches prevent safe application |
| EXPIRED / CANCELLED | New use of instructions closed; invoice debt remains, confirmed funds still reconciled |

On-time proof submission stops the customer's instruction timer from becoming a rejection merely because teller review is slow; it does not prove payment on time. Teller verifies actual bank transaction date/funds. Give corrections a separately configured resubmission deadline with audit; do not rewrite the original deadline. Late proof/funds are accepted into review with a late flag, not discarded. Expiry cannot cancel the fiscal invoice, erase debt, reset VIP aging, trigger a W30 late charge by itself or imply that an in-flight transfer failed.

For deposited checks, distinguish deposit proof, funds pending clearance, cleared and dishonored. Approved baseline: final settled status/PPA paid verification and receipt application follow cleared funds. Any required acknowledgment at deposit is explicitly provisional and its form/accounting treatment still needs review; recognizing collection earlier would require an explicit future policy change and separate check/reversal rules. A teller clicking "document valid" must not automatically mean "funds cleared".

Bank proof approval and receipt posting use one transaction after all prerequisites pass. Every channel uses shared source identity, locks, certificate-capacity checks and idempotency. A posted collection receipt can exist while its PDF is rendering; retry the artifact job without issuing another OR. Provider success redirects, uploads, chat messages and screenshots never independently settle debt.

## 8. Shared realtime updates, chat and notifications

User clarification, 2026-09-19: Laravel Reverb is the app-wide choice wherever instant data updates are needed. Its scope includes live queue positions and teller worklists, request assignment/correction, bill/OR readiness, tax/payment verification status, confirmed bill balances, VIP account summaries, and relevant administrative changes. Add events with each owning module; ordinary reference data can continue using normal API requests where live updates offer no benefit.

Distinguish silent data-refresh events from customer-facing notifications and chat messages. A queue count or table-row update need not create a bell alert. For financial/account views, events identify the changed record/version and trigger an authorized API refresh; they do not calculate balances in the browser. Coalesce repeated refreshes, ignore stale versions, and preserve unsaved form edits with a conflict indication rather than overwriting the editor.

Use Laravel Reverb with Echo for private customer/teller notifications and conversations. Persist messages and notification records in PostgreSQL before publishing an after-commit outbox event. Reverb transports updates; reconnect fetches durable state and unread counts over the API. Private channel authorization and after-commit broadcasting are supported by Laravel's documented broadcasting model. [Laravel broadcasting](https://laravel.com/framework/docs/broadcasting), [Reverb](https://laravel.com/docs/13.x/reverb)

Tie conversations to the account and billing request/payment group with explicit participants; staff handoff transfers access under scope/permission checks. Customer messages, staff-only notes and decision reasons have distinct visibility. Chat cannot replace a structured tax/payment approval or change balances. Chat attachments use the same private file checks; attaching a certificate in chat does not approve it.

Notify queue admission, correction requested, resumed queue, teller assignment where useful, bill ready, payment deadline/reminder, proof decision, tax decision/expiry and receipt ready. Store event ID, recipient and read time to deduplicate delivery. Avoid broadcasting every queue movement to every customer or exposing other customers' identities. W31 selects SkySMS for a controlled subset of Customer/VIP transactional events; its event matrix, message content, preferences, provider status and no-financial-effect boundary are authoritative in [SMS notifications](SMS_NOTIFICATIONS.md). Email remains a separate provider/configuration decision. W34 announcements are a distinct durable in-app global notice stream: Reverb signals an authorized refetch after commit and never invokes SMS/push. W33 PWA reconnect follows the same API recovery path and does not cache customer notifications as current state.

On logout, suspension or permission removal, stop live access and reauthorize/reconnect channels; already-subscribed sockets must not retain private document access. Keep broadcast payloads to scoped identifiers/versions and harmless display hints. UI supports polling/manual refresh when realtime service is down. Typing/read indicators are optional and not evidence of acceptance.

## 9. Workspaces, permissions and reporting

| Workspace | Required additions |
| --- | --- |
| Customer | My requests and queue progress, requirements/corrections, tax-evidence verification, bill details/multi-select checkout, bank instructions/proof review, invoices/OR history, chat/unread notifications and permitted SMS preferences |
| Teller | Claim-next billing worklist; separate payment-verification worklist; document reasons/version comparison; billing/editor/correction action; bank/clearance checks; unpaid/partial/paid balances and linked ORs; invoice/collection reports; customer conversation |
| Administrator | Users/permission bundles; Customer Accounts & Buyer Profiles exception review; Document Studio; requirement sets; tax-review policies and exemption approvals; versioned tariffs/fuel-price bands/schedules; payment thresholds/deadlines/receiving accounts; queue oversight; credit/aging/late-charge policy; Transactional SMS templates/policies/provider health/delivery reconciliation; Communications > Announcements; PWA deployment/update diagnostics; and invoice/OR reports |
| VIP customer | Same portal plus credit eligibility/remaining capacity, selected-bill charging, original terms/due dates, principal/late-charge account and aging totals, payment/OR history, applicable charge notices and permitted SMS preferences |
| PPA | Minimal scoped bill/payment verification and own checks; no documents/tax proofs, chat, general reports, payment approval or billing mutation |

Candidate permissions: `billing_requests.submit/view/claim/review/return/override`, `billing_requirements.manage`, `tax_evidence.submit/view/review`, `tax_exemptions.approve`, `withholding.approve/apply`, `customer_registration.review`, `buyer_profiles.view/edit/review`, `bill_claims.review`, `payment_policies.manage`, `bank_payments.verify`, `checks.confirm_clearance`, `templates.draft.edit/preview/publish/activate`, `tariffs.manage/publish`, `fuel_surcharge_schedules.manage/publish`, `credit_late_charge_policies.manage/publish`, `credit_late_charges.preview/post/waive/reverse`, `conversations.view/send`, `notification_templates.draft.edit/preview/publish/activate`, `notification_policies.manage`, `sms_provider.configure/enable`, `sms_deliveries.view/reconcile/resend`, `announcements.draft.edit/publish/retire/history.view/audience.manage` and `portal.notification_preferences.manage`. Reuse existing billing/receipt/report/credit permissions. Every action checks account ownership or staff scope and current membership; do not grant all tax decisions, provider authority or message-content access simply because a user is Teller.

Tariffs, fuel surcharge, tax/late-charge and other policy versions apply at documented calculation/issuance boundaries. Existing issued amounts and historical reports do not change when admin settings change. A material stale price preview requires reconfirmation. Templates only render captured values; a Studio change does not recalculate an invoice or receipt.

Operational reporting distinguishes request backlog/aging, customer-correction wait, teller handling, payment verification/clearance delay and financial receivable aging. Financial reports show invoices, tariff/PPA/fuel components, cash collected, withholding supported/applied, outstanding amounts and linked receipts separately; tax exemption is a classified sale, not a collection discount. VIP reporting separates original-principal aging, posted late charges, waivers/reversals and total due. Export permission and scope match on-screen access.

PPA must query current server balances when verifying and display checked-at time. An old screenshot/QR or earlier check cannot override a later reversal. W18's optional on-credit eligibility remains a separately labeled policy result; it never counts as verified paid or authorizes cargo release.

## 10. Data and acceptance contract

Proposed additions, not migrations:

| Records | Required relationship/control |
| --- | --- |
| document_types, requirement_set_versions, requirement_entries | Stable keys; published version immutable; real document-type FKs |
| private_files, billing_requests, request_document_versions, request_reviews | Customer/service/requirement-set scope; explicit FKs to each file/version; retain decisions |
| service_queues, queue_tickets, teller_assignments, queue_events | One ticket per request; unique queue identity/tie-breaker; one active assignment; original priority protected |
| customer_tax_evidence, tax_evidence_versions, tax_review_decisions, customer_tax_treatments | Exact reviewed evidence, validity/covered services/tax type/rule; customer scope; no boolean-only exemption |
| withholding_certificates, withholding_applications | Typed certificate/receipt/invoice FKs, capacity/period validation under locks, reversal history |
| report_templates, template_versions, template_activations, template_assets, template_validation_results | Controlled Studio drafts/publish/activation, versioned schemas and protected fiscal-field validation; no layout-side financial authority |
| tariffs, tariff_versions, fuel_price_observations, fuel_surcharge_policy_versions, fuel_surcharge_bands, invoice_item_pricing_snapshots, invoice_charge_components | Scoped effective rules, no overlap/ambiguous bands, source/basis/rate/amount snapshots and transparent charge display |
| late_charge_policy_versions, late_charge_bands, late_charge_assessments, late_charge_events, late_charge_payment_allocations, late_charge_waivers | Captured policy/cycle/source FKs, non-compounding baseline, immutable assessment/waiver/reversal and separate principal/charge allocation |
| customers, customer_identity_versions, customer_user_links, customer_contact_points, contact_verification_challenges, contact_verification_events | Separate business account/user/contact/buyer identities; explicit ownership, profile/contact versions and purpose-bound single-use OTP/claim records; no plaintext OTP or matching-data auto-link |
| payment_policy_versions, payment_groups, payment_group_items | Explicit selected invoices/versions, decimal allocations, policy/deadline snapshot |
| bank_payment_confirmations, check_clearance_events | Verified source identity distinct from uploaded proof; retained received/unapplied funds |
| bill_claim_requests | User/account/invoice verification; single-use claim identity; no buyer rewrite |
| conversations, conversation_participants, messages, notifications | Scoped participants, durable sequence/unread state, explicit request/payment links and private-file associations |
| customer_contact_points, contact_verification_events, notification_preference_versions, notification_templates, notification_template_versions, notification_policy_versions, notification_events, notification_deliveries, sms_delivery_attempts, sms_provider_status_observations | W31 verified contact/purpose/preference/template/delivery records; local effect deduplication, redacted provider observations and no financial-state authority |
| announcements, announcement_versions, announcement_audience_roles, announcement_audience_locations, announcement_user_states | W34 immutable published notice version, scoped audience and per-user UI state; after-commit/API recovery only, no SMS/push/financial effect |

Use typed FK-backed file/context associations rather than unconstrained generic type/id ownership. Scope-composite references reject cross-account/organization links. Preserve financial/evidence history with protected deletion; queue counters, invoice series and OR series remain independent. Paginate worklists and add reviewed scope/state/order and account/due-date indexes.

Use the existing global source -> credit-account -> invoice lock order for settlement. Add certificate locks after invoice locks in stable ID order for every withholding-consuming/reversing path; approval/revocation must serialize on those certificate rows. Final lock ordering across additional aggregates is reviewed in P1-03 before code to prevent deadlocks.

Required future acceptance examples:

- Two tellers claim next; one customer gets one owner/ticket despite retry. Correction returns ticket 41 ahead of unstarted 43 without interrupting 42; cross-day priority and assignment recovery preserve ordering.
- Partial uploads, quarantine and new requirement versions do not falsely complete admission. Replacing approved evidence invalidates only the affected decision; prior evidence remains.
- Wrong taxpayer, wrong period/tax type, expired/revoked exemption, mixed-tax invoice and late approval; receipt and invoice treatment stay consistent. Concurrent/partial uses cannot exceed one certificate's amount.
- PHP 9,999.99 / 10,000.00 / 10,000.01 with a configured PHP 10,000 example threshold: only the first qualifies; two selected PHP 6,000 balances route manually. A PHP 10,100 balance with PHP 200 supported withholding remains manual despite PHP 9,900 cash due. Fees do not alter the threshold basis. Regular-customer partial checkout is rejected; actual partial funds still require reconciliation. Gateway-disabled and policy-change cases preserve existing attempts.
- Proof arrives before timeout but teller reviews later; late bank confirmation after expiry; scheduler retry; correction timer; check deposited/cleared/dishonored; invoice due/credit aging remain independent.
- Concurrent manual/proof/gateway payments, duplicate references/certificates, stale bill revisions, a late callback for a replaced invoice and failed PDF job create no duplicate receipt or lost money.
- Document Studio draft/publish/activation and protected-field rejection, tariff/fuel price-band/effectivity boundaries, and late-charge scheduler retry/waiver/reversal preserve immutable source artifacts and separate monetary components.
- Unpaid-issued correction preserves original artifact; dependent partial settlement/credit assignment is reconciled. Unverified walk-in claim and guessed billing number disclose no bill; verified claim preserves buyer/history.
- Required full name/company/e-mail/mobile registration fields, normalized shared-contact/collision handling, expired/replayed/wrong-purpose OTP and contact change remain generic and cannot confer company authority. Buyer/profile edit after issuance leaves invoice/receipt/OR/PDF snapshot unchanged; imports create no verified contact, OTP or message.
- Reverb disconnect/reconnect/duplicate event and session revocation; unauthorized subscription/download/export fails; unread messages remain retrievable; chat cannot approve payment.
- W31 suppression for unverified/stale/opted-out contacts; safe-template lint/preview/version activation; after-commit local intent; no provider call before commit; rate limit/backoff; provider pending/sent/failed/unknown reconciliation; no blind timeout retry; scoped delivery dashboard and no SMS-driven financial state change.
- Installed/offline PWA shell cannot cache protected API/artifact/OTP data or issue a financial command. Announcement publication/schedule/expiry/audience/revision and per-user state are scope-safe; a Reverb signal/API refresh neither leaks recipients nor publishes SMS/web push.
- Customer/teller/PPA balances agree; VIP principal aging/late-charge history includes partial collections, waivers and reversals; tax approval does not itself mark paid and on-credit never displays as settled.

These are planned scenarios, not tests executed in this planning revision. See [PHASES](../PHASES.md) for delivery tasks and [DELIVERY](../DELIVERY.md) for acceptance ownership.
