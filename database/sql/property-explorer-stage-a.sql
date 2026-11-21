-- STAGE A: Admin Filter Builder. Select the SMP PORTAL database in phpMyAdmin.
-- Do not execute against SGC or Zalmi ERP databases.
-- This is a proposal only; no application code currently depends on this table.
-- Dependencies inspected locally: InnoDB portal_projects.slug VARCHAR(160)
-- COLLATE utf8mb4_general_ci; erp_heads PK (erp_source_id INT UNSIGNED,
-- erp_head_id BIGINT UNSIGNED). erp_heads already references erp_sources.
-- Existing audit tables/permissions are reused later, not changed by this SQL.
-- Rerunnable: IF NOT EXISTS preserves any existing table/data. It does not repair
-- an incompatible pre-existing definition; review SHOW CREATE TABLE in that case.
-- No inserts, backfill, ALTER, UPDATE, DELETE, triggers or ERP writes.
-- FINAL SCOPE: Smart City sectors are distinct exact values under the Head
-- resolved from existing project mappings, across ALL its Subheads.
-- No mapped-Subhead restriction and no hardcoded Head ID in application code.
-- Gulberg/Zalmi use their verified project-specific Sub Location mappings.
-- This table stores only admin-selected filter metadata, never inventory.

CREATE TABLE IF NOT EXISTS portal_explorer_filters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_slug VARCHAR(160)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    erp_source_id INT UNSIGNED NOT NULL,
    erp_head_id BIGINT UNSIGNED NOT NULL,
    parent_filter_id BIGINT UNSIGNED NULL DEFAULT NULL,
    -- 0 represents the project root in the unique key only, never a parent row.
    parent_identity BIGINT UNSIGNED
        GENERATED ALWAYS AS (IFNULL(parent_filter_id, 0)) STORED,
    -- Application-controlled allowlist: item_sublocation.id or pro_inventory.sector.
    -- Requests must never supply executable table/column names.
    source_field VARCHAR(64)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    -- Original sublocation ID as canonical decimal text OR exact sector string.
    -- '0' is valid. Do not trim, lowercase or replace this identity with a label.
    source_value VARCHAR(255)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    source_value_hash BINARY(32)
        GENERATED ALWAYS AS (UNHEX(SHA2(CAST(source_value AS BINARY), 256))) STORED,
    source_label VARCHAR(255) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    icon VARCHAR(64) NOT NULL DEFAULT 'fa-layer-group',
    logo_reference VARCHAR(80) NULL DEFAULT NULL,
    is_visible TINYINT UNSIGNED NOT NULL DEFAULT 0,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    record_version INT UNSIGNED NOT NULL DEFAULT 1,
    last_seen_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_explorer_filter_identity (
        project_slug, erp_source_id, erp_head_id,
        source_field, source_value_hash, parent_identity
    ),
    -- Parent and child must share the same portal project, source and Head.
    UNIQUE KEY uq_explorer_filter_parent_scope (
        id, project_slug, erp_source_id, erp_head_id
    ),
    KEY ix_explorer_filter_parent (
        parent_filter_id, project_slug, erp_source_id, erp_head_id
    ),
    KEY ix_explorer_filter_head (erp_source_id, erp_head_id),
    KEY ix_explorer_filter_order (project_slug, display_order, id),
    CONSTRAINT fk_explorer_filter_project FOREIGN KEY (project_slug)
        REFERENCES portal_projects (slug)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_explorer_filter_head FOREIGN KEY (erp_source_id, erp_head_id)
        REFERENCES erp_heads (erp_source_id, erp_head_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_explorer_filter_parent FOREIGN KEY (
        parent_filter_id, project_slug, erp_source_id, erp_head_id
    ) REFERENCES portal_explorer_filters (
        id, project_slug, erp_source_id, erp_head_id
    )
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_explorer_filter_visible CHECK (is_visible IN (0, 1)),
    CONSTRAINT ck_explorer_filter_value CHECK (source_value REGEXP '[^[:space:]]'),
    CONSTRAINT ck_explorer_filter_name CHECK (display_name REGEXP '[^[:space:]]')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Read-only verification after manual execution:
SHOW CREATE TABLE portal_explorer_filters;
