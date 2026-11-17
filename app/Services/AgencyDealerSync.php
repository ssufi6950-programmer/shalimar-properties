<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/** Manual, source-isolated agency dealer reconciliation. No ERP writes. */
final class AgencyDealerSync
{
    private const PAGE_SIZE=200;
    private const MAX_RECORDS=400;
    private const MAX_PAGES=2;
    private const MAX_SECONDS=17;
    private BaseConnection $db;

    public function __construct(?BaseConnection $db=null,private ?ErpAgencyDealerApi $api=null)
    {
        $this->db=$db??db_connect();$this->api??=service('agencyDealerApi');
    }

    private function link(int $portalAgencyId,int $linkId):array
    {
        $row=$this->db->query('SELECT l.*,s.code AS source_code,s.name AS source_name,s.is_active AS source_active,s.base_url AS base_url,s.auth_type AS auth_type,s.credential_ref AS credential_ref,a.portal_active,a.agency_type,a.is_shalimar_agency FROM agency_erp_links l JOIN erp_sources s ON s.id=l.erp_source_id JOIN authorized_agencies a ON a.id=l.portal_agency_id WHERE l.id=? AND l.portal_agency_id=?',[$linkId,$portalAgencyId])->getRowArray();
        if(!$row)throw new RuntimeException('This ERP agency link is no longer available.');
        if(!in_array($row['source_code'],['SGC','ZALMI'],true))throw new RuntimeException('This ERP source is not supported for dealer sync.');
        if(!(int)$row['source_active']||!(int)$row['portal_active']||$row['agency_type']!=='external'||(int)$row['is_shalimar_agency']||!(int)$row['present']||$row['mapping_status']!=='mapped'||$row['erp_authorize']!=='yes'||$row['erp_locked']!=='no')throw new RuntimeException('This source mapping is inactive or not currently authorized; no sync was started.');
        return$row;
    }

    private static function fingerprint(array $link):string
    {
        $keys=['id','portal_agency_id','erp_source_id','erp_agency_id','source_code','source_active','portal_active','agency_type','is_shalimar_agency','present','mapping_status','erp_authorize','erp_locked','base_url','auth_type','credential_ref'];$out=[];foreach($keys as$key)$out[$key]=$link[$key]??null;return hash('sha256',json_encode($out,JSON_THROW_ON_ERROR));
    }

    private function lock(array $link,string $token):void
    {
        $this->db->transBegin();
        try{
            $this->db->query('INSERT IGNORE INTO agency_dealer_sync_state (agency_erp_link_id,portal_agency_id,erp_source_id,erp_agency_id,sync_status,created_at,updated_at) VALUES (?,?,?,?,\'idle\',NOW(),NOW())',[(int)$link['id'],(int)$link['portal_agency_id'],(int)$link['erp_source_id'],(int)$link['erp_agency_id']]);
            $state=$this->db->query('SELECT * FROM agency_dealer_sync_state WHERE agency_erp_link_id=? FOR UPDATE',[(int)$link['id']])->getRowArray();
            if(!$state||($state['sync_status']==='running'&&$state['lock_expires_at']!==null&&strtotime($state['lock_expires_at'])>time()))throw new RuntimeException('A dealer sync is already running for this ERP agency.');
            $this->db->table('agency_dealer_sync_state')->where('id',$state['id'])->update(['portal_agency_id'=>(int)$link['portal_agency_id'],'erp_source_id'=>(int)$link['erp_source_id'],'erp_agency_id'=>(int)$link['erp_agency_id'],'sync_status'=>'running','lock_token'=>$token,'lock_expires_at'=>date('Y-m-d H:i:s',time()+180),'active_snapshot_token'=>null,'snapshot_max_ledger_id'=>null,'next_after_ledger_id'=>0,'snapshot_started_at'=>date('Y-m-d H:i:s'),'last_attempted_at'=>date('Y-m-d H:i:s'),'error_summary'=>null,'updated_at'=>date('Y-m-d H:i:s')]);
            if(!$this->db->transStatus())throw new RuntimeException('The dealer sync lock could not be acquired.');if(!$this->db->transCommit())throw new RuntimeException('The dealer sync lock could not be acquired.');
        }catch(Throwable$e){$this->db->transRollback();$this->db->resetTransStatus();throw$e;}
    }

    private function fail(array $link,string $token,string $message):void
    {
        $this->db->transBegin();try{$this->db->query("UPDATE agency_dealer_sync_state SET sync_status='failed',lock_token=NULL,lock_expires_at=NULL,active_snapshot_token=NULL,error_summary=?,consecutive_failures=consecutive_failures+1,updated_at=NOW() WHERE agency_erp_link_id=? AND lock_token=?",[mb_substr($message,0,500),(int)$link['id'],$token]);if(!$this->db->transStatus())throw new RuntimeException('sync state update failed');$this->db->transCommit();}catch(Throwable$e){$this->db->transRollback();$this->db->resetTransStatus();log_message('error','Agency dealer sync failure state could not be saved ({type}).',['type'=>get_class($e)]);}
    }

    private static function safeFailure(Throwable $error):string
    {
        $message=$error->getMessage();
        if(str_contains(strtolower($message),'credential'))return 'Required ERP credentials are not available on the server.';
        if(preg_match('/\AERP dealer API returned HTTP [1-5][0-9]{2}\.\z/D',$message))return $message;
        $safe=[
            'This ERP agency link is no longer available.','This ERP source is not supported for dealer sync.',
            'This source mapping is inactive or not currently authorized; no sync was started.',
            'The ERP agency is no longer authorized or is locked; existing memberships were retained.',
            'A dealer sync is already running for this ERP agency.','The dealer scan exceeded its time budget; existing memberships were retained.',
            'ERP dealer response did not match the requested source agency.','ERP dealer pagination snapshot changed.',
            'ERP dealer page is incomplete or malformed.','ERP dealer page contains invalid or duplicate identities.',
            'ERP dealer cursor is invalid.','The dealer source exceeded the safe record budget; no memberships were reconciled.',
            'The dealer source exceeded the safe page budget; no memberships were reconciled.',
            'ERP dealer record is malformed or outside the requested agency.','ERP dealer contact fields are malformed.',
            'ERP dealer image reference is malformed.','ERP dealer update timestamp is malformed.',
            'ERP dealer request timed out.','ERP dealer API rejected authentication. Check this source credential configuration.',
            'ERP dealer API could not be reached securely.','ERP dealer API returned invalid JSON.','ERP dealer API response is invalid.',
            'Configure this ERP source base URL.','This ERP host is not approved in server configuration.',
            'The ERP public address override is invalid. Check its server configuration.',
            'The ERP public address override must contain only public IP addresses.','ERP hostname could not be resolved.',
            'Unsafe ERP destination blocked.','The external agency dealer endpoint is not configured.',
            'Secure ERP HTTP client is unavailable.','This ERP source is not configured for authenticated dealer sync.',
            'Invalid dealer page request.',
            'The two complete MyISAM scans did not match; existing memberships were retained.',
            'ERP returned no dealers while current memberships exist; they were retained pending independent confirmation.',
            'The ERP agency mapping changed during sync; results were discarded.','The sync lease expired; results were discarded.',
            'A dealer record could not be saved.','Dealer sync transaction failed.','The ERP dealer response exceeded the permitted size.',
        ];
        return in_array($message,$safe,true)?$message:'The dealer sync failed; previous memberships were retained. Check the portal error log.';
    }

    private function scan(array $link,float $deadline):array
    {
        $after=0;$maximum=null;$all=[];
        for($pageNo=0;$pageNo<self::MAX_PAGES;$pageNo++){
            if(microtime(true)>$deadline)throw new RuntimeException('The dealer scan exceeded its time budget; existing memberships were retained.');
            $payload=$this->api->page($this->source($link),(int)$link['erp_agency_id'],$after,$maximum,self::PAGE_SIZE);
            if(($payload['api_version']??null)!=='v1'||!is_array($payload['agency']??null)||($payload['agency']['erp_agency_id']??null)!==(string)$link['erp_agency_id']||!in_array($payload['agency']['authorize']??null,['yes','no'],true)||!in_array($payload['agency']['locked']??null,['yes','no'],true)||!in_array($payload['agency']['restricted']??null,['yes','no'],true)||!is_array($payload['records']??null)||!array_is_list($payload['records'])||!is_bool($payload['has_more']??null)||!is_string($payload['snapshot_max_id']??null)||!is_array($payload['next_cursor']??null)||($payload['snapshot_consistency']??null)!=='best_effort_nontransactional_myisam')throw new RuntimeException('ERP dealer response did not match the requested source agency.');
            if($payload['agency']['authorize']!=='yes'||$payload['agency']['locked']!=='no')throw new RuntimeException('The ERP agency is no longer authorized or is locked; existing memberships were retained.');
            $bound=$payload['snapshot_max_id'];if(!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',$bound)||(int)$bound>2147483647||($maximum!==null&&$maximum!==$bound))throw new RuntimeException('ERP dealer pagination snapshot changed.');$maximum=$bound;
            $records=$payload['records'];if(count($records)>self::PAGE_SIZE||($payload['has_more']&&count($records)===0))throw new RuntimeException('ERP dealer page is incomplete or malformed.');
            $last=$after;
            foreach($records as$r){$record=$this->validateRecord($r,(string)$link['erp_agency_id']);$id=(int)$record['erp_ledger_id'];if($id<=$last||$id>(int)$maximum||isset($all[(string)$id]))throw new RuntimeException('ERP dealer page contains invalid or duplicate identities.');$last=$id;$all[(string)$id]=$record;}
            $cursor=$payload['next_cursor'];if(($cursor['snapshot_max_id']??null)!==$maximum||!is_string($cursor['after_id']??null)||!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',$cursor['after_id'])||(int)$cursor['after_id']!==$last)throw new RuntimeException('ERP dealer cursor is invalid.');
            if(count($all)>self::MAX_RECORDS)throw new RuntimeException('The dealer source exceeded the safe record budget; no memberships were reconciled.');
            $after=$last;if(!$payload['has_more'])return['maximum'=>(int)$maximum,'records'=>$all];
        }
        throw new RuntimeException('The dealer source exceeded the safe page budget; no memberships were reconciled.');
    }

    private static function source(array$link):array
    {
        return['id'=>(int)$link['erp_source_id'],'code'=>$link['source_code'],'base_url'=>$link['base_url'],'auth_type'=>$link['auth_type'],'credential_ref'=>$link['credential_ref'],'is_active'=>(int)$link['source_active']];
    }

    private function validateRecord($r,string$agency):array
    {
        if(!is_array($r)||!is_string($r['erp_ledger_id']??null)||!preg_match('/\A[1-9][0-9]{0,9}\z/D',$r['erp_ledger_id'])||(int)$r['erp_ledger_id']>2147483647||($r['erp_agency_id']??null)!==$agency||($r['dealer_flag']??null)!=='yes'||!in_array($r['locked_flag']??null,['yes','no'],true)||!in_array($r['sp_agent_flag']??null,['yes','no'],true)||!is_string($r['name']??null))throw new RuntimeException('ERP dealer record is malformed or outside the requested agency.');
        foreach(['mobile','email']as$key)if(!is_string($r[$key]??''))throw new RuntimeException('ERP dealer contact fields are malformed.');
        if(($r['image_reference']??null)!==null&&(!is_string($r['image_reference'])||strlen($r['image_reference'])>255||str_contains($r['image_reference'],'..')||!preg_match('/\A[A-Za-z0-9_-][A-Za-z0-9_. -]*\.(?:jpe?g|png|webp|gif)\z/iD',$r['image_reference'])))throw new RuntimeException('ERP dealer image reference is malformed.');
        $updated=$r['erp_updated_at']??null;if($updated!==null&&(!is_string($updated)||!preg_match('/\A[0-9]{1,10}\z/D',$updated)||(int)$updated>2147483647))throw new RuntimeException('ERP dealer update timestamp is malformed.');
        return['erp_ledger_id'=>$r['erp_ledger_id'],'erp_agency_id'=>$agency,'name'=>mb_substr($r['name'],0,255),'mobile'=>mb_substr((string)($r['mobile']??''),0,100),'email'=>mb_substr((string)($r['email']??''),0,100),'image_reference'=>is_string($r['image_reference']??null)?mb_substr($r['image_reference'],0,255):null,'dealer_flag'=>'yes','locked_flag'=>$r['locked_flag'],'sp_agent_flag'=>$r['sp_agent_flag'],'updated_at'=>$updated===null?null:(int)$updated];
    }

    private static function recordHash(array$records):string{ksort($records,SORT_NUMERIC);return hash('sha256',json_encode($records,JSON_THROW_ON_ERROR));}

    public function synchronize(int$portalAgencyId,int$linkId,int$actor,?array$expectedMapping=null):array
    {
        $link=$this->link($portalAgencyId,$linkId);
        if($expectedMapping!==null)foreach(['portal_agency_id','erp_source_id','erp_agency_id']as$key)if((string)($expectedMapping[$key]??'')!==(string)$link[$key])throw new RuntimeException('The ERP agency mapping changed during sync; results were discarded.');
        $fingerprint=self::fingerprint($link);$token=bin2hex(random_bytes(16));$this->lock($link,$token);
        try{
            $deadline=microtime(true)+self::MAX_SECONDS;$first=$this->scan($link,$deadline);$second=$this->scan($link,$deadline);
            if(!hash_equals(self::recordHash($first['records']),self::recordHash($second['records'])))throw new RuntimeException('The two complete MyISAM scans did not match; existing memberships were retained.');
            if(!$second['records']&&(int)$this->db->query("SELECT COUNT(*) AS n FROM agency_dealer_memberships WHERE agency_erp_link_id=? AND membership_status='current'",[$linkId])->getRow()->n>0)throw new RuntimeException('ERP returned no dealers while current memberships exist; they were retained pending independent confirmation.');
            return$this->persist($link,$fingerprint,$token,$second,$actor);
        }catch(Throwable$e){$safe=self::safeFailure($e);$this->fail($link,$token,$safe);$detail='details omitted';if($e instanceof \ErrorException&&preg_match('/\AUndefined array key ["\x27]([A-Za-z0-9_]{1,80})["\x27]\z/D',$e->getMessage(),$match))$detail='undefined array key '.$match[1];log_message('error','Agency dealer sync failed for ERP link {link} ({type}) at {file}:{line}; {detail}.',['link'=>$linkId,'type'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'detail'=>$detail]);throw new RuntimeException($safe);}
    }

    private function persist(array$link,string$fingerprint,string$token,array$snapshot,int$actor):array
    {
        $this->db->transBegin();
        try{
            $current=$this->link((int)$link['portal_agency_id'],(int)$link['id']);if(!hash_equals($fingerprint,self::fingerprint($current)))throw new RuntimeException('The ERP agency mapping changed during sync; results were discarded.');
            $state=$this->db->query('SELECT * FROM agency_dealer_sync_state WHERE agency_erp_link_id=? FOR UPDATE',[(int)$link['id']])->getRowArray();if(!$state||!hash_equals((string)$state['lock_token'],$token))throw new RuntimeException('The sync lease expired; results were discarded.');
        $now=date('Y-m-d H:i:s');$seen=[];$added=0;$transferred=0;
            foreach($snapshot['records']as$r){$seen[]=(int)$r['erp_ledger_id'];$exists=$this->db->query('SELECT id FROM agency_dealers WHERE erp_source_id=? AND erp_ledger_id=? FOR UPDATE',[(int)$link['erp_source_id'],(int)$r['erp_ledger_id']])->getRowArray();
                $this->db->query("INSERT INTO agency_dealers (erp_source_id,erp_ledger_id,erp_name,erp_mobile,erp_email,erp_image_reference,erp_dealer_flag,erp_locked_flag,erp_staff_agent_flag,erp_updated_at,present,first_discovered_at,last_seen_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,?,?,?,?) ON DUPLICATE KEY UPDATE erp_name=VALUES(erp_name),erp_mobile=VALUES(erp_mobile),erp_email=VALUES(erp_email),erp_image_reference=VALUES(erp_image_reference),erp_dealer_flag=VALUES(erp_dealer_flag),erp_locked_flag=VALUES(erp_locked_flag),erp_staff_agent_flag=VALUES(erp_staff_agent_flag),erp_updated_at=VALUES(erp_updated_at),present=1,last_seen_at=VALUES(last_seen_at),updated_at=VALUES(updated_at)",[(int)$link['erp_source_id'],(int)$r['erp_ledger_id'],$r['name'],$r['mobile'],$r['email'],$r['image_reference'],$r['dealer_flag'],$r['locked_flag'],$r['sp_agent_flag'],$r['updated_at'],$now,$now,$now,$now]);
                $dealer=$this->db->query('SELECT id FROM agency_dealers WHERE erp_source_id=? AND erp_ledger_id=?',[(int)$link['erp_source_id'],(int)$r['erp_ledger_id']])->getRowArray();if(!$dealer)throw new RuntimeException('A dealer record could not be saved.');$dealerId=(int)$dealer['id'];
                $currentMembership=$this->db->query("SELECT * FROM agency_dealer_memberships WHERE agency_dealer_id=? AND membership_status='current' FOR UPDATE",[$dealerId])->getRowArray();
                if($currentMembership&&(int)$currentMembership['agency_erp_link_id']===(int)$link['id']&&(int)$currentMembership['portal_agency_id']===(int)$link['portal_agency_id']&&(int)$currentMembership['erp_source_id']===(int)$link['erp_source_id']&&(int)$currentMembership['erp_agency_id']===(int)$link['erp_agency_id']){$this->db->table('agency_dealer_memberships')->where('id',$currentMembership['id'])->update(['last_seen_at'=>$now,'updated_at'=>$now]);}
                else{
                    if($currentMembership){$this->db->table('agency_dealer_memberships')->where('id',$currentMembership['id'])->update(['membership_status'=>'revoked','revoked_at'=>$now,'revocation_reason'=>'Confirmed source agency transfer during complete sync.','updated_at'=>$now]);$transferred++;}
                    $gen=$this->db->query('SELECT COALESCE(MAX(membership_generation),0)+1 AS generation FROM agency_dealer_memberships WHERE agency_dealer_id=? AND agency_erp_link_id=?',[$dealerId,(int)$link['id']])->getRowArray();$generation=max(1,(int)($gen['generation']??1));
                    $this->db->table('agency_dealer_memberships')->insert(['agency_dealer_id'=>$dealerId,'portal_agency_id'=>(int)$link['portal_agency_id'],'agency_erp_link_id'=>(int)$link['id'],'erp_source_id'=>(int)$link['erp_source_id'],'erp_agency_id'=>(int)$link['erp_agency_id'],'membership_generation'=>$generation,'membership_status'=>'current','first_seen_at'=>$now,'last_seen_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);$added++;
                }
            }
            $old=$this->db->query("SELECT m.id,d.erp_ledger_id FROM agency_dealer_memberships m JOIN agency_dealers d ON d.id=m.agency_dealer_id WHERE m.agency_erp_link_id=? AND m.membership_status='current' FOR UPDATE",[(int)$link['id']])->getResultArray();
            foreach($old as$m)if(!in_array((int)$m['erp_ledger_id'],$seen,true))$this->db->table('agency_dealer_memberships')->where('id',$m['id'])->update(['membership_status'=>'revoked','revoked_at'=>$now,'revocation_reason'=>'Absent from two matching complete ERP dealer scans.','updated_at'=>$now]);
            $this->db->table('agency_dealer_sync_state')->where('id',$state['id'])->update(['sync_status'=>'succeeded','lock_token'=>null,'lock_expires_at'=>null,'active_snapshot_token'=>hash('sha256',json_encode($seen)),'snapshot_max_ledger_id'=>$snapshot['maximum'],'next_after_ledger_id'=>$snapshot['maximum'],'last_succeeded_at'=>$now,'last_complete_at'=>$now,'last_record_count'=>count($seen),'consecutive_failures'=>0,'error_summary'=>null,'updated_at'=>$now]);
            (new AdminAuditService($this->db))->record('agency_dealers.sync_completed','agencies',$actor,'agency_erp_link',(int)$link['id'],null,['portal_agency_id'=>(int)$link['portal_agency_id'],'erp_source_id'=>(int)$link['erp_source_id'],'erp_agency_id'=>(int)$link['erp_agency_id'],'record_count'=>count($seen)]);
            if(!$this->db->transStatus())throw new RuntimeException('Dealer sync transaction failed.');if(!$this->db->transCommit())throw new RuntimeException('Dealer sync transaction failed.');return['count'=>count($seen),'added'=>$added,'transferred'=>$transferred,'revoked'=>count($old)-count(array_intersect(array_column($old,'erp_ledger_id'),$seen)),'source'=>$link['source_code']];
        }catch(Throwable$e){$this->db->transRollback();$this->db->resetTransStatus();throw$e;}
    }
}
