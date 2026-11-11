<?php
namespace App\Services;
class JazzCustomerSms implements CustomerSmsGateway
{
 public function __construct(private \Config\Sms $settings){}
 public function sendCode(string $verifiedMobile,string $otp):void
 {
  if(!$this->settings->enabled||$this->settings->gatewayKey==='')throw new \RuntimeException('SMS delivery is not configured.');
  $mobile=$this->deliveryRecipient($verifiedMobile);if(!preg_match('/\A[0-9]{6}\z/D',$otp))throw new \InvalidArgumentException('Invalid OTP format.');
  $url=parse_url($this->settings->gatewayURL);if(!$url||($url['scheme']??'')!=='https'||!in_array(strtolower($url['host']??''),['jaageer.com','www.jaageer.com'],true)||($url['path']??'')!=='/jazz-sms-api.php'||(isset($url['port'])&&$url['port']!==443)||isset($url['query'])||isset($url['user'])||isset($url['fragment']))throw new \RuntimeException('Invalid existing SMS gateway configuration.');
  // The existing provider redirects its apex domain to www. Use the known
  // canonical endpoint directly, including for older private configuration.
  $gateway='https://www.jaageer.com/jazz-sms-api.php';
  $phone=$this->settings->phoneFormat==='national'?'0'.substr($mobile,2):$mobile;
  $message='Your Shalimar Properties login verification code is '.$otp.'. This code expires in 5 minutes. Do not share it.';
  $query=http_build_query(['key'=>$this->settings->gatewayKey,'phone'=>$phone,'msg'=>str_replace(' ','<<>>',$message)],'', '&',PHP_QUERY_RFC3986);
  if(trim($this->performGatewayRequest($gateway.'?'.$query))!=='Message Sent Successfully!'){log_message('error','SMS gateway rejected the delivery request. Response body omitted.');throw new \RuntimeException('SMS delivery failed.');}
 }
 private function deliveryRecipient(string $verifiedMobile):string
 {
  $mobile=\App\Libraries\CustomerIdentity::mobile($verifiedMobile);
  if(!$this->settings->testMode)return $mobile;
  $host=strtolower((string)parse_url(config('App')->baseURL,PHP_URL_HOST));
  $local=in_array($host,['localhost','127.0.0.1','[::1]','::1'],true);
  $approvedHosts=[];foreach($this->settings->testAllowedHosts as$approved)if(is_string($approved)&&filter_var($approved,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)&&!filter_var($approved,FILTER_VALIDATE_IP))$approvedHosts[]=strtolower($approved);
  $liveTest=parse_url(config('App')->baseURL,PHP_URL_SCHEME)==='https'&&in_array($host,$approvedHosts,true);
  if(!$local&&!$liveTest)throw new \RuntimeException('SMS test routing requires local testing or an explicitly approved HTTPS test host.');
  $remote=$_SERVER['REMOTE_ADDR']??null;
  if($local&&$remote!==null&&!$this->allowedTestPeer($remote))throw new \RuntimeException('SMS test routing requires a configured local test network.');
  if($this->settings->testRecipient==='')throw new \RuntimeException('The SMS test recipient is not configured.');
  // No fallback to the real customer if this override is malformed.
  return \App\Libraries\CustomerIdentity::mobile($this->settings->testRecipient);
 }
 private function allowedTestPeer(string $remote):bool
 {
  if(in_array($remote,['127.0.0.1','::1','::ffff:127.0.0.1'],true))return true;
  if(!self::privateV4($remote))return false;
  foreach($this->settings->testAllowedNetworks as$cidr){
   if(!is_string($cidr)||!preg_match('/\A([0-9.]+)\/([0-9]{1,2})\z/D',$cidr,$parts)||!self::privateV4($parts[1]))continue;
   $bits=(int)$parts[2];$minimum=str_starts_with($parts[1],'10.')?8:(str_starts_with($parts[1],'172.')?12:16);
   if($bits<$minimum||$bits>32)continue;
   $mask=(0xffffffff<<(32-$bits))&0xffffffff;$network=ip2long($parts[1]);
   if(($network&$mask)!==$network)continue;
   if((ip2long($remote)&$mask)===$network)return true;
  }
  return false;
 }
 private static function privateV4(string $ip):bool
 {
  if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))return false;
  $value=ip2long($ip);
  return ($value&0xff000000)===0x0a000000||($value&0xfff00000)===0xac100000||($value&0xffff0000)===0xc0a80000;
 }
 protected function performGatewayRequest(string $url):string
 {
  $ch=curl_init($url);$body='';curl_setopt_array($ch,[CURLOPT_HTTPGET=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>12,CURLOPT_WRITEFUNCTION=>static function($curl,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>4096)return 0;$body.=$chunk;return strlen($chunk);}]);
  try{$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);if($ok===false){log_message('error','SMS gateway transport failed: curl_errno={curl_errno} http_status={http_status} dns_ms={dns_ms} connect_ms={connect_ms} tls_ms={tls_ms} total_ms={total_ms}.',['curl_errno'=>(string)curl_errno($ch),'http_status'=>(string)$status,'dns_ms'=>(string)round(curl_getinfo($ch,CURLINFO_NAMELOOKUP_TIME)*1000),'connect_ms'=>(string)round(curl_getinfo($ch,CURLINFO_CONNECT_TIME)*1000),'tls_ms'=>(string)round(curl_getinfo($ch,CURLINFO_APPCONNECT_TIME)*1000),'total_ms'=>(string)round(curl_getinfo($ch,CURLINFO_TOTAL_TIME)*1000)]);throw new \RuntimeException('SMS delivery failed.');}if($status!==200){log_message('error','SMS gateway returned non-200: http_status={http_status}.',['http_status'=>(string)$status]);throw new \RuntimeException('SMS delivery failed.');}return $body;}finally{curl_close($ch);}
 }
}
