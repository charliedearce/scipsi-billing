# Database contracts

## Scope and confidence

The repository has no SQL DDL, migrations, backup schema, or stored-procedure definitions. This document is inferred from SQL strings in the VB source and from `Reports/DataSets/DSBill.xsd`. It is a navigation aid, not a substitute for scripting the live SQL Server schema (tables, columns, types, defaults, keys, indexes, triggers, procedures, and permissions) before a database change.

The application connection used by normal code is `ConfigurationManager.ConnectionStrings("ConString")` in `Modules/sqlcon.vb`. Other settings-generated connection strings exist but are not the shared runtime connection used by the modules reviewed.

## Logical relationship map

```text
tbl_account
    +--< tbl_bill_trans (bt_acc_num)
    |       +--< tbl_item_trans (it_bill_num)
    |       +--< tbl_orbill_trans (ot_bill_no)
    |       +--< tbl_soa_item (si_billnum)
    |       +--< tbl_ymital_item (yi_billnum)
    |
    +--< tbl_or_trans (or_acc_num)
            +--< tbl_orbill_trans (ot_or_no)
            +--< tbl_wmital_item (wi_ornum)

tbl_soa_trans (st_soanum)   +--< tbl_soa_item (si_soanum)
tbl_ymital_trans            +--< tbl_ymital_item
tbl_wmital_trans            +--< tbl_wmital_item

tbl_bill_no / tbl_or_no are consumable number pools, not transaction headers.
```

Foreign keys are logical relationships observed in code; their physical existence is unverified.

## Table catalog

### Financial documents

| Table | Role | Important observed fields/behavior |
| --- | --- | --- |
| `tbl_bill_trans` | Invoice header | `bt_bill_num`, customer/account snapshot, vessel/voyage/particulars, route/type/date/employee, totals, period/reference, `bt_status`, VAT/PPA/print indicators |
| `tbl_item_trans` | Invoice detail | `it_bill_num`; quantity, unit, service/cargo and codes; rate/gross; PPA/net/tax/SCIPSI/charge/discount calculated snapshots |
| `tbl_or_trans` | Official-receipt header | `or_num`, customer/account snapshot, remarks/date/employee, aggregate amounts, cash/check/bank/book/period, `or_status`, `or_indiprint` |
| `tbl_orbill_trans` | OR-to-invoice allocation | `ot_or_no`, `ot_bill_no`, VAT/SCIPSI/withholding/net/discount, partial amount, remaining balance, `ot_indipartial` |
| `tbl_soa_trans` | Statement header | SOA number/dates, account/customer/address/addressee, prior balance, finance charge, payment/OR paid, overdue/current charge/amount due, employee |
| `tbl_soa_item` | Statement invoices | SOA number, bill number/date, vessel/voyage/service, SCIPSI charge, PO number |
| `tbl_ymital_trans` | Yellow-transmittal header | Transmittal number and date |
| `tbl_ymital_item` | Yellow-transmittal bills | Transmittal number, bill/date, vessel/voyage, service/type/customer |
| `tbl_wmital_trans` | White-transmittal header | Transmittal number and date |
| `tbl_wmital_item` | White-transmittal ORs | Transmittal number, account/customer, period, BIR code, income, withholding tax, OR/date |

### Numbering, approvals, and audit

| Table | Role | Important observed fields/behavior |
| --- | --- | --- |
| `tbl_bill_no` | Available invoice-number pool | `b_num`, `b_user`; row is deleted when a bill header is saved and may be restored on approved deletion |
| `tbl_or_no` | Available OR-number pool | `or_num`, `or_user`; same lifecycle as bill pool |
| `tbl_reqbill` | User request for bill cancel/edit/delete | number, request type/reason/requester, `Pending`/`Processed` status |
| `tbl_reqor` | User request for OR cancel/edit/delete | analogous to `tbl_reqbill` |
| `tbl_datesreq` | Backdate request | requested date/requester/reason, approval status, `d_use` flag |
| `tbl_logs` | Audit/event text | category-specific columns: `audit`, `b_desc`, `or_desc`, `btran_desc`, `ortran_desc` |

### Identity, master data, and settings

| Table | Role | Important observed fields/behavior |
| --- | --- | --- |
| `tbl_users` | Login and authorization | username, password, full name, exact role `USER` or `ADMIN` |
| `tbl_account` | Customers/accounts | code, name, address, TIN, business style; `WALK-IN` has special UI handling |
| `tbl_service` | Services | service code, description, type |
| `tbl_scode` | Service code choices | code list used in tariff/service UI |
| `tbl_tarrif` | Tariffs | tariff code/description/basis/unit/class, domestic and foreign rate components, service code/name |
| `tbl_vessel` | Vessel autocomplete | vessel values consumed by invoice UI |
| `tbl_bank` | Bank/deposit choices | bank code bound to OR UI |
| `tbl_settings` | Global percentages and system date | print setting, VAT, domestic/foreign PPA, CITW, discount, `sysdate` |

The spellings `tbl_tarrif` and `ctiw` are established database/code identifiers. Do not “correct” them in one layer only.

## Encoded state

| Field | Values observed | Meaning in current code |
| --- | --- | --- |
| `bt_status`, `or_status` | `FALSE`, `TRUE` | Active/non-canceled, canceled |
| `bt_indiprint`, `or_indiprint` | `N`, `Y` | Not marked printed, print form has loaded |
| `bt_indivat`, `bt_indippa` | `N`, `Y` | Header amount indicates VAT/PPA |
| `ot_indipartial` | `F`, `P` | Full invoice allocation, partial allocation |
| `b_user`, `or_user` | empty or full name | Soft workstation reservation of an available number |
| request status | `Pending`, `Processed` | Awaiting/admin-completed request |
| backdate status | `Pending`, `Approved`, `Denied` | Admin decision |
| `d_use` | `N`, `Y` | Approved backdate not yet used / used |
| route | `D`, `F` in header; descriptive text in UI | Domestic / foreign |
| type | `I`, `O` in header | In / out |

These values are strings, not booleans/enums at the application boundary.

## Required stored procedures

| Procedure | Parameters used by client | Consumer/output |
| --- | --- | --- |
| `BillRep` | `@employee varchar`, `@date_from datetime`, `@date_to datetime` | Operator bill reports and Excel/PDF/print views |
| `OrRep` | `@employee varchar`, `@date_from datetime`, `@date_to datetime` | Operator OR reports |
| `SunBill` | Either `@period int` or `@date_from`, `@date_to` depending admin tab | Admin bill export/report |
| `SunOr` | Either `@period int` or `@date_from`, `@date_to` depending admin tab | Admin OR export/report |
| `Volume` | `@period bigint` | Volume report |
| `RTC` | `@bill int` | RTC bill output |
| `Statement` | `@soanum int` | Statement detail report |
| `ytans` | `@transno int` | Yellow transmittal report |
| `wtrans` | `@transno varchar` | White transmittal report |

The procedure result shapes must remain compatible with the tables in `DSBill.xsd` and the fields embedded in the corresponding `.rpt` files. `SunBill`/`SunOr` parameter branching should be verified against the live procedure definitions because different UI tabs send different parameter sets.

## Write-integrity observations

- Most SQL is built by concatenating form text into command strings. This creates injection, quoting, locale, and date/decimal-conversion risk.
- Header, detail, number-pool, status, request, and log changes generally use separate commands without a shared `SqlTransaction`.
- A single global connection/reader/command is reused by forms. Some error paths call `Close`/`Dispose` on objects that may not have been initialized.
- There is no repository evidence of uniqueness constraints for document numbers or allocation idempotency.
- `NewAdminMod.cleanduplicates` contains destructive duplicate-removal SQL based on maximum row IDs. Do not run it casually or treat it as a substitute for database constraints.

Before changing persistence, capture the production schema and work against a restored test copy. For new writes, prefer parameterization, an explicit transaction, server-side constraints, and a clear retry/idempotency rule.
