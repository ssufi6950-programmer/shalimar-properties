-- PHASE 7A — DRAFT FOR MANUAL APPROVAL. NOT EXECUTED.
-- SMP Portal only; MariaDB 10.11. No ERP references/writes or existing-table ALTERs.
-- One account per exact membership generation. Never repoint accounts on transfer.
-- A small separate attempts table is proposed because unknown usernames and IP
-- throttling cannot be recorded safely in a table of provisioned accounts alone.
-- It does not reuse or mix Agency Portal login attempts.
--
-- Manual preflight (read-only; NOT executed by Codex):
-- SELECT VERSION(), DATABASE();
-- SHOW CREATE TABLE agency_dealer_memberships;
-- SHOW CREATE TABLE agency_dealers;
-- SHOW CREATE TABLE authorized_agencies;
-- SHOW CREATE TABLE admin_users;
-- SHOW TABLES LIKE 'agency_dealer_portal_accounts';
-- SHOW TABLES LIKE 'agency_dealer_login_attempts';
-- Verify InnoDB parents; membership UNIQUE(id,portal_agency_id);
-- membership/agency BIGINT UNSIGNED, admin_users.id INT UNSIGNED.
-- IF NOT EXISTS only avoids replacement; compare existing DDL if table exists.

CREATE TABLE IF NOT EXISTS agency_dealer_portal_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    agency_dealer_membership_id BIGINT UNSIGNED NOT NULL,
    portal_agency_id BIGINT UNSIGNED NOT NULL,

    -- Usernames are provisioned by Admin, never derived automatically from phone/ID.
    -- Application canonicalizes ASCII username to lowercase before lookup/storage.
    normalized_identifier VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
    must_change_password TINYINT UNSIGNED NOT NULL DEFAULT 1,
    temporary_expires_at DATETIME DEFAULT NULL,
    auth_version INT UNSIGNED NOT NULL DEFAULT 1,
    last_login_at DATETIME DEFAULT NULL,
    password_changed_at DATETIME DEFAULT NULL,
    revoked_at DATETIME DEFAULT NULL,
    revocation_reason VARCHAR(500) DEFAULT NULL,
    created_by_admin_id INT UNSIGNED NOT NULL,
    updated_by_admin_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY dealer_account_membership (agency_dealer_membership_id),
    UNIQUE KEY dealer_account_login (normalized_identifier),
    KEY dealer_account_member_agency (agency_dealer_membership_id, portal_agency_id),
    KEY dealer_account_agency_enabled (portal_agency_id, enabled, id),
    KEY dealer_account_created_admin (created_by_admin_id),
    KEY dealer_account_updated_admin (updated_by_admin_id),

    CONSTRAINT dealer_account_membership_agency_fk
        FOREIGN KEY (agency_dealer_membership_id, portal_agency_id)
        REFERENCES agency_dealer_memberships (id, portal_agency_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT dealer_account_agency_fk FOREIGN KEY (portal_agency_id)
        REFERENCES authorized_agencies (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT dealer_account_created_admin_fk FOREIGN KEY (created_by_admin_id)
        REFERENCES admin_users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT dealer_account_updated_admin_fk FOREIGN KEY (updated_by_admin_id)
        REFERENCES admin_users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT dealer_account_flags_check CHECK (
        enabled IN (0,1) AND must_change_password IN (0,1) AND auth_version >= 1
    ),
    CONSTRAINT dealer_account_identifier_check CHECK (
        CHAR_LENGTH(normalized_identifier) BETWEEN 3 AND 100
        AND normalized_identifier REGEXP '^[a-z0-9][a-z0-9._-]*$'
    ),
    -- A revoked account is permanently disabled; reprovision through explicit
    -- Admin authorization. Temporary enable requires a bounded password expiry.
    CONSTRAINT dealer_account_revocation_check CHECK (
        (revoked_at IS NULL AND revocation_reason IS NULL)
        OR (revoked_at IS NOT NULL AND enabled = 0 AND revocation_reason IS NOT NULL
            AND CHAR_LENGTH(TRIM(revocation_reason)) > 0)
    ),
    CONSTRAINT dealer_account_temporary_check CHECK (
        enabled = 0 OR must_change_password = 0 OR temporary_expires_at IS NOT NULL
    ),
    CONSTRAINT dealer_account_hash_check CHECK (CHAR_LENGTH(password_hash) BETWEEN 60 AND 255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proposed minimal security companion: rejected attempts, including unknown users.
-- Store HMAC-SHA256 of canonical identifier/IP using a private purpose-specific
-- server key, never raw phone/username/IP/password or credentials in this table.
-- Account FK intentionally absent: unknown login identifiers have no account.
CREATE TABLE IF NOT EXISTS agency_dealer_login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempted_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY dealer_attempt_identifier (identifier_hash, attempted_at),
    KEY dealer_attempt_ip (ip_hash, attempted_at),
    KEY dealer_attempt_prune (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No accounts, credentials, assignments, or attempt rows are created.
-- No grants, triggers, migrations, backfills, updates or deletes.
-- Manual post-execution inspection:
-- SHOW CREATE TABLE agency_dealer_portal_accounts;
-- SHOW CREATE TABLE agency_dealer_login_attempts;
