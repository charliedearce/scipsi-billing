# Development and validation

## Prerequisites

This is an old-style Visual Basic MSBuild project, not an SDK-style project. A compatible Windows development machine needs:

- Visual Studio/Build Tools with Visual Basic and the .NET Framework 4.5 targeting pack;
- DevExpress WinForms 15.1.4 assemblies/licenses;
- SAP Crystal Reports for Visual Studio 13.x assemblies and an appropriate client runtime (the repository parent currently contains 13.0.12 runtime installers, but installers are not source dependencies);
- `AutoUpdater.NET.dll` and `EncryptS.dll` one directory above `billing`, as referenced by `billing.vbproj`;
- access to a non-production SQL Server `billing` database with all required tables and stored procedures.

Some assemblies are referenced by strong name without a `HintPath`, so they must be installed/resolvable on the build machine. The project has no solution file and no package restore manifest.

## Configuration

Runtime modules read the `ConString` connection string from `app.config`. The checked-in file currently contains real-looking SQL Server credentials and hostnames. Do not paste them into tickets, documentation, test output, or prompts.

For development:

1. Use a dedicated least-privilege SQL login against a restored/disposable database.
2. Back up the current config outside version control if local changes must be preserved.
3. Replace only the local `ConString`; do not commit personal or production secrets.
4. Confirm the stored procedures listed in `docs/DATABASE.md` exist and match `DSBill.xsd`.

Longer term, move secrets to machine/user-protected configuration and rotate every credential already committed in repository history.

### Official Receipt template

The OR designer stores its active template at:

```text
%LOCALAPPDATA%\SCIPSI Billing\Templates\OfficialReceipt.repx
```

To use one centrally managed layout across workstations, add an application setting whose value is an absolute/shared path:

```xml
<appSettings>
  <add key="OrPrintTemplatePath" value="\\server\billing-templates\OfficialReceipt.repx" />
</appSettings>
```

The printing identity needs read access; administrators who save designs need create/write access to the directory. Saving through the application creates a timestamped backup beside the active template. Deploy DevExpress XtraReports runtime/design dependencies with the executable—the project references both `DevExpress.XtraReports.v15.1` and `DevExpress.XtraReports.v15.1.Extensions`.

Saved layouts bind to the `Receipt` and `Allocations` field names defined in `DocPrint/OrPrintTemplateService.vb`. Treat those names as a compatibility contract. The default layout uses `BillLines` and `AmountLines` for the fixed preprinted-form area; advanced templates may use the `ReceiptAllocations` relation for repeating detail rows.

### Service Billing template

The non-PPA service-bill designer stores its independent active template at:

```text
%LOCALAPPDATA%\SCIPSI Billing\Templates\ServiceBilling.repx
```

For a shared layout, add `ServicePrintTemplatePath` beside the OR setting:

```xml
<appSettings>
  <add key="ServicePrintTemplatePath" value="\\server\billing-templates\ServiceBilling.repx" />
</appSettings>
```

The same directory permissions, backup behavior, and DevExpress dependencies apply. Saved service layouts bind to the `Bill` and `Items` field names in `DocPrint/ServicePrintTemplateService.vb`. The default fixed-form layout uses the `QuantityLines`, `UnitLines`, `ServiceLines`, `CargoCodeLines`, `RateLines`, and `GrossLines` fields; advanced layouts may use the `BillItems` relation for repeating rows.

### NSCL Service Billing template

The NSCL designer uses the same service-billing data fields but saves a separate layout at:

```text
%LOCALAPPDATA%\SCIPSI Billing\Templates\ServiceBillingNSCL.repx
```

For a shared NSCL layout, add an optional application setting:

```xml
<appSettings>
  <add key="ServiceNsclPrintTemplatePath" value="\\server\billing-templates\ServiceBillingNSCL.repx" />
</appSettings>
```

No database setting or migration is required. At print time, any saved `tbl_item_trans.it_ccode` value whose trimmed value starts with `NSCL` selects this template before the legacy PPA print branch. Mixed invoices therefore use the NSCL layout for the whole invoice. Saving an NSCL design creates timestamped `ServiceBillingNSCL-*.repx.backup` files beside the active template.

## Build

From a Visual Studio Developer PowerShell or with the full executable path:

```powershell
& 'C:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\MSBuild\Current\Bin\MSBuild.exe' .\billing.vbproj /t:Build /p:Configuration=Debug /p:Platform=AnyCPU
```

Release build:

```powershell
& 'C:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\MSBuild\Current\Bin\MSBuild.exe' .\billing.vbproj /t:Build /p:Configuration=Release /p:Platform=AnyCPU
```

Expected output is under `bin/Debug` or `bin/Release`. Build success depends on licensed legacy UI/report dependencies being installed and does not validate the database or reports.

### Verified baseline (2026-09-15)

The Debug and Release `AnyCPU` commands above succeed on the current workstation with 0 errors and 15 warnings each. Fourteen `BC42104` warnings identify possibly unassigned local variables in period/reference, amount-to-words, and OR-allocation paths; one `MSB3187` warning reports a processor mismatch for Crystal Reports' `log4net` dependency. Treat this as the known baseline: do not silently add warnings, and do not interpret the successful build as runtime/database acceptance.

## Safe change workflow

### Before editing

1. Run `git status --short --branch` and note pre-existing edits.
2. Read the relevant form event handlers and every called module routine.
3. Search the affected table/column/status across the entire repository.
4. Inspect the direct print form, report caller, stored procedure, and request/admin path for the same document.
5. Record current behavior using a restored database and representative document numbers.

### While editing

- Keep hand-written behavior in `.vb` files. Use the Visual Studio designer for layout changes and expect paired `.Designer.vb`/`.resx` updates.
- Do not edit generated Crystal wrapper classes or `DSBill.Designer.vb` by hand.
- Parameterize user/data values. Specify SQL types and sizes instead of relying on string conversion.
- Wrap related financial writes in one connection and `SqlTransaction`; commit only after the header/detail/allocation/number-pool operation is complete.
- Use `Using` blocks for new connections, commands, readers, adapters, and report documents.
- Preserve `Decimal` and explicitly define rounding/truncation at two decimal places.
- Avoid adding more logic that depends on DataGridView column indexes or hidden controls; isolate new rules in testable functions where feasible.

### After editing

1. Build Debug and Release when dependency availability permits.
2. Run `git diff --check` and inspect the complete diff, especially generated resource churn.
3. Exercise the focused workflow on a test database.
4. Reopen/search the saved record and verify database rows directly.
5. Exercise cancel/edit/delete approval behavior if statuses or number allocation changed.
6. Preview and print/export every affected direct/Crystal output.
7. Verify restart/retry behavior after deliberately stopping between detail and header operations if save integrity changed.

## Manual acceptance matrix

There is no automated test suite, so choose at least the relevant rows below and report them explicitly.

| Area | Minimum acceptance |
| --- | --- |
| Login | Valid USER and ADMIN, invalid login, logout, audit entry |
| Invoice | Reserve number, normal and walk-in account, add/delete lines, domestic/foreign, PPA/VAT off/on, danger/fuel surcharge, save/search/edit/print |
| Number integrity | Two clients competing for a number, abandon/reselect, failed save, successful consumption, approved deletion restoration |
| OR | Full and partial allocations, wrong account, canceled bill, previously paid bill, CITW off/on, cash/check, insufficient cash, save/search/edit/print |
| Approval | User request, admin process, status/print flag, request status, audit log |
| Statement | Account match, invoice attach/delete, totals, save/search/edit/delete, Crystal preview |
| Transmittal | Add/remove active documents, save/search/edit/delete, report preview |
| Reporting | Date and period filters, employee scope, Crystal preview, Excel export, totals against SQL |

Use synthetic records. Never test destructive flows against the only production copy.

## Security and integrity risks

These are documented findings, not changes made by this documentation pass:

- Plaintext privileged SQL credentials are committed in `app.config` and generated settings.
- `Modules/Login.vb` includes a hard-coded privileged fallback credential, and normal passwords appear to be compared as plaintext.
- Most queries concatenate user-controlled strings, permitting SQL injection and failures on apostrophes/locale-specific values.
- The updater manifest uses unauthenticated HTTP on a hard-coded LAN address.
- Financial multi-table writes and number consumption are not atomic.
- The shared global connection/reader and broad exception handling can hide partial failures.
- Print authorization is represented by a mutable flag that changes when a print form loads, not on confirmed printer completion.
- Admin cleanup can delete duplicate rows without a schema-backed idempotency guarantee.
- Dates and decimals are often sent to SQL as formatted strings, making behavior sensitive to workstation/server locale.

Recommended modernization sequence:

1. Rotate/remove committed credentials and privileged fallback access.
2. Capture/version the database schema and procedures; introduce repeatable backups and migrations.
3. Add unique constraints for bill/OR numbers and intentional allocation identities after cleaning/validating live data.
4. Parameterize SQL and make document writes transactional at current module choke points.
5. Extract pure period and money calculations and cover them with unit tests.
6. Add integration tests against a disposable SQL Server database.
7. Replace global form/module state incrementally; keep each migration compatible with operational printing/reporting.

## Working-tree note

This repository may be used directly from an operator/developer workstation and can contain local config or Visual Studio designer changes. Never clean, reset, commit, or overwrite unrelated modifications without explicit authorization.
