<?php
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class ShalimarAgentSync
{
    public function __construct(private BaseConnection $db){}
    private static function text($value,int $max,bool $nullable=false):?string
    {
        if($nullable&&$value===null)return null;if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||mb_strlen($value)>$max)throw new RuntimeException('Invalid ERP dealer description or status.');return $value;
    }
    private static function compare(string $a,string $b):int{return strlen($a)<=>strlen($b)?:strcmp($a,$b);}
    public static function validatePage(array $data,string $agencyId,string $after,?string $maximum,?int $total,?array $previousAgency=null,int $limit=100):array
    {
        if(($data['api_version']??null)!=='v1'||($data['identity_type']??null)!=='ledger'||!is_array($data['agency']??null)||!is_array($data['dealers']??null)||!array_is_list($data['dealers'])||count($data['dealers'])>$limit||!is_bool($data['has_more']??null)||!is_int($data['total']??null)||$data['total']<0||$data['total']>10000)throw new RuntimeException('Invalid dealer snapshot contract.');
        $agency=$data['agency'];if(ShalimarAgents::identifier($agency['id']??null)!==$agencyId)throw new RuntimeException('ERP returned a different agency.');$proof=['id'=>$agencyId,'name'=>self::text($agency['name']??null,255)];foreach(['authorize','locked','restricted']as$key){if(!array_key_exists($key,$agency))throw new RuntimeException('Missing agency eligibility proof.');$proof[$key]=self::text($agency[$key],100,true);}
        if($previousAgency!==null&&$previousAgency!==$proof)throw new RuntimeException('Agency changed during dealer pagination.');
        $max=ShalimarAgents::identifier($data['snapshot_max_id']??null,true);if(($maximum!==null&&$maximum!==$max)||($total!==null&&$total!==$data['total']))throw new RuntimeException('Dealer snapshot changed during pagination.');
        $rows=[];$last=$after;$agencyEligible=AgencyDirectory::authorization($proof['authorize'],$proof['locked'],$proof['restricted'])==='authorized';
        foreach($data['dealers']as$row){if(!is_array($row)||($row['identity_type']??null)!=='ledger'||!array_key_exists('dealer_id',$row)||$row['dealer_id']!==null)throw new RuntimeException('Expected SGC ledger-backed dealer identity, not an invented dealer ID.');
            $id=ShalimarAgents::identifier($row['ledger_id']??null);if(self::compare($id,$last)<=0||self::compare($id,$max)>0||ShalimarAgents::identifier($row['agency_id']??null)!==$agencyId)throw new RuntimeException('Duplicate, unordered or unrelated ERP dealer.');
            foreach(['dealer','locked']as$key)if(!array_key_exists($key,$row))throw new RuntimeException('Missing dealer status.');$dealer=self::text($row['dealer'],100,true);$locked=self::text($row['locked'],100,true);$active=$dealer==='yes'&&$locked==='no';
            if(!is_bool($row['active']??null)||!is_bool($row['eligible']??null)||$row['active']!==$active||$row['eligible']!==($active&&$agencyEligible))throw new RuntimeException('ERP dealer eligibility proof is inconsistent.');
            $rows[]=['ledger_id'=>$id,'name'=>self::text($row['name']??null,255),'mobile'=>self::text($row['mobile']??'',150),'email'=>self::text($row['email']??'',255),'dealer'=>$dealer,'locked'=>$locked,'active'=>$active,'eligible'=>$active&&$agencyEligible,'updated_at'=>self::text($row['updated_at']??null,100,true)];$last=$id;
        }
        if(ShalimarAgents::identifier($data['next_after_id']??null,true)!==$last||($data['has_more']&&!$rows))throw new RuntimeException('Dealer pagination did not advance.');
        return ['rows'=>$rows,'agency'=>$proof,'maximum'=>$max,'total'=>$data['total'],'after'=>$last,'has_more'=>$data['has_more']];
    }
    private function bindAgency(array $settings,array $proof,?int $actor,string $now):int
    {
        $source=(int)$settings['sgc_source_id'];$agencyId=$proof['id'];$name='Shalimar Properties';$normalized=AgencyDirectory::normalize($name);
        $link=$this->db->query('SELECT * FROM agency_erp_links WHERE erp_source_id=? AND erp_agency_id=? FOR UPDATE',[$source,$agencyId])->getRowArray();$central=$settings['portal_agency_id']?(int)$settings['portal_agency_id']:null;
        if($link&&$link['portal_agency_id']!==null){if($central!==null&&$central!==(int)$link['portal_agency_id'])throw new RuntimeException('The configured ERP agency already has a different confirmed mapping. Existing mapping preserved.');$central=(int)$link['portal_agency_id'];}
        if($central===null){$matches=$this->db->table('authorized_agencies')->where('normalized_name',$normalized)->get()->getResultArray();if(count($matches)>1)throw new RuntimeException('Multiple Shalimar Properties agencies exist. Select the correct central record in Internal Agency configuration.');if($matches)$central=(int)$matches[0]['id'];else{$this->db->table('authorized_agencies')->insert(['agency_name'=>$name,'normalized_name'=>$normalized,'portal_active'=>1,'agency_type'=>'internal','is_shalimar_agency'=>1,'created_at'=>$now,'updated_at'=>$now]);$central=(int)$this->db->insertID();}}
        if(!$this->db->table('authorized_agencies')->where('id',$central)->countAllResults())throw new RuntimeException('Configured central agency no longer exists.');
        $this->db->table('authorized_agencies')->where('is_shalimar_agency',1)->where('id !=',$central)->update(['agency_type'=>'external','is_shalimar_agency'=>0,'updated_at'=>$now]);
        $classification=['agency_type'=>'internal','is_shalimar_agency'=>1,'updated_at'=>$now];if((int)$settings['verified_version']!==(int)$settings['version'])$classification['portal_active']=1;$this->db->table('authorized_agencies')->where('id',$central)->update($classification);
        $values=['erp_agency_name'=>$proof['name'],'normalized_name'=>AgencyDirectory::normalize($proof['name']),'erp_authorization_status'=>AgencyDirectory::authorization($proof['authorize'],$proof['locked'],$proof['restricted']),'erp_authorize'=>$proof['authorize'],'erp_locked'=>$proof['locked'],'erp_restricted'=>$proof['restricted'],'present'=>1,'last_seen_at'=>$now,'updated_at'=>$now];
        if(!$link||$link['portal_agency_id']===null)$values+=['portal_agency_id'=>$central,'mapping_status'=>'mapped','mapped_by'=>$actor,'mapped_at'=>$now];
        if($link)$this->db->table('agency_erp_links')->where('id',$link['id'])->update($values);else{$this->db->table('agency_erp_links')->insert($values+['erp_source_id'=>$source,'erp_agency_id'=>$agencyId,'contact_json'=>'{}','first_discovered_at'=>$now,'created_at'=>$now]);$link=['id'=>$this->db->insertID()];}
        $this->db->table('shalimar_agent_settings')->where('id',1)->update(['portal_agency_id'=>$central,'verified_version'=>$settings['version'],'updated_at'=>$now]);
        if((int)$settings['verified_version']!==(int)$settings['version'])(new AdminAuditService($this->db))->record('agents.internal_agency_confirmed','agents',$actor,'agency_erp_link',$link['id'],null,['portal_agency_id'=>$central,'erp_source_id'=>$source,'erp_agency_id'=>$agencyId]);
        return $central;
    }
    public function synchronize(callable $fetch,?int $actor=null):array
    {
        $directory=new ShalimarAgents($this->db);$lock='agent_sync_'.substr(hash('sha256',$this->db->getDatabase()),0,44);$nameLock='agency_name_'.substr(hash('sha256',$this->db->getDatabase().':shalimar properties'),0,44);$held=[];
        try{
            foreach([$lock,$nameLock]as$key){if((int)$this->db->query('SELECT GET_LOCK(?,0) AS acquired',[$key])->getRow()->acquired!==1)throw new RuntimeException('Shalimar agent synchronization or agency creation is already running.');$held[]=$key;}
            $settings=$directory->settings();$source=$this->db->table('erp_sources')->where('id',$settings['sgc_source_id'])->where('code','SGC')->where('is_active',1)->get()->getRowArray();if(!$source)throw new RuntimeException('Configure an enabled SGC source for the internal agency.');
            $rows=[];$after='0';$maximum=null;$total=null;$proof=null;$complete=false;
            for($page=0;$page<100;$page++){$data=self::validatePage($fetch($source,(string)$settings['erp_agency_id'],$after,$maximum,100),(string)$settings['erp_agency_id'],$after,$maximum,$total,$proof);$rows=array_merge($rows,$data['rows']);$proof=$data['agency'];$after=$data['after'];$maximum=$data['maximum'];$total=$data['total'];if(!$data['has_more']){$complete=true;break;}}
            if(!$complete||count($rows)!==$total)throw new RuntimeException('Complete dealer snapshot was not received. Previous agents retained.');
            $this->db->transBegin();try{
                $current=$this->db->query('SELECT * FROM shalimar_agent_settings WHERE id=1 FOR UPDATE')->getRowArray();if($current!==$settings)throw new RuntimeException('Internal agency configuration changed during synchronization.');
                $currentSource=$this->db->query('SELECT * FROM erp_sources WHERE id=? FOR UPDATE',[$source['id']])->getRowArray();foreach(['code','base_url','auth_type','credential_ref','is_active','updated_at']as$key)if(!$currentSource||$source[$key]!==$currentSource[$key])throw new RuntimeException('SGC source changed during synchronization.');
                $now=$directory->now();$central=$this->bindAgency($settings,$proof,$actor,$now);$this->db->table('agent_erp_links')->where('erp_source_id',$source['id'])->update(['present'=>0]);
                foreach($rows as$row){$existing=$this->db->query('SELECT * FROM agent_erp_links WHERE erp_source_id=? AND erp_ledger_id=? FOR UPDATE',[$source['id'],$row['ledger_id']])->getRowArray();$values=['name'=>$row['name'],'mobile'=>$row['mobile'],'email'=>filter_var($row['email'],FILTER_VALIDATE_EMAIL)?$row['email']:'','updated_at'=>$now];
                    if($existing){$agent=$this->db->query('SELECT * FROM shalimar_agents WHERE id=? FOR UPDATE',[$existing['portal_agent_id']])->getRowArray();if($agent['mobile']!==$values['mobile']||$agent['email']!==$values['email'])$values['contact_approved']=0;$agentId=(int)$agent['id'];$this->db->table('shalimar_agents')->where('id',$agentId)->update($values);}else{$this->db->table('shalimar_agents')->insert($values+['portal_active'=>1,'contact_approved'=>0,'created_at'=>$now]);$agentId=(int)$this->db->insertID();}
                    $link=['portal_agent_id'=>$agentId,'erp_source_id'=>$source['id'],'erp_dealer_id'=>null,'erp_ledger_id'=>$row['ledger_id'],'erp_agency_id'=>$proof['id'],'erp_dealer_flag'=>$row['dealer'],'erp_locked'=>$row['locked'],'erp_active'=>(int)$row['active'],'erp_eligible'=>(int)$row['eligible'],'present'=>1,'erp_updated_at'=>$row['updated_at'],'last_synced_at'=>$now];
                    if($existing)$this->db->table('agent_erp_links')->where('id',$existing['id'])->update($link);else $this->db->table('agent_erp_links')->insert($link+['first_discovered_at'=>$now]);
                }
                $flagged=$directory->flagAssignments();$this->db->table('agent_sync_runs')->insert(['erp_source_id'=>$source['id'],'erp_agency_id'=>$proof['id'],'status'=>'success','record_count'=>count($rows),'message'=>'Complete SGC dealer snapshot synchronized.','completed_at'=>$now]);(new AdminAuditService($this->db))->record('agents.synchronized','agents',$actor,'erp_source',$source['id'],null,['erp_source_id'=>$source['id'],'erp_agency_id'=>$proof['id'],'portal_agency_id'=>$central,'record_count'=>count($rows),'review_count'=>$flagged]);
                if(!$this->db->transStatus()||!$this->db->transCommit())throw new RuntimeException('Agent synchronization could not commit.');return ['records'=>count($rows),'portal_agency_id'=>$central,'flagged'=>$flagged];
            }catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
        }catch(\Throwable $e){if(isset($settings))$this->db->table('agent_sync_runs')->insert(['erp_source_id'=>$settings['sgc_source_id'],'erp_agency_id'=>$settings['erp_agency_id'],'status'=>'failed','record_count'=>0,'message'=>'Dealer sync failed; previous agents and historical assignments retained.','completed_at'=>$directory->now()]);throw $e;}
        finally{foreach(array_reverse($held)as$key)$this->db->query('SELECT RELEASE_LOCK(?)',[$key]);}
    }
}
