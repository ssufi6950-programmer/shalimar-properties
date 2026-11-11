<?php
namespace App\Services;

class ErpAgencyApi extends ErpConnectionService
{
    public function page(array $source,string $after='0',?string $maximum=null,int $limit=100): array
    {
        $query=['after_id'=>$after,'limit'=>$limit];if ($maximum!==null) $query['snapshot_max_id']=$maximum;
        return $this->directoryPage($source,$this->settings->agencyPaths[$source['code']]??null,$query);
    }
    protected function directoryPage(array $source,?string $path,array $query):array
    {
        if (($source['auth_type']??'')!=='bearer') throw new \RuntimeException('Agency sync requires the existing ERP bearer credential.');
        $base=self::normaliseBaseUrl($source['base_url']??null);
        if (!$base||!is_string($path)||!preg_match('#\A/(?!/)[A-Za-z0-9_./~-]+\z#D',$path)||preg_match('~(?:^|/)\.\.(?:/|$)~',$path)) throw new \RuntimeException('The agency endpoint is not configured for this source.');
        $host=strtolower((string)parse_url($base,PHP_URL_HOST));
        if (!in_array($host,array_map('strtolower',$this->settings->allowedHosts),true)) throw new \RuntimeException('ERP host is not approved.');
        $addresses=$this->connectionAddresses($host);if (!$addresses) throw new \RuntimeException('ERP agency DNS is unavailable.');foreach ($addresses as $address) if (!self::isPublicAddress($address)) throw new \RuntimeException('Unsafe ERP destination blocked.');
        try {$body=$this->performCatalogRequest($base.$path.'?'.http_build_query($query),$host,$addresses[0],$this->authenticationHeaders($source));}
        catch (\Throwable $e) {throw new \RuntimeException('Agency API unavailable. Upload the agency API package and check this source’s connection/credentials.',0,$e);}
        try {$data=json_decode($body,true,64,JSON_THROW_ON_ERROR);}catch (\Throwable $e) {throw new \RuntimeException('ERP agency response is not valid JSON.');}
        if (!is_array($data)) throw new \RuntimeException('ERP agency response is invalid.');return $data;
    }
}
