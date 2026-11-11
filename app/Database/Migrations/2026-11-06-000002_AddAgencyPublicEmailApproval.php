<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddAgencyPublicEmailApproval extends Migration
{
    public function up()
    {
        // The same additive field can be installed through the supplied phpMyAdmin SQL.
        if (!$this->db->fieldExists('public_email_approved','authorized_agencies')) {
        if(!$this->db->fieldExists('public_email_approved','authorized_agencies'))$this->db->query('ALTER TABLE authorized_agencies ADD public_email_approved TINYINT UNSIGNED NOT NULL DEFAULT 0');
        }
    }
    public function down()
    {
        $this->forge->dropColumn('authorized_agencies','public_email_approved');
    }
}
