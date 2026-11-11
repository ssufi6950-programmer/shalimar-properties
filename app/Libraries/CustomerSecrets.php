<?php
namespace App\Libraries;
final class CustomerSecrets
{
 private string $key;
 public function __construct(string $key){$raw=base64_decode($key,true);if($raw===false||strlen($raw)!==32)throw new \RuntimeException('Customer identity encryption is not configured.');$this->key=$raw;}
 public function digest(string $purpose,string $value):string{return hash_hmac('sha256',$purpose."\0".$value,$this->key);}
 public function encrypt(array $data):string{$nonce=random_bytes(12);$tag='';$cipher=openssl_encrypt(json_encode($data,JSON_THROW_ON_ERROR),'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,$nonce,$tag,'customer-identity-v1');if($cipher===false)throw new \RuntimeException('Identity encryption failed.');return base64_encode($nonce.$tag.$cipher);}
 public function decrypt(string $value):array{$raw=base64_decode($value,true);if($raw===false||strlen($raw)<28)throw new \RuntimeException('Invalid encrypted identity.');$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'customer-identity-v1');if($plain===false)throw new \RuntimeException('Invalid encrypted identity.');$result=json_decode($plain,true,64,JSON_THROW_ON_ERROR);if(!is_array($result))throw new \RuntimeException('Invalid encrypted identity.');return $result;}
 public function otpHash(string $otp):string{return password_hash($this->digest('otp',$otp),PASSWORD_DEFAULT);}
 public function otpMatches(string $otp,string $hash):bool{return password_verify($this->digest('otp',$otp),$hash);}
}
