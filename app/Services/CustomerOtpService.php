<?php
namespace App\Services;
use App\Libraries\CustomerIdentity;
use App\Libraries\CustomerSecrets;
use App\Exceptions\CustomerFlowException;
use CodeIgniter\Database\BaseConnection;
final class CustomerOtpService
{
 private CustomerSecrets $secrets;private CustomerRateLimiter $rate;
 public function __construct(private BaseConnection $db,private CustomerIdentityProvider $provider,private CustomerSmsGateway $sms,private \Config\CustomerPortal $settings,private $clock=null){$this->secrets=new CustomerSecrets($settings->identityKey);$this->rate=new CustomerRateLimiter($db,$this->secrets);}
 private function now():int{return $this->clock?($this->clock)():time();}
 private function unavailable():CustomerFlowException{return new CustomerFlowException(503,'Verification is temporarily unavailable. Please try again later.','service_unavailable');}
 private function previewOnly():bool
 {
  if(!$this->settings->loginOtpPreviewOnly)return false;
  if(!$this->settings->showOtpPreview||!config('Sms')->testMode){log_message('error','Login preview-only mode requires showOtpPreview=true and sms.testMode=true.');throw $this->unavailable();}
  return true;
 }
 private function previewNote(bool $matched):string
 {
  if(!$matched)return 'Testing only: this CNIC/mobile did not match ERP records. No SMS was sent. This code cannot sign you in.';
  return $this->previewOnly()?'Testing only: SMS delivery is disabled for login. Enter the code shown above.':'';
 }
 private function lock(string $pair,callable $action){$key='customer_'.substr($this->secrets->digest('lock',$pair),0,45);if((int)$this->db->query('SELECT GET_LOCK(?,0) AS acquired',[$key])->getRow()->acquired!==1)throw new CustomerFlowException(429,'A verification request is already in progress. Please wait.','busy');try{return $action();}finally{$this->db->query('SELECT RELEASE_LOCK(?)',[$key]);}}
 private function limits(string $cnicHash,string $mobileHash,string $ip):void{$this->rate->consume(['issue_cnic'=>[$cnicHash,5],'issue_mobile'=>[$mobileHash,3],'issue_ip'=>[$ip,10]],$this->now());}
 private function requireCurrentOwnership(array $matches,array $unavailable=[]):void
 {
  $ownership=(new CustomerPortfolio($this->db,$this->provider))->hasCurrent($matches,$unavailable);
  if($ownership['has_properties'])return;
  if($ownership['unavailable'])throw $this->unavailable();
  throw new CustomerFlowException(422,'No current property was found for this CNIC and mobile number. Please check your details or contact your ERP office.','no_properties');
 }
 public function request(string $cnicInput,string $mobileInput,string $binding,string $ip,?int $targetCustomer=null):array
 {
  $cnic=CustomerIdentity::cnic($cnicInput);$mobile=CustomerIdentity::mobile($mobileInput);$cnicHash=$this->secrets->digest('cnic',$cnic);$mobileHash=$this->secrets->digest('mobile',$mobile);
  return $this->lock('issue:'.$cnicHash.':'.$mobileHash,function()use($cnic,$mobile,$cnicHash,$mobileHash,$binding,$ip,$targetCustomer){
   $this->limits($cnicHash,$mobileHash,$ip);
   if($targetCustomer!==null){$target=$this->db->table('portal_customers')->where('id',$targetCustomer)->where('status','active')->get()->getRowArray();if(!$target||!hash_equals($target['cnic_hash'],$cnicHash))throw new CustomerFlowException(422,'Use the CNIC associated with your account to verify another ERP identity.');}
   try{$lookup=$this->provider->identities($cnic,$mobile);}catch(\Throwable $e){log_message('error','Customer OTP ERP provider threw: type={exception_type}.',['exception_type'=>get_class($e)]);throw $this->unavailable();}
   $matches=$lookup['matches']??[];$unavailable=$lookup['unavailable']??[];
   if(!$matches&&$unavailable){$codes=array_values(array_unique(array_filter(array_map(static fn($source)=>is_array($source)?($source['source_code']??null):null,$unavailable),'is_string')));log_message('error','Customer OTP has no identity match and ERP source(s) unavailable: sources={source_codes}.',['source_codes'=>$codes?implode(',',$codes):'unknown']);throw $this->unavailable();}
   // The trusted provider returns only identities whose registered mobile matched the submitted mobile.
   $unique=[];foreach($matches as$match){if(CustomerIdentity::mobile($match['mobile'])!==$mobile){log_message('error','Customer OTP provider returned a mobile validation mismatch.');throw $this->unavailable();}$unique[$match['erp_source_id'].':'.$match['erp_customer_id']]=$match;}$matches=array_values($unique);
   $this->requireCurrentOwnership($matches,$unavailable);
   $now=$this->now();$previous=$this->db->table('customer_otp_challenges')->where('cnic_hash',$cnicHash)->where('mobile_hash',$mobileHash)->where('resend_after >',$now)->where('delivery_state !=','failed')->where('consumed_at',null)->countAllResults();if($previous)throw new CustomerFlowException(429,'Please wait at least 60 seconds before requesting another code.','cooldown');
   $otp=(string)random_int(100000,999999);$id=bin2hex(random_bytes(32));$payload=['mobile'=>$mobile,'matches'=>$matches,'unavailable'=>$unavailable,'target_customer_id'=>$targetCustomer];
   $this->db->transBegin();try{
    $this->db->table('customer_otp_challenges')->where('cnic_hash',$cnicHash)->where('mobile_hash',$mobileHash)->where('consumed_at',null)->update(['consumed_at'=>$now,'otp_hash'=>'','payload'=>'']);
    $this->db->table('customer_otp_challenges')->insert(['id'=>$id,'session_hash'=>$this->secrets->digest('session',$binding),'cnic_hash'=>$cnicHash,'mobile_hash'=>$mobileHash,'otp_hash'=>$this->secrets->otpHash($otp),'payload'=>$this->secrets->encrypt($payload),'expires_at'=>$now+max(1,min(300,$this->settings->otpLifetime)),'resend_after'=>$now+max(60,$this->settings->resendCooldown),'delivery_state'=>'pending','created_at'=>$now]);
    if(!$this->db->transStatus())throw new \RuntimeException('Challenge storage failed.');$this->db->transCommit();
   }catch(\Throwable $e){log_message('error','Customer OTP challenge database write failed: type={exception_type}.',['exception_type'=>get_class($e)]);$this->db->transRollback();$this->db->resetTransStatus();throw $this->unavailable();}
   $this->deliver($id,$mobile,$otp,(bool)$matches);
   if($matches||$this->settings->showOtpPreview)LocalOtpPreview::remember('login',$id,$otp,$now+max(1,min(300,$this->settings->otpLifetime)),$this->previewNote((bool)$matches));
   return ['id'=>$id,'masked_mobile'=>CustomerIdentity::mask($mobile),'resend_after'=>$now+max(60,$this->settings->resendCooldown),'expires_at'=>$now+max(1,min(300,$this->settings->otpLifetime))];
  });
 }
 private function deliver(string $id,string $mobile,string $otp,bool $real):void
 {
  try{$preview=$real&&$this->previewOnly();if($real&&!$preview)$this->sms->sendCode($mobile,$otp);$stored=$this->db->table('customer_otp_challenges')->where('id',$id)->where('consumed_at',null)->update(['delivery_state'=>$real?($preview?'preview':'sent'):'decoy']);if(!$stored)throw new \RuntimeException('OTP delivery state could not be saved.');}
  catch(\Throwable $e){$reason=substr(preg_replace('/[^A-Za-z0-9 _.-]/','',$e->getMessage())??'unknown',0,120);log_message('error','Customer OTP delivery failed: type={exception_type} reason={reason}.',['exception_type'=>get_class($e),'reason'=>$reason]);$this->db->table('customer_otp_challenges')->where('id',$id)->update(['delivery_state'=>'failed','consumed_at'=>$this->now(),'otp_hash'=>'','payload'=>'']);throw $this->unavailable();}
 }
 private function row(string $id,string $binding,bool $forUpdate=false):array
 {
  if(!preg_match('/\A[a-f0-9]{64}\z/D',$id))throw new CustomerFlowException(410,'Your verification has expired. Please sign in again.','expired');
  $row=$this->db->query('SELECT * FROM customer_otp_challenges WHERE id=?'.($forUpdate?' FOR UPDATE':''),[$id])->getRowArray();
  if(!$row||!hash_equals($row['session_hash'],$this->secrets->digest('session',$binding))||$row['consumed_at']!==null||(int)$row['expires_at']<=$this->now()||!in_array($row['delivery_state'],['sent','decoy','preview'],true)||(int)$row['attempts']>=min(5,$this->settings->maximumAttempts))throw new CustomerFlowException(410,'Your verification has expired or is no longer valid. Please sign in again.','expired');
  if($row['delivery_state']==='preview'&&!$this->previewOnly())throw new CustomerFlowException(410,'Testing verification has ended. Please sign in again.','expired');return $row;
 }
 public function info(string $id,string $binding):array{$row=$this->row($id,$binding);$payload=$this->secrets->decrypt($row['payload']);return ['masked_mobile'=>CustomerIdentity::mask($payload['mobile']),'resend_after'=>(int)$row['resend_after'],'expires_at'=>(int)$row['expires_at'],'server_now'=>$this->now(),'preview_only'=>$row['delivery_state']==='preview']+LocalOtpPreview::fields('login',$id);}
 public function resend(string $id,string $binding,string $ip):array
 {
  $row=$this->row($id,$binding);
  return $this->lock('issue:'.$row['cnic_hash'].':'.$row['mobile_hash'],function()use($id,$binding,$ip){
   $row=$this->row($id,$binding);$now=$this->now();if((int)$row['resend_after']>$now)throw new CustomerFlowException(429,'Please wait before requesting another code.','cooldown');$this->limits($row['cnic_hash'],$row['mobile_hash'],$ip);
   $payload=$this->secrets->decrypt($row['payload']);$this->requireCurrentOwnership($payload['matches'],$payload['unavailable']);$otp=(string)random_int(100000,999999);$values=['otp_hash'=>$this->secrets->otpHash($otp),'attempts'=>0,'expires_at'=>$now+max(1,min(300,$this->settings->otpLifetime)),'resend_after'=>$now+max(60,$this->settings->resendCooldown),'delivery_state'=>'pending'];
   $this->db->transBegin();try{$locked=$this->row($id,$binding,true);if((int)$locked['resend_after']>$now)throw new CustomerFlowException(429,'Please wait before requesting another code.','cooldown');$this->db->table('customer_otp_challenges')->where('id',$id)->where('consumed_at',null)->update($values);if(!$this->db->transStatus())throw new \RuntimeException('Challenge update failed.');$this->db->transCommit();}catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
   $this->deliver($id,$payload['mobile'],$otp,(bool)$payload['matches']);if($payload['matches']||$this->settings->showOtpPreview)LocalOtpPreview::remember('login',$id,$otp,$values['expires_at'],$this->previewNote((bool)$payload['matches']));return ['id'=>$id,'masked_mobile'=>CustomerIdentity::mask($payload['mobile']),'resend_after'=>$values['resend_after'],'expires_at'=>$values['expires_at'],'server_now'=>$this->now(),'preview_only'=>(bool)$payload['matches']&&$this->previewOnly()]+LocalOtpPreview::fields('login',$id);
  });
 }
 public function verify(string $id,string $otp,string $binding,string $ip,?int $currentCustomer=null):array
 {
  if(!preg_match('/\A[0-9]{6}\z/D',$otp))throw new CustomerFlowException(422,'Enter the six-digit verification code.');
  $this->rate->consume(['verify_ip'=>[$ip,30]],$this->now());
  $this->db->transBegin();try{
   $row=$this->row($id,$binding,true);
   if(!$this->secrets->otpMatches($otp,$row['otp_hash'])){
    $attempts=(int)$row['attempts']+1;$values=['attempts'=>$attempts];if($attempts>=min(5,$this->settings->maximumAttempts))$values+=['consumed_at'=>$this->now(),'otp_hash'=>'','payload'=>''];
    $this->db->table('customer_otp_challenges')->where('id',$id)->update($values);$this->db->transCommit();throw new CustomerFlowException(422,'The verification code is invalid or expired.');
   }
   $payload=$this->secrets->decrypt($row['payload']);$this->db->table('customer_otp_challenges')->where('id',$id)->update(['consumed_at'=>$this->now(),'otp_hash'=>'','payload'=>'']);if(!$this->db->transStatus())throw new \RuntimeException('Verification storage failed.');$this->db->transCommit();
  }catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
  LocalOtpPreview::forget('login',$id);
  if(!$payload['matches'])throw new CustomerFlowException(422,'The verification code is invalid or expired.');
  if($payload['target_customer_id']!==null&&(int)$payload['target_customer_id']!==$currentCustomer)throw new CustomerFlowException(403,'Sign in again before linking another verified ERP identity.');
  $candidate=$this->candidate($row['cnic_hash'],$row['mobile_hash'],$payload['matches'],$payload['target_customer_id']);
  $identities=$candidate?$this->links((int)$candidate['id']):[];
  $byKey=[];foreach(array_merge($identities,$payload['matches'])as$identity)$byKey[$identity['erp_source_id'].':'.$identity['erp_customer_id']]=$identity;
  $portfolio=(new CustomerPortfolio($this->db,$this->provider))->all(array_values($byKey),$payload['unavailable']);
  if(!$portfolio['properties']){if($portfolio['unavailable'])throw $this->unavailable();throw new CustomerFlowException(403,'No currently owned properties found. Please contact your ERP office if your ownership has changed.','no_properties');}
  return $this->lock('account:'.$row['cnic_hash'],function()use($row,$payload,$portfolio){
   $this->db->transBegin();try{
    $customer=$this->candidate($row['cnic_hash'],$row['mobile_hash'],$payload['matches'],$payload['target_customer_id']);$date=date('Y-m-d H:i:s',$this->now());
    if(!$customer){$this->db->table('portal_customers')->insert(['cnic_hash'=>$row['cnic_hash'],'mobile_hash'=>$row['mobile_hash'],'display_name'=>$payload['matches'][0]['name']?:'Property owner','verified_mobile'=>$this->secrets->encrypt(['mobile'=>$payload['mobile']]),'created_at'=>$date,'updated_at'=>$date]);$id=(int)$this->db->insertID();}else$id=(int)$customer['id'];
    foreach($payload['matches']as$match){$existing=$this->db->query('SELECT * FROM customer_erp_links WHERE erp_source_id=? AND erp_customer_id=? FOR UPDATE',[$match['erp_source_id'],$match['erp_customer_id']])->getRowArray();if($existing&&(int)$existing['portal_customer_id']!==$id)throw new CustomerFlowException(409,'This verified ERP identity needs support assistance before it can be linked.','identity_conflict');$values=['verified_mobile'=>$this->secrets->encrypt(['mobile'=>$match['mobile']]),'verified_at'=>$date,'updated_at'=>$date];if($existing)$this->db->table('customer_erp_links')->where('id',$existing['id'])->update($values);else$this->db->table('customer_erp_links')->insert($values+['portal_customer_id'=>$id,'erp_source_id'=>$match['erp_source_id'],'erp_customer_id'=>$match['erp_customer_id'],'created_at'=>$date]);}
    if(!$this->db->transStatus())throw new \RuntimeException('Customer linking failed.');$this->db->transCommit();$customer=$this->db->table('portal_customers')->where('id',$id)->get()->getRowArray();return ['customer'=>$customer,'portfolio'=>$portfolio];
   }catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
  });
 }
 private function candidate(string $cnicHash,string $mobileHash,array $matches,?int $target):?array
 {
  $owners=[];foreach($matches as$match){$row=$this->db->table('customer_erp_links')->where('erp_source_id',$match['erp_source_id'])->where('erp_customer_id',$match['erp_customer_id'])->get()->getRowArray();if($row)$owners[(int)$row['portal_customer_id']]=true;}
  if(count($owners)>1||($target!==null&&$owners&&!isset($owners[$target])))throw new CustomerFlowException(409,'The verified ERP identities need support assistance before they can be linked.','identity_conflict');
  $id=$target??($owners?(int)array_key_first($owners):null);$query=$this->db->table('portal_customers');$row=$id!==null?$query->where('id',$id)->get()->getRowArray():$query->where('cnic_hash',$cnicHash)->where('mobile_hash',$mobileHash)->get()->getRowArray();
  if($id!==null&&!$row)throw $this->unavailable();if($row&&($row['status']!=='active'||!hash_equals($row['cnic_hash'],$cnicHash)))throw new CustomerFlowException(403,'This account cannot be accessed. Please contact support.');return $row;
 }
 public function links(int $customer):array
 {
  $rows=$this->db->query('SELECT l.*,s.name AS source_name,s.code AS source_code FROM customer_erp_links l JOIN erp_sources s ON s.id=l.erp_source_id WHERE l.portal_customer_id=? AND s.is_active=1 ORDER BY l.id',[$customer])->getResultArray();$out=[];
  foreach($rows as$row)$out[]=['portal_customer_id'=>$customer,'id'=>(int)$row['id'],'erp_source_id'=>(int)$row['erp_source_id'],'erp_customer_id'=>(string)$row['erp_customer_id'],'mobile'=>$this->secrets->decrypt($row['verified_mobile'])['mobile'],'source_name'=>$row['source_name'],'source_code'=>$row['source_code']];return $out;
 }
}
