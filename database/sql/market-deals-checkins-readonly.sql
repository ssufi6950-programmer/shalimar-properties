-- Read-only market check-in reconciliation. Select the ERP database first.
-- Match this value to the API's existing SMP_API_MARKET_COMPANY_NAME setting.
-- SGC uses Shalimar Sales & Marketing; use Zalmi's actual configured name there.
-- Only session variables and SELECTs are used. No ERP records/schema are changed.
SET @smp_sales_company = 'Shalimar Sales & Marketing';
SET @smp_asof = UNIX_TIMESTAMP();
SET @smp_epoch = CAST('1970-01-01 00:00:00' AS DATETIME);
SET @smp_today = DATE(DATE_ADD(@smp_epoch, INTERVAL (@smp_asof+18000) SECOND));
SET @smp_month = CAST(DATE_FORMAT(@smp_today,'%Y-%m-01') AS DATE);
SET @smp_from = TIMESTAMPDIFF(SECOND,@smp_epoch,DATE_SUB(@smp_month,INTERVAL 11 MONTH))-18000;
SET @smp_to = TIMESTAMPDIFF(SECOND,@smp_epoch,DATE_ADD(@smp_month,INTERVAL 1 MONTH))-18000;

-- Same 12-calendar-month reporting window as the API, including today.
-- locked is intentionally not a filter: later check-out locks the old check-in.
-- Inventory is matched by property_no, with allowed-company labels only.
SELECT DATE(DATE_ADD(@smp_epoch, INTERVAL (ci.event_at+18000) SECOND)) AS day,
       COALESCE(i.head,0) AS head_id, COALESCE(i.sub_head,0) AS subhead_id,
       COUNT(DISTINCT ci.id) AS check_ins
FROM (
    SELECT c.id,c.property_no,
           CASE
            WHEN TRIM(c.date) REGEXP '^[1-9][0-9]{0,11}$' THEN CAST(TRIM(c.date) AS UNSIGNED)
            WHEN (c.date IS NULL OR TRIM(c.date) IN ('','0'))
                AND TRIM(c.start_date) REGEXP '^(0[1-9]|[12][0-9]|3[01])-(0[1-9]|1[0-2])-[1-9][0-9]{3}$'
                AND CAST(SUBSTRING(TRIM(c.start_date),1,2) AS UNSIGNED)<=DAY(LAST_DAY(STR_TO_DATE(CONCAT('01-',SUBSTRING(TRIM(c.start_date),4)),'%d-%m-%Y')))
                THEN TIMESTAMPDIFF(SECOND,CAST('1970-01-01 00:00:00' AS DATETIME),STR_TO_DATE(TRIM(c.start_date),'%d-%m-%Y'))-18000
            WHEN (c.date IS NULL OR TRIM(c.date) IN ('','0'))
                AND TRIM(c.start_date) REGEXP '^[1-9][0-9]{3}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$'
                AND CAST(SUBSTRING(TRIM(c.start_date),9,2) AS UNSIGNED)<=DAY(LAST_DAY(STR_TO_DATE(CONCAT(SUBSTRING(TRIM(c.start_date),1,7),'-01'),'%Y-%m-%d')))
                THEN TIMESTAMPDIFF(SECOND,CAST('1970-01-01 00:00:00' AS DATETIME),STR_TO_DATE(TRIM(c.start_date),'%Y-%m-%d'))-18000
            ELSE NULL END AS event_at
    FROM m_check_in c JOIN companies scope_company ON scope_company.id=c.company_id
    WHERE BINARY c.status=BINARY 'check-in'
      AND BINARY scope_company.name IN (@smp_sales_company,'Shalimar O & M Services','Smart Business District')
) ci
LEFT JOIN pro_inventory i ON i.id=ci.property_no AND i.company_id IN (
    SELECT id FROM companies WHERE BINARY name IN (@smp_sales_company,'Shalimar O & M Services','Smart Business District')
)
WHERE ci.event_at>0 AND ci.event_at>=@smp_from AND ci.event_at<@smp_to AND ci.event_at<=@smp_asof
GROUP BY day,head_id,subhead_id
ORDER BY day,head_id,subhead_id;
