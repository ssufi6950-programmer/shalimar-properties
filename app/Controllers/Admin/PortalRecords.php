<?php
namespace App\Controllers\Admin;
use App\Services\AdminPortalData;
use CodeIgniter\Exceptions\PageNotFoundException;

final class PortalRecords extends \App\Controllers\BaseController
{
 protected $helpers=['url','form','admin','admin_records'];
 private function data():AdminPortalData{return new AdminPortalData(db_connect());}
 private function query():array{$input=$this->request->getGet();return is_array($input)?$input:[];}
 private function error(\Throwable $e,string $path)
 {
  $invalid=$e instanceof \InvalidArgumentException;if(!$invalid)log_message('error','Admin portal records unavailable ({type}).',['type'=>get_class($e)]);
  return $this->response->setStatusCode($invalid?422:503)->setBody(view('admin/records/error',['pageTitle'=>'Records unavailable','tab'=>'','message'=>$invalid?$e->getMessage():'Records are temporarily unavailable. Please try again.','returnUrl'=>site_url($path)]));
 }
 private function collection(string $kind,string $permission,string $title)
 {
  if(!can($permission))return admin_denied();
  try{$data=$this->data();$filters=AdminPortalData::filters($this->query());$method=$kind==='resale'?'listings':$kind;$page=$data->$method($filters);
   return view('admin/records/index',$page+['pageTitle'=>$title,'tab'=>$kind,'kind'=>$kind,'baseUrl'=>site_url('admin/'.$kind),'options'=>$data->options(),'retainedLegacy'=>$kind==='resale'?$data->retainedLegacyCount():0]);
  }catch(\Throwable $e){return $this->error($e,'admin/'.$kind);}
 }
 public function customers(){return $this->collection('customers','customers.view','Customer Directory');}
 public function properties(){return $this->collection('properties','properties.view','Properties Management');}
 public function listings(){return $this->collection('resale','resale_listings.view','Resale Listings');}
 public function customer(int $id)
 {
  if(!can('customers.view'))return admin_denied();$data=$this->data();$row=$data->customer($id);if(!$row)throw PageNotFoundException::forPageNotFound();
  $related=can('resale_listings.view')?$data->listings(AdminPortalData::filters(['customer'=>(string)$id,'per_page'=>'10'])):null;
  return view('admin/records/details',['pageTitle'=>$row['display_name'],'tab'=>'customers','kind'=>'customers','record'=>$row,'related'=>$related,'options'=>$data->options()]);
 }
 public function property(int $id)
 {
  if(!can('properties.view'))return admin_denied();$data=$this->data();$row=$data->property($id);if(!$row)throw PageNotFoundException::forPageNotFound();
  $related=can('resale_listings.view')?$data->listings(AdminPortalData::filters(['property'=>(string)$id,'per_page'=>'10'])):null;
  return view('admin/records/details',['pageTitle'=>'Property #'.$id,'tab'=>'properties','kind'=>'properties','record'=>$row,'related'=>$related,'options'=>$data->options()]);
 }
 public function listing(int $id)
 {
  if(!can('resale_listings.view'))return admin_denied();$data=$this->data();$row=$data->listing($id);if(!$row)throw PageNotFoundException::forPageNotFound();
  return view('admin/records/details',['pageTitle'=>$row['reference'],'tab'=>'resale','kind'=>'resale','record'=>$row,'related'=>null,'options'=>$data->options()]);
 }
 public function dashboard(bool $analytics=false)
 {
  if(!can($analytics?'reports.view':'dashboard.view'))return admin_denied();
  try{$access=['customers'=>can('customers.view'),'properties'=>can('properties.view'),'resale'=>can('resale_listings.view')];return view('admin/records/dashboard',$this->data()->dashboard($access)+['pageTitle'=>$analytics?'Portfolio Analytics':'Dashboard Overview','tab'=>$analytics?'analytics':'dashboard','access'=>$access]);}
  catch(\Throwable $e){return $this->error($e,'admin/dashboard');}
 }
 public function unpublish(int $id)
 {
  if(!can('resale_listings.deactivate'))return admin_denied();
  try{$reason=$this->request->getPost('reason')??'';if(!is_string($reason))throw new \InvalidArgumentException('Enter a valid reason.');$this->data()->unpublish($id,(int)admin_user()['id'],$reason);session()->setFlashdata('record_message','Listing unpublished. Its ownership, assignments and history have been preserved.');}
  catch(\InvalidArgumentException $e){session()->setFlashdata('record_error',$e->getMessage());}
  catch(\Throwable $e){log_message('error','Admin unpublish failed ({type}).',['type'=>get_class($e)]);session()->setFlashdata('record_error','The listing could not be changed. Previous data has been preserved.');}
  return redirect()->to(site_url('admin/resale/'.$id));
 }
}
