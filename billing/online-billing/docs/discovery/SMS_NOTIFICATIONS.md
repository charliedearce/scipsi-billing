# Transactional SMS notifications (W31)

Design approved: 2026-09-19. W31 adds SkySMS as the planned outbound SMS provider for eligible Customer and VIP-client transactional notifications. It extends W27's durable in-app notifications and Reverb updates; it does not implement an SMS campaign, payment gateway, authentication mechanism, fiscal document, or financial authority. This is a planning contract, not a configured provider account, production send, delivery guarantee, or privacy/legal acceptance.

## 1. Product boundary

SMS is a supplementary communication channel. The portal, authoritative API records, issued document artifacts and structured review/settlement states remain the source of truth. A notification delivery result must never post/reverse an invoice or receipt, approve/reject evidence, change a bill balance, grant PPA clearance, charge/waive VIP debt, or prove that a customer received/read/accepted an official receipt.

W31 permits only event-driven messages addressed to a verified mobile contact belonging to the affected customer account or VIP client. It excludes ad-hoc broadcasts, marketing, raw recipient-list upload, general staff alerts, automated chat replies, two-way SMS, and OTP/account-authentication use. W32 now owns the separate registration/contact-verification/OTP security contract; it does not turn the W31 template module into an authenticator.

Each template is classified as one of:

| Class | Intended use | Preference rule |
| --- | --- | --- |
| `CONTRACTUAL_TRANSACTIONAL` | Material request, document, settlement, credit or correction result | Requires a documented purpose/basis, verified contact and alternate-channel policy; it is not a hidden override of customer preferences |
| `OPERATIONAL_REMINDER` | Configured payment/deadline or VIP due-date reminder | Suppress when the customer has opted out, the item is already settled/reconciled, or the policy/quiet-time rule says not to send |
| `MARKETING` | Promotion, upsell, campaign or survey | Explicitly out of W31; requires a separately evidenced consent/opt-out design |

The Philippine privacy review is a launch gate, not a claim that a single consent rule applies to every transaction. The Data Privacy Act IRR identifies transparency, legitimate purpose, proportionality, retention, lawful processing, security, data-subject rights and outsourcing controls as relevant principles. [NPC Data Privacy Act IRR](https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/)

## 2. Eligible lifecycle events

The owning module emits an immutable, scoped notification intent only after its database command commits. The table below is the default policy; an Administrator may enable/disable an eligible event within its allowed class, but cannot use a template to create a new financial trigger or turn an internal event into a customer message.

| Event key | Audience | Default W31 policy | Content boundary |
| --- | --- | --- | --- |
| `BILLING_REQUEST_QUEUED` | Customer | Send | Safe transaction/queue reference only; never expose other customers' rank or data |
| `BILLING_REQUEST_CORRECTION_REQUIRED` / terminal cancellation | Customer | Send | Customer-visible action/reason summary only; files remain portal-private |
| `BILLING_REQUEST_REQUEUED` | Customer | Optional, default off | Status update only; original priority is not disclosed as a promise |
| Teller assignment, queue movement or worklist refresh | None | Never SMS | Reverb/internal UI only; no teller identity or queue internals |
| `INVOICE_ARTIFACT_READY` | Customer | Send | Only after the posted invoice and authorized artifact are available |
| `INVOICE_CORRECTED_OR_REPLACED` | Customer | Send | Tell the customer that a document changed; portal shows linked history/details |
| Tax/withholding evidence approved, rejected, correction-needed, expired or revoked | Customer | Send | Generic result/action only; never include tax, exemption, certificate or uploaded-file detail |
| Walk-in claim one-time code | Customer | Separate security scope | P2-09 may use SMS only after its own verification/rate-limit design; never disclose whether a bill number exists |
| `PAYMENT_INSTRUCTIONS_ISSUED` | Customer/VIP | Send | Safe reference and Manila-local deadline; bank instructions remain in the authenticated portal |
| Payment reminder or instruction expiry | Customer/VIP | Optional operational reminder | Suppress after settlement/reconciliation; expiry does not cancel debt or itself trigger a W30 late charge |
| Proof upload, gateway return URL or provider retry | None | Never SMS | Neither proves money nor settlement; in-app acknowledgement/operations monitoring only |
| Proof correction-needed/rejected or check dishonored | Customer/VIP | Send | Customer-action result; do not reveal bank/evidence details by SMS |
| Pending bank clearance | Customer/VIP | Optional | Never label the bill paid or receipt-ready |
| `SETTLEMENT_POSTED`, partial settlement or receipt/OR artifact ready | Customer/VIP | Send | State the confirmed result accurately; receipt availability remains distinct if rendering is delayed |
| `RECONCILIATION_REQUIRED` or receipt reversal | Customer/VIP | Send | Material exception with secure support/portal route, not raw financial detail |
| `VIP_CREDIT_ASSIGNED` | VIP client | Send | Material debt/terms notification; a credit assignment is not a payment receipt |
| VIP account hold/new-credit block | VIP client | Send when customer action is affected | Do not send each policy/risk recalculation |
| VIP due-date/aging reminder | VIP client | Optional operational reminder | Captured cadence, quiet-time, preference and contract wording apply |
| VIP repayment posted/partial/rejected | VIP client | Send | Same confirmed-settlement boundary as ordinary payments |
| Late-charge candidate, hold or worker retry | None | Never SMS | Internal/review-only; never pre-announce a charge |
| Late charge posted, waived, reversed or reconciliation-required | VIP client | Send after P3-11 fiscal gate | Only posted/reversal outcome; never before required fiscal/accounting mapping |
| Chat messages, staff notes, PPA checks, tariff/template edits, render retry or provider error | None | Never per-event SMS | Reverb/bell notifications, staff monitoring or an aggregated unread reminder only |

## 3. Template and recipient safeguards

Templates are a dedicated Customer Communications capability, separate from the Document Studio. The lifecycle is **draft -> preview with synthetic data -> validated publish -> scoped/effective activation -> retirement**. A published template is immutable; changing it creates a new version. Each notification stores the selected template version, safe variable snapshot, rendered-body hash and rendered message so a later edit cannot alter an already triggered message.

Administrators can customize approved template text, event enablement, schedule/cadence and normal/high priority within the published policy. They cannot add arbitrary PHP/JavaScript/SQL/HTML, raw recipient lists, formulas, external fetches, attachment contents, unrestricted variables or an arbitrary send-now button. A preview never sends a message. An intentional test send, if later permitted, targets only a designated verified test contact, records the expected provider charge and cannot target a customer by accident.

The initial safe variable catalog is limited to organization display name, recipient first/preferred name, a safe/masked reference, queue ticket, generic action label, Manila-local date/deadline/due date, and approved support contact text. Templates must not include full balance or bank details, tax/withholding/exemption facts, proof/file content, credentials, reusable codes, chat text, full document payloads or a payment/invoice link. The customer signs in to the portal to see protected details.

SkySMS documents a 1,000-character API message maximum, while its feature page says 160 characters; publish validation therefore warns at 160 and rejects above 1,000 until a provider sandbox confirms segment/credit behavior. URLs/links are blocked by the provider and can appear as sent while not delivered, so rendered-body validation rejects them. The current default priority is `normal`; high priority needs an explicitly permitted operational policy. [SkySMS API documentation](https://skysms.skyio.site/docs), [SkySMS feature page](https://skysms.skyio.site/features)

Send only to a normalized E.164 mobile contact with a captured verification method/time and an active customer-account/delegated authority link. W32 P1-10 creates registration/contact OTP verification; a provider queued/sent state is not verification, and mobile possession does not prove company authority. Contact changes create versions; stale, unverified, suppressed, opted-out or cross-account contacts never receive detailed messages. Record the selected notification purpose/basis, preference outcome and contact version. Do not infer a blanket consent rule from a historical phone number; the business/DPO must decide the lawful basis and alternate-channel treatment of each family before activation. Tax returns are specifically listed as sensitive personal information in the privacy rules, so tax-related messages stay generic. [NPC Data Privacy Act IRR](https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/)

## 4. SkySMS adapter and delivery-state contract

Use `POST /api/v1/sms/send` with an `X-API-Key` only from a Laravel worker after commit. The documented success response is a queue acknowledgement with `queue_id` and an initial `pending` status, not a handset receipt. The documented sync endpoint filters `pending`, `queued`, `sent` and `failed` records. The feature page mentions a per-message status endpoint, but its identifier/response contract is not in the API reference; webhook/callback signing is also not documented. Store the provider's exact raw status and map it conservatively.

```text
SUPPRESSED -> QUEUED_LOCAL -> DISPATCHING -> PROVIDER_PENDING/QUEUED/SENT/FAILED
                                      \-> UNKNOWN_RECONCILIATION_REQUIRED
```

`PROVIDER_SENT` is never displayed as **Delivered**. Reserve `PROVIDER_DELIVERED` only for a future provider contract that explicitly returns a correlated terminal delivery receipt. The Admin dashboard labels all external observations “provider-reported status”, shows the last synchronization time, and preserves an `UNKNOWN_RECONCILIATION_REQUIRED` result rather than guessing from message text, phone number or time.

Normal `send` does not document an idempotency key. On network timeout or ambiguous server failure, mark the local delivery unknown, poll/reconcile using the recorded provider identity where that mapping is proven, and do not blindly send again. A new resend is permitted only for a known provider-failed message or an authorized, audited staff action that creates a distinct follow-up attempt; it may consume another credit. SkySMS documents individual/bulk sends, but W31 uses individual sends so consent, template snapshots and status are traceable per recipient.

Throttle all workers per SkySMS account below its documented shared limits of 30 requests/minute and a 3/second burst. Respect `Retry-After` on `429` and apply bounded exponential backoff to known retryable work. No provider call occurs while a financial transaction holds invoice, receipt, proof or credit locks. Record response credit fields as operational observations only; provider credit/segment/failed-message rules have conflicting public wording and must be clarified before cost forecasting or automatic retry policy. [SkySMS API documentation](https://skysms.skyio.site/docs), [SkySMS terms](https://skysms.skyio.site/terms)

W31 does not enable SkySMS two-way conversations. An inbound SMS is not a portal chat, payment proof, tax approval, PPA verification or customer authorization. A future two-way feature needs an independently scoped conversation, privacy, sender/SIM, inbound-routing and human-review design.

## 5. Administration, permissions and records

Add **Admin > Customer Communications > Transactional SMS** with four scoped areas:

1. **Provider health**: enabled/disabled state, credential-reference health, account/environment reference, last status sync, rate-limit/error/credit observations and reconciliation alerts. API keys are write-only deployment-secret references and never appear in browser/API/audit values.
2. **Templates and event policies**: draft/preview/publish/activate versioned plain-text templates, approved variables, category, priority, scope/effectivity, customer-visible language and reminder cadence/quiet-time policy.
3. **Delivery dashboard**: recipient-safe reference, local/provider state, provider queue/message identity if returned, attempt count, status timestamps, policy/template/contact versions, suppression/failure/unknown reason and restricted rendered-message access. It never labels a provider status as payment or receipt proof.
4. **Contact and preference governance**: verified contact history, preference/purpose outcome, DPO/business policy references and support/review queue. Customers manage their own permitted preferences in the portal; staff cannot silently opt a customer into marketing.

Candidate named permissions are `notification_templates.view`, `notification_templates.draft.edit`, `notification_templates.preview`, `notification_templates.publish`, `notification_templates.activate`, `notification_policies.manage`, `sms_provider.view`, `sms_provider.configure`, `sms_provider.enable`, `sms_deliveries.view`, `sms_deliveries.reconcile`, `sms_deliveries.resend`, `sms_deliveries.export`, and `portal.notification_preferences.manage`. Grant provider configuration, production enablement and resend separately. Teller access is limited to delivery state for records in current scope; PPA has no message content/template/provider authority. Every secret, template, policy, enablement, suppression override, reconciliation and resend is scope-checked and audit-recorded with reason/correlation ID.

Proposed FK-backed records are `customer_contact_points`, `contact_verification_events`, `notification_preference_versions`, `notification_templates`, `notification_template_versions`, `notification_policy_versions`, `notification_events`, `notification_deliveries`, `sms_delivery_attempts` and `sms_provider_status_observations`. Keep source event/context links typed and scoped; do not use an unbounded generic `type`/`id` relation merely to avoid foreign keys. Use a unique local effect key across source event, recipient contact version, channel and policy/template version. Retain provider IDs/responses in redacted restricted fields; no API key, raw private evidence or broad message-content export enters ordinary audit logs.

Planned APIs include `GET/POST/PATCH /admin/sms/templates`, `POST /admin/sms/templates/{id}/versions/{version}/publish`, `GET/PATCH /admin/sms/policies`, `GET /admin/sms/deliveries`, `POST /admin/sms/deliveries/{id}/reconcile`, `POST /admin/sms/deliveries/{id}/resend`, and `GET/PATCH /portal/notification-preferences`. The final OpenAPI design must enforce scope, expected versions and the proven provider contract.

## 6. Activation gates and acceptance

P1-09 establishes generic verified-contact/preference, template/policy versions, local notification/delivery records, the adapter interface, a fake provider and authorization tests. P1-10 adds the W32 registration/buyer/contact/OTP flow; production W31 sends require a current verified active contact from that controlled path. P2-11 connects request/invoice/tax/claim events after their owning workflows are available. P3-12 connects payment instruction/proof/settlement/receipt/reconciliation, VIP and approved late-charge events and proves status synchronization. P4-03 adds scoped operational communications reporting; P6-02 requires carrier/provider and operations acceptance before production enablement.

P3-12 implementation note, 2026-09-20: available committed outcomes are connected so far: manual instruction issuance, manual proof rejection, posted manual settlement, dishonored deposited check; per-bill VIP credit assignment, posted VIP repayment, repayment proof rejection and an immediately effective held/disabled account; and a posted collection receipt whose canonical PDF successfully rendered. They create a local post-commit event and outbox intent, but do not call a provider. P3 payment, receipt and invoice-credit events now retain immutable FK-backed operational location scopes: a Teller may list only state for assigned scopes, while rendered customer content, hashes, provider identifiers and raw observations require `sms_deliveries:view_content`. Account-wide events with no authoritative location mapping are Administrator-only, never silently organization-wide. Proof upload/resubmission, teller claim and cleared-but-not-yet-settled check remain silent; a future-dated hold is not announced early. Failed receipt rendering remains silent and no artifact-retry behavior was added. Receipt reversal/reconciliation events and accountant-approved late-charge outcomes remain unconnected; P3-12 remains `IN_PROGRESS`.

Before any production send, obtain and record:

- provider-account owner, production/test environment, API-key rotation/revocation procedure, stable sender identity/carrier behavior, contact support and outage escalation;
- normal-send idempotency/client-reference support, exact message-list/status response and correlation fields, webhook availability/signature if offered, status semantics/timestamps and retention/deletion behavior;
- exact character/segment/credit rules, the conflicting failed-message credit wording, rate/throughput limits, provider data processing/subprocessor/location/retention terms and a DPO-approved processor/privacy-impact review;
- customer/VIP contact verification, purpose/basis/preference, opt-out and alternate-channel policies, including a business/DPO classification of reminders; commercial/promotional push messages have separate opt-in/quiet-time constraints in NTC guidance; and
- sandbox test evidence covering invalid/opted-out/stale contact suppression, template lint, 429/backoff, no provider call before commit, provider timeout/unknown outcome, known failure/reconcile/resend, stale/duplicate status, access denial, no financial state change, and carrier/operator message readability.

The NTC FAQ describes prior opt-in and 9 PM–7 AM restrictions for commercial/promotional broadcast/push messages. W31 therefore does not treat a billing reminder as automatically exempt or automatically marketing; the DPO/business owner must classify it before activation. [NTC FAQ](https://region7.ntc.gov.ph/faqs/)

SkySMS sources used for the planned adapter: [API documentation](https://skysms.skyio.site/docs), [features](https://skysms.skyio.site/features), and [terms](https://skysms.skyio.site/terms). They are provider documentation, not an independent production-service guarantee.
