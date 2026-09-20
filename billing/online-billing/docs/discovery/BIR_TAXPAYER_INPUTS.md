# P0-06: BIR taxpayer input worksheet

Status: `IN_PROGRESS`, 2026-09-18. This worksheet supports the online billing compliance-readiness plan. It is not a BIR form and does not establish approval. Complete it with the taxpayer's accountant/tax adviser and, where needed, the responsible BIR office. It records system inputs and review decisions only; it does not track BIR registration, certification, filing or approval status.

Keep the completed copy in a restricted location. Do not commit credentials, signing keys, payment details or unredacted customer records. TIN and taxpayer documents should be shared with an AI only when the owner has approved the handling and has redacted unnecessary personal data.

## Source-review prompts for the accountant, 2026-09-20

These prompts translate the current official-source review into company-specific decisions. Enter an evidence reference and reviewer decision; do not infer an answer from this worksheet, a legacy form, a demo layout or a draft issuance. The authoritative source register and limitations are in the [BIR compliance plan](../BIR_COMPLIANCE.md).

| Topic to resolve | Restricted evidence/reference needed | Result required from the accountant or authorized owner |
| --- | --- | --- |
| Principal service invoice versus supplementary collection document | Approved/redacted current document samples and applicable issuance rule | Decide the allowed document names, required fields/legends, when each is issued, and the payer/buyer snapshot representation |
| Electronic-invoice applicability | Classification evidence for taxpayer size, LTS, e-commerce/internet activity, and CAS/CBA/other invoicing-software use | State whether an electronic-invoice rule applies to this taxpayer, the affected branches and effective date; do not treat the online app or a portal as proof by itself |
| Electronic reporting/integration | Existing notices, pilot/certificate references, approved technical contract and accountable owner | State any confirmed reporting schema, transport, timing, acknowledgment, outage and reconciliation rule separately from invoice issuance; this app does not track registration or approval status |
| Issuer, buyer and tax profile | Restricted profile/branch evidence plus approved examples for VAT, exempt, zero-rated, non-VAT and withholding cases that actually occur | Approve exact validation/display fields, legal basis, rate/base/rounding treatment and effective dates; portal name, email and mobile do not establish fiscal buyer identity |
| Series, correction, retention and accounting outputs | Approved number-register/sample evidence, correction policy and books/GL interface specification | Approve branch/series boundaries, void/gap/replacement controls, correction document behavior, retention anchors/holds, export fields and reconciliation owner |

The prompts prepare implementation and review. They do not report or imply BIR registration, certification, filing, approval, enrollment, permit or transmission completion.

## A. Taxpayer identity and locations used by the system

| Field | Value / evidence reference | Reviewer / date |
| --- | --- | --- |
| Legal entity / registered taxpayer name | `UNKNOWN` |  |
| Registered address and business style (if used) | `UNKNOWN` |  |
| TIN and branch code used on documents (restricted copy reference) | `UNKNOWN` |  |
| RDO, LT office or other responsible BIR office | `UNKNOWN` |  |
| Head office and all registered branches/facilities | `UNKNOWN` |  |
| Fiscal year and return filing calendar | `UNKNOWN` |  |

## B. Taxpayer classification and activities

Mark `YES`, `NO` or `UNKNOWN`, and attach an evidence reference—not a secret—to each answer.

| Question | Answer / evidence reference | Reviewer / date |
| --- | --- | --- |
| VAT-registered? Registered VAT tax types? | `UNKNOWN` |  |
| Non-VAT / percentage-tax registration also applicable? | `UNKNOWN` |  |
| Micro, Small, Medium or Large classification under applicable rules? | `UNKNOWN` |  |
| Under Large Taxpayers Service (LTS)? | `UNKNOWN` |  |
| E-commerce or internet transactions? | `UNKNOWN` |  |
| Export of goods/services? | `UNKNOWN` |  |
| Registered Business Enterprise / tax incentives? | `UNKNOWN` |  |
| POS, CRM or other sales machine used? | `UNKNOWN` |  |
| CAS, CBA, ESS, middleware or other invoicing software used? | `UNKNOWN` |  |
| Existing BIR EIS enrollment, pilot, certificate or transmission duty? | `UNKNOWN` |  |

## C. Existing issuance inputs and approved document references

These references help configure the system's output. The application does not record whether a registration or approval process is complete.

| Item | Current status / restricted evidence reference | Reviewer / date |
| --- | --- | --- |
| Taxpayer/tax-type details needed in the system profile | `UNKNOWN` |  |
| Invoice/issuance identifiers or legends that must appear, if applicable | `UNKNOWN` |  |
| Existing CAS/CBA/component/POS/EIS details that affect system behavior, if applicable | `UNKNOWN` |  |
| Principal invoice samples supplied for design review | `UNKNOWN` |  |
| Supplementary receipt/document samples supplied for design review | `UNKNOWN` |  |
| Series, ranges, branches and contingency-form inputs | `UNKNOWN` |  |
| Books and accounting/report system owner | `UNKNOWN` |  |

## D. Accountant-reviewed fiscal contract

Do not turn an unknown into a default just to complete the form. Each decision needs a provision/evidence reference, reviewer and effective date.

| Decision | Confirmed rule / example | Provision or evidence | Reviewer / effective date |
| --- | --- | --- | --- |
| When a service invoice is issued/recognized | `UNKNOWN` |  |  |
| VATable, exempt, zero-rated and mixed-item treatment | `UNKNOWN` |  |  |
| Required issuer, buyer, item, tax, legend and identifier fields | `UNKNOWN` |  |  |
| Buyer profile versus portal-contact fields; legal-name/TIN/address validation; invoice buyer and collection-receipt payer snapshot rules | `UNKNOWN` |  |  |
| Cash, credit and VIP credit invoice labels | `UNKNOWN` |  |  |
| Collection receipt and allocation treatment | `UNKNOWN` |  |  |
| Discounts, withholding and rounding equations | `UNKNOWN` |  |  |
| Cancellation, credit/debit adjustment and correction workflow | `UNKNOWN` |  |  |
| Number series scope, gaps, voids and replacement policy | `UNKNOWN` |  |  |
| Filing-date retention anchor, holds and legal exceptions | `UNKNOWN` |  |  |
| Books/GL export fields, frequency and reconciliation owner | `UNKNOWN` |  |  |
| Electronic invoice/reporting schema, timing and outage rules | `UNKNOWN` |  |  |

## E. Review decision (not a registration status)

- Applicability status: `UNREVIEWED` / `ACCOUNTANT_REVIEWED` / `EXTERNAL_CONFIRMATION_NEEDED`
- Affected organization/branches:
- Open issues and owner:
- Next review date or regulation trigger:
- Related task evidence: [BIR compliance plan](../BIR_COMPLIANCE.md), [P0-06 evidence](../evidence/P0-06.md)

This worksheet is complete only when the input references and reviewers are recorded. A completed worksheet still does not equal BIR certification and does not report whether registration occurred; it enables implementation and external-review preparation in P2-06, P4-05, EI-01 and P6-02.
