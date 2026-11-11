<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\PortalCatalogStore;
class ImportLegacyCatalog extends BaseCommand
{
    protected $group='ERP'; protected $name='erp:import-legacy-catalog';
    protected $description='Copy existing public projects/listings into the local database, preserving existing database rows.';
    public function run(array $params)
    {
        $store=PortalCatalogStore::connect();
        foreach (['projects','listings'] as $name) {
            $path=ROOTPATH.'html/data/'.$name.'.json.php';
            if (!is_file($path)) { CLI::write('No legacy '.$name.' file.');continue; }
            $raw=file_get_contents($path);$pos=strpos($raw,"\n");
            if (!str_starts_with($raw,'<?php') || $pos===false) throw new \RuntimeException('Invalid guarded legacy data file.');
            $rows=json_decode(substr($raw,$pos+1),true,128,JSON_THROW_ON_ERROR);
            $store->write($name,$rows,true);
            CLI::write('Imported '.count($rows).' '.$name.'; existing DB rows preserved.');
        }
    }
}
