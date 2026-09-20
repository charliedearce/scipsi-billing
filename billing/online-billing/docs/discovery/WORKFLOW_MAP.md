# P0-01: Legacy workflow map

Evidence date: 2026-09-17. Paths below are relative to the legacy repository root, the parent of `online-billing/` (`../../..` from this document's directory). Confidence: SOURCE_REVIEWED means static call/data-path review, not UI acceptance. DB_METADATA_OBSERVED means the reachable local SQL Server was queried read-only; its production/restore provenance remains unconfirmed.

## Reachable workflows

| Workflow | Entry and called path | State/tables/number effects | Output and target task |
| --- | --- | --- | --- |
| Login | `frmLogin` -> `Modules/Login.vb::mylogin` | `tbl_users`; USER -> `frmBilling`, ADMIN -> `frmControlpanel`; shared connection from `Modules/sqlcon.vb`; credentials intentionally omitted | Role-specific shell; P1-02 |
| Invoice start and customer | `frmBillings/frmBilling.vb::btnNewBill_Click`, `txtBillno_LostFocus`, `txtAccountno_LostFocus` -> `BillingMod.vBillno`, `billnostatus`, `vAccno` | `tbl_bill_trans` existence check, `tbl_bill_no.b_user` soft reservation, `tbl_account`; walk-in snapshot fields | Draft controls; P2-01/P2-02 |
| Price and add item | `frmBillings/frmBilladd.vb::frmBilladd_KeyDown` and quantity/cargo events -> `BillingAddMod.vCargo`, `qtyvalidate`, `computation`, `addItem`, `viewItem` | Tariff/service/settings reads; insert `tbl_item_trans` before header; UI sums detail grid into header totals | Domestic/foreign and service-type rates; P0-03/P2-01 |
| Save/search/edit invoice | `frmBilling.btnSave_Click`, `btnSearch_Click`, `btnEdit_Click` -> `BillingMod.saveBill`, `vBillno`, `updateBill` | Header insert/update; separate pool DELETE; `FALSE` active, `N` print flag; customer/rate/date snapshots | Saved bill; P2-02/P3-03 |
| Invoice printing | `frmBilling.btnPrint_Click` -> `ServicePrintTemplateService.IsNsclBill`; then `PrintBill` or `frmPrintBill.Show` | NSCL prefix first, else PPA if nonzero, else normal; template path reloads saved header/items; printed flag after print request; PPA sets flag on form load | SERVICE_NSCL/PPA/SERVICE; P2-03..05 |
| OR start/customer | `OR/frmOR.vb` number/account handlers -> `ORModule.vORno`, `vORAccno`, `bank` | `tbl_or_no.or_user` soft reservation; `tbl_account`, `tbl_bank`; receipt draft controls | P3-01/P3-02 |
| Full allocation | `frmOR.btnAddOr_Click` -> `frmORBillno` -> `ORBillnoModule.orBillNo`, `viewORItems` | Account match; active OR join rejects already allocated bill; canceled invoice rejected; settings CITW; INSERT `tbl_orbill_trans` before header with F, partial=0, balance=0 | OR grid totals; P3-02 |
| Partial allocation | `frmOR.btnpartial_Click` -> `OR/frmPartial.vb::frmDeposit_KeyDown` -> `ORModule.partialbill`, `savePartial` | Latest allocation by `ot_id DESC`, without active receipt join; balance/indicator computed by textbox events; direct insert even though lookup does not return success/failure | Partial balance chain; unresolved rules below; P3-01/P3-02 |
| Receipt save and print | `frmOR.btnCashtendered_Click` -> `OR/frmORCash.vb::frmORCash_KeyDown` -> `ORAddMod.AddOR` -> `OrPrintTemplateService.PrintReceipt` | Cash/check branches; header INSERT then pool DELETE separately; captured receipt number passed to print after AddOR resets UI | Saved receipt + allocations; OR PDF/template replacement; P3-02/P3-04 |
| Receipt edit/reprint | `frmOR.btnUpdate_Click`, `btnPrintOr_Click` -> `ORAddMod.updateOR`, template service | Header metadata update, print flag gating; no atomic correction model | P3-03 |
| Requests and approvals | Active `Administrator/frmbilltrans.vb`/`frmortrans.vb` -> `NewAdminMod.processbillrequest`/`processorrequest` | Cancel sets status TRUE; Edit resets print flag N; Delete calls delete routine, which removes rows/restores number; request Processed and audit are separate writes | Approval result; P3-03 |
| Backdates | `NewAdminMod.requestuserbackdate`, `approveddate`, `denieddate`, `autochangedate`, `restoredate` | `tbl_datesreq` request/approval/used flags and `tbl_settings.sysdate`; operator control date may differ from real time | Period-sensitive document date; P4-01 |
| Statement | `Statement/frmStatement.vb` add/delete/save/print handlers -> `StatementMod.additemsatement`, `ViewStatement`, `SaveStatement`, `UpdateStatement`, `overdue`, `amountdue` | `tbl_soa_trans` + `tbl_soa_item`; customer match; prior balance + finance charge - payment + current charge; does not settle invoice | `Statement/frmPrintStatement.vb` calls `Statement(@soanum)` and supplies form text; P4-02 |
| Yellow transmittal | `yTransmital/` forms -> `yellowTransmittal.additemTransmittal`, `SaveYmital`, `UpdateYmital`, deletion/search routines | `tbl_ymital_trans` + `tbl_ymital_item`, active invoices | `frmPrintYtrans` -> `Ytans`; P4-02 |
| White transmittal | `wTransmital/` forms -> `WhiteTransmittal.additemWhiteTransmittal`, `SaveWmital`, `UpdateWmital`, `Updatehtax` | `tbl_wmital_trans` + `tbl_wmital_item`; OR/customer/period/withholding snapshot | `frmPrintWtrans` -> `Wtrans`; P4-02 |
| Administration | `Administrator/frmControlpanel.vb` runtime menus -> `NewAdminMod` number/customer/service/tariff/user/settings routines | Number generation/deletion, master data and settings, logs | P1-02/P4-01; older `frmAdmin`/`AdminMod` is not assumed equivalent |
| Reports/exports | `Reports/frmReport.vb`, `ORMonthSelectionReport.vb`, `frmVolume.vb`, `frmRevenue.vb`; `NewAdminMod.exportsunbill/exportsunor` | BillRep/OrRep/SunBill/SunOr/Volume/RTC, typed DSBill and direct report queries | Crystal viewers/PDF/Excel; see `REPORT_CATALOG.md`; P4-03 |

All rows above are SOURCE_REVIEWED. Procedure existence/signatures additionally observed in local DB. No operator workflow was executed in this pass.

## Discrepancies requiring decisions or targeted fixtures

1. `BillingAddMod.viewItem` selects `it_unit` at grid column 2 and `it_rate` at 5. `computation` searches column 2 for `XXXX` and treats column 5 as a discount percent. Earlier prose describing this as a service-code marker is too broad: the actual code checks the unit column. Confirm real use before remodeling it.
2. Enter in `frmBilladd` calls `computation` before `qtyvalidate`, although quantity changes also invoke `qtyvalidate`. Event order/state can affect stale inputs; the web calculator needs explicit inputs.
3. NSCL1/2/3 with PPA is blocked in item-entry validation; print selection accepts ANY NSCL prefix and gives it precedence even on historical/mixed PPA bills. Keep entry validation and print routing as separate rules.
4. Full OR allocation checks customer/cancellation and an active receipt join. Partial lookup does not reproduce these checks and compares the bill number against OR grid column 0, although `viewORItems` selects `ot_id` there. This is a discovery finding, not a behavior to copy into the web API.
5. Partial `txtBal` subtracts net cash only, while full/partial status uses cash + withholding. Withholding textbox change does not itself recompute balance. One invoice can therefore be F with a positive balance. Resolve the settlement equation explicitly.
6. Local `Volume` procedure ignores `@period` and uses a fixed 2015 date range; `volume1` does use `@period`. Existing report parity must not silently perpetuate that hard-coded filter. Record a correction decision for P4-03.
7. `AddOR` catches errors and clears fields; caller can still attempt printing. Target posting must return a definitive committed result rather than treating control reset as success.
8. The active OR month-selection count query uses `or_status='INVALID'`, while the observed local source status values are `FALSE`, `TRUE`, and `N`. Other report fragments use `VALID`/`INVALID`. Do not reproduce either spelling until restored-source output totals establish the intended semantics; see `REPORT_CATALOG.md`.

## Required next evidence

Capture UI traces and verified financial examples on a disposable restored DB, including partial/canceled payments, `XXXX`, and form event order. See SOURCE_DATABASE.md for observed anomalies, CALCULATION_FIXTURES.md for synthetic evidence, and PRINT_INVENTORY.md for output requirements.
