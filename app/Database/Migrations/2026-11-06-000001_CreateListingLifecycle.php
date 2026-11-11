<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateListingLifecycle extends Migration
{
 public function up()
 {
  $this->db->query('ALTER TABLE portal_listings ADD record_version INT UNSIGNED NOT NULL DEFAULT 1');
  $this->db->query('ALTER TABLE resale_otp_challenges ADD authorization_scope VARCHAR(80) NULL, ADD INDEX resale_authorization_scope(portal_customer_id,authorization_scope)');
  $this->db->query("CREATE TABLE listing_events (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,listing_id BIGINT UNSIGNED NOT NULL,
   portal_customer_id BIGINT UNSIGNED NULL,admin_user_id INT UNSIGNED NULL,
   event VARCHAR(40) NOT NULL,from_status VARCHAR(30) NULL,to_status VARCHAR(30) NOT NULL,
   previous_record LONGTEXT NULL,next_record LONGTEXT NOT NULL,authorization_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
   created_at DATETIME NOT NULL,UNIQUE KEY event_authorization(authorization_id),INDEX listing_history(listing_id,id),
   FOREIGN KEY(listing_id) REFERENCES portal_listings(id) ON DELETE RESTRICT,
   FOREIGN KEY(portal_customer_id) REFERENCES portal_customers(id) ON DELETE RESTRICT,
   FOREIGN KEY(admin_user_id) REFERENCES admin_users(id) ON DELETE RESTRICT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $this->db->query("CREATE TABLE resale_photos (
   id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,portal_customer_id BIGINT UNSIGNED NOT NULL,
   submission_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
   listing_id BIGINT UNSIGNED NULL,mime VARCHAR(20) NOT NULL,extension VARCHAR(5) NOT NULL,
   checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,bytes INT UNSIGNED NOT NULL,width INT UNSIGNED NOT NULL,height INT UNSIGNED NOT NULL,
   created_at BIGINT NOT NULL,expires_at BIGINT NOT NULL,INDEX photo_expiry(listing_id,expires_at),INDEX customer_draft(portal_customer_id,submission_key),
   FOREIGN KEY(portal_customer_id) REFERENCES portal_customers(id) ON DELETE RESTRICT,
   FOREIGN KEY(listing_id) REFERENCES portal_listings(id) ON DELETE RESTRICT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 }
 public function down()
 {
  // Refuse to silently discard production revision/photo history.
  if($this->db->table('listing_events')->countAllResults()||$this->db->table('resale_photos')->countAllResults())throw new \RuntimeException('Archive lifecycle history and photo metadata before explicitly rolling back this migration.');
  $this->forge->dropTable('resale_photos');$this->forge->dropTable('listing_events');$this->db->query('ALTER TABLE resale_otp_challenges DROP INDEX resale_authorization_scope,DROP COLUMN authorization_scope');$this->db->query('ALTER TABLE portal_listings DROP COLUMN record_version');
 }
}
