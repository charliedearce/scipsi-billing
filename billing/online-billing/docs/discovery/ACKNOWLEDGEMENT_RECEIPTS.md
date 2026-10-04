# Acknowledgement vs Official Receipt (collection documents)

Status: **engineering design + implementation slice**, 2026-09-22. Not accountant/BIR acceptance.

## Business intent (user-required)

- At collection, a teller may issue either an **Official Receipt (OR)** or an **Acknowledgement Receipt**.
- Acknowledgement receipts must still record settlement, allocations, audit and history internally.
- Acknowledgement receipts must **not** be reflected as Official Receipts / fiscal OR issuances on BIR-facing exports.
- Official Receipts remain the BIR-facing fiscal collection documents.

## Distinction from existing “provisional acknowledgment”

W24 / P3-01/P3-10 “provisional acknowledgment” refers to **check-deposit** treatment while funds are pending clearance. That remains an accountant-gated policy question and is **not** the same product choice as a posted acknowledgement receipt kind. This document covers the posted collection-document kind only.

## Implemented contract (code)

| Concern | Official (`OFFICIAL`) | Acknowledgement (`ACKNOWLEDGEMENT`) |
| --- | --- | --- |
| Settlement / allocations / invoice balances | Same shared `ReceiptPostingService` path | Same |
| Number series | `document_type = COLLECTION_RECEIPT` (e.g. `CR-…`) | Separate `document_type = ACKNOWLEDGEMENT_RECEIPT` (e.g. `ACK-…`) |
| Studio / PDF | `COLLECTION_RECEIPT` layout (“COLLECTION RECEIPT / OFFICIAL RECEIPT”) | `ACKNOWLEDGEMENT_RECEIPT` layout (“ACKNOWLEDGEMENT RECEIPT / NOT AN OFFICIAL RECEIPT”) |
| `counts_as_official_receipt` | `true` | `false` |
| `Receipt::scopeOfficialFiscal()` | Included | Excluded |
| White-receipt transmittal eligibility | Included | Excluded |
| Operational Billing & Collections Register | Included, with kind columns | Included for internal visibility; flagged non-OR |
| Future BIR OR register / fiscal export | Must use `officialFiscal()` / `counts_as_official_receipt` | Must never appear as OR |

Default when `receipt_kind` is omitted: **OFFICIAL** (preserves existing OR behavior).

## Open decisions (accountant / operations)

- Whether acknowledgement may be used for every tender channel, or only selected cases.
- ATP / registered series labeling and any statutory wording on acknowledgement PDFs.
- Whether PPA “paid” verification should treat acknowledgement the same as OR (currently both settle balances; PPA reads posted receipts — confirm if ACK should display as paid).
- Exact GL / books mapping for acknowledgement versus OR (not invented here).
- Relation, if any, between this kind and W24 provisional check acknowledgment.

## Evidence boundary

Automated tests prove kind separation, separate numbering, settlement posting, non-OR flag, Studio route and fiscal scope exclusion helpers. They do **not** prove BIR compliance, accountant acceptance, or that a live BIR export format exists (EI fiscal export remains planned).
