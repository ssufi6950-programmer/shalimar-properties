<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
class ErpSyncAgents extends BaseCommand
{
    protected $group='ERP';protected $name='erp:sync-agents';protected $description='Sync SGC internal-agency dealers as cross-ERP Shalimar Agents.';
    public function run(array $params)
    {
        try{$result=(new \App\Services\ShalimarAgentSync(db_connect()))->synchronize(fn($source,$agency,$after,$max,$limit)=>service('dealerApi')->dealers($source,$agency,$after,$max,$limit));CLI::write($result['records'].' Shalimar dealers synchronized; '.$result['flagged'].' assignments flagged for review.','green');}
        catch(\Throwable $e){CLI::error('Shalimar Agent sync failed; previous agents retained. Check Internal Agency settings and the SGC dealers.php API upload.');}
    }
}
