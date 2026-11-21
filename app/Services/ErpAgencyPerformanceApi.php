<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Standalone-public, server-side client for safe agency-level ERP totals. */
final class ErpAgencyPerformanceApi
{
    private const CACHE_SECONDS=60;
    private static function env(string $key,string $default=''):string
    {
        $value=$_ENV[$key]??$_SERVER[$key]??getenv($key);
        return is_string($value)?$value:$default;
    }
    private static function jsonSetting(string $key,array $default=[]):array
    {
        $raw=self::env($key);
        if($raw==='')return $default;
        $decoded=json_decode($raw,true);
        if(!is_array($decoded))throw new RuntimeException('ERP performance server configuration is invalid.');
        return $decoded;
    }
    private function headers():array
    {
        return ['Accept: application/json','User-Agent: Shalimar-Portal-Agency-Performance/1.0'];
    }
    private static function cachePath(string $key):?string
    {
        $dir=dirname(__DIR__,2).'/writable/cache/agency-performance';
        if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))return null;
        return is_writable($dir)?$dir.'/'.$key.'.json':null;
    }
    private static function readCache(?string $path):?array
    {
        if(!$path||!is_file($path)||filemtime($path)<time()-self::CACHE_SECONDS)return null;
        $data=json_decode((string)@file_get_contents($path),true);
        return is_array($data)?$data:null;
    }
    private static function writeCache(?string $path,array $data):void
    {
        if(!$path)return;
        $temporary=@tempnam(dirname($path),'ap-');
        if($temporary===false)return;
        try{if(@file_put_contents($temporary,json_encode($data,JSON_THROW_ON_ERROR),LOCK_EX)!==false)@rename($temporary,$path);}
        catch(\Throwable$error){}
        finally{if(is_file($temporary))@unlink($temporary);}
    }
    public function summaries(array $source,array $agencyIds,string $purpose,string $dateFrom,string $dateTo):array
    {
        if(!in_array($source['code']??null,['SGC','ZALMI'],true)||!$agencyIds||count($agencyIds)>100||!in_array($purpose,['all','sale','rent'],true)||!preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D',$dateFrom)||!preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D',$dateTo))throw new RuntimeException('Agency performance source configuration is incomplete.');
        $ids=[];foreach($agencyIds as$id){$id=(string)$id;if(!preg_match('/\A[1-9][0-9]{0,17}\z/D',$id))throw new RuntimeException('An ERP agency mapping is invalid.');$ids[$id]=$id;}
        sort($ids,SORT_STRING);$idSet=array_fill_keys($ids,true);
        $cacheKey=hash('sha256',json_encode([(string)($source['id']??$source['source_id']??''),(string)($source['base_url']??''),$source['code'],$purpose,$dateFrom,$dateTo,$ids],JSON_THROW_ON_ERROR));
        $cachePath=self::cachePath($cacheKey);$cached=self::readCache($cachePath);if($cached!==null)return$cached;
        $base=ErpConnectionService::normaliseBaseUrl($source['base_url']??null);
        if(!$base)throw new RuntimeException('ERP API base URL is not configured.');
        $host=strtolower((string)parse_url($base,PHP_URL_HOST));
        $allowed=self::jsonSetting('erp.allowedHosts');
        if(!in_array($host,array_map('strtolower',array_filter($allowed,'is_string')),true))throw new RuntimeException('ERP host is not approved in server configuration.');
        $paths=self::jsonSetting('erp.agencyPerformancePaths',['SGC'=>'/agency-performance.php','ZALMI'=>'/agency-performance.php']);
        $path=$paths[$source['code']]??null;
        if(!is_string($path)||!preg_match('#\A/(?!/)[A-Za-z0-9_./~-]+\.php\z#D',$path)||preg_match('~(?:^|/)\.\.(?:/|$)~',$path))throw new RuntimeException('Agency performance endpoint is not configured.');
        $overrides=self::jsonSetting('erp.publicAddressOverrides',[]);
        if(array_key_exists($host,$overrides))$addresses=$overrides[$host];
        else{$addresses=[];foreach(dns_get_record($host,DNS_A|DNS_AAAA)?:[]as$record){if(isset($record['ip']))$addresses[]=$record['ip'];if(isset($record['ipv6']))$addresses[]=$record['ipv6'];}}
        if(!is_array($addresses)||!$addresses||!array_is_list($addresses))throw new RuntimeException('ERP hostname could not be resolved.');
        foreach($addresses as$address)if(!is_string($address)||!ErpConnectionService::isPublicAddress($address))throw new RuntimeException('Unsafe ERP destination blocked.');

        $url=$base.$path.'?'.http_build_query(['agency_ids'=>implode(',',$ids),'listing_purpose'=>$purpose,'date_from'=>$dateFrom,'date_to'=>$dateTo]);
        $handle=curl_init($url);if($handle===false)throw new RuntimeException('Secure ERP HTTP client is unavailable.');$body='';
        $pinned=str_contains($addresses[0],':')?'['.$addresses[0].']':$addresses[0];
        curl_setopt_array($handle,[CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>$this->headers(),CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',
            CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_RESOLVE=>[$host.':443:'.$pinned],CURLOPT_HEADER=>false,
            CURLOPT_WRITEFUNCTION=>static function($curl,string$chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>1048576)return 0;$body.=$chunk;return strlen($chunk);}]);
        try{if(curl_exec($handle)===false)throw new RuntimeException('ERP performance API could not be reached securely.');$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);if($status===401||$status===403)throw new RuntimeException('ERP performance API rejected its configured credentials.');if($status!==200)throw new RuntimeException('ERP performance API returned HTTP '.$status.'.');}
        finally{curl_close($handle);}
        try{$data=json_decode($body,true,64,JSON_THROW_ON_ERROR);}catch(\Throwable$error){throw new RuntimeException('ERP performance API returned invalid JSON.');}
        if(!is_array($data)||($data['date_from']??null)!==$dateFrom||($data['date_to']??null)!==$dateTo||!is_array($data['summaries']??null)||!array_is_list($data['summaries']))throw new RuntimeException('ERP performance response does not match the expected date range or format.');
        $result=[];
        foreach($data['summaries']as$row){
            if(!is_array($row)||!is_string($row['agency_id']??null)||!isset($idSet[$row['agency_id']]))throw new RuntimeException('ERP returned an unexpected agency summary.');
            foreach(['sales_bookings','sale_party_entries','rentals']as$field){$excluded=($purpose==='rent'&&$field!=='rentals')||($purpose==='sale'&&$field==='rentals');if($excluded){if(!array_key_exists($field,$row)||$row[$field]!==null)throw new RuntimeException('ERP returned an unexpected agency total.');}elseif(!is_int($row[$field]??null)||$row[$field]<0||$row[$field]>100000000)throw new RuntimeException('ERP returned invalid agency totals.');}
            if(isset($result[$row['agency_id']]))throw new RuntimeException('ERP returned a duplicate agency summary.');
            $result[$row['agency_id']]=['sales_bookings'=>$row['sales_bookings'],'sale_party_entries'=>$row['sale_party_entries'],'rentals'=>$row['rentals']];
        }
        foreach($ids as$id)if(!isset($result[$id]))throw new RuntimeException('ERP omitted a requested agency summary.');
        self::writeCache($cachePath,$result);
        return $result;
    }
}
