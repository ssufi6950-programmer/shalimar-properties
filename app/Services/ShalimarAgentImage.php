<?php
declare(strict_types=1);
namespace App\Services;

/** Explicitly flagged staff profile photos only; never identity-document photos. */
final class ShalimarAgentImage
{
    public static function filename(mixed $value):string
    {
        if(!is_string($value))return '';
        $value=trim($value);
        return strlen($value)<=255 && preg_match('/\A[A-Za-z0-9_-][A-Za-z0-9_. -]*\.(?:jpe?g|png|webp|gif)\z/iD',$value) && !str_contains($value,'..') ? $value : '';
    }
    public static function url(mixed $filename,string $source='SGC'):string
    {
        $file=self::filename($filename);
        $origin=match($source){'SGC'=>'https://sgcacc.com','ZALMI'=>'https://zalmidevelopments.com/ERP786-ZALMIDEV',default=>''};
        return $file!==''&&$origin!==''?$origin.'/customer-images/'.rawurlencode($file):'';
    }
}
