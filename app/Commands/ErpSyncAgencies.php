<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ErpSyncAgencies extends BaseCommand
{
    protected $group='ERP';protected $name='erp:sync-agencies';protected $description='Sync all enabled ERP agencies; optionally specify a source code.';
    public function run(array $params)
    {
        $db=db_connect();$query=$db->table('erp_sources')->where('is_active',1);if (isset($params[0])) $query->where('code',$params[0]);
        foreach ($query->get()->getResultArray() as $source) {
            try {$result=(new \App\Services\AgencySync($db))->synchronize((int)$source['id'],fn($s,$after,$max,$limit)=>service('agencyApi')->page($s,$after,$max,$limit));CLI::write($source['code'].': '.$result['authorized'].' authorized agencies; '.$result['unmapped'].' require mapping.','green');}
            catch (\Throwable $e) {CLI::error($source['code'].': agency sync unavailable; previous data retained. Check the deployed agency API.');}
        }
    }
}
