<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateErpCatalog extends Migration
{
    public function up()
    {
        // Binary collation makes only the exact original string 'yes' eligible.
        $this->db->query("CREATE TABLE IF NOT EXISTS erp_heads (
            erp_source_id INT UNSIGNED NOT NULL, erp_head_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL, smp_active TEXT COLLATE utf8mb4_bin NULL,
            present TINYINT NOT NULL DEFAULT 1, PRIMARY KEY(erp_source_id, erp_head_id),
            FOREIGN KEY(erp_source_id) REFERENCES erp_sources(id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("CREATE TABLE IF NOT EXISTS erp_subheads (
            erp_source_id INT UNSIGNED NOT NULL, erp_head_id BIGINT UNSIGNED NOT NULL, erp_subhead_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL, smp_active TEXT COLLATE utf8mb4_bin NULL,
            present TINYINT NOT NULL DEFAULT 1, PRIMARY KEY(erp_source_id, erp_head_id, erp_subhead_id),
            FOREIGN KEY(erp_source_id) REFERENCES erp_sources(id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("CREATE TABLE IF NOT EXISTS portal_projects (
            slug VARCHAR(160) NOT NULL PRIMARY KEY, name VARCHAR(255) NOT NULL,
            published TINYINT NOT NULL DEFAULT 0, payload LONGTEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("CREATE TABLE IF NOT EXISTS erp_project_mappings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, erp_source_id INT UNSIGNED NOT NULL,
            erp_head_id BIGINT UNSIGNED NOT NULL, erp_subhead_id BIGINT UNSIGNED NOT NULL,
            project_slug VARCHAR(160) NOT NULL, is_active TINYINT NOT NULL DEFAULT 1,
            UNIQUE KEY source_head_subhead (erp_source_id, erp_head_id, erp_subhead_id),
            FOREIGN KEY(erp_source_id, erp_head_id, erp_subhead_id) REFERENCES erp_subheads(erp_source_id, erp_head_id, erp_subhead_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
            FOREIGN KEY(project_slug) REFERENCES portal_projects(slug) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("CREATE TABLE IF NOT EXISTS portal_listings (
            id BIGINT UNSIGNED NOT NULL PRIMARY KEY, project_slug VARCHAR(160) NULL,
            mapping_id INT UNSIGNED NULL, status VARCHAR(30) NOT NULL, deal VARCHAR(30) NOT NULL,
            payload LONGTEXT NOT NULL,
            FOREIGN KEY(project_slug) REFERENCES portal_projects(slug) ON DELETE RESTRICT ON UPDATE RESTRICT,
            FOREIGN KEY(mapping_id) REFERENCES erp_project_mappings(id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Independent ownership table: visibility synchronization never writes/deletes ownership.
        $this->db->query("CREATE TABLE IF NOT EXISTS portal_owned_properties (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, erp_source_id INT UNSIGNED NOT NULL,
            erp_property_id BIGINT UNSIGNED NOT NULL, erp_head_id BIGINT UNSIGNED NOT NULL,
            erp_subhead_id BIGINT UNSIGNED NOT NULL, owner_reference VARCHAR(160) NOT NULL,
            payload LONGTEXT NOT NULL, UNIQUE KEY source_property(erp_source_id, erp_property_id),
            FOREIGN KEY(erp_source_id) REFERENCES erp_sources(id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("CREATE TABLE IF NOT EXISTS erp_sync_runs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, erp_source_id INT UNSIGNED NOT NULL,
            completed_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, message VARCHAR(255) NOT NULL,
            FOREIGN KEY(erp_source_id) REFERENCES erp_sources(id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    public function down()
    {
        foreach (['erp_sync_runs','portal_owned_properties','portal_listings','erp_project_mappings','portal_projects','erp_subheads','erp_heads'] as $table) $this->forge->dropTable($table);
    }
}
