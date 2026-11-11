<?php
declare(strict_types=1);
namespace App\Services;

final class ErpPropertyClassification
{
    public static function fromApi(array $row):array
    {
        $base=$row['inventory_property_type']??$row['property_type']??'';$base=is_string($base)?mb_substr(trim($base),0,100):'';
        $state=$row['classification_status']??'not_supplied';$status=$row['maintenance_status']??null;
        if(!is_string($state)||!in_array($state,['verified','not_recorded','unavailable','ambiguous','unknown','not_supplied'],true))$state='unavailable';
        if($status!==null&&!is_string($status)){$status=null;$state='unavailable';}
        $type=$state==='verified'&&$status==='constructed'?'House':($base!==''?$base:'Property');
        return ['type'=>$type,'inventory_property_type'=>$base,'maintenance_status'=>$status,'classification_status'=>$state,'construction_status'=>self::statusLabel(['classification_status'=>$state,'maintenance_status'=>$status])];
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
        $type=strtolower(trim($type));$aliases=['residential plot'=>'plot','commercial plot'=>'commercial','flat'=>'apartment','villa'=>'house'];
        $type=$aliases[$type]??$type;
        return in_array($type,['house','apartment','plot','commercial','office','shop','farmhouse','plaza','tower'],true)?$type:'property';
    }
}
