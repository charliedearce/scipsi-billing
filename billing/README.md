# SCIPSI Billing

SCIPSI Billing is a legacy Windows desktop application for preparing billing invoices, issuing official receipts (ORs), producing statements of account, preparing yellow/white transmittals, and running administrative and revenue reports.

The application is a VB.NET WinForms executable targeting .NET Framework 4.5. It connects directly to a Microsoft SQL Server `billing` database and uses DevExpress 15.1 controls and SAP Crystal Reports 13.x. There is no service/API layer in this repository.

## Documentation

- [AI contributor instructions](AGENTS.md) — the first file an AI coding agent should read.
- [Architecture and workflows](docs/ARCHITECTURE.md) — runtime structure, entry points, and document lifecycles.
- [Database contracts](docs/DATABASE.md) — code-inferred tables, relationships, statuses, and stored procedures.
- [Development and validation](docs/DEVELOPMENT.md) — setup, build commands, safe change process, and known risks.

## Repository layout

| Path | Purpose |
| --- | --- |
| `billing.vbproj` | Visual Basic project, dependency references, embedded forms/reports, and ClickOnce metadata |
| `frmLogin.vb`, `frmBillings/`, `OR/` | Login, invoice-entry, and official-receipt workflows |
| `Statement/` | Statement-of-account entry and printing |
| `yTransmital/`, `wTransmital/` | Yellow invoice and white OR transmittals (the misspelling is part of existing identifiers) |
| `Administrator/` | Active DevExpress admin control panel, master data, approvals, audits, and report exports |
| `Modules/` | Shared database access and business operations used directly by forms |
| `DocPrint/` | Direct invoice printing plus the data-bound OR template designer/renderer |
| `Reports/` | Crystal Report definitions, generated wrappers, typed dataset, viewers, and exports |
| `Resources/`, `My Project/` | Images, application settings, manifest, and generated VB project files |

## Important limitations

- The SQL Server schema and stored-procedure definitions are not versioned here.
- No automated test project is present.
- The current configuration contains plaintext database credentials and the login code contains a hard-coded privileged fallback. Do not copy those values into documentation, logs, issues, or AI prompts. See [Development and validation](docs/DEVELOPMENT.md#security-and-integrity-risks).
- Many database writes are assembled with string concatenation and span multiple commands without a transaction. Treat billing, receipt, number-pool, and cancellation changes as accounting-integrity work.

## Print designers

Administrators can open **Settings > OR Print Designer** to move, resize, format, add, or remove data-bound Official Receipt fields. The active DevExpress `.repx` template is stored under the current user's local application-data directory by default; see [Development and validation](docs/DEVELOPMENT.md#official-receipt-template) for deployment and shared-template configuration.

The same Settings menu contains **Service Print Designer** for service bills that do not use the PPA bill form. Its layout is stored independently, so OR and service-billing changes cannot overwrite each other.
