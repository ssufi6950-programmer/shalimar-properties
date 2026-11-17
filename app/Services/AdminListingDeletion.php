<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\CustomerFlowException;
use CodeIgniter\Database\BaseConnection;

/** Retains the listing and its relationships; an immutable event records removal. */
final class AdminListingDeletion
{
    public function __construct(private BaseConnection $db) {}

    public static function retainedSql(string $alias='l'):string
    {
        if(!preg_match('/\A[a-z][a-z0-9_]*\z/D',$alias))throw new \InvalidArgumentException('Invalid listing alias.');
        return "NOT EXISTS (SELECT 1 FROM listing_events deleted_listing WHERE deleted_listing.listing_id=$alias.id AND deleted_listing.event='admin_deleted')";
    }

    public static function marked(array $row):bool
    {
        $payload=json_decode($row['payload']??'{}',true);
        return is_array($payload)&&!empty($payload['admin_deleted_at']);
    }

    public function deletion(int $id):?array
    {
        return $this->db->table('listing_events')->where('listing_id',$id)->where('event','admin_deleted')->orderBy('id','DESC')->get()->getRowArray();
    }

    public function delete(int $id,int $actor,int $version,string $fingerprint,string $reason=''):array
    {
        $permission=new AdminPermissionService($this->db);
        $required=['resale_listings.view','resale_listings.edit','resale_listings.deactivate'];
        if(!$permission->hasAllPermissions($actor,$required))throw new CustomerFlowException(403,'You do not have permission to delete listings.');
        $reason=trim($reason);
        if(!mb_check_encoding($reason,'UTF-8')||mb_strlen($reason)>500)throw new \InvalidArgumentException('Use a deletion reason of up to 500 characters.');
        if($version<1||!preg_match('/\A[a-f0-9]{64}\z/D',$fingerprint))throw new \InvalidArgumentException('Reload the listing before deleting it.');
        $this->db->transBegin();
        try{
            $row=(new AdminListingReview($this->db))->row($id,true);
            if(!$permission->hasAllPermissions($actor,$required))throw new CustomerFlowException(403,'You do not have permission to delete listings.');
            if($deleted=$this->deletion($id)){
                $saved=json_decode($deleted['next_record'],true);$context=$saved['_admin_review']??[];
                if((int)$deleted['admin_user_id']!==$actor||($context['reviewed_version']??null)!==$version||($context['reviewed_fingerprint']??null)!==$fingerprint||($context['reason']??'')!==$reason||!is_array($saved)||ListingLifecycle::fingerprint($row)!==ListingLifecycle::fingerprint($saved))throw new CustomerFlowException(409,'This listing has already been deleted.');
                $this->commit();return ['id'=>$id,'idempotent'=>true];
            }
            if((int)$row['record_version']!==$version||!hash_equals(ListingLifecycle::fingerprint($row),$fingerprint))throw new CustomerFlowException(409,'This listing changed. Reload it and confirm deletion again.','listing_changed');
            $payload=json_decode($row['payload'],true,128,JSON_THROW_ON_ERROR);
            if(!is_array($payload)||array_is_list($payload))throw new \InvalidArgumentException('The listing content must be reviewed before deletion.');
            $now=(new ShalimarAgents($this->db))->now();$payload['admin_deleted_at']=$now;
            // Keep closed outcomes intact. Active and pending records become Removed.
            $terminal=in_array(strtolower(trim($row['status'])),['sold','rented','cancelled','rejected','unlisted'],true)||in_array(strtolower(trim($row['deal'])),['sold','rented','cancelled','rejected'],true);
            $status=$terminal?$row['status']:'removed';$payload['status']=$status;
            $values=['status'=>$status,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'record_version'=>$version+1,'updated_at'=>$now];
            if(!$this->db->table('portal_listings')->where('id',$id)->where('record_version',$version)->update($values)||$this->db->affectedRows()!==1)throw new \RuntimeException('The listing could not be removed.');
            $saved=(new AdminListingReview($this->db))->row($id);$snapshot=$saved;
            $snapshot['_admin_review']=['reason'=>$reason,'reviewed_version'=>$version,'reviewed_fingerprint'=>$fingerprint];
            (new ListingLifecycle($this->db))->record('admin_deleted',$row,$snapshot,null,$actor);
            (new AdminAuditService($this->db))->record('listing.admin_deleted','resale_listings',$actor,'portal_listing',$id,['status'=>$row['status'],'version'=>$version],['status'=>$status,'version'=>$version+1,'reason'=>$reason]);
            $this->commit();return ['id'=>$id,'idempotent'=>false];
        }catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
    }

    private function commit():void
    {
        if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('The audited deletion could not be saved.');
    }
}
