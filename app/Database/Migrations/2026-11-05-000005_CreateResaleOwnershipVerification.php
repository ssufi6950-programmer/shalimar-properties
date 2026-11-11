<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateResaleOwnershipVerification extends Migration
{
 public function up()
 {
  $this->db->query("CREATE TABLE resale_otp_challenges (
   id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
   portal_customer_id BIGINT UNSIGNED NOT NULL,
   session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
   submission_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
   otp_hash VARCHAR(255) NOT NULL,payload LONGTEXT NOT NULL,
   attempts INT UNSIGNED NOT NULL DEFAULT 0,delivery_state VARCHAR(20) NOT NULL,
   expires_at BIGINT NOT NULL,resend_after BIGINT NOT NULL,verified_at BIGINT NULL,
   consumed_at BIGINT NULL,created_at BIGINT NOT NULL,
   FOREIGN KEY(portal_customer_id) REFERENCES portal_customers(id) ON DELETE RESTRICT,
   INDEX resale_challenge_customer(portal_customer_id,submission_key,created_at),INDEX(expires_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $this->db->query('ALTER TABLE portal_listings ADD resale_verification_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, ADD owner_verification LONGTEXT NULL, ADD UNIQUE KEY listing_resale_verification(resale_verification_id), ADD FOREIGN KEY(resale_verification_id) REFERENCES resale_otp_challenges(id) ON DELETE RESTRICT');
 }
 public function down()
 {
  $keys=$this->db->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portal_listings' AND COLUMN_NAME='resale_verification_id' AND REFERENCED_TABLE_NAME='resale_otp_challenges'")->getResultArray();
  foreach($keys as$key)$this->db->query('ALTER TABLE portal_listings DROP FOREIGN KEY '.$this->db->escapeIdentifiers($key['CONSTRAINT_NAME']));
  $this->db->query('ALTER TABLE portal_listings DROP INDEX listing_resale_verification,DROP COLUMN resale_verification_id,DROP COLUMN owner_verification');$this->forge->dropTable('resale_otp_challenges');
 }
}
