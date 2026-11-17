<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Admin presentation only; uses the same effective-access policy as the agency. */
final class AdminListingDealerAssignment
{
    public function __construct(private BaseConnection $db) {}

    public function detail(int $listingId): array
    {
        $listing=$this->db->table('portal_listings')->select('id,selling_method,portal_agency_id,agency_erp_link_id,erp_source_id')->where('id',$listingId)->get()->getRowArray();
        if(!$listing||$listing['selling_method']!=='authorized_agency')return ['applicable'=>false,'ready'=>true,'assignment'=>null];
        if(!$this->db->tableExists('portal_listing_dealer_assignments'))return ['applicable'=>true,'ready'=>false,'assignment'=>null];
        $agency=(int)$listing['portal_agency_id'];
        if(!$agency)return ['applicable'=>true,'ready'=>true,'assignment'=>null];
        $effective=(new AgencyDealerAssignments($this->db))->effective($agency,[$listingId])[$listingId]??null;
        if(!$effective)return ['applicable'=>true,'ready'=>true,'assignment'=>null];
        $row=$this->db->query("SELECT x.assigned_at,m.id AS membership_id,d.erp_name AS dealer_name,a.agency_name,s.code AS source_code
            FROM portal_listing_dealer_assignments x
            JOIN agency_dealer_memberships m ON m.id=x.agency_dealer_membership_id AND m.portal_agency_id=x.portal_agency_id AND m.agency_erp_link_id=x.agency_erp_link_id AND m.erp_source_id=x.erp_source_id
            JOIN agency_dealers d ON d.id=m.agency_dealer_id AND d.erp_source_id=m.erp_source_id
            JOIN agency_erp_links l ON l.id=m.agency_erp_link_id AND l.portal_agency_id=m.portal_agency_id AND l.erp_source_id=m.erp_source_id AND l.erp_agency_id=m.erp_agency_id
            JOIN authorized_agencies a ON a.id=x.portal_agency_id
            JOIN erp_sources s ON s.id=x.erp_source_id
            WHERE x.portal_listing_id=? AND x.portal_agency_id=? AND x.agency_erp_link_id=? AND x.erp_source_id=?
            AND x.agency_dealer_membership_id=? AND x.assignment_status='active' AND m.membership_status='current'
            AND l.mapping_status='mapped' AND l.present=1 AND l.erp_authorize='yes' AND l.erp_locked='no' AND s.is_active=1 AND a.portal_active=1",
            [$listingId,$agency,$listing['agency_erp_link_id'],$listing['erp_source_id'],$effective['membership_id']])->getRowArray();
        return ['applicable'=>true,'ready'=>true,'assignment'=>$row];
    }
}
