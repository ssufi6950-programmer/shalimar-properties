<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseBuilder;

/** Agency-scoped, read-only projection of the synchronized dealer directory. */
final class AgencyPortalDealers
{
    public function __construct(private BaseConnection $db) {}

    private function links(int $agencyId): BaseBuilder
    {
        return $this->db->table('agency_erp_links l')
            ->join('erp_sources s','s.id=l.erp_source_id')
            ->join('authorized_agencies a','a.id=l.portal_agency_id')
            ->where('l.portal_agency_id',$agencyId)->where('a.portal_active',1)
            ->where('a.agency_type','external')->where('a.is_shalimar_agency',0)
            ->where('l.mapping_status','mapped')->where('l.present',1)
            ->where('l.erp_authorize','yes')->where('l.erp_locked','no')
            ->where('s.is_active',1)->whereIn('s.code',['SGC','ZALMI']);
    }

    public function currentQuery(int $agencyId): BaseBuilder
    {
        return $this->links($agencyId)
            ->join('agency_dealer_memberships m','m.agency_erp_link_id=l.id AND m.portal_agency_id=l.portal_agency_id AND m.erp_source_id=l.erp_source_id AND m.erp_agency_id=l.erp_agency_id')
            ->join('agency_dealers d','d.id=m.agency_dealer_id AND d.erp_source_id=m.erp_source_id')
            ->where('m.portal_agency_id',$agencyId)->where('m.membership_status','current');
    }

    public function search(int $agencyId,array $input=[]): array
    {
        $source=in_array($input['source']??null,['SGC','ZALMI'],true)?$input['source']:'';
        $q=is_string($input['q']??null)?mb_substr(trim($input['q']),0,100):'';
        $requested=$input['page']??'1';
        $page=is_scalar($requested)&&ctype_digit((string)$requested)?max(1,min(100000,(int)$requested)):1;
        $perPage=20;
        $counts=['SGC'=>0,'ZALMI'=>0];
        foreach($this->currentQuery($agencyId)->select('s.code,COUNT(*) AS total',false)->groupBy('s.code')->get()->getResultArray() as $count) $counts[$count['code']]=(int)$count['total'];
        $query=$this->currentQuery($agencyId);
        if($source!=='')$query->where('s.code',$source);
        if($q!=='')$query->groupStart()->like('d.erp_name',$q)->orLike('d.erp_mobile',$q)->groupEnd();
        $total=(clone $query)->countAllResults();
        $pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);
        $rows=$query->select('m.id AS membership_id,m.membership_status,m.last_seen_at,d.erp_ledger_id,d.erp_name,d.erp_mobile,d.erp_email,d.erp_locked_flag,d.erp_staff_agent_flag,s.code AS source_code')
            ->orderBy('s.code')->orderBy('d.erp_name')->orderBy('m.id')->limit($perPage,($page-1)*$perPage)->get()->getResultArray();
        // Sync status is joined on all identities so a remapped state is not displayed.
        $sync=$this->links($agencyId)->select('l.id AS agency_erp_link_id,s.code AS source_code,st.sync_status,st.last_complete_at')
            ->join('agency_dealer_sync_state st','st.agency_erp_link_id=l.id AND st.portal_agency_id=l.portal_agency_id AND st.erp_source_id=l.erp_source_id AND st.erp_agency_id=l.erp_agency_id','left')
            ->orderBy('s.code')->orderBy('l.id')->get()->getResultArray();
        return compact('rows','total','page','pages','perPage','source','q','counts','sync');
    }
}
