<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\CustomerFlowException;
use App\Libraries\CustomerIdentity;
use App\Libraries\CustomerSecrets;
use CodeIgniter\Database\BaseConnection;

/** Refreshes only an approved SGC listing's saved Plot/House display category. */
final class AdminPropertyTypeRefresh
{
    public function __construct(private BaseConnection $db) {}

    private function current(int $id): array
    {
        $row=(new AdminListingReview($this->db))->row($id);
        if(!ListingPolicy::active($row))throw new CustomerFlowException(409,'Only an active, approved public listing can be refreshed.','listing_not_active');
        $source=$this->db->table('erp_sources')->select('id,code,name,is_active')->where('id',$row['erp_source_id'])->get()->getRowArray();
        if(!$source||(int)$source['is_active']!==1||strtoupper((string)$source['code'])!=='SGC')throw new CustomerFlowException(409,'This refresh is available only for active SGC listings.','source_not_supported');
        $payload=json_decode($row['payload'],true,128,JSON_THROW_ON_ERROR);
        if(!is_array($payload)||strtolower(trim((string)($payload['category']??'')))!=='plot')throw new CustomerFlowException(409,'Only a listing currently saved as Plot can use this refresh.','not_saved_as_plot');

        $links=$this->db->table('customer_erp_links')->select('erp_customer_id,verified_mobile,verified_at')
            ->where('portal_customer_id',$row['portal_customer_id'])->where('erp_source_id',$row['erp_source_id'])
            ->where('verified_at IS NOT NULL',null,false)->orderBy('id')->get()->getResultArray();
        if(!$links)throw new CustomerFlowException(409,'The listing owner has no verified SGC identity link.','identity_link_missing');
        $secrets=new CustomerSecrets(config('CustomerPortal')->identityKey);
        $identities=[];
        foreach($links as$link){
            $secret=$secrets->decrypt((string)$link['verified_mobile']);
            $identities[]=['portal_customer_id'=>(int)$row['portal_customer_id'],'erp_source_id'=>(int)$row['erp_source_id'],
                'erp_customer_id'=>(string)$link['erp_customer_id'],'mobile'=>CustomerIdentity::mobile((string)($secret['mobile']??'')),
                'source_name'=>(string)$source['name'],'source_code'=>'SGC'];
        }
        $portfolio=new CustomerPortfolio($this->db,new CustomerErpApi(config('CustomerPortal'),$this->db));
        $property=null;
        foreach($identities as$identity){
            $property=$portfolio->find([$identity],(int)$row['erp_source_id'],$identity['erp_customer_id'],(string)$row['erp_property_id'],(int)$row['id'],(string)$row['listing_purpose']);
            if($property!==null)break;
        }
        if(!$property)throw new CustomerFlowException(409,'The current SGC ownership response did not confirm this property for the listing owner.','property_not_confirmed');
        if((int)$property['erp_source_id']!==(int)$row['erp_source_id']||$property['erp_property_id']!==(string)$row['erp_property_id']||$property['mapping_id']!==(int)$row['mapping_id'])throw new CustomerFlowException(409,'The current SGC property mapping differs from this listing.','property_mapping_changed');
        if(strtolower(trim((string)$property['inventory_property_type']))!=='plot')throw new CustomerFlowException(409,'The current SGC inventory type is not Plot; no listing change was made.','inventory_type_mismatch');
        if(($property['classification_status']??null)!=='verified'||($property['maintenance_status']??null)!=='constructed')throw new CustomerFlowException(409,'SGC has not confirmed a single current Constructed status for this property. No listing change was made.','construction_not_confirmed');

        return ['row'=>$row,'payload'=>$payload,'property'=>$property];
    }

    public function preview(int $id): array
    {
        $data=$this->current($id);$payload=$data['payload'];
        return ['saved_type'=>ErpPropertyClassification::listingType($payload,'SGC'),
            'inventory_type'=>$data['property']['inventory_property_type'],
            'construction_status'=>$data['property']['construction_status'],
            'suggested_type'=>'House','change_needed'=>strtolower(trim((string)($payload['category']??'')))!=='house'];
    }

    public function apply(int $id,int $actor,int $version,string $fingerprint): void
    {
        if($actor<1||$version<1||!preg_match('/\A[a-f0-9]{64}\z/D',$fingerprint))throw new \InvalidArgumentException('Reload the current listing review before refreshing its type.');
        // Keep network access outside the transaction; the listing is rechecked under lock below.
        $fresh=$this->current($id);$old=$fresh['row'];$payload=$fresh['payload'];
        $this->db->transBegin();
        try{
            $locked=(new AdminListingReview($this->db))->row($id,true);
            if((int)$locked['record_version']!==$version||!hash_equals((new AdminListingReview($this->db))->fingerprint($locked),$fingerprint))throw new CustomerFlowException(409,'The listing changed during the ERP check. Reload and try again.','listing_changed');
            if(!ListingPolicy::active($locked)||strtolower(trim((string)(json_decode($locked['payload'],true,128,JSON_THROW_ON_ERROR)['category']??'')))!=='plot')throw new CustomerFlowException(409,'The listing is no longer eligible for this type refresh.','listing_changed');
            $now=(new ShalimarAgents($this->db))->now();$payload['category']='house';
            $values=['payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'record_version'=>$version+1,'updated_at'=>$now];
            $this->db->table('portal_listings')->where('id',$id)->where('record_version',$version)->update($values);
            if($this->db->affectedRows()!==1)throw new CustomerFlowException(409,'The listing changed before the type could be saved. Reload and try again.','listing_changed');
            // Add a matching approval snapshot so the public eligibility check remains valid.
            (new ListingLifecycle($this->db))->record('admin_property_type_refreshed',$old,array_replace($old,$values),null,$actor);
            (new AdminAuditService($this->db))->record('listing.property_type_refreshed','resale_listings',$actor,'portal_listing',$id,
                ['category'=>'plot','erp_source_id'=>(int)$old['erp_source_id'],'erp_property_id'=>(string)$old['erp_property_id']],
                ['category'=>'house','maintenance_status'=>'constructed','classification_status'=>'verified','version'=>$version+1]);
            if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('The type refresh could not be committed.');
        }catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
    }
}
