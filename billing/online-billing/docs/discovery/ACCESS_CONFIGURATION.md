# P0-05: Access and configuration baseline

Decision date: 2026-09-17. The user requires the online billing system to include user management, permissions, and web-application configuration. This document fixes the minimum Phase 1 boundary; it does not claim those features are implemented.

## User and access administration

The user has now specified four initial roles: **Customer**, **PPA user**, **Teller**, and **Administrator** (W15). [CUSTOMER_PAYMENTS.md](CUSTOMER_PAYMENTS.md) defines their access matrix and W16's gateway/proof-upload workflows. Customer ownership requires explicit customer-account links in addition to organization/location scope; PPA receives a limited verification view. Phase 1 establishes identities and access; Phase 3 delivers payment workflows after receipt posting exists.

- Administrators can search, invite/activate, suspend/reactivate, and update users within their authorized organization. Removing access preserves the historical actor record.
- A user can belong to one or more authorized locations. Membership, not a browser-selected location alone, controls data scope.
- Stable named permissions are defined in code and enforced by Laravel policies. Roles are organization-scoped bundles of those permissions; the UI shows effective permissions and their role source.
- Initial capability families cover users, roles, settings, customers/master data, invoice draft/post/reverse, receipt draft/post/reverse, approvals, templates preview/publish, reports/export, audit, and migration/operations. Exact permission names are frozen with the OpenAPI/schema review in P1-02.
- Administrators can revoke active sessions. Suspension revokes sessions and blocks new login. Activation/password-reset links are expiring and one-time; legacy plaintext passwords are never migrated as working credentials.
- Server policies prevent cross-location access, self-escalation, unauthorized role delegation, and removal/suspension of the last effective administrator. Every access mutation is structured-audited.

## Configuration boundary

W20-W34, approved as design 2026-09-19, add Admin sections for **Billing Requirements**, **Customer Tax Verification**, **Queue Operations**, **Payments: Routing and Deadlines**, **Document Studio**, **Tariffs & Surcharges**, **Credit & Collections > Late Charges**, **Customer Communications > Transactional SMS**, **Customer Accounts & Buyer Profiles**, and **Communications > Announcements**. Use the [customer service lifecycle](CUSTOMER_SERVICE_LIFECYCLE.md), [Document Studio](DOCUMENT_STUDIO.md), [pricing rules](PRICING_RULES.md), [VIP credit](VIP_CREDIT.md), [SMS notifications](SMS_NOTIFICATIONS.md), [customer registration](CUSTOMER_REGISTRATION.md), [PWA readiness](PWA_READINESS.md) and [announcements](ANNOUNCEMENTS.md) for approved states/settings and candidate permission names. Domain owners version requirements, templates, tax rules, tariff/fuel policies, queue policies, payment settings, late-charge policies, SMS templates/event policies, buyer-profile rules and announcements. Admin configures threshold amounts and hours; W23's aggregate gross-balance basis and strict less-than comparison are fixed v1 semantics, not arbitrary editable formulas. Studio layouts/SMS templates cannot calculate amounts or create triggers; labels/upload requirements cannot invent tax entitlement. Exemption approval defaults to Administrator; withholding review, bank verification, check clearance, bill-claim review, template publication, tariff/fuel publication, late-charge assessment/waiver, SMS provider enablement/resend, registration/profile exception review, announcement publication/retirement and queue overrides are distinct grants. Reverb channel membership and file downloads use the same current scope checks as API actions.

W19 adds Admin Settings > Tax & BIR, backed by domain-owned taxpayer/fiscal profile and rule versions. Taxpayer identity inputs are separate from editable branding; the app does not track whether BIR registration or approval was completed. Dedicated permissions cover tax profiles, fiscal rule publication, exports, integration and holds. Even Administrator cannot waive mandatory invoice fields, required reporting, preservation duties or posted immutability. See [BIR readiness](../BIR_COMPLIANCE.md) for boundaries and phased implementation.

W17 adds VIP client as a customer type with account-level credit eligibility, not an unrestricted staff role. See [VIP_CREDIT.md](VIP_CREDIT.md). A linked user requires both portal credit permission and an active account credit profile to charge bills. Staff credit monitoring, payment-proof approval and credit-profile management are separate grants; tellers and other permitted reviewers can approve bank-payment evidence within scope. Credit terms/limits are versioned domain configuration. VIP bank repayment remains available regardless of gateway enablement.

| Configuration class | Storage/management | Examples |
| --- | --- | --- |
| Deployment configuration | Environment/secret manager; not ordinary web settings | Database, Redis, mail, object storage, app encryption keys, trusted origins |
| Organization application settings | Typed, allowlisted, versioned DB values | Display identity, locale/timezone, non-financial UI defaults |
| Location overrides | Denied by default; registry explicitly permits a key | Location display/contact data, approved printer profile/default |
| Personal preferences | User-scoped, non-authoritative | Table page size, UI density, remembered safe filters |
| Domain configuration | Owned versioned tables/workflows, not generic settings | Number series, accounting periods, tariffs/rates, tax/calculation rules, template activation, retention, integration behavior |
| Sensitive provider material | Dedicated encrypted/write-only rotation workflow if required | Gateway/SkySMS API keys and signing secrets; never readable through settings responses |
| Customer identity and buyer profile | Domain-owned versioned records/workflows, not generic settings | Required registration fields, contact verification, account-link/claim authority and reviewed fiscal buyer-profile fields |
| In-app announcements | Domain-owned versioned records/workflows, not generic settings | Scoped audience, effective window, severity, safe content, publication/retirement and per-user UI state |
| PWA delivery | Reviewed deployment/application configuration, not customer/admin setting rows | Manifest, service-worker cache scope, release/update policy and optional future Web Push boundary |

Each database-managed key has a server registry specifying data type, validation, default, allowed scope, override policy, sensitivity, and restart behavior. Writes require permission, expected version, and audit. Cache invalidation happens after commit. A changed setting applies according to its documented effective rule and cannot rewrite issued-document snapshots.

## Phase 1 acceptance baseline

W18 places credit limits, payment terms, overdue restrictions and PPA credit acceptance in Admin Settings. Default policies and permitted per-client overrides are validated, versioned domain records with specific management permissions; this is compatible with the domain-configuration boundary above. Existing invoice terms stay captured while current policy governs new charge/eligibility decisions. Detailed controls and precedence: [VIP_CREDIT.md](VIP_CREDIT.md).

1. Direct API tests prove that hiding Vue controls is not the authorization boundary.
2. Cross-organization/location identifiers return no unintended data or mutation capability.
3. User activation, suspension, session revocation, membership/role assignment, self-escalation rejection, and last-admin protection pass.
4. Invalid/unknown setting keys, types, scopes, stale versions, forbidden overrides, and attempts to read/write deployment secrets fail with stable errors.
5. Authorized setting changes are audited, become visible after commit/cache refresh, and do not alter existing posted-document snapshots.
6. Browser acceptance covers user/role/settings workflows, but API tests remain the security proof.
7. W31 requires distinct SMS provider/template/policy/delivery permissions, write-only credential rotation, safe preview, verified contact/preference enforcement, suppression/audit history and no direct customer/bulk send control. Provider status/credit observations remain operational data and cannot mutate a financial record.
8. W32 requires distinct customer-account/buyer-profile/contact/claim-review authority; OTP/security verification cannot be an editable general setting or a W31 template. A name/contact match never auto-links an account or changes issued snapshots.
9. W34 requires separate announcement draft/publish/retire/audience/history permissions and scope checks. Publishing is an in-app after-commit/API-recoverable notice, not a direct SMS/e-mail/Web Push sender. W33 PWA caching/deployment policy stays outside ordinary Admin settings.

The four initial role names and responsibilities are user-confirmed. Open decisions remain: final organization/location model, whether administrators may create additional role bundles, customer enrollment/link verification and OTP provider/recovery policy, PPA verification scope, which non-financial settings permit location overrides, PWA update/offline policy, announcement audience/critical-message governance, and emergency access recovery ownership. These decisions refine W13-W16 without weakening the boundaries above.
