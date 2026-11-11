<?php
namespace App\Controllers\Customer;
abstract class PortalController extends \App\Controllers\BaseController
{
 protected $helpers=['url','form','customer'];
 protected function renderPortal(string $view,array $data=[]):string
 {
  $this->response->setHeader('X-SMP-Portal-Diagnostic','viewcheck-20261111');
  $bufferLevel=ob_get_level();
  try{
  $user=service('customerSession')->user();$customer=['name'=>'Property owner','initials'=>'SP','masked_mobile'=>'','id'=>null];$sources=[];
  if($user){$secret=new \App\Libraries\CustomerSecrets(config('CustomerPortal')->identityKey);$mobile=$secret->decrypt($user['verified_mobile'])['mobile'];$parts=preg_split('/\s+/',trim($user['display_name']));$customer=['name'=>$user['display_name'],'initials'=>mb_strtoupper(mb_substr($parts[0]??'P',0,1).mb_substr($parts[1]??'',0,1)),'masked_mobile'=>\App\Libraries\CustomerIdentity::mask($mobile),'id'=>(int)$user['id']];foreach(service('customerOtp')->links((int)$user['id'])as$link)$sources[]=['id'=>$link['id'],'source_id'=>$link['erp_source_id'],'name'=>$link['source_name'],'code'=>$link['source_code']];}
  return view('customer/'.$view,customer_portal_view_data(array_merge(['customer'=>$customer,'sources'=>$sources,'properties'=>[],'listings'=>[],'agents'=>[],'agencies'=>[],'activity'=>[],'pageTitle'=>'Your property, your possibilities','activeNav'=>'','authPage'=>false,'liveMode'=>true,'sourceWarnings'=>session()->get('customer_source_warnings')??[]],$data)));
  }catch(\CodeIgniter\Exceptions\PageNotFoundException $error){
   while(ob_get_level()>$bufferLevel)ob_end_clean();
   throw $error;
  }catch(\Throwable $error){
   // A production-safe support code is useful even when hosting logs are unavailable.
   while(ob_get_level()>$bufferLevel)ob_end_clean();
   $code='PORTAL_COMPONENT_ERROR';
   if($error instanceof \CodeIgniter\View\Exceptions\ViewException)$code='PORTAL_VIEW_UNAVAILABLE';
   elseif($error instanceof \CodeIgniter\Database\Exceptions\DatabaseException)$code='PORTAL_DATA_UNAVAILABLE';
   elseif($error instanceof \ErrorException&&str_contains($error->getMessage(),'filemtime('))$code='PORTAL_ASSET_METADATA';
   elseif($error instanceof \RuntimeException&&$error->getMessage()==='The public website URL is invalid.')$code='PORTAL_URL_CONFIGURATION';
   $detail=[];$detailHtml='';
   if($error instanceof \CodeIgniter\View\Exceptions\ViewException){
    $detail=$this->viewFailureDetail($error);
    $this->response->setHeader('X-SMP-Portal-View-Reason',$detail['reason']);
    $detailHtml='<br>View check: '.$detail['reason'].'<br>View root: '.$detail['root'].'<br>Templates: '.implode('; ',array_map(static fn($name,$status)=>$name.'='.$status,array_keys($detail['files']),$detail['files']));
   }
   $reference=strtoupper(bin2hex(random_bytes(5)));
   try{log_message('error','Customer portal render unavailable: {diagnostic}',['diagnostic'=>json_encode(['reference'=>$reference,'code'=>$code,'type'=>get_class($error),'origin'=>basename($error->getFile()),'line'=>$error->getLine(),'view'=>$detail],JSON_UNESCAPED_SLASHES)]);}catch(\Throwable $loggingError){}
   $this->response->setStatusCode(503)->setHeader('Cache-Control','no-store, private')->setHeader('Retry-After','60')->setHeader('X-Robots-Tag','noindex, nofollow')->setHeader('X-SMP-Portal-Error-Code',$code);
   return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Customer portal temporarily unavailable — Shalimar Properties</title><style>body{margin:0;padding:12vh 6vw;background:#f8f7f2;color:#133d32;font:16px/1.6 system-ui,sans-serif}main{max-width:550px;padding:32px;border:1px solid #e0e7df;border-radius:18px;background:white}h1{font-size:28px;line-height:1.3}small{color:#536d64}</style></head><body><main><h1>The customer portal is temporarily unavailable</h1><p>Please try again shortly.</p><small>Reference: '.$reference.'<br>Support code: '.$code.$detailHtml.'<br>Diagnostic: viewcheck-20261111</small></main></body></html>';
  }
 }
 /** Public diagnostics contain only fixed labels/statuses, never exception text or paths. */
 private function viewFailureDetail(\Throwable $error):array
 {
  $names=['customer/auth/login.php'=>'AUTH_LOGIN','customer/auth/register.php'=>'AUTH_REGISTER','customer/auth/identity_form.php'=>'AUTH_FORM','customer/auth/verify_otp.php'=>'AUTH_OTP','customer/layouts/auth.php'=>'AUTH_LAYOUT','customer/layouts/main.php'=>'MAIN_LAYOUT'];
  $detail=['reason'=>'VIEW_OTHER','root'=>'UNKNOWN','files'=>[]];
  foreach($error->getTrace()as$frame){
   $reasons=['forInvalidFile'=>'VIEW_FILE','forInvalidDecorator'=>'VIEW_DECORATOR','forInvalidCellClass'=>'VIEW_CELL','forInvalidCellMethod'=>'VIEW_CELL'];
   if(isset($reasons[$frame['function']??''])){$detail['reason']=$reasons[$frame['function']];break;}
  }
  foreach($names as$relative=>$label){
   $file=APPPATH.'Views/'.$relative;
   $detail['files'][$label]=@is_file($file)?(@is_readable($file)?'READY':'UNREADABLE'):'MISSING_OR_INACCESSIBLE';
   foreach([$relative,substr($relative,0,-4),$file]as$candidate){
    if($error->getMessage()===lang('Core.invalidFile',[$candidate]))$detail['reason']='VIEW_FILE_'.$label;
   }
  }
  try{
   $renderer=service('renderer');$reflection=new \ReflectionObject($renderer);
   if($reflection->hasProperty('viewPath')){
    $property=$reflection->getProperty('viewPath');$property->setAccessible(true);$value=$property->getValue($renderer);
    $actual=is_string($value)?@realpath($value):false;$canonical=@realpath(APPPATH.'Views');
    $detail['root']=$actual===false?'MISSING_OR_INACCESSIBLE':($canonical!==false&&$actual===$canonical?'STANDARD':'CUSTOM');
   }
  }catch(\Throwable $diagnosticError){}
  return $detail;
 }
 protected function input():array{$type=$this->request->getHeaderLine('Content-Type');if(str_contains($type,'application/json')){try{$value=$this->request->getJSON(true);return is_array($value)?$value:[];}catch(\Throwable $e){throw new \InvalidArgumentException('Send a valid request.');}}return $this->request->getPost();}
 protected function json(array $data,int $status=200){
  foreach(['message','source','local_test_otp_note']as$key)if(is_string($data[$key]??null))$data[$key]=customer_ui_text($data[$key]);
  if(is_string($data['source']['name']??null))$data['source']['name']=customer_ui_text($data['source']['name']);
  if(is_string($data['challenge']['local_test_otp_note']??null))$data['challenge']['local_test_otp_note']=customer_ui_text($data['challenge']['local_test_otp_note']);
  return $this->response->setStatusCode($status)->setHeader('Cache-Control','no-store, private')->setJSON($data+['csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);}
 protected function action(callable $callback)
 {
  try{return $callback();}catch(\App\Exceptions\CustomerFlowException $e){return $this->json(['message'=>$e->getMessage(),'code'=>$e->reason],$e->httpStatus);}catch(\InvalidArgumentException $e){return $this->json(['message'=>$e->getMessage()],422);}catch(\Throwable $e){log_message('error','Customer operation unavailable ({exception_type}) at {origin_file}:{origin_line}.',['exception_type'=>get_class($e),'origin_file'=>basename($e->getFile()),'origin_line'=>$e->getLine()]);return $this->json(['message'=>'This service is temporarily unavailable. Please try again later.'],503);}
 }
 protected function currentProperty(string $reference,string $purpose='sale'):array
 {
  $purpose=\App\Services\ListingPolicy::purpose($purpose);$pointer=service('customerSession')->resolve($reference);if(!$pointer)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
  $user=service('customerSession')->user();$links=service('customerOtp')->links((int)$user['id']);
  try{$property=service('customerPortfolio')->find($links,$pointer['source'],$pointer['customer'],$pointer['property'],null,$purpose);}catch(\Throwable $e){throw new \App\Exceptions\CustomerFlowException(503,'Current ownership could not be checked. Please try again shortly.','ownership_unavailable');}
  if(!$property)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();$property['id']=$reference;return $property;
 }
}
