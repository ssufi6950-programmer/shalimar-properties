<?php
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

final class ShalimarAgents
{
    public function __construct(private BaseConnection $db){}
    public static function identifier($value,bool $zero=false):string
    {
        if((!is_int($value)&&!is_string($value))||!preg_match($zero?'/\A(?:0|[1-9][0-9]{0,17})\z/D':'/\A[1-9][0-9]{0,17}\z/D',(string)$value))throw new InvalidArgumentException('Choose a valid ERP, agency or agent reference.');
        return (string)$value;
    }
    public function settings():array
    {
        $row=$this->db->table('shalimar_agent_settings')->where('id',1)->get()->getRowArray();
        if(!$row)throw new InvalidArgumentException('Internal agency configuration is unavailable. Apply the Shalimar Agents migration.');return $row;
    }
    /** Shared settings now supply only the central business identity. */
    public function sourceSettings(int $source,bool $lock=false):array
    {
        $row=$this->db->query('SELECT sc.*,c.portal_agency_id,c.version AS central_version FROM shalimar_agent_source_settings sc JOIN shalimar_agent_settings c ON c.id=sc.settings_id WHERE sc.erp_source_id=? AND sc.settings_id=1'.($lock?' FOR UPDATE':''),[$source])->getRowArray();
        if(!$row)throw new InvalidArgumentException('Source settings are missing. Apply the source-settings SQL and verify the confirmed agency mapping.');
        return $row;
    }
    public function sourceRows():array
    {
        return $this->db->query("SELECT s.id AS source_id,s.name AS source_name,s.code,s.is_active AS source_active,sc.*,a.agency_name FROM erp_sources s LEFT JOIN shalimar_agent_source_settings sc ON sc.erp_source_id=s.id AND sc.settings_id=1 LEFT JOIN shalimar_agent_settings c ON c.id=sc.settings_id LEFT JOIN authorized_agencies a ON a.id=c.portal_agency_id WHERE s.code IN ('SGC','ZALMI') ORDER BY s.code")->getResultArray();
    }
    /** The assignment target is independent of the agent's ledger source. */
    private function assignmentSource(int $source):void
    {
        if(!$this->db->table('erp_sources')->where('id',$source)->where('is_active',1)->whereIn('code',['SGC','ZALMI'])->countAllResults())throw new InvalidArgumentException('This property source does not currently support Shalimar Agent assignments.');
    }
    public static function eligibleSql(string $select='a.id AS portal_agent_id,a.name,a.mobile,a.email,a.image_filename,l.id AS agent_erp_link_id,l.erp_source_id AS agent_source_id,l.erp_ledger_id,l.erp_dealer_id,l.erp_agency_id,s.code AS agent_source,(SELECT ps.code FROM agent_erp_links pl JOIN erp_sources ps ON ps.id=pl.erp_source_id WHERE pl.portal_agent_id=a.id ORDER BY pl.id LIMIT 1) AS profile_source'):string
    {
        return "SELECT $select FROM shalimar_agents a JOIN agent_erp_links l ON l.portal_agent_id=a.id
            JOIN shalimar_agent_settings c ON c.id=1
            JOIN shalimar_agent_source_settings sc ON sc.settings_id=c.id AND sc.erp_source_id=l.erp_source_id
            JOIN erp_sources s ON s.id=l.erp_source_id
            JOIN authorized_agencies pa ON pa.id=c.portal_agency_id
            JOIN agency_erp_links al ON al.erp_source_id=l.erp_source_id AND al.erp_agency_id=sc.erp_agency_id AND al.portal_agency_id=pa.id
            WHERE sc.is_active=1 AND sc.verified_version=sc.version AND sc.last_synced_at=l.last_synced_at AND a.portal_active=1 AND l.present=1 AND l.erp_active=1 AND l.erp_eligible=1
            AND BINARY l.erp_locked=BINARY 'no'
            AND s.code IN ('SGC','ZALMI') AND s.is_active=1 AND pa.portal_active=1 AND pa.is_shalimar_agency=1 AND pa.agency_type='internal'
            AND al.mapping_status='mapped' AND al.present=1 AND BINARY al.erp_authorize=BINARY 'yes'
            AND BINARY al.erp_locked=BINARY 'no' AND BINARY al.erp_restricted=BINARY 'no'";
    }
    public function eligibleForProperty(int $source):array
    {
        $this->assignmentSource($source);
        return $this->adminAssignmentOptions($source);
    }
    /** Admin chooses from the current synced eligible directory at approval. */
    public function adminAssignmentOptions(int $source):array
    {
        $this->assignmentSource($source);$options=[];
        foreach($this->db->query($this->eligibleSql().' ORDER BY a.name,a.id,(l.erp_source_id='.(int)$source.') DESC,l.id')->getResultArray()as$row){
            try{\App\Libraries\CustomerIdentity::mobile($row['mobile']);ListingSellerContact::requireBusiness($row);$key=(int)$row['portal_agent_id'];if(!isset($options[$key])){$row['agent_sources']=[$row['agent_source']];$options[$key]=$row;}else $options[$key]['agent_sources'][]=$row['agent_source'];}catch(InvalidArgumentException $error){}
        }
        foreach($options as&$option)$option['agent_source_label']=implode(' + ',array_unique($option['agent_sources']));unset($option);
        return array_values($options);
    }
    public function requireAdminAssignable(int $source,int $agent,bool $lock=false):array
    {
        $row=$this->requireEligible($source,$agent,$lock);
        \App\Libraries\CustomerIdentity::mobile($row['mobile']);ListingSellerContact::requireBusiness($row);
        return $row;
    }
    /** Compatibility for internal callers; customers cannot assign an agent. */
    public function requireSelectable(int $source,int $agent,bool $lock=false):array
    {
        return $this->requireAdminAssignable($source,$agent,$lock);
    }
    public function requireEligible(int $source,int $agent,bool $lock=false,?int $link=null):array
    {
        $this->assignmentSource($source);$rows=$this->db->query($this->eligibleSql().' AND a.id=?'.($link!==null?' AND l.id='.(int)$link:'').' ORDER BY (l.erp_source_id='.(int)$source.') DESC,l.id'.($lock?' FOR UPDATE':''),[$agent])->getResultArray();
        if(!$rows)throw new InvalidArgumentException('This Shalimar Agent is no longer available. Choose an active agent again.');return $rows[0];
    }
    public function reviewCount():int
    {
        if(!$this->db->fieldExists('agent_review_required','portal_listings'))return 0;
        return $this->db->table('portal_listings')->where('agent_review_required',1)->whereIn('status',['pending','pending_approval','live','approved','active','cancellation_requested'])->whereIn('deal',['available','active'])->countAllResults();
    }
    public function flagAssignments():int
    {
        $this->db->query("UPDATE portal_listings pl SET agent_review_required=1,agent_review_reason='The selected Shalimar Agent is no longer eligible; review this assignment.'
            WHERE pl.portal_agent_id IS NOT NULL AND pl.agent_review_required=0
            AND pl.status IN ('pending','pending_approval','live','approved','active','cancellation_requested') AND pl.deal IN ('available','active')
            AND NOT EXISTS (".$this->eligibleSql('1')." AND a.id=pl.portal_agent_id AND l.id=pl.agent_erp_link_id AND l.erp_source_id=pl.agent_source_id)");
        return $this->db->affectedRows();
    }
    public function configure(int $source,string $agency,?int $portalAgency,?int $actor,int $version,int $centralVersion,bool $enabled):void
    {
        self::identifier($agency);
        $this->transaction(function()use($source,$agency,$portalAgency,$actor,$version,$centralVersion,$enabled){
            $old=$this->sourceSettings($source,true);
            if((int)$old['version']!==$version||(int)$old['central_version']!==$centralVersion)throw new InvalidArgumentException('Configuration changed. Reload the source settings before saving.');
            if($portalAgency===null||$portalAgency!==(int)$old['portal_agency_id'])throw new InvalidArgumentException('The central agency changed. Review confirmed mappings separately; this form does not merge or move agencies.');
            $sql="SELECT l.id FROM agency_erp_links l JOIN erp_sources s ON s.id=l.erp_source_id JOIN authorized_agencies a ON a.id=l.portal_agency_id WHERE l.erp_source_id=? AND l.erp_agency_id=? AND l.portal_agency_id=? AND l.mapping_status='mapped' AND s.code IN ('SGC','ZALMI') AND a.agency_type='internal' AND a.is_shalimar_agency=1 FOR UPDATE";
            if(!$this->db->query($sql,[$source,$agency,$portalAgency])->getRowArray())throw new InvalidArgumentException('Choose a confirmed internal-agency mapping for this ERP source.');
            if((string)$old['erp_agency_id']===$agency&&(int)$old['is_active']===(int)$enabled)return;
            $change=['erp_agency_id'=>$agency,'is_active'=>(int)$enabled,'version'=>$version+1,'verified_version'=>0,'last_synced_at'=>null,'updated_by'=>$actor,'updated_at'=>$this->now()];
            $this->db->table('shalimar_agent_source_settings')->where('erp_source_id',$source)->update($change);
            $this->flagAssignments();
            (new AdminAuditService($this->db))->record('agents.source_configuration_changed','agents',$actor,'shalimar_agent_source_settings',$source,$old,$change+['erp_source_id'=>$source]);
        });
    }
    public function update(int $id,?bool $active,?bool $contacts,?int $actor):void
    {
        $this->transaction(function()use($id,$active,$contacts,$actor){$old=$this->db->query('SELECT * FROM shalimar_agents WHERE id=? FOR UPDATE',[$id])->getRowArray();if(!$old)throw new InvalidArgumentException('Agent not found.');$change=['updated_at'=>$this->now()];if($active!==null)$change['portal_active']=(int)$active;if($contacts!==null)$change['contact_approved']=(int)$contacts;
            $this->db->table('shalimar_agents')->where('id',$id)->update($change);$this->flagAssignments();(new AdminAuditService($this->db))->record('agents.status_changed','agents',$actor,'shalimar_agent',$id,$old,$change);
        });
    }
    public function deletionDetails(int $id):array
    {
        $agent=$this->db->table('shalimar_agents')->where('id',$id)->get()->getRowArray();
        if(!$agent)throw new InvalidArgumentException('Agent not found.');
        $links=$this->db->table('agent_erp_links')->where('portal_agent_id',$id)->get()->getResultArray();
        $assigned=$this->db->table('portal_listings')->groupStart()->where('portal_agent_id',$id);
        if($links)$assigned->orWhereIn('agent_erp_link_id',array_column($links,'id'));
        return ['agent'=>$agent,'links'=>$links,'listingCount'=>$assigned->groupEnd()->countAllResults()];
    }
    /** Delete only unused portal records; ERP records and historical assignments are preserved. */
    public function delete(int $id,?int $actor):void
    {
        $lock='agent_sync_'.substr(hash('sha256',$this->db->getDatabase()),0,44);
        if((int)$this->db->query('SELECT GET_LOCK(?,0) AS acquired',[$lock])->getRow()->acquired!==1)throw new InvalidArgumentException('Agent synchronization is running. Try deleting again after it finishes.');
        try{
            $this->transaction(function()use($id,$actor){
                $agent=$this->db->query('SELECT * FROM shalimar_agents WHERE id=? FOR UPDATE',[$id])->getRowArray();
                if(!$agent)throw new InvalidArgumentException('Agent not found.');
                $links=$this->db->query('SELECT id FROM agent_erp_links WHERE portal_agent_id=? ORDER BY id FOR UPDATE',[$id])->getResultArray();
                $assigned=$this->db->table('portal_listings')->select('id')->groupStart()->where('portal_agent_id',$id);
                if($links)$assigned->orWhereIn('agent_erp_link_id',array_column($links,'id'));
                if($this->db->query($assigned->groupEnd()->limit(1)->getCompiledSelect().' FOR UPDATE')->getRowArray())throw new InvalidArgumentException('This agent has listing assignments or history and cannot be deleted. Disable the agent instead.');
                $this->db->table('agent_erp_links')->where('portal_agent_id',$id)->delete();
                $this->db->table('shalimar_agents')->where('id',$id)->delete();
                (new AdminAuditService($this->db))->record('agents.deleted','agents',$actor,'shalimar_agent',$id,$agent+['portal_agent_id'=>$id,'record_count'=>count($links)],null);
            });
        }finally{$this->db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
    public function acknowledge(int $listing,?int $actor):void
    {
        $this->transaction(function()use($listing,$actor){$row=$this->db->query('SELECT * FROM portal_listings WHERE id=? FOR UPDATE',[$listing])->getRowArray();if(!$row||!$row['portal_agent_id'])throw new InvalidArgumentException('Agent assignment not found.');$agent=$this->requireEligible((int)$row['erp_source_id'],(int)$row['portal_agent_id'],true,(int)$row['agent_erp_link_id']);
            if((int)$row['agent_erp_link_id']!==(int)$agent['agent_erp_link_id'])throw new InvalidArgumentException('The historical assignment requires reassignment before review can be cleared.');
            $this->db->table('portal_listings')->where('id',$listing)->update(['agent_review_required'=>0,'agent_review_reason'=>null,'updated_at'=>$this->now()]);(new AdminAuditService($this->db))->record('agents.assignment_reviewed','agents',$actor,'portal_listing',$listing,null,['portal_agent_id'=>$row['portal_agent_id']]);
        });
    }
    /** Central mapping never rewrites an already assigned identity or notification history. */
    public function mapLink(int $linkId,int $target,int $expectedOwner,?int $actor):void
    {
        $lock='agent_sync_'.substr(hash('sha256',$this->db->getDatabase()),0,44);
        if((int)$this->db->query('SELECT GET_LOCK(?,0) AS acquired',[$lock])->getRow()->acquired!==1)throw new InvalidArgumentException('Agent synchronization is running. Try again afterwards.');
        try{$this->transaction(function()use($linkId,$target,$expectedOwner,$actor){
            $link=$this->db->query('SELECT * FROM agent_erp_links WHERE id=? FOR UPDATE',[$linkId])->getRowArray();
            if(!$link)throw new InvalidArgumentException('ERP agent link not found.');
            if((int)$link['portal_agent_id']===$target)return;
            if((int)$link['portal_agent_id']!==$expectedOwner)throw new InvalidArgumentException('This link was mapped by another request. Reload it.');
            $owner=(int)$link['portal_agent_id'];$agents=[];
            foreach($this->db->query('SELECT * FROM shalimar_agents WHERE id IN (?,?) ORDER BY id FOR UPDATE',[$owner,$target])->getResultArray()as$row)$agents[(int)$row['id']]=$row;
            if(!isset($agents[$owner],$agents[$target])||!(int)$agents[$target]['portal_active'])throw new InvalidArgumentException('Choose an existing enabled local agent.');
            if(!$this->db->table('agent_erp_links')->where('portal_agent_id',$target)->countAllResults())throw new InvalidArgumentException('Choose a local agent with an existing ERP identity.');
            if($this->db->table('agent_erp_links')->where('portal_agent_id',$target)->where('erp_source_id',$link['erp_source_id'])->countAllResults())throw new InvalidArgumentException('This local agent already has an identity for that ERP source.');
            $history=$this->db->query('SELECT id FROM portal_listings WHERE portal_agent_id=? OR agent_erp_link_id=? LIMIT 1 FOR UPDATE',[$owner,$linkId])->getRowArray();
            if($history||$this->db->table('listing_assignment_sms')->groupStart()->where('portal_agent_id',$owner)->orWhere('agent_erp_link_id',$linkId)->groupEnd()->countAllResults())throw new InvalidArgumentException('This identity has listing or SMS history. Preserve it as the central agent and link the unused identity into it instead. No history was changed.');
            $primary=$this->db->table('agent_erp_links')->select('id')->where('portal_agent_id',$target)->orderBy('id')->get(1)->getRowArray();
            $this->db->table('agent_erp_links')->where('id',$linkId)->update(['portal_agent_id'=>$target]);
            if($linkId<(int)$primary['id']){
                $profile=array_intersect_key($agents[$owner],array_flip(['name','mobile','email','image_filename','contact_approved']));$profile['updated_at']=$this->now();
                $this->db->table('shalimar_agents')->where('id',$target)->update($profile);
            }
            if(!$this->db->table('agent_erp_links')->where('portal_agent_id',$owner)->countAllResults())$this->db->table('shalimar_agents')->where('id',$owner)->update(['portal_active'=>0,'updated_at'=>$this->now()]);
            (new AdminAuditService($this->db))->record('agents.erp_link_mapped','agents',$actor,'agent_erp_link',$linkId,['portal_agent_id'=>$owner],['portal_agent_id'=>$target,'agent_erp_link_id'=>$linkId,'erp_source_id'=>$link['erp_source_id'],'erp_ledger_id'=>$link['erp_ledger_id']]);
        });}finally{$this->db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
    private function transaction(callable $action)
    {
        $this->db->transBegin();try{$result=$action();if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('Agent change could not commit.');return $result;}catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
    }
    public function now():string{return(new \DateTimeImmutable('now',new \DateTimeZone(config('App')->appTimezone)))->format('Y-m-d H:i:s');}
}
