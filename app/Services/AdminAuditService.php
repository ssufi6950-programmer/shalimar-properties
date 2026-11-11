<?php
namespace App\Services;

class AdminAuditService
{
    private static ?string $requestId=null;
    public function __construct(private ?\CodeIgniter\Database\BaseConnection $db=null) {$this->db??=db_connect();}
    public static function safeValues(?array $values): ?array
    {
        if ($values===null) return null;
        $allowed=['name','username','email','status','is_active','role_ids','permission_keys','slug','description','must_change_password','code','base_url','auth_type','credential_ref','connection_status','last_checked_at','permission','reason','portal_agency_id','erp_source_id','erp_agency_id','mapping_status','agency_name','portal_active','normalized_name','record_count','portal_agent_id','agent_erp_link_id','agent_source_id','erp_ledger_id','erp_dealer_id','sgc_source_id','version','verified_version','contact_approved','review_count','selling_method','portal_customer_id'];
        $allowed=array_merge($allowed,['enabled','username_type']);
        $result=[];
        foreach ($values as $key=>$value) {
            if (!in_array($key,$allowed,true)) continue;
            if ($key==='base_url' && is_string($value)) {
                $parts=parse_url($value);
                $value=$parts?($parts['scheme']??'https').'://'.($parts['host']??'').($parts['path']??''):'[redacted]';
            }
            if (is_string($value)) $value=preg_replace('/\b\d{5}-?\d{7}-?\d\b/','[redacted identifier]',mb_substr($value,0,500));
            if (is_array($value)) $value=array_slice(array_values(array_filter($value,fn($v)=>is_int($v)||(is_string($v)&&preg_match('/\A[a-z_]+\.[a-z_]+\z/',$v)))),0,200);
            $result[$key]=$value;
        }
        return $result;
    }
    public function record(string $action,string $module,?int $actor=null,?string $targetType=null,int|string|null $targetId=null,?array $old=null,?array $new=null): void
    {
        $request=service('request');
        self::$requestId??=bin2hex(random_bytes(16));
        $success=$this->db->table('admin_audit_logs')->insert([
            'actor_user_id'=>$actor,'action'=>$action,'module'=>$module,'target_type'=>$targetType,'target_id'=>$targetId,
            'description'=>ucwords(str_replace(['.','_'],' ',$action)),
            'old_values'=>$old===null?null:json_encode(self::safeValues($old),JSON_THROW_ON_ERROR),
            'new_values'=>$new===null?null:json_encode(self::safeValues($new),JSON_THROW_ON_ERROR),
            'ip_address'=>is_cli()?null:$request->getIPAddress(),
            'user_agent'=>is_cli()?'CLI':'sha256:'.hash('sha256',$request->getHeaderLine('User-Agent')),
            'request_id'=>self::$requestId,'created_at'=>date('Y-m-d H:i:s'),
        ]);
        if (!$success) throw new \RuntimeException('Security audit could not be recorded.');
    }
}
