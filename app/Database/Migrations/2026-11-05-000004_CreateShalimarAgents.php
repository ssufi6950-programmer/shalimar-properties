<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateShalimarAgents extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE authorized_agencies ADD agency_type VARCHAR(20) NOT NULL DEFAULT 'external', ADD is_shalimar_agency TINYINT NOT NULL DEFAULT 0");
        $this->db->query("CREATE TABLE shalimar_agent_settings (
            id TINYINT UNSIGNED PRIMARY KEY, sgc_source_id INT UNSIGNED NULL,
            erp_agency_id BIGINT UNSIGNED NOT NULL DEFAULT 193, portal_agency_id BIGINT UNSIGNED NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1, verified_version INT UNSIGNED NOT NULL DEFAULT 0,
            updated_by INT UNSIGNED NULL, updated_at DATETIME NOT NULL,
            FOREIGN KEY(sgc_source_id) REFERENCES erp_sources(id) ON DELETE RESTRICT,
            FOREIGN KEY(portal_agency_id) REFERENCES authorized_agencies(id) ON DELETE RESTRICT,
            FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("INSERT INTO shalimar_agent_settings(id,sgc_source_id,erp_agency_id,updated_at) SELECT 1,IF(COUNT(*)=1,MIN(id),NULL),193,NOW() FROM erp_sources WHERE code='SGC'");
        $this->db->query("CREATE TABLE shalimar_agents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL,
            mobile VARCHAR(150) NOT NULL DEFAULT '', email VARCHAR(255) NOT NULL DEFAULT '',
            portal_active TINYINT NOT NULL DEFAULT 1, contact_approved TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("CREATE TABLE agent_erp_links (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, portal_agent_id BIGINT UNSIGNED NOT NULL,
            erp_source_id INT UNSIGNED NOT NULL, erp_dealer_id BIGINT UNSIGNED NULL,
            erp_ledger_id BIGINT UNSIGNED NOT NULL, erp_agency_id BIGINT UNSIGNED NOT NULL,
            erp_dealer_flag VARCHAR(100) COLLATE utf8mb4_bin NULL,
            erp_locked VARCHAR(100) COLLATE utf8mb4_bin NULL,
            erp_active TINYINT NOT NULL, erp_eligible TINYINT NOT NULL, present TINYINT NOT NULL DEFAULT 1,
            erp_updated_at VARCHAR(100) NULL, first_discovered_at DATETIME NOT NULL, last_synced_at DATETIME NOT NULL,
            UNIQUE KEY source_ledger(erp_source_id,erp_ledger_id), INDEX(portal_agent_id),
            FOREIGN KEY(portal_agent_id) REFERENCES shalimar_agents(id) ON DELETE RESTRICT,
            FOREIGN KEY(erp_source_id) REFERENCES erp_sources(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("CREATE TABLE agent_sync_runs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, erp_source_id INT UNSIGNED NULL,
            erp_agency_id BIGINT UNSIGNED NOT NULL, status VARCHAR(20) NOT NULL,
            record_count INT UNSIGNED NOT NULL DEFAULT 0, message VARCHAR(255) NOT NULL,
            completed_at DATETIME NOT NULL, INDEX(erp_source_id,completed_at),
            FOREIGN KEY(erp_source_id) REFERENCES erp_sources(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("ALTER TABLE portal_listings MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ADD selling_method VARCHAR(30) NULL, ADD portal_agent_id BIGINT UNSIGNED NULL,
            ADD agent_erp_link_id BIGINT UNSIGNED NULL, ADD agent_source_id INT UNSIGNED NULL,
            ADD agent_snapshot LONGTEXT NULL, ADD agent_review_required TINYINT NOT NULL DEFAULT 0,
            ADD agent_review_reason VARCHAR(255) NULL, ADD asking_price DECIMAL(14,0) NULL,
            ADD submission_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
            ADD created_at DATETIME NULL, ADD updated_at DATETIME NULL,
            ADD UNIQUE KEY unique_customer_submission(submission_key),
            ADD CONSTRAINT listing_portal_agent FOREIGN KEY(portal_agent_id) REFERENCES shalimar_agents(id) ON DELETE RESTRICT,
            ADD CONSTRAINT listing_agent_link FOREIGN KEY(agent_erp_link_id) REFERENCES agent_erp_links(id) ON DELETE RESTRICT,
            ADD CONSTRAINT listing_agent_source FOREIGN KEY(agent_source_id) REFERENCES erp_sources(id) ON DELETE RESTRICT");
        $this->db->resetDataCache();
    }
    public function down()
    {
        $this->db->query('ALTER TABLE portal_listings DROP FOREIGN KEY listing_portal_agent, DROP FOREIGN KEY listing_agent_link, DROP FOREIGN KEY listing_agent_source, DROP INDEX unique_customer_submission');
        foreach(['selling_method','portal_agent_id','agent_erp_link_id','agent_source_id','agent_snapshot','agent_review_required','agent_review_reason','asking_price','submission_key','created_at','updated_at']as$column)$this->db->query('ALTER TABLE portal_listings DROP COLUMN '.$column);
        $this->db->query('ALTER TABLE portal_listings MODIFY id BIGINT UNSIGNED NOT NULL');
        foreach(['agent_sync_runs','agent_erp_links','shalimar_agents','shalimar_agent_settings']as$table)$this->forge->dropTable($table);
        $this->db->query('ALTER TABLE authorized_agencies DROP COLUMN agency_type, DROP COLUMN is_shalimar_agency');
        $this->db->resetDataCache();
    }
}
