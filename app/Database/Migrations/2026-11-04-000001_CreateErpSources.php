<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateErpSources extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type'=>'INT','unsigned'=>true,'auto_increment'=>true],
            'name' => ['type'=>'VARCHAR','constraint'=>120],
            'code' => ['type'=>'VARCHAR','constraint'=>30],
            'base_url' => ['type'=>'VARCHAR','constraint'=>2048,'null'=>true],
            'auth_type' => ['type'=>'VARCHAR','constraint'=>20,'default'=>'none'],
            'credential_ref' => ['type'=>'VARCHAR','constraint'=>100,'null'=>true],
            'is_active' => ['type'=>'TINYINT','constraint'=>1,'unsigned'=>true,'default'=>1],
            'connection_status' => ['type'=>'VARCHAR','constraint'=>30,'default'=>'not_configured'],
            'last_checked_at' => ['type'=>'DATETIME','null'=>true],
            'last_sync_at' => ['type'=>'DATETIME','null'=>true],
            'created_at' => ['type'=>'DATETIME','null'=>true],
            'updated_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code', 'erp_sources_code_unique');
        $this->forge->addKey('is_active');
        $this->forge->createTable('erp_sources', true, ['ENGINE'=>'InnoDB']);
    }

    public function down()
    {
        // MySQL rejects this operation if a future module still has a foreign key to this table.
        $this->forge->dropTable('erp_sources');
    }
}
