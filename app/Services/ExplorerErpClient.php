<?php
declare(strict_types=1);
namespace App\Services;

/** Uses ERP Sources authentication/host approval/DNS pinning; no ERP business writes. */
class ExplorerErpClient extends ErpConnectionService
{
    public function fetchOptions(array $source, string $head, string $field, array $subheads): array
    {
        if (!in_array($field, ['pro_inventory.sector', 'pro_inventory.category', 'item_sublocation.id'], true) || !preg_match('/\A[0-9]{1,18}\z/D', $head)
            || count($subheads) > 100 || ($field === 'item_sublocation.id' && !$subheads)) throw new \InvalidArgumentException('Invalid metadata scope.');
        foreach ($subheads as $id) if (!is_string($id) || !preg_match('/\A[0-9]{1,18}\z/D', $id)) throw new \InvalidArgumentException('Invalid mapped Sub Location.');
        if (($source['auth_type'] ?? '') !== 'bearer' || (int) ($source['is_active'] ?? 0) !== 1) throw new \App\Exceptions\ExplorerMetadataException('Enable an ERP source with server-side bearer credentials.');
        $base = self::normaliseBaseUrl($source['base_url'] ?? null);
        if (!$base) throw new \App\Exceptions\ExplorerMetadataException('Configure the ERP API base URL.');
        $host = strtolower((string) parse_url($base, PHP_URL_HOST));
        if (!in_array($host, array_map('strtolower', $this->settings->allowedHosts), true)) throw new \App\Exceptions\ExplorerMetadataException('ERP host is not approved.');
        $addresses = $this->connectionAddresses($host);
        if (!$addresses) throw new \App\Exceptions\ExplorerMetadataException('ERP hostname could not be resolved.');
        foreach ($addresses as $address) if (!self::isPublicAddress($address)) throw new \App\Exceptions\ExplorerMetadataException('Non-public ERP destination blocked.');
        $query = ['head' => $head, 'kind' => match ($field) { 'pro_inventory.sector' => 'sector', 'pro_inventory.category' => 'category', default => 'sublocation' }];
        if ($query['kind'] !== 'sector') $query['subheads'] = implode(',', $subheads);
        return $this->request($base . '/filter-options.php?' . http_build_query($query), $host, $addresses[0], $this->authenticationHeaders($source));
    }

    public function companyProperties(array $source, string $after, ?string $property=null): array
    {
        if (!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',$after)) throw new \InvalidArgumentException('Invalid inventory cursor.');
        if (($source['auth_type'] ?? '') !== 'bearer' || (int) ($source['is_active'] ?? 0) !== 1) throw new \App\Exceptions\ExplorerMetadataException('Enable an ERP source with server-side bearer credentials.');
        $base = self::normaliseBaseUrl($source['base_url'] ?? null);
        if (!$base) throw new \App\Exceptions\ExplorerMetadataException('Configure the ERP API base URL.');
        $host = strtolower((string) parse_url($base, PHP_URL_HOST));
        if (!in_array($host, array_map('strtolower', $this->settings->allowedHosts), true)) throw new \App\Exceptions\ExplorerMetadataException('ERP host is not approved.');
        $addresses = $this->connectionAddresses($host);
        if (!$addresses) throw new \App\Exceptions\ExplorerMetadataException('ERP hostname could not be resolved.');
        foreach ($addresses as $address) if (!self::isPublicAddress($address)) throw new \App\Exceptions\ExplorerMetadataException('Non-public ERP destination blocked.');
        if($property!==null&&!preg_match('/\A[1-9][0-9]{0,9}\z/D',$property))throw new \InvalidArgumentException('Invalid inventory identity.');
        return $this->request($base.'/company-properties.php?'.http_build_query(['after_id'=>$after]+($property===null?[]:['property_id'=>$property])),$host,$addresses[0],$this->authenticationHeaders($source));
    }

    protected function request(string $url, string $host, string $address, array $headers): array
    {
        return $this->send($url,$host,$address,$headers);
    }

    public function fetchTree(array $source, array $context): array
    {
        if (($source['auth_type']??'')!=='bearer'||(int)($source['is_active']??0)!==1||($context['source']??null)!==($source['code']??null)) throw new \App\Exceptions\ExplorerMetadataException('Enable the matching authenticated ERP source.');
        $base=self::normaliseBaseUrl($source['base_url']??null);
        if(!$base)throw new \App\Exceptions\ExplorerMetadataException('Configure the ERP API base URL.');
        $host=strtolower((string)parse_url($base,PHP_URL_HOST));
        if(!in_array($host,array_map('strtolower',$this->settings->allowedHosts),true))throw new \App\Exceptions\ExplorerMetadataException('ERP host is not approved.');
        $addresses=$this->connectionAddresses($host);
        if(!$addresses)throw new \App\Exceptions\ExplorerMetadataException('ERP hostname could not be resolved.');
        foreach($addresses as$address)if(!self::isPublicAddress($address))throw new \App\Exceptions\ExplorerMetadataException('Non-public ERP destination blocked.');
        $payload=json_encode($context,JSON_THROW_ON_ERROR);
        if(strlen($payload)>131072)throw new \InvalidArgumentException('This ancestor context exceeds the 128 KiB request budget; no path was truncated.');
        return $this->send($base.'/filter-tree.php',$host,$addresses[0],array_merge($this->authenticationHeaders($source),['Content-Type: application/json']),$payload);
    }

    private function send(string $url, string $host, string $address, array $headers, ?string $payload=null): array
    {
        $body = '';
        $handle = curl_init($url);
        $pinned = str_contains($address, ':') ? '[' . $address . ']' : $address;
        curl_setopt_array($handle, [CURLOPT_HTTPGET => true, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROXY => '',
            CURLOPT_RESOLVE => [$host . ':443:' . $pinned], CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 2097152) return 0;
                $body .= $chunk;
                return strlen($chunk);
            }]);
        if($payload!==null)curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload]);
        try {
            if (curl_exec($handle) === false) throw new \App\Exceptions\ExplorerMetadataException('ERP metadata request failed, timed out or exceeded 2 MiB.');
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($status === 404) throw new \App\Exceptions\ExplorerMetadataException('ERP endpoint is unavailable. Upload the matching SMP API endpoint files.');
            if($payload!==null&&$status!==200){
                $failure=json_decode($body,true);
                $messages=['schema_unavailable'=>'ERP inventory schema inspection is unavailable.','high_cardinality'=>'Selected field exceeds the 128-value category limit.','empty_branch'=>'No inventory property satisfies this complete ancestor branch.','field_not_allowed'=>'Selected field is excluded or no longer available.','unsupported_value'=>'A source value cannot be preserved within the existing schema.','unsafe_value'=>'This field contains unsuitable or potentially sensitive values.','metadata_budget'=>'ERP metadata inspection exceeded its operational time budget.'];
                $code=$failure['error']['code']??null;
                if(is_string($code)&&isset($messages[$code]))throw new \App\Exceptions\ExplorerMetadataException($messages[$code]);
            }
            if ($status !== 200) throw new \App\Exceptions\ExplorerMetadataException('ERP metadata returned HTTP ' . $status . '. Check source credentials/API configuration.');
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data)) throw new \App\Exceptions\ExplorerMetadataException('ERP metadata response is invalid.');
            return $data;
        } finally {
            curl_close($handle);
        }
    }
}
