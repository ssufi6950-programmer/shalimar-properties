<?php
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

final class ResaleSubmission
{
    public const CONFIRMATION='Your property listing has been submitted successfully and is under review. It will appear on Shalimar Properties after admin verification and approval.';
    public function __construct(private BaseConnection $db){}
    public function existing(string $key,int $customer):?array{$row=$this->db->table('portal_listings')->select('id,status,listing_purpose,review_state')->where('submission_key',$key)->where('portal_customer_id',$customer)->get()->getRowArray();if($row)$row['id']=(int)$row['id'];return $row;}
    public function validateDraft(array $input):array
    {
        $purpose=ListingPolicy::purpose($input['listing_purpose']??'sale');$rental=null;
        if($purpose==='rent'){
            if(!is_array($input['rental']??null)||array_is_list($input['rental']))throw new InvalidArgumentException('Enter the rental terms including Monthly Rent.');
            $rental=ListingRentalTerms::validate($input['rental']);$price=$rental['monthly_rent'];
            if(isset($input['asking_price'])&&ListingRentalTerms::amount($input['asking_price'],'Monthly rent')!==$price)throw new InvalidArgumentException('Monthly Rent does not match the reviewed rental amount.');
        }else{if(!empty($input['rental']))throw new InvalidArgumentException('Rental terms cannot be submitted as a Sale.');$price=ListingRentalTerms::amount($input['asking_price']??null,'Asking sale price');}
        $method=$input['selling_method']??null;if(!in_array($method,['self_sell','shalimar_agent','authorized_agency'],true))throw new InvalidArgumentException('Choose a selling method.');
        $remarks=$input['remarks']??'';if(!is_string($remarks)||!mb_check_encoding($remarks,'UTF-8')||mb_strlen($remarks)>500)throw new InvalidArgumentException('Remarks must contain up to 500 characters.');
        if($purpose==='rent'&&trim($remarks)==='')throw new InvalidArgumentException('Add a property description for your rental listing.');
        if(($input['confirmed']??null)!==true)throw new InvalidArgumentException('Confirm the property, asking price and selected representative before continuing.');
        $contact=$input['public_contact']??'';if(!is_string($contact))throw new InvalidArgumentException('Enter a valid public contact number.');
        $contact=$method==='self_sell'?\App\Libraries\CustomerIdentity::mobile($contact):'';
        $email=$method==='self_sell'?PublicContactEmail::optional($input['public_email']??''):'';
        $negotiable=$input['negotiable']??false;if(!is_bool($negotiable))throw new InvalidArgumentException('Negotiable must be true or false.');
        $photos=ResalePhotos::ids($input['photos']??[]);
        return ['listing_purpose'=>$purpose,'rental'=>$rental,'negotiable'=>$negotiable,'photos'=>$photos,'asking_price'=>(string)$price,'selling_method'=>$method,'remarks'=>trim($remarks),'confirmed'=>true,'public_contact'=>$contact,'public_email'=>$email,
            'portal_agent_id'=>$method==='shalimar_agent'?ShalimarAgents::identifier($input['portal_agent_id']??null):null,
            'agency_erp_link_id'=>$method==='authorized_agency'?ShalimarAgents::identifier($input['agency_erp_link_id']??null):null];
    }
    public function preview(array $user,array $property,array $input,?array $listing=null):array
    {
        $input=$this->validateDraft($input);$person=['name'=>$user['display_name']??'Property Owner','phone'=>$input['public_contact'],'email'=>$input['public_email']];
        if($input['selling_method']==='shalimar_agent'){$agent=(new ShalimarAgents($this->db))->requireSelectable((int)$property['erp_source_id'],(int)$input['portal_agent_id']);$business=ListingSellerContact::requireBusiness($agent);$person=['name'=>$agent['name']]+$business;}
        elseif($input['selling_method']==='authorized_agency'){$agency=(new AgencyDirectory($this->db))->requireEligible((int)$property['erp_source_id'],(int)$input['agency_erp_link_id']);$business=ListingSellerContact::requireBusiness($agency);$person=['name'=>$agency['agency_name']]+$business;}
        return ['listing_purpose'=>$input['listing_purpose'],'rental'=>$input['rental'],'property_type'=>$property['type'],'erp'=>$property['erp']??$property['erp_code'],'negotiable'=>$input['negotiable'],'photos'=>(new ResalePhotos($this->db))->previewItems($input['photos'],$listing),'title'=>$property['project'].' - '.$property['plot'],'project'=>$property['project'],'plot'=>$property['plot'],'size'=>$property['size'],'floor'=>$property['floor'],'location'=>$property['location'],'price'=>$input['asking_price'],'selling_method'=>$input['selling_method'],'representative'=>$person,'remarks'=>$input['remarks'],'image'=>$property['image']];
    }
    public function submit(array $user,array $property,array $input,string $key):array{return $this->save($user,$property,$input,$key);}
    public function publishVerified(array $user,array $property,array $input,string $key,array $authorization):array{return $this->save($user,$property,$input,$key,$authorization);}
    /** $property must be freshly re-fetched from the authenticated customer's ERP identity. */
    private function save(array $user,array $property,array $input,string $key,?array $authorization=null):array
    {
        if(!preg_match('/\A[a-f0-9]{64}\z/D',$key))throw new InvalidArgumentException('Open the property review again.');
        $customer=(int)$user['id'];if($existing=$this->existing($key,$customer))return $existing;
        if(empty($property['eligible'])||!in_array($property['booking_in_process']??null,['yes','no'],true)||!$property['mapping_id'])throw new InvalidArgumentException('This property is not currently eligible for resale.');
        $input=$this->validateDraft($input);$purpose=$input['listing_purpose'];if($purpose!==($property['listing_purpose']??'sale'))throw new InvalidArgumentException('The listing purpose changed. Open a fresh verified property review.');$price=$input['asking_price'];$method=$input['selling_method'];$remarks=$input['remarks'];
        $status='pending';$reviewState='under_review';$verification=null;$privateProof=null;$target=null;$context=null;
        $source=(int)$property['erp_source_id'];$directory=new ShalimarAgents($this->db);$now=$directory->now();
        $this->db->transBegin();try{
            if($existing=$this->existing($key,$customer)){$this->db->transCommit();return $existing;}
            if($authorization!==null){
                $verification=$this->db->query('SELECT * FROM resale_otp_challenges WHERE id=? FOR UPDATE',[$authorization['challenge_id']??''])->getRowArray();
                if(!$verification||(int)$verification['portal_customer_id']!==$customer||!hash_equals($verification['submission_key'],$key)||!hash_equals($verification['session_hash'],$authorization['session_hash']??'')||$verification['verified_at']===null||$verification['consumed_at']!==null||(int)$verification['expires_at']<=time()||!ResaleOwnershipOtp::deliveryUsable($verification['delivery_state']))throw new InvalidArgumentException('Fresh property-specific OTP verification is required before publication.');
                $secrets=new \App\Libraries\CustomerSecrets(config('CustomerPortal')->identityKey);$verified=$secrets->decrypt($verification['payload']);
                if(($verified['draft']??null)!==$input||($verified['property']['erp_source_id']??null)!==$property['erp_source_id']||($verified['property']['erp_property_id']??null)!==$property['erp_property_id']||($verified['property']['erp_customer_id']??null)!==$property['erp_customer_id']||($verified['property']['mapping_id']??null)!==$property['mapping_id']||!hash_equals(ResaleOwnershipOtp::fingerprint($verified['proof']),ResaleOwnershipOtp::fingerprint($authorization['proof']??[])))throw new InvalidArgumentException('Ownership or authorized listing details changed. Verify a fresh code.');
                $context=$verified['context']??null;if($context!==null)$target=(new ListingLifecycle($this->db))->lockTarget($customer,$context,$property);
                if($target){if($target['listing_purpose']!==$purpose)throw new InvalidArgumentException('An existing listing purpose cannot be changed.');if($target['review_state']!=='approved'&&!($context['operation']==='edit'&&ListingLifecycle::canCorrect($target)))throw new InvalidArgumentException('This listing still requires admin review.');}
                $privateProof=$authorization['proof'];$privateProof['registered_mobile_hash']=$secrets->digest('resale_mobile',$privateProof['registered_mobile']);unset($privateProof['registered_mobile']);
            }
            $owner=$this->db->query("SELECT id FROM portal_customers WHERE id=? AND status='active' FOR UPDATE",[$customer])->getRowArray();if(!$owner)throw new InvalidArgumentException('Sign in again before submitting.');
            if(!$this->db->table('customer_erp_links')->where('portal_customer_id',$customer)->where('erp_source_id',$source)->where('erp_customer_id',$property['erp_customer_id'])->countAllResults())throw new InvalidArgumentException('This property does not match your verified ERP identity.');
            $mapping=$this->db->query('SELECT m.* FROM erp_project_mappings m'.ErpVisibility::joins().' WHERE m.id=? AND m.erp_source_id=? AND m.erp_head_id=? AND m.erp_subhead_id=? AND '.ErpVisibility::mappingSql().' FOR UPDATE',[$property['mapping_id'],$source,$property['erp_head_id'],$property['erp_subhead_id']])->getRowArray();
            if(!$mapping)throw new InvalidArgumentException('This project is no longer published or eligible for resale.');
            (new ListingLifecycle($this->db))->assertNoAdminRestriction($source,$property['erp_property_id'],$purpose);
            $duplicates=$this->db->table('portal_listings')->where('active_erp_property',ListingPolicy::key($source,$property['erp_property_id'],$purpose));if($target)$duplicates->where('id !=',$target['id']);if($duplicates->countAllResults())throw new InvalidArgumentException('This property already has an active or pending resale listing.');
            $images=(new ResalePhotos($this->db))->selection($customer,$input['photos'],$key,$target);
            $agent=null;$agency=null;
            if($method==='shalimar_agent')$agent=$directory->requireSelectable($source,(int)ShalimarAgents::identifier($input['portal_agent_id']??null),true);
            elseif($method==='authorized_agency')$agency=(new AgencyDirectory($this->db))->requireEligible($source,(int)ShalimarAgents::identifier($input['agency_erp_link_id']??null),true);
            if($agent)ListingSellerContact::requireBusiness($agent);if($agency)ListingSellerContact::requireBusiness($agency);
            $snapshot=$agent?['portal_agent_id'=>(int)$agent['portal_agent_id'],'agent_source'=>'SGC','agent_source_id'=>(int)$agent['agent_source_id'],'erp_ledger_id'=>(string)$agent['erp_ledger_id'],'erp_dealer_id'=>$agent['erp_dealer_id'],'erp_agency_id'=>(string)$agent['erp_agency_id'],'name'=>$agent['name'],'mobile'=>$agent['mobile'],'email'=>$agent['email'],'recorded_at'=>$now]:null;
            if($agent)$poster=['role'=>'agent','party_id'=>(int)$agent['portal_agent_id'],'name'=>$agent['name'],'phone'=>$agent['mobile'],'verified'=>true];
            elseif($agency)$poster=['role'=>'agency','party_id'=>(int)$agency['portal_agency_id'],'name'=>$agency['agency_name'],'phone'=>$agency['phone'],'verified'=>true];
            else{$poster=['role'=>'owner','party_id'=>$customer,'name'=>$user['display_name']??'Property Owner','phone'=>$input['public_contact'],'email'=>$input['public_email'],'email_public'=>true,'verified'=>true];}
            if($authorization!==null&&($verified['preview']??null)!==$this->preview($user,$property,$input,$target))throw new InvalidArgumentException('The property or representative details changed. Verify a fresh code before publishing.');
            $category=ErpPropertyClassification::category($property['type']);preg_match('/[0-9]+(?:\.[0-9]+)?/',$property['size'],$sizeMatch);
            $payload=['id'=>0,'ref'=>'','title'=>$property['project'].' - '.$property['plot'],'purpose'=>$purpose==='rent'?'rent':'resale','category'=>$category,'project'=>$mapping['project_slug'],'sector'=>$property['sector'],'block'=>$property['block'],'plot_no'=>$property['plot'],'floor'=>$property['floor'],'city'=>'','location'=>$property['location'],'price'=>(int)$price,'rental'=>$input['rental'],'size'=>isset($sizeMatch[0])?(float)$sizeMatch[0]:0,'size_label'=>$property['size'],'unit'=>str_contains(strtolower($property['size']),'sq')?'sqft':'marla','beds'=>0,'baths'=>0,'description'=>trim($remarks),'images_status'=>'pending','negotiable'=>$input['negotiable'],'images'=>$images?:['assets/images/listing-'.(in_array($category,['house','apartment','plot','commercial','office','farmhouse'],true)?$category:'plot').'.svg'],'poster'=>$poster,'status'=>'pending','deal'=>'available','created_at'=>$now,'published_at'=>null,'review_note'=>'','source'=>$property['erp_code'].' customer resale','selling_method'=>$method,'original_agent_agency'=>$property['original_agency'],'agent_snapshot'=>$snapshot];
            $previousPayload=$target?json_decode($target['payload'],true,128,JSON_THROW_ON_ERROR):[];
            $payload=array_replace($previousPayload,$payload);$payload['status']=$status;$payload['published_at']=$target?($previousPayload['published_at']??null):null;$payload['submitted_at']=$now;
            if($target){$payload['created_at']=$previousPayload['created_at']??$target['created_at'];$payload['review_note']=$previousPayload['review_note']??'';$payload['last_edited_at']=$now;}
            if($target&&$context['operation']==='republish')$payload['resubmitted_at']=$now;
            $values=['listing_purpose'=>$purpose,'review_state'=>$reviewState,'project_slug'=>$mapping['project_slug'],'mapping_id'=>$mapping['id'],'status'=>$status,'deal'=>'available','payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'portal_customer_id'=>$customer,'erp_source_id'=>$source,'erp_property_id'=>$property['erp_property_id'],'portal_agency_id'=>$agency['portal_agency_id']??null,'agency_erp_link_id'=>$agency['agency_erp_link_id']??null,'selling_method'=>$method,'portal_agent_id'=>$agent['portal_agent_id']??null,'agent_erp_link_id'=>$agent['agent_erp_link_id']??null,'agent_source_id'=>$agent['agent_source_id']??null,'agent_snapshot'=>$snapshot?json_encode($snapshot,JSON_THROW_ON_ERROR):null,'asking_price'=>$price,'submission_key'=>$key,'created_at'=>$now,'updated_at'=>$now,'resale_verification_id'=>$verification['id']??null,'owner_verification'=>$privateProof?json_encode($privateProof,JSON_THROW_ON_ERROR):null];
            if($target){$id=(int)$target['id'];$values['record_version']=(int)$target['record_version']+1;foreach(['project_slug','mapping_id','portal_customer_id','erp_source_id','erp_property_id','created_at']as$column)unset($values[$column]);$this->db->table('portal_listings')->where('id',$id)->update($values);}
            else{$this->db->table('portal_listings')->insert($values);$id=(int)$this->db->insertID();}
            $payload['id']=$id;$payload['ref']=$previousPayload['ref']??'SP-'.$id;$this->db->table('portal_listings')->where('id',$id)->update(['payload'=>json_encode($payload,JSON_THROW_ON_ERROR)]);
            (new ResalePhotos($this->db))->attach($customer,$id,$input['photos']);
            $saved=$this->db->table('portal_listings')->where('id',$id)->get()->getRowArray();$event=$target?($target['review_state']==='needs_changes'?'customer_changes_resubmitted':($context['operation']==='edit'?'customer_edit_submitted':'customer_resubmitted')):'customer_submitted';
            (new ListingLifecycle($this->db))->record($event,$target,$saved,$customer,null,$verification['id']??null);
            $selected=$property;unset($selected['id']);$this->db->query('INSERT INTO portal_owned_properties(erp_source_id,erp_property_id,erp_head_id,erp_subhead_id,owner_reference,payload,portal_customer_id) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE erp_head_id=VALUES(erp_head_id),erp_subhead_id=VALUES(erp_subhead_id),owner_reference=VALUES(owner_reference),payload=VALUES(payload),portal_customer_id=VALUES(portal_customer_id)',[$source,$property['erp_property_id'],$property['erp_head_id'],$property['erp_subhead_id'],$property['erp_customer_id'],json_encode($selected,JSON_THROW_ON_ERROR),$customer]);
            if($verification)$this->db->table('resale_otp_challenges')->where('id',$verification['id'])->update(['consumed_at'=>time(),'otp_hash'=>'','payload'=>'']);
            (new AdminAuditService($this->db))->record('resale.'.$event,'resale_listings',null,'portal_listing',$id,null,['portal_customer_id'=>$customer,'selling_method'=>$method,'portal_agent_id'=>$agent['portal_agent_id']??null,'agent_erp_link_id'=>$agent['agent_erp_link_id']??null,'agent_source_id'=>$agent['agent_source_id']??null,'erp_ledger_id'=>$agent['erp_ledger_id']??null,'erp_source_id'=>$source]);
            if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('Listing could not commit.');return ['id'=>$id,'status'=>$status,'listing_purpose'=>$purpose,'review_state'=>$reviewState];
        }catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();if($existing=$this->existing($key,$customer))return $existing;if($e instanceof \App\Exceptions\CustomerFlowException)throw $e;if(!$target&&$this->db->table('portal_listings')->where('active_erp_property',ListingPolicy::key($source,$property['erp_property_id'],$purpose))->countAllResults())throw new InvalidArgumentException('This property already has an active or pending resale listing.');throw $e;}
    }
}
