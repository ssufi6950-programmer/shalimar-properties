-- Select the SGC ERP database in phpMyAdmin, then import/run this entire file.
-- Creates Shalimar Properties + its separate Shalimar agent dealer ledger.
-- Uses real agency/ledger fields. Does not create a login, SMS, opening-balance
-- voucher, project contract, or any customer identity. Does not update old rows.
-- Rerunnable: existing matching records are preserved, including their balances.
-- ledger is MyISAM in the inspected SGC schema; no transactional rollback is claimed.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @smp_agency_name = 'Shalimar Properties';
SET @smp_dealer_name = 'Shalimar agent';
SET @smp_company_name = 'Shalimar Sales & Marketing';
SET @smp_operator_username = 'faisal'; -- Existing ERP operator in the inspected SGC.
SET @smp_city_name = 'Sargodha';

-- Resolve IDs on the selected database, never copy local agency/dealer IDs to live.
SET @smp_company_id = (SELECT IF(COUNT(*)=1,MIN(id),NULL) FROM companies WHERE name=@smp_company_name COLLATE utf8mb4_unicode_ci);
SET @smp_operator_id = (SELECT IF(COUNT(*)=1,MIN(id),NULL) FROM admin WHERE username=@smp_operator_username COLLATE utf8mb4_unicode_ci);
SET @smp_city_id = (SELECT IF(COUNT(*)=1,MIN(id),NULL) FROM cities WHERE name=@smp_city_name COLLATE utf8mb4_unicode_ci);
SET @smp_group_id = (SELECT IF(COUNT(*)=1,MIN(id),NULL) FROM ledger_group WHERE company_id=@smp_company_id AND LOWER(TRIM(name))='debtor');
SET @smp_nature_id = (SELECT IF(COUNT(*)=1,MIN(id),NULL) FROM nature_groups WHERE LOWER(TRIM(name))='assets');
SET @smp_now = UNIX_TIMESTAMP();
SET @smp_lock_name = CONCAT('smp_agency_',LEFT(SHA2(CONCAT(DATABASE(),':shalimar properties'),256),40));
SET @smp_lock_acquired = GET_LOCK(@smp_lock_name,5);

SET @smp_existing_agencies = (SELECT COUNT(*) FROM agency WHERE company_id=@smp_company_id AND LOWER(TRIM(name))=LOWER(@smp_agency_name) COLLATE utf8mb4_unicode_ci);
SET @smp_existing_agency_id = (SELECT IF(COUNT(*)=1,MIN(id),NULL) FROM agency WHERE company_id=@smp_company_id AND LOWER(TRIM(name))=LOWER(@smp_agency_name) COLLATE utf8mb4_unicode_ci);
SET @smp_existing_dealers = (SELECT COUNT(*) FROM ledger WHERE parent_company=@smp_company_id AND LOWER(TRIM(name))=LOWER(@smp_dealer_name) COLLATE utf8mb4_unicode_ci);
SET @smp_ready = IF(
    @smp_lock_acquired=1 AND @smp_company_id IS NOT NULL AND @smp_operator_id IS NOT NULL
    AND @smp_city_id IS NOT NULL AND @smp_group_id IS NOT NULL AND @smp_nature_id IS NOT NULL
    AND @smp_existing_agencies<=1 AND @smp_existing_dealers<=1
    AND (@smp_existing_agencies=0 OR EXISTS (
        SELECT 1 FROM agency WHERE id=@smp_existing_agency_id
        AND authorize='yes' AND locked='no' AND restricted='no'
    ))
    AND (@smp_existing_dealers=0 OR EXISTS (
        SELECT 1 FROM ledger WHERE parent_company=@smp_company_id
        AND LOWER(TRIM(name))=LOWER(@smp_dealer_name) COLLATE utf8mb4_unicode_ci
        AND dealer='yes' AND agency=@smp_existing_agency_id
    )),1,0);

INSERT INTO agency
    (name,type,city,services,cell,cell2,phone,phone2,email,address,news_letter,
     total_project,complete_project,locked,authorize,restricted,category,
     user_id,company_id,created_at,updated_at)
SELECT @smp_agency_name,'type1',CAST(@smp_city_id AS CHAR),'Property resale',
       '','','','','','','no',0,0,'no','yes','no','none',
       @smp_operator_id,@smp_company_id,@smp_now,@smp_now
FROM DUAL WHERE @smp_ready=1 AND @smp_existing_agencies=0;
SET @smp_agency_created = ROW_COUNT();
SET @smp_agency_id = (SELECT IF(COUNT(*)=1,MIN(id),NULL) FROM agency
    WHERE @smp_ready=1 AND company_id=@smp_company_id
    AND LOWER(TRIM(name))=LOWER(@smp_agency_name) COLLATE utf8mb4_unicode_ci);

-- Keep the ERP's SGC{company}{yy}{#####} membership format. Read the existing
-- sequence without backfilling or changing any previous ledger memberships.
SET @smp_member_prefix = CONCAT('SGC',@smp_company_id,DATE_FORMAT(CURRENT_DATE(),'%y'));
SET @smp_member_next = (SELECT COALESCE(MAX(CAST(SUBSTRING(membership_file_no,CHAR_LENGTH(@smp_member_prefix)+1) AS UNSIGNED)),0)+1
    FROM ledger WHERE parent_company=@smp_company_id
    AND membership_file_no REGEXP CONCAT('^',@smp_member_prefix,'[0-9]{5,}$') COLLATE utf8mb4_unicode_ci);
SET @smp_membership = CONCAT(@smp_member_prefix,LPAD(@smp_member_next,GREATEST(5,CHAR_LENGTH(@smp_member_next)),'0'));

INSERT INTO ledger
    (membership_file_no,name,under_group,opening_balance,reconciliation,created_at,
     updated_at,company_id,user_id,balance_type,bank,charges,active_customer,
     dealer,agency,locked,created_by,parent_company,nature_group,ledger_group,
     sub_nature,mobile,id_card,email,address,city,company_name,gender)
SELECT @smp_membership,@smp_dealer_name,@smp_group_id,'0','no',@smp_now,
       @smp_now,CAST(@smp_company_id AS CHAR),@smp_operator_id,'debit','no','no','no',
       'yes',@smp_agency_id,'no',@smp_operator_id,@smp_company_id,@smp_nature_id,0,
       0,'','','','',CAST(@smp_city_id AS CHAR),@smp_agency_name,'other'
FROM DUAL WHERE @smp_ready=1 AND @smp_agency_id IS NOT NULL AND @smp_existing_dealers=0
    AND NOT EXISTS (SELECT 1 FROM ledger WHERE membership_file_no=@smp_membership COLLATE utf8mb4_unicode_ci);
SET @smp_dealer_created = ROW_COUNT();
SET @smp_dealer_id = (SELECT IF(COUNT(*)=1,MIN(id),NULL) FROM ledger
    WHERE @smp_ready=1 AND parent_company=@smp_company_id AND agency=@smp_agency_id
    AND LOWER(TRIM(name))=LOWER(@smp_dealer_name) COLLATE utf8mb4_unicode_ci AND dealer='yes');

SELECT CASE
    WHEN @smp_ready<>1 THEN 'NOT CREATED: missing/ambiguous company, operator, city, group, or conflicting existing names. Check the settings above.'
    WHEN @smp_agency_id IS NULL OR @smp_dealer_id IS NULL THEN 'INCOMPLETE: check SQL errors; correct the problem and rerun this file.'
    ELSE 'OK: agency and linked dealer are ready; existing records were preserved.'
END AS result,
@smp_agency_id AS agency_id,@smp_dealer_id AS dealer_ledger_id,
@smp_agency_created AS agency_inserted,@smp_dealer_created AS dealer_inserted;

SELECT a.id AS agency_id,a.name AS agency_name,a.authorize,a.locked,a.restricted,
       l.id AS dealer_ledger_id,l.name AS dealer_name,l.membership_file_no,l.dealer,
       l.agency AS dealer_agency_id,l.opening_balance,l.active_customer
FROM agency a JOIN ledger l ON l.agency=a.id
WHERE a.id=@smp_agency_id AND l.id=@smp_dealer_id;

SET @smp_lock_released = IF(@smp_lock_acquired=1,RELEASE_LOCK(@smp_lock_name),NULL);
