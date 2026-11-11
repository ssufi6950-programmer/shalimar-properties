<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdminSecurity extends Migration
{
    public function up()
    {
        $tables = [
            'admin_users'=>"id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, username VARCHAR(60) NOT NULL UNIQUE, email VARCHAR(190) NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active', must_change_password TINYINT UNSIGNED NOT NULL DEFAULT 1, auth_version INT UNSIGNED NOT NULL DEFAULT 1, last_login_at DATETIME NULL, last_login_ip VARCHAR(45) NULL, password_changed_at DATETIME NULL, created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX(status)",
            'admin_roles'=>"id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, slug VARCHAR(80) NOT NULL UNIQUE, description VARCHAR(500) NOT NULL DEFAULT '', is_system TINYINT UNSIGNED NOT NULL DEFAULT 0, is_active TINYINT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL",
            'admin_permissions'=>"id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, module VARCHAR(80) NOT NULL, action VARCHAR(80) NOT NULL, permission_key VARCHAR(160) NOT NULL UNIQUE, description VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX(module)",
            'admin_role_permissions'=>"id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, role_id INT UNSIGNED NOT NULL, permission_id INT UNSIGNED NOT NULL, UNIQUE KEY(role_id, permission_id)",
            'admin_user_roles'=>"id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, role_id INT UNSIGNED NOT NULL, UNIQUE KEY(user_id, role_id), INDEX(role_id)",
            'admin_audit_logs'=>"id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_user_id INT UNSIGNED NULL, action VARCHAR(100) NOT NULL, module VARCHAR(80) NOT NULL, target_type VARCHAR(80) NULL, target_id VARCHAR(80) NULL, description VARCHAR(255) NOT NULL, old_values LONGTEXT NULL, new_values LONGTEXT NULL, ip_address VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, request_id VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, INDEX(created_at), INDEX(module, action), INDEX(actor_user_id)",
            'admin_login_attempts'=>"id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ip_hash CHAR(64) NOT NULL, identifier_hash CHAR(64) NOT NULL, created_at DATETIME NOT NULL, INDEX(ip_hash, created_at), INDEX(identifier_hash, created_at), INDEX(created_at)",
        ];
        foreach ($tables as $name=>$fields) $this->db->query('CREATE TABLE '.$this->db->escapeIdentifiers($this->db->prefixTable($name)).' ('.$fields.') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        foreach ([['admin_role_permissions','role_id','admin_roles'],['admin_role_permissions','permission_id','admin_permissions'],['admin_user_roles','user_id','admin_users'],['admin_user_roles','role_id','admin_roles']] as [$table,$column,$parent]) {
            $this->db->query('ALTER TABLE '.$this->db->escapeIdentifiers($this->db->prefixTable($table)).' ADD FOREIGN KEY ('.$column.') REFERENCES '.$this->db->escapeIdentifiers($this->db->prefixTable($parent)).'(id) ON DELETE RESTRICT ON UPDATE RESTRICT');
        }
    }
    public function down()
    {
        foreach (['admin_login_attempts','admin_audit_logs','admin_user_roles','admin_role_permissions','admin_permissions','admin_roles','admin_users'] as $table) $this->forge->dropTable($table);
    }
}
