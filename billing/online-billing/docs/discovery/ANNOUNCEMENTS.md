# Admin in-app announcements (W34)

Design approved: 2026-09-19. Authorized Administrators need to announce maintenance and other important information to users of the application. This contract provides durable, organization-scoped **in-app** announcements. It is not a bulk SMS/e-mail tool, chat channel, payment alert, public website feed, financial command, or proof that any user has read a document or paid a bill.

## 1. Audience and display boundary

An announcement is visible through the authenticated application shell. The default audience is **all active authenticated users in the Administrator's organization** for the announcement's effective window, including users who sign in after publication. Logged-out/public visitors are not recipients; a public maintenance/status page would be a separate feature. Administrators may use a narrower approved role/location audience when the message genuinely applies only there; a customer-facing notice must never disclose staff-only operational/security information. Suspended/deactivated users do not receive new notices.

Every user receives the same approved content for an announcement version, subject to their authorized organization/location and audience rule. Multiple selected roles match with **OR**, multiple selected locations match with **OR**, and a notice with both filters requires a user to match at least one selected role **and** at least one selected location. Customer, VIP, PPA, Teller and Administrator users see notices in the global in-app notice area; role-specific text should be separate announcements with a narrowed audience, not conditionally hidden text in one broad message.

Announcements remain supplementary. The portal/API, issued invoice/receipt artifact and verified settlement records remain authoritative. Do not put individual account balances, payment-proof/tax evidence, OTPs, credentials, private files, security investigation details, customer lists, or financial instructions into an announcement. Do not use a notice to change payment status, close a queue, issue/cancel a fiscal document, bypass PPA/teller approval, or perform maintenance actions on behalf of a user.

## 2. Authoring lifecycle and content rules

Use the lifecycle **DRAFT -> SCHEDULED -> PUBLISHED -> EXPIRED or RETIRED**. A published version is immutable; a correction creates a linked replacement/version with a visible change reason. Expiry removes a notice from normal active display but preserves the audited history. Retiring a serious mistake requires an authorized reason and immediately stops future display without deleting delivery/read history.

Required authoring fields are title, plain-language body, severity, audience, effective-start time and expiry/review time. The allowed severities are `INFO`, `MAINTENANCE`, `IMPORTANT`, and `CRITICAL`; severity affects visual urgency only, never permission or financial workflow. A maintenance notice should state expected impact/window in Asia/Manila and give users enough time to save in-progress work where feasible. A `CRITICAL` notice may be non-dismissible while active only under a documented operational policy; the user still has access to the signed-in API/app unless a separate authorized maintenance mode is active.

Use plain text or a small, sanitized, allowlisted Markdown subset. No arbitrary HTML/CSS/JavaScript, embedded content, remote images, tracking pixels, raw customer data, unreviewed uploads, or external URLs in v1. An internal help/reference route may be added later only through an allowlisted internal route key. The body is rendered server-side/sanitized consistently and stored with a content hash so an edit cannot silently change a published message.

## 3. Delivery, state, and realtime behavior

The server persists the announcement/version/audience atomically before it emits an after-commit Reverb signal. A private organization/audience channel carries only an announcement identifier/version/change hint; clients refetch the authorized API record. If Reverb is unavailable, a normal authenticated API refresh/poll on app resume retrieves active notices. No application state is correct merely because a broadcast arrived.

Each user may have an `unseen`, `seen`, `acknowledged`, or `dismissed` state for an applicable published version. Viewing/acknowledging is an auditable UI state, not legal receipt, policy consent, payment proof, or proof of human comprehension. New versions reset the relevant state; notification unread counts are a convenience and must not block billing, payment, teller queue work or login. An active non-dismissible critical notice can be marked seen but stays visible until retired/expired.

W31 SMS does not automatically mirror an announcement, and W33 PWA installation does not grant Web Push permission. If an urgent customer communication later requires SMS/e-mail/web push, design a separate eligible event/purpose/audience/consent and provider flow; do not turn **publish announcement** into an unrestricted broadcast trigger.

## 4. Proposed persistence, authorization, and APIs

These are candidate FK-backed records, not migrations:

| Record | Ownership and integrity |
| --- | --- |
| `announcements` | `organization_id -> organizations`, creator/retirer actor FKs, stable lifecycle identity and current published-version pointer. |
| `announcement_versions` | `announcement_id -> announcements`, immutable published content/severity/effective window/hash and author/reviewer actors. Restrict deletion while referenced. |
| `announcement_audience_roles` / `announcement_audience_locations` | Concrete FKs to version and role/location rows. The default all-authenticated audience is an explicit enum, not a missing audience row. |
| `announcement_user_states` | `announcement_version_id -> announcement_versions`, `user_id -> users`; unique per version/user and timestamps for seen/acknowledged/dismissed. It records only users who actually encounter the message, so publishing does not require inserting rows for every user. |
| `outbox_events` / `audit_events` | Typed announcement/version references, actor/reason/correlation and redacted event metadata; no generic unbounded association replaces the concrete relations above. |

Use restrictive deletion/archival policies for audit/history and supporting indexes for active effective-window, organization/audience lookup and per-user unread-state queries. Enforce same-organization relations through reviewed constraints and policies. A staff member cannot publish across organizations, select an unassigned location, target a role they cannot administer, or infer users from a state list outside their scope.

Planned endpoints include `GET /announcements/active`, `POST /announcements/{id}/seen`, `POST /announcements/{id}/acknowledge`, `POST /announcements/{id}/dismiss`, and scoped Admin draft/schedule/publish/retire/history endpoints. Publication, retirement and a non-dismissible policy require expected-version checks, an audit reason and server-side authorization. Candidate permissions are `announcements.view`, `announcements.draft.edit`, `announcements.publish`, `announcements.retire`, `announcements.history.view`, and `announcements.audience.manage`; broader administrator status does not bypass scope/audit rules.

## 5. Admin and user experience

Add **Admin > Communications > Announcements** beside, but distinct from, **Customer Communications > Transactional SMS**. Admin sees draft/scheduled/active/expired history, audience summary, severity, effective period, revision/retirement reason and aggregated audience-state counts permitted by scope. It does not expose a customer-recipient export or a one-click mass-message action.

The application shell fetches active notices after authenticated bootstrap and shows a clear banner/notice center. Notices must remain accessible on desktop and installed PWA layouts, with an accessible focus/order, semantic severity, screen-reader text and no intrusive rendering that covers a teller's active financial editor. A maintenance warning may link only to an approved internal status/help area when such a route exists. The user can dismiss only notices whose configured policy allows it; a later change/version never erases audit history.

## 6. Acceptance and phase mapping

**P1-12 — Establish in-app announcements** depends on P1-02/P1-03/P1-05/P1-08/P1-11. It implements durable draft/schedule/publish/retire flow, organization/role/location authorization, safe rendering, per-user state, after-commit Reverb update with API recovery, audit and fake-clock/effective-window tests. PWA support is tested as an app-shell presentation/recovery detail; no Web Push, SMS or e-mail send is included.

Required acceptance proves:

- only authorized scoped Administrators can create/publish/retire; attempts to target another organization/location/role fail without disclosing its users;
- published content cannot silently mutate; schedule, expiry, replacement and retirement behave correctly in Asia/Manila time;
- the default all-authenticated audience includes a user who signs in after publication, while role/location filtering is enforced on both API and Reverb channel authorization;
- unsafe markup, URLs, personal/financial placeholders and unrestricted rich content are rejected/sanitized;
- Reverb sends after commit, duplicate/stale signals recover via API, a disconnected/reconnected client sees the correct active set, and cached PWA data never displays a retired/unauthorized notice as current;
- seen/acknowledge/dismiss state is isolated by user/version and cannot change any bill, payment, receipt, queue, credit, PPA or fiscal state; and
- W31 is not called by publication, and web-push permission is not requested merely to show an in-app notice.

Open decisions: exact content retention/visibility after expiry, operations owner and emergency/maintenance mode procedure, which role/location targeting is allowed in v1, approval/separation requirements for `CRITICAL` messages, organization support-contact wording, and a future separately approved push/e-mail/SMS escalation path.
