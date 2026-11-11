<?php
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use App\Exceptions\CustomerFlowException;

final class ListingLifecycle
{
 public function __construct(private BaseConnection $db){}
 public static function active(array $row):bool{return ListingPolicy::active($row);}
 public function assertNoAdminRestriction(int $source,string $property,string $purpose='sale'):void
 {
  $sql=$this->db->table('portal_listings')->select('id')->where('erp_source_id',$source)->where('erp_property_id',$property)->where('listing_purpose',ListingPolicy::purpose($purpose))->where('status','unlisted')->where('portal_customer_id IS NOT NULL',null,false)->getCompiledSelect();
  if($this->db->query($sql.' FOR UPDATE')->getRowArray())throw new CustomerFlowException(409,'This property has an admin-unpublished listing. Contact support before creating or reactivating this offer.','admin_restricted');
 }
 public static function fingerprint(array $row):string
 {
  $data=[];foreach(['id','portal_customer_id','erp_source_id','erp_property_id','mapping_id','project_slug','status','deal','payload','asking_price','selling_method','portal_agent_id','agent_erp_link_id','agent_source_id','portal_agency_id','agency_erp_link_id','record_version','resale_verification_id','owner_verification','listing_purpose','review_state']as$key)$data[$key]=$row[$key]??null;
  return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
 }
 public function owned(int $customer,int $id,bool $lock=false):array
 {
  $q=$this->db->table('portal_listings')->where('id',$id)->where('portal_customer_id',$customer)->getCompiledSelect();$row=$this->db->query($q.($lock?' FOR UPDATE':''))->getRowArray();
  if(!$row)throw new CustomerFlowException(404,'Listing not found.');return $row;
 }
 public function canRepublish(array $row):bool
 {
  if(($row['review_state']??null)!=='approved'||$row['status']!=='cancelled'||!in_array($row['deal'],['available','active'],true))return false;
  $last=$this->db->table('listing_events')->select('event,portal_customer_id')->where('listing_id',$row['id'])->orderBy('id','DESC')->get()->getRowArray();
  return $last&&$last['event']==='customer_withdrawn'&&(int)$last['portal_customer_id']===(int)$row['portal_customer_id'];
 }
 public static function canCorrect(array $row):bool{return ($row['review_state']??null)==='needs_changes'&&in_array($row['status']??null,['pending','pending_approval'],true)&&in_array($row['deal']??null,['available','active'],true);}
 public function decorate(array $rows):array
 {
  if(!$rows)return [];$ids=array_column($rows,'id');$latest=$this->db->table('listing_events')->select('MAX(id)',false)->whereIn('listing_id',$ids)->groupBy('listing_id')->getCompiledSelect();$events=[];
  foreach($this->db->table('listing_events')->where('id IN ('.$latest.')',null,false)->get()->getResultArray()as$event)$events[$event['listing_id']]=$event;
  foreach($rows as&$row){$event=$events[$row['id']]??null;$row['customer_withdrawn']=$row['status']==='cancelled'&&in_array($row['deal'],['available','active'],true)&&$event&&$event['event']==='customer_withdrawn'&&(int)$event['portal_customer_id']===(int)$row['portal_customer_id'];}unset($row);return $rows;
 }
 public function context(int $customer,int $id,string $operation):array
 {
  $row=$this->owned($customer,$id);$this->requireOperation($row,$operation);
  return ['listing_id'=>$id,'operation'=>$operation,'version'=>(int)$row['record_version'],'fingerprint'=>self::fingerprint($row)];
 }
 private function requireOperation(array $row,string $operation):void
 {
  if(($operation==='edit'&&(self::active($row)||self::canCorrect($row)))||($operation==='republish'&&$this->canRepublish($row)))return;
  throw new CustomerFlowException(409,'This listing cannot be changed or reactivated. Sold, rejected and admin-unpublished listings remain restricted.');
 }
 public function lockTarget(int $customer,array $context,array $property):array
 {
  $row=$this->owned($customer,(int)($context['listing_id']??0),true);$this->requireOperation($row,$context['operation']??'');
  if((int)$row['record_version']!==($context['version']??null)||!is_string($context['fingerprint']??null)||!hash_equals(self::fingerprint($row),$context['fingerprint']))throw new CustomerFlowException(409,'This listing changed since review. Open it again and verify a fresh code.','listing_changed');
  if((int)$row['erp_source_id']!==$property['erp_source_id']||(string)$row['erp_property_id']!==$property['erp_property_id']||(int)$row['mapping_id']!==$property['mapping_id'])throw new CustomerFlowException(409,'The authoritative property or mapping changed. Contact support.');
  return $row;
 }
 public function property(int $customer,int $id,CustomerPortfolio $portfolio,array $links):array
 {
  $row=$this->owned($customer,$id);
  $local=$this->db->table('portal_owned_properties')->where('erp_source_id',$row['erp_source_id'])->where('erp_property_id',$row['erp_property_id'])->where('portal_customer_id',$customer)->get()->getRowArray();
  if(!$local)throw new CustomerFlowException(409,'The locally saved property no longer matches this account.');
  try{$property=$portfolio->find($links,(int)$row['erp_source_id'],(string)$local['owner_reference'],(string)$row['erp_property_id'],$id,$row['listing_purpose']);}catch(\Throwable $e){throw new CustomerFlowException(503,'Current ERP ownership could not be verified. Try again later.');}
  if(!$property||!$property['eligible'])throw new CustomerFlowException(409,$property['reason']??'This property no longer belongs to your verified ERP identity.');
  if($property['mapping_id']!==(int)$row['mapping_id'])throw new CustomerFlowException(409,'This listing mapping changed. Contact support.');return $property;
 }
 public function record(string $event,?array $previous,array $next,?int $customer=null,?int $admin=null,?string $authorization=null):void
 {
  $this->db->table('listing_events')->insert(['listing_id'=>$next['id'],'portal_customer_id'=>$customer,'admin_user_id'=>$admin,'event'=>$event,'from_status'=>$previous['status']??null,'to_status'=>$next['status'],'previous_record'=>$previous?json_encode($previous,JSON_THROW_ON_ERROR):null,'next_record'=>json_encode($next,JSON_THROW_ON_ERROR),'authorization_id'=>$authorization,'created_at'=>(new ShalimarAgents($this->db))->now()]);
 }
 public function transition(int $customer,int $id,string $action,int $version):void
 {
  if(!in_array($action,['withdraw','sold'],true))throw new \InvalidArgumentException('Choose a supported action.');
  $this->db->transBegin();try{
   $old=$this->owned($customer,$id,true);
   if(!self::active($old)||(int)$old['record_version']!==$version)throw new CustomerFlowException(409,'The listing state changed or this action is no longer available. Refresh your listings.');
   $now=(new ShalimarAgents($this->db))->now();$payload=json_decode($old['payload'],true,128,JSON_THROW_ON_ERROR);
   $status=$action==='withdraw'?'cancelled':'sold';$deal=$action==='sold'?'sold':$old['deal'];$payload['status']=$status;$payload['deal']=$deal;$payload[$action==='withdraw'?'withdrawn_at':'sold_at']=$now;
   $values=['status'=>$status,'deal'=>$deal,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'record_version'=>$version+1,'updated_at'=>$now];
   $this->db->table('portal_listings')->where('id',$id)->update($values);$event=$action==='withdraw'?'customer_withdrawn':'customer_marked_sold';$this->record($event,$old,array_replace($old,$values),$customer);
   (new AdminAuditService($this->db))->record('resale.'.$event,'resale_listings',null,'portal_listing',$id,['status'=>$old['status']],['status'=>$status,'portal_customer_id'=>$customer]);
   if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('The listing action could not be saved.');
  }catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
 }
}
