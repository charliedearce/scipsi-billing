# AI contributor guide

Read `README.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, and `docs/DEVELOPMENT.md` before changing code. This file applies to the entire repository.

## Project facts

- Legacy VB.NET WinForms, .NET Framework 4.5, single-instance startup at `frmLogin`.
- Direct SQL Server access through the shared `myCon` connection in `Modules/sqlcon.vb`; there is no API or repository layer.
- Active user shell: `frmBillings/frmBilling.vb`. Active admin shell: `Administrator/frmControlpanel.vb` backed mainly by `Administrator/NewAdminMod.vb`.
- `frmAdmin.vb` and `Modules/AdminMod.vb` are older parallel admin code. Confirm reachability before editing them.
- UI code uses VB default form instances and module procedures that read/write controls on other forms. A change that looks local often has cross-form side effects.
- Database DDL and stored-procedure source are absent. `Reports/DataSets/DSBill.xsd` is a report projection, not a complete authoritative schema.

## Change boundaries

1. Inspect `git status` and preserve unrelated work. Never regenerate or normalize `.resx`, `.Designer.vb`, `.rpt`, or typed-dataset generated files unless the requested UI/report change requires it.
2. Trace the event handler, called module procedure, every affected table, print/report path, status flag, and number-pool side effect before editing.
3. Preserve ten-digit zero-padded bill/OR numbers and the existing meanings of `FALSE`/`TRUE`, `N`/`Y`, and `F`/`P` documented in `docs/DATABASE.md`.
4. Do not add or update a financial header without considering its detail rows. Do not consume or restore a bill/OR number without considering failure and retry behavior.
5. Use parameterized `SqlCommand` statements and explicit `SqlTransaction` for new or substantially revised database writes. Do not widen a focused fix into a wholesale data-layer rewrite.
6. Keep money in `Decimal`; avoid introducing `Double` into new financial calculations. Match the existing two-decimal truncation/rounding rule for the workflow unless requirements explicitly change it.
7. Never expose, duplicate, or commit new credentials. Treat all existing connection strings and fallback credentials as secrets.
8. Do not infer that a successful compile proves financial correctness. Validate against a disposable/restored SQL Server database and exercise printing/reporting when those paths change.

## Where to start by task

| Task | Start here | Then inspect |
| --- | --- | --- |
| Login/roles | `frmLogin.vb`, `Modules/Login.vb` | `tbl_users`, `singinlogs`, both user/admin shells |
| Invoice header | `frmBillings/frmBilling.vb`, `Modules/BillingMod.vb` | number pool, line items, direct print, reports |
| Invoice line/rate | `frmBillings/frmBilladd.vb`, `Modules/BillingAddMod.vb` | `tbl_settings`, tariff/service/cargo lookup, invoice totals |
| Official receipt | `OR/frmOR.vb`, `Modules/ORModule.vb`, `Modules/ORAddMod.vb` | allocation module, cash/check form, `DocPrint/OrPrintTemplateService.vb` |
| Invoice-to-OR allocation | `Modules/ORBillnoModule.vb`, `OR/frmPartial.vb` | cancellation status, partial balance history |
| Statement | `Statement/frmStatement.vb`, `Modules/StatementMod.vb` | `Statement` stored procedure and Crystal report |
| Transmittal | `Modules/yellowTransmittal.vb` or `Modules/WhiteTransmittal.vb` | matching forms and `ytans`/`wtrans` reports |
| Admin/master data | `Administrator/frmControlpanel.vb`, `Administrator/NewAdminMod.vb` | request/approval and audit tables |
| Reporting/export | caller form under `Reports/` or `Administrator/` | stored procedure, `DSBill.xsd`, `.rpt`, generated wrapper |

## Minimum validation report

State separately:

- build result and exact configuration;
- database workflow tested and database used;
- print/Crystal Report path tested;
- anything not tested manually;
- unrelated pre-existing working-tree changes left untouched.

For OR layout changes, preserve the public field names in `OrPrintDataProvider` because saved `.repx` templates bind to them. Add new fields compatibly; do not rename/remove existing fields without a template migration.

For service-billing layout changes, preserve the field names in `ServicePrintDataProvider`. Saved templates bind to the `Bill` table and its `BillItems` relation; add fields compatibly rather than renaming or removing them.
