<?php
namespace App\Services;

use App\Models\ErpSourceModel;
use Config\ErpConnections;
use RuntimeException;

/** Bounded, authenticated, GET-only client for the external agency dealer API. */
class ErpAgencyDealerApi extends ErpConnectionService
{
    public function __construct(ErpSourceModel $sources, ErpConnections $settings)
    {
        parent::__construct($sources,$settings);
    }

    public function page(array $source,int $agencyId,int $afterId,?int $snapshotMax,int $limit=200):array
    {
        if(!in_array($source['code']??null,['SGC','ZALMI'],true)||($source['auth_type']??'')!=='bearer')throw new RuntimeException('This ERP source is not configured for authenticated dealer sync.');
        if($agencyId<1||$afterId<0||$limit<1||$limit>200||($snapshotMax!==null&&($snapshotMax<0||$afterId>$snapshotMax)))throw new RuntimeException('Invalid dealer page request.');
        $base=self::normaliseBaseUrl($source['base_url']??null);if(!$base)throw new RuntimeException('Configure this ERP source base URL.');
        $host=strtolower((string)parse_url($base,PHP_URL_HOST));
        if(!in_array($host,array_map('strtolower',$this->settings->allowedHosts),true))throw new RuntimeException('This ERP host is not approved in server configuration.');
        $path=$this->settings->agencyDealerPaths[$source['code']]??null;
        if(!is_string($path)||!preg_match('#\A/(?!/)[A-Za-z0-9_./~-]+\z#D',$path)||preg_match('~(?:^|/)\.\.(?:/|$)~',$path))throw new RuntimeException('The external agency dealer endpoint is not configured.');
        $addresses=$this->connectionAddresses($host);if(!$addresses)throw new RuntimeException('ERP hostname could not be resolved.');foreach($addresses as$address)if(!self::isPublicAddress($address))throw new RuntimeException('Unsafe ERP destination blocked.');
        $query=['agency_id'=>(string)$agencyId,'after_id'=>(string)$afterId,'limit'=>(string)$limit];if($snapshotMax!==null)$query['snapshot_max_id']=(string)$snapshotMax;
        $url=$base.$path.'?'.http_build_query($query);$handle=curl_init($url);if($handle===false)throw new RuntimeException('Secure ERP HTTP client is unavailable.');$body='';$pinned=str_contains($addresses[0],':')?'['.$addresses[0].']':$addresses[0];
        curl_setopt_array($handle,[CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>array_merge($this->authenticationHeaders($source),['User-Agent: Shalimar-Portal-Agency-Dealer-Sync/1.0']),CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>max(1,min(3,$this->settings->connectTimeout)),CURLOPT_TIMEOUT=>max(1,min(4,$this->settings->requestTimeout)),CURLOPT_RESOLVE=>[$host.':443:'.$pinned],CURLOPT_HEADER=>false,CURLOPT_WRITEFUNCTION=>static function($curl,string$chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>1048576)return 0;$body.=$chunk;return strlen($chunk);}]);
        try{
            if(curl_exec($handle)===false){$errno=curl_errno($handle);if($errno===CURLE_OPERATION_TIMEDOUT)throw new RuntimeException('ERP dealer request timed out.');if(strlen($body)>=1048576)throw new RuntimeException('ERP dealer response exceeded the permitted size.');throw new RuntimeException('ERP dealer API could not be reached securely.');}
            $status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
            if($status===401||$status===403)throw new RuntimeException('ERP dealer API rejected authentication. Check this source credential configuration.');
            if($status!==200)throw new RuntimeException('ERP dealer API returned HTTP '.$status.'.');
        }finally{curl_close($handle);}
        try{$data=json_decode($body,true,64,JSON_THROW_ON_ERROR);}catch(\Throwable$e){throw new RuntimeException('ERP dealer API returned invalid JSON.');}
        if(!is_array($data))throw new RuntimeException('ERP dealer API response is invalid.');return$data;
    }
}
