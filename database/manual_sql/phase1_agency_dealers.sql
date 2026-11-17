-- SMP Portal — Phase 1 agency dealers database setup
-- DRAFT ONLY. User must review and execute manually in phpMyAdmin.
-- Target: MariaDB 10.11, SMP Portal database.
-- This file has NOT been executed.
--
-- Preflight:
-- 1. Back up the SMP Portal database.
-- 2. Confirm the selected database is the SMP Portal database.
-- 3. Confirm existing parent tables and key types match the supplied
--    dealer_schema_reference_READ_ONLY.txt.
-- 4. Check that no same-named tables already exist with different definitions.
--
-- Safe creation order:
--   agency_dealers
--   agency_dealer_memberships
--   agency_dealer_profile_overrides
--   agency_dealer_sync_state
--
-- CREATE TABLE IF NOT EXISTS avoids replacing an existing table, but it does
-- NOT validate or repair the definition of a table that already exists.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agency_dealers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    erp_source_id INT UNSIGNED NOT NULL,
    erp_ledger_id INT NOT NULL,

    erp_name VARCHAR(255) NOT NULL,
    erp_mobile VARCHAR(100) DEFAULT NULL,
    erp_email VARCHAR(100) DEFAULT NULL,
    erp_image_reference VARCHAR(255) DEFAULT NULL,
    erp_dealer_flag ENUM('no','yes') NOT NULL,
    erp_locked_flag ENUM('no','yes') NOT NULL,
    erp_staff_agent_flag ENUM('yes','no') NOT NULL,
    erp_updated_at INT DEFAULT NULL,

    present TINYINT UNSIGNED NOT NULL DEFAULT 1,
    first_discovered_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY agency_dealer_source_identity (erp_source_id, erp_ledger_id),
    UNIQUE KEY agency_dealer_id_source (id, erp_source_id),
    KEY agency_dealer_presence (erp_source_id, present, last_seen_at),
    KEY agency_dealer_name (erp_name),

    CONSTRAINT agency_dealer_source_fk
        FOREIGN KEY (erp_source_id)
        REFERENCES erp_sources (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agency_dealer_memberships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    agency_dealer_id BIGINT UNSIGNED NOT NULL,
    portal_agency_id BIGINT UNSIGNED NOT NULL,
    agency_erp_link_id BIGINT UNSIGNED NOT NULL,
    erp_source_id INT UNSIGNED NOT NULL,
    erp_agency_id BIGINT UNSIGNED NOT NULL,

    membership_generation INT UNSIGNED NOT NULL DEFAULT 1,
    membership_status ENUM('current','revoked') NOT NULL DEFAULT 'current',
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    revocation_reason VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    current_dealer_id BIGINT UNSIGNED
        GENERATED ALWAYS AS (
            CASE WHEN membership_status = 'current' THEN agency_dealer_id ELSE NULL END
        ) STORED,

    PRIMARY KEY (id),
    UNIQUE KEY agency_dealer_membership_generation
        (agency_dealer_id, agency_erp_link_id, membership_generation),
    UNIQUE KEY agency_dealer_one_current_membership (current_dealer_id),
    UNIQUE KEY agency_dealer_membership_agency (id, portal_agency_id),
    KEY agency_dealer_membership_sync
        (agency_erp_link_id, membership_status, last_seen_at),
    KEY agency_dealer_membership_source_agency
        (erp_source_id, erp_agency_id, membership_status),

    CONSTRAINT agency_dealer_membership_identity_fk
        FOREIGN KEY (agency_dealer_id, erp_source_id)
        REFERENCES agency_dealers (id, erp_source_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT agency_dealer_membership_portal_agency_fk
        FOREIGN KEY (portal_agency_id)
        REFERENCES authorized_agencies (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT agency_dealer_membership_erp_link_fk
        FOREIGN KEY (agency_erp_link_id)
        REFERENCES agency_erp_links (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT agency_dealer_membership_source_fk
        FOREIGN KEY (erp_source_id)
        REFERENCES erp_sources (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agency_dealer_profile_overrides (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    agency_dealer_membership_id BIGINT UNSIGNED NOT NULL,

    local_name_override VARCHAR(255) DEFAULT NULL,
    local_mobile_override VARCHAR(100) DEFAULT NULL,
    local_email_override VARCHAR(100) DEFAULT NULL,
    encrypted_cnic VARBINARY(512) DEFAULT NULL,
    cnic_key_version SMALLINT UNSIGNED DEFAULT NULL,

    created_by_admin_id INT UNSIGNED NOT NULL,
    updated_by_admin_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY agency_dealer_profile_membership (agency_dealer_membership_id),
    KEY agency_dealer_profile_updated (updated_at),

    CONSTRAINT agency_dealer_profile_membership_fk
        FOREIGN KEY (agency_dealer_membership_id)
        REFERENCES agency_dealer_memberships (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT agency_dealer_profile_created_by_fk
        FOREIGN KEY (created_by_admin_id)
        REFERENCES admin_users (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT agency_dealer_profile_updated_by_fk
        FOREIGN KEY (updated_by_admin_id)
        REFERENCES admin_users (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT agency_dealer_cnic_ciphertext_version_ck
        CHECK (
            (encrypted_cnic IS NULL AND cnic_key_version IS NULL)
            OR
            (encrypted_cnic IS NOT NULL AND cnic_key_version IS NOT NULL)
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agency_dealer_sync_state (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    agency_erp_link_id BIGINT UNSIGNED NOT NULL,
    portal_agency_id BIGINT UNSIGNED NOT NULL,
    erp_source_id INT UNSIGNED NOT NULL,
    erp_agency_id BIGINT UNSIGNED NOT NULL,

    sync_status ENUM('idle','running','succeeded','failed') NOT NULL DEFAULT 'idle',
    lock_token CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    lock_expires_at DATETIME DEFAULT NULL,
    active_snapshot_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    snapshot_max_ledger_id INT DEFAULT NULL,
    next_after_ledger_id INT DEFAULT NULL,
    snapshot_started_at DATETIME DEFAULT NULL,

    last_attempted_at DATETIME DEFAULT NULL,
    last_succeeded_at DATETIME DEFAULT NULL,
    last_complete_at DATETIME DEFAULT NULL,
    last_record_count INT UNSIGNED NOT NULL DEFAULT 0,
    consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
    error_summary VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY agency_dealer_sync_one_per_link (agency_erp_link_id),
    KEY agency_dealer_sync_lock (sync_status, lock_expires_at),
    KEY agency_dealer_sync_freshness (last_complete_at),
    KEY agency_dealer_sync_source_agency (erp_source_id, erp_agency_id),

    CONSTRAINT agency_dealer_sync_link_fk
        FOREIGN KEY (agency_erp_link_id)
        REFERENCES agency_erp_links (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT agency_dealer_sync_portal_agency_fk
        FOREIGN KEY (portal_agency_id)
        REFERENCES authorized_agencies (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT agency_dealer_sync_source_fk
        FOREIGN KEY (erp_source_id)
        REFERENCES erp_sources (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Post-creation review (read-only; run manually after approving/executing above):
-- SHOW CREATE TABLE agency_dealers;
-- SHOW CREATE TABLE agency_dealer_memberships;
-- SHOW CREATE TABLE agency_dealer_profile_overrides;
-- SHOW CREATE TABLE agency_dealer_sync_state;
