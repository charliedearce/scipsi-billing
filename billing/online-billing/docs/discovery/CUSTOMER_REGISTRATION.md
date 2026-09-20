# Customer registration, buyer profile, and verified contacts (W32)

Design approved: 2026-09-19. The customer registration form must require a **full name**, **company / registered buyer name**, **email address**, and **mobile number**. The mobile number supports account OTP and eligible customer/VIP notifications. This is a planning contract, not a registered user, OTP provider call, database migration, or proof that a supplied company name satisfies a fiscal buyer requirement.

## 1. Four distinct meanings

Keep these records and responsibilities distinct:

| Meaning | Required registration data | Authority and use |
| --- | --- | --- |
| Portal user | `full_name`, login identity, email and mobile contacts | The natural person who signs in. Their name is a contact/actor name, not automatically the legal buyer on an invoice or collection receipt. |
| Customer business account | `company_name` submitted at registration | The customer/VIP account to which bills, credit eligibility, requests and authorized portal users are linked. A walk-in account can exist before any portal user. |
| Contact point | Normalized email or E.164 mobile number, verification state and purpose | A controlled communication destination. Mobile OTP proves present possession of that number; it does not prove the person may represent a company or see every bill. |
| Buyer/billing profile version | Submitted company/registered buyer name plus the reviewed fields that P0-06 requires | The source for a new draft. Posting captures an immutable buyer/payer snapshot; it is not a live profile lookup during a reprint. |

The current portal label is **Company / registered buyer name** and it is required. A natural-person buyer still needs an approved legal-name capture rule rather than a made-up company value. P0-06 and the accountant-reviewed fiscal matrix decide which legal buyer name, address, TIN, branch or other fields are required for an applicable invoice or collection receipt. Registration does not itself verify those facts.

## 2. Registration and contact-verification journey

1. The applicant supplies full name, company / registered buyer name, email and mobile number. The server validates required fields, normalizes email/mobile values, creates or resumes an opaque registration flow, and returns non-enumerating responses.
2. The application creates a pending portal user, customer account, initial buyer-profile version, and unverified contact records in one controlled transaction. A phone or company match never automatically joins an existing account.
3. A separate OTP security flow verifies the mobile contact. Email verification is also required before that address becomes an eligible future email-notification destination. Production activation rules must require verified contacts rather than trusting typed values.
4. Once required account activation checks complete, create the explicitly authorized `customer_user_link`. A newly verified mobile proves control of a phone at that time only; it does not establish employment, delegated payer authority, tax status, or account-wide access.
5. The customer can complete requests, bill claims and payment actions only within their established link/scope. An otherwise valid business account may remain portal-unlinked until teller review or an approved claim grants the least access needed.

Use generic success/availability responses for registration, resend, contact changes and claims. Do not reveal whether an email, mobile, company, account, or billing number already exists. A shared business mobile is possible, so do not impose a global phone uniqueness rule that silently blocks a valid account or leaks the account that used it first. The reviewed migration instead defines permitted account/contact/user associations and checks their authority explicitly.

### OTP is a security flow, not W31 transactional SMS

W31 deliberately excludes authentication/OTP. OTP uses a separate server-side adapter, fixed security purpose and restricted audit path; an Administrator cannot edit the OTP message in **Customer Communications > Transactional SMS**, send an arbitrary security code, or repurpose a notification template as an authenticator.

SkySMS documents an OTP send endpoint, provider-generated six-digit code, five-minute expiry and a verification endpoint. P1-10 must first prove whether that provider service is suitable for the required security, correlation, retention and abuse controls; it may instead select an equivalent adapter behind the same application contract. The browser calls only the application's POST endpoints. If the SkySMS verification route remains a query-string request, the Laravel adapter calls it server-to-server with URL/query redaction and no raw code in request logs, audit events, exceptions, analytics, queues, browser history or support exports. [SkySMS API documentation](https://skysms.skyio.site/docs)

Every challenge has a narrow purpose (`REGISTRATION_MOBILE`, `MOBILE_CHANGE`, or `WALK_IN_CLAIM`), an opaque challenge ID, target contact version, expiry, single-use atomic consumption and a server-side attempt/send policy. A resend supersedes the prior challenge; never persist a plaintext OTP. Enforce independently reviewed rate limits and abuse controls by normalized contact, registration/account context, IP/device risk and action; lock/step-up/manual review behavior is explicit rather than silently retrying. Wrong, expired, replayed, replaced or cross-purpose codes return generic results and cannot activate a link. Provider result, credit use, timeout or delivery state cannot itself grant authentication or change a financial record.

## 3. Profile ownership, changes, and documents

The portal user manages their own full name and requests contact/profile changes through authorized flows. Changing an email or mobile creates a new contact/profile version and requires verification before it is used. A company/registered-buyer or fiscally relevant profile change is audited and may require staff review under the P0-06 field matrix. It affects future drafts only unless an approved, linked fiscal correction is required; it never edits an issued invoice, collection receipt/OR, artifact, number, tax result or historic reprint.

At invoice posting, the service validates the reviewed buyer requirements and captures both the selected `buyer_profile_version_id` and an immutable field-level buyer snapshot in the issued document payload. The Document Studio binds the stored snapshot, not the latest company/contact record. At receipt/collection-receipt posting, capture the applicable payer/buyer snapshot under the accountant-approved rule for the settled invoice(s). A later receipt may carry a different contemporaneous payer/contact fact without mutating the original sale snapshot. Replacements/corrections create a linked new document/snapshot under W26; the original remains intact.

This distinction is why a full name is required at registration while the company / registered buyer name is needed for billing and OR output: the full name identifies the authorized portal person, while the reviewed buyer profile supplies the business-facing document identity. Neither value alone substitutes for the P0-06 fiscal matrix.

## 4. Walk-ins, claims, and legacy import

A teller may create a customer business account and buyer-profile version for a walk-in without fabricating a portal user. The issued invoice uses the information supplied at that time and preserves its snapshot. Later registration does not overwrite that buyer data simply because the applicant typed the same company name, email or phone number.

Entering a billing number starts an opaque claim request. It may grant only invoice-level access after a successful purpose-bound one-time code delivered to an already verified eligible contact, or after authorized teller review. It must not disclose that a billing number exists, expose the business account, grant all company bills, or create delegated authority. A broader account link needs its own evidence/approval. P2-09 owns this claim policy; generic registration OTP success is not a shortcut around it.

Historical import preserves source buyer/document snapshots and provenance. It must not invent full names, company names, email/mobile contacts, contact verification, portal links, OTP events or SMS/e-mail notifications. Missing buyer/fiscal facts remain classified migration exceptions rather than inferred registrations.

## 5. Proposed relational contract and APIs

These are candidate reviewed migrations, not SQL to apply now. They honor W12's real foreign-key requirement without using a polymorphic `type`/`id` shortcut for financial or authorization facts.

| Record | Required relations and integrity boundary |
| --- | --- |
| `users` | Portal identity and lifecycle; normalized email identity is unique only where the approved login model requires it. Historical actors are retained. |
| `customers` | Organization-scoped business account; can exist without a `users` row. Archive, do not delete, while referenced. |
| `customer_user_links` | `user_id -> users`, `customer_id -> customers`; unique active association and explicit authority/scope. The link, not a matching phone/company name, authorizes portal access. |
| `customer_contact_points` | `customer_id -> customers`, optional `user_id -> users`, contact kind/value/version and verification state. Index normalized value for controlled duplicate/risk checks; use explicit association/authority rows where a shared contact is allowed. |
| `contact_verification_challenges` / `contact_verification_events` | Contact/version and user/registration context FKs; purpose, expiry, consumption and redacted provider reference. Retain no plaintext code. |
| `customer_identity_versions` | `customer_id -> customers`, author/reviewer actor FKs and immutable version/effective state. Validated fiscal fields are added only after P0-06 approves them. |
| `invoices` / `receipts` | Restrictive links to customer account and captured buyer-profile version plus immutable buyer/payer snapshot fields in the document payload. Referenced accounts/profiles cannot be cascaded away. |

Add supporting indexes to the child FK columns and scope/document lookup paths; an FK does not create those indexes automatically. Enforce organization consistency through composite foreign keys or equivalent reviewed constraints. Do not use mobile, email, full name, company name or printable bill number as a financial primary key.

Planned application endpoints include `POST /auth/register`, `POST /auth/contact-verifications/mobile/send`, `POST /auth/contact-verifications/mobile/verify`, an equivalent email-verification flow, and authorized `GET/PATCH /portal/profile` operations. Claim endpoints remain distinct from registration and use opaque request IDs/consistent responses. The final OpenAPI contract must use CSRF/session controls, idempotency where appropriate, expected versions for profile edits, server validation, rate limiting and redacted observability.

## 6. Administration and acceptance

Add **Admin > Customer Accounts & Buyer Profiles** for scoped account/profile correction, contact-review and registration/claim exceptions. This is not a free-form application setting: field definitions, verification rules, authority policy and fiscal requirements are domain-owned and audited. Candidate permissions are `customer_accounts.view/manage`, `buyer_profiles.view/edit/review`, `customer_contacts.view/manage`, `customer_registration.review`, `bill_claims.review` and a separate security/provider diagnostic permission. Teller access is only the least scope needed for walk-in setup or review; PPA has no profile/OTP authority.

P1-10 acceptance must prove at least:

- missing/invalid required registration fields are rejected server-side; normalized duplicate/shared-contact behavior is safe and non-enumerating;
- mobile OTP is purpose-bound, expires, cannot be replayed/reused, cannot be logged in plaintext, and is rate-limited without a provider call under a financial lock;
- a verified mobile proves possession only, not company authority or fiscal eligibility;
- registration/claim cannot disclose or link another account from a shared company/mobile/email or guessed billing number;
- email/mobile/company/profile changes take effect only after the proper verification/review and only for future documents;
- an invoice/receipt/OR/PDF remains byte/content-stable with its stored buyer/payer snapshot after profile/contact edits, and Studio preview/reprint cannot read live customer data;
- walk-in/imported records remain portal-unlinked until approved, generate no OTP/SMS on import, and retain original buyer provenance; and
- P0-06-required buyer fields block applicable fiscal posting until a reviewed profile supplies them.

## 7. Phase mapping and remaining decisions

P1-10 implements the registration/buyer/contact/OTP foundation after P1-02/P1-03/P1-05/P1-06/P1-09. P2-01 and P2-06 consume a validated buyer profile for draft/posting; P2-09 adds the separate walk-in claim flow; P3-02 captures the receipt payer/buyer snapshot. W31 sends an eligible transactional SMS only to an active verified mobile contact after an owning committed event; OTP has no W31 template or financial effect.

Still open: the approved login/email provider and activation policy; exact OTP provider/correlation/retention and rate-limit values; supported individual-buyer naming; shared-contact/delegated-authority evidence; P0-06 required buyer fields and validation; accountant-approved payer-versus-buyer receipt representation; staff review/SLA; and historical import mapping. These are implementation/activation gates, not reasons to collect less than the four user-required registration fields now.
