/*
  P0-03/P0-02 read-only consistency inventory.
  Run against the approved disposable SQL Server restore, not the live source:

    sqlcmd -S '.\SQLEXPRESS' -E -d billing_p0_restore_20260920 -i .\scripts\Inspect-RestoredFinancialConsistency.sql

  These counts classify persisted legacy anomalies; they are not repair instructions
  and must not be turned into target-system expectations without owner review.
*/

WITH item_sum AS (
    SELECT
        it_bill_num,
        SUM(ISNULL(it_gross, 0)) AS gross_sum,
        SUM(ISNULL(it_ppa, 0)) AS ppa_sum,
        SUM(ISNULL(it_net, 0)) AS net_sum,
        SUM(ISNULL(it_tax, 0)) AS tax_sum,
        SUM(ISNULL(it_scipsi, 0)) AS scipsi_sum,
        SUM(ISNULL(it_disc, 0)) AS discount_sum
    FROM dbo.tbl_item_trans
    GROUP BY it_bill_num
)
SELECT
    ISNULL(NULLIF(LTRIM(RTRIM(b.bt_status)), ''), '(NULL/EMPTY)') AS bill_status,
    COUNT(*) AS bill_headers,
    SUM(CASE WHEN i.it_bill_num IS NULL THEN 1 ELSE 0 END) AS bills_without_items,
    SUM(CASE WHEN i.it_bill_num IS NOT NULL AND ABS(ISNULL(b.bt_total, 0) - i.gross_sum) > 0.01 THEN 1 ELSE 0 END) AS gross_mismatches,
    SUM(CASE WHEN i.it_bill_num IS NOT NULL AND ABS(ISNULL(b.bt_ppa, 0) - i.ppa_sum) > 0.01 THEN 1 ELSE 0 END) AS ppa_mismatches,
    SUM(CASE WHEN i.it_bill_num IS NOT NULL AND ABS(ISNULL(b.bt_net, 0) - i.net_sum) > 0.01 THEN 1 ELSE 0 END) AS net_mismatches,
    SUM(CASE WHEN i.it_bill_num IS NOT NULL AND ABS(ISNULL(b.bt_vat, 0) - i.tax_sum) > 0.01 THEN 1 ELSE 0 END) AS vat_mismatches,
    SUM(CASE WHEN i.it_bill_num IS NOT NULL AND ABS(ISNULL(b.bt_scipsi, 0) - i.scipsi_sum) > 0.01 THEN 1 ELSE 0 END) AS scipsi_mismatches,
    SUM(CASE WHEN i.it_bill_num IS NOT NULL AND ABS(ISNULL(b.bt_disc, 0) - i.discount_sum) > 0.01 THEN 1 ELSE 0 END) AS discount_mismatches
FROM dbo.tbl_bill_trans AS b
LEFT JOIN item_sum AS i ON i.it_bill_num = b.bt_bill_num
GROUP BY ISNULL(NULLIF(LTRIM(RTRIM(b.bt_status)), ''), '(NULL/EMPTY)')
ORDER BY bill_status;

WITH allocation_sum AS (
    SELECT
        ot_bill_no,
        SUM(ISNULL(ot_bill_vat, 0)) AS vat_sum,
        SUM(ISNULL(ot_scipsi, 0)) AS scipsi_sum
    FROM dbo.tbl_orbill_trans
    GROUP BY ot_bill_no
)
SELECT
    COUNT(*) AS allocation_bill_groups,
    SUM(CASE WHEN a.ot_bill_no IS NULL THEN 1 ELSE 0 END) AS bills_without_allocations,
    SUM(CASE WHEN a.ot_bill_no IS NOT NULL AND ABS(ISNULL(b.bt_vat, 0) - a.vat_sum) > 0.01 THEN 1 ELSE 0 END) AS vat_mismatches,
    SUM(CASE WHEN a.ot_bill_no IS NOT NULL AND ABS(ISNULL(b.bt_scipsi, 0) - a.scipsi_sum) > 0.01 THEN 1 ELSE 0 END) AS scipsi_mismatches
FROM dbo.tbl_bill_trans AS b
LEFT JOIN allocation_sum AS a ON a.ot_bill_no = b.bt_bill_num;
