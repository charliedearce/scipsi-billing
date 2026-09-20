# PWA readiness (W33)

Design approved: 2026-09-19. The user asked that the online billing application be "WPA ready"; this plan interprets that as **Progressive Web App (PWA) readiness** unless the user corrects the term. The goal is an installable, reliable Vue application with a controlled offline and update experience. It does **not** authorize offline financial processing, local document issuance, an offline database replica, browser-held credentials, or web push by default.

## 1. Product boundary

PWA installation is a delivery/experience capability, not a different financial channel. The Laravel API and PostgreSQL database remain authoritative for authentication, current balances, customer ownership, pricing, bill creation/posting, number allocation, payment verification, receipt posting, artifacts and BIR-relevant snapshots. A device that is disconnected may show a clear offline state and recover safely; it must not calculate/post a bill, reserve a number, initiate a payment, approve evidence, generate an official document, or treat cached data as current.

The first PWA scope is:

- HTTPS production deployment, web-app manifest, icons, app name/theme/display configuration, install guidance and a registered service worker;
- cacheable public/static application shell assets with a documented cache version and update lifecycle;
- connectivity/staleness UI, an offline help/retry screen and safe restoration of an in-progress non-financial screen after the browser reconnects; and
- compatibility with the first-party Sanctum session, Reverb reconnect/recovery and the normal responsive browser experience.

PWA installation does not grant a browser notification permission. In-app announcements use the ordinary authenticated API/Reverb path while the application is open. A later Web Push feature needs a separately approved consent, subscription, VAPID/key, audience, payload-minimization, revocation, delivery-status and DPO/security design; it cannot be inferred from W33 or W34.

## 2. Cache and offline contract

| Resource/category | v1 service-worker policy | Reason |
| --- | --- | --- |
| Versioned JS/CSS/fonts/icons and non-sensitive app-shell assets | Precache/versioned cache | Lets the installable shell open and update predictably. |
| HTML/navigation shell | Network-first with a controlled offline shell fallback | Avoids presenting a stale release as the current app. |
| `/api/v1`, authentication/CSRF, Reverb authorization, command/status APIs | Network-only; never cache successful protected responses | Current server authorization and financial state are required. |
| Invoice/receipt PDFs, artifacts, uploads, tax evidence, payment proof, exports and private media | Network-only with `no-store`/equivalent response policy; never precache | Prevents cross-user leakage and false historical/current-document presentation. |
| Browser-held credentials, OTPs, session identifiers, CSRF material and private request bodies | Never persist in Cache Storage/IndexedDB/localStorage for PWA convenience | The service worker is not a secret store or authentication authority. |
| Unsaved form inputs | No automatic financial command queue. Any later local-draft recovery must have an explicit privacy/expiry/consent design and never masquerade as a saved server draft. | Prevents an offline replay from creating or changing a financial record. |

When offline, the app visibly says **Offline — reconnect to refresh protected data** and disables every server-authoritative command. It may retain only a user-entered, unsubmitted screen locally if a future approved recovery contract permits it. It must never show cached bills, balances, payment status, queue position, announcement audience, OR/PDF or a previous API result as if it were current. Reconnect performs normal authentication/authorization and authoritative API refresh; Reverb is an optimization after that refresh, not the recovery source.

Service workers normally require a secure context, and cached resources can outlive a tab; use that capability only for the narrowly classified shell. [MDN service-worker guidance](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers), [MDN PWA offline/background guide](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Offline_and_background_operation)

## 3. Safe installation and update lifecycle

Use a reviewed manifest with stable identity, icon assets, display mode, start URL and versioned build metadata. Do not force an install prompt; expose an accessible install action only when the current browser supports it. Keep browser/non-installable access fully supported.

Each deploy produces immutable hashed assets and a release/build identifier. The client detects a waiting service-worker update, shows a non-alarming **Update available** notice, and lets a user reload at a safe point. Before reloading, preserve only deliberately permitted unsaved non-financial form state; warn rather than discard an active draft/editor. A documented emergency update path may force refresh only for a security/compatibility incident, with a user-visible reason and an API-compatible deployment sequence.

On logout, session revocation, organization/account switch or permissions loss, clear any permitted user-scoped recovery state and refresh the shell's authorization-dependent view. Never rely on a cached page to decide that a revoked user can still view an artifact. Deploy application/API contracts with backward-compatible expand/contract steps so an older installed shell receives an actionable update/retry response rather than silently issuing an invalid command.

## 4. Security, operations, and accessibility

- Scope the service worker to the application origin/path only; serve its script, manifest, icons and shell over HTTPS with controlled cache headers and content-security policy.
- Review every route response for cache-control and authenticated-data leakage. A successful service-worker test is not a substitute for API authorization, CSRF, scope, financial-lock or privacy tests.
- Do not put API keys, provider credentials, OTPs, customer exports, document payloads or environment configuration into the manifest, service-worker bundle, source maps, precache list, browser console or client telemetry.
- Preserve normal keyboard, screen-reader, browser-print and low-bandwidth behavior. Installation is optional; support users who decline it or use browsers without PWA features.
- Instrument registration/update/activation/error events with redacted release/device capability data. Monitor stale shell versions, update failures, cache quota/errors, offline-screen use and Reverb reconnects without turning device telemetry into a customer-tracking export.
- Test a clean browser profile, signed-in/out transitions, permission revocation, upgrade with open unsaved screens, captive/no-network recovery, slow network, cache corruption/eviction, stale UI/API compatibility and service-worker unregister/rollback. Physical mobile acceptance remains separate.

## 5. Phase mapping

**P1-11 — Establish PWA shell and safe update/offline boundary** depends on P1-01/P1-02/P1-04/P1-08. It delivers the manifest, HTTPS/deployment requirements, versioned service worker, classified cache rules, connectivity/update UI, session/account-change purge behavior, release compatibility checks and automated/browser acceptance. It does not add offline mutation, offline billing, background financial sync or web push.

W32 registration/OTP, W31 SMS, W34 announcements and all billing modules must respect this boundary. In particular, OTP, customer contacts, announcement audience/state, invoice/receipt artifacts and financial APIs are network-only. P2/P3 financial acceptance remains online/server-authoritative even when the app can be installed.

## 6. Open decisions

Confirm the final name/branding/icons, supported browser/device matrix, offline wording/help path, release/update policy, permitted non-financial local recovery fields, telemetry retention, accessibility/device acceptance and whether a future separate push-notification initiative is wanted. No PWA cache policy is allowed to weaken fiscal retention, privacy, BIR snapshot, document-artifact or customer-ownership requirements.
