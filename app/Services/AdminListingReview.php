<?php
declare(strict_types=1);
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use App\Exceptions\CustomerFlowException;

/** New review workflow. No ERP writes, SMS, legacy approval or terminal-state reactivation. */
final class AdminListingReview
{
    private AdminListingPhotos $photos;
    public function __construct(private BaseConnection $db,private ?CustomerPortfolio $portfolio=null,private ?CurrentBookingProvider $booking=null){$this->photos=new AdminListingPhotos($db);}
    public static function requiresOwnershipVerification():bool{return config('AdminReview')->requireOwnershipVerification;}
    public function row(int $id,bool $lock=false):array
    {
        $sql=$this->db->table('portal_listings')->where('id',$id)->where('portal_customer_id IS NOT NULL',null,false)->where('erp_source_id IS NOT NULL',null,false)->where('erp_property_id IS NOT NULL',null,false)->getCompiledSelect();
        $row=$this->db->query($sql.($lock?' FOR UPDATE':''))->getRowArray();if(!$row)throw new CustomerFlowException(404,'Customer listing not found.');return$row;
    }
    public function fingerprint(array $row):string
    {
        try{$contact=['eligible'=>true]+$this->representative($row);}catch(\InvalidArgumentException$e){$contact=['eligible'=>false,'reason'=>$e->getMessage()];}
        return hash('sha256',$this->photos->fingerprint($row).json_encode($contact,JSON_THROW_ON_ERROR));
    }
    public static function reviewable(array $row):bool{return in_array($row['status'],['pending','pending_approval'],true)&&in_array($row['deal'],['available','active'],true)&&$row['review_state']==='under_review';}
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
        if(self::reviewable($row)||($allowChanges&&$row['review_state']==='needs_changes'&&in_array($row['status'],['pending','pending_approval'],true)&&in_array($row['deal'],['available','active'],true)))return;
        throw new CustomerFlowException(409,'This listing is not awaiting this review action. Approved, rejected, withdrawn, unpublished and Sold records cannot be reopened here.','not_reviewable');
    }
    private function validateSubmission(array $row):array
    {
        $challenge=$row['resale_verification_id']?$this->db->table('resale_otp_challenges')->where('id',$row['resale_verification_id'])->get()->getRowArray():null;
        if(!$challenge||!PublicationVerification::submission($row+['otp_customer_id'=>$challenge['portal_customer_id'],'otp_submission_key'=>$challenge['submission_key'],'otp_delivery_state'=>$challenge['delivery_state'],'otp_verified_at'=>$challenge['verified_at'],'otp_consumed_at'=>$challenge['consumed_at']]))throw new CustomerFlowException(409,'A complete current-owner OTP submission is required. Request Changes so the customer can verify and resubmit.','submission_proof_missing');
        $submission=$this->db->table('listing_events')->where('listing_id',$row['id'])->where('portal_customer_id',$row['portal_customer_id'])->where('authorization_id',$row['resale_verification_id'])->whereIn('event',['customer_submitted','customer_edit_submitted','customer_resubmitted','customer_changes_resubmitted'])->get()->getRowArray();
        $original=$submission?json_decode($submission['next_record'],true):null;if(!is_array($original))throw new CustomerFlowException(409,'The verified customer submission revision is unavailable. Request fresh customer verification.');
        foreach(['portal_customer_id','erp_source_id','erp_property_id','mapping_id','project_slug','listing_purpose','asking_price','selling_method','portal_agent_id','agent_erp_link_id','agent_source_id','portal_agency_id','agency_erp_link_id','owner_verification','submission_key']as$key)if(($row[$key]??null)!==($original[$key]??null))throw new CustomerFlowException(409,'Material listing identity or contact changed outside the verified customer submission. Request Changes.','submission_changed');
        $material=static function(string $json):array{$data=json_decode($json,true,128,JSON_THROW_ON_ERROR);foreach(['images','images_status','review_note','status','approved_at','published_at']as$key)unset($data[$key]);return$data;};
        if($material($row['payload'])!==$material($original['payload']))throw new CustomerFlowException(409,'Material listing content changed after customer verification. Request Changes.','submission_changed');
        return $this->validateContent($row);
    }
    private function validateContent(array $row):array
    {
        $payload=json_decode($row['payload'],true,128,JSON_THROW_ON_ERROR);$purpose=ListingPolicy::purpose($row['listing_purpose']);$poster=$payload['poster']??[];
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
            $agent=(new ShalimarAgents($this->db))->requireSelectable($source,(int)$row['portal_agent_id'],$lock);
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
        if(!$this->db->query("SELECT id FROM portal_customers WHERE id=? AND status='active' FOR UPDATE",[$row['portal_customer_id']])->getRowArray())throw new CustomerFlowException(409,'The submitting customer account is unavailable.');
        $mapping=$this->db->query('SELECT m.id FROM erp_project_mappings m'.ErpVisibility::joins().' WHERE m.id=? AND m.project_slug=? AND m.erp_source_id=? AND '.ErpVisibility::mappingSql().' FOR UPDATE',[$row['mapping_id'],$row['project_slug'],$row['erp_source_id']])->getRowArray();
        if(!$mapping)throw new CustomerFlowException(409,'The selected project/source mapping is unavailable for publication.');
        (new ListingLifecycle($this->db))->assertNoAdminRestriction((int)$row['erp_source_id'],(string)$row['erp_property_id'],$row['listing_purpose']);
        if($this->db->query('SELECT id FROM portal_listings WHERE active_erp_property=? AND id<>? FOR UPDATE',[ListingPolicy::key((int)$row['erp_source_id'],(string)$row['erp_property_id'],$row['listing_purpose']),$row['id']])->getRowArray())throw new CustomerFlowException(409,'Another current listing occupies this property and purpose.');
        $this->representative($row,true);$this->photos->requirePublishable($row);
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
        if(!self::requiresOwnershipVerification())throw new CustomerFlowException(409,'Ownership verification is disabled for the current admin review workflow.','ownership_check_disabled');
        $this->requirePermission($actor,'resale_listings.approve');try{$row=$this->row($id);$this->expected($row,$version,$fingerprint);$this->pending($row);$fresh=$this->fresh($row);$this->db->transBegin();try{$current=$this->row($id,true);$this->expected($current,$version,$fingerprint);$this->localLocks($current,$fresh);$this->expected($current,$version,$fingerprint);$this->requirePermission($actor,'resale_listings.approve');$this->event('admin_ownership_verified',$current,$current,$actor,['verification'=>$fresh['proof']]);$this->commit();}catch(\Throwable$e){$this->rollback();throw$e;}return$fresh['proof'];}catch(\Throwable$e){$this->failure($id,$actor,$e);throw$e;}
    }
    public function decide(int $id,int $actor,string $action,int $version,string $fingerprint,string $reason=''):array
    {
        if(!in_array($action,['approve','reject','changes'],true))throw new \InvalidArgumentException('Choose a supported review action.');$permission=$action==='approve'?'resale_listings.approve':'resale_listings.reject';$this->requirePermission($actor,$permission);
        $reason=trim($reason);if(!mb_check_encoding($reason,'UTF-8')||mb_strlen($reason)>2000||($action!=='approve'&&$reason===''))throw new \InvalidArgumentException('Provide a review explanation between 1 and 2000 characters.');
        $event=['approve'=>'admin_approved_published','reject'=>'admin_rejected','changes'=>'admin_changes_requested'][$action];
        try{
            $row=$this->row($id);if($this->repeat($row,$event,$actor,$version,$fingerprint,$reason))return ['id'=>$id,'state'=>$row['review_state'],'idempotent'=>true];
            $this->expected($row,$version,$fingerprint);$this->pending($row,$action==='reject');$fresh=$action==='approve'&&self::requiresOwnershipVerification()?$this->fresh($row):null;
            $this->db->transBegin();try{
                $row=$this->row($id,true);if($this->repeat($row,$event,$actor,$version,$fingerprint,$reason)){$this->commit();return ['id'=>$id,'state'=>$row['review_state'],'idempotent'=>true];}
                $this->expected($row,$version,$fingerprint);$this->pending($row,$action==='reject');$this->requirePermission($actor,$permission);if($fresh){$this->localLocks($row,$fresh);$this->expected($row,$version,$fingerprint);}elseif($action==='approve'){$this->manualApprovalLocks($row);$this->expected($row,$version,$fingerprint);}
                $payload=json_decode($row['payload'],true,128,JSON_THROW_ON_ERROR);$now=(new ShalimarAgents($this->db))->now();$state=['approve'=>'approved','reject'=>'rejected','changes'=>'needs_changes'][$action];$status=$action==='approve'?'live':($action==='reject'?'rejected':'pending');
                $payload['status']=$status;$payload['review_note']=$action==='approve'?'':$reason;if($action==='approve'){$payload['published_at']=$payload['published_at']??$now;$payload['approved_at']=$now;$payload['images_status']='approved';$payload['approval_mode']=$fresh?'erp_verified':'manual';}
                $values=['status'=>$status,'review_state'=>$state,'record_version'=>$version+1,'updated_at'=>$now,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR)];$this->db->table('portal_listings')->where('id',$id)->update($values);$saved=$this->row($id);
                $context=['reason'=>$reason,'reviewed_version'=>$version,'reviewed_fingerprint'=>$fingerprint,'approved_content_hash'=>$this->fingerprint($saved)];if($action==='approve'){$context['approval_mode']=$fresh?'erp_verified':'manual';$context['ownership_check_performed']=$fresh!==null;}if($fresh)$context['verification']=$fresh['proof'];$this->event($event,$row,$saved,$actor,$context);$this->commit();return ['id'=>$id,'state'=>$state,'idempotent'=>false];
            }catch(\Throwable$e){$this->rollback();throw$e;}
        }catch(\Throwable$e){$this->failure($id,$actor,$e);throw$e;}
    }
    public function photoSet(int $id,int $actor,int $version,string $fingerprint,array $ids,?string $newPhoto=null):void
    {
        $this->requirePermission($actor,'resale_listings.edit');$this->db->transBegin();try{
            $row=$this->row($id,true);$this->pending($row);$this->expected($row,$version,$fingerprint);$images=$this->photos->references($row,$ids);
            if($newPhoto){$photo=$this->db->table('resale_photos')->where('id',$newPhoto)->where('portal_customer_id',$row['portal_customer_id'])->where('submission_key',$this->uploadKey($row,$actor))->where('listing_id',null)->get()->getRowArray();if(!$photo)throw new \InvalidArgumentException('The uploaded photo does not belong to this review.');$images[]=ResalePhotos::marker($newPhoto);(new ResalePhotos($this->db))->attach((int)$row['portal_customer_id'],$id,[$newPhoto]);}
            if(count($images)>config('CustomerPortal')->photoMaximumCount)throw new \InvalidArgumentException('The final photo set exceeds the configured limit.');
            $payload=json_decode($row['payload'],true);$payload['images']=$images?:AdminListingPhotos::placeholder($payload);$payload['images_status']='pending';$values=['payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'record_version'=>$version+1,'updated_at'=>(new ShalimarAgents($this->db))->now()];$this->db->table('portal_listings')->where('id',$id)->update($values);$saved=$this->row($id);
            $this->event($newPhoto?'admin_photo_added':'admin_photo_set_changed',$row,$saved,$actor,['reason'=>'Photo set reviewed; removed files/history retained.','reviewed_version'=>$version,'reviewed_fingerprint'=>$fingerprint,'before_photos'=>array_column($this->photos->manifest($row),'id'),'after_photos'=>array_column($this->photos->manifest($saved),'id')]);$this->commit();
        }catch(\Throwable$e){$this->rollback();throw$e;}
    }
    private function uploadKey(array $row,int $actor):string{return hash('sha256','admin-review:'.$actor.':'.$row['id'].':'.$row['record_version']);}
    public function upload(int $id,int $actor,int $version,string $fingerprint,$file):void
    {
        $this->requirePermission($actor,'resale_listings.edit');$row=$this->row($id);$this->pending($row);$this->expected($row,$version,$fingerprint);$items=$this->photos->items($row);if(count($items)>=config('CustomerPortal')->photoMaximumCount)throw new \InvalidArgumentException('Remove a photo before adding another.');
        $key=$this->uploadKey($row,$actor);$photo=(new ResalePhotos($this->db))->upload($file,(int)$row['portal_customer_id'],$key,'admin-review:'.$actor.':'.$id);
        // On a concurrent conflict the unattached upload remains private and expires via the existing pruner.
        $this->photoSet($id,$actor,$version,$fingerprint,array_column($items,'id'),$photo['id']);
    }
    private function commit():void{if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('The audited review could not be committed.');}
    private function rollback():void{$this->db->transRollback();$this->db->resetTransStatus();}
}

