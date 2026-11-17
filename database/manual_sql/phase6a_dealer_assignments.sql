-- PHASE 6A — DRAFT FOR MANUAL REVIEW. NOT EXECUTED BY CODEX.
-- MariaDB 10.11. Execute only in the SMP portal database after backup/review.
-- Evidence: supplied dealer_schema_reference_READ_ONLY.txt and approved Phase 1
-- agency_dealer_memberships DDL. No remote ERP foreign keys or existing-table ALTERs.
--
-- PREFLIGHT (read-only; user may run manually):
-- SELECT VERSION(), DATABASE();
-- SHOW CREATE TABLE portal_listings;
-- SHOW CREATE TABLE authorized_agencies;
-- SHOW CREATE TABLE agency_erp_links;
-- SHOW CREATE TABLE agency_dealer_memberships;
-- SHOW CREATE TABLE agency_portal_accounts;
-- SHOW CREATE TABLE erp_sources;
-- SHOW TABLES LIKE 'portal_listing_dealer_assignments';
--
-- Confirm referenced IDs have the exact unsigned types below and all parent
-- tables use InnoDB. Membership must have UNIQUE(id,portal_agency_id), named
-- agency_dealer_membership_agency in the Phase 1 DDL. If any differs, STOP.
-- If this table already exists, compare SHOW CREATE TABLE before proceeding.
-- IF NOT EXISTS does NOT validate or upgrade a pre-existing table.

CREATE TABLE IF NOT EXISTS portal_listing_dealer_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    portal_listing_id BIGINT UNSIGNED NOT NULL,
    agency_dealer_membership_id BIGINT UNSIGNED NOT NULL,

    -- Immutable assignment-time identities; never rewrite these on a transfer.
    portal_agency_id BIGINT UNSIGNED NOT NULL,
    agency_erp_link_id BIGINT UNSIGNED NOT NULL,
    erp_source_id INT UNSIGNED NOT NULL,

    assignment_status ENUM('active','revoked') NOT NULL DEFAULT 'active',
    assigned_at DATETIME NOT NULL,
    assigned_by_agency_account_id BIGINT UNSIGNED NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    revoked_by_agency_account_id BIGINT UNSIGNED DEFAULT NULL,
    -- A future reconciliation job may revoke as system, without impersonating an account.
    revocation_actor ENUM('agency','system') DEFAULT NULL,
    revocation_reason VARCHAR(500) DEFAULT NULL,
    record_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    -- Revoked rows yield NULL. MariaDB unique indexes allow multiple NULLs.
    active_portal_listing_id BIGINT UNSIGNED
        GENERATED ALWAYS AS (
            CASE WHEN assignment_status = 'active' THEN portal_listing_id ELSE NULL END
        ) STORED,

    PRIMARY KEY (id),
    UNIQUE KEY listing_dealer_one_active (active_portal_listing_id),
    KEY listing_dealer_history (portal_listing_id, assigned_at, id),
    KEY listing_dealer_member_agency (agency_dealer_membership_id, portal_agency_id),
    KEY listing_dealer_agency_current (portal_agency_id, assignment_status, id),
    KEY listing_dealer_member_current (agency_dealer_membership_id, assignment_status, id),
    KEY listing_dealer_link_current (agency_erp_link_id, assignment_status, id),
    KEY listing_dealer_source (erp_source_id),
    KEY listing_dealer_assigned_actor (assigned_by_agency_account_id),
    KEY listing_dealer_revoked_actor (revoked_by_agency_account_id),

    CONSTRAINT listing_dealer_listing_fk FOREIGN KEY (portal_listing_id)
        REFERENCES portal_listings (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    -- Enforces that the membership belongs to the snapshotted central agency.
    CONSTRAINT listing_dealer_membership_agency_fk
        FOREIGN KEY (agency_dealer_membership_id, portal_agency_id)
        REFERENCES agency_dealer_memberships (id, portal_agency_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT listing_dealer_agency_fk FOREIGN KEY (portal_agency_id)
        REFERENCES authorized_agencies (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT listing_dealer_link_fk FOREIGN KEY (agency_erp_link_id)
        REFERENCES agency_erp_links (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT listing_dealer_source_fk FOREIGN KEY (erp_source_id)
        REFERENCES erp_sources (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT listing_dealer_assigned_account_fk FOREIGN KEY (assigned_by_agency_account_id)
        REFERENCES agency_portal_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT listing_dealer_revoked_account_fk FOREIGN KEY (revoked_by_agency_account_id)
        REFERENCES agency_portal_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT listing_dealer_revocation_check CHECK (
        (assignment_status = 'active' AND revoked_at IS NULL
            AND revoked_by_agency_account_id IS NULL AND revocation_actor IS NULL
            AND revocation_reason IS NULL)
        OR
        (assignment_status = 'revoked' AND revoked_at IS NOT NULL
            AND revoked_at >= assigned_at
            AND revocation_reason IS NOT NULL AND CHAR_LENGTH(TRIM(revocation_reason)) > 0
            AND revocation_actor IS NOT NULL
            AND (
                (revocation_actor = 'agency' AND revoked_by_agency_account_id IS NOT NULL)
                OR (revocation_actor = 'system' AND revoked_by_agency_account_id IS NULL)
            ))
    ),
    CONSTRAINT listing_dealer_version_check CHECK (record_version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No existing rows are backfilled, assigned, revoked, updated or deleted.
-- Optional post-execution manual inspection:
-- SHOW CREATE TABLE portal_listing_dealer_assignments;
