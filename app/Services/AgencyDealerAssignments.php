<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use Throwable;

/** Local assignments only; effective reads always apply portfolio + directory policy. */
final class AgencyDealerAssignments
{
    public function __construct(private BaseConnection $db) {}

    private function portfolio(int $agency,int $listing): array
    {
        $row=(new AgencyPortalListings($this->db))->assignmentQuery($agency)->where('pl.id',$listing)->get()->getRowArray();
        if(!$row)throw new DomainException('This listing is no longer available in your agency portfolio. Refresh listings.');
        return $row;
    }

    private function members(int $agency,array $listing)
    {
        return (new AgencyPortalDealers($this->db))->currentQuery($agency)
            ->where('m.erp_source_id',$listing['erp_source_id'])->where('m.agency_erp_link_id',$listing['agency_erp_link_id']);
    }

    /** At most one page of listing IDs; no private listing/dealer fields returned. */
    public function effective(int $agency,array $ids,bool $lock=false): array
    {
        if(!$ids)return [];
        if(count($ids)>50)throw new DomainException('Too many listings requested.');
        $member=(new AgencyPortalDealers($this->db))->currentQuery($agency)->select('1',false)
            ->where('m.id=x.agency_dealer_membership_id',null,false)
            ->where('m.agency_erp_link_id=pl.agency_erp_link_id',null,false)
            ->where('m.erp_source_id=pl.erp_source_id',null,false)->getCompiledSelect();
        $query=(new AgencyPortalListings($this->db))->assignmentQuery($agency)
            ->join('portal_listing_dealer_assignments x',"x.portal_listing_id=pl.id AND x.portal_agency_id=pl.portal_agency_id AND x.erp_source_id=pl.erp_source_id AND x.agency_erp_link_id=pl.agency_erp_link_id AND x.assignment_status='active'")
            ->join('agency_dealer_memberships am','am.id=x.agency_dealer_membership_id')
            ->join('agency_dealers ad','ad.id=am.agency_dealer_id')
            ->where('EXISTS ('.$member.')',null,false)->whereIn('pl.id',$ids)
            ->select('x.agency_dealer_membership_id,ad.erp_name AS assigned_dealer_name');
        $rows=$lock?$this->db->query($query->getCompiledSelect().' FOR UPDATE')->getResultArray():$query->get()->getResultArray();
        $out=[];foreach($rows as $row)$out[(int)$row['id']]=['membership_id'=>(int)$row['agency_dealer_membership_id'],'name'=>$row['assigned_dealer_name'],'source'=>$row['source_code']];
        return $out;
    }

    private static function token(?array $row): string
    {
        return hash('sha256',$row ? $row['id'].':'.$row['record_version'] : 'unassigned');
    }

    public function current(int $agency,int $listing): array
    {
        $this->portfolio($agency,$listing);
        $raw=$this->db->query("SELECT id,record_version FROM portal_listing_dealer_assignments WHERE portal_listing_id=? AND assignment_status='active'",[$listing])->getRowArray();
        return ['assignment'=>$this->effective($agency,[$listing])[$listing]??null,'checkpoint'=>self::token($raw)];
    }

    public function options(int $agency,int $listing,string $search,int $page): array
    {
        $row=$this->portfolio($agency,$listing);$query=$this->members($agency,$row);
        $search=mb_substr(trim($search),0,100);if($search!=='')$query->groupStart()->like('d.erp_name',$search)->orLike('d.erp_mobile',$search)->groupEnd();
        $page=max(1,min(100000,$page));$total=(clone $query)->countAllResults();$pages=max(1,(int)ceil($total/20));$page=min($page,$pages);
        $rows=$query->select('m.id AS membership_id,d.erp_name AS name,d.erp_mobile AS mobile,s.code AS source,d.erp_locked_flag AS locked,d.erp_staff_agent_flag AS staff_overlap')
            ->orderBy('d.erp_name')->orderBy('m.id')->limit(20,($page-1)*20)->get()->getResultArray();
        return compact('rows','total','page','pages');
    }

    private function revoke(array $row,int $account,bool $system,string $reason,string $now): void
    {
        $this->db->table('portal_listing_dealer_assignments')->where('id',$row['id'])->update([
            'assignment_status'=>'revoked','revoked_at'=>$now,'revoked_by_agency_account_id'=>$system?null:$account,
            'revocation_actor'=>$system?'system':'agency','revocation_reason'=>$reason,
            'record_version'=>(int)$row['record_version']+1,'updated_at'=>$now]);
    }

    public function change(int $agency,int $account,int $authVersion,int $listing,?int $membership,string $checkpoint): void
    {
        $this->db->transBegin();
        try {
            // Global lock order: listing, assignment, account, then portfolio and membership dependencies.
            $locked=$this->db->query('SELECT id FROM portal_listings WHERE id=? AND portal_agency_id=? FOR UPDATE',[$listing,$agency])->getRowArray();
            if(!$locked)throw new DomainException('Listing unavailable. Refresh listings.');
            $current=$this->db->query("SELECT * FROM portal_listing_dealer_assignments WHERE portal_listing_id=? AND assignment_status='active' FOR UPDATE",[$listing])->getRowArray();
            $actor=$this->db->query('SELECT id,portal_agency_id,enabled,auth_version,must_change_password FROM agency_portal_accounts WHERE id=? FOR UPDATE',[$account])->getRowArray();
            if(!$actor||(int)$actor['portal_agency_id']!==$agency||!(int)$actor['enabled']||(int)$actor['auth_version']!==$authVersion||(int)$actor['must_change_password'])throw new DomainException('Your agency session changed. Please sign in again.');
            $query=(new AgencyPortalListings($this->db))->assignmentQuery($agency)->where('pl.id',$listing)->getCompiledSelect();
            $row=$this->db->query($query.' FOR UPDATE')->getRowArray();
            if(!$row)throw new DomainException('Listing ownership, approval or mapping changed. Refresh listings.');
            if(!hash_equals(self::token($current),$checkpoint))throw new DomainException('The assignment changed in another request. Reload assignment details.');
            if($membership!==null){
                $query=$this->members($agency,$row)->where('m.id',$membership)->select('m.id')->getCompiledSelect();
                if(!$this->db->query($query.' FOR UPDATE')->getRowArray())throw new DomainException('Dealer membership or source mapping changed. Choose a current matching dealer.');
            }
            $effective=$this->effective($agency,[$listing],true)[$listing]??null;
            $now=date('Y-m-d H:i:s');
            if($current && !$effective)$this->revoke($current,$account,true,'Assignment no longer matches the current listing portfolio or dealer membership.',$now);
            elseif($current && ($membership===null || $effective['membership_id']!==$membership))$this->revoke($current,$account,false,$membership===null?'Agency unassigned dealer.':'Agency reassigned listing to another dealer.',$now);
            if($membership!==null && (!$effective || $effective['membership_id']!==$membership)){
                $this->db->table('portal_listing_dealer_assignments')->insert([
                    'portal_listing_id'=>$listing,'agency_dealer_membership_id'=>$membership,'portal_agency_id'=>$agency,
                    'agency_erp_link_id'=>$row['agency_erp_link_id'],'erp_source_id'=>$row['erp_source_id'],
                    'assignment_status'=>'active','assigned_at'=>$now,'assigned_by_agency_account_id'=>$account,
                    'record_version'=>1,'created_at'=>$now,'updated_at'=>$now]);
            }
            if(!$this->db->transStatus() || !$this->db->transCommit())throw new \RuntimeException('Assignment transaction failed.');
        } catch(Throwable $e){
            $this->db->transRollback();$this->db->resetTransStatus();
            if($e instanceof DomainException)throw $e;
            log_message('error','Agency dealer assignment transaction failed ({type}).',['type'=>get_class($e)]);
            throw new DomainException('Assignment could not be saved. Refresh assignment details and try again.');
        }
    }

    public function history(int $agency,int $listing): array
    {
        $this->portfolio($agency,$listing);
        return $this->db->table('portal_listing_dealer_assignments')->select('id,agency_dealer_membership_id,assignment_status,assigned_at,assigned_by_agency_account_id,revoked_at,revoked_by_agency_account_id,revocation_actor,revocation_reason,record_version')
            ->where('portal_listing_id',$listing)->where('portal_agency_id',$agency)->orderBy('id','DESC')->limit(100)->get()->getResultArray();
    }
}
