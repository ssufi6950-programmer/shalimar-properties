<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Customer-scoped local presentation queries; no ERP writes or publication decisions. */
final class CustomerListingReadModel
{
    public function __construct(private BaseConnection $db) {}

    public function counts(int $customer): array
    {
        $active=ListingPolicy::publicSql('portal_listings');
        $row=$this->db->query("SELECT COUNT(*) AS total,COALESCE(SUM($active),0) AS active,COALESCE(SUM(NOT ($active)),0) AS history,COALESCE(SUM(status='sold' OR deal='sold'),0) AS sold FROM portal_listings WHERE portal_customer_id=?",[$customer])->getRowArray();
        return array_map('intval',$row);
    }

    public function page(int $customer,array $input,bool $history=false): array
    {
        $q=is_string($input['q']??null)?mb_substr(trim($input['q']),0,120):'';
        $status=is_string($input['status']??null)?$input['status']:'';
        if(!in_array($status,['','active','withdrawn','unlisted','pending','sold','rejected','needs_changes','cancelled','removed'],true))$status='';
        $perPage=in_array((int)($input['per_page']??10),[10,20,50],true)?(int)($input['per_page']??10):10;
        $builder=$this->db->table('portal_listings l')->join('erp_sources s','s.id=l.erp_source_id','left')->join('portal_projects p','p.slug=l.project_slug','left')->where('l.portal_customer_id',$customer);
        if($history)$builder->where('NOT ('.ListingPolicy::publicSql().')',null,false);
        else $builder->where('l.status !=','sold')->where('l.deal !=','sold');
        if($q!=='')$builder->groupStart()->like('l.payload',$q)->orLike('p.name',$q)->orLike('s.name',$q)->orLike('l.id',$q)->groupEnd();
        if($status==='active')$builder->where(ListingPolicy::publicSql(),null,false);
        elseif($status==='sold')$builder->groupStart()->where('l.status','sold')->orWhere('l.deal','sold')->groupEnd();
        elseif($status==='withdrawn')$builder->where('l.status','cancelled')->whereIn('l.deal',['available','active'])->where("EXISTS (SELECT 1 FROM listing_events e WHERE e.listing_id=l.id AND e.portal_customer_id=l.portal_customer_id AND e.event='customer_withdrawn' AND e.id=(SELECT MAX(last_event.id) FROM listing_events last_event WHERE last_event.listing_id=l.id))",null,false);
        elseif($status==='needs_changes')$builder->where('l.review_state','needs_changes');
        elseif($status!=='')$builder->where('l.status',$status);
        $total=(clone $builder)->countAllResults();$pages=max(1,(int)ceil($total/$perPage));$page=max(1,min($pages,(int)($input['page']??1)));
        $rows=$builder->select('l.id,l.payload,l.status,l.deal,l.listing_purpose,l.review_state,l.record_version,l.portal_customer_id,l.erp_source_id,l.erp_property_id,l.project_slug,l.selling_method,l.asking_price,l.agent_snapshot,l.agent_review_required,l.created_at,l.updated_at,s.name AS source_name,p.name AS project_name')->orderBy('l.id','DESC')->limit($perPage,($page-1)*$perPage)->get()->getResultArray();
        return ['records'=>(new ListingLifecycle($this->db))->decorate($rows),'pagination'=>['page'=>$page,'pages'=>$pages,'total'=>$total,'per_page'=>$perPage],'filters'=>['q'=>$q,'status'=>$status,'per_page'=>$perPage],'historyMode'=>$history];
    }
}
