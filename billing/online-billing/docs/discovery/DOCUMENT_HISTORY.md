# Document history and audit contract

Decision W35, recorded 2026-09-19 from the user's requirement: the online billing system must retain a searchable history whenever a bill, invoice, official receipt, payment-related document, template, setting or similar record is edited. This is a planning contract; no runtime implementation or database migration is implied.

## 1. Why this is separate from ordinary timestamps

`created_at`, `updated_at` and a current `status` are not an audit trail. They cannot show who changed a line, what the previous amount was, why a correction was approved, or which version was printed. The system therefore keeps:

1. **Document revisions** for editable drafts and versioned non-financial records.
2. **Business audit events** for commands, approvals, postings, corrections, reversals, reviews and access-sensitive actions.
3. **Issued snapshots and artifact history** for the exact values/layout/payload used for an invoice, bill, receipt/OR or PDF.
4. **Linked correction/reversal history** for records that are no longer allowed to be overwritten.

The history is append-only from the application's point of view. Administrators may view or export it under permission, but cannot edit or delete a committed event through the normal UI.

## 2. Records covered

At minimum, history applies to:

- invoice/bill headers, line items, charges, tax, withholding, PPA and fuel components;
- official receipts/collection receipts, tenders, allocations, reversals and payment-proof decisions;
- VIP credit assignments, aging/late-charge assessments, waivers and reversals;
- customer tax evidence, exemption/withholding decisions and uploaded file versions;
- customer billing requests, queue assignment/correction decisions and teller notes;
- Document Studio drafts, template versions, publication/activation/retirement and render attempts;
- tariffs, fuel schedules, payment/credit policies and other domain-owned versioned settings;
- user/permission changes and Admin announcements where the change affects access or user-visible state.

Failed or denied commands may be recorded as security/operational events, but must never appear as a successful document revision or financial posting.

## 3. Event fields

Every committed history event should carry the following fields or their reviewed equivalent:

| Field | Purpose |
| --- | --- |
| `id` | Internal immutable event identifier |
| `organization_id`, optional `location_id` | Scope and cross-organization protection |
| `aggregate_type`, `aggregate_id` | Logical event target for display/search; financial references also use typed `audit_event_links` FKs and must not rely on this pair alone |
| `aggregate_version` | Optimistic-concurrency version after the event |
| `event_type` | For example `DRAFT_EDITED`, `POSTED`, `CORRECTED`, `REVERSED`, `APPROVED`, `PRINTED` |
| `actor_type`, `actor_id` | User, system worker or migration actor; preserve the actor even after deactivation |
| `permission_snapshot` | Permission/scope used for the action, without secrets |
| `occurred_at` | Server UTC timestamp; display in Asia/Manila |
| `business_date`, `accounting_period_id` | Financial/document context where applicable |
| `reason` | Required for correction, rejection, override, reversal, waiver and sensitive configuration changes |
| `request_id`, `correlation_id`, `idempotency_key` | Trace retries and related commands without treating a retry as a new action |
| `changed_fields` | Allowlisted field names and line identifiers |
| `before_snapshot`, `after_snapshot` | Redacted JSONB values or references to immutable revision snapshots |
| `parent_event_id` / related record IDs | Link approvals, corrections, reversals, artifacts and source requests |
| `source` and security metadata | API/UI/worker/import plus carefully limited IP/user-agent metadata when approved |

Do not store passwords, OTPs, bearer tokens, provider secrets or unnecessary full payment credentials in history. Sensitive evidence remains in private storage with a file/version reference, hash and access event.

## 4. Edit and correction rules

### Draft bills and editable records

Saving a draft requires the expected current version. A successful save creates a new `document_revisions` row with a monotonic revision number, actor, reason where required, changed-field diff, immutable snapshot/hash and timestamp. A stale editor receives a conflict and cannot overwrite the newer revision. Autosave, if introduced, follows the same version rule and is clearly labeled as a draft edit.

### Issued invoices and bills

An issued financial document is never directly overwritten or deleted. W26's approved `Correct bill` action creates a linked correction/adjustment record after authorization and retains the original number, buyer/tax/pricing snapshots, PDF/artifact and full history. The correction chain records target, reason, approver, execution result and any accountant-defined fiscal adjustment document.

### Receipts, payments and allocations

Posted receipt, tender and allocation facts are immutable. A correction uses a linked reversal, replacement or reconciliation event; it does not edit the original amount or hide a prior allocation. Duplicate approval/retry must resolve to the existing command result and not create a second receipt.

### Templates, policies and settings

Template, tariff, fuel, credit, payment and application-setting changes create versioned records. Publish/activate/retire and effective-time changes are separately auditable. A later version affects future work only; it cannot rewrite an issued document or historical calculation.

### Files and review decisions

Replacing a customer file creates a new private file version. The old file, hash, reviewer decision, rejection reason and replacement link remain available according to retention policy. A corrected request returns to its queue position without erasing the prior review.

## 5. Transaction and storage rules

- The owning business action, new revision/snapshot, audit event, approval/execution link and durable outbox effect commit in one PostgreSQL transaction.
- Realtime notifications and SMS are after-commit effects; they wake an authorized history/API refresh and never become the audit authority.
- Use real foreign keys for document, actor, scope, revision, correction, approval and artifact links. Prefer separate typed revision/correction tables per aggregate (for example `invoice_revisions`/`invoice_correction_links` and `receipt_revisions`/`receipt_reversal_links`), or nullable typed FKs with a CHECK that exactly one owner is set. Do not use an unconstrained `aggregate_type`/`aggregate_id` pair for financial history. Protect issued history with `RESTRICT`; do not cascade-delete financial or audit records.
- Store queryable accounting facts in columns. Use JSONB for validated before/after metadata and render payloads, not as a replacement for invoice/receipt relationships.
- Keep audit timestamps in UTC, record the business date/period separately, and retain source/import provenance for historical events.
- Restrict direct database credentials and audit writers. If tamper-evidence is required, add an event hash/previous-hash chain and periodic signed export as an operations decision; a hash is not a substitute for authorization, retention or backup controls.

## 6. User experience and permissions

Authorized staff receive a History tab/timeline with filters for actor, date, event, status, document number and correlation ID. A revision comparison shows field and line-item changes, previous/current values, reason, reviewer and linked correction/reversal/artifact. Customers see only their own permitted document/status history and customer-facing reasons; staff-only notes, security metadata and other customers never appear.

Candidate permissions are `audit.view`, `audit.export`, `document_history.view`, `document_history.compare`, `corrections.request`, `corrections.approve`, `reversals.approve` and `history.retention.manage`. Viewing history does not grant permission to edit, approve, post, reverse or download private evidence.

## 7. Required acceptance examples

- Two tellers edit the same draft: one revision commits, the stale editor is rejected, and no changes disappear.
- A posted invoice correction preserves the original invoice/PDF/number and links the approved correction; a later template or customer-profile edit cannot change either snapshot.
- A receipt reversal and retry create one linked reversal event and restore the balance exactly once; the original receipt remains visible.
- A rejected tax file followed by replacement retains both file versions and reviewer reasons without exposing the private file to an unauthorized user.
- A tariff, fuel band, payment policy or template activation shows actor, reason, effective time and before/after version, while already-issued amounts remain unchanged.
- An administrator can view/export permitted history but cannot modify or delete an event; disabled users remain attributable.
- Failed transactions leave no false successful revision/posting, while separately recorded denied/security events cannot be mistaken for financial history.

The detailed schema and API names remain subject to the Phase 1 contract review, but every future document module must satisfy this boundary before its phase can be accepted.
