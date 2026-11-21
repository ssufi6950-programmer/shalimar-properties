-- Run once in the PORTAL database if the manually-created explorer table
-- predates the application migration.
ALTER TABLE portal_explorer_filters
    ADD COLUMN IF NOT EXISTS logo_reference VARCHAR(100) NULL DEFAULT NULL AFTER icon;

SHOW COLUMNS FROM portal_explorer_filters LIKE 'logo_reference';
