<?php
declare(strict_types=1);
namespace App\Services;

/** Rental amounts are whole PKR. Terms are stored in the existing listing payload. */
final class ListingRentalTerms
{
    public static function amount($value,string $label,bool $optional=false):?string
    {
        if($optional&&($value===null||$value===''))return null;
        if((!is_string($value)&&!is_int($value))||!preg_match($optional?'/\A(?:0|[1-9][0-9]{0,11})\z/D':'/\A[1-9][0-9]{0,11}\z/D',(string)$value))throw new \InvalidArgumentException($label.' must be a whole PKR amount '.($optional?'from 0':'from 1').' to 999,999,999,999.');
        return(string)$value;
    }
    public static function validate(array $input):array
    {
        $furnishing=$input['furnishing']??null;if($furnishing==='')$furnishing=null;
        if($furnishing!==null&&!in_array($furnishing,['furnished','unfurnished','partially_furnished'],true))throw new \InvalidArgumentException('Choose a valid furnishing option.');
        $months=$input['lease_duration_months']??null;if($months==='')$months=null;
        if($months!==null&&((!is_string($months)&&!is_int($months))||!preg_match('/\A[1-9][0-9]{0,2}\z/D',(string)$months)||(int)$months>120))throw new \InvalidArgumentException('Lease duration must be between 1 and 120 months.');
        return ['monthly_rent'=>self::amount($input['monthly_rent']??null,'Monthly rent'),'security_deposit'=>self::amount($input['security_deposit']??null,'Security deposit',true),'advance_rent'=>self::amount($input['advance_rent']??null,'Advance rent',true),'furnishing'=>$furnishing,'lease_duration_months'=>$months===null?null:(int)$months];
    }
}
