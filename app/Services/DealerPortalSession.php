<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;

final class DealerPortalSession
{
    public function __construct(private BaseConnection $db) {}
    private const KEYS=['dealer_account_id','dealer_auth_version','dealer_authenticated_at','dealer_last_activity'];

    public function user(): ?array
    {
        $session=session();$id=(int)$session->get('dealer_account_id');if(!$id)return null;
        $row=$this->db->table('agency_dealer_portal_accounts')->select(DealerCredentials::SAFE_ACCOUNT.',portal_agency_id')->where('id',$id)->get()->getRowArray();
        $settings=config('DealerPortal');
        if(!$row||!(int)$row['enabled']||$row['revoked_at']!==null||(int)$row['auth_version']!==(int)$session->get('dealer_auth_version')
            ||(int)$session->get('dealer_authenticated_at')<=time()-min(28800,max(300,$settings->maximumLifetime))
            ||(int)$session->get('dealer_last_activity')<=time()-min(1800,max(60,$settings->idleTimeout))
            ||((int)$row['must_change_password']&&(!$row['temporary_expires_at']||$row['temporary_expires_at']<=date('Y-m-d H:i:s')))){$this->logout();return null;}
        try{$member=(new DealerCredentials($this->db))->membership((int)$row['portal_agency_id'],(int)$row['agency_dealer_membership_id']);}
        catch(DomainException $e){$this->logout();return null;}
        $session->set('dealer_last_activity',time());return $row+$member;
    }

    public function attempt(string $identifier,string $password,string $ip): bool
    {
        $key=config('DealerPortal')->rateLimitKey;
        if(strlen($key)<32)throw new DomainException('Dealer sign-in is not configured. Administration must set dealerPortal.rateLimitKey.');
        try{$identifier=DealerCredentials::username($identifier);}catch(DomainException $e){$identifier='invalid:'.hash('sha256',substr($identifier,0,200));}
        $identifierHash=hash_hmac('sha256',"dealer:identifier\0".$identifier,$key);
        $ipHash=hash_hmac('sha256',"dealer:ip\0".$ip,$key);
        $locks=['dealer_i_'.substr($identifierHash,0,40),'dealer_p_'.substr($ipHash,0,40)];sort($locks);$acquired=[];
        try{
            foreach($locks as $lock){if((int)$this->db->query('SELECT GET_LOCK(?,2) AS acquired',[$lock])->getRow('acquired')!==1)return false;$acquired[]=$lock;}
            if(!$this->db->transBegin())throw new DomainException('Dealer sign-in security storage is unavailable.');
            try{
                $since=date('Y-m-d H:i:s',time()-600);
                $blocked=$this->db->table('agency_dealer_login_attempts')->where('identifier_hash',$identifierHash)->where('attempted_at >=',$since)->countAllResults()>=5
                    ||$this->db->table('agency_dealer_login_attempts')->where('ip_hash',$ipHash)->where('attempted_at >=',$since)->countAllResults()>=10;
                $row=$this->db->query('SELECT * FROM agency_dealer_portal_accounts WHERE normalized_identifier=?',[$identifier])->getRowArray();
                // Valid bcrypt dummy; unknown accounts still incur password verification cost.
                $matches=password_verify($password,$row['password_hash']??'$2y$12$obCB3sdXMfVEmwGyhBjaJ.3xBxAPOuleMZEJG8tPtFvlooY.wvn4i');
                $valid=!$blocked&&$row&&$matches&&(int)$row['enabled']&&$row['revoked_at']===null;
                if($valid&&(int)$row['must_change_password']&&(!$row['temporary_expires_at']||$row['temporary_expires_at']<=date('Y-m-d H:i:s')))$valid=false;
                if($valid){
                    try{
                        // Match credential writer ordering: membership first, account second.
                        (new DealerCredentials($this->db))->membership((int)$row['portal_agency_id'],(int)$row['agency_dealer_membership_id'],true);
                        $fresh=$this->db->query('SELECT * FROM agency_dealer_portal_accounts WHERE normalized_identifier=? FOR UPDATE',[$identifier])->getRowArray();
                        $valid=$fresh&&(int)$fresh['id']===(int)$row['id']&&(int)$fresh['agency_dealer_membership_id']===(int)$row['agency_dealer_membership_id']
                            &&(int)$fresh['portal_agency_id']===(int)$row['portal_agency_id']&&hash_equals($row['password_hash'],$fresh['password_hash'])
                            &&(int)$fresh['enabled']&&$fresh['revoked_at']===null
                            &&(!(int)$fresh['must_change_password']||($fresh['temporary_expires_at']&&$fresh['temporary_expires_at']>date('Y-m-d H:i:s')));
                        if($valid)$row=$fresh;
                    }catch(DomainException $e){$valid=false;}
                }
                if(!$valid){if(!$blocked&&!$this->db->table('agency_dealer_login_attempts')->insert(['identifier_hash'=>$identifierHash,'ip_hash'=>$ipHash,'attempted_at'=>date('Y-m-d H:i:s')]))throw new \RuntimeException('Throttle storage failed.');}
                else{if(!$this->db->table('agency_dealer_portal_accounts')->where('id',$row['id'])->update(['last_login_at'=>date('Y-m-d H:i:s')]))throw new \RuntimeException('Login storage failed.');}
                if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('Login security commit failed.');
                if(!$valid)return false;
                session()->regenerate(true);session()->set(['dealer_account_id'=>(int)$row['id'],'dealer_auth_version'=>(int)$row['auth_version'],'dealer_authenticated_at'=>time(),'dealer_last_activity'=>time()]);return true;
            }catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
        }finally{foreach(array_reverse($acquired) as $lock)$this->db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }

    public function logout(): void {session()->remove(self::KEYS);}
}
