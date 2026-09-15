# Architecture and workflows

## 1. System context

This repository builds one Windows executable. Each workstation runs the WinForms client and connects directly to a shared SQL Server database. Business rules, UI state, SQL, printing, and reporting are all implemented in the desktop process.

```text
Operator/Admin
    |
    v
VB.NET WinForms client
    |-- form event handlers
    |-- shared VB Modules
    |-- direct PrintDocument output
    |-- Crystal Report viewers/exports
    |
    +---------------------> SQL Server: billing
    |                         tables + required stored procedures
    |
    +---------------------> HTTP auto-update manifest (LAN endpoint)
```

There is no network service boundary, dependency injection container, domain layer, migration system, or automated test suite in the repository. Most routines manipulate VB default form instances directly.

## 2. Runtime and startup

- `billing.vbproj` targets .NET Framework 4.5 as an `AnyCPU` WinExe. Debug prefers 32-bit; Release does not explicitly set `Prefer32Bit`.
- `My Project/Application.Designer.vb` configures a single-instance application whose main form is `frmLogin`.
- `frmLogin_Load` invokes AutoUpdater.NET using a hard-coded LAN HTTP manifest.
- `Modules/Login.vb::mylogin` reads `tbl_users` and branches on exact role strings:
  - `USER` opens `frmBilling` modally.
  - `ADMIN` opens the DevExpress `frmControlpanel` modally.
- Logout disposes the current shell and shows the default `frmLogin` instance again.
- The active SQL connection is the process-global `myCon` declared in `Modules/sqlcon.vb` and initialized from the `ConString` entry in `app.config`.

## 3. Code organization

### Forms

Forms own interaction state and trigger work in global modules. Their hidden labels/text boxes frequently serve as state flags (`txtIndicator`, `txtSearchIndicator`, `txtTempno`, `txtindi`, `labelstatus`) rather than display-only fields.

Designer pairs are important:

- `X.vb` contains hand-written behavior.
- `X.Designer.vb` contains generated control construction and event-capable field declarations.
- `X.resx` contains serialized designer resources and images.

Avoid hand-editing generated pairs unless necessary. Visual Studio can rewrite large `.resx` files for a small designer change.

### Modules

Modules are the effective application/service layer, but they are tightly bound to forms:

- `sqlcon`: one global `SqlConnection`, command, reader, and adapter.
- `Login`: credential lookup and role navigation.
- `BillingMod`: invoice account/number validation, header save/update, number reservation, paid-customer search.
- `BillingAddMod`: service/cargo/tariff lookup, line calculation, detail insert, total projection.
- `BillingDeleteMod`: removes invoice detail rows.
- `ORModule`: OR number/account validation, partial-payment lookup, bank lookup.
- `ORBillnoModule`: full invoice allocation into an OR and receipt-total projection.
- `ORAddMod` / `ORDeleteMod`: OR header save/update and allocation deletion.
- `StatementMod`: SOA items, totals, header CRUD.
- `yellowTransmittal` / `WhiteTransmittal`: transmittal header/detail CRUD.
- `NewAdminMod`: active admin users, number pools, master data, approvals, audit logs, settings, exports, and cleanup.
- `AdminMod`: older admin implementation associated with `frmAdmin`; verify whether any requested flow still reaches it.
- `Formating`: number display and peso amount-to-words conversion.

### Printing and reports

There are two output stacks:

1. `DocPrint/` uses `PrintDocument.PrintPage` for bill and service-bill layouts. Official Receipts use the data-bound DevExpress report/template pipeline in `OrPrintTemplateService.vb`.
2. `Reports/` and the statement/transmittal print forms use Crystal Reports. Callers execute SQL Server stored procedures, fill the typed `DSBill` dataset, and assign a generated report class to a Crystal viewer or export it.

The `.vb` files beneath `Reports/CrystalReport/` and `Reports/CrystalRep/` are generated wrappers for `.rpt` files; behavior changes generally belong in the caller, the `.rpt`, dataset, or stored procedure rather than those wrappers.

## 4. Core document lifecycles

### 4.1 Bill/invoice

Primary UI: `frmBillings/frmBilling.vb`.

1. Admin pre-generates ten-digit bill numbers in `tbl_bill_no`.
2. An operator starts a new bill and types a number. `billnostatus` rejects an existing `tbl_bill_trans` header, checks the pool, and writes the operator name to `b_user` as a soft reservation. Moving to another number clears the previous reservation.
3. Account lookup populates customer snapshot fields. Account `11001-0000` / name `WALK-IN` enables manual customer details.
4. `frmBilladd` selects service, cargo/tariff, route-sensitive rate, quantity, and optional danger/fuel-surcharge factor. `BillingAddMod.computation` reads percentages from `tbl_settings` and writes a calculated row to `tbl_item_trans`.
5. `viewItem` treats the detail table as the source for UI totals and sums gross, PPA, discount, net, tax, and SCIPSI amounts.
6. `saveBill` inserts the `tbl_bill_trans` header and deletes the consumed number from `tbl_bill_no`.
7. Printing chooses `frmPrintBill` when PPA is present, otherwise `frmPrintService`, and marks `bt_indiprint='Y'` when the print form loads.

Important: detail rows are created before the header. Header insertion and number consumption are separate commands without a transaction. An interrupted workflow can leave orphan detail rows, a stuck reservation, or a partially saved document.

### 4.2 Calculation and period behavior

Invoice details store both inputs and calculated snapshots in `tbl_item_trans`: quantity, unit, service/cargo descriptions and codes, rate, gross, PPA, net, tax, SCIPSI, charge, and discount.

Current calculations use settings from `tbl_settings`, route (`DOMESTIC` or foreign), the invoice PPA/VAT check boxes, and special handling when an existing grid item has service marker `XXXX`. Many results use `Math.Truncate(value * 100) / 100`; preserve this two-decimal truncation unless the business owner approves a different rounding policy.

Invoice and OR period calculation is duplicated in several routines. Dates on days 1–25 use the current month period (`yyyy001` through `yyyy012`); dates after the 25th roll into the next period, except December remains `yyyy012`. Invoice references use `RB010` through `RB120` plus the numeric bill number, while `bt_br` is inserted as `RB001`.

### 4.3 Official receipt (OR)

Primary UI: `OR/frmOR.vb`.

1. Admin pre-generates ten-digit OR numbers in `tbl_or_no`.
2. `vORno` validates and soft-reserves an available number through `or_user`.
3. The operator selects an account and bank/deposit code.
4. A full allocation (`orBillNo`) requires the invoice account to match, rejects canceled invoices, and prevents allocation when the bill is already linked to an active OR. It calculates withholding (`ctiw`) from `tbl_settings` when selected and inserts `tbl_orbill_trans` with `ot_indipartial='F'`.
5. A partial allocation (`partialbill` / `savePartial`) uses the latest allocation balance and inserts `ot_indipartial='P'`, partial amount, and remaining balance.
6. `viewORItems` sums allocation rows to derive OR VAT, bill amount, withholding, and net income.
7. `frmORCash` handles cash or check entry and calls `AddOR`, which writes the `tbl_or_trans` header and consumes the OR number from the pool.
8. After the saved header exists, `OrPrintTemplateService` reloads the header/allocation snapshot, applies the active `.repx` layout, and prints. It sets `or_indiprint='Y'` only after a direct print call succeeds or the user accepts the printer dialog.

Allocation rows are still inserted before the OR header, but printing now reads the saved receipt rather than live controls. Treat changes here as exact-once accounting work.

### 4.4 OR and service-billing print templates

`DocPrint/OrPrintTemplateService.vb` contains three responsibilities kept together for this legacy project:

- `OrPrintDataProvider` loads a parameterized, read-only receipt snapshot and exposes stable `Receipt` and `Allocations` tables.
- `OrPrintReportFactory` creates the default layout using the former overlay coordinates.
- `OrPrintTemplateService` opens the DevExpress end-user designer, saves/backs up `.repx` layouts, selects direct print versus printer dialog from `tbl_settings.myprint`, and marks successful print requests.

The Admin **Settings > OR Print Designer** entry is added at runtime in `frmControlpanel_Load`, avoiding generated designer/resource churn. The designer uses sample data and does not need a live OR. Accounting values are calculated by application/database code; the template controls only presentation.

The default template location is `%LOCALAPPDATA%\SCIPSI Billing\Templates\OfficialReceipt.repx`. An `OrPrintTemplatePath` app setting can override it with an absolute or environment-variable-based path, including a shared UNC path. Existing templates are copied to timestamped `.repx.backup` files before replacement.

`DocPrint/ServicePrintTemplateService.vb` applies the same design to the non-PPA service bill formerly printed by `frmPrintService`. It reloads the saved `tbl_bill_trans` header and `tbl_item_trans` rows, exposes stable `Bill` and `Items` tables with a `BillItems` relation, and marks `bt_indiprint='Y'` only after a print request. The Admin **Settings > Service Print Designer** entry uses sample data and stores an independent `%LOCALAPPDATA%\SCIPSI Billing\Templates\ServiceBilling.repx` template. `ServicePrintTemplatePath` may override that location.

### 4.5 Cancellation, editing, and deletion approvals

Normal users create requests in `tbl_reqbill`, `tbl_reqor`, or `tbl_datesreq`. Admin screens process them through `NewAdminMod`:

- Cancel sets `bt_status` or `or_status` to `TRUE`.
- Edit resets the relevant `*_indiprint` flag to `N`, allowing the document to be edited/reprinted.
- Delete removes the header and detail/allocation records and restores the document number to its pool in the active admin deletion path.
- Backdate requests are approved/denied and marked used through `tbl_datesreq`.

These are multi-command operations without an explicit transaction. Audit messages are stored in `tbl_logs`, with separate nullable columns for login, number, bill/OR transaction, and bill/OR description categories.

### 4.6 Statement of account

`frmStatement` creates a statement header in `tbl_soa_trans` and attaches active invoices in `tbl_soa_item`. An attached bill must match the statement account. UI calculations derive overdue as `(prior balance + finance charge) - payment`, then total amount due as `overdue + current invoice charges`.

Printing executes stored procedure `Statement(@soanum)` and combines its dataset with report text objects populated directly from form controls. Statement creation does not change invoice status.

### 4.7 Transmittals

- Yellow transmittals group active bill invoices in `tbl_ymital_trans` + `tbl_ymital_item`; printing uses `ytans(@transno)`.
- White transmittals group active ORs in `tbl_wmital_trans` + `tbl_wmital_item`; item rows capture account, period, BIR code `2307`, income, withholding, OR number, and date; printing uses `wtrans(@transno)`.

The existing directory/module spelling `Transmital` is part of compiled identifiers. Rename only as a coordinated project-wide migration.

## 5. Admin and reporting

`Administrator/frmControlpanel.vb` is the active MDI admin shell. It opens user management, bill/OR number generation, transaction requests, client/service/tariff master data, percentage settings, backdate requests, audit views, and report/export dialogs.

Master data used by billing includes:

- accounts/customers and their printable identity snapshot;
- services and service aliases/codes;
- tariffs with domestic/foreign component rates;
- vessel names;
- banks/deposit codes;
- percentage settings for VAT, PPA, withholding, and discount behavior.

Revenue reporting includes hard-coded service-code groupings in `Reports/frmRevenue.vb`. Changing service codes can silently change report classification unless those queries and database reports are updated together.

## 6. Coupling and invariants

- Bill/OR numbers are strings formatted to ten digits even when forms temporarily parse them as integers.
- Header records snapshot customer name/address/TIN/business style; reports should not assume current account master data matches historical documents.
- Detail/allocation rows may exist before their headers.
- `FALSE` means active/non-canceled for bill and OR headers; `TRUE` means canceled.
- Print flags are state, not presentation: print-form load sets them to `Y`, and approved edit resets them to `N`.
- Receipt allocations link by document number, not declared foreign keys in this repository.
- UI grids are used as calculation and duplicate-detection inputs; column order therefore matters.
- The global connection/reader objects make nested or concurrent access fragile. Close/dispose behavior is part of current control flow.
