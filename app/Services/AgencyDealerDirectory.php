<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class AgencyDealerDirectory
{
    private BaseConnection $db;
    public function __construct(?BaseConnection$db=null){$this->db=$db??db_connect();}

    private function ready():bool
    {
        foreach(['agency_dealers','agency_dealer_memberships','agency_dealer_sync_state']as$table)if(!$this->db->tableExists($table))return false;return true;
    }

    public function data(int$portalAgencyId,array$input=[]):array
    {
        if(!$this->ready())return['ready'=>false,'links'=>[],'rows'=>[],'total'=>0,'page'=>1,'perPage'=>25,'source'=>'all','q'=>''];
        $links=$this->db->query("SELECT l.id AS agency_erp_link_id,l.portal_agency_id,l.erp_source_id,l.erp_agency_id,l.erp_agency_name,l.mapping_status,l.present,l.erp_authorize,l.erp_locked,s.code AS source_code,s.name AS source_name,s.is_active AS source_active,a.portal_active,a.agency_type,a.is_shalimar_agency,st.sync_status,st.last_attempted_at,st.last_succeeded_at,st.last_complete_at,st.last_record_count,st.consecutive_failures,st.error_summary,(SELECT COUNT(*) FROM agency_dealer_memberships m WHERE m.agency_erp_link_id=l.id AND m.portal_agency_id=l.portal_agency_id AND m.erp_source_id=l.erp_source_id AND m.erp_agency_id=l.erp_agency_id AND m.membership_status='current') AS current_count FROM agency_erp_links l JOIN erp_sources s ON s.id=l.erp_source_id JOIN authorized_agencies a ON a.id=l.portal_agency_id LEFT JOIN agency_dealer_sync_state st ON st.agency_erp_link_id=l.id WHERE l.portal_agency_id=? AND s.code IN ('SGC','ZALMI') ORDER BY s.code,l.id",[$portalAgencyId])->getResultArray();
        foreach($links as&$link)$link['sync_eligible']=(int)$link['source_active']===1&&(int)$link['portal_active']===1&&$link['agency_type']==='external'&&(int)$link['is_shalimar_agency']===0&&(int)$link['present']===1&&$link['mapping_status']==='mapped'&&$link['erp_authorize']==='yes'&&$link['erp_locked']==='no';unset($link);
        $requestedSource=$input['dealer_source']??'all';$source=in_array($requestedSource,['all','SGC','ZALMI'],true)?$requestedSource:'all';$q=is_string($input['dealer_q']??null)?mb_substr(trim($input['dealer_q']),0,100):'';$page=max(1,min(100000,(int)($input['dealer_page']??1)));$perPage=25;
        $builder=$this->db->table('agency_dealer_memberships m')->select('m.id AS membership_id,m.membership_status,m.first_seen_at,m.last_seen_at,m.revoked_at,d.erp_ledger_id,d.erp_name,d.erp_mobile,d.erp_email,d.erp_locked_flag,d.erp_staff_agent_flag,d.last_seen_at AS dealer_last_seen,s.code AS source_code,s.name AS source_name,l.erp_agency_id,l.id AS agency_erp_link_id')
            ->join('agency_dealers d','d.id=m.agency_dealer_id AND d.erp_source_id=m.erp_source_id')->join('erp_sources s','s.id=m.erp_source_id')->join('agency_erp_links l','l.id=m.agency_erp_link_id AND l.portal_agency_id=m.portal_agency_id AND l.erp_source_id=m.erp_source_id AND l.erp_agency_id=m.erp_agency_id')
            ->where('m.portal_agency_id',$portalAgencyId);
        if($source!=='all')$builder->where('s.code',$source);
        if($q!=='')$builder->groupStart()->like('d.erp_name',$q)->orLike('d.erp_mobile',$q)->groupEnd();
        $total=(clone$builder)->countAllResults();$rows=$builder->orderBy("CASE WHEN m.membership_status='current' THEN 0 ELSE 1 END",'ASC',false)->orderBy('s.code')->orderBy('d.erp_name')->limit($perPage,($page-1)*$perPage)->get()->getResultArray();
        return['ready'=>true,'links'=>$links,'rows'=>$rows,'total'=>$total,'page'=>$page,'perPage'=>$perPage,'source'=>$source,'q'=>$q];
    }
}
