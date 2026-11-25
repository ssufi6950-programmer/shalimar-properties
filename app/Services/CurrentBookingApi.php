<?php
namespace App\Services;
use App\Libraries\CustomerIdentity;
class CurrentBookingApi extends CustomerErpApi implements CurrentBookingProvider
{
 private \CodeIgniter\Database\BaseConnection $bookingDb;
 public function __construct(private \Config\CustomerPortal $bookingSettings,?\CodeIgniter\Database\BaseConnection $db=null){$this->bookingDb=$db??db_connect();parent::__construct($bookingSettings,$this->bookingDb);}
 public function verify(array $identity,string $property,?string $booking=null):array
 {
  $source=$this->bookingDb->table('erp_sources')->where('id',$identity['erp_source_id'])->where('is_active',1)->get()->getRowArray();if(!$source)throw new \RuntimeException('The property ERP source is unavailable.');
  $path=$this->bookingSettings->bookingPaths[$source['code']]??null;if(!is_string($path)||$path==='')throw new \RuntimeException('Current booking endpoint is not configured.');
  $input=['property_ref'=>$property,'expected_customer_id'=>(string)$identity['erp_customer_id']];if($booking!==null)$input['expected_booking_ref']=$booking;
  try{
   $response=$this->request($source,$path,$input);
   return self::validate($response,$property,(string)$identity['erp_customer_id'],$booking);
  }catch(\Throwable $error){
   $message=$error->getMessage();
   $reason=match($message){
    'Current booking endpoint is not configured.'=>'endpoint_missing',
    'The property ERP source is unavailable.'=>'source_unavailable',
    'ERP customer API requires bearer credentials.'=>'auth_not_configured',
    'ERP URL is missing.'=>'base_url_missing',
    'ERP host not approved.'=>'host_not_approved',
    'ERP DNS unavailable.'=>'dns_unavailable',
    'ERP transport failed.'=>'transport_failed',
    'Invalid ERP response.'=>'invalid_json',
    'Current booking ownership proof is invalid.'=>'proof_contract_invalid',
    'Invalid current booking identifiers.'=>'identifier_contract_invalid',
    'Current booking changed.'=>'booking_reference_mismatch',
    'Invalid current owner role.'=>'owner_role_invalid',
    'Invalid visibility proof.'=>'visibility_contract_invalid',
    'Inconsistent visibility proof.'=>'eligibility_contract_invalid',
    'Invalid authoritative registered mobile.'=>'registered_mobile_invalid',
    'Stale current booking verification.'=>'verification_timestamp_stale',
    default=>preg_match('/\bHTTP status (\d{3})\b/i',$message,$status)?'http_'.$status[1]:'verification_failed',
   };
   log_message('error','Current booking verification failed: source={source_code} reason_code={reason_code} exception_type={exception_type}.',[
    'source_code'=>(string)$source['code'],
    'reason_code'=>$reason,
    'exception_type'=>get_class($error),
   ]);
   throw $error;
  }
 }
 public static function validate(array $response,string $property,string $customer,?string $booking=null):array
 {
  $data=$response['data']??null;
  if(($response['success']??null)!==true||!is_array($data)||($data['property_ref']??null)!==$property||($data['current_owner_customer_id']??null)!==$customer||($data['current_booking']??null)!==true||($data['ownership_match']??null)!==true||($data['booking_status']??null)!=='active'||!is_bool($data['resale_eligible']??null))throw new \RuntimeException('Current booking ownership proof is invalid.');
  foreach(['current_booking_ref','head_id','subhead_id']as$key)if(!is_string($data[$key]??null)||!preg_match('/\A[0-9]{1,18}\z/D',$data[$key]))throw new \RuntimeException('Invalid current booking identifiers.');
  if($booking!==null&&$data['current_booking_ref']!==$booking)throw new \RuntimeException('Current booking changed.');
  if(isset($data['owner_role'])&&!in_array($data['owner_role'],['primary','co_owner','nominee'],true))throw new \RuntimeException('Invalid current owner role.');
  foreach(['head_smp_active','subhead_smp_active']as$key)if(!array_key_exists($key,$data)||($data[$key]!==null&&!is_string($data[$key])))throw new \RuntimeException('Invalid visibility proof.');
  if($data['resale_eligible']!==($data['head_smp_active']==='yes'&&$data['subhead_smp_active']==='yes'))throw new \RuntimeException('Inconsistent visibility proof.');
  if(!is_string($data['registered_mobile']??null)||CustomerIdentity::mobile($data['registered_mobile'])!==$data['registered_mobile'])throw new \RuntimeException('Invalid authoritative registered mobile.');
  $timestamp=$data['verified_at']??null;if(!is_string($timestamp)||!preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})\z/D',$timestamp)||abs((new \DateTimeImmutable($timestamp))->getTimestamp()-time())>90)throw new \RuntimeException('Stale current booking verification.');
  return $data;
 }
}
