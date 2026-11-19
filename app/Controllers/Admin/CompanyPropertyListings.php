<?php
namespace App\Controllers\Admin;
use App\Services\CompanyPropertyListing;
final class CompanyPropertyListings extends \App\Controllers\BaseController
{
    protected $helpers=['url','form','admin'];
    private function allowed():bool{return can('properties.view')&&can('resale_listings.view')&&can('resale_listings.edit')&&can('resale_listings.approve');}
    private function service():CompanyPropertyListing{return new CompanyPropertyListing(db_connect());}
    private function scope($source,$property):array
    {
        if(!is_string($source)||!preg_match('/\A[1-9][0-9]{0,9}\z/D',$source)||!is_string($property)||!preg_match('/\A[1-9][0-9]{0,9}\z/D',$property))throw new \InvalidArgumentException('Select an exact inventory property.');
        return [(int)$source,$property];
    }
    public function form(?int $id=null)
    {
        if($id===null?!$this->allowed():(!can('properties.view')||!can('resale_listings.view')))return admin_denied();
        try{
            $listing=$id?$this->service()->row($id):null;
            [$source,$property]=$listing?[(int)$listing['erp_source_id'],(string)$listing['erp_property_id']]:$this->scope($this->request->getGet('source'),$this->request->getGet('property'));
            return $this->renderForm($source,$property,$listing);
        }catch(\Throwable $e){return $this->failed($e);}
    }
    private function renderForm(int $source,string $property,?array $listing=null,array $input=[],?string $error=null)
    {
        $input=array_filter($input,static fn($value)=>is_string($value)||is_int($value)||is_bool($value)||is_array($value));
        foreach($input as$key=>$value)if(is_array($value)&&$key!=='rental')unset($input[$key]);
        if(isset($input['rental']))$input['rental']=is_array($input['rental'])?array_filter($input['rental'],static fn($value)=>is_string($value)||is_int($value)):[];
        $current=true;
        try{$fresh=$this->service()->property($source,$property);}
        catch(\Throwable $failure){
            if(!$listing)throw $failure;
            $saved=json_decode($listing['payload'],true)?:[];$current=false;
            $sourceRow=db_connect()->table('erp_sources')->select('id,code')->where('id',$source)->get()->getRowArray();
            $project=db_connect()->table('portal_projects')->select('name')->where('slug',$listing['project_slug'])->get()->getRowArray();
            $fresh=['inventory'=>['id'=>$property,'property_no'=>$saved['plot_no']??$property,'size_label'=>$saved['size_label']??'','floor'=>$saved['floor']??'','status'=>$saved['_company_inventory']['inventory_status']??'Not recorded'],'source'=>$sourceRow?:['id'=>$source,'code'=>'Not recorded'],'mapping'=>['project_name'=>$project['name']??$listing['project_slug']],'classification'=>['type'=>\App\Services\ErpPropertyClassification::listingType($saved,$sourceRow['code']??''),'construction_status'=>'Not freshly verified']];
        }
        $forms=session()->get('company_listing_forms')??[];
        if(!is_array($forms))$forms=[];$key=bin2hex(random_bytes(32));$forms[$key]=[(int)admin_user()['id'],$source,$property,$listing['id']??null,time()];
        session()->set('company_listing_forms',array_slice($forms,-20,null,true));
        return view('admin/company_listing_form',['pageTitle'=>$listing?'Manage Company Listing '.\App\Services\ListingReference::display($listing):'Create Company Listing','tab'=>'company-properties','fresh'=>$fresh,'listing'=>$listing,'input'=>$input,'error'=>$error,'key'=>$key,'current'=>$current,'mayEdit'=>$current&&$this->allowed(),'agents'=>$current&&$this->allowed()?(new \App\Services\ShalimarAgents(db_connect()))->adminAssignmentOptions($source):[]]);
    }
    public function save()
    {
        if(!$this->allowed())return admin_denied();
        try{
            $input=$this->request->getPost();if(!is_array($input))throw new \InvalidArgumentException('Send valid listing details.');
            [$source,$property]=$this->scope($input['source']??null,$input['property']??null);
            $key=$input['submission_key']??null;$forms=session()->get('company_listing_forms')??[];$context=is_string($key)?($forms[$key]??null):null;
            $id=$input['listing_id']??'';if(!is_string($id)||($id!==''&&!preg_match('/\A[1-9][0-9]{0,17}\z/D',$id)))throw new \InvalidArgumentException('Invalid listing identity.');$id=$id===''?null:(int)$id;
            if(!is_array($context)||$context[0]!== (int)admin_user()['id']||$context[1]!==$source||$context[2]!==$property||($context[3]===null?null:(int)$context[3])!==$id||$context[4]<time()-1800)throw new \InvalidArgumentException('This form expired or changed. Open the property again.');
            $files=$this->request->getFileMultiple('photos')??[];
            $saved=$this->service()->save((int)admin_user()['id'],$source,$property,$input,$files,$id);
            unset($forms[$key]);session()->set('company_listing_forms',$forms);$savedRow=$this->service()->row($saved);$reference=\App\Services\ListingReference::display($savedRow);
            $underReview=$savedRow['review_state']==='under_review';session()->setFlashdata('company_message',$underReview?'Company listing '.$reference.' sent to resale review. Add photos and assign a Shalimar Agent before approval.':'Company listing '.$reference.' published successfully.');
            return redirect()->to(site_url('admin/listings').'?review='.($underReview?'under_review':'approved').'&q='.rawurlencode($reference));
        }catch(\InvalidArgumentException $e){
            try{if(isset($source,$property))return $this->renderForm($source,$property,isset($id)&&$id?$this->service()->row($id):null,$input??[],$e->getMessage());}catch(\Throwable $ignored){}
            return $this->failed($e);
        }catch(\Throwable $e){return $this->failed($e);}
    }
    public function unpublish(int $id)
    {
        if(!can('properties.view')||!can('resale_listings.view')||!can('resale_listings.deactivate'))return admin_denied();
        try{
            $version=$this->request->getPost('version');if(!is_string($version)||!preg_match('/\A[1-9][0-9]{0,9}\z/D',$version))throw new \InvalidArgumentException('Reload the listing.');
            $this->service()->unpublish($id,(int)admin_user()['id'],(int)$version);session()->setFlashdata('company_message','Company listing unpublished.');
            $reference=\App\Services\ListingReference::display($this->service()->row($id));return redirect()->to(site_url('admin/listings').'?review=&q='.rawurlencode($reference));
        }catch(\Throwable $e){return $this->failed($e);}
    }
    private function failed(\Throwable $error)
    {
        if(!$error instanceof \InvalidArgumentException)log_message('error','Company listing action failed: type={type} file={origin} line={line}',['type'=>get_class($error),'origin'=>basename($error->getFile()),'line'=>$error->getLine()]);
        session()->setFlashdata('company_error',$error instanceof \InvalidArgumentException?$error->getMessage():'Company listing is unavailable. Verify the API update, project mapping and configured source, then retry.');
        return redirect()->to(site_url('admin/company-properties'));
    }
}
