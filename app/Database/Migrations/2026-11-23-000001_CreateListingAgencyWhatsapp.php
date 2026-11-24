<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateListingAgencyWhatsapp extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `listing_agency_whatsapp` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `listing_id` bigint(20) unsigned NOT NULL,
          `listing_version` int(10) unsigned NOT NULL,
          `portal_agency_id` bigint(20) unsigned DEFAULT NULL,
          `template_name` varchar(512) NOT NULL,
          `language_code` varchar(12) NOT NULL,
          `template_var_1` varchar(180) NOT NULL,
          `template_var_2` varchar(220) NOT NULL,
          `status` enum('pending','sending','accepted','failed','uncertain','skipped') NOT NULL DEFAULT 'pending',
          `attempts` int(10) unsigned NOT NULL DEFAULT 0,
          `test_mode` tinyint(1) DEFAULT NULL,
          `recipient_mask` varchar(40) DEFAULT NULL,
          `wamid` varchar(256) DEFAULT NULL,
          `last_error` varchar(60) DEFAULT NULL,
          `created_at` datetime NOT NULL,
          `attempted_at` datetime DEFAULT NULL,
          `accepted_at` datetime DEFAULT NULL,
          `updated_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_agency_wa_listing` (`listing_id`, `listing_version`),
          KEY `idx_agency_wa_wamid` (`wamid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->query($sql);
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS `listing_agency_whatsapp`");
    }
}
