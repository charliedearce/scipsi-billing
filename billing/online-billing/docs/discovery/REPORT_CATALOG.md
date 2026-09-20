# P0-01 / P4-03: Report and export catalog

Evidence dates: 2026-09-17 source catalog; 2026-09-20 initial online register. This is a static source map of currently reachable report and export routes. It is not a claim that any Crystal output, stored procedure, or direct query reconciles on a particular SQL Server instance. Procedure signatures were observed read-only in the local database; report definitions and business filters still require restored-source comparison in P4-03.

| Report/export family | Caller and source contract | Current output | Migration parity risk / Phase 4 evidence |
| --- | --- | --- | --- |
| Billing-period report | `Reports/frmReport.vb` / billing month-selection forms; `BillRep(@date_from, @date_to, @employee)` through `DSBill` | Crystal viewer/export | Confirm inclusive date boundaries, employee semantics, status filter, layout totals and PDF/Excel format. |
| OR-period report | `OR/frmOR.vb::btnOrReport_Click` -> `Reports/ORMonthSelectionReport.vb`; `OrRep(@date_from, @date_to, @employee)` | Crystal viewer/export | Active count query currently uses `or_status='INVALID'`, but observed source values are `FALSE`, `TRUE`, `N`; resolve meaning with output totals before migration. |
| Administrator SUN bill/OR exports | Active `Administrator/NewAdminMod.vb::exportsunbill` / `exportsunor`; `SunBill` / `SunOr` receive optional date range and period | Crystal report exported to `.xls` | Procedure definitions use active status and period-or-date predicates. Verify null/period precedence, actual export shape, and whether legacy Excel output must be retained. |
| Volume reports | `Reports/frmVolume.vb`; `Volume(@period)` and `volume1(@period)` | Crystal report | `Volume` ignores its parameter and has a fixed 2015 date range; `volume1` uses period. Treat target date rule as an explicit product decision, not implementation parity. |
| Revenue report | `Reports/frmRevenue.vb` direct SQL and report components | Report/export UI | Static source contains hard-coded service-code grouping. Capture agreed category map and totals rather than preserving opaque literal filters. |
| RTC | Billing shell opens the RTC form; `RTC(@bill numeric(18,0))` observed | Crystal/dataset route | Verify caller, input identity (numeric bill vs printable bill number), record set and output geometry on restored source. |
| Statement | `Statement/frmPrintStatement.vb`; `Statement(@soanum)` plus current form text | Crystal `PrintStatement` | Reconcile statement snapshot/as-of formula, supplemental form text, RPT parameters, page breaking and totals. |
| Yellow transmittal | `yTransmital/frmPrintYtrans` calls `Ytans(@transno)` | Crystal report | Verify header/item membership, active-invoice filtering, report dimensions, and stock/output. |
| White transmittal | `wTransmital/frmPrintWtrans` calls `Wtrans(@transno)` | Crystal report | Verify OR/customer/period/withholding snapshot semantics, dimensions, and stock/output. |

## Observed status-filter discrepancy

The reachable local database has receipt status counts of `FALSE` 111,872, `TRUE` 1,147, and `N` 1,354. Yet the active `ORMonthSelectionReport.vb` count query uses `or_status='INVALID'`; related report snippets use `VALID`/`INVALID`. The source may contain historical code, an incomplete refactor, a different deployed database contract, or an intentional query that returns no current rows. None is safe to infer.

P4-03 must run each retained report against an approved restored source with a controlled input matrix: date boundaries, period-only, employee/no employee, active/cancelled/nonstandard statuses, empty data and a known reconciliation sample. Record input parameters, source row count, visible report total, exported total, and the approved target rule. An intentional exclusion needs a named owner and a documented replacement, not silent omission.

## P4-03 initial operational register

Implemented 2026-09-20: **Billing & Collections Register** is the safe first web-report vertical. A Teller or Administrator can read invoice-issuance and collection-receipt activity inside their organization and authorized location scope, filter it by business-date/customer/status, see exact two-decimal totals grouped by currency, and create an audited CSV export. The report has a 10,000-row hard cap before pagination/export so it never silently returns a partial data set. Exports add a UTF-8 BOM and neutralize spreadsheet formula prefixes.

The register is deliberately not a trial balance, customer balance, aging result, VAT/tax book, BIR/fiscal export, or a replacement for an issued invoice/receipt/artifact. Invoice issuance and posted cash/withholding/allocation activity remain separate streams; their period difference must not be read as a receivable balance. The current web screen uses `POSTED` invoices and receipts by default. A user may explicitly include `CANCELLED` invoices; reversed/void receipt treatment is not implied by the default.

| Legacy family | P4-03 initial mapping | Explicitly deferred / needs source-owner or accountant rule |
| --- | --- | --- |
| Billing-period Crystal report / Admin SUN bill export | Scoped invoice rows, business dates, status filter and per-currency issued totals | Legacy employee filter, `BillRep` output geometry, `SunBill` period-vs-date precedence and source status mapping |
| OR-period Crystal report / Admin SUN OR export | Scoped receipt rows, business dates, status filter and separately displayed cash, withholding, applied and unapplied totals | The observed `INVALID` versus `FALSE`/`TRUE`/`N` discrepancy, tender detail/formulas, `OrRep`/`SunOr` output parity |
| Revenue report | No implementation parity claim | Accountant-approved service/category map, revenue recognition basis and exact legacy-code replacement |
| Volume report | No implementation parity claim | Agreed cargo/unit/weight facts, period rule and treatment of the observed fixed-date `Volume` procedure |
| Account statement and yellow/white transmittals | P4-02 immutable non-fiscal snapshots and private canonical PDFs | Due/finance/void/delivery policy and legacy tax/income semantics |
| VIP aging / late charges | Administrator-only, organization-scoped, audited, spreadsheet-safe CSV now exports P3-09 **principal** aging at an explicit Asia/Manila cutoff | P3-11 late charges, policy/aging cutoff acceptance, late-charge accounting/fiscal mapping, customer/teller report scope, and accountant acceptance remain separate; a principal-aging export is not a fiscal book or PPA release |
| SMS/announcement operations | Read-only `GET /reports/communications-operations` aggregates, separate from financial reports and exports | Recipient/message/provider IDs, exports, delivery/read proof, financial state and provider action remain excluded |

## Communications operations aggregate

Implemented 2026-09-20: **Communications Operations** is a read-only, non-financial dashboard at `GET /api/v1/reports/communications-operations`. It defaults to the last 30 Asia/Manila calendar days and accepts at most 93 calendar days. It returns only delivery status/event-key counts and a failed-or-unknown follow-up count. An Administrator receives organization-scoped delivery counts plus current announcement lifecycle and in-period seen/acknowledged/dismissed aggregates; a Teller with `sms_deliveries:view` receives only delivery counts for immutable source locations assigned to that Teller and no organization-wide announcement aggregates.

The endpoint/UI contains no recipient phone/contact data, message body/hash, template/policy, provider queue/message identifier, raw provider observation, customer, bill, invoice, receipt, payment, tax, PPA or fiscal data. `provider_sent` remains a provider-reported status, not proof of delivery, payment, receipt availability or customer reading. The aggregate is not exported or audited because it is read-only and contains no financial output.

## Scope boundary

This catalog maps reporting responsibilities; it does not approve source defects as web behavior, make the initial register a fiscal export, or claim that a Crystal layout has been reproduced. Designer-driven OR/service invoice work is covered separately by [PRINT_INVENTORY.md](PRINT_INVENTORY.md). The source database facts and exception constraints are in [SOURCE_DATABASE.md](SOURCE_DATABASE.md).
