<?php
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

final class ShalimarAgents
{
    /** Generic SGC dealer selected for customer resale: Portal Agent #5 in this deployment. */
    public const CUSTOMER_AGENT_LEDGER_ID='11770';
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
    /** The assignment target is independent of the agent's SGC ledger source. */
    private function assignmentSource(int $source):void
    {
        if(!$this->db->table('erp_sources')->where('id',$source)->where('is_active',1)->whereIn('code',['SGC','ZALMI'])->countAllResults())throw new InvalidArgumentException('This property source does not currently support Shalimar Agent assignments.');
    }
    public static function eligibleSql(string $select='a.id AS portal_agent_id,a.name,a.mobile,a.email,l.id AS agent_erp_link_id,l.erp_source_id AS agent_source_id,l.erp_ledger_id,l.erp_dealer_id,l.erp_agency_id,s.code AS agent_source'):string
    {
        return "SELECT $select FROM shalimar_agents a JOIN agent_erp_links l ON l.portal_agent_id=a.id
            JOIN shalimar_agent_settings c ON c.id=1 AND c.sgc_source_id=l.erp_source_id AND c.erp_agency_id=l.erp_agency_id
            JOIN erp_sources s ON s.id=l.erp_source_id
            JOIN authorized_agencies pa ON pa.id=c.portal_agency_id
            JOIN agency_erp_links al ON al.erp_source_id=c.sgc_source_id AND al.erp_agency_id=c.erp_agency_id AND al.portal_agency_id=pa.id
            WHERE c.verified_version=c.version AND a.portal_active=1 AND l.present=1 AND l.erp_active=1 AND l.erp_eligible=1
            AND BINARY l.erp_dealer_flag=BINARY 'yes' AND BINARY l.erp_locked=BINARY 'no'
            AND s.code='SGC' AND s.is_active=1 AND pa.portal_active=1 AND pa.is_shalimar_agency=1 AND pa.agency_type='internal'
            AND al.mapping_status='mapped' AND al.present=1 AND BINARY al.erp_authorize=BINARY 'yes'
            AND BINARY al.erp_locked=BINARY 'no' AND BINARY al.erp_restricted=BINARY 'no'";
    }
    public function eligibleForProperty(int $source):array
    {
        $this->assignmentSource($source);
        return $this->db->query($this->eligibleSql()." AND c.erp_agency_id='193' AND a.contact_approved=1 AND l.erp_ledger_id=? ORDER BY a.name,a.id",[self::CUSTOMER_AGENT_LEDGER_ID])->getResultArray();
    }
    /** New customer assignments use only the generic agent; historical eligibility stays separate. */
    public function requireSelectable(int $source,int $agent,bool $lock=false):array
    {
        $this->assignmentSource($source);
        $rows=$this->db->query($this->eligibleSql()." AND c.erp_agency_id='193' AND a.contact_approved=1 AND a.id=? AND l.erp_ledger_id=? ORDER BY l.id".($lock?' FOR UPDATE':''),[$agent,self::CUSTOMER_AGENT_LEDGER_ID])->getResultArray();
        if(!$rows)throw new InvalidArgumentException('Choose the available Shalimar agent from the resale dropdown.');return $rows[0];
    }
    public function requireEligible(int $source,int $agent,bool $lock=false):array
    {
        $this->assignmentSource($source);$rows=$this->db->query($this->eligibleSql().' AND a.id=? ORDER BY l.id'.($lock?' FOR UPDATE':''),[$agent])->getResultArray();
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
    public function configure(int $source,string $agency,?int $portalAgency,?int $actor):void
    {
        self::identifier($agency);$row=$this->db->table('erp_sources')->where('id',$source)->where('code','SGC')->where('is_active',1)->get()->getRowArray();if(!$row)throw new InvalidArgumentException('Choose the enabled SGC ERP source.');
        if($portalAgency!==null&&!$this->db->table('authorized_agencies')->where('id',$portalAgency)->countAllResults())throw new InvalidArgumentException('Choose an existing central agency or automatic confirmed-business setup.');
        $this->transaction(function()use($source,$agency,$portalAgency,$actor){$old=$this->db->query('SELECT * FROM shalimar_agent_settings WHERE id=1 FOR UPDATE')->getRowArray();
            if((int)$old['sgc_source_id']===$source&&(string)$old['erp_agency_id']===$agency&&($portalAgency===null||$portalAgency===(int)$old['portal_agency_id']))return;
            $change=['sgc_source_id'=>$source,'erp_agency_id'=>$agency,'portal_agency_id'=>$portalAgency,'version'=>(int)$old['version']+1,'verified_version'=>0,'updated_by'=>$actor,'updated_at'=>$this->now()];
            $this->db->table('shalimar_agent_settings')->where('id',1)->update($change);$this->flagAssignments();(new AdminAuditService($this->db))->record('agents.configuration_changed','agents',$actor,'shalimar_agent_settings',1,$old,$change);
        });
    }
    public function update(int $id,?bool $active,?bool $contacts,?int $actor):void
    {
        $this->transaction(function()use($id,$active,$contacts,$actor){$old=$this->db->query('SELECT * FROM shalimar_agents WHERE id=? FOR UPDATE',[$id])->getRowArray();if(!$old)throw new InvalidArgumentException('Agent not found.');$change=['updated_at'=>$this->now()];if($active!==null)$change['portal_active']=(int)$active;if($contacts!==null)$change['contact_approved']=(int)$contacts;
            $this->db->table('shalimar_agents')->where('id',$id)->update($change);$this->flagAssignments();(new AdminAuditService($this->db))->record('agents.status_changed','agents',$actor,'shalimar_agent',$id,$old,$change);
        });
    }
    public function acknowledge(int $listing,?int $actor):void
    {
        $this->transaction(function()use($listing,$actor){$row=$this->db->query('SELECT * FROM portal_listings WHERE id=? FOR UPDATE',[$listing])->getRowArray();if(!$row||!$row['portal_agent_id'])throw new InvalidArgumentException('Agent assignment not found.');$agent=$this->requireEligible((int)$row['erp_source_id'],(int)$row['portal_agent_id'],true);
            if((int)$row['agent_erp_link_id']!==(int)$agent['agent_erp_link_id'])throw new InvalidArgumentException('The historical assignment requires reassignment before review can be cleared.');
            $this->db->table('portal_listings')->where('id',$listing)->update(['agent_review_required'=>0,'agent_review_reason'=>null,'updated_at'=>$this->now()]);(new AdminAuditService($this->db))->record('agents.assignment_reviewed','agents',$actor,'portal_listing',$listing,null,['portal_agent_id'=>$row['portal_agent_id']]);
        });
    }
    private function transaction(callable $action)
    {
        $this->db->transBegin();try{$result=$action();if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('Agent change could not commit.');return $result;}catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
    }
    public function now():string{return(new \DateTimeImmutable('now',new \DateTimeZone(config('App')->appTimezone)))->format('Y-m-d H:i:s');}
}
