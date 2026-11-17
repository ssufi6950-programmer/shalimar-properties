<?php
declare(strict_types=1);
namespace App\Services;

final class ErpPropertyClassification
{
    public const RENT_HOUSE_ONLY='Only properties with verified Constructed status can be listed for rent.';

    /** Rental listings require verified construction for every project and property type. */
    public static function rentEligible(array $property):bool
    {
        return ($property['classification_status'] ?? null) === 'verified'
            && ($property['maintenance_status'] ?? null) === 'constructed';
    }

    /** Public room details still require actual construction, not rent eligibility. */
    public static function verifiedConstructed(array $property):bool
    {
        return ($property['classification_status'] ?? null) === 'verified'
            && ($property['maintenance_status'] ?? null) === 'constructed';
    }

    public static function assertPurpose(array $property,string $purpose):void
    {
        if(ListingPolicy::purpose($purpose)==='rent'&&!self::rentEligible($property))throw new \InvalidArgumentException(self::RENT_HOUSE_ONLY);
    }
    /** Source-specific wording; never changes the stored inventory category. */
    public static function displayType(string $type, string $sourceCode = ''): string
    {
        if (strtoupper(trim($sourceCode)) !== 'ZALMI') {
            return $type;
        }

        return match (strtolower(trim($type))) {
            'apartment' => 'Corporate Office',
            'tower' => 'Commercial Outlet',
            default => $type,
        };
    }

    /** Older saved listings identify their source in the existing payload. */
    public static function listingType(array $payload, ?string $sourceCode = null): string
    {
        if ($sourceCode === null || $sourceCode === '') {
            $source = $payload['source'] ?? '';
            $sourceCode = is_string($source) && preg_match('/\AZALMI(?:\s|$)/i', trim($source)) ? 'ZALMI' : '';
        }

        $type = $payload['category'] ?? 'Property';
        $type = is_string($type) ? ucwords(str_replace('_', ' ', $type)) : 'Property';
        return self::displayType($type, $sourceCode);
    }

    public static function fromApi(array $row, string $sourceCode = ''):array
    {
        $base=$row['inventory_property_type']??$row['property_type']??'';$base=is_string($base)?mb_substr(trim($base),0,100):'';
        $state=$row['classification_status']??'not_supplied';$status=$row['maintenance_status']??null;
        if(!is_string($state)||!in_array($state,['verified','not_recorded','unavailable','ambiguous','unknown','not_supplied'],true))$state='unavailable';
        if($status!==null&&!is_string($status)){$status=null;$state='unavailable';}
        // ERP reports constructed plots as House in the customer portfolio.
        // Preserve the inventory category for every other category.
        $isPlot=strtolower(trim($base))==='plot';
        $type=$isPlot&&$state==='verified'&&$status==='constructed'?'House':($base!==''?$base:'Property');
        return ['type'=>self::displayType($type, $sourceCode),'inventory_property_type'=>$base,'maintenance_status'=>$status,'classification_status'=>$state,'construction_status'=>self::statusLabel(['classification_status'=>$state,'maintenance_status'=>$status])];
    }
    /** Show construction independently from the inventory type and booking eligibility. */
    public static function statusLabel(array $property):string
    {
        $state=$property['classification_status']??'not_supplied';
        if($state==='verified')return match($property['maintenance_status']??null){'constructed'=>'Constructed','under_constructed'=>'Under Construction','plot'=>'Plot',default=>'Unconfirmed'};
        return match($state){'not_recorded','not_supplied'=>'Not recorded','unavailable'=>'Unavailable',default=>'Unconfirmed'};
    }
    public static function category(string $type):string
    {
        $type=strtolower(trim($type));$aliases=['residential plot'=>'plot','commercial plot'=>'commercial','flat'=>'apartment','villa'=>'house','corporate office'=>'apartment','commercial outlet'=>'tower'];
        $type=$aliases[$type]??$type;
        return in_array($type,['house','apartment','plot','commercial','office','shop','farmhouse','plaza','tower'],true)?$type:'property';
    }
}
