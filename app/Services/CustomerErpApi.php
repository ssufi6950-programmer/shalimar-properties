<?php
namespace App\Services;
use App\Libraries\CustomerIdentity;
class CustomerErpApi extends ErpConnectionService implements CustomerIdentityProvider
{
 private \CodeIgniter\Database\BaseConnection $db;
 public function __construct(private \Config\CustomerPortal $customerSettings,?\CodeIgniter\Database\BaseConnection $db=null){$this->db=$db??db_connect();parent::__construct(new \App\Models\ErpSourceModel($this->db),config('ErpConnections'));}
 public function identities(string $cnic,string $mobile):array
 {
  $matches=[];$unavailable=[];$sources=$this->db->table('erp_sources')->where('is_active',1)->get()->getResultArray();
  foreach($sources as$source){try{
   $path=$this->customerSettings->identityPaths[$source['code']]??null;
   if(!is_string($path)||$path==='')throw new \RuntimeException('identity_endpoint_missing');
   $response=$this->request($source,$path,['cnic'=>$cnic,'mobile'=>$mobile]);
   if(($response['api_version']??null)!=='v1'||!is_bool($response['matched']??null)||!is_array($response['customers']??null)||!array_is_list($response['customers'])||count($response['customers'])>10)throw new \RuntimeException('identity_contract_invalid');
   if(!$response['matched']){if($response['customers']!==[])throw new \RuntimeException('identity_contract_invalid');continue;}
   if(!$response['customers'])throw new \RuntimeException('identity_contract_invalid');
   $sourceMatches=[];
   foreach($response['customers']as$identity){$id=$identity['customer_id']??null;if((!is_string($id)&&!is_int($id))||!preg_match('/\A[1-9][0-9]{0,17}\z/D',(string)$id)||!is_string($identity['registered_mobile']??null)||CustomerIdentity::mobile($identity['registered_mobile'])!==$mobile||!is_string($identity['display_name']??null)||mb_strlen($identity['display_name'])>120)throw new \RuntimeException('identity_contract_invalid');$sourceMatches[]=['erp_source_id'=>(int)$source['id'],'erp_customer_id'=>(string)$id,'mobile'=>$mobile,'name'=>$identity['display_name'],'source_name'=>$source['name'],'source_code'=>$source['code']];}
   $matches=array_merge($matches,$sourceMatches);
  }catch(\Throwable $e){log_message('error','Customer identity lookup failed: source={source_code} type={exception_type} reason={reason}.',['source_code'=>(string)$source['code'],'exception_type'=>get_class($e),'reason'=>substr(preg_replace('/[^A-Za-z0-9 _.-]/','',$e->getMessage())??'unknown',0,120)]);$unavailable[]=['erp_source_id'=>(int)$source['id'],'source_name'=>$source['name'],'source_code'=>$source['code'],'message'=>'This ERP source could not be verified.'];}}
  if(!$sources)$unavailable[]=['erp_source_id'=>0,'source_name'=>'ERP service','source_code'=>'','message'=>'No active ERP source is configured.'];
  return ['matches'=>$matches,'unavailable'=>$unavailable];
 }
 public function owned(array $identity,int $page=1):array
 {
  if($page<1||$page>100)throw new \InvalidArgumentException('Invalid property page.');
  $source=$this->db->table('erp_sources')->where('id',$identity['erp_source_id'])->where('is_active',1)->get()->getRowArray();if(!$source)throw new \RuntimeException('ERP source unavailable.');
  $path=$this->customerSettings->propertiesPaths[$source['code']]??null;if(!is_string($path)||$path==='')throw new \RuntimeException('Current ownership endpoint is not configured.');
  $response=$this->request($source,$path,['customer_id'=>(string)$identity['erp_customer_id'],'verified_mobile'=>$identity['mobile'],'page'=>$page,'page_size'=>50]);
  if(($response['api_version']??null)!=='v1'||(string)($response['customer_id']??'')!==(string)$identity['erp_customer_id']||($response['page']??null)!==$page||!is_array($response['properties']??null)||!array_is_list($response['properties'])||count($response['properties'])>50||!is_bool($response['has_more']??null)||($response['has_more']&&(!$response['properties']||$page>=100)))throw new \RuntimeException('Invalid ownership API response.');
  return ['properties'=>$response['properties'],'has_more'=>$response['has_more']];
 }
 protected function request(array $source,string $path,array $payload):array
 {
  if(($source['auth_type']??'')!=='bearer')throw new \RuntimeException('ERP customer API requires bearer credentials.');
  if(!preg_match('~\A/(?!/)[A-Za-z0-9_./\~-]+\z~D',$path)||preg_match('~(?:^|/)\.\.(?:/|$)~',$path))throw new \RuntimeException('Invalid approved customer endpoint path.');
  $base=self::normaliseBaseUrl($source['base_url']??null);if(!$base)throw new \RuntimeException('ERP URL is missing.');$host=(string)parse_url($base,PHP_URL_HOST);if(!in_array(strtolower($host),array_map('strtolower',$this->settings->allowedHosts),true))throw new \RuntimeException('ERP host not approved.');
  $ips=$this->connectionAddresses($host);if(!$ips)throw new \RuntimeException('ERP DNS unavailable.');foreach($ips as$ip)if(!self::isPublicAddress($ip))throw new \RuntimeException('Unsafe ERP destination.');
  $headers=$this->authenticationHeaders($source);$headers[]='Content-Type: application/json';$ch=curl_init($base.$path);$body='';$ip=str_contains($ips[0],':')?'['.$ips[0].']':$ips[0];
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>$headers,CURLOPT_PROXY=>'',CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_RESOLVE=>[$host.':443:'.$ip],CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>15,CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>2097152)return 0;$body.=$chunk;return strlen($chunk);}]);
  try{$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);if($ok===false){log_message('error','Customer ERP transport failed: source={source_code} curl_errno={curl_errno} http_status={http_status}.',['source_code'=>(string)$source['code'],'curl_errno'=>(string)curl_errno($ch),'http_status'=>(string)$status]);throw new \RuntimeException('ERP transport failed.');}if($status!==200){log_message('error','Customer ERP returned non-200: source={source_code} http_status={http_status}.',['source_code'=>(string)$source['code'],'http_status'=>(string)$status]);throw new \RuntimeException('ERP HTTP status '.$status.'.');}$data=json_decode($body,true,64,JSON_THROW_ON_ERROR);if(!is_array($data))throw new \RuntimeException('Invalid ERP response.');return $data;}finally{curl_close($ch);}
 }
}
