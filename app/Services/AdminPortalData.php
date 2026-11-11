<?php
namespace App\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

/** Local portal records only. This service has no ERP client or synchronization path. */
final class AdminPortalData
{
 public function __construct(private BaseConnection $db){}
 public static function object($json):array{$value=is_string($json)?json_decode($json,true):null;return is_array($value)&&!array_is_list($value)?$value:[];}
 public static function text($value):string{return is_string($value)||is_numeric($value)?(string)$value:'';}
 public static function filters(array $input):array
 {
  $out=[];foreach(['q'=>120,'status'=>30,'project'=>160,'method'=>30]as$key=>$limit){$value=$input[$key]??'';if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||mb_strlen($value)>$limit)throw new InvalidArgumentException('Check your search and filters.');$out[$key]=trim($value);}
  foreach(['source','customer','property']as$key){$value=$input[$key]??'';if($value!==''&&(!is_string($value)||!preg_match('/\A[1-9][0-9]{0,17}\z/D',$value)))throw new InvalidArgumentException('Choose a valid filter.');$out[$key]=$value===''?null:(int)$value;}
  $page=$input['page']??'1';if(!is_string($page)||!preg_match('/\A[1-9][0-9]{0,5}\z/D',$page))throw new InvalidArgumentException('Choose a valid page.');$out['page']=(int)$page;
  $size=$input['per_page']??'25';if(!is_string($size)||!in_array($size,['10','25','50','100'],true))throw new InvalidArgumentException('Choose a supported page size.');$out['per_page']=(int)$size;
  if($out['method']!==''&&!in_array($out['method'],['self_sell','shalimar_agent','authorized_agency'],true))throw new InvalidArgumentException('Choose a supported selling method.');return $out;
 }
 /** Legacy imports lacking customer/property provenance are preserved, not counted as customer submissions. */
 private function workflow(BaseBuilder $query,string $alias='l'):BaseBuilder{return $query->where($alias.'.portal_customer_id IS NOT NULL',null,false)->where($alias.'.erp_source_id IS NOT NULL',null,false)->where($alias.'.erp_property_id IS NOT NULL',null,false);}
 private function listingBase():BaseBuilder{return $this->workflow($this->db->table('portal_listings l'));}
 private function latestPropertyListings():string
 {
  return $this->listingBase()->select('l.erp_source_id,l.erp_property_id,MAX(l.id) AS latest_id,MIN(l.created_at) AS first_resale_at,COUNT(*) AS listing_count',false)->groupBy(['l.erp_source_id','l.erp_property_id'])->getCompiledSelect();
 }
 private function propertyBase():BaseBuilder
 {
  return $this->db->table('portal_owned_properties o')->join('('.$this->latestPropertyListings().') r','r.erp_source_id=o.erp_source_id AND r.erp_property_id=o.erp_property_id','inner',false)
   ->join('portal_listings l','l.id=r.latest_id')->join('erp_sources s','s.id=o.erp_source_id','left')->join('portal_customers c','c.id=o.portal_customer_id','left')
   ->join('portal_projects p','p.slug=l.project_slug','left')->join('erp_heads h','h.erp_source_id=o.erp_source_id AND h.erp_head_id=o.erp_head_id','left')
   ->join('erp_subheads sh','sh.erp_source_id=o.erp_source_id AND sh.erp_head_id=o.erp_head_id AND sh.erp_subhead_id=o.erp_subhead_id','left')->where('o.portal_customer_id IS NOT NULL',null,false);
 }
 private function page(BaseBuilder $query,array $filters,string $order):array
 {
  $total=(clone $query)->countAllResults();$pages=max(1,(int)ceil($total/$filters['per_page']));$current=min($filters['page'],$pages);
  return ['rows'=>$query->orderBy($order,'DESC')->limit($filters['per_page'],($current-1)*$filters['per_page'])->get()->getResultArray(),'total'=>$total,'page'=>$current,'pages'=>$pages,'size'=>$filters['per_page'],'filters'=>array_replace($filters,['page'=>$current])];
 }
 public function options():array
 {
  return ['sources'=>$this->db->table('erp_sources')->select('id,code,name,is_active')->orderBy('name')->get()->getResultArray(),'projects'=>$this->db->table('portal_projects')->select('slug,name')->orderBy('name')->get()->getResultArray(),
   'customerStatuses'=>array_column($this->db->table('portal_customers')->select('status')->distinct()->orderBy('status')->get()->getResultArray(),'status'),
   'listingStatuses'=>array_values(array_unique(array_merge(['live','unlisted'],array_column($this->listingBase()->select('l.status')->distinct()->orderBy('l.status')->get()->getResultArray(),'status'))))];
 }
 private function accountMobile(string $cipher):string
 {
  try{return self::text((new \App\Libraries\CustomerSecrets(config('CustomerPortal')->identityKey))->decrypt($cipher)['mobile']??'');}catch(\Throwable $e){return '';}
 }
 private function customerRows(array $rows):array
 {
  if(!$rows)return [];$ids=array_column($rows,'id');$sources=[];
  foreach($this->db->table('customer_erp_links e')->select('e.id,e.portal_customer_id,e.erp_source_id,e.erp_customer_id,e.verified_at,s.code,s.name,s.is_active')->join('erp_sources s','s.id=e.erp_source_id','left')->whereIn('e.portal_customer_id',$ids)->orderBy('e.id')->get()->getResultArray()as$link)$sources[$link['portal_customer_id']][]=$link;
  $counts=array_column($this->listingBase()->select('l.portal_customer_id,COUNT(*) AS records',false)->whereIn('l.portal_customer_id',$ids)->groupBy('l.portal_customer_id')->get()->getResultArray(),'records','portal_customer_id');
  foreach($rows as&$row){$row['mobile']=$this->accountMobile($row['verified_mobile']);unset($row['verified_mobile']);$row['sources']=$sources[$row['id']]??[];$row['listing_count']=(int)($counts[$row['id']]??0);$row['last_login']=null;$row['cnic_summary']='Protected — hash only';}unset($row);return $rows;
 }
 public function customers(array $filters):array
 {
  $query=$this->db->table('portal_customers c')->select('c.id,c.display_name,c.verified_mobile,c.status,c.created_at');
  if($filters['q']!==''){$query->groupStart()->like('c.display_name',$filters['q']);if(ctype_digit($filters['q']))$query->orWhere('c.id',$filters['q']);try{$mobile=\App\Libraries\CustomerIdentity::mobile($filters['q']);$query->orWhere('c.mobile_hash',(new \App\Libraries\CustomerSecrets(config('CustomerPortal')->identityKey))->digest('mobile',$mobile));}catch(\Throwable $e){}$query->groupEnd();}
  if($filters['status']!=='')$query->where('c.status',$filters['status']);
  if($filters['source']!==null){$linked=$this->db->table('customer_erp_links e')->select('1',false)->where('e.portal_customer_id=c.id',null,false)->where('e.erp_source_id',$filters['source'])->getCompiledSelect();$query->where('EXISTS ('.$linked.')',null,false);}
  if($filters['customer']!==null)$query->where('c.id',$filters['customer']);$page=$this->page($query,$filters,'c.id');$page['rows']=$this->customerRows($page['rows']);return $page;
 }
 public function customer(int $id):?array
 {
  $rows=$this->db->table('portal_customers')->select('id,display_name,verified_mobile,status,created_at')->where('id',$id)->get()->getResultArray();return $this->customerRows($rows)[0]??null;
 }
 private function propertySelect():BaseBuilder
 {
  return $this->propertyBase()->select('o.id,o.erp_source_id,o.erp_property_id,o.erp_head_id,o.erp_subhead_id,o.portal_customer_id,o.payload,s.name AS source_name,s.code AS source_code,c.display_name AS customer_name,p.name AS project_name,l.project_slug,h.name AS head_name,sh.name AS subhead_name,l.id AS listing_id,l.status AS listing_status,l.owner_verification,l.resale_verification_id,r.first_resale_at,r.listing_count');
 }
 private function propertyRows(array $rows):array
 {
  foreach($rows as&$row){$payload=self::object($row['payload']);$proof=self::object($row['owner_verification']);
   $row['plot']=self::text($payload['plot']??'');$row['size']=self::text($payload['size']??'');$row['type']=self::text($payload['type']??'');$row['floor']=self::text($payload['floor']??'');$row['booking']=self::text($payload['booking']??'');$row['original_agency']=self::text($payload['original_agency']??'');
   $row['project_name']=$row['project_name']?:self::text($payload['project']??'');$row['head_name']=$row['head_name']?:self::text($payload['head']??'');$row['subhead_name']=$row['subhead_name']?:self::text($payload['subhead']??'');
   $row['verified_at']=$row['resale_verification_id']&&($proof['ownership_match']??null)===true?self::text($proof['verified_at']??''):'';$row['verification_status']=$row['verified_at']!==''?'Verified at publication':'Not recorded';unset($row['payload'],$row['owner_verification'],$row['resale_verification_id']);
  }unset($row);return $rows;
 }
 public function properties(array $filters):array
 {
  $query=$this->propertySelect();if($filters['source']!==null)$query->where('o.erp_source_id',$filters['source']);if($filters['customer']!==null)$query->where('o.portal_customer_id',$filters['customer']);if($filters['project']!=='')$query->where('l.project_slug',$filters['project']);
  if($filters['q']!==''){$query->groupStart()->like('o.payload',$filters['q'])->orLike('c.display_name',$filters['q'])->orLike('p.name',$filters['q']);if(ctype_digit($filters['q']))$query->orWhere('o.id',$filters['q'])->orWhere('o.erp_property_id',$filters['q']);$query->groupEnd();}
  $page=$this->page($query,$filters,'o.id');$page['rows']=$this->propertyRows($page['rows']);return $page;
 }
 public function property(int $id):?array{return $this->propertyRows($this->propertySelect()->where('o.id',$id)->get()->getResultArray())[0]??null;}
 private function listingSelect():BaseBuilder
 {
  return $this->listingBase()->select('l.*,c.display_name AS customer_name,s.name AS source_name,s.code AS source_code,p.name AS project_name,o.id AS local_property_id,a.name AS current_agent_name,ag.agency_name AS current_agency_name')
   ->join('portal_customers c','c.id=l.portal_customer_id','left')->join('erp_sources s','s.id=l.erp_source_id','left')->join('portal_projects p','p.slug=l.project_slug','left')
   ->join('portal_owned_properties o','o.erp_source_id=l.erp_source_id AND o.erp_property_id=l.erp_property_id','left')->join('shalimar_agents a','a.id=l.portal_agent_id','left')->join('authorized_agencies ag','ag.id=l.portal_agency_id','left');
 }
 private function listingRows(array $rows):array
 {
  if(!$rows)return [];$ids=array_column($rows,'id');
  $placeholders=implode(',',array_fill(0,count($ids),'?'));$public=array_column($this->db->query('SELECT l.id FROM portal_listings l JOIN erp_project_mappings m ON m.id=l.mapping_id AND m.project_slug=l.project_slug'.ErpVisibility::joins().' WHERE l.id IN ('.$placeholders.') AND '.ErpVisibility::mappingSql().' AND '.ListingPolicy::publicSql(),$ids)->getResultArray(),'id');
  foreach($rows as&$row){$payload=self::object($row['payload']);$snapshot=self::object($row['agent_snapshot']);$proof=self::object($row['owner_verification']);$poster=is_array($payload['poster']??null)?$payload['poster']:[];
   $row['negotiable']=($payload['negotiable']??false)===true;$row['reference']=self::text($payload['ref']??'')?:'SP-'.$row['id'];$row['title']=self::text($payload['title']??'');$row['plot']=self::text($payload['plot_no']??'');$row['size']=self::text($payload['size_label']??'');$row['floor']=self::text($payload['floor']??'');$row['description']=self::text($payload['description']??'');$row['original_agency']=self::text($payload['original_agent_agency']??'');$row['agent_ledger_ref']=self::text($snapshot['erp_ledger_id']??'');
   $row['project_name']=$row['project_name']?:self::text($payload['project']??'');$row['price']=$row['asking_price']??(is_numeric($payload['price']??null)?$payload['price']:null);
   $row['representative_name']=self::text($snapshot['name']??$poster['name']??$row['current_agent_name']??$row['current_agency_name']??'');$row['public_contact']=self::text($poster['phone']??'');$row['published_at']=self::text($payload['published_at']??'');
   $row['verified_at']=$row['resale_verification_id']&&($proof['ownership_match']??null)===true?self::text($proof['verified_at']??''):'';$row['booking_ref']=self::text($proof['current_booking_ref']??'');$row['verification_status']=$row['verified_at']!==''?'Verified at publication':'Not recorded';
   $row['public_visible']=in_array($row['id'],$public,true);$row['can_unpublish']=in_array($row['status'],['live','approved','active'],true)&&in_array($row['deal'],['available','active'],true);$row['review_note']=self::text($payload['review_note']??'');
   unset($row['payload'],$row['owner_verification'],$row['agent_snapshot'],$row['submission_key'],$row['resale_verification_id'],$row['current_agent_name'],$row['current_agency_name'],$row['active_erp_property']);
  }unset($row);return $rows;
 }
 public function listings(array $filters):array
 {
  $query=$this->listingSelect();foreach(['source'=>'l.erp_source_id','customer'=>'l.portal_customer_id','property'=>'o.id','project'=>'l.project_slug','status'=>'l.status','method'=>'l.selling_method']as$key=>$column)if($filters[$key]!==null&&$filters[$key]!=='')$query->where($column,$filters[$key]);
  if($filters['q']!==''){$query->groupStart()->like('l.payload',$filters['q'])->orLike('c.display_name',$filters['q'])->orLike('p.name',$filters['q'])->orLike('a.name',$filters['q'])->orLike('ag.agency_name',$filters['q']);if(ctype_digit($filters['q']))$query->orWhere('l.id',$filters['q'])->orWhere('l.erp_property_id',$filters['q']);$query->groupEnd();}
  $page=$this->page($query,$filters,'l.id');$page['rows']=$this->listingRows($page['rows']);return $page;
 }
 public function listing(int $id):?array{return $this->listingRows($this->listingSelect()->where('l.id',$id)->get()->getResultArray())[0]??null;}
 public function retainedLegacyCount():int{return $this->db->table('portal_listings')->groupStart()->where('portal_customer_id',null)->orWhere('erp_source_id',null)->orWhere('erp_property_id',null)->groupEnd()->countAllResults();}
 public function dashboard(array $access):array
 {
  $counts=[];$recent=[];$monthly=[];$sources=[];$top=[];
  if($access['customers']??false)$counts['customers']=$this->db->table('portal_customers')->countAllResults();
  if($access['properties']??false)$counts['properties']=$this->propertyBase()->countAllResults();
  if($access['resale']??false){
   $totals=$this->listingBase()->select("COUNT(*) AS listings,COALESCE(SUM(".ListingPolicy::publicSql()."),0) AS active,COALESCE(SUM(l.status='unlisted'),0) AS unpublished,COALESCE(SUM(l.status='sold' OR l.deal='sold'),0) AS sold",false)->get()->getRowArray();foreach($totals as$key=>$value)$counts[$key]=(int)$value;
   $recent=$this->listingRows($this->listingSelect()->orderBy('l.id','DESC')->limit(6)->get()->getResultArray());
   $start=(new \DateTimeImmutable('first day of this month',new \DateTimeZone(config('App')->appTimezone)))->modify('-11 months')->setTime(0,0);
   $buckets=array_column($this->listingBase()->select("DATE_FORMAT(l.created_at,'%Y-%m') AS month,COUNT(*) AS records",false)->where('l.created_at >=',$start->format('Y-m-d H:i:s'))->groupBy("DATE_FORMAT(l.created_at,'%Y-%m')",false)->get()->getResultArray(),'records','month');for($i=0;$i<12;$i++){$date=$start->modify('+'.$i.' months');$monthly[]=['month'=>$date->format('M Y'),'count'=>(int)($buckets[$date->format('Y-m')]??0)];}
   $sources=$this->listingBase()->select('s.name,COUNT(*) AS records',false)->join('erp_sources s','s.id=l.erp_source_id','left')->where(ListingPolicy::publicSql(),null,false)->groupBy(['s.id','s.name'])->orderBy('s.name')->get()->getResultArray();
   $top=$this->listingBase()->select('l.project_slug,p.name,COUNT(*) AS records',false)->join('portal_projects p','p.slug=l.project_slug','left')->groupBy(['l.project_slug','p.name'])->orderBy('records','DESC')->limit(5)->get()->getResultArray();
  }
  return ['counts'=>$counts,'recent'=>$recent,'monthly'=>$monthly,'bySource'=>$sources,'topProjects'=>$top];
 }
 public function unpublish(int $id,int $actor,string $reason):void
 {
  if(!mb_check_encoding($reason,'UTF-8')||mb_strlen($reason)>500)throw new InvalidArgumentException('Use an unpublish reason of up to 500 characters.');
  $this->db->transBegin();try{
   $row=$this->workflow($this->db->table('portal_listings l'))->where('l.id',$id)->getCompiledSelect();$record=$this->db->query($row.' FOR UPDATE')->getRowArray();
   if(!$record)throw new InvalidArgumentException('Customer resale listing not found.');if($record['status']==='unlisted'){$this->db->transCommit();return;}
   if(!in_array($record['status'],['live','approved','active'],true)||!in_array($record['deal'],['available','active'],true))throw new InvalidArgumentException('Only a currently active available listing can be unpublished. Existing terminal states were preserved.');
   $payload=json_decode($record['payload'],true);if(!is_array($payload)||json_last_error()!==JSON_ERROR_NONE||substr(ltrim($record['payload']),0,1)!=='{')throw new InvalidArgumentException('This listing payload needs review before it can be changed.');
   $payload['status']='unlisted';if(trim($reason)!=='')$payload['review_note']=trim($reason);$now=(new \DateTimeImmutable('now',new \DateTimeZone(config('App')->appTimezone)))->format('Y-m-d H:i:s');
   $values=['status'=>'unlisted','payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'updated_at'=>$now,'record_version'=>(int)$record['record_version']+1];$this->db->table('portal_listings')->where('id',$id)->update($values);(new ListingLifecycle($this->db))->record('admin_unpublished',$record,array_replace($record,$values),null,$actor);
   (new AdminAuditService($this->db))->record('resale.unpublished','resale_listings',$actor,'portal_listing',$id,['status'=>$record['status']],['status'=>'unlisted','reason'=>trim($reason)]);
   if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('The listing could not be unpublished.');
  }catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
 }
}
