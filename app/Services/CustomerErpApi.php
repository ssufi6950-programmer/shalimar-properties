<?php
namespace App\Services;
use App\Libraries\CustomerIdentity;
class CustomerErpApi extends ErpConnectionService implements CustomerIdentityProvider
{
 private \CodeIgniter\Database\BaseConnection $db;
 private array $identityDiagnostics=[];
 private ?int $lastIdentityHttpStatus=null;
 public function __construct(private \Config\CustomerPortal $customerSettings,?\CodeIgniter\Database\BaseConnection $db=null){$this->db=$db??db_connect();parent::__construct(new \App\Models\ErpSourceModel($this->db),config('ErpConnections'));}
 public function identities(string $cnic,string $mobile):array
 {
  $matches=[];$unavailable=[];$this->identityDiagnostics=[];$sources=$this->db->table('erp_sources')->where('is_active',1)->get()->getResultArray();
  foreach($sources as$source){$this->lastIdentityHttpStatus=null;try{
   $path=$this->customerSettings->identityPaths[$source['code']]??null;
   if(!is_string($path)||$path==='')throw new \RuntimeException('identity_endpoint_missing');
   $response=$this->request($source,$path,['cnic'=>$cnic,'mobile'=>$mobile],'identity');
   if(($response['api_version']??null)!=='v1'||!is_bool($response['matched']??null)||!is_array($response['customers']??null)||!array_is_list($response['customers'])||count($response['customers'])>10)throw new \RuntimeException('identity_contract_invalid');
   if(!$response['matched']){if($response['customers']!==[])throw new \RuntimeException('identity_contract_invalid');$this->identityDiagnostics[]=['source_code'=>(string)$source['code'],'source_name'=>(string)$source['name'],'http_status'=>200,'result'=>'no_match','message'=>'The identity endpoint responded successfully; no matching customer was found.'];continue;}
   if(!$response['customers'])throw new \RuntimeException('identity_contract_invalid');
   $sourceMatches=[];
   foreach($response['customers']as$identity){$id=$identity['customer_id']??null;if((!is_string($id)&&!is_int($id))||!preg_match('/\A[1-9][0-9]{0,17}\z/D',(string)$id)||!is_string($identity['registered_mobile']??null)||CustomerIdentity::mobile($identity['registered_mobile'])!==$mobile||!is_string($identity['display_name']??null)||mb_strlen($identity['display_name'])>120)throw new \RuntimeException('identity_contract_invalid');$sourceMatches[]=['erp_source_id'=>(int)$source['id'],'erp_customer_id'=>(string)$id,'mobile'=>$mobile,'name'=>$identity['display_name'],'source_name'=>$source['name'],'source_code'=>$source['code']];}
   $matches=array_merge($matches,$sourceMatches);$this->identityDiagnostics[]=['source_code'=>(string)$source['code'],'source_name'=>(string)$source['name'],'http_status'=>200,'result'=>'matched','message'=>'The identity endpoint responded successfully and returned a valid match.'];
  }catch(\Throwable $e){$safe=$this->safeIdentityDiagnostic($e);log_message('error','Customer ERP lookup failed: operation=identity source={source_code} http_status={http_status} reason_code={reason_code} exception_type={exception_type}.',['source_code'=>(string)$source['code'],'http_status'=>(string)($this->lastIdentityHttpStatus??$safe['http_status']??0),'reason_code'=>$this->identityFailureCode($e),'exception_type'=>get_class($e)]);$unavailable[]=['erp_source_id'=>(int)$source['id'],'source_name'=>$source['name'],'source_code'=>$source['code'],'message'=>'This ERP source could not be verified.'];$this->identityDiagnostics[]=['source_code'=>(string)$source['code'],'source_name'=>(string)$source['name'],'http_status'=>$this->lastIdentityHttpStatus??$safe['http_status'],'result'=>'error','message'=>$safe['message']];}}
  if(!$sources){$unavailable[]=['erp_source_id'=>0,'source_name'=>'ERP service','source_code'=>'','message'=>'No active ERP source is configured.'];$this->identityDiagnostics[]=['source_code'=>'','source_name'=>'ERP service','http_status'=>null,'result'=>'unavailable','message'=>'No active ERP source is configured.'];}
  return ['matches'=>$matches,'unavailable'=>$unavailable];
 }
 /** Admin diagnostic: reports only bounded status/result metadata; never returns raw ERP payloads or entered identifiers. */
 public function diagnoseIdentities(string $cnic,string $mobile):array
 {
  $result=$this->identities($cnic,$mobile);$bySource=[];foreach($result['matches'] as$match)$bySource[(string)$match['source_code']][]=$match;
  foreach($this->identityDiagnostics as&$diagnostic){$source=(string)$diagnostic['source_code'];$diagnostic['match_count']=count($bySource[$source]??[]);$diagnostic['matches']=array_map(static fn(array $match):array=>['customer_id'=>$match['erp_customer_id'],'display_name'=>$match['name']],$bySource[$source]??[]);}unset($diagnostic);
  return $this->identityDiagnostics;
 }
 private function safeIdentityDiagnostic(\Throwable $error):array
 {
  $message=$error->getMessage();if(preg_match('/\bHTTP status (\d{3})\b/i',$message,$match))return ['http_status'=>(int)$match[1],'message'=>'The ERP identity endpoint returned HTTP '.(int)$match[1].'.'];
  $known=['identity_endpoint_missing'=>'Identity endpoint is not configured for this source.','identity_contract_invalid'=>'The ERP response did not match the expected identity format.','ERP host not approved.'=>'Configured ERP host is not approved.','ERP DNS unavailable.'=>'ERP hostname could not be resolved.','ERP URL is missing.'=>'ERP source URL is not configured.','ERP customer API requires bearer credentials.'=>'This source is not configured for bearer authentication.','ERP transport failed.'=>'The ERP identity endpoint could not be reached securely or timed out.','Invalid ERP response.'=>'ERP returned invalid JSON.'];
  if(isset($known[$message]))return ['http_status'=>null,'message'=>$known[$message]];
  if(str_contains($message,'credentials are not available'))return ['http_status'=>null,'message'=>'The configured ERP credential is missing on this server.'];
  return ['http_status'=>null,'message'=>'The identity check could not be completed; inspect the server error log for its safe source-level reason.'];
 }
 private function identityFailureCode(\Throwable $error):string
 {
  $message=$error->getMessage();if(preg_match('/\bHTTP status (\d{3})\b/i',$message,$match))return 'http_'.$match[1];
  return match($message){'identity_endpoint_missing'=>'identity_endpoint_missing','identity_contract_invalid'=>'identity_contract_invalid','ERP host not approved.'=>'host_not_approved','ERP DNS unavailable.'=>'dns_unavailable','ERP URL is missing.'=>'base_url_missing','ERP customer API requires bearer credentials.'=>'bearer_auth_required','ERP transport failed.'=>'transport_failed','Invalid ERP response.'=>'invalid_json',default=>str_contains($message,'credentials are not available')?'credential_missing':'identity_lookup_failed'};
 }
 public function owned(array $identity,int $page=1):array
 {
  if($page<1||$page>100)throw new \InvalidArgumentException('Invalid property page.');
  $source=$this->db->table('erp_sources')->where('id',$identity['erp_source_id'])->where('is_active',1)->get()->getRowArray();if(!$source)throw new \RuntimeException('ERP source unavailable.');
  $path=$this->customerSettings->propertiesPaths[$source['code']]??null;if(!is_string($path)||$path==='')throw new \RuntimeException('Current ownership endpoint is not configured.');
  $response=$this->request($source,$path,['customer_id'=>(string)$identity['erp_customer_id'],'verified_mobile'=>$identity['mobile'],'page'=>$page,'page_size'=>50],'ownership');
  if(($response['api_version']??null)!=='v1'||(string)($response['customer_id']??'')!==(string)$identity['erp_customer_id']||($response['page']??null)!==$page||!is_array($response['properties']??null)||!array_is_list($response['properties'])||count($response['properties'])>50||!is_bool($response['has_more']??null)||($response['has_more']&&(!$response['properties']||$page>=100))){log_message('error','Customer ERP lookup failed: operation=ownership source={source_code} http_status=200 reason_code=ownership_contract_invalid.',['source_code'=>(string)$source['code']]);throw new \RuntimeException('Invalid ownership API response.');}
  return ['properties'=>$response['properties'],'has_more'=>$response['has_more']];
 }
 protected function request(array $source,string $path,array $payload,string $operation='unknown'):array
 {
  if(($source['auth_type']??'')!=='bearer')throw new \RuntimeException('ERP customer API requires bearer credentials.');
  if(!preg_match('~\A/(?!/)[A-Za-z0-9_./\~-]+\z~D',$path)||preg_match('~(?:^|/)\.\.(?:/|$)~',$path))throw new \RuntimeException('Invalid approved customer endpoint path.');
  $base=self::normaliseBaseUrl($source['base_url']??null);if(!$base)throw new \RuntimeException('ERP URL is missing.');$host=(string)parse_url($base,PHP_URL_HOST);if(!in_array(strtolower($host),array_map('strtolower',$this->settings->allowedHosts),true))throw new \RuntimeException('ERP host not approved.');
  $ips=$this->connectionAddresses($host);if(!$ips)throw new \RuntimeException('ERP DNS unavailable.');foreach($ips as$ip)if(!self::isPublicAddress($ip))throw new \RuntimeException('Unsafe ERP destination.');
  $headers=$this->authenticationHeaders($source);$headers[]='Content-Type: application/json';$ch=curl_init($base.$path);$body='';$ip=str_contains($ips[0],':')?'['.$ips[0].']':$ips[0];
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>$headers,CURLOPT_PROXY=>'',CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_RESOLVE=>[$host.':443:'.$ip],CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>15,CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>2097152)return 0;$body.=$chunk;return strlen($chunk);}]);
  try{$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$this->lastIdentityHttpStatus=$status?:null;if($ok===false){log_message('error','Customer ERP transport failed: operation={operation} source={source_code} curl_errno={curl_errno} http_status={http_status}.',['operation'=>$operation,'source_code'=>(string)$source['code'],'curl_errno'=>(string)curl_errno($ch),'http_status'=>(string)$status]);throw new \RuntimeException('ERP transport failed.');}if($status!==200){$apiCode='none';$errorData=json_decode($body,true);$candidate=is_array($errorData)?($errorData['error']['code']??$errorData['code']??null):null;if(is_string($candidate)&&preg_match('/\A[a-zA-Z0-9_.-]{1,64}\z/D',$candidate))$apiCode=$candidate;log_message('error','Customer ERP returned non-200: operation={operation} source={source_code} http_status={http_status} api_error_code={api_error_code}.',['operation'=>$operation,'source_code'=>(string)$source['code'],'http_status'=>(string)$status,'api_error_code'=>$apiCode]);throw new \RuntimeException('ERP HTTP status '.$status.'.');}try{$data=json_decode($body,true,64,JSON_THROW_ON_ERROR);}catch(\JsonException $error){log_message('error','Customer ERP returned invalid JSON: operation={operation} source={source_code} http_status={http_status}.',['operation'=>$operation,'source_code'=>(string)$source['code'],'http_status'=>(string)$status]);throw new \RuntimeException('Invalid ERP response.');}if(!is_array($data)){log_message('error','Customer ERP returned invalid JSON: operation={operation} source={source_code} http_status={http_status}.',['operation'=>$operation,'source_code'=>(string)$source['code'],'http_status'=>(string)$status]);throw new \RuntimeException('Invalid ERP response.');}return $data;}finally{curl_close($ch);}
 }
}
