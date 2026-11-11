-- Select the Shalimar Properties PORTAL database in phpMyAdmin, then run this file.
-- Adds one approval flag; existing email/contact/listing records are preserved.
-- Rerunnable. Never marks an email approved and does not touch either ERP database.
SET @smp_email_portal_ready = (
    EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='authorized_agencies')
    AND EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portal_listings')
);
SET @smp_email_column_exists = EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='authorized_agencies' AND COLUMN_NAME='public_email_approved'
);
SET @smp_email_ddl = IF(@smp_email_portal_ready AND NOT @smp_email_column_exists,
    'ALTER TABLE authorized_agencies ADD public_email_approved TINYINT UNSIGNED NOT NULL DEFAULT 0',
    IF(@smp_email_portal_ready,
       'SELECT ''Approval column already exists; no changes made.'' AS result',
       'SELECT ''NOT APPLIED: select the Shalimar Properties portal database first.'' AS result')
);
PREPARE smp_email_patch FROM @smp_email_ddl;
EXECUTE smp_email_patch;
DEALLOCATE PREPARE smp_email_patch;
