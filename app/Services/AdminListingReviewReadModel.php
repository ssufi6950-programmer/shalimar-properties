<?php
declare(strict_types=1);
namespace App\Services;
use CodeIgniter\Database\BaseConnection;

final class AdminListingReviewReadModel
{
    public function __construct(private BaseConnection $db){}
    public function queue(array $input):array
    {
        $filters=AdminPortalData::filters($input);$review=$input['review']??'under_review';$purpose=$input['purpose']??'';
        if(!is_string($review)||!in_array($review,['','under_review','approved','rejected','needs_changes'],true)||!is_string($purpose)||!in_array($purpose,['','sale','rent'],true))throw new \InvalidArgumentException('Choose a valid review state and purpose.');
        $query=$this->db->table('portal_listings l')->join('erp_sources s','s.id=l.erp_source_id')->join('portal_customers c','c.id=l.portal_customer_id')->join('portal_projects p','p.slug=l.project_slug','left')->where('l.erp_property_id IS NOT NULL',null,false);
        $counts=[];foreach((clone$query)->select('l.review_state,COUNT(*) AS records',false)->groupBy('l.review_state')->get()->getResultArray()as$row)$counts[$row['review_state']]=(int)$row['records'];
        if($review!=='')$query->where('l.review_state',$review);if($purpose!=='')$query->where('l.listing_purpose',$purpose);if($filters['source'])$query->where('l.erp_source_id',$filters['source']);if($filters['project']!=='')$query->where('l.project_slug',$filters['project']);
        if($filters['q']!=='')$query->groupStart()->like('l.payload',$filters['q'])->orLike('l.id',$filters['q'])->orLike('c.display_name',$filters['q'])->orLike('s.name',$filters['q'])->groupEnd();
        $total=(clone$query)->countAllResults();$size=$filters['per_page'];$pages=max(1,(int)ceil($total/$size));$page=min($pages,$filters['page']);
        $rows=$query->select('l.id,l.status,l.deal,l.listing_purpose,l.review_state,l.payload,l.asking_price,l.selling_method,l.created_at,l.erp_property_id,s.name AS source_name,p.name AS project_name,c.display_name AS customer_name')->orderBy('l.id','DESC')->limit($size,($page-1)*$size)->get()->getResultArray();
        return ['rows'=>$rows,'counts'=>$counts,'total'=>$total,'size'=>$size,'pages'=>$pages,'page'=>$page,'filters'=>array_replace($filters,['review'=>$review,'purpose'=>$purpose]),'options'=>(new AdminPortalData($this->db))->options()];
    }
    public function detail(int $id,bool $internalContact):array
    {
        $review=new AdminListingReview($this->db);$record=$review->row($id);$public=(new AdminPortalData($this->db))->listing($id);$payload=json_decode($record['payload'],true,128,JSON_THROW_ON_ERROR);
        $customer=$this->db->table('portal_customers')->select('id,display_name,status,verified_mobile')->where('id',$record['portal_customer_id'])->get()->getRowArray();$mobile='';if($internalContact&&$customer)$mobile=(new \App\Libraries\CustomerSecrets(config('CustomerPortal')->identityKey))->decrypt($customer['verified_mobile'])['mobile'];unset($customer['verified_mobile']);
        $links=$this->db->table('customer_erp_links')->select('erp_source_id,erp_customer_id,verified_at')->where('portal_customer_id',$record['portal_customer_id'])->where('erp_source_id',$record['erp_source_id'])->get()->getResultArray();
        $mapping=$this->db->table('erp_project_mappings m')->select('m.id,m.erp_source_id,m.erp_head_id,m.erp_subhead_id,m.project_slug,m.is_active,h.name AS head_name,h.smp_active AS head_flag,sh.name AS subhead_name,sh.smp_active AS subhead_flag')->join('erp_heads h','h.erp_source_id=m.erp_source_id AND h.erp_head_id=m.erp_head_id','left')->join('erp_subheads sh','sh.erp_source_id=m.erp_source_id AND sh.erp_head_id=m.erp_head_id AND sh.erp_subhead_id=m.erp_subhead_id','left')->where('m.id',$record['mapping_id'])->get()->getRowArray();
        try{$contact=$review->representative($record);$contactError='';}catch(\InvalidArgumentException$e){$contact=[];$contactError=$e->getMessage();}
        $history=[];foreach($this->db->table('listing_events e')->select('e.event,e.from_status,e.to_status,e.next_record,e.created_at,e.admin_user_id,a.name AS reviewer_name')->join('admin_users a','a.id=e.admin_user_id','left')->where('e.listing_id',$id)->orderBy('e.id','DESC')->get()->getResultArray()as$event){$next=json_decode($event['next_record'],true);$context=$next['_admin_review']??[];unset($event['next_record']);$history[]=$event+['reason'=>$context['reason']??'','verified_at'=>$context['verification']['verified_at']??'','reviewed_version'=>$context['reviewed_version']??null];}
        foreach($this->db->table('admin_audit_logs a')->select('a.created_at,a.new_values,u.name AS reviewer_name')->join('admin_users u','u.id=a.actor_user_id','left')->where('a.action','listing.review_verification_failed')->where('a.target_type','portal_listing')->where('a.target_id',(string)$id)->get()->getResultArray()as$failure){$safe=json_decode($failure['new_values'],true);$history[]=['event'=>'admin_verification_failed','from_status'=>null,'to_status'=>'Unchanged','reviewer_name'=>$failure['reviewer_name'],'created_at'=>$failure['created_at'],'reason'=>'Verification/action blocked: '.($safe['reason']??'unavailable'),'verified_at'=>'','reviewed_version'=>null];}
        usort($history,static fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
        return ['record'=>$record,'payload'=>$payload,'public'=>$public,'customer'=>$customer,'internalMobile'=>$mobile,'links'=>$links,'mapping'=>$mapping,'contact'=>$contact,'contactError'=>$contactError,'photos'=>(new AdminListingPhotos($this->db))->items($record),'fingerprint'=>$review->fingerprint($record),'history'=>$history,'reviewable'=>AdminListingReview::reviewable($record)];
    }
}
