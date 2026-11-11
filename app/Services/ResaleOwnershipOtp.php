<?php
namespace App\Services;
use App\Exceptions\CustomerFlowException;
use App\Libraries\CustomerIdentity;
use App\Libraries\CustomerSecrets;
use CodeIgniter\Database\BaseConnection;

final class ResaleOwnershipOtp
{
 private CustomerSecrets $secrets;private CustomerRateLimiter $rate;
 public function __construct(private BaseConnection $db,private CurrentBookingProvider $provider,private CustomerSmsGateway $sms,private \Config\CustomerPortal $settings,private $clock=null){$this->secrets=new CustomerSecrets($settings->identityKey);$this->rate=new CustomerRateLimiter($db,$this->secrets);}
 private function now():int{return $this->clock?($this->clock)():time();}
 public static function fingerprint(array $proof):string{$values=[];foreach(['property_ref','current_booking_ref','booking_status','current_booking','current_owner_customer_id','owner_role','ownership_match','registered_mobile','head_id','subhead_id','head_smp_active','subhead_smp_active','resale_eligible']as$key)$values[$key]=$proof[$key]??null;return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));}
 private function unavailable():CustomerFlowException{return new CustomerFlowException(503,'Current booking ownership could not be verified. Please try again later.','booking_verification_unavailable');}
 public static function deliveryUsable(string $state,?\Config\CustomerPortal $settings=null):bool
 {
  if($state==='sent')return true;
  $settings??=config('CustomerPortal');
  return $state==='preview'&&$settings->resaleOtpPreviewOnly&&$settings->showOtpPreview&&config('Sms')->testMode;
 }
 private function previewOnly():bool
 {
  if(!$this->settings->resaleOtpPreviewOnly)return false;
  if(!$this->settings->showOtpPreview||!config('Sms')->testMode)throw new CustomerFlowException(503,'Property OTP testing requires showOtpPreview=true and sms.testMode=true.','preview_unavailable');
  return true;
 }
 private function lock(int $customer,string $key,callable $action){$name='resale_'.substr($this->secrets->digest('lock',$customer.':'.$key),0,45);if((int)$this->db->query('SELECT GET_LOCK(?,0) AS acquired',[$name])->getRow()->acquired!==1)throw new CustomerFlowException(429,'Verification is already in progress. Please wait.','busy');try{return $action();}finally{$this->db->query('SELECT RELEASE_LOCK(?)',[$name]);}}
 private function proof(array $identity,array $property,?string $booking=null):array
 {
  try{$proof=CurrentBookingApi::validate(['success'=>true,'data'=>$this->provider->verify($identity,$property['erp_property_id'],$booking)],$property['erp_property_id'],$property['erp_customer_id'],$booking);}catch(\Throwable $e){throw $this->unavailable();}
  if($proof['resale_eligible']!==true||$proof['head_id']!==$property['erp_head_id']||$proof['subhead_id']!==$property['erp_subhead_id'])throw new CustomerFlowException(409,'This booking or Head/Subhead is no longer eligible. Refresh your properties.','booking_not_eligible');
  return $proof;
 }
 public function request(array $user,array $property,array $identity,array $input,string $key,string $binding,string $ip,?array $context=null):array
 {
  $previewOnly=$this->previewOnly();
  $customer=(int)$user['id'];if(!preg_match('/\A[a-f0-9]{64}\z/D',$key)||!$property['eligible']||!in_array($property['booking_in_process']??null,['yes','no'],true)||(int)$identity['erp_source_id']!==$property['erp_source_id']||(string)$identity['erp_customer_id']!==$property['erp_customer_id']||!$this->db->table('customer_erp_links')->where('portal_customer_id',$customer)->where('erp_source_id',$property['erp_source_id'])->where('erp_customer_id',$property['erp_customer_id'])->countAllResults())throw new CustomerFlowException(403,'Choose a currently eligible property from your verified portfolio.');
  if(!$this->db->query('SELECT m.id FROM erp_project_mappings m'.ErpVisibility::joins().' WHERE m.id=? AND m.erp_source_id=? AND m.erp_head_id=? AND m.erp_subhead_id=? AND '.ErpVisibility::mappingSql(),[$property['mapping_id'],$property['erp_source_id'],$property['erp_head_id'],$property['erp_subhead_id']])->getRowArray())throw new CustomerFlowException(409,'This project mapping no longer permits resale verification.');
  $listing=$context?(new ListingLifecycle($this->db))->lockTarget($customer,$context,$property):null;
   $submission=new ResaleSubmission($this->db);$draft=$submission->validateDraft($input);if($draft['listing_purpose']!==($property['listing_purpose']??'sale'))throw new CustomerFlowException(409,'The listing purpose changed. Open a fresh property review.');(new ResalePhotos($this->db))->selection($customer,$draft['photos'],$key,$listing,$binding);$preview=$submission->preview($user,$property,$draft,$listing);
   $scope=$context?'listing:'.$context['listing_id']:'property:'.$property['erp_source_id'].':'.$property['erp_property_id'].':'.$draft['listing_purpose'];
  return $this->lock($customer,$scope,function()use($customer,$property,$identity,$draft,$preview,$key,$binding,$ip,$context,$scope,$previewOnly){
   $now=$this->now();$this->rate->consume(['resale_issue_customer'=>[(string)$customer,5],'resale_issue_ip'=>[$ip,10]],$now);
   if($this->db->table('resale_otp_challenges')->where('portal_customer_id',$customer)->where('submission_key',$key)->where('resend_after >',$now)->where('consumed_at',null)->countAllResults())throw new CustomerFlowException(429,'Wait at least 60 seconds before requesting another property code.','cooldown');
   $proof=$this->proof($identity,$property,$property['booking']);$this->rate->consume(['resale_issue_mobile'=>[$proof['registered_mobile'],3]],$now);
   $otp=(string)random_int(100000,999999);$id=bin2hex(random_bytes(32));$expires=$now+max(1,min(300,$this->settings->otpLifetime));$after=$now+max(60,$this->settings->resendCooldown);
   $payload=['property'=>$property,'identity'=>$identity,'draft'=>$draft,'proof'=>$proof,'preview'=>$preview,'context'=>$context];
   $this->db->transBegin();try{
    $this->db->table('resale_otp_challenges')->where('portal_customer_id',$customer)->groupStart()->where('submission_key',$key)->orWhere('authorization_scope',$scope)->groupEnd()->where('consumed_at',null)->update(['consumed_at'=>$now,'otp_hash'=>'','payload'=>'']);
    $this->db->table('resale_otp_challenges')->insert(['id'=>$id,'portal_customer_id'=>$customer,'authorization_scope'=>$scope,'session_hash'=>$this->secrets->digest('resale_session',$binding),'submission_key'=>$key,'otp_hash'=>$this->secrets->otpHash($otp),'payload'=>$this->secrets->encrypt($payload),'delivery_state'=>'pending','expires_at'=>$expires,'resend_after'=>$after,'created_at'=>$now]);
    if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('Challenge storage failed.');
   }catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $this->unavailable();}
   try{if(!$previewOnly)$this->sms->sendCode($proof['registered_mobile'],$otp);if(!$this->db->table('resale_otp_challenges')->where('id',$id)->update(['delivery_state'=>$previewOnly?'preview':'sent']))throw new \RuntimeException('Property OTP delivery state could not be saved.');}
   catch(\Throwable $e){log_message('error','Property OTP delivery failed: type={exception_type} origin={origin_file}:{origin_line}.',['exception_type'=>get_class($e),'origin_file'=>basename($e->getFile()),'origin_line'=>$e->getLine()]);$this->invalidate($id,$customer,$binding);throw new CustomerFlowException(503,$previewOnly?'The property verification code could not be prepared. Please try again later.':'The property verification SMS could not be sent. Please try again later.',$previewOnly?'verification_unavailable':'sms_unavailable');}
   $fields=[];
   if($previewOnly){LocalOtpPreview::remember('resale',$id,$otp,$expires,'Testing only: no property verification SMS was sent. Enter the code shown above.');$fields=['preview_only'=>true]+LocalOtpPreview::fields('resale',$id);}
   return ['id'=>$id,'masked_mobile'=>CustomerIdentity::mask($proof['registered_mobile']),'expires_at'=>$expires,'resend_after'=>$after,'server_now'=>$this->now()]+$fields;
  });
 }
 private function row(string $id,int $customer,string $binding,bool $verified=false,bool $locked=false):array
 {
  if(!preg_match('/\A[a-f0-9]{64}\z/D',$id))throw new CustomerFlowException(410,'Property verification expired. Request a fresh code.','verification_expired');
  $row=$this->db->query('SELECT * FROM resale_otp_challenges WHERE id=?'.($locked?' FOR UPDATE':''),[$id])->getRowArray();
  if(!$row||(int)$row['portal_customer_id']!==$customer||!hash_equals($row['session_hash'],$this->secrets->digest('resale_session',$binding))||$row['consumed_at']!==null||(int)$row['expires_at']<=$this->now()||!self::deliveryUsable($row['delivery_state'],$this->settings)||(int)$row['attempts']>=min(5,$this->settings->maximumAttempts)||($verified&&$row['verified_at']===null))throw new CustomerFlowException(410,'Property verification expired or is unavailable. Request a fresh code.','verification_expired');
  return $row;
 }
 public function invalidate(string $id,int $customer,string $binding):void{$this->db->table('resale_otp_challenges')->where('id',$id)->where('portal_customer_id',$customer)->where('session_hash',$this->secrets->digest('resale_session',$binding))->update(['consumed_at'=>$this->now(),'otp_hash'=>'','payload'=>'']);LocalOtpPreview::forget('resale',$id);}
 public function verify(string $id,string $otp,int $customer,string $binding,string $ip):void
 {
  if(!preg_match('/\A[0-9]{6}\z/D',$otp))throw new CustomerFlowException(422,'Enter the six-digit property verification code.');
  $this->rate->consume(['resale_verify_ip'=>[$ip,30]],$this->now());$this->db->transBegin();
  try{
   $row=$this->row($id,$customer,$binding,false,true);
   if($row['verified_at']!==null)throw new CustomerFlowException(409,'This code was already verified. Open your listing preview.');
   if(!$this->secrets->otpMatches($otp,$row['otp_hash'])){
    $attempts=(int)$row['attempts']+1;$values=['attempts'=>$attempts];if($attempts>=min(5,$this->settings->maximumAttempts))$values+=['consumed_at'=>$this->now(),'otp_hash'=>'','payload'=>''];
    $this->db->table('resale_otp_challenges')->where('id',$id)->update($values);$this->db->transCommit();throw new CustomerFlowException(422,'The property verification code is invalid or expired.','invalid_otp');
   }
   $this->db->table('resale_otp_challenges')->where('id',$id)->update(['verified_at'=>$this->now(),'otp_hash'=>'']);if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('Verification storage failed.');LocalOtpPreview::forget('resale',$id);
  }catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
 }
 public function authorized(string $id,int $customer,string $binding):array{$row=$this->row($id,$customer,$binding,true);return ['row'=>$row,'payload'=>$this->secrets->decrypt($row['payload'])];}
 public function resend(string $id,array $user,string $binding,string $ip):array
 {
  $row=$this->row($id,(int)$user['id'],$binding);if($row['verified_at']!==null)throw new CustomerFlowException(409,'Open your verified listing preview.');if((int)$row['resend_after']>$this->now())throw new CustomerFlowException(429,'Please wait before requesting another code.','cooldown');
  $payload=$this->secrets->decrypt($row['payload']);return $this->request($user,$payload['property'],$payload['identity'],$payload['draft'],$row['submission_key'],$binding,$ip,$payload['context']??null);
 }
 public function publish(string $id,array $user,array $property,string $binding):array
 {
  $authorization=$this->authorized($id,(int)$user['id'],$binding);$payload=$authorization['payload'];
  if($property['erp_source_id']!==$payload['property']['erp_source_id']||$property['erp_property_id']!==$payload['property']['erp_property_id']||$property['erp_customer_id']!==$payload['property']['erp_customer_id'])throw new CustomerFlowException(403,'This authorization belongs to another property.');
  try{
   $fresh=$this->proof($payload['identity'],$property,$payload['proof']['current_booking_ref']);
   if(!hash_equals(self::fingerprint($payload['proof']),self::fingerprint($fresh)))throw new CustomerFlowException(409,'The booking, owner, registered mobile or eligibility changed. Request a fresh property code.','booking_changed');
   return (new ResaleSubmission($this->db))->publishVerified($user,$property,$payload['draft'],$authorization['row']['submission_key'],['challenge_id'=>$id,'session_hash'=>$authorization['row']['session_hash'],'proof'=>$fresh]);
  }catch(\Throwable $e){$this->invalidate($id,(int)$user['id'],$binding);throw $e;}
 }
}
