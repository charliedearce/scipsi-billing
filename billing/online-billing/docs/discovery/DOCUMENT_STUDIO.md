# P0-05: Admin Document Studio

User requirement W28, recorded 2026-09-19. Administrators need one controlled **Document Studio** to design the bill/sales-invoice and collection-receipt/official-receipt layouts, with later support for statements and transmittals. P2-03 implemented the JSON/Dompdf editor, protected fiscal validation, publication and activation; P4-02 added the three non-fiscal operational layout kinds; P4-04 is completing lifecycle administration and retained-workflow parity. This remains neither accountant/BIR approval nor a physical-printer acceptance claim.

## Scope and document identity

The Studio owns presentation only. Billing owns invoice amounts; Receivables owns receipt allocations; Fiscal Compliance supplies required fields and legends. A layout cannot calculate rates, tax, PPA share, fuel surcharge, payment balance, or late charges.

| Studio document kind                                   | Purpose                                                                                     | Initial route                                                                               |
| ------------------------------------------------------ | ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| `SERVICE`                                              | Regular service sales invoice/bill                                                          | Default when no higher-priority route applies                                               |
| `SERVICE_NSCL`                                         | NSCL service sales invoice/bill                                                             | Any trimmed, case-insensitive cargo code beginning with `NSCL`; this remains first priority |
| `PPA`                                                  | Applicable PPA sales invoice/bill                                                           | After NSCL, when the posted invoice has an applicable PPA route/result                      |
| `COLLECTION_RECEIPT`                                   | Receipt for a confirmed collection, including the legacy-facing OR wording where applicable | Receipt posting only; never another sale invoice                                            |
| `ACCOUNT_STATEMENT`, `YELLOW_INVOICE`, `WHITE_RECEIPT` | Non-fiscal operational documents                                                            | P4-02 snapshot payloads and canonical PDFs; under their own data contracts                  |

The primary sales document remains the applicable Invoice. A collection receipt/legacy OR is supplementary. Studio labels cannot change the legal document type, document series, fiscal date, buyer/payer snapshot, or historical record. W32 supplies a versioned future buyer profile, but Studio binds only the immutable issued snapshot; it never reads a current customer full name, company, e-mail or mobile during reprint. See [BIR readiness](../BIR_COMPLIANCE.md) and [customer registration](CUSTOMER_REGISTRATION.md).

## Studio boundary and editor contract

The Admin workspace exposes a focused Vue editor that works in physical units and renders the same server-produced PDF used for the final artifact. It supports only allowlisted elements:

- static and bound text, lines, rectangles, images, barcodes/QR codes where the approved contract permits them, and repeating item/allocation tables;
- paper size, orientation, margins, positions, fonts, wrapping, overflow, page breaks, repeated headers and supported display conditions;
- organization/location branding assets, stored privately and validated before use.

The Studio must not execute arbitrary JavaScript, PHP, SQL, remote fetches, unrestricted HTML/CSS, browser calculations, or user-defined formulas. It cannot use a template to bypass authorization, override calculated values, add a payment allocation, or reveal a private document.

The exact rendering implementation remains W08's proof-of-concept choice. A custom JSON layout is the preferred candidate; DevExpress or another renderer remains acceptable only if the proof shows it can meet fidelity, licensing, security and operating-cost requirements. Existing desktop `.repx` and Crystal assets are visual references, not files that can be imported without a reviewed mapping.

## Versioning, validation and publication

Each document kind has a versioned binding schema and immutable published layouts.

```text
draft layout -> server preview and validation -> published immutable version
              -> scheduled/scoped activation -> issued artifact snapshot
published layout -> new draft version -> validate/publish/activate -> retire when no longer routed
```

1. A designer creates or edits a draft only. Editing a published layout always forks a new draft.
2. Preview uses approved synthetic or authorized masked data and the server renderer. It identifies the binding-schema version, renderer version and any unavailable field.
3. Validation checks allowed elements, field types, overflow and rendered visibility. Fiscal document validation also checks protected content.
4. Publishing creates an immutable version. Activation is versioned by organization, permitted location/series and effective time; a newly activated matching route supersedes the prior matching route inside the activation transaction.
5. Posting resolves the route on the server, snapshots the template version, routing-rule version, payload schema and renderer version, then generates the canonical PDF after commit.
6. A historical reprint returns the original artifact. A later Studio change must never alter an issued invoice, collection receipt or prior PDF.

An activation affects future issuance only. A scheduled activation uses the server's Asia/Manila business time; it cannot silently re-render or reprice existing documents. A missing valid active layout is a configuration error before fiscal posting, not a reason to fall back to a random workstation layout.

### Current retirement boundary (P4-04)

`DRAFT` and `VALIDATED` versions may be edited/validated; `PUBLISHED` and `RETIRED` versions are immutable. Retiring requires the `templates:retire` permission and a non-empty reason. The server locks the version, refuses retirement while any current or future activation still routes to it, changes only the version status/retirement timestamp, and writes `DOCUMENT_TEMPLATE_VERSION_RETIRED` with before/after status, actor, reason, template code and version number. A retirement never deletes the layout, changes a historical document snapshot/artifact, or permits the version to be reactivated. Administrators must publish and activate a replacement route before retiring a currently used layout.

## Binding contracts and protected content

Binding schemas are additive and versioned. New fields can be added compatibly; published layouts retain the field semantics they were issued with.

| Contract                             | Required data families                                                                                                                                                                                                                                                             |
| ------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Sales invoice/bill                   | Issuer fiscal profile, immutable buyer snapshot and source profile version, invoice number/date/series, services/items, quantities, tariff/rate snapshots, tax classification/base/rate/amount, PPA share result, fuel-surcharge result, totals, terms and required fiscal legends |
| Collection receipt/OR                | Receipt number/date/series, immutable applicable payer/buyer snapshot and source profile version, verified payment method/reference, cash and approved withholding shown separately, allocations, current balances, amount in words and approved bank/check details                |
| Future adjustment/late-charge output | Linked source, adjustment/charge identity, amount, policy/rule snapshot and fiscal classification only after accountant approval                                                                                                                                                   |

For every applicable fiscal profile, protected blocks must remain visible and printable. Publication rejects a required field that is missing, clipped, zero-size, white-on-white, hidden behind another element, or conditionally removed. Branding can decorate a document but cannot overwrite legal issuer identity, tax breakdown, number, date, buyer or required legends. P0-06's accountant-reviewed field/legend matrix remains the authority for production acceptance.

## Access, audit and realtime

Implemented named permissions are `templates:view`, `templates:draft`, `templates:preview`, `templates:validate`, `templates:publish`, `templates:activate`, `templates:retire`, and `templates:assets:manage`; artifact/print permissions are separately scoped. The code-defined names are the authority, not this illustrative list.

Administrators manage Studio drafts and activation through scoped actions. Publication/activation of a fiscal template requires a distinct elevated permission and cannot bypass fiscal validation. Tellers can print/reprint only authorized existing artifacts; customers can see only their own artifacts; PPA has no template authority. Every successful Admin state change now records an append-only audit event: template creation, draft creation/fork, draft update with before/after layout, manual validation outcome, immutable publication, route activation/replaced routes, template retirement, asset upload and asset retirement. The internal validation performed by publication does not emit a misleading duplicate validation event. Preview, list and show are read-only operations and intentionally do not produce business audit records. Browser role acceptance and retained-workflow parity remain P4-04 follow-up work.

W27 Reverb events are after commit and carry only scoped identifiers/version hints. A completed preview or activation may refresh an Admin screen; it must not push private payloads or overwrite an unsaved Studio draft.

## Persistence, phases and acceptance

Logical records extend the existing output model: `document_templates`, `document_template_versions`, `document_template_activations`, `document_template_assets`, `document_snapshots`, `document_artifacts` and `print_attempts`. Use real scoped foreign keys, protected deletion, immutable publication and indexes for document kind/scope/effective-time lookup. JSON is permitted for validated layout definitions and frozen render payloads, not for financial facts.

### Branding asset boundary (P4-04)

`templates:assets:manage` uploads only structurally valid private PNG/JPEG files (2 MB and 4096 pixels per dimension maximum) into organization-scoped `local_private` storage. The API returns metadata but never a storage path; authorized download is a no-store private stream. Layout `image` elements must use a positive `asset_id`; `source`, remote URLs, SVG and arbitrary data are rejected. The server verifies each selected asset belongs to the template organization and is `ACTIVE` when a draft is created or edited. A renderer receives an organization ID, reads the private payload itself and embeds a data URI with Dompdf remote fetch disabled.

Retirement is non-destructive: it records `DOCUMENT_TEMPLATE_ASSET_RETIRED`, prevents selecting the asset in a newly created or edited draft, and does not delete the bytes. A fork may retain a historical image reference so an administrator can replace it, and existing published layouts, snapshots and failed-artifact retries remain renderable. The supported Docker PHP runtime includes GD for PNG/JPEG PDF rendering. Host-only PHP without GD does not constitute image-PDF acceptance.

P2-03 proves the Studio/editor/renderer and the binding/validation contract. P2-04 uses it for sales-invoice routes and digital artifacts; P3-02 adds the collection-receipt/OR template route; P4-04 completes operational document administration and parity. Physical printer/paper and output samples are intentionally deferred from the current online-only scope and must be accepted before a physical-print rollout.

Required future acceptance includes:

- Draft, preview, validation, publish, scheduled activation, failed activation and retirement; no duplicate/ambiguous active layout.
- Normal/PPA/NSCL routing, including lowercase/space-prefixed and mixed NSCL invoices; NSCL still wins before PPA.
- Sales-invoice and receipt contracts with empty/long/multi-page data, fuel/PPA/tax/withholding values and amount-in-words.
- Required-field omission, clipping, conditional hiding, invalid asset and unauthorized publish/activation attempts rejected before issuance.
- Historical reprint after a later template change, failed PDF retry and scope-restricted artifact access. Physical printer alignment is a deferred acceptance case to re-open with P2-05 before enabling device printing.

P2-03/P4-02/P4-04 automated evidence exists for the implemented Studio slices. The retained browser, physical-print and business acceptance cases above remain open.

The [P4-04 parity and exclusion register](DOCUMENT_STUDIO_PARITY.md) maps the legacy OR/service/NSCL designer, PPA, statement, and transmittal outputs to these online kinds. It records the explicit online-only exclusions and re-entry gates; it is not a substitute for browser, accountant, or physical-output acceptance.
