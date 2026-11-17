-- PHASE 7B-A — MANUAL SQL DRAFT; NOT EXECUTED.
-- MariaDB 10.11 / SMP database only. Back up and review before manual execution.
-- Evidence: user confirmed applying Phase 7A; compare actual DDL below FIRST.
-- No accounts/credentials created. No existing attribution values overwritten.
--
-- PREFLIGHT: run these read-only checks manually, inspect results, then run
-- the ALTER/CREATE separately. STOP if current schema differs from Phase 7A.
-- SELECT VERSION(), DATABASE();
-- SHOW CREATE TABLE agency_dealer_portal_accounts;
-- SHOW CREATE TABLE agency_dealer_memberships;
-- SHOW CREATE TABLE agency_portal_accounts;
-- SHOW CREATE TABLE authorized_agencies;
-- SHOW CREATE TABLE erp_sources;
-- SHOW CREATE TABLE admin_users;
-- SHOW TABLES LIKE 'agency_dealer_credential_audit';
-- SELECT COUNT(*) AS missing_admin_attribution
-- FROM agency_dealer_portal_accounts
-- WHERE created_by_admin_id IS NULL OR updated_by_admin_id IS NULL;
--
-- Required: InnoDB parents; account/member/agency/agency-account IDs BIGINT
-- UNSIGNED, source/Admin IDs INT UNSIGNED; membership UNIQUE(id,portal_agency_id).
-- Existing created_by_admin_id and updated_by_admin_id FKs/indexes remain intact.
-- Verify missing_admin_attribution=0 for the original Phase 7A shape.
-- If this migration/columns/constraints already exist, DO NOT rerun ALTER.
-- MariaDB DDL auto-commits; the two statements are not one atomic migration.

-- ONE-TIME ALTER. Nullable Admin fields allow truthful Agency/Dealer/System
-- attribution. Existing non-NULL values retain their exact values.
ALTER TABLE agency_dealer_portal_accounts
    MODIFY COLUMN created_by_admin_id INT UNSIGNED NULL,
    MODIFY COLUMN updated_by_admin_id INT UNSIGNED NULL,
    ADD COLUMN created_by_agency_account_id BIGINT UNSIGNED NULL AFTER created_by_admin_id,
    ADD KEY dealer_account_created_agency (created_by_agency_account_id),
    ADD CONSTRAINT dealer_account_created_agency_fk
        FOREIGN KEY (created_by_agency_account_id)
        REFERENCES agency_portal_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT dealer_account_creator_check CHECK (
        (created_by_admin_id IS NOT NULL AND created_by_agency_account_id IS NULL)
        OR (created_by_admin_id IS NULL AND created_by_agency_account_id IS NOT NULL)
    );

-- Dedicated append-only credential-management history. Application must INSERT
-- successful mutation events in the SAME transaction as the account mutation.
-- Failure events are inserted separately after rollback, with safe reason code.
-- Ordinary rejected login attempts belong ONLY to agency_dealer_login_attempts.
CREATE TABLE IF NOT EXISTS agency_dealer_credential_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    occurred_at DATETIME(6) NOT NULL,
    dealer_account_id BIGINT UNSIGNED NULL,
    agency_dealer_membership_id BIGINT UNSIGNED NOT NULL,
    portal_agency_id BIGINT UNSIGNED NOT NULL,
    erp_source_id INT UNSIGNED NOT NULL,
    -- Display snapshots preserve context after names/username change. IDs are keys.
    agency_name_snapshot VARCHAR(255) NOT NULL,
    dealer_name_snapshot VARCHAR(255) NOT NULL,
    source_code_snapshot VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    username_snapshot VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,

    action ENUM(
        'LOGIN_CREATED','USERNAME_CHANGED','PASSWORD_RESET','PASSWORD_CHANGED',
        'ACCOUNT_ENABLED','ACCOUNT_DISABLED','ACCOUNT_REVOKED',
        'TEMPORARY_PASSWORD_ISSUED','TEMPORARY_PASSWORD_DISCLOSED'
    ) NOT NULL,
    outcome ENUM('success','failure') NOT NULL,
    -- Enum-only failure metadata: never copy exception/provider/request content.
    failure_code ENUM(
        'not_authorized','membership_unavailable','mapping_changed',
        'username_unavailable','account_unavailable','validation_failed',
        'concurrent_change','operation_failed'
    ) NULL,
    actor_role ENUM('agency','admin','dealer','system') NOT NULL,
    actor_agency_account_id BIGINT UNSIGNED NULL,
    actor_admin_id INT UNSIGNED NULL,
    actor_dealer_account_id BIGINT UNSIGNED NULL,

    -- Closed safe before/after field list: no free-form JSON, password/hash,
    -- tokens, OTP, session IDs or temporary-password text columns.
    before_username VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
    after_username VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
    before_enabled TINYINT UNSIGNED NULL,
    after_enabled TINYINT UNSIGNED NULL,
    before_must_change_password TINYINT UNSIGNED NULL,
    after_must_change_password TINYINT UNSIGNED NULL,
    before_auth_version INT UNSIGNED NULL,
    after_auth_version INT UNSIGNED NULL,
    before_temporary_expires_at DATETIME NULL,
    after_temporary_expires_at DATETIME NULL,
    before_revoked_at DATETIME NULL,
    after_revoked_at DATETIME NULL,

    PRIMARY KEY (id),
    KEY dealer_credential_audit_time (occurred_at, id),
    KEY dealer_credential_audit_agency (portal_agency_id, occurred_at, id),
    KEY dealer_credential_audit_member_agency (agency_dealer_membership_id, portal_agency_id),
    KEY dealer_credential_audit_member_time (agency_dealer_membership_id, occurred_at, id),
    KEY dealer_credential_audit_account (dealer_account_id, occurred_at, id),
    KEY dealer_credential_audit_source (erp_source_id, occurred_at, id),
    KEY dealer_credential_audit_action (action, outcome, occurred_at, id),
    KEY dealer_credential_audit_agency_actor (actor_agency_account_id, occurred_at, id),
    KEY dealer_credential_audit_admin_actor (actor_admin_id, occurred_at, id),
    KEY dealer_credential_audit_dealer_actor (actor_dealer_account_id, occurred_at, id),

    CONSTRAINT dealer_credential_audit_account_fk FOREIGN KEY (dealer_account_id)
        REFERENCES agency_dealer_portal_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT dealer_credential_audit_member_agency_fk
        FOREIGN KEY (agency_dealer_membership_id, portal_agency_id)
        REFERENCES agency_dealer_memberships (id, portal_agency_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT dealer_credential_audit_agency_fk FOREIGN KEY (portal_agency_id)
        REFERENCES authorized_agencies (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT dealer_credential_audit_source_fk FOREIGN KEY (erp_source_id)
        REFERENCES erp_sources (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT dealer_credential_audit_agency_actor_fk FOREIGN KEY (actor_agency_account_id)
        REFERENCES agency_portal_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT dealer_credential_audit_admin_actor_fk FOREIGN KEY (actor_admin_id)
        REFERENCES admin_users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT dealer_credential_audit_dealer_actor_fk FOREIGN KEY (actor_dealer_account_id)
        REFERENCES agency_dealer_portal_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT dealer_credential_audit_actor_check CHECK (
        (actor_role='agency' AND actor_agency_account_id IS NOT NULL
            AND actor_admin_id IS NULL AND actor_dealer_account_id IS NULL)
        OR (actor_role='admin' AND actor_admin_id IS NOT NULL
            AND actor_agency_account_id IS NULL AND actor_dealer_account_id IS NULL)
        OR (actor_role='dealer' AND actor_dealer_account_id IS NOT NULL
            AND dealer_account_id IS NOT NULL AND actor_dealer_account_id=dealer_account_id
            AND actor_agency_account_id IS NULL AND actor_admin_id IS NULL)
        OR (actor_role='system' AND actor_agency_account_id IS NULL
            AND actor_admin_id IS NULL AND actor_dealer_account_id IS NULL)
    ),
    CONSTRAINT dealer_credential_audit_outcome_check CHECK (
        (outcome='success' AND failure_code IS NULL AND dealer_account_id IS NOT NULL)
        OR (outcome='failure' AND failure_code IS NOT NULL)
    ),
    CONSTRAINT dealer_credential_audit_flags_check CHECK (
        (before_enabled IS NULL OR before_enabled IN (0,1))
        AND (after_enabled IS NULL OR after_enabled IN (0,1))
        AND (before_must_change_password IS NULL OR before_must_change_password IN (0,1))
        AND (after_must_change_password IS NULL OR after_must_change_password IN (0,1))
        AND (before_auth_version IS NULL OR before_auth_version>=1)
        AND (after_auth_version IS NULL OR after_auth_version>=1)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manual postflight; retain output with migration approval:
-- SHOW CREATE TABLE agency_dealer_portal_accounts;
-- SHOW CREATE TABLE agency_dealer_credential_audit;
-- No historical event backfill: prior events cannot be reconstructed reliably.
-- Never disable foreign_key_checks to force this migration through.
