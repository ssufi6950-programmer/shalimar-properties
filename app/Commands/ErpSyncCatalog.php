<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\ErpCatalogSync;
use App\Services\ErpConnectionService;
use App\Models\ErpSourceModel;
class ErpSyncCatalog extends BaseCommand
{
    protected $group='ERP';
    protected $name='erp:sync-catalog';
    protected $description='Synchronize complete Head/Subhead snapshots for one source ID or all active sources.';
    protected $usage='erp:sync-catalog [source-id]';
    public function run(array $params)
    {
        $db=db_connect(); $query=$db->table('erp_sources')->where('is_active',1);
        if (isset($params[0])) {
            if (!ctype_digit($params[0]) || (int)$params[0]<1) throw new \InvalidArgumentException('A positive source ID is required.');
            $query->where('id',(int)$params[0]);
        }
        $sources=$query->get()->getResultArray();$failed=false;
        if (!$sources) throw new \RuntimeException('No matching active sources.');
        $transport=new ErpConnectionService(new ErpSourceModel($db),config('ErpConnections'));
        foreach ($sources as $source) {
            try {
                $result=(new ErpCatalogSync($db))->synchronize((int)$source['id'],fn($s)=>$transport->catalogSnapshot($s));
                CLI::write($source['code'].': synchronized '.$result['heads'].' Heads / '.$result['subheads'].' Subheads.','green');
            } catch (\Throwable $e) { $failed=true; CLI::error($source['code'].': '.$e->getMessage()); }
        }
        if ($failed) exit(EXIT_ERROR);
    }
}
