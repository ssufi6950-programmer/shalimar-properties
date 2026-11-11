<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateAuthorizedAgencies extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS authorized_agencies (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, agency_name VARCHAR(255) NOT NULL,
            normalized_name VARCHAR(512) COLLATE utf8mb4_bin NOT NULL,
            portal_active TINYINT NOT NULL DEFAULT 1, phone VARCHAR(100) NOT NULL DEFAULT '',
            email VARCHAR(255) NOT NULL DEFAULT '', city VARCHAR(255) NOT NULL DEFAULT '',
            address TEXT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            INDEX agency_name_match(normalized_name(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("CREATE TABLE IF NOT EXISTS agency_erp_links (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, portal_agency_id BIGINT UNSIGNED NULL,
            erp_source_id INT UNSIGNED NOT NULL, erp_agency_id BIGINT UNSIGNED NOT NULL,
            erp_agency_name VARCHAR(255) NOT NULL, normalized_name VARCHAR(512) COLLATE utf8mb4_bin NOT NULL,
            erp_authorization_status VARCHAR(20) NOT NULL, erp_authorize VARCHAR(100) COLLATE utf8mb4_bin NULL,
            erp_locked VARCHAR(100) COLLATE utf8mb4_bin NULL, erp_restricted VARCHAR(100) COLLATE utf8mb4_bin NULL,
            mapping_status VARCHAR(12) NOT NULL DEFAULT 'unmapped', present TINYINT NOT NULL DEFAULT 1,
            contact_json LONGTEXT NOT NULL, first_discovered_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL,
            mapped_at DATETIME NULL, mapped_by INT UNSIGNED NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE KEY agency_source_identity(erp_source_id,erp_agency_id),
            INDEX pending_agencies(mapping_status,first_discovered_at), INDEX agency_name_match(normalized_name(191)),
            FOREIGN KEY(portal_agency_id) REFERENCES authorized_agencies(id) ON DELETE RESTRICT,
            FOREIGN KEY(erp_source_id) REFERENCES erp_sources(id) ON DELETE RESTRICT,
            FOREIGN KEY(mapped_by) REFERENCES admin_users(id) ON DELETE RESTRICT,
            CHECK ((portal_agency_id IS NULL AND mapping_status='unmapped') OR (portal_agency_id IS NOT NULL AND mapping_status='mapped'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("CREATE TABLE IF NOT EXISTS agency_sync_runs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, erp_source_id INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL, record_count INT UNSIGNED NOT NULL DEFAULT 0,
            message VARCHAR(255) NOT NULL, completed_at DATETIME NOT NULL,
            FOREIGN KEY(erp_source_id) REFERENCES erp_sources(id) ON DELETE RESTRICT,
            INDEX(erp_source_id,completed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        foreach (['portal_agency_id'=>'authorized_agencies','agency_erp_link_id'=>'agency_erp_links'] as $column=>$table) {
            if (!$this->db->fieldExists($column,'portal_listings')) $this->db->query("ALTER TABLE portal_listings ADD `$column` BIGINT UNSIGNED NULL, ADD CONSTRAINT `listing_$column` FOREIGN KEY (`$column`) REFERENCES `$table`(id) ON DELETE RESTRICT");
        }
    }
    public function down()
    {
        foreach (['agency_erp_link_id','portal_agency_id'] as $column) if ($this->db->fieldExists($column,'portal_listings')) $this->db->query("ALTER TABLE portal_listings DROP FOREIGN KEY `listing_$column`, DROP COLUMN `$column`");
        foreach (['agency_sync_runs','agency_erp_links','authorized_agencies'] as $table) $this->forge->dropTable($table,true);
    }
}
