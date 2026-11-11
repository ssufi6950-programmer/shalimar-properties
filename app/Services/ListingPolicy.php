<?php
declare(strict_types=1);
namespace App\Services;

/** Sale/Rent identity and admin review are independent of lifecycle and ERP ownership. */
final class ListingPolicy
{
    public static function purpose($value='sale'):string
    {
        if(!is_string($value)||!in_array($value,['sale','rent'],true))throw new \InvalidArgumentException('Choose a valid listing purpose: Sale or Rent.');return $value;
    }
    public static function key(int $source,string $property,string $purpose='sale'):string
    {
        if($source<1||!preg_match('/\A[1-9][0-9]{0,17}\z/D',$property))throw new \InvalidArgumentException('A verified ERP property identity is required.');
        return $source.':'.$property.':'.self::purpose($purpose);
    }
    public static function publicSql(string $alias='l'):string
    {
        if(!preg_match('/\A[a-z][a-z0-9_]*\z/D',$alias))throw new \InvalidArgumentException('Invalid listing alias.');
        // Phase 3 records are public only while they match their committed approval.
        // Grandfathered records (including older OTP-backed Sales) retain Phase 1 approval.
        $matches=[];
        foreach(['record_version','payload','portal_customer_id','erp_source_id','erp_property_id','mapping_id','project_slug','asking_price','selling_method','portal_agent_id','agent_erp_link_id','agent_source_id','portal_agency_id','agency_erp_link_id','resale_verification_id','owner_verification','listing_purpose'] as $column) {
            $matches[]="(BINARY CAST($alias.$column AS CHAR) <=> BINARY NULLIF(JSON_UNQUOTE(JSON_EXTRACT(public_approval.next_record,'$.$column')),'null'))";
        }
        $approval="(NOT EXISTS (SELECT 1 FROM listing_events public_submission WHERE public_submission.listing_id=$alias.id AND public_submission.event IN ('customer_submitted','customer_edit_submitted','customer_resubmitted','customer_changes_resubmitted')) OR EXISTS (SELECT 1 FROM listing_events public_approval WHERE public_approval.listing_id=$alias.id AND public_approval.event='admin_approved_published' AND ".implode(' AND ',$matches).'))';
        return "BINARY $alias.review_state=BINARY 'approved' AND $alias.listing_purpose IN ('sale','rent') AND BINARY $alias.status IN ('live','approved','active') AND BINARY $alias.deal IN ('available','active') AND $approval";
    }
    public static function active(array $row):bool
    {
        return ($row['review_state']??null)==='approved'&&in_array($row['listing_purpose']??null,['sale','rent'],true)
            &&in_array($row['status']??null,['live','approved','active'],true)&&in_array($row['deal']??null,['available','active'],true);
    }
}
