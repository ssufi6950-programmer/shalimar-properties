<?php
namespace App\Libraries;
final class CustomerIdentity
{
 public static function cnic(string $value):string{$value=trim($value);if(!preg_match('/\A(?:[0-9]{13}|[0-9]{5}-[0-9]{7}-[0-9])\z/D',$value))throw new \InvalidArgumentException('Enter a valid 13-digit CNIC, with or without hyphens.');return str_replace('-','',$value);}
 public static function mobile(string $value):string{$value=trim($value);if(!preg_match('/\A\+?[0-9 ()-]+\z/D',$value))throw new \InvalidArgumentException('Enter a valid Pakistani mobile number.');$digits=preg_replace('/[^0-9]/','',$value);if(preg_match('/\A03[0-9]{9}\z/D',$digits))return '92'.substr($digits,1);if(preg_match('/\A923[0-9]{9}\z/D',$digits))return $digits;throw new \InvalidArgumentException('Use 03XXXXXXXXX or +923XXXXXXXXX.');}
 public static function mask(string $mobile):string{return '+92 3******'.substr($mobile,-4);}
}
