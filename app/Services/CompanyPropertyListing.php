<?php
declare(strict_types=1);
namespace App\Services;
use CodeIgniter\Database\BaseConnection;

/** Explicit Admin-origin listings; never creates a customer or ownership claim. */
final class CompanyPropertyListing
{
    public function __construct(private BaseConnection $db){}
    public static function owned(array $row):bool
    {
        $payload=json_decode($row['payload']??'',true);
        return ($row['portal_customer_id']??null)===null&&($payload['_company_inventory']['origin']??null)==='admin_company_inventory';
    }
    public function row(int $id,bool $lock=false):array
    {
        $row=$this->db->query('SELECT l.* FROM portal_listings l WHERE l.id=? AND '.AdminListingDeletion::retainedSql('l').($lock?' FOR UPDATE':''),[$id])->getRowArray();
        if(!$row||!self::owned($row)||AdminListingDeletion::marked($row))throw new \InvalidArgumentException('Company listing not found.');
        return $row;
    }
    public function property(int $sourceId,string $property):array
    {
        $source=$this->db->table('erp_sources')->where('id',$sourceId)->where('is_active',1)->get()->getRowArray();
        if(!$source||!in_array($source['code'],['SGC','ZALMI'],true))throw new \InvalidArgumentException('Choose an enabled SGC or Zalmi source.');
        $data=(new ExplorerErpClient(new \App\Models\ErpSourceModel($this->db),config('ErpConnections')))->companyProperties($source,'0',$property);
        $rows=$data['records']??null;
        if(!is_array($rows)||count($rows)!==1||(string)($rows[0]['id']??'')!==$property||($rows[0]['smp_active']??null)!=='yes')throw new \InvalidArgumentException('This property is no longer marked smp_active = yes. Refresh Company Properties.');
        $record=$rows[0];foreach($record as$value)if(!is_scalar($value)&&$value!==null)throw new \RuntimeException('Invalid inventory response.');
        $mappings=$this->db->query('SELECT m.*,p.name AS project_name FROM erp_project_mappings m'.ErpVisibility::joins().' WHERE m.erp_source_id=? AND m.erp_head_id=? AND m.erp_subhead_id=? AND '.ErpVisibility::mappingSql().' LIMIT 2',[$sourceId,$record['head']??0,$record['sub_head']??0])->getResultArray();
        if(count($mappings)!==1)throw new \InvalidArgumentException('Configure one eligible published project mapping for this exact Head/Subhead before listing.');
        return ['source'=>$source,'inventory'=>$record,'mapping'=>$mappings[0],'classification'=>ErpPropertyClassification::fromApi($record,$source['code']),'fetched_at'=>microtime(true)];
    }
    public static function draft(array $input):array
    {
        $purpose=ListingPolicy::purpose($input['listing_purpose']??'sale');
        $description=$input['description']??'';
        if(!is_string($description)||!mb_check_encoding($description,'UTF-8')||mb_strlen($description)>500||trim($description)==='')throw new \InvalidArgumentException('Enter a description of 1?500 characters.');
        $rental=$purpose==='rent'?ListingRentalTerms::validate(is_array($input['rental']??null)?$input['rental']:[]):null;
        $price=$purpose==='rent'?$rental['monthly_rent']:ListingRentalTerms::amount($input['asking_price']??null,'Asking sale price');
        $agent=$input['portal_agent_id']??'';
        if(!is_string($agent)||!preg_match('/\A[1-9][0-9]{0,9}\z/D',$agent))throw new \InvalidArgumentException('Select a Shalimar Agent.');
        if(($input['confirmed']??null)!=='1')throw new \InvalidArgumentException('Confirm you are authorized to publish this property.');
        return ['listing_purpose'=>$purpose,'description'=>trim($description),'rental'=>$rental,'price'=>$price,'agent_id'=>(int)$agent,'negotiable'=>($input['negotiable']??'')==='1'];
    }
    public function save(int $actor,int $source,string $property,array $input,array $files,?int $id=null):int
    {
        $permissions=['properties.view','resale_listings.view','resale_listings.edit','resale_listings.approve'];
        if(!(new AdminPermissionService($this->db))->hasAllPermissions($actor,$permissions))throw new \InvalidArgumentException('You do not have permission to publish company listings.');
        $draft=self::draft($input);$fresh=$this->property($source,$property);
        ErpPropertyClassification::assertPurpose($fresh['classification'],$draft['listing_purpose']);
        $version=$input['version']??'0';if(!is_string($version)||!preg_match('/\A[0-9]{1,10}\z/D',$version))throw new \InvalidArgumentException('Reload the listing form.');
        $key=$input['submission_key']??'';if(!is_string($key)||!preg_match('/\A[a-f0-9]{64}\z/D',$key))throw new \InvalidArgumentException('Reload the listing form.');
        $newFiles=[];$committed=false;
        $this->db->transBegin();
        try{
            $previous=$id?$this->row($id,true):null;
            if($previous&&((int)$previous['erp_source_id']!==$source||(string)$previous['erp_property_id']!==$property||(int)$previous['record_version']!==(int)$version||$previous['listing_purpose']!==$draft['listing_purpose']))throw new \InvalidArgumentException('Listing identity, purpose or version changed. Reload.');
            if($previous&&($previous['review_state']!=='approved'||!in_array($previous['status'],['live','approved','active'],true)||!in_array($previous['deal'],['available','active'],true)))throw new \InvalidArgumentException('This company listing is read-only. Create a fresh listing where permitted.');
            if(microtime(true)-$fresh['fetched_at']>15)throw new \InvalidArgumentException('Inventory check expired. Try again.');
            $m=$fresh['mapping'];
            if(!$this->db->query('SELECT m.id FROM erp_project_mappings m'.ErpVisibility::joins().' WHERE m.id=? AND m.erp_source_id=? AND m.erp_head_id=? AND m.erp_subhead_id=? AND '.ErpVisibility::mappingSql().' FOR UPDATE',[$m['id'],$source,$m['erp_head_id'],$m['erp_subhead_id']])->getRowArray())throw new \InvalidArgumentException('Project mapping changed.');
            (new ListingLifecycle($this->db))->assertNoAdminRestriction($source,$property,$draft['listing_purpose']);
            $duplicate=$this->db->query('SELECT id,submission_key FROM portal_listings WHERE active_erp_property=? FOR UPDATE',[ListingPolicy::key($source,$property,$draft['listing_purpose'])])->getRowArray();
            if($duplicate&&(!$previous||(int)$duplicate['id']!==$id))throw new \InvalidArgumentException('An active or pending listing already exists for this property and purpose.');
            $agent=(new ShalimarAgents($this->db))->requireAdminAssignable($source,$draft['agent_id'],true);ListingSellerContact::requireBusiness($agent);
            $now=(new ShalimarAgents($this->db))->now();$record=$fresh['inventory'];
            $prior=$previous?json_decode($previous['payload'],true,128,JSON_THROW_ON_ERROR):[];
            $images=CompanyPropertyImages::save($files,$prior['images']??[],$input['remove_images']??[],$newFiles);
            $main=$input['main_image']??'';if(!is_string($main))throw new \InvalidArgumentException('Choose a valid main image.');
            if($main!==''&&in_array($main,$images,true)){$images=array_values(array_diff($images,[$main]));array_unshift($images,$main);}
            $number=trim((string)($record['property_no']??''));if($number==='')$number=trim((string)($record['plot_no']??''));if($number==='')$number=trim(($record['file_no_series']??'').'-'.($record['file_no']??''),'-');
            if($number==='')$number='Inventory '.$property;
            $floor=trim((string)($record['floor']??''));if($floor==='')$floor=trim((string)($record['floor_no']??''));if($floor!=='')$number.=' ? '.$floor;
            if(microtime(true)-$fresh['fetched_at']>15)throw new \InvalidArgumentException('Inventory check expired while preparing photos. Try fewer photos and retry.');
            $category=ErpPropertyClassification::category($fresh['classification']['type']);
            preg_match('/[0-9]+(?:\.[0-9]+)?/',(string)($record['size_label']??$record['size']??''),$size);
            $snapshot=['portal_agent_id'=>(int)$agent['portal_agent_id'],'agent_source'=>$agent['agent_source'],'agent_source_id'=>(int)$agent['agent_source_id'],'erp_ledger_id'=>(string)$agent['erp_ledger_id'],'erp_dealer_id'=>$agent['erp_dealer_id'],'erp_agency_id'=>(string)$agent['erp_agency_id'],'name'=>$agent['name'],'mobile'=>$agent['mobile'],'email'=>$agent['email'],'image_filename'=>$agent['image_filename']??'','recorded_at'=>$now,'assigned_by'=>$actor];
            $payload=array_replace($prior,['id'=>$id??0,'ref'=>$prior['ref']??'','title'=>$m['project_name'].' - '.$number,'purpose'=>$draft['listing_purpose']==='rent'?'rent':'resale','category'=>$category,'project'=>$m['project_slug'],'sector'=>(string)($record['sector']??''),'block'=>(string)($record['block_no']??''),'plot_no'=>$number,'floor'=>$floor,'city'=>'','location'=>$record['subhead_name']??'','price'=>(int)$draft['price'],'size'=>isset($size[0])?(float)$size[0]:0,'size_label'=>$record['size_label']??'','unit'=>stripos($record['size_label']??'','feet')!==false||stripos($record['size_label']??'','sq')!==false?'sqft':(stripos($record['size_label']??'','marla')!==false?'marla':''),'beds'=>0,'baths'=>0,'description'=>$draft['description'],'negotiable'=>$draft['negotiable'],'rental'=>$draft['rental'],'images'=>$images?:AdminListingPhotos::placeholder(['category'=>$category]),'images_status'=>'approved','status'=>'live','deal'=>'available','published_at'=>$prior['published_at']??$now,'approved_at'=>$now,'created_at'=>$prior['created_at']??$now,'source'=>$fresh['source']['code'].' company inventory','selling_method'=>'shalimar_agent','agent_snapshot'=>$snapshot,'approval_mode'=>'manual','poster'=>['role'=>'agent','party_id'=>(int)$agent['portal_agent_id'],'name'=>$agent['name'],'phone'=>$agent['mobile'],'email'=>$agent['email'],'verified'=>true],
                '_company_inventory'=>['origin'=>'admin_company_inventory','created_by'=>$prior['_company_inventory']['created_by']??$actor,'updated_by'=>$actor,'company_id'=>(string)$record['company_id'],'inventory_status'=>$record['status']??null,'booking_id'=>$record['booking_id']??null,'maintenance_status'=>$fresh['classification']['maintenance_status'],'classification_status'=>$fresh['classification']['classification_status'],'checked_at'=>$now],
                '_explorer_snapshot'=>ExplorerListingSnapshot::capture(json_decode($record['explorer_snapshot_json']??'null',true),$source,$property,(string)$m['erp_head_id'],(string)$m['erp_subhead_id'])]);
            $values=['portal_customer_id'=>null,'erp_source_id'=>$source,'erp_property_id'=>$property,'project_slug'=>$m['project_slug'],'mapping_id'=>$m['id'],'listing_purpose'=>$draft['listing_purpose'],'selling_method'=>'shalimar_agent','asking_price'=>$draft['price'],'status'=>'live','deal'=>'available','review_state'=>'approved','record_version'=>$previous?(int)$previous['record_version']+1:1,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'portal_agent_id'=>$agent['portal_agent_id'],'agent_erp_link_id'=>$agent['agent_erp_link_id'],'agent_source_id'=>$agent['agent_source_id'],'agent_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR),'submission_key'=>$previous['submission_key']??$key,'updated_at'=>$now];
            if($previous){$this->db->table('portal_listings')->where('id',$id)->update($values);}else{$values['created_at']=$now;$this->db->table('portal_listings')->insert($values);$id=(int)$this->db->insertID();}
            if($id<1)throw new \RuntimeException('Listing save failed.');
            $payload['id']=$id;$payload['ref']=$prior['ref']??'SP-'.$id;$values['payload']=json_encode($payload,JSON_THROW_ON_ERROR);$this->db->table('portal_listings')->where('id',$id)->update(['payload'=>$values['payload']]);
            $saved=$this->row($id);(new AdminListingPhotos($this->db))->requirePublishable($saved);
            (new ListingLifecycle($this->db))->record('admin_company_published',$previous,$saved,null,$actor);
            (new AdminAuditService($this->db))->record('listing.admin_company_published','resale_listings',$actor,'portal_listing',$id,null,['erp_source_id'=>$source,'status'=>'live','version'=>$saved['record_version']]);
            if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('Listing could not commit.');$committed=true;
            return $id;
        }catch(\Throwable $e){if(!$committed){$this->db->transRollback();$this->db->resetTransStatus();foreach($newFiles as$file)if(is_file($file))unlink($file);}throw $e;}
    }
    public function unpublish(int $id,int $actor,int $version):void
    {
        if(!(new AdminPermissionService($this->db))->hasAllPermissions($actor,['properties.view','resale_listings.view','resale_listings.deactivate']))throw new \InvalidArgumentException('You cannot unpublish company listings.');
        $this->db->transBegin();try{
            $row=$this->row($id,true);if(!in_array($row['status'],['live','approved','active'],true))throw new \InvalidArgumentException('This listing is already unpublished.');if((int)$row['record_version']!==$version)throw new \InvalidArgumentException('Reload the latest listing.');
            $payload=json_decode($row['payload'],true);$payload['status']='unpublished';$now=(new ShalimarAgents($this->db))->now();$payload['unpublished_at']=$now;
            $this->db->table('portal_listings')->where('id',$id)->update(['status'=>'unpublished','payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'updated_at'=>$now,'record_version'=>$version+1]);
            (new ListingLifecycle($this->db))->record('admin_company_unpublished',$row,$this->row($id),null,$actor);
            (new AdminAuditService($this->db))->record('listing.admin_company_unpublished','resale_listings',$actor,'portal_listing',$id,null,['status'=>'unpublished']);
            if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('Unpublish failed.');
        }catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
    }
}
