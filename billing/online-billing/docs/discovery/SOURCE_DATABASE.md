# P0-02: Source database observations

Observed 2026-09-17 and rechecked read-only 2026-09-18 via Windows integrated authentication to local SQL Server `SQLEXPRESS`, database `billing`. Metadata and aggregate SELECTs only; no source rows modified, no backup/restore performed. The user has not yet identified this database as a disposable or authoritative production copy. Results apply only to this reachable instance at inspection time; no consistent snapshot/transaction was taken across separate queries.

The database creation date reported by SQL Server is 2026-05-25. It identifies the currently reachable database, not its production provenance, completeness, backup lineage, or approval for migration testing. The database uses SIMPLE recovery, MULTI_USER, read-write state, and has both read-committed snapshot and snapshot isolation disabled. Separate aggregate SELECTs may therefore observe different committed states if an operator is active; acceptance must run on an approved restored copy under a defined consistent-extraction procedure.

## Repeatable capture

From the legacy repository root:

```powershell
& ./online-billing/scripts/Capture-LegacyMetadata.ps1 -Server '.\SQLEXPRESS' -Database billing
sqlcmd -S '.\SQLEXPRESS' -E -d billing -l 5 -t 20 -b -W -s '|' -i online-billing/scripts/Inspect-LegacyIntegrity.sql
```

The capture uses SELECT-only metadata queries, a 5-second lock timeout and 20-second command timeout. It stores timestamped JSON under ignored `online-billing/.local/discovery/`. Definitions are retained locally for review, not printed automatically or added to Git. It captures tables/columns/types/indexes/constraints, procedures and parameters, triggers/views/default/check definitions, approximate row counts and aggregate permission categories. It is not a complete restorable DDL script, a login export, or a backup. Re-run on the agreed restored copy for acceptance.

## Observed structure

- Engine version 10.50.4000.0; compatibility 100; collation SQL_Latin1_General_CP1_CI_AS.
- 25 user tables; 24 primary keys; 8 defaults; 10 stored procedures; no observed foreign keys or DML triggers. Metadata visibility used the current integrated identity.
- Bill/OR header and number-pool primary keys are row IDs (`bt_id`, `or_id`, `b_id`), not printable document numbers. The inspected financial tables have no additional unique indexes enforcing printable numbers.
- Bill/OR numbers and most codes are nullable `varchar(50)`, not enforced ten-digit types. Header/detail amounts are `decimal(18,2)` or `numeric(18,2)`; item quantity is `decimal(18,3)`; rate is `decimal(18,2)`; dates use nullable `datetime`. Customer address/TIN/business style include legacy `text` columns.
- PostgreSQL migration must explicitly preserve source rounding-on-storage, null semantics, case-insensitive comparisons, and time interpretation. Wider target decimals do not justify changing the existing calculation stages.

| Table group | Approximate rows from metadata |
| --- | --- |
| tbl_bill_trans / tbl_item_trans | 235,201 / 375,151 |
| tbl_or_trans / tbl_orbill_trans | 114,373 / 213,342 |
| tbl_bill_no / tbl_or_no | 43,899 / 6,564 |
| tbl_account / tbl_tarrif / tbl_service | 2,146 / 204 / 75 |
| tbl_soa_trans / tbl_soa_item | 19,278 / 69,480 |
| tbl_ymital_trans / tbl_ymital_item | 5,381 / 34,446 |
| tbl_wmital_trans / tbl_wmital_item | 7 / 782 |
| tbl_logs | 100,715 |

## Observed procedure signatures

| Procedure | Parameters | Notes |
| --- | --- | --- |
| BillRep / OrRep | date_from datetime, date_to datetime, employee varchar(50) | Definitions captured locally |
| SunBill / SunOr | date_from datetime=NULL, date_to datetime=NULL, period int=NULL | OR between period/date filtering, active status only; confirms dual caller shapes |
| RTC | bill numeric(18,0) | Previously inferred int in repository docs; metadata is more precise for this instance |
| Statement | soanum int | Definition captured |
| Volume / volume1 | period int | Volume ignores parameter and filters Jan 1-Jun 25, 2015; volume1 filters period |
| Wtrans | transno varchar(10) | Definition captured |
| Ytans | transno numeric(18,0) | Name matching case-insensitive on this source |

All ten procedure definitions were readable. Capturing them does not establish whether they match every operator's deployed server.

## Aggregate integrity findings

| Query finding | Count | Required treatment |
| --- | --- | --- |
| Duplicate invoice number groups | 0 | Still add reviewed uniqueness constraints to target |
| Duplicate OR number groups | 0 | Same |
| Items without matching invoice header | 5 | Classify abandoned drafts vs missing history |
| Allocations without matching receipt header | 2 | Classify incomplete transaction vs missing history |
| Allocations without matching invoice header | 6,136 | Resolve source history/deletion/import provenance; never fabricate invoices |
| Invoice pool entries also present in headers | 14,176 | Reconcile availability before importing numbering register |
| OR pool entries also present in headers | 2,327 | Same |
| Negative allocation balances | 1 | Accounting review |
| F allocations with positive balance | 5,578 | Review cash/withholding/status mismatch; not proof every row is erroneous |
| Invoice status null or not FALSE/TRUE | 2,661 | Classify actual values on restored copy |
| OR status null or not FALSE/TRUE | 1,354 | Same |

### Expanded classification from the same point-in-time inspection

| Area | Observed grouping | Count | Interpretation boundary |
| --- | --- | ---: | --- |
| Bill status | `FALSE` / `TRUE` / null-or-empty | 229,295 / 3,245 / 2,661 | Observed values only; `FALSE`/`TRUE` is not yet an approved web lifecycle map. |
| OR status | `FALSE` / `TRUE` / `N` | 111,872 / 1,147 / 1,354 | `N` accounts for the prior non-`FALSE`/`TRUE` exception count; its business meaning requires source-owner confirmation. |
| Allocation missing bill reference | no matching bill header **and** no matching bill item | 6,136 | These records cannot be made referentially valid by adding a late header from available item history. Preserve source reference and quarantine/classify them during staged import. |
| Allocation missing receipt header | no matching OR header | 2 | Accounting review; do not manufacture receipt parents. |
| Bill number-pool overlap | `FALSE` / `TRUE` bill header | 14,065 / 111 | Number pools are not an authoritative availability register without reconciliation. |
| OR number-pool overlap | `FALSE` / `TRUE` OR header | 2,307 / 20 | Same. |
| Allocation marker/balance | `F` with zero / positive / negative / null balance | 196,909 / 5,578 / 1 / 1,153 | The legacy full/partial indicator and balance must not become the target settlement truth without owner-reviewed equations. |
| Allocation marker/balance | `P` with zero / positive / null balance | 85 / 236 / 2 | Same. |
| Allocation marker/balance | null-or-empty marker with zero / positive / null balance | 423 / 89 / 8,866 | Treat as historical anomaly categories until reviewed. |

The active `Reports/ORMonthSelectionReport.vb` count query filters `or_status='INVALID'`, whereas this local source contains `FALSE`, `TRUE`, and `N` status values. Other report fragments also refer to `VALID`/`INVALID`. This is a report-parity discrepancy, not proof that either filter is correct. It is catalogued in [REPORT_CATALOG.md](REPORT_CATALOG.md) and needs a restored-source result comparison before P4-03.

These are exception categories, not automated repair instructions. Their causes and historical business meaning remain unverified. Queries exclude customer identities and return aggregate counts only. Final checks must also include header/line totals, allocation history, status distribution, date range, collation edge cases, report totals, and full account balance reconciliation on a consistent restore.

## Disposable restore evidence

On 2026-09-20 a pre-existing full backup was located and validated without changing the live `billing` database:

- Backup: `G:\Program Files\Microsoft SQL Server\MSSQL10_50.SQLEXPRESS\MSSQL\Backup\billing5222026.bak`.
- `msdb` backup history identifies database `billing`, full backup type `D`, start `2026-05-22 15:04:23`, finish `2026-05-22 15:05:06`, size `328,089,600` bytes, `is_copy_only=0`, `has_backup_checksums=0`, `is_damaged=0`, and the recorded backup device path.
- `RESTORE VERIFYONLY` reported that the backup set is valid. A checksum verification was not possible because this backup set contains no backup checksum.
- `RESTORE FILELISTONLY` identified logical files `billing` and `billing_log`. The backup was restored with explicit `MOVE` targets to `G:\Program Files\Microsoft SQL Server\MSSQL10_50.SQLEXPRESS\MSSQL\DATA\billing_p0_restore_20260920.mdf` and `billing_p0_restore_20260920_log.ldf` as database `billing_p0_restore_20260920`.
- SQL Server upgraded the restored database from version 655 to the current instance version 661. This is a reproducible disposable copy, but it is not a byte-for-byte preservation of the source engine version.
- `DBCC CHECKDB (billing_p0_restore_20260920) WITH NO_INFOMSGS` completed successfully with no reported consistency errors.
- `Capture-LegacyMetadata.ps1` and `Inspect-LegacyIntegrity.sql` completed against the restored database. The restore has 25 tables, 10 readable procedure definitions, no foreign keys and no DML triggers. The restored aggregate scan found 4 items without a bill header, 2 allocations without a receipt header, 6,136 allocations without a bill header, 14,176 bill-pool overlaps, 2,327 OR-pool overlaps, 1 negative allocation balance, 5,578 `F` allocations with positive balance, 2,661 invalid/null bill statuses and 1,354 invalid OR statuses.

The restored counts differ slightly from the live point-in-time observation (for example, items without a bill header were 4 versus 5), confirming that the live instance and the May backup are not the same snapshot. The backup's database creation date (`2019-03-09`) also differs from the currently reachable database creation date (`2026-05-25`). These differences must be retained in migration provenance; they are not grounds for automatic reconciliation.

## Remaining gate

P0-02 now has reproducible disposable-restore evidence, but it remains pending source-owner approval of backup lineage, restored-copy scope, exception classifications and a consistent extraction/reconciliation procedure. PostgreSQL Docker remains a target sandbox only and is not a substitute for SQL Server source restoration.
