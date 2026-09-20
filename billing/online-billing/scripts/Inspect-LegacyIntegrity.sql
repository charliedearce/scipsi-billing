-- SQL Server 2008-compatible, SELECT only. Returns aggregate exception counts, no customer records.
-- Run on a restored copy for final evidence. On an active source these are point-in-time observations.
SET NOCOUNT ON;
SET LOCK_TIMEOUT 5000;
SELECT 'duplicate_bill_number_groups' AS finding, COUNT_BIG(*) AS affected_count
FROM (SELECT bt_bill_num FROM dbo.tbl_bill_trans GROUP BY bt_bill_num HAVING COUNT(*) > 1) d;
SELECT 'duplicate_or_number_groups' AS finding, COUNT_BIG(*) AS affected_count
FROM (SELECT or_num FROM dbo.tbl_or_trans GROUP BY or_num HAVING COUNT(*) > 1) d;
SELECT 'items_without_bill' AS finding, COUNT_BIG(*) AS affected_count FROM dbo.tbl_item_trans i
WHERE NOT EXISTS (SELECT 1 FROM dbo.tbl_bill_trans b WHERE b.bt_bill_num=i.it_bill_num);
SELECT 'allocations_without_receipt' AS finding, COUNT_BIG(*) AS affected_count FROM dbo.tbl_orbill_trans a
WHERE NOT EXISTS (SELECT 1 FROM dbo.tbl_or_trans r WHERE r.or_num=a.ot_or_no);
SELECT 'allocations_without_bill' AS finding, COUNT_BIG(*) AS affected_count FROM dbo.tbl_orbill_trans a
WHERE NOT EXISTS (SELECT 1 FROM dbo.tbl_bill_trans b WHERE b.bt_bill_num=a.ot_bill_no);
SELECT 'available_bill_number_also_issued' AS finding, COUNT_BIG(*) AS affected_count FROM dbo.tbl_bill_no n
WHERE EXISTS (SELECT 1 FROM dbo.tbl_bill_trans b WHERE b.bt_bill_num=n.b_num);
SELECT 'available_or_number_also_issued' AS finding, COUNT_BIG(*) AS affected_count FROM dbo.tbl_or_no n
WHERE EXISTS (SELECT 1 FROM dbo.tbl_or_trans r WHERE r.or_num=n.or_num);
SELECT 'negative_allocation_balances' AS finding, COUNT_BIG(*) AS affected_count FROM dbo.tbl_orbill_trans WHERE ot_balance<0;
SELECT 'full_allocation_with_positive_balance' AS finding, COUNT_BIG(*) AS affected_count FROM dbo.tbl_orbill_trans WHERE ot_indipartial='F' AND ot_balance>0;
SELECT 'invalid_bill_status' AS finding, COUNT_BIG(*) AS affected_count FROM dbo.tbl_bill_trans WHERE bt_status IS NULL OR bt_status NOT IN ('FALSE','TRUE');
SELECT 'invalid_or_status' AS finding, COUNT_BIG(*) AS affected_count FROM dbo.tbl_or_trans WHERE or_status IS NULL OR or_status NOT IN ('FALSE','TRUE');

-- Distribution probes distinguish observed legacy values from target-domain decisions.
-- Do not treat these as approval to normalize, repair, or delete source rows.
SELECT 'bill_status_distribution' AS finding,
       ISNULL(NULLIF(LTRIM(RTRIM(bt_status)), ''), '(NULL/EMPTY)') AS status_value,
       COUNT_BIG(*) AS affected_count
FROM dbo.tbl_bill_trans
GROUP BY ISNULL(NULLIF(LTRIM(RTRIM(bt_status)), ''), '(NULL/EMPTY)')
ORDER BY status_value;

SELECT 'or_status_distribution' AS finding,
       ISNULL(NULLIF(LTRIM(RTRIM(or_status)), ''), '(NULL/EMPTY)') AS status_value,
       COUNT_BIG(*) AS affected_count
FROM dbo.tbl_or_trans
GROUP BY ISNULL(NULLIF(LTRIM(RTRIM(or_status)), ''), '(NULL/EMPTY)')
ORDER BY status_value;

-- Separates a missing bill header from a reference with neither header nor item history.
SELECT 'allocation_bill_reference_class' AS finding,
       classified.reference_class,
       COUNT_BIG(*) AS affected_count
FROM (
    SELECT CASE
               WHEN NOT EXISTS (SELECT 1 FROM dbo.tbl_bill_trans b WHERE b.bt_bill_num = a.ot_bill_no)
                AND NOT EXISTS (SELECT 1 FROM dbo.tbl_item_trans i WHERE i.it_bill_num = a.ot_bill_no) THEN 'no_bill_header_or_item'
               WHEN NOT EXISTS (SELECT 1 FROM dbo.tbl_bill_trans b WHERE b.bt_bill_num = a.ot_bill_no) THEN 'no_bill_header_with_item'
               ELSE 'bill_header_present'
           END AS reference_class
    FROM dbo.tbl_orbill_trans a
) classified
GROUP BY classified.reference_class
ORDER BY classified.reference_class;

-- Number-pool overlap needs status context before a target number register is designed.
SELECT 'bill_pool_overlap_by_header_status' AS finding,
       ISNULL(NULLIF(LTRIM(RTRIM(b.bt_status)), ''), '(NULL/EMPTY)') AS header_status,
       COUNT_BIG(*) AS affected_count
FROM dbo.tbl_bill_no n
INNER JOIN dbo.tbl_bill_trans b ON b.bt_bill_num = n.b_num
GROUP BY ISNULL(NULLIF(LTRIM(RTRIM(b.bt_status)), ''), '(NULL/EMPTY)')
ORDER BY header_status;

SELECT 'or_pool_overlap_by_header_status' AS finding,
       ISNULL(NULLIF(LTRIM(RTRIM(r.or_status)), ''), '(NULL/EMPTY)') AS header_status,
       COUNT_BIG(*) AS affected_count
FROM dbo.tbl_or_no n
INNER JOIN dbo.tbl_or_trans r ON r.or_num = n.or_num
GROUP BY ISNULL(NULLIF(LTRIM(RTRIM(r.or_status)), ''), '(NULL/EMPTY)')
ORDER BY header_status;

-- The legacy F/P marker and balance are not a settled target contract.
SELECT 'allocation_kind_balance_distribution' AS finding,
       COALESCE(NULLIF(LTRIM(RTRIM(CONVERT(varchar(20), ot_indipartial))), ''), '(NULL/EMPTY)') AS allocation_kind,
       CASE
           WHEN ot_balance IS NULL THEN 'NULL'
           WHEN ot_balance < 0 THEN 'NEGATIVE'
           WHEN ot_balance = 0 THEN 'ZERO'
           ELSE 'POSITIVE'
       END AS balance_class,
       COUNT_BIG(*) AS affected_count
FROM dbo.tbl_orbill_trans
GROUP BY COALESCE(NULLIF(LTRIM(RTRIM(CONVERT(varchar(20), ot_indipartial))), ''), '(NULL/EMPTY)'),
         CASE
             WHEN ot_balance IS NULL THEN 'NULL'
             WHEN ot_balance < 0 THEN 'NEGATIVE'
             WHEN ot_balance = 0 THEN 'ZERO'
             ELSE 'POSITIVE'
         END
ORDER BY allocation_kind, balance_class;
