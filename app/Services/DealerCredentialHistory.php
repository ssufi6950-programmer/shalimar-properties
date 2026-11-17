<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class DealerCredentialHistory
{
    public const ACTIONS=['LOGIN_CREATED','USERNAME_CHANGED','PASSWORD_RESET','PASSWORD_CHANGED','ACCOUNT_ENABLED','ACCOUNT_DISABLED','ACCOUNT_REVOKED','TEMPORARY_PASSWORD_ISSUED','TEMPORARY_PASSWORD_DISCLOSED'];
    public function __construct(private BaseConnection $db) {}
    public function search(array $input): array
    {
        $query=$this->db->table('agency_dealer_credential_audit e');$filters=[];
        foreach(['agency'=>'portal_agency_id','dealer'=>'agency_dealer_membership_id'] as $name=>$column){$value=$input[$name]??'';$filters[$name]=is_string($value)&&preg_match('/\A[1-9][0-9]{0,17}\z/D',$value)?$value:'';if($filters[$name]!=='')$query->where('e.'.$column,$filters[$name]);}
        foreach(['action'=>self::ACTIONS,'role'=>['agency','admin','dealer','system']] as $name=>$allowed){$filters[$name]=in_array($input[$name]??null,$allowed,true)?$input[$name]:'';if($filters[$name]!=='')$query->where($name==='role'?'e.actor_role':'e.action',$filters[$name]);}
        foreach(['from','to'] as $name){$value=$input[$name]??'';$date=is_string($value)?\DateTimeImmutable::createFromFormat('!Y-m-d',$value):false;$filters[$name]=$date&&$date->format('Y-m-d')===$value?$value:'';if($filters[$name]!=='')$query->where($name==='from'?'e.occurred_at >=':'e.occurred_at <',($name==='from'?$date:$date->modify('+1 day'))->format('Y-m-d H:i:s'));}
        $total=(clone $query)->countAllResults();$pages=max(1,(int)ceil($total/25));$requested=$input['page']??1;$page=min($pages,max(1,is_scalar($requested)?(int)$requested:1));
        $columns=['id','occurred_at','dealer_account_id','agency_dealer_membership_id','portal_agency_id','agency_name_snapshot','dealer_name_snapshot','source_code_snapshot','username_snapshot','action','outcome','failure_code','actor_role','actor_agency_account_id','actor_admin_id','actor_dealer_account_id'];
        foreach(['before','after'] as $prefix)foreach(['username','enabled','must_change_password','auth_version','temporary_expires_at','revoked_at'] as $field)$columns[]=$prefix.'_'.$field;
        $rows=$query->select(implode(',',array_map(static fn($column)=>'e.'.$column,$columns)))->orderBy('e.id','DESC')->limit(25,($page-1)*25)->get()->getResultArray();
        return compact('rows','filters','total','pages','page');
    }
}
