<?php
declare(strict_types=1);
namespace App\Services;

/** Standalone-public/CI4-compatible transport using the existing ERP Sources and bearer refs. */
class ErpMarketApi
{
    public static function env(string $name,string $default=''):string
    {
        $v=$_ENV[$name]??$_SERVER[$name]??getenv($name);return is_string($v)?$v:$default;
    }
    private static function jsonSetting(string $name):array
    {
        $v=json_decode(self::env($name),true);if(!is_array($v))throw new \RuntimeException('ERP market configuration unavailable.');return $v;
    }
    public static function credential(array $source):string
    {
        $refs=self::jsonSetting('erp.credentialRefs');$ref=$refs[$source['credential_ref']??'']??null;
        if(($source['auth_type']??null)!=='bearer'||!is_array($ref)||($ref['type']??null)!=='bearer'||!preg_match('/\A[A-Z_][A-Z0-9_]{0,99}\z/D',$ref['tokenEnv']??''))throw new \RuntimeException('ERP market bearer reference unavailable.');
        $token=self::env($ref['tokenEnv']);if($token===''||strlen($token)>4096||preg_match('/[\r\n\x00]/',$token))throw new \RuntimeException('ERP market bearer credential unavailable.');return $token;
    }
    public function fetch(array $source,array $payload):array
    {
        $base=ErpConnectionService::normaliseBaseUrl($source['base_url']??null);if(!$base)throw new \RuntimeException('ERP market URL unavailable.');
        $host=strtolower((string)parse_url($base,PHP_URL_HOST));$allowed=self::jsonSetting('erp.allowedHosts');
        if(!in_array($host,array_map('strtolower',array_filter($allowed,'is_string')),true))throw new \RuntimeException('ERP market host is not approved.');
        $overrides=json_decode(self::env('erp.publicAddressOverrides','{}'),true);if(!is_array($overrides))throw new \RuntimeException('ERP market address configuration invalid.');
        if(array_key_exists($host,$overrides))$ips=$overrides[$host];else{$ips=[];foreach(dns_get_record($host,DNS_A|DNS_AAAA)?:[]as$row){if(isset($row['ip']))$ips[]=$row['ip'];if(isset($row['ipv6']))$ips[]=$row['ipv6'];}}
        if(!is_array($ips)||!$ips||!array_is_list($ips))throw new \RuntimeException('ERP market DNS unavailable.');
        foreach($ips as$ip)if(!is_string($ip)||!ErpConnectionService::isPublicAddress($ip))throw new \RuntimeException('Unsafe ERP market destination.');
        $token=self::credential($source);$paths=json_decode(self::env('erp.marketPaths','{"SGC":"/market-intelligence.php","ZALMI":"/market-intelligence.php"}'),true);$path=$paths[$source['code']]??null;
        if(!is_string($path)||!preg_match('~\A/[a-zA-Z0-9_-]+\.php\z~D',$path))throw new \RuntimeException('ERP market endpoint path invalid.');
        $ip=str_contains($ips[0],':')?'['.$ips[0].']':$ips[0];$body='';$curl=curl_init($base.$path);
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json','Authorization: Bearer '.$token],
            CURLOPT_PROXY=>'',CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_RESOLVE=>[$host.':443:'.$ip],CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>6,
            CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>2097152)return 0;$body.=$chunk;return strlen($chunk);}]);
        try{$ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);if($ok===false||$status!==200){error_log('[ERP market] source='.$source['code'].' http='.$status.' curl='.curl_errno($curl));throw new \RuntimeException('ERP market request failed.');}
            $data=json_decode($body,true,64,JSON_THROW_ON_ERROR);if(!is_array($data))throw new \RuntimeException('Invalid ERP market JSON.');return $data;
        }finally{curl_close($curl);}
    }
}
