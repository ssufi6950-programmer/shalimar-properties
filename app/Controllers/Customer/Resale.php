<?php
namespace App\Controllers\Customer;
final class Resale extends PortalController
{
 public function start(){return redirect()->to(url_to('customer.properties'));}
 public function index():string{return $this->listingTable(false);}
 public function history():string{return $this->listingTable(true);}
 private function listingTable(bool $history):string
 {
  $data=(new \App\Services\CustomerListingReadModel(db_connect()))->page((int)service('customerSession')->user()['id'],$this->request->getGet(),$history);
  return $this->renderPortal('resale/live_index',$data+['pageTitle'=>$history?'Listing History':'My Listings','activeNav'=>$history?'history':'resale']);
 }
 public function edit(int $id){return $this->editor($id,'edit');}
 public function republish(int $id){return $this->editor($id,'republish');}
 private function editor(int $id,string $operation)
 {
  $user=service('customerSession')->user();$lifecycle=new \App\Services\ListingLifecycle(db_connect());
  try{$context=$lifecycle->context((int)$user['id'],$id,$operation);$row=$lifecycle->owned((int)$user['id'],$id);$property=$lifecycle->property((int)$user['id'],$id,service('customerPortfolio'),service('customerOtp')->links((int)$user['id']));$property['id']=service('customerSession')->reference($property);
   $prepared=$this->retainPreparedReview(['reference'=>$property['id'],'source_id'=>$property['erp_source_id'],'property_id'=>$property['erp_property_id'],'mapping_id'=>$property['mapping_id'],'customer_id'=>(int)$user['id'],'listing_purpose'=>$row['listing_purpose'],'expires_at'=>time()+600,'submission_key'=>bin2hex(random_bytes(32)),'context'=>$context]);$key=$prepared['submission_key'];
   $payload=json_decode($row['payload'],true);$initial=['listing_purpose'=>$row['listing_purpose'],'asking_price'=>(string)($row['asking_price']??$payload['price']),'rental'=>$payload['rental']??[],'negotiable'=>($payload['negotiable']??false)===true,'remarks'=>$payload['description']??'','selling_method'=>$row['selling_method'],'public_contact'=>$row['selling_method']==='self_sell'?($payload['poster']['phone']??''):'','public_email'=>$row['selling_method']==='self_sell'&&($payload['poster']['email_public']??null)===true?($payload['poster']['email']??''):'','portal_agent_id'=>$row['portal_agent_id'],'agency_erp_link_id'=>$row['agency_erp_link_id'],'photos'=>(new \App\Services\ResalePhotos(db_connect()))->items($row)];
   return $this->renderPortal('resale/live_create',['pageTitle'=>$operation==='edit'?'Edit your resale listing':'Republish your withdrawn listing','activeNav'=>'resale','selectedProperty'=>$property,'submissionKey'=>$key,'agencyOptions'=>$this->agencyOptions($property['erp_source_id']),'agentOptions'=>service('shalimarAgents')->eligibleForProperty($property['erp_source_id']),'initialDraft'=>$initial,'operation'=>$operation,'reviewFeedback'=>$payload['review_note']??'']);
  }catch(\App\Exceptions\CustomerFlowException $e){return $this->response->setStatusCode($e->httpStatus)->setBody($this->renderPortal('properties/unavailable',['message'=>$e->getMessage()]));}
 }
 private function agencyOptions(int $source):array
 {
  $rows=service('agencyDirectory')->eligibleForSource($source);foreach($rows as&$row){$row['selectable']=true;$row['unavailable_reason']='';try{\App\Services\ListingSellerContact::requireBusiness($row);}catch(\InvalidArgumentException $e){$row['selectable']=false;$row['unavailable_reason']='Public business contact needs correction';}}unset($row);return $rows;
 }
 private function retainPreparedReview(array $candidate):array
 {
  $previous=session()->get('customer_prepared_resale');
  if(is_array($previous)&&($previous['expires_at']??0)>time()&&is_string($previous['submission_key']??null)){
   $same=true;foreach(['reference','source_id','property_id','mapping_id','customer_id','listing_purpose','context']as$key)if(($previous[$key]??null)!==($candidate[$key]??null)){$same=false;break;}
   if($same)return $previous;
  }
  session()->set('customer_prepared_resale',$candidate);return $candidate;
 }
 private function preparedProperty(array $prepared,string $reference):array
 {
  if(empty($prepared['context']))return $this->currentProperty($reference,$prepared['listing_purpose']??'sale');
  $user=service('customerSession')->user();$property=(new \App\Services\ListingLifecycle(db_connect()))->property((int)$user['id'],(int)$prepared['context']['listing_id'],service('customerPortfolio'),service('customerOtp')->links((int)$user['id']));$property['id']=$reference;return $property;
 }
 public function transition(int $id,string $action)
 {
  if($action!=='withdraw')throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
  return $this->action(function()use($id,$action){$data=$this->input();$version=$data['version']??null;if(($data['confirmed']??null)!==true||!is_int($version)||$version<1)throw new \InvalidArgumentException('Confirm this action on the current listing.');(new \App\Services\ListingLifecycle(db_connect()))->transition((int)service('customerSession')->user()['id'],$id,$action,$version);return $this->json(['message'=>$action==='sold'?'Listing marked as Sold in the portal. No ERP ownership transfer was performed.':'Listing withdrawn. Your property and listing history are preserved.','redirect'=>url_to('customer.resale')]);});
 }
 public function uploadPhotos()
 {
  return $this->action(function(){$prepared=session()->get('customer_prepared_resale');$key=$this->request->getPost('submission_key');$user=service('customerSession')->user();if(!is_array($prepared)||($prepared['customer_id']??0)!==(int)$user['id']||!is_string($key)||!hash_equals($prepared['submission_key'],$key)||$prepared['expires_at']<time())throw new \InvalidArgumentException('Open your property review again before uploading.');
   (new \App\Services\CustomerRateLimiter(db_connect(),new \App\Libraries\CustomerSecrets(config('CustomerPortal')->identityKey)))->consume(['photo_customer'=>[(string)$user['id'],30],'photo_ip'=>[$this->request->getIPAddress(),60]],time());$files=$this->request->getFileMultiple('photos')??[];$cfg=config('CustomerPortal');if(!$files||count($files)>$cfg->photoMaximumCount)throw new \InvalidArgumentException('Choose up to '.$cfg->photoMaximumCount.' photos.');$db=db_connect();$count=$db->table('resale_photos')->where('portal_customer_id',$user['id'])->where('submission_key',$key)->countAllResults();if($count+count($files)>$cfg->photoMaximumCount*3)throw new \InvalidArgumentException('This draft reached its upload limit. Open a fresh review.');$photos=new \App\Services\ResalePhotos($db);$photos->prune(20);$result=[];foreach($files as$file)$result[]=$photos->upload($file,(int)$user['id'],$key,service('customerSession')->binding());return $this->json(['photos'=>$result],201);});
 }
 public function photo(string $id)
 {
  try{$file=(new \App\Services\ResalePhotos(db_connect()))->customerFile($id,(int)service('customerSession')->user()['id'],service('customerSession')->binding());return $this->response->setHeader('Cache-Control','no-store, private')->setHeader('X-Content-Type-Options','nosniff')->setContentType($file['mime'])->setBody(file_get_contents($file['path']));}catch(\App\Exceptions\CustomerFlowException $e){return $this->response->setStatusCode(404)->setBody('Image unavailable.');}
 }
 public function create()
 {
  try{
   $purpose=\App\Services\ListingPolicy::purpose($this->request->getGet('purpose')??'sale');$ref=$this->request->getGet('property');if(!is_string($ref)||$ref==='')return redirect()->to(url_to('customer.properties.choose').'?purpose='.$purpose);
   $property=$this->currentProperty($ref,$purpose);if(!$property['eligible'])return $this->response->setStatusCode(409)->setBody($this->renderPortal('properties/unavailable',['message'=>$property['reason']]));
   $prepared=['reference'=>$ref,'source_id'=>$property['erp_source_id'],'property_id'=>$property['erp_property_id'],'mapping_id'=>$property['mapping_id'],'customer_id'=>(int)service('customerSession')->user()['id'],'listing_purpose'=>$purpose,'expires_at'=>time()+600,'submission_key'=>bin2hex(random_bytes(32))];$prepared=$this->retainPreparedReview($prepared);
   try{$agents=service('shalimarAgents')->eligibleForProperty($property['erp_source_id']);}catch(\InvalidArgumentException $e){$agents=[];}
   return $this->renderPortal('resale/live_create',['pageTitle'=>'Review verified property','activeNav'=>'resale','selectedProperty'=>$property,'agencyOptions'=>$this->agencyOptions($property['erp_source_id']),'agentOptions'=>$agents,'submissionKey'=>$prepared['submission_key']]);
  }catch(\CodeIgniter\Exceptions\PageNotFoundException $e){return redirect()->to(url_to('customer.properties.choose').'?purpose='.($purpose??'sale'));}catch(\InvalidArgumentException $e){return $this->response->setStatusCode(422)->setBody($this->renderPortal('properties/unavailable',['message'=>$e->getMessage()]));}catch(\App\Exceptions\CustomerFlowException $e){return $this->response->setStatusCode($e->httpStatus)->setBody($this->renderPortal('properties/unavailable',['message'=>$e->getMessage()]));}
 }
 public function prepare(){return $this->action(function(){$data=$this->input();if(!is_string($data['reference']??null))throw new \InvalidArgumentException('Choose a property from your live portfolio.');try{$purpose=\App\Services\ListingPolicy::purpose($data['listing_purpose']??'sale');$property=$this->currentProperty($data['reference'],$purpose);}catch(\CodeIgniter\Exceptions\PageNotFoundException $e){return $this->json(['message'=>'This property is unavailable or no longer belongs to you. Refresh your portfolio.'],404);}if(!$property['eligible'])return $this->json(['message'=>$property['reason']],409);return $this->json(['redirect'=>url_to('customer.resale.create').'?property='.rawurlencode($data['reference']).'&purpose='.$purpose]);});}
 public function agencies(){return $this->action(function(){$reference=$this->request->getGet('property');if(!is_string($reference))throw new \InvalidArgumentException('Choose a property from My Properties.');$property=$this->currentProperty($reference,\App\Services\ListingPolicy::purpose($this->request->getGet('purpose')??'sale'));if(!$property['eligible'])return $this->json(['message'=>$property['reason']],409);return $this->json(['agencies'=>service('agencyDirectory')->eligibleForSource($property['erp_source_id'])]);});}
 public function selectAgency(){return $this->action(function(){$data=$this->input();$reference=$data['reference']??null;$link=$data['agency_link_id']??null;if(!is_string($reference)||!is_string($link)||!preg_match('/\A[1-9][0-9]{0,17}\z/D',$link))throw new \InvalidArgumentException('Choose a property and an authorized agency.');$property=$this->currentProperty($reference,\App\Services\ListingPolicy::purpose($data['listing_purpose']??'sale'));if(!$property['eligible'])return $this->json(['message'=>$property['reason']],409);$agency=service('agencyDirectory')->requireEligible($property['erp_source_id'],(int)$link);$prepared=session()->get('customer_prepared_resale');if(!is_array($prepared)||($prepared['reference']??null)!==$reference||($prepared['listing_purpose']??'sale')!==$property['listing_purpose']||($prepared['expires_at']??0)<time())return $this->json(['message'=>'Open the property resale review again before choosing an agency.'],409);$prepared['agency_erp_link_id']=(int)$agency['agency_erp_link_id'];$prepared['portal_agency_id']=(int)$agency['portal_agency_id'];session()->set('customer_prepared_resale',$prepared);return $this->json(['message'=>'Authorized agency selected for this property.','agency_name'=>$agency['agency_name']]);});}
 public function details(string $id){$row=db_connect()->table('portal_listings l')->select('l.*,s.name AS source_name,p.name AS project_name')->join('erp_sources s','s.id=l.erp_source_id','left')->join('portal_projects p','p.slug=l.project_slug','left')->where('l.id',$id)->where('l.portal_customer_id',(int)service('customerSession')->user()['id'])->get()->getRowArray();if(!$row)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();return $this->renderPortal('resale/live_details',['pageTitle'=>'Your resale listing','activeNav'=>'resale','records'=>(new \App\Services\ListingLifecycle(db_connect()))->decorate([$row]),'history'=>db_connect()->table('listing_events')->select('event,from_status,to_status,created_at')->where('listing_id',$row['id'])->orderBy('id','DESC')->get()->getResultArray()]);}
 public function agents()
 {
  return $this->action(function(){$reference=$this->request->getGet('property');if(!is_string($reference))throw new \InvalidArgumentException('Choose a property from My Properties.');$property=$this->currentProperty($reference,\App\Services\ListingPolicy::purpose($this->request->getGet('purpose')??'sale'));if(!$property['eligible'])return $this->json(['message'=>$property['reason']],409);$agents=array_map(static fn($agent)=>array_intersect_key($agent,array_flip(['portal_agent_id','name','mobile','email'])),service('shalimarAgents')->eligibleForProperty($property['erp_source_id']));return $this->json(['agents'=>$agents]);});
 }
 public function submit()
 {
  return $this->action(function(){
   $data=$this->input();$reference=$data['reference']??null;$key=$data['submission_key']??null;$prepared=session()->get('customer_prepared_resale');$user=service('customerSession')->user();
   if(!is_string($reference)||!is_string($key)||!is_array($prepared)||($prepared['reference']??null)!==$reference||($prepared['customer_id']??null)!==(int)$user['id']||!is_string($prepared['submission_key']??null)||!hash_equals($prepared['submission_key'],$key))throw new \InvalidArgumentException('Open the property resale review again before submitting.');
   if($existing=service('resaleSubmission')->existing($key,(int)$user['id']))return $this->json(['redirect'=>url_to('customer.resale.success').'?listing='.$existing['id'],'listing_id'=>$existing['id'],'status'=>$existing['status']]);
   if(\App\Services\ListingPolicy::purpose($data['listing_purpose']??'sale')!==($prepared['listing_purpose']??'sale'))throw new \InvalidArgumentException('The listing purpose changed. Open the correct property action again.');
   if(($prepared['expires_at']??0)<time())return $this->json(['message'=>'Your property review expired. Open the property from My Properties again.'],409);
   try{$property=$this->preparedProperty($prepared,$reference);}catch(\CodeIgniter\Exceptions\PageNotFoundException $e){return $this->json(['message'=>'This property is unavailable or no longer belongs to you.'],404);}
   if(!$property['eligible'])return $this->json(['message'=>$property['reason']],409);
   if($property['erp_source_id']!==$prepared['source_id']||$property['erp_property_id']!==$prepared['property_id']||$property['mapping_id']!==$prepared['mapping_id'])throw new \InvalidArgumentException('The property mapping changed. Review the property again.');
   $identity=null;foreach(service('customerOtp')->links((int)$user['id'])as$link)if($link['erp_source_id']===$property['erp_source_id']&&$link['erp_customer_id']===$property['erp_customer_id']){$identity=$link;break;}
   if(!$identity)throw new \InvalidArgumentException('Verify this property ERP identity again before continuing.');
   $challenge=service('resaleOwnershipOtp')->request($user,$property,$identity,$data,$key,service('customerSession')->binding(),$this->request->getIPAddress(),$prepared['context']??null);
   return $this->json(['message'=>!empty($challenge['preview_only'])?'Property verification testing mode: use the code displayed on this page. No SMS was sent.':'A property verification code was sent to the current booking owner registered mobile.','challenge'=>$challenge],202);
  });
 }
 public function verifyOwnershipOtp()
 {
  return $this->action(function(){$data=$this->input();if(!is_string($data['challenge_id']??null)||!is_string($data['otp']??null))throw new \InvalidArgumentException('Enter your property verification code.');$user=service('customerSession')->user();service('resaleOwnershipOtp')->verify($data['challenge_id'],$data['otp'],(int)$user['id'],service('customerSession')->binding(),$this->request->getIPAddress());return $this->json(['redirect'=>site_url('customer/resale/preview').'?challenge='.rawurlencode($data['challenge_id'])]);});
 }
 public function invalidateOwnershipOtp()
 {
  return $this->action(function(){$data=$this->input();$id=$data['challenge_id']??null;if(!is_string($id)||!preg_match('/\A[a-f0-9]{64}\z/D',$id))throw new \InvalidArgumentException('Choose your verification request.');service('resaleOwnershipOtp')->invalidate($id,(int)service('customerSession')->user()['id'],service('customerSession')->binding());return $this->json(['message'=>'Previous authorization invalidated. Verify the revised details with a fresh code.']);});
 }
 public function resendOwnershipOtp()
 {
  return $this->action(function(){$data=$this->input();if(!is_string($data['challenge_id']??null))throw new \InvalidArgumentException('Choose your property verification request.');$challenge=service('resaleOwnershipOtp')->resend($data['challenge_id'],service('customerSession')->user(),service('customerSession')->binding(),$this->request->getIPAddress());return $this->json(['challenge'=>$challenge,'message'=>!empty($challenge['preview_only'])?'A fresh testing code is displayed on this page. No SMS was sent.':'A fresh property verification code was sent.'],202);});
 }
 public function preview()
 {
  $id=$this->request->getGet('challenge');if(!is_string($id))throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
  try{$authorization=service('resaleOwnershipOtp')->authorized($id,(int)service('customerSession')->user()['id'],service('customerSession')->binding());return $this->renderPortal('resale/public_preview',['pageTitle'=>'Preview your public listing','activeNav'=>'resale','preview'=>$authorization['payload']['preview'],'challengeId'=>$id]);}
  catch(\App\Exceptions\CustomerFlowException $e){return $this->response->setStatusCode($e->httpStatus)->setBody($this->renderPortal('properties/unavailable',['message'=>$e->getMessage()]));}
 }
 public function publish()
 {
  return $this->action(function(){
   $data=$this->input();$id=$data['challenge_id']??null;if(!is_string($id)||!preg_match('/\A[a-f0-9]{64}\z/D',$id)||($data['confirmed']??null)!==true)throw new \InvalidArgumentException('Confirm your verified public listing preview before publishing.');
   $user=service('customerSession')->user();if(array_diff(array_keys($data),['challenge_id','confirmed',csrf_token()])){service('resaleOwnershipOtp')->invalidate($id,(int)$user['id'],service('customerSession')->binding());throw new \InvalidArgumentException('Changed listing details require fresh OTP verification.');}$existing=db_connect()->table('portal_listings')->select('id,status,listing_purpose,review_state')->where('resale_verification_id',$id)->where('portal_customer_id',$user['id'])->get()->getRowArray();
   if($existing)return $this->json(['listing_id'=>$existing['id'],'status'=>$existing['status'],'redirect'=>url_to('customer.resale.success').'?listing='.$existing['id']]);
   $binding=service('customerSession')->binding();$authorization=service('resaleOwnershipOtp')->authorized($id,(int)$user['id'],$binding);
   try{$property=$this->preparedProperty(['listing_purpose'=>$authorization['payload']['draft']['listing_purpose']??'sale','context'=>$authorization['payload']['context']??null],$authorization['payload']['property']['id']);if(!$property['eligible'])throw new \InvalidArgumentException($property['reason']);}
   catch(\Throwable $e){service('resaleOwnershipOtp')->invalidate($id,(int)$user['id'],$binding);throw $e;}
   $listing=service('resaleOwnershipOtp')->publish($id,$user,$property,$binding);
   return $this->json(['message'=>\App\Services\ResaleSubmission::CONFIRMATION,'review_state'=>$listing['review_state']??'under_review','listing_id'=>$listing['id'],'status'=>$listing['status'],'redirect'=>url_to('customer.resale.success').'?listing='.$listing['id']],201);
  });
 }
 public function success()
 {
  $id=$this->request->getGet('listing');if(!is_string($id)||!preg_match('/\A[1-9][0-9]{0,17}\z/D',$id))throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();$row=db_connect()->table('portal_listings')->where('id',$id)->where('portal_customer_id',(int)service('customerSession')->user()['id'])->get()->getRowArray();if(!$row)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();return $this->renderPortal('resale/submitted',['pageTitle'=>'Resale listing submitted','activeNav'=>'resale','listing'=>$row]);
 }
}
