<?php
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
final class CustomerPortfolio
{
 public function __construct(private BaseConnection $db,private CustomerIdentityProvider $provider){}
 /** Read-only login precheck; stop as soon as any verified identity owns a current property. */
 public function hasCurrent(array $identities,array $unavailable=[]):array
 {
  $errors=$unavailable;
  foreach($identities as$identity){try{for($page=1;$page<=100;$page++){
   $result=$this->page($identity,$page);
   if($result['properties'])return ['has_properties'=>true,'unavailable'=>$errors];
   if(!$result['has_more'])break;
   if($page===100)throw new \RuntimeException('Ownership pagination limit reached.');
  }}catch(\Throwable $e){$errors[]=['erp_source_id'=>(int)$identity['erp_source_id'],'source_name'=>$identity['source_name']??'ERP','source_code'=>$identity['source_code']??'','message'=>'Current properties from this source could not be fully loaded.'];}}
  return ['has_properties'=>false,'unavailable'=>$errors];
 }
 public function all(array $identities,array $unavailable=[]):array
 {
  $properties=[];$errors=$unavailable;$seen=[];
  foreach($identities as$identity){try{for($page=1;$page<=100;$page++){$result=$this->page($identity,$page);foreach($result['properties']as$property){$key=$property['erp_source_id'].':'.$property['erp_property_id'];if(!isset($seen[$key])){$properties[]=$property;$seen[$key]=true;}}if(!$result['has_more'])break;if($page===100)throw new \RuntimeException('Ownership pagination limit reached.');}}catch(\Throwable $e){$errors[]=['erp_source_id'=>(int)$identity['erp_source_id'],'source_name'=>$identity['source_name']??'ERP','source_code'=>$identity['source_code']??'','message'=>'Current properties from this source could not be fully loaded.'];}}
  return ['properties'=>$properties,'unavailable'=>$errors];
 }
 public function page(array $identity,int $page,?int $ignoreListing=null,string $purpose='sale'):array
 {
  $result=$this->provider->owned($identity,$page);
  if(!is_array($result['properties']??null)||!is_bool($result['has_more']??null))throw new \RuntimeException('Invalid ownership result.');$properties=[];
  foreach($result['properties']as$row){$property=$this->normalize($row,$identity,$ignoreListing,$purpose);if($property!==null)$properties[]=$property;}
  return ['properties'=>$properties,'has_more'=>$result['has_more']];
 }
 private static function id($value,bool $zero=false):string
 {
  if((!is_string($value)&&!is_int($value))||!preg_match($zero?'/\A(?:0|[1-9][0-9]{0,17})\z/D':'/\A[1-9][0-9]{0,17}\z/D',(string)$value))throw new \RuntimeException('Invalid ERP ownership identifier.');return (string)$value;
 }
 private function normalize(array $row,array $identity,?int $ignoreListing=null,string $purpose='sale'):?array
 {
  $purpose=ListingPolicy::purpose($purpose);$classification=ErpPropertyClassification::fromApi($row);$id=self::id($row['property_id']??null);$head=self::id($row['head_id']??null,true);$sub=self::id($row['subhead_id']??null,true);
  $owners=$row['current_owner_customer_ids']??[$row['current_owner_customer_id']??null];if(!is_array($owners)||!array_is_list($owners))throw new \RuntimeException('Invalid current ownership proof.');$owners=array_map(static fn($id)=>self::id($id),$owners);
  if(!in_array((string)$identity['erp_customer_id'],$owners,true)||($row['ownership_status']??null)!=='current'||($row['booking_status']??null)!=='active')return null;
  foreach(['head_smp_active','subhead_smp_active']as$key)if(!array_key_exists($key,$row)||($row[$key]!==null&&!is_string($row[$key])))throw new \RuntimeException('Invalid ERP visibility values.');
  $text=static function($value):string{if($value===null)return '';if(!is_string($value)&&!is_int($value)&&!is_float($value))throw new \RuntimeException('Invalid property description.');return mb_substr((string)$value,0,255);};
  $mapping=$this->db->query('SELECT m.id,m.project_slug,m.is_active,p.name,p.published,h.smp_active AS head_flag,sh.smp_active AS sub_flag,h.present AS head_present,sh.present AS sub_present FROM erp_project_mappings m JOIN portal_projects p ON p.slug=m.project_slug LEFT JOIN erp_heads h ON h.erp_source_id=m.erp_source_id AND h.erp_head_id=m.erp_head_id LEFT JOIN erp_subheads sh ON sh.erp_source_id=m.erp_source_id AND sh.erp_head_id=m.erp_head_id AND sh.erp_subhead_id=m.erp_subhead_id WHERE m.erp_source_id=? AND m.erp_head_id=? AND m.erp_subhead_id=?',[$identity['erp_source_id'],$head,$sub])->getRowArray();
  // Older endpoints already excluded in-process rows; new endpoints expose the raw flag.
  $processing=array_key_exists('booking_in_process',$row)?$row['booking_in_process']:'no';
  if($processing!==null&&!is_string($processing))throw new \RuntimeException('Invalid ERP processing status.');
  $processingLabel=$processing==='no'?'Cleared':($processing==='yes'?'Pending ERP approval':'Unconfirmed');
  // Document processing is separate from current ownership and resale permission.
  $reason='';if(!in_array($processing,['yes','no'],true))$reason='ERP booking approval status could not be confirmed.';
  elseif(!ErpVisibility::eligible($row['head_smp_active'],$row['subhead_smp_active']))$reason='Public resale is disabled for this ERP Head/Subhead.';
  elseif(!$mapping)$reason='This ERP Head/Subhead has not been mapped to a portal project.';
  elseif(!(int)$mapping['is_active']||!(int)$mapping['published'])$reason='The project or mapping is not currently published/active.';
  elseif(!(int)$mapping['head_present']||!(int)$mapping['sub_present']||!ErpVisibility::eligible($mapping['head_flag'],$mapping['sub_flag']))$reason='The synchronized ERP catalog does not currently permit public resale.';
  $baseReason=$reason;$actions=[];$records=$this->db->table('portal_listings')->select('id,portal_customer_id,status,review_state,listing_purpose,active_erp_property')->where('erp_source_id',$identity['erp_source_id'])->where('erp_property_id',$id)->get()->getResultArray();
  foreach(['sale','rent']as$actionPurpose){$actionReason=$baseReason;$existing=null;
   foreach($records as$record){if($record['listing_purpose']!==$actionPurpose)continue;
    if($record['status']==='unlisted'&&$record['portal_customer_id']!==null)$actionReason='This purpose has an admin-unpublished listing. Contact support before relisting.';
    if($record['active_erp_property']===ListingPolicy::key((int)$identity['erp_source_id'],$id,$actionPurpose)&&(int)$record['id']!==$ignoreListing){$existing=$record;$actionReason='This property already has a current '.($actionPurpose==='rent'?'Rent':'Sale').' listing.';}
   }
   $own=$existing&&(int)$existing['portal_customer_id']===(int)($identity['portal_customer_id']??0)&&(int)($identity['portal_customer_id']??0)>0;
   $actions[$actionPurpose]=['eligible'=>$actionReason==='','reason'=>$actionReason,'listing_id'=>$own?(int)$existing['id']:null,'status'=>$existing['status']??null,'review_state'=>$existing['review_state']??null];
  }
  $reason=$actions[$purpose]['reason'];$existing=$actions[$purpose];
  return ['erp_source_id'=>(int)$identity['erp_source_id'],'erp_customer_id'=>(string)$identity['erp_customer_id'],'erp_property_id'=>$id,'erp_head_id'=>$head,'erp_subhead_id'=>$sub,'mapping_id'=>$mapping?(int)$mapping['id']:null,'project_slug'=>$mapping['project_slug']??null,'project'=>$mapping['name']??$text($row['head_name']??'ERP property'),'erp'=>$identity['source_name']??'ERP','erp_code'=>$identity['source_code']??'','head'=>$text($row['head_name']??''),'subhead'=>$text($row['subhead_name']??''),'plot'=>$text($row['unit_number']??$id),'floor'=>$text($row['floor']??''),'floor_title'=>$text($row['floor_title']??''),'floor_category'=>$text($row['floor_category']??''),'booking'=>$text($row['booking_id']??''),'block'=>$text($row['block']??''),'sector'=>$text($row['sector']??''),'type'=>$classification['type'],'inventory_property_type'=>$classification['inventory_property_type'],'maintenance_status'=>$classification['maintenance_status'],'classification_status'=>$classification['classification_status'],'construction_status'=>$classification['construction_status'],'listing_purpose'=>$purpose,'size'=>$text($row['size']??''),'location'=>$text($row['location']??''),'ownership'=>$processing==='no'?'Verified':'Current booking','booking_status'=>'active','booking_in_process'=>$processing,'booking_processing_status'=>$processingLabel,'sale_type'=>$text($row['sale_type']??''),'erp_booking_status'=>$text($row['erp_booking_status']??'active'),'last_updated'=>$text($row['updated_at']??''),'original_agency'=>$text($row['original_agent_agency']??''),'eligible'=>$reason==='','reason'=>$reason,'listing_actions'=>$actions,'listing_status'=>$existing['status']??null,'image'=>'gc-villas.webp'];
 }
 public function find(array $identities,int $source,string $customer,string $property,?int $ignoreListing=null,string $purpose='sale'):?array
 {
  foreach($identities as$identity)if((int)$identity['erp_source_id']===$source&&(string)$identity['erp_customer_id']===$customer){for($page=1;$page<=100;$page++){$result=$this->page($identity,$page,$ignoreListing,$purpose);foreach($result['properties']as$row)if($row['erp_property_id']===$property)return $row;if(!$result['has_more'])break;}return null;}return null;
 }
}
