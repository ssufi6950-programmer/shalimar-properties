<?php
namespace App\Services;
final class CustomerSession
{
 private bool $loaded=false;private ?array $user=null;
 public function user():?array
 {
  if($this->loaded)return $this->user;$this->loaded=true;$s=session();$id=(int)$s->get('customer_id');if(!$id)return null;$row=(new \App\Models\PortalCustomerModel())->find($id);$cfg=config('CustomerPortal');
  if(!$row||$row['status']!=='active'||(int)$row['auth_version']!==(int)$s->get('customer_auth_version')||(int)$s->get('customer_authenticated_at')<=time()-$cfg->maximumLifetime||(int)$s->get('customer_last_activity')<=time()-$cfg->idleTimeout){$this->logout();return null;}
  $s->set('customer_last_activity',time());return $this->user=$row;
 }
 public function login(array $user):void{$this->clear();session()->regenerate(true);session()->set(['customer_id'=>(int)$user['id'],'customer_auth_version'=>(int)$user['auth_version'],'customer_authenticated_at'=>time(),'customer_last_activity'=>time()]);$this->loaded=false;$this->user=null;}
 private function clear():void{session()->remove(['customer_id','customer_auth_version','customer_authenticated_at','customer_last_activity','customer_property_refs','customer_prepared_resale','customer_challenge_id','customer_linking','customer_source_warnings','customer_resale_entry','local_otp_login','local_otp_resale']);}
 public function logout():void{$this->clear();session()->remove('customer_otp_binding');session()->regenerate(true);$this->loaded=true;$this->user=null;}
 public function binding():string{$binding=session()->get('customer_otp_binding');if(!is_string($binding)||strlen($binding)!==64){$binding=bin2hex(random_bytes(32));session()->set('customer_otp_binding',$binding);}return $binding;}
 public function reference(array $property):string
 {
  $refs=session()->get('customer_property_refs')??[];$now=time();foreach($refs as$key=>$row){if($row['expires']<$now){unset($refs[$key]);continue;}if($row['source']===$property['erp_source_id']&&$row['customer']===$property['erp_customer_id']&&$row['property']===$property['erp_property_id'])return $key;}
  if(count($refs)>=5000)array_shift($refs);$key=bin2hex(random_bytes(24));$refs[$key]=['source'=>$property['erp_source_id'],'customer'=>$property['erp_customer_id'],'property'=>$property['erp_property_id'],'expires'=>$now+600];session()->set('customer_property_refs',$refs);return $key;
 }
 public function resolve(string $reference):?array{$row=(session()->get('customer_property_refs')??[])[$reference]??null;return is_array($row)&&$row['expires']>=time()?$row:null;}
}
