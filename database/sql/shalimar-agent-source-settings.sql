-- Select the SMP PORTAL database in phpMyAdmin and run the entire file.
-- Additive, rerunnable MariaDB 10.4+ SQL. No ERP database changes.
-- Existing live reference types and agency_source_identity were inspected.
-- Creates per-source configuration; retains the existing central settings row
-- and all old columns until dependent application code has been updated.
-- Does not modify any agent, agency mapping, listing, contact, or old setting.

CREATE TABLE IF NOT EXISTS shalimar_agent_source_settings (
    erp_source_id INT UNSIGNED NOT NULL,
    settings_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    erp_agency_id BIGINT UNSIGNED NOT NULL,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    verified_version INT UNSIGNED NOT NULL DEFAULT 0,
    last_synced_at DATETIME NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (erp_source_id),
    KEY agent_source_settings_parent (settings_id),
    KEY agent_source_settings_mapping (erp_source_id, erp_agency_id),
    KEY agent_source_settings_actor (updated_by),
    CONSTRAINT agent_source_settings_source_fk
        FOREIGN KEY (erp_source_id) REFERENCES erp_sources (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT agent_source_settings_parent_fk
        FOREIGN KEY (settings_id) REFERENCES shalimar_agent_settings (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT agent_source_settings_mapping_fk
        FOREIGN KEY (erp_source_id, erp_agency_id)
        REFERENCES agency_erp_links (erp_source_id, erp_agency_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT agent_source_settings_actor_fk
        FOREIGN KEY (updated_by) REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT agent_source_settings_active_check CHECK (is_active IN (0, 1)),
    CONSTRAINT agent_source_settings_version_check
        CHECK (version >= 1 AND verified_version <= version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resolve portal source IDs from their actual codes and confirmed mappings.
-- Do not overwrite a configuration if this file is run a second time.
-- Fresh per-source verification starts at 0, to be confirmed by the future
-- updated sync code. Existing global verified_version is not changed.
INSERT INTO shalimar_agent_source_settings
    (erp_source_id, settings_id, erp_agency_id, is_active, version,
     verified_version, last_synced_at, updated_by, created_at, updated_at)
SELECT s.id, cfg.id, link.erp_agency_id, 1, 1, 0, NULL, NULL, NOW(), NOW()
FROM shalimar_agent_settings cfg
JOIN authorized_agencies central ON central.id = cfg.portal_agency_id
JOIN agency_erp_links link ON link.portal_agency_id = central.id
JOIN erp_sources s ON s.id = link.erp_source_id
WHERE cfg.id = 1
  AND central.agency_type = 'internal'
  AND central.is_shalimar_agency = 1
  AND link.mapping_status = 'mapped'
  AND ((s.code = 'SGC' AND link.erp_agency_id = 193)
       OR (s.code = 'ZALMI' AND link.erp_agency_id = 2))
  AND NOT EXISTS (
      SELECT 1 FROM shalimar_agent_source_settings existing
      WHERE existing.erp_source_id = s.id
  );

-- Expect two rows for the inspected setup: SGC/193 and ZALMI/2, both linked
-- to the existing central portal agency. If either is missing or different,
-- stop and review the confirmed mappings; do not invent or move a mapping.
SELECT source_cfg.erp_source_id, s.code AS erp_code,
       source_cfg.erp_agency_id, cfg.portal_agency_id,
       central.agency_name, source_cfg.is_active,
       source_cfg.version, source_cfg.verified_version
FROM shalimar_agent_source_settings source_cfg
JOIN erp_sources s ON s.id = source_cfg.erp_source_id
JOIN shalimar_agent_settings cfg ON cfg.id = source_cfg.settings_id
LEFT JOIN authorized_agencies central ON central.id = cfg.portal_agency_id
ORDER BY s.code;

SHOW CREATE TABLE shalimar_agent_source_settings;
