<?php
namespace App\Controllers\Customer;
final class Properties extends PortalController
{
 public function index():string{return $this->renderPortal('properties/index',['pageTitle'=>'My Properties','activeNav'=>'properties']);}
 public function choose():string
 {
  $purpose=$this->request->getGet('purpose');
  if(!is_string($purpose)||!in_array($purpose,['sale','rent'],true))throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
  return $this->renderPortal('properties/choose',['pageTitle'=>'Choose a Property','activeNav'=>'list','listingPurpose'=>$purpose]);
 }
 public function data(int $linkId){return $this->action(function()use($linkId){$links=service('customerOtp')->links((int)service('customerSession')->user()['id']);$identity=null;foreach($links as$link)if($link['id']===$linkId)$identity=$link;if(!$identity)return $this->json(['message'=>'Source not found.'],404);$page=$this->request->getGet('page')??'1';if(!is_string($page)||!ctype_digit($page)||(int)$page<1||(int)$page>100)throw new \InvalidArgumentException('Invalid property page.');try{$result=service('customerPortfolio')->page($identity,(int)$page);}catch(\Throwable $e){return $this->json(['message'=>'Current properties from this ERP could not be loaded. Please try again.','source'=>$identity['source_name']],503);}$out=[];foreach($result['properties']as$property){$property['id']=service('customerSession')->reference($property);unset($property['erp_customer_id']);$out[]=$property;}return $this->json(['properties'=>$out,'has_more'=>$result['has_more'],'page'=>(int)$page,'source'=>['name'=>$identity['source_name'],'code'=>$identity['source_code']]]);});}
 public function details(string $reference){try{$property=$this->currentProperty($reference);return $this->renderPortal('properties/details',['pageTitle'=>$property['project'].' - '.$property['plot'],'activeNav'=>'properties','property'=>$property]);}catch(\App\Exceptions\CustomerFlowException $e){return $this->response->setStatusCode($e->httpStatus)->setBody($this->renderPortal('properties/unavailable',['pageTitle'=>'Ownership check unavailable','message'=>$e->getMessage()]));}}
}
