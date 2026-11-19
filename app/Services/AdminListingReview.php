<?php
declare(strict_types=1);
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use App\Exceptions\CustomerFlowException;

/** Audited review with a post-commit assignment notification; no ERP writes or terminal reactivation. */
final class AdminListingReview
{
    private AdminListingPhotos $photos;
    public function __construct(private BaseConnection $db,private ?CustomerPortfolio $portfolio=null,private ?CurrentBookingProvider $booking=null,private ?AgentAssignmentSms $assignmentSms=null){$this->photos=new AdminListingPhotos($db);}
    public static function requiresOwnershipVerification():bool{return config('AdminReview')->requireOwnershipVerification;}
    public function row(int $id,bool $lock=false):array
    {
        $sql="SELECT l.* FROM portal_listings l WHERE l.id=? AND (l.portal_customer_id IS NOT NULL OR JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$._company_inventory.origin'))='admin_company_inventory') AND l.erp_source_id IS NOT NULL AND l.erp_property_id IS NOT NULL";
        $row=$this->db->query($sql.($lock?' FOR UPDATE':''),[$id])->getRowArray();if(!$row)throw new CustomerFlowException(404,'Listing not found.');return$row;
    }
    public function fingerprint(array $row):string
    {
        try{$contact=['eligible'=>true]+$this->representative($row);}catch(\InvalidArgumentException$e){$contact=['eligible'=>false,'reason'=>$e->getMessage()];}
        return hash('sha256',$this->photos->fingerprint($row).json_encode($contact,JSON_THROW_ON_ERROR));
    }
    public static function reviewable(array $row):bool{return !AdminListingDeletion::marked($row)&&in_array($row['status'],['pending','pending_approval'],true)&&in_array($row['deal'],['available','active'],true)&&$row['review_state']==='under_review';}
    private function requirePermission(int $actor,string $permission):void
    {
        if(!(new AdminPermissionService($this->db))->hasAllPermissions($actor,['resale_listings.view',$permission]))throw new CustomerFlowException(403,'You do not have permission for this review action.');
    }
    private function expected(array $row,int $version,string $fingerprint):void
    {
        if($version<1||!preg_match('/\A[a-f0-9]{64}\z/D',$fingerprint)||(int)$row['record_version']!==$version||!hash_equals($this->fingerprint($row),$fingerprint))throw new CustomerFlowException(409,'This listing or photo set changed. Reload and review the current version.','review_changed');
    }
    private function pending(array $row,bool $allowChanges=false):void
    {
        if(AdminListingDeletion::marked($row)||(new AdminListingDeletion($this->db))->deletion((int)$row['id']))throw new CustomerFlowException(409,'This listing was deleted. Its history is read-only.');
        if(self::reviewable($row)||($allowChanges&&$row['review_state']==='needs_changes'&&in_array($row['status'],['pending','pending_approval'],true)&&in_array($row['deal'],['available','active'],true)))return;
        throw new CustomerFlowException(409,'This listing is not awaiting this review action. Approved, rejected, withdrawn, unpublished and Sold records cannot be reopened here.','not_reviewable');
    }
    private function validateSubmission(array $row):array
    {
        if(CompanyPropertyListing::owned($row))return $this->validateContent($row);
        $challenge=$row['resale_verification_id']?$this->db->table('resale_otp_challenges')->where('id',$row['resale_verification_id'])->get()->getRowArray():null;
        $proofRow=$row;
        if($challenge)$proofRow+=['otp_customer_id'=>$challenge['portal_customer_id'],'otp_submission_key'=>$challenge['submission_key'],'otp_delivery_state'=>$challenge['delivery_state'],'otp_verified_at'=>$challenge['verified_at'],'otp_consumed_at'=>$challenge['consumed_at']];
        if(!PublicationVerification::submission($proofRow))throw new CustomerFlowException(409,'A complete current-owner submission is required. Request Changes so the customer can verify ownership and resubmit.','submission_proof_missing');
        $submission=$this->db->table('listing_events')->where('listing_id',$row['id'])->where('portal_customer_id',$row['portal_customer_id'])->where('authorization_id',$row['resale_verification_id'])->whereIn('event',['customer_submitted','customer_edit_submitted','customer_resubmitted','customer_changes_resubmitted'])->orderBy('id','DESC')->get()->getRowArray();
        $original=$submission?json_decode($submission['next_record'],true):null;if(!is_array($original))throw new CustomerFlowException(409,'The verified customer submission revision is unavailable. Request fresh customer verification.');
        foreach(['portal_customer_id','erp_source_id','erp_property_id','mapping_id','project_slug','listing_purpose','asking_price','selling_method','portal_agent_id','agent_erp_link_id','agent_source_id','portal_agency_id','agency_erp_link_id','owner_verification','submission_key']as$key)if(($row[$key]??null)!==($original[$key]??null))throw new CustomerFlowException(409,'Material listing identity or contact changed outside the verified customer submission. Request Changes.','submission_changed');
        $material=static function(string $json):array{$data=json_decode($json,true,128,JSON_THROW_ON_ERROR);foreach(['images','images_status','youtube_video_id','review_note','status','approved_at','published_at']as$key)unset($data[$key]);return$data;};
        if($material($row['payload'])!==$material($original['payload']))throw new CustomerFlowException(409,'Material listing content changed after customer verification. Request Changes.','submission_changed');
        return $this->validateContent($row);
    }
    private function validateContent(array $row):array
    {
        $payload=json_decode($row['payload'],true,128,JSON_THROW_ON_ERROR);$purpose=ListingPolicy::purpose($row['listing_purpose']);$poster=$payload['poster']??[];
        if(($payload['youtube_video_id']??'')!==''&&!ListingVideo::valid($payload['youtube_video_id']))throw new \InvalidArgumentException('The saved YouTube video is invalid.');
        if(!is_array($poster)||($payload['purpose']??null)!==($purpose==='rent'?'rent':'resale')||!in_array($row['selling_method'],['self_sell','shalimar_agent','authorized_agency'],true))throw new \InvalidArgumentException('The submitted purpose or listing method is inconsistent. Request customer corrections.');
        if(!is_string($payload['description']??null)||!mb_check_encoding($payload['description'],'UTF-8')||mb_strlen($payload['description'])>500||($purpose==='rent'&&trim($payload['description'])===''))throw new \InvalidArgumentException('A valid property description is required.');
        $amount=ListingRentalTerms::amount($row['asking_price'],'Listing amount');if(!is_int($payload['price']??null)||(string)$payload['price']!==$amount)throw new \InvalidArgumentException('The saved listing amounts do not match.');
        if(!is_bool($payload['negotiable']??null))throw new \InvalidArgumentException('The Negotiable value is invalid.');
        if($purpose==='rent'){$rental=ListingRentalTerms::validate(is_array($payload['rental']??null)?$payload['rental']:[]);if($rental['monthly_rent']!==$amount)throw new \InvalidArgumentException('The monthly rent does not match the saved amount.');}
        return$payload;
    }
    public function representative(array $row,bool $lock=false):array
    {
        $payload=json_decode($row['payload'],true,128,JSON_THROW_ON_ERROR);$poster=$payload['poster']??[];$source=(int)$row['erp_source_id'];
        if($row['selling_method']==='self_sell'){
            if(($poster['role']??null)!=='owner'||(int)($poster['party_id']??0)!==(int)$row['portal_customer_id']||(!empty($poster['email'])&&($poster['email_public']??null)!==true))throw new \InvalidArgumentException('The explicit public owner contact is inconsistent.');
            return ['name'=>'Direct Owner','phone'=>\App\Libraries\CustomerIdentity::mobile($poster['phone']??''),'email'=>PublicContactEmail::optional($poster['email']??'')];
        }
        if($row['selling_method']==='shalimar_agent'){
            if(self::reviewable($row))return ['name'=>'Admin assignment required at approval','phone'=>'','email'=>''];
            $agent=(new ShalimarAgents($this->db))->requireEligible($source,(int)$row['portal_agent_id'],$lock,(int)$row['agent_erp_link_id']);
            if((int)$agent['agent_erp_link_id']!==(int)$row['agent_erp_link_id']||(int)$agent['agent_source_id']!==(int)$row['agent_source_id'])throw new \InvalidArgumentException('The exact selected agent link is no longer eligible.');
            return ['name'=>$agent['name']]+ListingSellerContact::requireBusiness($agent);
        }
        if($row['selling_method']==='authorized_agency'){
            $agency=(new AgencyDirectory($this->db))->requireEligible($source,(int)$row['agency_erp_link_id'],$lock);
            if((int)$agency['portal_agency_id']!==(int)$row['portal_agency_id'])throw new \InvalidArgumentException('The selected central agency mapping changed.');
            return ['name'=>$agency['agency_name']]+ListingSellerContact::requireBusiness($agency);
        }
        throw new \InvalidArgumentException('The selected listing method is invalid.');
    }
    /** Always fetches from the originating ERP; a local snapshot is only an identity pointer. */
    private function fresh(array $row):array
    {
        $this->validateSubmission($row);$customer=$this->db->table('portal_customers')->where('id',$row['portal_customer_id'])->where('status','active')->get()->getRowArray();if(!$customer)throw new CustomerFlowException(409,'The submitting customer account is unavailable.');
        $links=service('customerOtp')->links((int)$row['portal_customer_id']);$portfolio=$this->portfolio??service('customerPortfolio');
        $property=(new ListingLifecycle($this->db))->property((int)$row['portal_customer_id'],(int)$row['id'],$portfolio,$links);$identity=null;
        foreach($links as$link)if((int)$link['erp_source_id']===(int)$row['erp_source_id']&&(string)$link['erp_customer_id']===$property['erp_customer_id']){$identity=$link;break;}
        $submitted=json_decode($row['owner_verification'],true,128,JSON_THROW_ON_ERROR);
        if(!$identity||($submitted['current_owner_customer_id']??null)!==$identity['erp_customer_id']||($submitted['property_ref']??null)!==(string)$row['erp_property_id'])throw new CustomerFlowException(409,'The original customer-to-ERP ownership link changed. Request fresh customer verification.','identity_changed');
        try{$proof=CurrentBookingApi::validate(['success'=>true,'data'=>($this->booking??service('currentBooking'))->verify($identity,(string)$row['erp_property_id'],$submitted['current_booking_ref'])],(string)$row['erp_property_id'],$identity['erp_customer_id'],$submitted['current_booking_ref']);}catch(\Throwable$e){throw new CustomerFlowException(503,'Fresh originating ERP booking ownership could not be verified. Nothing was approved.','ownership_unavailable');}
        if(!$proof['resale_eligible']||$proof['head_id']!==$property['erp_head_id']||$proof['subhead_id']!==$property['erp_subhead_id'])throw new CustomerFlowException(409,'Current ERP Head/Subhead eligibility does not match this listing.','eligibility_changed');
        $payload=json_decode($row['payload'],true);if(($payload['category']??null)!==ErpPropertyClassification::category($property['type'])||($payload['plot_no']??null)!==$property['plot'])throw new CustomerFlowException(409,'Current ERP property type or number changed. Request customer corrections before approval.','property_changed');
        $this->representative($row);
        return ['identity'=>$identity,'property'=>$property,'proof'=>['erp_source_id'=>(int)$row['erp_source_id'],'erp_property_id'=>(string)$row['erp_property_id'],'erp_booking_id'=>$proof['current_booking_ref'],'head_id'=>$proof['head_id'],'subhead_id'=>$proof['subhead_id'],'type'=>$property['type'],'verified_at'=>$proof['verified_at'],'ownership_match'=>true],'received_at'=>microtime(true)];
    }
    private function localLocks(array $row,array $fresh):void
    {
        if(microtime(true)-$fresh['received_at']>15)throw new CustomerFlowException(503,'Ownership verification is no longer fresh. Please try approval again.');
        $identity=$fresh['identity'];$property=$fresh['property'];
        if(!$this->db->query("SELECT id FROM portal_customers WHERE id=? AND status='active' FOR UPDATE",[$row['portal_customer_id']])->getRowArray()||!$this->db->query('SELECT id FROM customer_erp_links WHERE portal_customer_id=? AND erp_source_id=? AND erp_customer_id=? FOR UPDATE',[$row['portal_customer_id'],$row['erp_source_id'],$identity['erp_customer_id']])->getRowArray())throw new CustomerFlowException(409,'Customer identity eligibility changed.');
        $mapping=$this->db->query('SELECT m.id FROM erp_project_mappings m'.ErpVisibility::joins().' WHERE m.id=? AND m.project_slug=? AND m.erp_source_id=? AND m.erp_head_id=? AND m.erp_subhead_id=? AND '.ErpVisibility::mappingSql().' FOR UPDATE',[$row['mapping_id'],$row['project_slug'],$row['erp_source_id'],$property['erp_head_id'],$property['erp_subhead_id']])->getRowArray();
        if(!$mapping)throw new CustomerFlowException(409,'Project/source/Head/Subhead eligibility changed. Nothing was approved.');
        (new ListingLifecycle($this->db))->assertNoAdminRestriction((int)$row['erp_source_id'],(string)$row['erp_property_id'],$row['listing_purpose']);
        if($this->db->query('SELECT id FROM portal_listings WHERE active_erp_property=? AND id<>? FOR UPDATE',[ListingPolicy::key((int)$row['erp_source_id'],(string)$row['erp_property_id'],$row['listing_purpose']),$row['id']])->getRowArray())throw new CustomerFlowException(409,'Another current listing occupies this property and purpose.');
        $this->representative($row,true);$this->photos->requirePublishable($row);
    }
    /** Manual review validates local publication data and photos without an ERP/OTP lookup. */
    private function manualApprovalLocks(array $row):void
    {
        $this->validateContent($row);
        $company=CompanyPropertyListing::owned($row);
        if(!$company&&!$this->db->query("SELECT id FROM portal_customers WHERE id=? AND status='active' FOR UPDATE",[$row['portal_customer_id']])->getRowArray())throw new CustomerFlowException(409,'The submitting customer account is unavailable.');
        $mapping=$this->db->query('SELECT m.id FROM erp_project_mappings m'.ErpVisibility::joins().' WHERE m.id=? AND m.project_slug=? AND m.erp_source_id=? AND '.ErpVisibility::mappingSql().' FOR UPDATE',[$row['mapping_id'],$row['project_slug'],$row['erp_source_id']])->getRowArray();
        if(!$mapping)throw new CustomerFlowException(409,'The selected project/source mapping is unavailable for publication.');
        (new ListingLifecycle($this->db))->assertNoAdminRestriction((int)$row['erp_source_id'],(string)$row['erp_property_id'],$row['listing_purpose']);
        if($this->db->query('SELECT id FROM portal_listings WHERE active_erp_property=? AND id<>? FOR UPDATE',[ListingPolicy::key((int)$row['erp_source_id'],(string)$row['erp_property_id'],$row['listing_purpose']),$row['id']])->getRowArray())throw new CustomerFlowException(409,'Another current listing occupies this property and purpose.');
        $this->representative($row,true);$this->photos->requirePublishable($row);
        if($company){$valid=array_filter($this->photos->manifest($row),static fn($photo)=>$photo['valid']);if(!$valid)throw new \InvalidArgumentException('Add at least one valid property photo before approving this company listing.');}
    }
    private function event(string $event,array $before,array $after,int $actor,array $context):void
    {
        $snapshot=$after;$snapshot['_admin_review']=$context;
        (new ListingLifecycle($this->db))->record($event,$before,$snapshot,null,$actor);
        (new AdminAuditService($this->db))->record('listing.'.$event,'resale_listings',$actor,'portal_listing',$after['id'],['status'=>$before['status']],['status'=>$after['status'],'reason'=>$context['reason']??'','version'=>$after['record_version'],'erp_source_id'=>$after['erp_source_id']]);
    }
    private function repeat(array $row,string $event,int $actor,int $version,string $fingerprint,string $reason):bool
    {
        $last=$this->db->table('listing_events')->where('listing_id',$row['id'])->where('event',$event)->orderBy('id','DESC')->get()->getRowArray();if(!$last||(int)$last['admin_user_id']!==$actor)return false;
        $saved=json_decode($last['next_record'],true);$context=$saved['_admin_review']??[];
        return ($context['reviewed_version']??null)===$version&&($context['reviewed_fingerprint']??null)===$fingerprint&&($context['reason']??'')===$reason&&ListingLifecycle::fingerprint($row)===ListingLifecycle::fingerprint($saved);
    }
    private function failure(int $id,int $actor,\Throwable $e):void
    {
        try{(new AdminAuditService($this->db))->record('listing.review_verification_failed','resale_listings',$actor,'portal_listing',$id,null,['reason'=>$e instanceof CustomerFlowException?$e->reason:'validation_or_storage_failure']);}catch(\Throwable$ignored){log_message('error','Listing review failure audit unavailable ({type}).',['type'=>get_class($ignored)]);}
    }
    public function verify(int $id,int $actor,int $version,string $fingerprint):array
    {
        $this->requirePermission($actor,'resale_listings.approve');
        if(CompanyPropertyListing::owned($this->row($id)))throw new \InvalidArgumentException('Company inventory listings do not use customer ownership verification.');
        if(!self::requiresOwnershipVerification())throw new CustomerFlowException(409,'Ownership verification is disabled for the current admin review workflow.','ownership_check_disabled');
        $this->requirePermission($actor,'resale_listings.approve');try{$row=$this->row($id);$this->expected($row,$version,$fingerprint);$this->pending($row);$fresh=$this->fresh($row);$this->db->transBegin();try{$current=$this->row($id,true);$this->expected($current,$version,$fingerprint);$this->localLocks($current,$fresh);$this->expected($current,$version,$fingerprint);$this->requirePermission($actor,'resale_listings.approve');$this->event('admin_ownership_verified',$current,$current,$actor,['verification'=>$fresh['proof']]);$this->commit();}catch(\Throwable$e){$this->rollback();throw$e;}return$fresh['proof'];}catch(\Throwable$e){$this->failure($id,$actor,$e);throw$e;}
    }
    public function decide(int $id,int $actor,string $action,int $version,string $fingerprint,string $reason='',?int $assignedAgent=null):array
    {
        if(!in_array($action,['approve','reject','changes'],true))throw new \InvalidArgumentException('Choose a supported review action.');$permission=$action==='approve'?'resale_listings.approve':'resale_listings.reject';$this->requirePermission($actor,$permission);
        $reason=trim($reason);if(!mb_check_encoding($reason,'UTF-8')||mb_strlen($reason)>2000||($action!=='approve'&&$reason===''))throw new \InvalidArgumentException('Provide a review explanation between 1 and 2000 characters.');
        $event=['approve'=>'admin_approved_published','reject'=>'admin_rejected','changes'=>'admin_changes_requested'][$action];
        try{
            $row=$this->row($id);
            $company=CompanyPropertyListing::owned($row);if($action==='approve'&&$company)$event='admin_company_published';
            if($action==='approve'&&$row['selling_method']==='shalimar_agent'&&(!$assignedAgent||$assignedAgent<1))throw new \InvalidArgumentException('Select a Shalimar Agent before approval.');
            if($assignedAgent!==null&&($action!=='approve'||$row['selling_method']!=='shalimar_agent'))throw new \InvalidArgumentException('Agent assignment is only available when approving a Shalimar Agent listing.');
            if($this->repeat($row,$event,$actor,$version,$fingerprint,$reason)){if($assignedAgent!==null&&(int)$row['portal_agent_id']!==$assignedAgent)throw new CustomerFlowException(409,'This approval already assigned a different agent. Reload the current record.');return $this->afterDecision($row,$action,true);}
            $this->expected($row,$version,$fingerprint);$this->pending($row,$action==='reject');$fresh=$action==='approve'&&!$company&&self::requiresOwnershipVerification()?$this->fresh($row):null;$companyInventory=null;
            if($action==='approve'&&$company){$companyInventory=(new CompanyPropertyListing($this->db))->property((int)$row['erp_source_id'],(string)$row['erp_property_id']);ErpPropertyClassification::assertPurpose($companyInventory['classification'],$row['listing_purpose']);if((int)$companyInventory['mapping']['id']!==(int)$row['mapping_id']||$companyInventory['mapping']['project_slug']!==$row['project_slug'])throw new CustomerFlowException(409,'The current ERP inventory no longer matches this company listing mapping. Refresh the property and submit it again.','company_inventory_changed');}
            $this->db->transBegin();try{
                $row=$this->row($id,true);$company=CompanyPropertyListing::owned($row);if($action==='approve'&&$company)$event='admin_company_published';if($this->repeat($row,$event,$actor,$version,$fingerprint,$reason)){if($assignedAgent!==null&&(int)$row['portal_agent_id']!==$assignedAgent)throw new CustomerFlowException(409,'This approval already assigned a different agent.');$this->commit();return $this->afterDecision($row,$action,true);}
                $this->expected($row,$version,$fingerprint);$this->pending($row,$action==='reject');$this->requirePermission($actor,$permission);if($companyInventory&&microtime(true)-$companyInventory['fetched_at']>15)throw new CustomerFlowException(503,'The ERP inventory check expired. Reload the review and approve again.','company_inventory_stale');if($fresh){$this->localLocks($row,$fresh);$this->expected($row,$version,$fingerprint);}elseif($action==='approve'){$this->manualApprovalLocks($row);$this->expected($row,$version,$fingerprint);}
                $payload=json_decode($row['payload'],true,128,JSON_THROW_ON_ERROR);$now=(new ShalimarAgents($this->db))->now();$state=['approve'=>'approved','reject'=>'rejected','changes'=>'needs_changes'][$action];$status=$action==='approve'?'live':($action==='reject'?'rejected':'pending');
                $payload['status']=$status;$payload['review_note']=$action==='approve'?'':$reason;if($action==='approve'){$payload['published_at']=$payload['published_at']??$now;$payload['approved_at']=$now;$payload['images_status']='approved';$payload['approval_mode']=$fresh?'erp_verified':'manual';if($companyInventory){$payload['_company_inventory']['inventory_status']=$companyInventory['inventory']['status']??null;$payload['_company_inventory']['maintenance_status']=$companyInventory['classification']['maintenance_status'];$payload['_company_inventory']['classification_status']=$companyInventory['classification']['classification_status'];$payload['_company_inventory']['checked_at']=$now;}}
                $assignment=[];
                if($action==='approve'&&$row['selling_method']==='shalimar_agent'){
                    $agent=(new ShalimarAgents($this->db))->requireAdminAssignable((int)$row['erp_source_id'],$assignedAgent,true);
                    $snapshot=['portal_agent_id'=>(int)$agent['portal_agent_id'],'agent_source'=>$agent['agent_source'],'agent_source_id'=>(int)$agent['agent_source_id'],'erp_ledger_id'=>(string)$agent['erp_ledger_id'],'erp_dealer_id'=>$agent['erp_dealer_id'],'erp_agency_id'=>(string)$agent['erp_agency_id'],'name'=>$agent['name'],'mobile'=>$agent['mobile'],'email'=>$agent['email'],'image_filename'=>$agent['image_filename']??'','recorded_at'=>$now,'assigned_by'=>$actor];
                    $payload['agent_snapshot']=$snapshot;$payload['poster']=['role'=>'agent','party_id'=>(int)$agent['portal_agent_id'],'name'=>$agent['name'],'phone'=>$agent['mobile'],'email'=>$agent['email'],'verified'=>true];
                    $assignment=['portal_agent_id'=>(int)$agent['portal_agent_id'],'agent_erp_link_id'=>(int)$agent['agent_erp_link_id'],'agent_source_id'=>(int)$agent['agent_source_id'],'agent_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR),'agent_review_required'=>0,'agent_review_reason'=>null];
                }
                $values=['status'=>$status,'review_state'=>$state,'record_version'=>$version+1,'updated_at'=>$now,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR)]+$assignment;$this->db->table('portal_listings')->where('id',$id)->update($values);$saved=$this->row($id);
                $context=['reason'=>$reason,'reviewed_version'=>$version,'reviewed_fingerprint'=>$fingerprint,'approved_content_hash'=>$this->fingerprint($saved)];if($action==='approve'){$context['approval_mode']=$fresh?'erp_verified':'manual';$context['ownership_check_performed']=$fresh!==null;if($assignedAgent!==null)$context['assigned_agent_id']=$assignedAgent;}if($fresh)$context['verification']=$fresh['proof'];$this->event($event,$row,$saved,$actor,$context);if($action==='approve'&&$saved['selling_method']==='shalimar_agent'){if(config('Sms')->enabled)($this->assignmentSms??new AgentAssignmentSms($this->db))->queue($saved);(new AgentAssignmentWhatsapp($this->db))->queue($saved);}$this->commit();return $this->afterDecision($saved,$action,false);
            }catch(\Throwable$e){$this->rollback();throw$e;}
        }catch(\Throwable$e){$this->failure($id,$actor,$e);throw$e;}
    }
    private function afterDecision(array $listing,string $action,bool $repeat):array
    {
        $result=['id'=>(int)$listing['id'],'state'=>$listing['review_state'],'idempotent'=>$repeat];
        if($action==='approve'&&$listing['selling_method']==='shalimar_agent'){
            try{$result['assignment_sms']=($this->assignmentSms??new AgentAssignmentSms($this->db))->dispatch($listing);}
            catch(\Throwable $e){log_message('error','Approved listing SMS status unavailable: listing={id} type={type}.',['id'=>$listing['id'],'type'=>get_class($e)]);$result['assignment_sms']=['status'=>'unavailable'];}
            try{$result['assignment_whatsapp']=(new AgentAssignmentWhatsapp($this->db))->dispatch($listing);}
            catch(\Throwable $e){log_message('error','Approved listing WhatsApp status unavailable: listing={id} type={type}.',['id'=>$listing['id'],'type'=>get_class($e)]);$result['assignment_whatsapp']=['status'=>'unavailable'];}
        }
        return $result;
    }
    public function retryAssignmentSms(int $id,int $actor,int $version,string $fingerprint,int $attempts):?array
    {
        $this->requirePermission($actor,'resale_listings.approve');$row=$this->row($id);$this->expected($row,$version,$fingerprint);
        if(!ListingPolicy::active($row)||$row['selling_method']!=='shalimar_agent')throw new CustomerFlowException(409,'Only an approved, active Shalimar Agent assignment can be notified.');
        (new AdminAuditService($this->db))->record('listing.agent_assignment_sms_retry_requested','resale_listings',$actor,'portal_listing',$id,null,['version'=>$version,'record_count'=>$attempts]);
        return ($this->assignmentSms??new AgentAssignmentSms($this->db))->dispatch($row,true,$attempts);
    }
    public function retryAssignmentWhatsapp(int $id,int $actor,int $version,string $fingerprint,int $attempts):?array
    {
        $this->requirePermission($actor,'resale_listings.approve');$row=$this->row($id);$this->expected($row,$version,$fingerprint);
        if(!ListingPolicy::active($row)||$row['selling_method']!=='shalimar_agent')throw new CustomerFlowException(409,'Only an approved, active Shalimar Agent assignment can be notified.');
        (new AdminAuditService($this->db))->record('listing.agent_assignment_whatsapp_retry_requested','resale_listings',$actor,'portal_listing',$id,null,['version'=>$version,'record_count'=>$attempts]);
        return (new AgentAssignmentWhatsapp($this->db))->dispatch($row,true,$attempts);
    }
    public function photoSet(int $id,int $actor,int $version,string $fingerprint,array $ids,array|string|null $newPhotos=null,?string $youtubeUrl=null,bool $approvedMedia=false):void
    {
        $this->requirePermission($actor,'resale_listings.edit');$this->db->transBegin();try{
            $row=$this->row($id,true);if($approvedMedia){if(!ListingPolicy::active($row))throw new CustomerFlowException(409,'Only a currently approved, active listing can have its public photos changed.');}else $this->pending($row);$this->expected($row,$version,$fingerprint);if($approvedMedia&&$youtubeUrl!==null)throw new \InvalidArgumentException('Approved listing media updates can change photos only.');$images=$this->photos->references($row,$ids);
            $newPhotoIds=is_string($newPhotos)?[$newPhotos]:($newPhotos??[]);if(!array_is_list($newPhotoIds))throw new \InvalidArgumentException('The uploaded photo set is invalid.');
            foreach($newPhotoIds as$newPhoto){if(!is_string($newPhoto)||!preg_match('/\A[a-f0-9]{64}\z/D',$newPhoto))throw new \InvalidArgumentException('The uploaded photo reference is invalid.');$photo=$this->db->table('resale_photos')->where('id',$newPhoto)->where('portal_customer_id',$row['portal_customer_id'])->where('submission_key',$this->uploadKey($row,$actor))->where('listing_id',null)->get()->getRowArray();if(!$photo)throw new \InvalidArgumentException('An uploaded photo does not belong to this review.');$images[]=ResalePhotos::marker($newPhoto);}
            if(count($images)>config('CustomerPortal')->adminPhotoMaximumCount)throw new \InvalidArgumentException('The final photo set exceeds the configured limit.');
            $payload=json_decode($row['payload'],true);$beforeVideo=$payload['youtube_video_id']??'';if($youtubeUrl!==null)$payload['youtube_video_id']=ListingVideo::id($youtubeUrl);$payload['images']=$images?:AdminListingPhotos::placeholder($payload);$payload['images_status']=$approvedMedia?'approved':'pending';$values=['payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'record_version'=>$version+1,'updated_at'=>(new ShalimarAgents($this->db))->now()];$this->db->table('portal_listings')->where('id',$id)->update($values);if($newPhotoIds)(new ResalePhotos($this->db))->attach((int)$row['portal_customer_id'],$id,$newPhotoIds);$saved=$this->row($id);
            $event=$approvedMedia?'admin_media_updated':($newPhotoIds?'admin_photo_added':'admin_photo_set_changed');$reason=$approvedMedia?'Approved listing photos updated; publication and listing details preserved.':($youtubeUrl!==null?'Property video reviewed; media history retained.':($newPhotoIds?'Photos added to the listing; previous files/history retained.':'Photo set reviewed; removed files/history retained.'));$this->event($event,$row,$saved,$actor,['reason'=>$reason,'reviewed_version'=>$version,'reviewed_fingerprint'=>$fingerprint,'before_youtube_video_id'=>$beforeVideo,'after_youtube_video_id'=>$payload['youtube_video_id']??'','before_photos'=>array_column($this->photos->manifest($row),'id'),'after_photos'=>array_column($this->photos->manifest($saved),'id')]);$this->commit();
        }catch(\Throwable$e){$this->rollback();throw$e;}
    }
    private function uploadKey(array $row,int $actor):string{return hash('sha256','admin-review:'.$actor.':'.$row['id'].':'.$row['record_version']);}
    private function uploadCompanyPhotos(int $id,int $actor,int $version,string $fingerprint,array $files):void
    {
        $written=[];$this->db->transBegin();
        try{
            $row=$this->row($id,true);$this->pending($row);$this->expected($row,$version,$fingerprint);
            if(!CompanyPropertyListing::owned($row))throw new \InvalidArgumentException('This is not a company inventory listing.');
            $payload=json_decode($row['payload'],true,128,JSON_THROW_ON_ERROR);
            $images=CompanyPropertyImages::save($files,$payload['images']??[],[],$written);
            if(!$images)throw new \InvalidArgumentException('Add at least one valid property photo.');
            $payload['images']=$images;$payload['images_status']='pending';$now=(new ShalimarAgents($this->db))->now();
            $this->db->table('portal_listings')->where('id',$id)->update(['payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'record_version'=>$version+1,'updated_at'=>$now]);
            $saved=$this->row($id);$this->event('admin_photo_added',$row,$saved,$actor,['reason'=>'Company inventory photos added to the review listing.','reviewed_version'=>$version,'reviewed_fingerprint'=>$fingerprint,'before_photos'=>array_column($this->photos->manifest($row),'id'),'after_photos'=>array_column($this->photos->manifest($saved),'id')]);
            $this->commit();
        }catch(\Throwable $e){$this->rollback();foreach($written as$file)if(is_file($file))@unlink($file);throw $e;}
    }
    public function upload(int $id,int $actor,int $version,string $fingerprint,array $files,bool $approvedMedia=false):void
    {
        $candidate=$this->row($id);
        if(CompanyPropertyListing::owned($candidate)){
            if($approvedMedia)throw new \InvalidArgumentException('Use the company listing review flow to update pending photos.');
            $this->requirePermission($actor,'resale_listings.edit');$items=$this->photos->items($candidate);$remaining=config('CustomerPortal')->adminPhotoMaximumCount-count($items);
            if(!$files||count($files)>$remaining)throw new \InvalidArgumentException('Choose no more photos than the remaining listing photo slots.');
            $this->uploadCompanyPhotos($id,$actor,$version,$fingerprint,$files);return;
        }
        $this->requirePermission($actor,'resale_listings.edit');$row=$this->row($id);if($approvedMedia){if(!ListingPolicy::active($row))throw new CustomerFlowException(409,'Only a currently approved, active listing can have its public photos changed.');}else $this->pending($row);$this->expected($row,$version,$fingerprint);$items=$this->photos->items($row);$remaining=config('CustomerPortal')->adminPhotoMaximumCount-count($items);if(!$files||count($files)>$remaining)throw new \InvalidArgumentException('Choose no more photos than the remaining listing photo slots.');
        $key=$this->uploadKey($row,$actor);$photos=new ResalePhotos($this->db);$newPhotoIds=[];foreach($files as$file){if(!$file||!$file->isValid())throw new \InvalidArgumentException('One of the selected photos is invalid.');$photo=$photos->upload($file,(int)$row['portal_customer_id'],$key,'admin-review:'.$actor.':'.$id);$newPhotoIds[]=$photo['id'];}
        // The ordered photo set is committed once; incomplete batches stay private and expire through the existing pruner.
        $this->photoSet($id,$actor,$version,$fingerprint,array_column($items,'id'),$newPhotoIds,null,$approvedMedia);
    }
    private function commit():void{if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('The audited review could not be committed.');}
    private function rollback():void{$this->db->transRollback();$this->db->resetTransStatus();}
}

