<?php
namespace App\Controllers\Admin;
use App\Services\ErpMappingService;
use App\Services\ErpCatalogSync;
use App\Services\ErpConnectionService;
use App\Models\ErpSourceModel;
class ErpCatalog extends \App\Controllers\BaseController
{
    protected $helpers=['url','form','admin'];
    public function index(string $tab='mapping')
    {
        if (!can($tab==='synchronization'?'erp_sync.view':'erp_mapping.view')) return admin_denied();
        $db=db_connect();
        $pairs=$db->query('SELECT sh.*,h.name AS head_name,h.smp_active AS head_active,h.present AS head_present,s.name AS source_name FROM erp_subheads sh LEFT JOIN erp_heads h ON h.erp_source_id=sh.erp_source_id AND h.erp_head_id=sh.erp_head_id JOIN erp_sources s ON s.id=sh.erp_source_id ORDER BY s.name,h.name,sh.name')->getResultArray();
        return view('admin/erp/catalog',['pageTitle'=>match($tab){'heads'=>'Head / Subhead','synchronization'=>'Synchronization',default=>'Project Mapping'},'tab'=>$tab,
            'heads'=>$db->query('SELECT h.*,s.name AS source_name FROM erp_heads h JOIN erp_sources s ON s.id=h.erp_source_id ORDER BY s.name,h.name')->getResultArray(),
            'sources'=>$db->table('erp_sources')->get()->getResultArray(), 'pairs'=>$pairs,
            'options'=>(new ErpMappingService($db))->options(),
            'projects'=>$db->table('portal_projects')->get()->getResultArray(),
            'mappings'=>$db->query('SELECT m.*,s.name AS source_name,p.name AS project_name,h.name AS head_name,sh.name AS subhead_name,h.smp_active AS head_active,sh.smp_active AS subhead_active,h.present AS head_present,sh.present AS subhead_present FROM erp_project_mappings m'.\App\Services\ErpVisibility::joins().' ORDER BY m.id')->getResultArray(),
            'listings'=>can('resale_listings.edit')?$db->table('portal_listings')->get()->getResultArray():[],
            'runs'=>can('erp_sync.view_logs')?$db->table('erp_sync_runs')->orderBy('id','DESC')->limit(30)->get()->getResultArray():[]]);
    }
    private function perform(string $permission,callable $action,string $tab,string $event)
    {
        if (!can($permission)) return admin_denied();
        try { $action(); (new \App\Services\AdminAuditService())->record($event,'erp_catalog',(int)admin_user()['id']); session()->setFlashdata('catalog_message','Saved successfully.'); }
        catch (\InvalidArgumentException $e) { session()->setFlashdata('catalog_error',$e->getMessage()); }
        catch (\App\Exceptions\ErpCatalogRequestException $e) { session()->setFlashdata('catalog_error',$e->getMessage()); }
        catch (\Throwable $e) { log_message('error','ERP catalog operation failed: {type}',['type'=>get_class($e)]); session()->setFlashdata('catalog_error','Operation failed. Check configuration and retry; no incomplete snapshot was applied.'); }
        return redirect()->to(site_url('admin/workspace/'.$tab));
    }
    public function create()
    {
        return $this->perform('erp_mapping.create',function () {
            $key=$this->request->getPost('pair');
            if (!is_string($key) || !preg_match('/\A([1-9][0-9]{0,9}):([1-9][0-9]{0,17}):([1-9][0-9]{0,17})\z/D',$key,$m)) throw new \InvalidArgumentException('Choose an eligible ERP pair.');
            $project=$this->request->getPost('project');if (!is_string($project)) throw new \InvalidArgumentException('Choose a project.');
            (new ErpMappingService(db_connect()))->create((int)$m[1],$m[2],$m[3],$project);
        },'mapping','erp_mapping.created');
    }
    public function status(int $id)
    {
        return $this->perform('erp_mapping.edit',function () use ($id) {
            $value=$this->request->getPost('active');if (!in_array($value,['0','1'],true)) throw new \InvalidArgumentException('Invalid mapping state.');
            (new ErpMappingService(db_connect()))->setActive($id,$value==='1');
        },'mapping','erp_mapping.status_changed');
    }
    public function assign()
    {
        return $this->perform('resale_listings.edit',function () {
            $listing=$this->request->getPost('listing');$mapping=$this->request->getPost('mapping');
            if (!is_string($listing)||!ctype_digit($listing)||!is_string($mapping)||!ctype_digit($mapping)) throw new \InvalidArgumentException('Choose a listing and mapping.');
            (new ErpMappingService(db_connect()))->assignListing((int)$listing,(int)$mapping);
        },'mapping','erp_mapping.listing_assigned');
    }
    public function sync(int $id)
    {
        return $this->perform('erp_sync.run',function () use ($id) {
            $db=db_connect();$transport=new ErpConnectionService(new ErpSourceModel($db),config('ErpConnections'));
            (new ErpCatalogSync($db))->synchronize($id,fn($source)=>$transport->catalogSnapshot($source));
        },'synchronization','erp_catalog.synchronized');
    }
}
