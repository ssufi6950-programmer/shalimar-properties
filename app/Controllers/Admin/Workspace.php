<?php
namespace App\Controllers\Admin;
class Workspace extends \App\Controllers\BaseController
{
    protected $helpers=['url','form','admin'];
    public function index(){return $this->show('dashboard');}
    public function show(string $tab)
    {
        $permission=\Config\AdminSecurity::TABS[$tab]??null;if(!$permission||!can($permission))return admin_denied();
        if(in_array($tab,['dashboard','analytics'],true)){$records=new PortalRecords();$records->initController($this->request,$this->response,service('logger'));return $records->dashboard($tab==='analytics');}
        if(in_array($tab,['customers','properties','resale'],true)){$query=$this->request->getGet();$suffix=is_array($query)&&$query?'?'.http_build_query($query):'';return redirect()->to(site_url('admin/'.$tab).$suffix);}
        if(in_array($tab,['property-details','resale-details'],true)){$id=$this->request->getGet('id');if(!is_string($id)||!preg_match('/\A[1-9][0-9]{0,17}\z/D',$id))throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();return redirect()->to(site_url('admin/'.($tab==='property-details'?'properties':'resale').'/'.$id));}
        if(in_array($tab,['sources','users','roles','activity'],true))return redirect()->to(admin_nav_url($tab));
        if($tab==='agencies')return redirect()->to(site_url('admin/agencies'));
        if($tab==='agents')return redirect()->to(site_url('admin/agents'));
        if(in_array($tab,['mapping','heads','synchronization'],true)) { $catalog=new ErpCatalog();$catalog->initController($this->request,$this->response,service('logger'));return $catalog->index($tab); }
        return view('admin/security/workspace',['workspaceTab'=>$tab,'pageTitle'=>ucwords(str_replace('-',' ',$tab)),'tab'=>$tab]);
    }
    public function classic()
    {
        $tab=$this->request->getGet('tab')??'dashboard';
        $tabs=['dashboard'=>'dashboard.view','pending'=>'resale_listings.view','images'=>'resale_listings.view','listings'=>'resale_listings.view','transactions'=>'transactions.view','parties'=>'agencies.view','roles'=>'settings.view','projects'=>'projects.view','records'=>'audit_logs.view'];
        if(!is_string($tab)||!isset($tabs[$tab])||!can($tabs[$tab]))return admin_denied();
        if(strtoupper($this->request->getMethod())==='POST') {
            $action=$this->request->getPost('do');
            $actions=['approve'=>'resale_listings.approve','reject'=>'resale_listings.reject','unpublish'=>'resale_listings.deactivate','reopen'=>'resale_listings.edit','remove'=>'resale_listings.deactivate','img_review'=>'resale_listings.edit','deal'=>'resale_listings.edit','add_tx'=>'transactions.create','party_save'=>'agencies.create','party_toggle'=>'agencies.deactivate','roles_save'=>'settings.edit','role_add'=>'settings.edit','role_delete'=>'settings.edit','project_save'=>'projects.edit','project_toggle'=>'projects.publish'];
            if($action==='logout')return(new Auth())->logout();
            if(!is_string($action)||!isset($actions[$action])||!can($actions[$action]))return admin_denied();
            if(\App\Services\LegacyListingPolicy::blocks($action)) {
                $id=$this->request->getPost('id');
                $target=is_string($id)&&preg_match('/\A[1-9][0-9]{0,17}\z/D',$id)?$id:null;
                (new \App\Services\AdminAuditService())->record('resale.legacy_reactivation_blocked','resale_listings',(int)admin_user()['id'],'portal_listing',$target,null,['reason'=>$action]);
                $message=\App\Services\LegacyListingPolicy::MESSAGE;
                if($this->request->isAJAX()||str_contains($this->request->getHeaderLine('Accept'),'application/json'))return $this->response->setStatusCode(409)->setJSON(['message'=>$message]);
                return $this->response->setStatusCode(409)->setBody(view('admin/records/error',['pageTitle'=>'Publication requires customer verification','tab'=>'resale','message'=>$message,'returnUrl'=>site_url('admin/resale')]));
            }
            (new \App\Services\AdminAuditService())->record('legacy.'.$action,'classic',(int)admin_user()['id']);
        }
        define('PORTAL_CLASSIC_AUTHORIZED',true);
        ob_start();require ROOTPATH.'html/admin-classic.php';$html=ob_get_clean();
        $html=str_replace('href="listing.php?','href="'.site_url('admin/listing-preview').'?',$html);
        return str_replace('<head>','<head><base href="'.esc(admin_legacy_url(),'attr').'">',$html);
    }
    public function listingPreview()
    {
        if(!can('resale_listings.view'))return admin_denied();
        define('PORTAL_CLASSIC_AUTHORIZED',true);
        ob_start();require ROOTPATH.'html/listing.php';$html=ob_get_clean();
        return str_replace('<head>','<head><base href="'.esc(admin_legacy_url(),'attr').'">',$html);
    }
}
