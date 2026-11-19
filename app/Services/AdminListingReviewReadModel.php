<?php
declare(strict_types=1);
namespace App\Services;
use CodeIgniter\Database\BaseConnection;

final class AdminListingReviewReadModel
{
    public function __construct(private BaseConnection $db){}
    public function queue(array $input):array
    {
        $filters=AdminPortalData::filters($input);$review=$input['review']??'';$purpose=$input['purpose']??'';
        if(!is_string($review)||!in_array($review,['','under_review','approved','rejected','needs_changes','deleted'],true)||!is_string($purpose)||!in_array($purpose,['','sale','rent'],true))throw new \InvalidArgumentException('Choose a valid review state and purpose.');
        $query=$this->db->table('portal_listings l')->join('erp_sources s','s.id=l.erp_source_id')->join('portal_customers c','c.id=l.portal_customer_id','left')->join('portal_projects p','p.slug=l.project_slug','left')->where('l.erp_property_id IS NOT NULL',null,false)->groupStart()->where('l.portal_customer_id IS NOT NULL',null,false)->orWhere("JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$._company_inventory.origin'))",'admin_company_inventory')->groupEnd();
        $retained=AdminListingDeletion::retainedSql();$bucket="CASE WHEN $retained THEN l.review_state ELSE 'deleted' END";
        $counts=[];foreach((clone$query)->select($bucket.' AS bucket,COUNT(*) AS records',false)->groupBy('bucket')->get()->getResultArray()as$row)$counts[$row['bucket']]=(int)$row['records'];
        $query->where($review==='deleted'?'NOT ('.$retained.')':$retained,null,false);
        if($review!==''&&$review!=='deleted')$query->where('l.review_state',$review);if($purpose!=='')$query->where('l.listing_purpose',$purpose);if($filters['source'])$query->where('l.erp_source_id',$filters['source']);if($filters['project']!=='')$query->where('l.project_slug',$filters['project']);
        if($filters['q']!=='')$query->groupStart()->like('l.payload',$filters['q'])->orLike('l.id',$filters['q'])->orLike('c.display_name',$filters['q'])->orLike('s.name',$filters['q'])->groupEnd();
        $total=(clone$query)->countAllResults();$size=$filters['per_page'];$pages=max(1,(int)ceil($total/$size));$page=min($pages,$filters['page']);
        $rows=$query->select('l.id,l.portal_customer_id,l.record_version,l.status,l.deal,l.listing_purpose,l.review_state,l.payload,l.asking_price,l.selling_method,l.created_at,l.erp_property_id,s.name AS source_name,s.code AS source_code,p.name AS project_name,c.display_name AS customer_name')->orderBy('l.id','DESC')->limit($size,($page-1)*$size)->get()->getResultArray();
        return ['rows'=>$rows,'counts'=>$counts,'total'=>$total,'size'=>$size,'pages'=>$pages,'page'=>$page,'filters'=>array_replace($filters,['review'=>$review,'purpose'=>$purpose]),'options'=>(new AdminPortalData($this->db))->options()];
    }
    public function detail(int $id,bool $internalContact):array
    {
        $review=new AdminListingReview($this->db);$record=$review->row($id);$company=CompanyPropertyListing::owned($record);$public=(new AdminPortalData($this->db))->listing($id);$payload=json_decode($record['payload'],true,128,JSON_THROW_ON_ERROR);
        if($company){$source=$this->db->table('erp_sources')->select('name AS source_name,code AS source_code')->where('id',$record['erp_source_id'])->get()->getRowArray()?:[];$project=$this->db->table('portal_projects')->select('name AS project_name')->where('slug',$record['project_slug'])->get()->getRowArray()?:[];$visibility=$this->db->query('SELECT '.ListingPolicy::publicSql().' AS public_visible FROM portal_listings l WHERE l.id=?',[$id])->getRowArray();$public=array_merge($source,$project,['public_visible'=>(bool)($visibility['public_visible']??false)]);}
        $customer=null;$mobile='';$links=[];
        if(!$company){$customer=$this->db->table('portal_customers')->select('id,display_name,status,verified_mobile')->where('id',$record['portal_customer_id'])->get()->getRowArray();if($internalContact&&$customer)$mobile=(new \App\Libraries\CustomerSecrets(config('CustomerPortal')->identityKey))->decrypt($customer['verified_mobile'])['mobile'];unset($customer['verified_mobile']);$links=$this->db->table('customer_erp_links')->select('erp_source_id,erp_customer_id,verified_at')->where('portal_customer_id',$record['portal_customer_id'])->where('erp_source_id',$record['erp_source_id'])->get()->getResultArray();}
        $mapping=$this->db->table('erp_project_mappings m')->select('m.id,m.erp_source_id,m.erp_head_id,m.erp_subhead_id,m.project_slug,m.is_active,h.name AS head_name,h.smp_active AS head_flag,sh.name AS subhead_name,sh.smp_active AS subhead_flag')->join('erp_heads h','h.erp_source_id=m.erp_source_id AND h.erp_head_id=m.erp_head_id','left')->join('erp_subheads sh','sh.erp_source_id=m.erp_source_id AND sh.erp_head_id=m.erp_head_id AND sh.erp_subhead_id=m.erp_subhead_id','left')->where('m.id',$record['mapping_id'])->get()->getRowArray();
        try{$contact=$review->representative($record);$contactError='';}catch(\InvalidArgumentException$e){$contact=[];$contactError=$e->getMessage();}
        $history=[];foreach($this->db->table('listing_events e')->select('e.event,e.from_status,e.to_status,e.next_record,e.created_at,e.admin_user_id,a.name AS reviewer_name')->join('admin_users a','a.id=e.admin_user_id','left')->where('e.listing_id',$id)->orderBy('e.id','DESC')->get()->getResultArray()as$event){$next=json_decode($event['next_record'],true);$context=$next['_admin_review']??[];unset($event['next_record']);$history[]=$event+['reason'=>$context['reason']??'','verified_at'=>$context['verification']['verified_at']??'','reviewed_version'=>$context['reviewed_version']??null];}
        foreach($this->db->table('admin_audit_logs a')->select('a.created_at,a.new_values,u.name AS reviewer_name')->join('admin_users u','u.id=a.actor_user_id','left')->where('a.action','listing.review_verification_failed')->where('a.target_type','portal_listing')->where('a.target_id',(string)$id)->get()->getResultArray()as$failure){$safe=json_decode($failure['new_values'],true);$history[]=['event'=>'admin_verification_failed','from_status'=>null,'to_status'=>'Unchanged','reviewer_name'=>$failure['reviewer_name'],'created_at'=>$failure['created_at'],'reason'=>'Verification/action blocked: '.($safe['reason']??'unavailable'),'verified_at'=>'','reviewed_version'=>null];}
        usort($history,static fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
        $deleted=(new AdminListingDeletion($this->db))->deletion($id);
        $agentOptions=[];
        if(!$deleted&&AdminListingReview::reviewable($record)&&$record['selling_method']==='shalimar_agent'){
            try{$agentOptions=(new ShalimarAgents($this->db))->adminAssignmentOptions((int)$record['erp_source_id']);}catch(\InvalidArgumentException $error){}
        }
        return ['dealerAssignment'=>(new AdminListingDealerAssignment($this->db))->detail($id),'agentOptions'=>$agentOptions,'record'=>$record,'payload'=>$payload,'public'=>$public,'customer'=>$customer,'isCompany'=>$company,'internalMobile'=>$mobile,'links'=>$links,'mapping'=>$mapping,'contact'=>$contact,'contactError'=>$contactError,'photos'=>(new AdminListingPhotos($this->db))->items($record),'fingerprint'=>$review->fingerprint($record),'history'=>$history,'deleted'=>$deleted,'reviewable'=>!$deleted&&AdminListingReview::reviewable($record),'assignmentSms'=>!$company&&$record['selling_method']==='shalimar_agent'?(new AgentAssignmentSms($this->db))->statusFor($record):null,'assignmentWhatsapp'=>!$company&&$record['selling_method']==='shalimar_agent'&&$this->db->tableExists('listing_assignment_whatsapp')?(new AgentAssignmentWhatsapp($this->db))->statusFor($record):null];
    }
}
