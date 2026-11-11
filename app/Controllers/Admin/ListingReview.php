<?php
namespace App\Controllers\Admin;
use App\Services\AdminListingReview;
use App\Services\AdminListingReviewReadModel;
use App\Exceptions\CustomerFlowException;

final class ListingReview extends \App\Controllers\BaseController
{
    protected $helpers=['url','form','admin','admin_records','customer'];
    private function service():AdminListingReview{return new AdminListingReview(db_connect());}
    private function json(array $body,int $status=200){return$this->response->setStatusCode($status)->setHeader('Cache-Control','no-store, private')->setJSON($body+['csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);}
    private function action(string $permission,callable $fn)
    {
        if(!can('resale_listings.view')||!can($permission))return admin_denied();
        try{return$fn();}catch(CustomerFlowException$e){return$this->json(['message'=>$e->getMessage(),'code'=>$e->reason],$e->httpStatus);}catch(\InvalidArgumentException$e){return$this->json(['message'=>$e->getMessage()],422);}catch(\Throwable$e){log_message('error','Admin listing review unavailable ({type}) at {origin}:{line}.',['type'=>get_class($e),'origin'=>basename($e->getFile()),'line'=>$e->getLine()]);return$this->json(['message'=>'The audited review could not be completed. Nothing was published; reload and try again.'],503);}
    }
    private function input():array
    {
        if(str_contains($this->request->getHeaderLine('Content-Type'),'application/json')){try{$data=$this->request->getJSON(true);}catch(\Throwable$e){throw new \InvalidArgumentException('Send valid review data.');}}else$data=$this->request->getPost();if(!is_array($data))throw new \InvalidArgumentException('Send valid review data.');return$data;
    }
    private function version(array $data):int{$version=$data['version']??null;if((!is_int($version)&&!is_string($version))||!preg_match('/\A[1-9][0-9]{0,9}\z/D',(string)$version))throw new \InvalidArgumentException('Reload the current review version.');return(int)$version;}
    private function fingerprint(array $data):string{$value=$data['fingerprint']??null;if(!is_string($value)||!preg_match('/\A[a-f0-9]{64}\z/D',$value))throw new \InvalidArgumentException('Reload the reviewed listing and photos.');return$value;}
    public function index()
    {
        if(!can('resale_listings.view'))return admin_denied();try{return view('admin/listing_review/index',(new AdminListingReviewReadModel(db_connect()))->queue($this->request->getGet())+['pageTitle'=>'Property Listings','tab'=>'resale']);}catch(\InvalidArgumentException$e){return$this->json(['message'=>$e->getMessage()],422);}
    }
    public function show(int $id)
    {
        if(!can('resale_listings.view'))return admin_denied();try{return view('admin/listing_review/detail',(new AdminListingReviewReadModel(db_connect()))->detail($id,can('customers.view'))+['pageTitle'=>'Review Listing SP-'.$id,'tab'=>'resale']);}catch(CustomerFlowException$e){throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();}
    }
    public function verify(int $id){return$this->action('resale_listings.approve',function()use($id){$data=$this->input();$proof=$this->service()->verify($id,(int)admin_user()['id'],$this->version($data),$this->fingerprint($data));return$this->json(['message'=>'Current ERP ownership and eligibility verified. Approve & Publish will check again.','verification'=>$proof]);});}
    public function decision(int $id,string $action)
    {
        return$this->action($action==='approve'?'resale_listings.approve':'resale_listings.reject',function()use($id,$action){$data=$this->input();if(($data['confirmed']??null)!==true)throw new \InvalidArgumentException('Confirm the exact review decision.');$reason=$data['reason']??'';if(!is_string($reason))throw new \InvalidArgumentException('Enter a valid reason.');$result=$this->service()->decide($id,(int)admin_user()['id'],$action,$this->version($data),$this->fingerprint($data),$reason);return$this->json(['message'=>['approve'=>'Listing approved and published.','reject'=>'Listing rejected; the customer can see your reason.','changes'=>'Changes requested; fresh owner OTP is required for corrections.'][$action],'result'=>$result,'redirect'=>site_url('admin/listings/'.$id.'/review')]);});
    }
    public function upload(int $id){return$this->action('resale_listings.edit',function()use($id){$data=$this->input();$file=$this->request->getFile('photo');if(!$file)throw new \InvalidArgumentException('Choose one JPEG, PNG or WebP photo.');$this->service()->upload($id,(int)admin_user()['id'],$this->version($data),$this->fingerprint($data),$file);return$this->json(['message'=>'Photo added privately. Review the new version.','redirect'=>site_url('admin/listings/'.$id.'/review')],201);});}
    public function photoSet(int $id){return$this->action('resale_listings.edit',function()use($id){$data=$this->input();if(($data['confirmed']??null)!==true||!is_array($data['photos']??null))throw new \InvalidArgumentException('Confirm the final photo set.');$this->service()->photoSet($id,(int)admin_user()['id'],$this->version($data),$this->fingerprint($data),$data['photos']);return$this->json(['message'=>'Photo set updated; previous files and history retained.','redirect'=>site_url('admin/listings/'.$id.'/review')]);});}
    public function photo(int $id,string $photo)
    {
        if(!can('resale_listings.view'))return admin_denied();try{$row=$this->service()->row($id);$file=(new \App\Services\AdminListingPhotos(db_connect()))->file($row,$photo);return$this->response->setHeader('Cache-Control','no-store, private')->setHeader('X-Content-Type-Options','nosniff')->setContentType($file['mime'])->setBody(file_get_contents($file['path']));}catch(CustomerFlowException$e){return$this->response->setStatusCode(404)->setBody('Image unavailable.');}
    }
}
