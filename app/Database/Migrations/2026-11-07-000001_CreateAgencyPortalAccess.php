<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAgencyPortalAccess extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE agency_portal_accounts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            portal_agency_id BIGINT UNSIGNED NOT NULL,
            username VARCHAR(100) NOT NULL,
            username_type VARCHAR(12) NOT NULL,
            normalized_identifier VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            enabled TINYINT UNSIGNED NOT NULL DEFAULT 1,
            must_change_password TINYINT UNSIGNED NOT NULL DEFAULT 1,
            temporary_expires_at DATETIME NULL,
            auth_version INT UNSIGNED NOT NULL DEFAULT 1,
            last_login_at DATETIME NULL,
            created_by INT UNSIGNED NOT NULL,
            updated_by INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY agency_portal_one_account (portal_agency_id),
            UNIQUE KEY agency_portal_login_identifier (normalized_identifier),
            CONSTRAINT agency_account_agency FOREIGN KEY (portal_agency_id) REFERENCES authorized_agencies(id) ON DELETE RESTRICT,
            CONSTRAINT agency_account_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE RESTRICT,
            CONSTRAINT agency_account_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE RESTRICT,
            CHECK (username_type IN ('phone','custom'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->query("CREATE TABLE agency_login_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            identifier_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            attempted_at DATETIME NOT NULL,
            INDEX agency_login_ip_time (ip_hash, attempted_at),
            INDEX agency_login_identifier_time (identifier_hash, attempted_at),
            INDEX agency_login_attempt_time (attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->forge->dropTable('agency_login_attempts', true);
        $this->forge->dropTable('agency_portal_accounts', true);
    }
}
