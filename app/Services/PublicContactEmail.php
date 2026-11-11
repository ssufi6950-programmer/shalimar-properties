<?php
namespace App\Services;

/** Explicit public contact addresses only; never reads account or ERP contacts. */
final class PublicContactEmail
{
    public static function normalize($value): ?string
    {
        if (!is_string($value) || strlen($value)>254 || preg_match('/[\x00-\x1f\x7f]/',$value)) return null;
        $value=trim($value);
        if ($value==='' || !filter_var($value,FILTER_VALIDATE_EMAIL)) return null;
        $at=strrpos($value,'@');
        return substr($value,0,$at+1).strtolower(substr($value,$at+1));
    }

    public static function optional($value): string
    {
        if (is_string($value) && !preg_match('/[\x00-\x1f\x7f]/',$value) && trim($value)==='') return '';
        $email=self::normalize($value);
        if ($email===null) throw new \InvalidArgumentException('Enter a valid public contact email address.');
        return $email;
    }
}
