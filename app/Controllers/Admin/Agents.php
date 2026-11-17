<?php
namespace App\Controllers\Admin;

final class Agents extends \App\Controllers\BaseController
{
    protected $helpers=['url','form','admin'];
    public function index(?int $id=null)
    {
        if(!can('agents.view'))return admin_denied();$db=db_connect();$directory=service('shalimarAgents');$settings=$directory->settings();$page=max(1,min(100000,(int)$this->request->getGet('page')));$q=$this->request->getGet('q');$q=is_string($q)?mb_substr(trim($q),0,100):'';
        $query=$db->table('shalimar_agents a')->select('a.*,l.id AS link_id,l.erp_source_id,l.erp_dealer_id,l.erp_ledger_id,l.erp_agency_id,l.erp_active,l.erp_eligible,l.present,l.last_synced_at,s.name AS source_name,s.code AS source_code')->join('agent_erp_links l','l.portal_agent_id=a.id AND l.id=(SELECT MIN(primary_link.id) FROM agent_erp_links primary_link WHERE primary_link.portal_agent_id=a.id)')->join('erp_sources s','s.id=l.erp_source_id');
        if($id!==null)$query->where('a.id',$id);if($q!=='')$query->groupStart()->like('a.name',$q)->orLike('l.erp_ledger_id',$q)->orLike('l.erp_dealer_id',$q)->orWhere('EXISTS (SELECT 1 FROM agent_erp_links search_link WHERE search_link.portal_agent_id=a.id AND CAST(search_link.erp_ledger_id AS CHAR) LIKE '.$db->escape('%'.$db->escapeLikeString($q).'%')." ESCAPE '!')",null,false)->groupEnd();$total=(clone $query)->countAllResults();$rows=$query->orderBy('a.name')->orderBy('a.id')->limit(25,($page-1)*25)->get()->getResultArray();
        $memberships=[];if($rows)foreach($db->table('agent_erp_links l')->select('l.*,s.code,s.name AS source_name')->join('erp_sources s','s.id=l.erp_source_id')->whereIn('l.portal_agent_id',array_column($rows,'id'))->orderBy('l.erp_source_id')->get()->getResultArray()as$link)$memberships[(int)$link['portal_agent_id']][]=$link;
        foreach($rows as&$row)$row['erp_links']=$memberships[(int)$row['id']]??[];unset($row);
        if($id!==null&&!$rows)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $eligible=array_map('intval',array_column($db->query($directory->eligibleSql())->getResultArray(),'portal_agent_id'));
        $listings=[];if(can('resale_listings.view')){$listingQuery=$db->table('portal_listings p')->select('p.*,s.name AS property_source')->join('erp_sources s','s.id=p.erp_source_id')->where('p.portal_agent_id IS NOT NULL',null,false);if($id!==null)$listingQuery->where('p.portal_agent_id',$id);if($this->request->getGet('review')==='1')$listingQuery->where('p.agent_review_required',1);$listings=$listingQuery->orderBy('p.id','DESC')->limit(100)->get()->getResultArray();}
        return view('admin/agents/index',['pageTitle'=>'Shalimar Agents','tab'=>'agents','rows'=>$rows,'eligibleIds'=>$eligible,'settings'=>$settings,'sourceSettings'=>$directory->sourceRows(),'agencies'=>$db->table('authorized_agencies')->orderBy('agency_name')->get()->getResultArray(),'lastSuccess'=>$db->table('agent_sync_runs')->where('status','success')->orderBy('id','DESC')->get(1)->getRowArray(),'runs'=>can('erp_sync.view_logs')?$db->table('agent_sync_runs')->orderBy('id','DESC')->get(10)->getResultArray():[],'reviewCount'=>$directory->reviewCount(),'listings'=>$listings,'q'=>$q,'total'=>$total,'page'=>$page,'selectedAgent'=>$id]);
    }
    public function links(int $id)
    {
        if(!can('agents.view')||!can('agents.edit'))return admin_denied();
        $db=db_connect();$details=service('shalimarAgents')->deletionDetails($id);
        $details['links']=$db->table('agent_erp_links l')->select('l.*,s.name AS source_name,s.code AS source_code')->join('erp_sources s','s.id=l.erp_source_id')->where('l.portal_agent_id',$id)->orderBy('l.erp_source_id')->get()->getResultArray();
        $targets=$db->query('SELECT a.id,a.name,a.mobile FROM shalimar_agents a WHERE a.portal_active=1 AND a.id<>? AND EXISTS (SELECT 1 FROM agent_erp_links l WHERE l.portal_agent_id=a.id) ORDER BY a.name,a.id',[$id])->getResultArray();
        return view('admin/agents/links',$details+['targets'=>$targets,'pageTitle'=>'Agent ERP Links','tab'=>'agents']);
    }
    public function mapLink(int $id)
    {
        if(!can('agents.view')||!can('agents.edit'))return admin_denied();
        return $this->perform(function()use($id){
            if($this->request->getPost('confirmed')!=='1')throw new \InvalidArgumentException('Confirm these ERP identities belong to the same person.');
            $target=(int)\App\Services\ShalimarAgents::identifier($this->request->getPost('portal_agent_id'));
            $owner=(int)\App\Services\ShalimarAgents::identifier($this->request->getPost('expected_owner'));
            service('shalimarAgents')->mapLink($id,$target,$owner,(int)admin_user()['id']);
        },'ERP identity linked to the central agent. Listings and SMS history were preserved.');
    }
    private function perform(callable $action,string $message)
    {
        try{$action();session()->setFlashdata('agent_message',$message);}catch(\InvalidArgumentException $e){session()->setFlashdata('agent_error',$e->getMessage());}catch(\Throwable $e){log_message('error','Shalimar agent operation failed ({type}) at {file}:{line}.',['type'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine()]);session()->setFlashdata('agent_error','The operation could not finish for every source. Completed source syncs were saved; failed sources retained their previous agents. Check both dealers.php uploads, ledger.sp_Agent, the portal image column and confirmed Internal Agency mappings.');}return redirect()->to(site_url('admin/agents'));
    }
    public function sync()
    {
        if(!can('agents.edit')||!can('erp_sync.run'))return admin_denied();
        return $this->perform(fn()=>(new \App\Services\ShalimarAgentSync(db_connect()))->synchronize(fn($source,$agency,$after,$maximum,$limit)=>service('dealerApi')->dealers($source,$agency,$after,$maximum,$limit),(int)admin_user()['id']),'Shalimar Agents synchronized. Eligible agents can handle SGC and Zalmi resale properties.');
    }
    public function configure()
    {
        if(!can('erp_sync.manage_settings')||!can('agents.edit')||!can('agencies.edit'))return admin_denied();
        return $this->perform(function(){
            $source=(int)\App\Services\ShalimarAgents::identifier($this->request->getPost('erp_source_id'));
            $agency=\App\Services\ShalimarAgents::identifier($this->request->getPost('erp_agency_id'));
            $central=(int)\App\Services\ShalimarAgents::identifier($this->request->getPost('portal_agency_id'));
            $version=\App\Services\ShalimarAgents::identifier($this->request->getPost('version'));
            $centralVersion=\App\Services\ShalimarAgents::identifier($this->request->getPost('central_version'));
            $enabled=$this->request->getPost('is_active');
            if(strlen($version)>9||strlen($centralVersion)>9||!in_array($enabled,['0','1'],true))throw new \InvalidArgumentException('Reload the source configuration and choose a valid enabled status.');
            service('shalimarAgents')->configure($source,$agency,$central,(int)admin_user()['id'],(int)$version,(int)$centralVersion,$enabled==='1');
        },'Source configuration saved. Sync enabled sources to verify any changed configuration.');
    }
    public function status(int $id)
    {
        if(!can('agents.deactivate'))return admin_denied();return $this->perform(function()use($id){$active=$this->request->getPost('portal_active');if(!in_array($active,['0','1'],true))throw new \InvalidArgumentException('Choose a valid portal status.');service('shalimarAgents')->update($id,$active==='1',null,(int)admin_user()['id']);},'Agent portal status saved. Historical assignments have been preserved.');
    }
    public function contacts(int $id)
    {
        if(!can('agents.edit'))return admin_denied();return $this->perform(function()use($id){$approved=$this->request->getPost('contact_approved');if(!in_array($approved,['0','1'],true))throw new \InvalidArgumentException('Choose a valid contact approval.');service('shalimarAgents')->update($id,null,$approved==='1',(int)admin_user()['id']);},'Legacy contact flag recorded. Valid business contacts are displayed automatically for eligible agents.');
    }
    public function confirmDelete(int $id)
    {
        if(!can('agents.view')||!can('agents.edit')||!can('agents.deactivate'))return admin_denied();
        try{$details=service('shalimarAgents')->deletionDetails($id);}catch(\InvalidArgumentException $e){session()->setFlashdata('agent_error',$e->getMessage());return redirect()->to(site_url('admin/agents'));}
        return view('admin/agents/delete',$details+['pageTitle'=>'Delete Agent','tab'=>'agents']);
    }
    public function delete(int $id)
    {
        if(!can('agents.view')||!can('agents.edit')||!can('agents.deactivate'))return admin_denied();
        return $this->perform(function()use($id){
            if($this->request->getPost('confirmed')!=='1')throw new \InvalidArgumentException('Confirm the agent deletion first.');
            service('shalimarAgents')->delete($id,(int)admin_user()['id']);
        },'Unused agent deleted from the portal. ERP records were preserved. A future ERP sync may import the agent again.');
    }
    public function review(int $id)
    {
        if(!can('agents.view')||!can('resale_listings.edit'))return admin_denied();return $this->perform(fn()=>service('shalimarAgents')->acknowledge($id,(int)admin_user()['id']),'Assignment reviewed. Listing status was preserved.');
    }
}
