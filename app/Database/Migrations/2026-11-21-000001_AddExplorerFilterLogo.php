<?php
declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddExplorerFilterLogo extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('portal_explorer_filters') && !$this->db->fieldExists('logo_reference', 'portal_explorer_filters')) {
            $this->forge->addColumn('portal_explorer_filters', [
                'logo_reference' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null, 'after' => 'icon'],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('portal_explorer_filters') && $this->db->fieldExists('logo_reference', 'portal_explorer_filters')) {
            $this->forge->dropColumn('portal_explorer_filters', 'logo_reference');
        }
    }
}
