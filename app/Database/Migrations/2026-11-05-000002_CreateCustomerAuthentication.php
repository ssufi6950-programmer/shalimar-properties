<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateCustomerAuthentication extends Migration
{
 public function up()
 {
  $tables=[
   'portal_customers'=>"id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cnic_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, mobile_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, display_name VARCHAR(120) NOT NULL, verified_mobile TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active', auth_version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE KEY customer_identity(cnic_hash,mobile_hash)",
   'customer_erp_links'=>"id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, portal_customer_id BIGINT UNSIGNED NOT NULL, erp_source_id INT UNSIGNED NOT NULL, erp_customer_id BIGINT UNSIGNED NOT NULL, verified_mobile TEXT NOT NULL, verified_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE KEY source_customer(erp_source_id,erp_customer_id), FOREIGN KEY(portal_customer_id) REFERENCES portal_customers(id) ON DELETE RESTRICT, FOREIGN KEY(erp_source_id) REFERENCES erp_sources(id) ON DELETE RESTRICT ON UPDATE RESTRICT",
   'customer_otp_challenges'=>"id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, cnic_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, mobile_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, otp_hash VARCHAR(255) NOT NULL, payload LONGTEXT NOT NULL, attempts INT UNSIGNED NOT NULL DEFAULT 0, expires_at BIGINT NOT NULL, resend_after BIGINT NOT NULL, consumed_at BIGINT NULL, delivery_state VARCHAR(20) NOT NULL, created_at BIGINT NOT NULL, INDEX pair_expiry(cnic_hash,mobile_hash,expires_at), INDEX(expires_at)",
   'customer_rate_limits'=>"bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, window_start BIGINT NOT NULL, hits INT UNSIGNED NOT NULL DEFAULT 0, INDEX(window_start)",
  ];
  foreach($tables as$table=>$fields)$this->db->query('CREATE TABLE '.$table.' ('.$fields.') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
  $this->db->query('ALTER TABLE portal_listings ADD portal_customer_id BIGINT UNSIGNED NULL, ADD erp_source_id INT UNSIGNED NULL, ADD erp_property_id BIGINT UNSIGNED NULL, ADD FOREIGN KEY(portal_customer_id) REFERENCES portal_customers(id) ON DELETE RESTRICT, ADD FOREIGN KEY(erp_source_id) REFERENCES erp_sources(id) ON DELETE RESTRICT ON UPDATE RESTRICT');
  $this->db->query("ALTER TABLE portal_listings ADD active_erp_property VARCHAR(80) GENERATED ALWAYS AS (CASE WHEN status IN ('pending','pending_approval','live','approved','active','cancellation_requested') AND deal IN ('available','active') AND erp_source_id IS NOT NULL AND erp_property_id IS NOT NULL THEN CONCAT(erp_source_id,':',erp_property_id) ELSE NULL END) STORED, ADD UNIQUE KEY unique_active_erp_property(active_erp_property)");
  $this->db->query('ALTER TABLE portal_owned_properties ADD portal_customer_id BIGINT UNSIGNED NULL, ADD FOREIGN KEY(portal_customer_id) REFERENCES portal_customers(id) ON DELETE RESTRICT');
 }
 public function down()
 {
  // Explicit schema rollback must respect existing property/customer references.
  $this->db->query('ALTER TABLE portal_listings DROP INDEX unique_active_erp_property, DROP COLUMN active_erp_property');
  foreach(['portal_listings'=>['portal_customer_id','erp_source_id'],'portal_owned_properties'=>['portal_customer_id']]as$table=>$columns){
   $keys=$this->db->query('SELECT CONSTRAINT_NAME,COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$table])->getResultArray();
   foreach($keys as$key)if(in_array($key['COLUMN_NAME'],$columns,true))$this->db->query('ALTER TABLE '.$table.' DROP FOREIGN KEY '.$this->db->escapeIdentifiers($key['CONSTRAINT_NAME']));
   foreach($columns as$column)$this->db->query('ALTER TABLE '.$table.' DROP COLUMN '.$column);
  }
  $this->db->query('ALTER TABLE portal_listings DROP COLUMN erp_property_id');
  foreach(['customer_rate_limits','customer_otp_challenges','customer_erp_links','portal_customers']as$table)$this->forge->dropTable($table);
 }
}
