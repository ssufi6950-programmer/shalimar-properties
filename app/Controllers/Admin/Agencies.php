<?php
namespace App\Controllers\Admin;

final class Agencies extends \App\Controllers\BaseController
{
    protected $helpers=['url','form','admin'];
    public function index()
    {
        if (!can('agencies.view')) return admin_denied();$db=db_connect();$directory=service('agencyDirectory');
        $tab=$this->request->getGet('tab')==='unmapped'?'unmapped':'directory';$page=max(1,min(100000,(int)$this->request->getGet('page')));$requestedSize=$this->request->getGet('per_page');$size=in_array($requestedSize,['25','50','100'],true)?(int)$requestedSize:25;
        if ($tab==='unmapped') {
            $query=$directory->unmappedLinks();
            $total=(clone $query)->countAllResults();$rows=$query->orderBy('l.first_discovered_at')->orderBy('l.id')->limit($size,($page-1)*$size)->get()->getResultArray();
            foreach ($rows as &$row) $row['suggestions']=$directory->suggestions($row['normalized_name']);unset($row);
        } else {
            $query=$directory->portalAgencies();$total=(clone $query)->countAllResults();$rows=$query->orderBy('a.agency_name')->orderBy('a.id')->limit($size,($page-1)*$size)->get()->getResultArray();
            $counts=$rows?array_column($directory->authorizedLinks()->select('l.portal_agency_id,COUNT(*) AS link_count',false)->whereIn('l.portal_agency_id',array_column($rows,'id'))->where('l.mapping_status','mapped')->groupBy('l.portal_agency_id')->get()->getResultArray(),'link_count','portal_agency_id'):[];
            foreach($rows as&$row)$row['link_count']=$counts[$row['id']]??0;unset($row);
        }
        return view('admin/agencies/index',['pageTitle'=>'Authorized Agencies','tab'=>'agencies','agencyTab'=>$tab,'rows'=>$rows,'total'=>$total,'page'=>$page,'size'=>$size,'pending'=>$directory->unmappedCount(),'authorizedTotal'=>$directory->authorizedLinks()->countAllResults(),'sources'=>$db->table('erp_sources')->get()->getResultArray(),'runs'=>can('erp_sync.view_logs')?$db->query('SELECT r.*,s.name AS source_name FROM agency_sync_runs r JOIN erp_sources s ON s.id=r.erp_source_id ORDER BY r.id DESC LIMIT 10')->getResultArray():[]]);
    }
    public function link(int $id)
    {
        if (!can('agencies.view')) return admin_denied();
        try {$row=service('agencyDirectory')->link($id);}catch (\InvalidArgumentException $e) {throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();}
        return view('admin/agencies/link',['pageTitle'=>'ERP Agency Details','tab'=>'agencies','link'=>$row,'mappable'=>service('agencyDirectory')->isMappable($row),'contact'=>json_decode($row['contact_json'],true)??[],'suggestions'=>service('agencyDirectory')->suggestions($row['normalized_name']),'agencies'=>db_connect()->table('authorized_agencies')->orderBy('agency_name')->get()->getResultArray()]);
    }
    public function show(int $id)
    {
        if (!can('agencies.view')) return admin_denied();$db=db_connect();$row=$db->table('authorized_agencies')->where('id',$id)->get()->getRowArray();if (!$row) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $accounts=service('agencyPortalAccounts');$account=$accounts->find($id);
        return view('admin/agencies/show',['pageTitle'=>$row['agency_name'],'tab'=>'agencies','agency'=>$row,'links'=>$db->query('SELECT l.*,s.name AS source_name FROM agency_erp_links l JOIN erp_sources s ON s.id=l.erp_source_id WHERE l.portal_agency_id=? ORDER BY s.name,l.id',[$id])->getResultArray(),'portalAccount'=>$account,'portalEligible'=>$accounts->agencyEligible($id),'phoneUsernameValid'=>\App\Libraries\AgencyPortalIdentity::phone((string)$row['phone'])!==null]);
    }
    private function portalCredentialResponse(int $agencyId,array $credentials)
    {
        $agency=db_connect()->table('authorized_agencies')->where('id',$agencyId)->get()->getRowArray();
        if(!$agency)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        return $this->response->setHeader('Cache-Control','no-store, private')->setHeader('Pragma','no-cache')->setHeader('Referrer-Policy','no-referrer')->setBody(view('admin/agencies/credentials_once',['pageTitle'=>'Agency Portal Credentials','tab'=>'agencies','agency'=>$agency,'credentials'=>$credentials]));
    }
    public function portalAccess(int $id)
    {
        if(!can('agencies.edit'))return admin_denied();
        try {
            $type=$this->request->getPost('username_type');$username=$this->request->getPost('custom_username');
            if(!is_string($type)||($username!==null&&!is_string($username)))throw new \DomainException('Choose a valid username type and username.');
            $credentials=service('agencyPortalAccounts')->create($id,(int)admin_user()['id'],$type,$username);
            return $this->portalCredentialResponse($id,$credentials);
        } catch(\DomainException $e) { session()->setFlashdata('agency_error',$e->getMessage()); }
        catch(\Throwable $e) { log_message('error','Agency portal credential generation failed ({type}).',['type'=>get_class($e)]);session()->setFlashdata('agency_error','Credentials could not be generated. No password was displayed.'); }
        return redirect()->to(site_url('admin/agencies/'.$id));
    }
    public function portalReset(int $id)
    {
        if(!can('agencies.edit'))return admin_denied();
        try {
            $credentials=service('agencyPortalAccounts')->reset($id,(int)admin_user()['id']);
            return $this->portalCredentialResponse($id,$credentials);
        } catch(\DomainException $e) { session()->setFlashdata('agency_error',$e->getMessage()); }
        catch(\Throwable $e) { log_message('error','Agency portal password reset failed ({type}).',['type'=>get_class($e)]);session()->setFlashdata('agency_error','Password reset could not be completed.'); }
        return redirect()->to(site_url('admin/agencies/'.$id));
    }
    public function portalStatus(int $id)
    {
        return $this->perform('agencies.edit',function()use($id){$enabled=$this->request->getPost('enabled');if(!in_array($enabled,['0','1'],true))throw new \InvalidArgumentException('Choose a valid portal access status.');service('agencyPortalAccounts')->setEnabled($id,(int)admin_user()['id'],$enabled==='1');},'admin/agencies/'.$id);
    }
    private function perform(string $permission,callable $action,string $return)
    {
        if (!can($permission)) return admin_denied();
        try {$action();session()->setFlashdata('agency_message','Agency change saved.');}
        catch (\InvalidArgumentException $e) {session()->setFlashdata('agency_error',$e->getMessage());}
        catch (\Throwable $e) {log_message('error','Agency operation failed ({type}) at {file}:{line}.',['type'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine()]);session()->setFlashdata('agency_error','The change could not be saved. Previous records have been preserved.');}
        return redirect()->to(site_url($return));
    }
    public function map(int $id)
    {
        return $this->perform('agencies.edit',function()use($id){$agency=$this->request->getPost('portal_agency_id');if (!is_string($agency)||!preg_match('/\A[1-9][0-9]{0,17}\z/D',$agency)) throw new \InvalidArgumentException('Choose a portal agency.');service('agencyDirectory')->map($id,(int)$agency,(int)admin_user()['id']);},'admin/agency-links/'.$id);
    }
    public function create(int $id)
    {
        if (!can('agencies.edit')) return admin_denied();
        return $this->perform('agencies.create',fn()=>service('agencyDirectory')->createAndMap($id,$this->request->getPost(),(int)admin_user()['id']),'admin/agency-links/'.$id);
    }
    public function bulkReview()
    {
        if (!can('agencies.edit')) return admin_denied();
        session()->remove('agency_bulk_review');
        try {
            $selected=$this->request->getPost('selected_links');$targets=$this->request->getPost('portal_agencies');
            if (!is_array($selected)||!array_is_list($selected)||!is_array($targets)||count($selected)>100) throw new \InvalidArgumentException('Select between 1 and 100 agencies for review.');
            $choices=[];foreach ($selected as $id) {if (!is_string($id)||!preg_match('/\A[1-9][0-9]{0,17}\z/D',$id)) throw new \InvalidArgumentException('Invalid ERP agency selection.');$choices[]=['link_id'=>$id,'portal_agency_id'=>$targets[$id]??null];}
            if(in_array('new',array_column($choices,'portal_agency_id'),true)&&!can('agencies.create'))return admin_denied();
            $rows=service('agencyDirectory')->reviewMappings($choices,can('agencies.create'));$token=bin2hex(random_bytes(32));$pageSize=$this->request->getPost('per_page');
            session()->set('agency_bulk_review',['token'=>$token,'actor'=>(int)admin_user()['id'],'expires'=>time()+600,'rows'=>$rows,'per_page'=>in_array($pageSize,['25','50','100'],true)?$pageSize:'25']);
            return view('admin/agencies/bulk_review',['pageTitle'=>'Review Agency Mappings','tab'=>'agencies','rows'=>$rows,'reviewToken'=>$token]);
        } catch (\InvalidArgumentException $e) {session()->setFlashdata('agency_error',$e->getMessage());}
        catch (\Throwable $e) {log_message('error','Agency bulk review unavailable ({type}).',['type'=>get_class($e)]);session()->setFlashdata('agency_error','Bulk review could not be prepared. No mappings were changed.');}
        return redirect()->to(site_url('admin/agencies?tab=unmapped'));
    }
    public function bulkMap()
    {
        if (!can('agencies.edit')) return admin_denied();$review=session()->get('agency_bulk_review');$token=$this->request->getPost('review_token');$size='25';
        try {
            if (!is_array($review)||!is_string($token)||!is_string($review['token']??null)||!hash_equals($review['token'],$token)||($review['actor']??null)!==(int)admin_user()['id']||($review['expires']??0)<time()) throw new \InvalidArgumentException('This mapping review expired or was already used. Select matches and review again.');
            if(in_array('create',array_column($review['rows'],'action'),true)&&!can('agencies.create'))return admin_denied();
            session()->remove('agency_bulk_review');$size=$review['per_page'];
            $result=service('agencyDirectory')->bulkMap($review['rows'],(int)admin_user()['id'],can('agencies.create'));
            session()->setFlashdata('agency_message',$result['mapped'].' agency mappings confirmed.'.(!empty($result['created'])?' '.$result['created'].' portal agencies created.':'').($result['already_mapped']?' '.$result['already_mapped'].' were already confirmed.':''));
        } catch (\InvalidArgumentException $e) {session()->setFlashdata('agency_error',$e->getMessage());}
        catch (\Throwable $e) {log_message('error','Agency bulk mapping unavailable ({type}).',['type'=>get_class($e)]);session()->setFlashdata('agency_error','Bulk confirmation failed. No mappings were changed.');}
        return redirect()->to(site_url('admin/agencies?tab=unmapped&per_page='.$size));
    }
    public function update(int $id)
    {
        return $this->perform('agencies.edit',fn()=>service('agencyDirectory')->update($id,$this->request->getPost(),(int)admin_user()['id']),'admin/agencies/'.$id);
    }
    public function status(int $id)
    {
        return $this->perform('agencies.deactivate',function()use($id){$active=$this->request->getPost('portal_active');if (!in_array($active,['0','1'],true)) throw new \InvalidArgumentException('Invalid agency status.');service('agencyDirectory')->setActive($id,$active==='1',(int)admin_user()['id']);},'admin/agencies/'.$id);
    }
    public function sync(?int $id=null)
    {
        if (!can('erp_sync.run')||!can('agencies.edit')) return admin_denied();$db=db_connect();$query=$db->table('erp_sources')->where('is_active',1);if ($id!==null) $query->where('id',$id);$sources=$query->get()->getResultArray();$messages=[];$failed=false;
        foreach ($sources as $source) {
            try {$result=(new \App\Services\AgencySync($db))->synchronize((int)$source['id'],fn($s,$after,$max,$limit)=>service('agencyApi')->page($s,$after,$max,$limit),(int)admin_user()['id']);$messages[]=$source['name'].': '.$result['authorized'].' authorized agencies; '.$result['unmapped'].' require mapping.';}
            catch (\Throwable $e) {$failed=true;$messages[]=$source['name'].': synchronization failed; previous records retained. Check the agency API upload and source connection.';log_message('error','Agency synchronization failed for source {source} ({type}).',['source'=>$source['id'],'type'=>get_class($e)]);}
        }
        session()->setFlashdata($failed?'agency_error':'agency_message',$sources?implode(' ',$messages):'No enabled ERP sources are available.');return redirect()->to(site_url('admin/agencies?tab=unmapped'));
    }
}
