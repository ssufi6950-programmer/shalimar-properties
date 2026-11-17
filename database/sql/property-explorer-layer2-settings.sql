-- SUPERSEDED PROPOSAL: DO NOT EXECUTE FOR THE CURRENT IMPLEMENTATION.
-- Layer 2 now reuses portal_projects.payload JSON; no DDL is required.
-- Retained only as the historical proposal.

-- Property Explorer Layer 2: portal-only presentation settings.
-- Select the PORTAL database in phpMyAdmin before running this file.
-- Do not run against either ERP database.
-- Dependencies, verified locally on MariaDB 10.4.32:
--   portal_projects.slug: VARCHAR(160), utf8mb4_general_ci, PRIMARY KEY
--   erp_heads: PRIMARY KEY (erp_source_id INT UNSIGNED,
--                           erp_head_id BIGINT UNSIGNED)
--   Both parent tables must use InnoDB.
-- No inserts, listing updates, backfill, permission changes or ERP writes.
-- Rerunnable: does not overwrite existing settings. IF NOT EXISTS does not
-- repair an incompatible table that was independently created with this name.

CREATE TABLE IF NOT EXISTS portal_explorer_layer2_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_slug VARCHAR(160)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    erp_source_id INT UNSIGNED NOT NULL,
    erp_head_id BIGINT UNSIGNED NOT NULL,
    source_type ENUM('subhead', 'sector') NOT NULL,
    -- Subhead: canonical original ERP subhead ID as decimal text.
    -- Sector: exact original sector string, including valid string '0'.
    -- Display labels are never used as source identities.
    source_value VARCHAR(255)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    -- Compact index; retains exact UTF-8 source bytes, including trailing spaces.
    source_value_hash BINARY(32)
        GENERATED ALWAYS AS (UNHEX(SHA2(source_value, 256))) STORED,
    is_visible TINYINT UNSIGNED NOT NULL DEFAULT 1,
    display_name VARCHAR(120) NOT NULL,
    icon VARCHAR(64) NOT NULL DEFAULT 'fa-layer-group',
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_explorer_l2_source (
        project_slug, erp_source_id, erp_head_id, source_type, source_value_hash
    ),
    KEY ix_explorer_l2_order (project_slug, display_order, id),
    KEY ix_explorer_l2_head (erp_source_id, erp_head_id),
    CONSTRAINT fk_explorer_l2_project FOREIGN KEY (project_slug)
        REFERENCES portal_projects (slug)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_explorer_l2_head FOREIGN KEY (erp_source_id, erp_head_id)
        REFERENCES erp_heads (erp_source_id, erp_head_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_explorer_l2_value
        CHECK (CHAR_LENGTH(TRIM(source_value)) > 0),
    CONSTRAINT ck_explorer_l2_visible CHECK (is_visible IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
