<?php

namespace App\Services;

use App\Models\ErpSourceModel;
use Config\ErpConnections;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Only this server-side service can perform approved ERP health checks. */
class ErpConnectionService
{
    public function __construct(protected ErpSourceModel $sources, protected ErpConnections $settings) {}

    public static function normaliseBaseUrl(?string $value): ?string
    {
        $value=trim($value ?? '');
        if ($value==='') return null;
        if (strlen($value)>2048 || preg_match('/[\x00-\x20\x7f\\\\]/',$value)) throw new InvalidArgumentException('Enter a valid HTTPS base URL without whitespace or backslashes.');
        $parts=parse_url($value);
        if (!$parts || ($parts['scheme']??'')!=='https' || isset($parts['user'],$parts['pass']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || (isset($parts['port'])&&$parts['port']!==443)) {
            throw new InvalidArgumentException('Use HTTPS on port 443, without embedded credentials, a query string or a fragment.');
        }
        $host=strtolower($parts['host']??'');
        if (!self::validHostname($host)) throw new InvalidArgumentException('Use a DNS hostname, not a local address or IP literal.');
        $path=$parts['path']??'';
        if (str_contains($path,'%') || preg_match('~(?:^|/)\.\.(?:/|$)~',$path)) throw new InvalidArgumentException('Encoded or parent-directory URL paths are not supported.');
        return 'https://'.$host.rtrim($path,'/');
    }

    protected static function validHostname(string $host): bool
    {
        return strlen($host)<=253 && str_contains($host,'.') && preg_match('/[a-z]/i',$host)
            && filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)!==false
            && filter_var($host,FILTER_VALIDATE_IP)===false
            && !preg_match('/(?:\.|^)(?:localhost|local|internal|invalid|test)$/i',$host)
            && !str_ends_with($host,'.');
    }

    /** Safe, non-secret metadata for the UI. No DNS or HTTP requests occur here. */
    public function configuration(array $source): array
    {
        try {
            $this->target($source);
            $this->authenticationHeaders($source);
        } catch (InvalidArgumentException $error) {
            return ['configured'=>false,'test_available'=>false,'test_unavailable_reason'=>$error->getMessage()];
        }
        return [
            'configured'=>true,'test_available'=>(bool)($source['is_active']??false),
            'test_unavailable_reason'=>empty($source['is_active'])?'Activate this ERP source before testing its connection.':null,
        ];
    }

    protected function target(array $source): array
    {
        $base=self::normaliseBaseUrl($source['base_url']??null);
        if (!$base) throw new InvalidArgumentException('No ERP base URL is configured.');
        $host=strtolower((string)parse_url($base,PHP_URL_HOST));
        $allowed=array_filter($this->settings->allowedHosts,static fn($value)=>is_string($value)&&self::validHostname(strtolower($value)));
        if (!in_array($host,array_map('strtolower',$allowed),true)) throw new InvalidArgumentException('The ERP host has not been approved in server configuration.');
        $path=$this->settings->healthPaths[$source['code']]??null;
        if (!is_string($path)||!preg_match('#\A/(?!/)[A-Za-z0-9_./~-]+\z#D',$path)||preg_match('~(?:^|/)\.\.(?:/|$)~',$path)) {
            throw new InvalidArgumentException('No valid, approved health endpoint is configured for this ERP source.');
        }
        return ['url'=>$base.$path,'host'=>$host];
    }

    /** Return header strings only inside the service. Neither values nor environment keys leave it. */
    protected function authenticationHeaders(array $source): array
    {
        $headers=['Accept: application/json','User-Agent: Shalimar-Portal-Health/1.0'];
        $type=$source['auth_type']??'none';
        if ($type==='none') return $headers;
        $reference=$this->settings->credentialRefs[$source['credential_ref']??'']??null;
        if (!is_array($reference)||($reference['type']??'')!==$type) throw new InvalidArgumentException('A matching server-side credential reference is not configured.');
        $secret=static function ($name): string {
            if (!is_string($name)||!preg_match('/\A[A-Z_][A-Z0-9_]{0,99}\z/D',$name)) throw new InvalidArgumentException('The credential environment reference is invalid.');
            $value=env($name);
            if (!is_string($value)||$value===''||strlen($value)>4096||preg_match('/[\r\n\x00]/',$value)) throw new InvalidArgumentException('Required ERP credentials are not available on the server.');
            return $value;
        };
        if ($type==='bearer') $headers[]='Authorization: Bearer '.$secret($reference['tokenEnv']??null);
        elseif ($type==='basic') $headers[]='Authorization: Basic '.base64_encode($secret($reference['usernameEnv']??null).':'.$secret($reference['passwordEnv']??null));
        elseif ($type==='api_key') {
            $header=$reference['header']??'';
            if (!is_string($header)||!preg_match('/\A[A-Za-z][A-Za-z0-9-]{0,63}\z/D',$header)||in_array(strtolower($header),['host','cookie','set-cookie','connection','content-length','transfer-encoding','proxy-authorization','proxy-connection'],true)) {
                throw new InvalidArgumentException('The configured API-key header is invalid.');
            }
            $headers[]=$header.': '.$secret($reference['tokenEnv']??null);
        } else throw new InvalidArgumentException('Unsupported ERP authentication type.');
        return $headers;
    }

    protected function resolveHost(string $host): array
    {
        $records=dns_get_record($host,DNS_A|DNS_AAAA);
        if (!is_array($records)) return [];
        $addresses=[];
        foreach ($records as $record) {
            if (isset($record['ip'])) $addresses[]=$record['ip'];
            if (isset($record['ipv6'])) $addresses[]=$record['ipv6'];
        }
        return array_values(array_unique($addresses));
    }

    /** Server-only public pins bypass split DNS without permitting private destinations. */
    protected function connectionAddresses(string $host): array
    {
        if (!array_key_exists($host,$this->settings->publicAddressOverrides)) return $this->resolveHost($host);
        $addresses=$this->settings->publicAddressOverrides[$host];
        if (!is_array($addresses) || !$addresses || !array_is_list($addresses)) {
            throw new RuntimeException('The ERP public address override is invalid. Check its server configuration.');
        }
        foreach ($addresses as $address) {
            if (!is_string($address) || !self::isPublicAddress($address)) {
                throw new RuntimeException('The ERP public address override must contain only public IP addresses.');
            }
        }
        return array_values(array_unique($addresses));
    }

    public static function isPublicAddress(string $address): bool
    {
        if (filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false) return false;
        $packed=inet_pton($address);
        if ($packed===false) return false;
        if (strlen($packed)===4) {
            $number=unpack('N',$packed)[1];
            foreach ([['0.0.0.0',8],['10.0.0.0',8],['100.64.0.0',10],['127.0.0.0',8],['169.254.0.0',16],['172.16.0.0',12],['192.0.0.0',24],['192.0.2.0',24],['192.168.0.0',16],['198.18.0.0',15],['198.51.100.0',24],['203.0.113.0',24],['224.0.0.0',4],['240.0.0.0',4]] as [$network,$bits]) {
                $mask=(0xffffffff << (32-$bits)) & 0xffffffff;
                if (($number&$mask)===(unpack('N',inet_pton($network))[1]&$mask)) return false;
            }
            return true;
        }
        // Only global IPv6 unicast. Block transition mechanisms and documentation ranges, too.
        $first=ord($packed[0]);
        if (($first&0xe0)!==0x20) return false;
        foreach ([['2001::',23],['2001:db8::',32],['2002::',16],['3fff::',20]] as [$network,$bits]) {
            $prefix=inet_pton($network);$bytes=intdiv($bits,8);$remaining=$bits%8;
            if (substr($packed,0,$bytes)===substr($prefix,0,$bytes) && (!$remaining||((ord($packed[$bytes])&(0xff<<(8-$remaining)))===(ord($prefix[$bytes])&(0xff<<(8-$remaining)))))) return false;
        }
        return true;
    }

    /** TLS verification, pinned public DNS, no proxy, no redirects, bounded response and timeouts. */
    protected function performRequest(string $url, string $host, string $address, array $headers): int
    {
        $handle=curl_init($url);
        if ($handle===false) throw new RuntimeException('The secure HTTP client could not start.');
        $bytes=0;
        $pinned=str_contains($address,':')?'['.$address.']':$address;
        curl_setopt_array($handle,[
            CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',
            CURLOPT_CONNECTTIMEOUT=>max(1,min(5,$this->settings->connectTimeout)),
            CURLOPT_TIMEOUT=>max(1,min(15,$this->settings->requestTimeout)),
            CURLOPT_RESOLVE=>[$host.':443:'.$pinned],CURLOPT_HEADER=>false,
            CURLOPT_WRITEFUNCTION=>static function ($curl,string $chunk) use (&$bytes): int {
                $bytes+=strlen($chunk);
                return $bytes>65536?0:strlen($chunk); // Discard response bodies; never send them to the browser.
            },
        ]);
        try {
            $success=curl_exec($handle);
            if ($success===false) {
                if (curl_errno($handle)===CURLE_OPERATION_TIMEDOUT) throw new RuntimeException('The ERP health endpoint timed out.');
                if ($bytes>65536) throw new RuntimeException('The health endpoint response exceeded the permitted size.');
                throw new RuntimeException('The ERP health endpoint could not be reached securely.');
            }
            return (int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
        } finally { curl_close($handle); }
    }

    /** Full snapshot only; uses the same host approval, TLS, DNS and credential rules as health. */
    public function catalogSnapshot(array $source): array
    {
        if (($source['auth_type']??'none')!=='bearer') throw new InvalidArgumentException('Catalog synchronization requires a server-side bearer credential reference.');
        $base=self::normaliseBaseUrl($source['base_url']??null);
        if (!$base) throw new InvalidArgumentException('Configure the ERP API base URL.');
        $host=strtolower((string)parse_url($base,PHP_URL_HOST));
        if (!in_array($host,array_map('strtolower',$this->settings->allowedHosts),true)) throw new InvalidArgumentException('ERP host is not approved.');
        $addresses=$this->connectionAddresses($host);
        if (!$addresses) throw new RuntimeException('ERP hostname could not be resolved.');
        foreach ($addresses as $address) if (!self::isPublicAddress($address)) throw new RuntimeException('Non-public ERP destination blocked.');
        $body=$this->performCatalogRequest($base.'/catalog.php',$host,$addresses[0],$this->authenticationHeaders($source));
        try { $snapshot=json_decode($body,true,128,JSON_THROW_ON_ERROR); }
        catch (\JsonException $error) { throw new RuntimeException('ERP returned invalid catalog JSON.'); }
        if (!is_array($snapshot)) throw new RuntimeException('ERP returned an invalid catalog.');
        return ErpCatalogSync::validate($snapshot);
    }

    protected function performCatalogRequest(string $url,string $host,string $address,array $headers): string
    {
        $handle=curl_init($url); $body='';
        if ($handle===false) throw new RuntimeException('Secure HTTP client unavailable.');
        $pinned=str_contains($address,':')?'['.$address.']':$address;
        curl_setopt_array($handle,[CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',
            CURLOPT_CONNECTTIMEOUT=>max(1,min(5,$this->settings->connectTimeout)),CURLOPT_TIMEOUT=>30,
            CURLOPT_RESOLVE=>[$host.':443:'.$pinned],CURLOPT_HEADER=>false,
            CURLOPT_WRITEFUNCTION=>static function ($curl,string $chunk) use (&$body): int {
                if (strlen($body)+strlen($chunk)>16*1024*1024) return 0;
                $body.=$chunk;return strlen($chunk);
            }]);
        try {
            if (curl_exec($handle)===false) throw new RuntimeException('Catalog request failed, timed out or exceeded 16 MiB.');
            $status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
            if ($status!==200) {
                $error=json_decode($body,true);
                $code=is_array($error)?($error['error']['code']??null):null;
                throw \App\Exceptions\ErpCatalogRequestException::response($status,is_string($code)?$code:null);
            }
            return $body;
        } finally { curl_close($handle); }
    }

    public function test(int $id): array
    {
        if(!is_cli()) {helper('admin');if(!can('erp_sources.test_connection'))return ['ok'=>false,'http_status'=>403,'message'=>'ERP connection testing permission is required.'];}
        $source=$this->sources->find($id);
        if (!$source) return ['ok'=>false,'http_status'=>404,'message'=>'ERP source not found.'];
        $configuration=$this->configuration($source);
        if (!$configuration['configured']) {
            return ['ok'=>false,'http_status'=>422,'message'=>'Not Configured: '.$configuration['test_unavailable_reason']];
        }
        if (empty($source['is_active'])) return ['ok'=>false,'http_status'=>422,'message'=>'Activate this ERP source before testing its connection.'];
        $status='failed';$message='The ERP health check failed.';
        try {
            $target=$this->target($source);
            $addresses=$this->connectionAddresses($target['host']);
            if (!$addresses) throw new RuntimeException('The approved ERP hostname could not be resolved.');
            foreach ($addresses as $address) if (!self::isPublicAddress($address)) throw new RuntimeException('The ERP health check was blocked because DNS resolved to a non-public address.');
            $http=$this->performRequest($target['url'],$target['host'],$addresses[0],$this->authenticationHeaders($source));
            if ($http>=200&&$http<300) {$status='connected';$message='The approved ERP health endpoint responded successfully.';}
            elseif ($http>=300&&$http<400) $message='The health endpoint returned a redirect. Redirects are not permitted.';
            else $message='The ERP health endpoint returned HTTP '.$http.'.';
        } catch (RuntimeException $error) { $message=$error->getMessage(); }
        catch (Throwable $error) { $message='The ERP health check could not be completed.'; }

        $db=$this->sources->databaseConnection();$db->transBegin();
        try {
            $current=$db->query('SELECT * FROM '.$db->escapeIdentifiers($db->prefixTable('erp_sources')).' WHERE id = ? FOR UPDATE',[$id])->getRowArray();
            foreach (['code','base_url','auth_type','credential_ref','is_active'] as $field) {
                if (!$current||$current[$field]!==$source[$field]) {
                    $db->transRollback();
                    return ['ok'=>false,'http_status'=>409,'message'=>'This ERP configuration changed during the check. Please test it again.'];
                }
            }
            $checkedAt=(new \DateTimeImmutable('now',new \DateTimeZone(config('App')->appTimezone)))->format('Y-m-d H:i:s');
            if (!$this->sources->update($id,['connection_status'=>$status,'last_checked_at'=>$checkedAt])) throw new RuntimeException('Connection result could not be saved.');
            if($db->tableExists('admin_audit_logs'))(new AdminAuditService($db))->record('erp_source.connection_tested','erp_sources',is_cli()?null:(int)service('adminAuth')->user()['id'],'erp_source',$id,null,['connection_status'=>$status,'last_checked_at'=>$checkedAt]);
            $db->transCommit();
        } catch (Throwable $error) {$db->transRollback();throw $error;}
        return ['ok'=>$status==='connected','http_status'=>$status==='connected'?200:502,'message'=>$message];
    }
}
