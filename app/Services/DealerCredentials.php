<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use Throwable;

/** Sole credential writer. Actor identities are supplied by authenticated controllers. */
final class DealerCredentials
{
    public const SAFE_ACCOUNT='id,agency_dealer_membership_id,normalized_identifier,enabled,must_change_password,temporary_expires_at,auth_version,last_login_at,revoked_at';
    public function __construct(private BaseConnection $db) {}

    public static function username(string $value): string
    {
        $value=strtolower(trim($value));
        if(!preg_match('/\A[a-z0-9][a-z0-9._-]{2,99}\z/D',$value))throw new DomainException('Use 3–100 letters, numbers, dots, underscores or hyphens for the username.');
        return $value;
    }

    public static function strongPassword(string $value): void
    {
        if(strlen($value)<12||strlen($value)>72||!preg_match('/[a-z]/',$value)||!preg_match('/[A-Z]/',$value)||!preg_match('/[0-9]/',$value)||!preg_match('/[^A-Za-z0-9]/',$value))throw new DomainException('Use 12–72 characters with uppercase, lowercase, a number and a symbol.');
    }

    public static function hash(string $password): string
    {
        $hash=password_hash($password,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_BCRYPT);
        if(!is_string($hash))throw new \RuntimeException('Password hashing unavailable.');
        return $hash;
    }

    public static function checkpoint(?array $row): string
    {
        return hash('sha256',$row?$row['id'].':'.$row['auth_version'].':'.$row['enabled'].':'.($row['revoked_at']??''):'new');
    }

    public function membership(int $agency,int $membership,bool $lock=false): array
    {
        $query=(new AgencyPortalDealers($this->db))->currentQuery($agency)->where('m.id',$membership)
            ->select('m.id AS membership_id,m.portal_agency_id,m.agency_erp_link_id,m.erp_source_id,m.erp_agency_id,d.erp_name AS dealer_name,a.agency_name,s.code AS source_code');
        $row=$lock?$this->db->query($query->getCompiledSelect().' FOR UPDATE')->getRowArray():$query->get()->getRowArray();
        if(!$row)throw new DomainException('This current dealer membership is no longer available for this agency.');
        return $row;
    }

    public function status(int $agency,int $membership): array
    {
        $this->membership($agency,$membership);
        $row=$this->db->table('agency_dealer_portal_accounts')->select(self::SAFE_ACCOUNT)->where('agency_dealer_membership_id',$membership)->where('portal_agency_id',$agency)->get()->getRowArray();
        return ['account'=>$row,'checkpoint'=>self::checkpoint($row)];
    }

    public function statuses(int $agency,array $memberships): array
    {
        if(!$memberships)return [];
        if(count($memberships)>50)throw new DomainException('Too many memberships.');
        $out=[];foreach($this->db->table('agency_dealer_portal_accounts')->select(self::SAFE_ACCOUNT)->where('portal_agency_id',$agency)->whereIn('agency_dealer_membership_id',$memberships)->get()->getResultArray() as $row)$out[(int)$row['agency_dealer_membership_id']]=$row;
        return $out;
    }

    private function actor(array $actor,array $member,?array $account): void
    {
        $id=(int)($actor['id']??0);$role=$actor['role']??'';
        if($id<1)throw new DomainException('Authenticated actor required.');
        if($role==='agency'){
            $row=$this->db->query('SELECT portal_agency_id,enabled,must_change_password,auth_version FROM agency_portal_accounts WHERE id=? FOR UPDATE',[$id])->getRowArray();
            if(!$row||(int)$row['portal_agency_id']!==(int)$member['portal_agency_id']||!(int)$row['enabled']||(int)$row['must_change_password']||(int)$row['auth_version']!==($actor['auth_version']??0))throw new DomainException('Your agency authorization changed. Sign in again.');
        }elseif($role==='admin'){
            $row=$this->db->query('SELECT status,must_change_password,auth_version FROM admin_users WHERE id=? FOR UPDATE',[$id])->getRowArray();
            if(!$row||$row['status']!=='active'||(int)$row['must_change_password']||(int)$row['auth_version']!==($actor['auth_version']??0))throw new DomainException('Your Admin authorization changed. Sign in again.');
        }elseif($role==='dealer'){
            if(!$account||(int)$account['id']!==$id||(int)$account['auth_version']!==($actor['auth_version']??0)||!(int)$account['enabled']||$account['revoked_at']!==null)throw new DomainException('Your dealer session changed. Sign in again.');
            if((int)$account['must_change_password']&&(!$account['temporary_expires_at']||$account['temporary_expires_at']<=date('Y-m-d H:i:s')))throw new DomainException('Temporary password expired. Ask your agency to reset it.');
        }else throw new DomainException('Actor role unavailable.');
    }

    /** Explicit audit field allowlist. Never accepts arbitrary before/after payloads. */
    private function audit(string $action,array $actor,array $member,array $after,?array $before): void
    {
        $event=['occurred_at'=>date('Y-m-d H:i:s'),'dealer_account_id'=>$after['id'],
            'agency_dealer_membership_id'=>$member['membership_id'],'portal_agency_id'=>$member['portal_agency_id'],'erp_source_id'=>$member['erp_source_id'],
            'agency_name_snapshot'=>$member['agency_name'],'dealer_name_snapshot'=>$member['dealer_name'],'source_code_snapshot'=>$member['source_code'],
            'username_snapshot'=>$after['normalized_identifier'],'action'=>$action,'outcome'=>'success','actor_role'=>$actor['role'],
            'actor_agency_account_id'=>$actor['role']==='agency'?$actor['id']:null,'actor_admin_id'=>$actor['role']==='admin'?$actor['id']:null,
            'actor_dealer_account_id'=>$actor['role']==='dealer'?$actor['id']:null];
        foreach(['username'=>'normalized_identifier','enabled'=>'enabled','must_change_password'=>'must_change_password','auth_version'=>'auth_version','temporary_expires_at'=>'temporary_expires_at','revoked_at'=>'revoked_at'] as $safe=>$column){$event['before_'.$safe]=$before[$column]??null;$event['after_'.$safe]=$after[$column]??null;}
        if(!$this->db->table('agency_dealer_credential_audit')->insert($event))throw new \RuntimeException('Credential audit failed.');
    }

    /** Returns a freshly generated secret directly once; never persists it beyond hash. */
    public function mutate(int $agency,int $membership,array $actor,string $action,string $checkpoint,array $input=[]): array
    {
        if(!in_array($action,['create','username','reset','enable','disable','revoke','password'],true))throw new DomainException('Unknown credential action.');
        if(($actor['role']??'')==='dealer'&&$action!=='password')throw new DomainException('This action is unavailable.');
        if($action==='password'&&($actor['role']??'')!=='dealer')throw new DomainException('Dealer authentication required.');
        if($action==='revoke'&&($actor['role']??'')!=='admin')throw new DomainException('Only authorized Admin can permanently revoke an account.');
        $temporary=null;
        if(!$this->db->transBegin())throw new DomainException('Credential security storage is unavailable. Please try again later.');
        try{
            // Shared ordering: exact membership/dependencies, dealer account, actor account.
            $member=$this->membership($agency,$membership,true);
            $before=$this->db->query('SELECT * FROM agency_dealer_portal_accounts WHERE agency_dealer_membership_id=? AND portal_agency_id=? FOR UPDATE',[$membership,$agency])->getRowArray();
            $this->actor($actor,$member,$before);
            if(!hash_equals(self::checkpoint($before),$checkpoint))throw new DomainException('Login status changed. Close and reopen before retrying.');
            if($action==='create'&&$before)throw new DomainException('Login already exists for this membership.');
            if($action!=='create'&&!$before)throw new DomainException('Create this dealer login first.');
            if($before&&$before['revoked_at']!==null)throw new DomainException('This login is permanently revoked. It cannot be reset or re-enabled.');
            $now=date('Y-m-d H:i:s');$after=$before??[];$events=[];
            if(in_array($action,['create','reset'],true)){
                $temporary='D!a9'.bin2hex(random_bytes(18));
                $after=array_replace($after,['password_hash'=>self::hash($temporary),'must_change_password'=>1,'temporary_expires_at'=>date('Y-m-d H:i:s',time()+max(300,min(172800,config('DealerPortal')->temporaryLifetime)))]);
                $events=[$action==='create'?'LOGIN_CREATED':'PASSWORD_RESET','TEMPORARY_PASSWORD_ISSUED'];
            }
            if(in_array($action,['create','username'],true)){
                $username=self::username((string)($input['username']??''));
                if(!$before||$username!==$before['normalized_identifier']){
                    $conflict=$this->db->table('agency_dealer_portal_accounts')->select('id')->where('normalized_identifier',$username)->get()->getRowArray();
                    if($conflict)throw new DomainException('This username is unavailable. Choose another.');
                    $after['normalized_identifier']=$username;if($action==='username')$events=['USERNAME_CHANGED'];
                }
            }
            if($action==='create'){
                $after+=['agency_dealer_membership_id'=>$membership,'portal_agency_id'=>$agency,'enabled'=>1,'auth_version'=>1,'revoked_at'=>null,
                    'created_by_admin_id'=>$actor['role']==='admin'?$actor['id']:null,'created_by_agency_account_id'=>$actor['role']==='agency'?$actor['id']:null,'created_at'=>$now];
            }
            if(in_array($action,['enable','disable'],true)){
                $enabled=$action==='enable'?1:0;
                if($enabled && (int)$before['must_change_password']&&(!$before['temporary_expires_at']||$before['temporary_expires_at']<=$now))throw new DomainException('Temporary password expired. Reset it before enabling login.');
                if((int)$before['enabled']!==$enabled){$after['enabled']=$enabled;$events=[$enabled?'ACCOUNT_ENABLED':'ACCOUNT_DISABLED'];}
            }
            if($action==='revoke'){$after['enabled']=0;$after['revoked_at']=$now;$after['revocation_reason']='Permanently revoked by authorized Admin.';$events=['ACCOUNT_REVOKED'];}
            if($action==='password'){
                $current=(string)($input['current_password']??'');$password=(string)($input['password']??'');$confirm=(string)($input['confirmation']??'');
                if(!password_verify($current,$before['password_hash']))throw new DomainException('Enter your current password.');
                self::strongPassword($password);if(!hash_equals($password,$confirm))throw new DomainException('Password confirmation does not match.');
                if(password_verify($password,$before['password_hash']))throw new DomainException('Choose a different new password.');
                $after['password_hash']=self::hash($password);$after['must_change_password']=0;$after['temporary_expires_at']=null;$after['password_changed_at']=$now;$events=['PASSWORD_CHANGED'];
            }
            if($events){
                if($before)$after['auth_version']=(int)$before['auth_version']+1;
                $after['updated_at']=$now;$after['updated_by_admin_id']=$actor['role']==='admin'?$actor['id']:null;
                if($before){$values=$after;unset($values['id']);if(!$this->db->table('agency_dealer_portal_accounts')->where('id',$before['id'])->update($values))throw new \RuntimeException('Credential update failed.');}
                else{if(!$this->db->table('agency_dealer_portal_accounts')->insert($after))throw new \RuntimeException('Credential creation failed.');$after['id']=(int)$this->db->insertID();}
                foreach($events as $event)$this->audit($event,$actor,$member,$after,$before);
            }
            if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('Credential commit failed.');
            $safe=array_intersect_key($after,array_flip(explode(',',self::SAFE_ACCOUNT)));
            return ['account'=>$safe,'checkpoint'=>self::checkpoint($after),'temporary_password'=>$temporary];
        }catch(Throwable $e){
            $temporary=null;$this->db->transRollback();$this->db->resetTransStatus();
            if($e instanceof DomainException)throw $e;
            log_message('error','Dealer credential mutation failed ({type}); transaction rolled back.',['type'=>get_class($e)]);
            throw new DomainException('Credentials could not be saved. Reload login status; username may be unavailable.');
        }
    }
}
