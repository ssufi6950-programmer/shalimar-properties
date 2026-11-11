<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateListingPurposeReviewFoundation extends Migration
{
    public function up()
    {
        // MariaDB/MySQL DDL implicitly commits. Deploy while listing writes are paused.
        $columns=array_column($this->db->getFieldData('portal_listings'),'name');
        if(in_array('listing_purpose',$columns,true)||in_array('review_state',$columns,true))throw new \RuntimeException('Foundation columns already exist without this migration entry. Verify the partial migration/backfill before resuming; do not grandfather new rows automatically.');
        $cutoff=(int)($this->db->table('portal_listings')->selectMax('id')->get()->getRow()->id??0);
        $this->db->query("ALTER TABLE portal_listings ADD listing_purpose ENUM('sale','rent') CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'sale', ADD review_state ENUM('under_review','approved','rejected','needs_changes') CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'under_review'");
        // Only pre-existing IDs may be grandfathered; no status, payload or timestamps are rewritten.
        $json="CASE WHEN JSON_VALID(l.payload) THEN l.payload ELSE '{}' END";
        $this->db->query("UPDATE portal_listings l SET listing_purpose=CASE WHEN JSON_UNQUOTE(JSON_EXTRACT($json,'$.purpose'))='rent' THEN 'rent' ELSE 'sale' END,
            review_state=CASE WHEN l.status='rejected' THEN 'rejected'
                WHEN l.status IN ('live','approved','active','sold') OR l.deal='sold' THEN 'approved'
                WHEN EXISTS(SELECT 1 FROM listing_events e WHERE e.listing_id=l.id AND e.event IN ('customer_published','customer_republished') AND e.to_status IN ('live','approved','active')) THEN 'approved'
                WHEN l.status NOT IN ('pending','pending_approval') AND JSON_TYPE(JSON_EXTRACT($json,'$.published_at'))='STRING' AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT($json,'$.published_at')),'')<>'' THEN 'approved'
                ELSE 'under_review' END WHERE l.id<=?",[$cutoff]);
        $this->db->query("ALTER TABLE portal_listings MODIFY COLUMN active_erp_property VARCHAR(80) GENERATED ALWAYS AS (CASE WHEN status IN ('pending','pending_approval','live','approved','active','cancellation_requested') AND deal IN ('available','active') AND review_state IN ('under_review','approved','needs_changes') AND erp_source_id IS NOT NULL AND erp_property_id IS NOT NULL THEN CONCAT(erp_source_id,':',erp_property_id,':',listing_purpose) ELSE NULL END) STORED");
    }
    public function down()
    {
        throw new \RuntimeException('Automatic rollback could discard Rent/review decisions or collapse Sale/Rent identities. Restore the verified pre-migration backup with its matching application version instead.');
    }
}
