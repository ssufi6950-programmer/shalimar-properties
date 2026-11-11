<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
class AdminSecurityPrune extends BaseCommand
{
    protected $group='Administration';protected $name='admin:prune-security';
    protected $description='Remove expired login attempts; optionally archive expired audit logs outside the document root.';
    protected $options=['--archive'=>'Absolute private archive file path. Existing files are refused.'];
    public function run(array $params)
    {
        date_default_timezone_set(config('App')->appTimezone);
        $db=db_connect();$db->table('admin_login_attempts')->where('created_at <',date('Y-m-d H:i:s',time()-max(86400,config('AdminSecurity')->loginWindow)))->delete();
        $path=CLI::getOption('archive');
        if($path){
            $parent=realpath(dirname($path));$web=realpath(dirname(rtrim(ROOTPATH,'/\\')));$resolved=$parent?str_replace('\\','/',$parent).'/'.basename($path):'';
            if(!$parent||!preg_match('~^(?:[A-Za-z]:/|/)~',$resolved)||str_starts_with(strtolower($resolved),strtolower(str_replace('\\','/',$web)).'/'))throw new \RuntimeException('Archive must be an absolute path outside the application parent directory/web root.');
            $file=fopen($resolved,'x');if(!$file)throw new \RuntimeException('A new private archive file could not be created.');
            $cutoff=date('Y-m-d H:i:s',time()-max(30,config('AdminSecurity')->auditRetentionDays)*86400);$maxId=0;
            try{foreach($db->table('admin_audit_logs')->where('created_at <',$cutoff)->orderBy('id')->get()->getResultArray()as$row){if(fwrite($file,json_encode($row,JSON_THROW_ON_ERROR).PHP_EOL)===false)throw new \RuntimeException('Archive write failed.');$maxId=max($maxId,(int)$row['id']);}if(!fflush($file))throw new \RuntimeException('Archive flush failed.');}finally{fclose($file);}
            if($maxId)$db->table('admin_audit_logs')->where('id <=',$maxId)->where('created_at <',$cutoff)->delete();
            (new \App\Services\AdminAuditService())->record('audit.archived','security');
        }
        CLI::write('Expired login attempts pruned. Audit logs are retained unless a private archive was requested.');
    }
}
