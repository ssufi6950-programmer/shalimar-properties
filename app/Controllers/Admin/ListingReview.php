<?php
namespace App\Controllers\Admin;
use App\Services\AdminListingReview;
use App\Services\AdminListingReviewReadModel;
use App\Services\AdminListingDeletion;
use App\Services\ListingLifecycle;
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
    private function iniBytes(string $value):int
    {
        $value=trim($value);if($value===''||$value==='0')return 0;
        if(!preg_match('/\A([0-9]+(?:\.[0-9]+)?)\s*([KMG]?)B?\z/i',$value,$match))return 0;
        $power=match(strtoupper($match[2]??'')){'K'=>1,'M'=>2,'G'=>3,default=>0};
        return(int)round((float)$match[1]*(1024**$power));
    }
    private function uploadSizeLabel(int $bytes):string{return number_format($bytes/1048576,2).' MB';}
    public function index()
    {
        if(!can('resale_listings.view'))return admin_denied();try{return view('admin/listing_review/index',(new AdminListingReviewReadModel(db_connect()))->queue($this->request->getGet())+['pageTitle'=>'Property Listings','tab'=>'resale']);}catch(\InvalidArgumentException$e){return$this->json(['message'=>$e->getMessage()],422);}
    }
    public function show(int $id)
    {
        if(!can('resale_listings.view'))return admin_denied();try{$data=(new AdminListingReviewReadModel(db_connect()))->detail($id,can('customers.view'));return view('admin/listing_review/detail',$data+['pageTitle'=>'Review Listing '.\App\Services\ListingReference::display($data['record']),'tab'=>'resale']);}catch(CustomerFlowException$e){throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();}
    }
    public function confirmDelete(int $id)
    {
        if(!can('resale_listings.view')||!can('resale_listings.edit')||!can('resale_listings.deactivate'))return admin_denied();
        try{$record=$this->service()->row($id);}catch(CustomerFlowException $e){throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();}
        return view('admin/listing_review/delete',['record'=>$record,'payload'=>json_decode($record['payload'],true)?:[],'fingerprint'=>ListingLifecycle::fingerprint($record),'deleted'=>(new AdminListingDeletion(db_connect()))->deletion($id),'pageTitle'=>'Delete Listing '.\App\Services\ListingReference::display($record),'tab'=>'resale']);
    }
    public function delete(int $id)
    {
        if(!can('resale_listings.view')||!can('resale_listings.edit')||!can('resale_listings.deactivate'))return admin_denied();
        try{
            $data=$this->input();if(($data['confirmed']??null)!=='1')throw new \InvalidArgumentException('Confirm the listing deletion first.');
            $reason=$data['reason']??'';if(!is_string($reason))throw new \InvalidArgumentException('Enter a valid deletion reason.');
            (new AdminListingDeletion(db_connect()))->delete($id,(int)admin_user()['id'],$this->version($data),$this->fingerprint($data),$reason);
            session()->setFlashdata('listing_delete_message','Listing deleted. Property ownership and history have been retained.');
            return redirect()->to(site_url('admin/listings?review='),303);
        }catch(CustomerFlowException|\InvalidArgumentException $e){session()->setFlashdata('listing_delete_error',$e->getMessage());}
        catch(\Throwable $e){log_message('error','Admin listing deletion failed ({type}) at {origin}:{line}.',['type'=>get_class($e),'origin'=>basename($e->getFile()),'line'=>$e->getLine()]);session()->setFlashdata('listing_delete_error','Deletion could not be saved. The listing and its history were preserved.');}
        return redirect()->to(site_url('admin/listings/'.$id.'/delete'),303);
    }
    public function verify(int $id){return$this->action('resale_listings.approve',function()use($id){$data=$this->input();$proof=$this->service()->verify($id,(int)admin_user()['id'],$this->version($data),$this->fingerprint($data));return$this->json(['message'=>'Current ERP ownership and eligibility verified. Approve & Publish will check again.','verification'=>$proof]);});}
    public function propertyTypePreview(int $id)
    {
        return $this->action('resale_listings.edit',function()use($id){
            $this->version($this->input());
            $preview=(new \App\Services\AdminPropertyTypeRefresh(db_connect()))->preview($id);
            return $this->json(['message'=>'Current SGC classification confirmed. Review the proposed type before saving.','preview'=>$preview]);
        });
    }
    public function refreshPropertyType(int $id)
    {
        return $this->action('resale_listings.edit',function()use($id){
            $data=$this->input();if(($data['confirmed']??null)!==true)throw new \InvalidArgumentException('Confirm the SGC-verified property type change.');
            (new \App\Services\AdminPropertyTypeRefresh(db_connect()))->apply($id,(int)admin_user()['id'],$this->version($data),$this->fingerprint($data));
            return $this->json(['message'=>'Saved listing type updated from Plot to House. Listing approval and other listing details were preserved.','redirect'=>site_url('admin/listings/'.$id.'/review')]);
        });
    }
    public function decision(int $id,string $action)
    {
        return$this->action($action==='approve'?'resale_listings.approve':'resale_listings.reject',function()use($id,$action){$data=$this->input();if(($data['confirmed']??null)!==true)throw new \InvalidArgumentException('Confirm the exact review decision.');$reason=$data['reason']??'';if(!is_string($reason))throw new \InvalidArgumentException('Enter a valid reason.');$assigned=$data['portal_agent_id']??null;if($assigned!==null){$assigned=\App\Services\ShalimarAgents::identifier($assigned);if(strlen($assigned)>9)throw new \InvalidArgumentException('Select a valid portal agent.');$assigned=(int)$assigned;}$result=$this->service()->decide($id,(int)admin_user()['id'],$action,$this->version($data),$this->fingerprint($data),$reason,$assigned);return$this->json(['message'=>['approve'=>'Listing approved and published.','reject'=>'Listing rejected; the customer can see your reason.','changes'=>'Changes requested; fresh owner OTP is required for corrections.'][$action],'result'=>$result,'redirect'=>site_url('admin/listings/'.$id.'/review')]);});
    }
    public function upload(int $id){return$this->action('resale_listings.edit',function()use($id){$data=$this->input();$files=$this->request->getFileMultiple('photos')??[];if(!$files){$contentLength=(int)$this->request->getHeaderLine('Content-Length');$postMax=(string)ini_get('post_max_size');$postMaxBytes=$this->iniBytes($postMax);if($contentLength>0&&$postMaxBytes>0&&$contentLength>$postMaxBytes)throw new \InvalidArgumentException('PHP discarded the photos because this upload request is '.$this->uploadSizeLabel($contentLength).', above post_max_size='.$postMax.'. Increase PHP post_max_size above the combined size of the selected photos.');$details='PHP received no files in the photos[] field. Current PHP limits: post_max_size='.$postMax.', upload_max_filesize='.(string)ini_get('upload_max_filesize').', max_file_uploads='.(string)ini_get('max_file_uploads').'. Check the multipart request; when its total size exceeds post_max_size, PHP discards all uploaded files.';throw new \InvalidArgumentException($details);}$approvedMedia=($data['approved_media']??null)==='1';$this->service()->upload($id,(int)admin_user()['id'],$this->version($data),$this->fingerprint($data),$files,$approvedMedia);return$this->json(['message'=>$approvedMedia?'Approved listing photos updated.':'Photos added privately. Review the updated photo set.','redirect'=>site_url('admin/listings/'.$id.'/review')],201);});}
    public function retryAssignmentSms(int $id)
    {
        return $this->action('resale_listings.approve',function()use($id){
            $data=$this->input();$attempts=$data['attempts']??null;
            if(($data['confirmed']??null)!==true||!is_int($attempts)||$attempts<0)throw new \InvalidArgumentException('Confirm the current SMS delivery attempt before retrying.');
            $result=$this->service()->retryAssignmentSms($id,(int)admin_user()['id'],$this->version($data),$this->fingerprint($data),$attempts);
            return $this->json(['message'=>'Agent SMS delivery status updated. The listing approval was preserved.','assignment_sms'=>$result,'redirect'=>site_url('admin/listings/'.$id.'/review')]);
        });
    }
    public function photoSet(int $id){return$this->action('resale_listings.edit',function()use($id){$data=$this->input();if(($data['confirmed']??null)!==true||!is_array($data['photos']??null))throw new \InvalidArgumentException('Confirm the final photo set.');$approvedMedia=($data['approved_media']??false)===true;$youtube=$approvedMedia?null:(array_key_exists('youtube_url',$data)?(is_string($data['youtube_url'])?$data['youtube_url']:throw new \InvalidArgumentException('Enter a YouTube link.')):null);$this->service()->photoSet($id,(int)admin_user()['id'],$this->version($data),$this->fingerprint($data),$data['photos'],null,$youtube,$approvedMedia);return$this->json(['message'=>$approvedMedia?'Approved listing photos updated; publication was preserved.':'Listing media updated; previous files and history retained.','redirect'=>site_url('admin/listings/'.$id.'/review')]);});}
    public function photo(int $id,string $photo)
    {
        if(!can('resale_listings.view'))return admin_denied();try{$row=$this->service()->row($id);$file=(new \App\Services\AdminListingPhotos(db_connect()))->file($row,$photo);return$this->response->setHeader('Cache-Control','no-store, private')->setHeader('X-Content-Type-Options','nosniff')->setContentType($file['mime'])->setBody(file_get_contents($file['path']));}catch(CustomerFlowException$e){return$this->response->setStatusCode(404)->setBody('Image unavailable.');}
    }
}
