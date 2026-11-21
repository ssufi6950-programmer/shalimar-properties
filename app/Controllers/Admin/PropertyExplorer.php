<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Services\ExplorerErpClient;
use App\Services\ExplorerFilterBuilder;
use App\Services\PropertyExplorer as LegacyExplorer;
use CodeIgniter\Exceptions\PageNotFoundException;
use InvalidArgumentException;

final class PropertyExplorer extends \App\Controllers\BaseController
{
    protected $helpers = ['url', 'form', 'admin'];

    private function project(bool $post = false): array
    {
        $slug = $post ? $this->request->getPost('project') : $this->request->getGet('project');
        $db = db_connect();
        if (!$post && $slug === null) $slug = $db->table('portal_projects')->orderBy('name')->get()->getRowArray()['slug'] ?? '';
        if (!is_string($slug)) throw PageNotFoundException::forPageNotFound();
        $project = $db->table('portal_projects')->select('slug,name')->where('slug', $slug)->get()->getRowArray();
        if (!$project) throw PageNotFoundException::forPageNotFound();
        return $project;
    }

    private function draft(string $slug): array
    {
        $draft = session()->get('explorer_builder_draft');
        return is_array($draft) && ($draft['project'] ?? '') === $slug && (int) ($draft['expires'] ?? 0) > time() ? $draft : [];
    }

    private function summaries(string $slug): ?array
    {
        try{return (new \App\Services\ExplorerChildBuilder(db_connect()))->summaries($slug);}
        catch(\Throwable$exception){return null;}
    }

    public function index()
    {
        if (!can('projects.view')) return admin_denied();
        $project = $this->project();
        if($this->request->getGet('parent')!==null)return $this->childIndex($project);
        $service = new ExplorerFilterBuilder(db_connect());
        $scope = null;
        $error = '';
        $rows = [];
        try {
            $scope = $service->scope($project['slug']);
        } catch (\Throwable $exception) {
            $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Project mapping is unavailable.';
        }
        $draft = $this->draft($project['slug']);
        if ($scope && ($draft['scope'] ?? '') !== ExplorerFilterBuilder::fingerprint($scope)) $draft = [];
        try {
            $rows = $service->displayRows($project['slug'], $scope, $draft['options'] ?? []);
        } catch (\Throwable $exception) {
            $error = 'Filter storage unavailable. Verify the manually created portal_explorer_filters table.';
            $scope = null;
        }
        if ($scope && !$draft) {
            $draft = ['project' => $project['slug'], 'scope' => ExplorerFilterBuilder::fingerprint($scope), 'options' => [], 'expires' => time() + 900, 'id' => bin2hex(random_bytes(16))];
            session()->set('explorer_builder_draft', $draft);
        }
        return view('admin/projects/explorer', [
            'pageTitle' => 'Filter Builder', 'tab' => 'explorer-layer2',
            'projects' => db_connect()->table('portal_projects')->select('slug,name')->orderBy('name')->get()->getResultArray(),
            'project' => $project, 'scope' => $scope, 'rows' => $rows, 'draft' => $draft,
            'metadataError' => $error, 'icons' => LegacyExplorer::ICONS, 'childSummaries'=>$this->summaries($project['slug']),
        ]);
    }

    public function fetch()
    {
        if (!can('projects.view') || !can('projects.edit')) return admin_denied();
        $project = $this->project(true);
        if($this->request->getPost('parent')!==null)return $this->childFetch($project);
        try {
            $service = new ExplorerFilterBuilder(db_connect());
            $scope = $service->scope($project['slug']);
            $client = new ExplorerErpClient(new \App\Models\ErpSourceModel(db_connect()), config('ErpConnections'));
            $options = $service->fetch($scope, [$client, 'fetchOptions']);
            // Fetches create only an expiring server-session draft. No filter writes.
            session()->set('explorer_builder_draft', [
                'project' => $project['slug'], 'scope' => ExplorerFilterBuilder::fingerprint($scope),
                'options' => $options, 'expires' => time() + 900, 'id' => bin2hex(random_bytes(16)), 'fetched_at' => date('Y-m-d H:i:s'),
            ]);
            $message = count($options) . ' options fetched. Nothing was saved. Select options and save explicitly.';
            session()->setFlashdata('success', $message);
        } catch (\Throwable $exception) {
            // No draft/saved-filter deletion on connection failure.
            $message = $exception instanceof \App\Exceptions\ExplorerMetadataException || $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'ERP metadata request failed. Check API upload and server logs.';
            log_message('error', 'Explorer fetch failed: type={type}.', ['type' => get_class($exception)]);
            session()->setFlashdata('error', $message);
        }
        return redirect()->to(site_url('admin/property-explorer') . '?project=' . rawurlencode($project['slug']));
    }

    public function save()
    {
        if (!can('projects.view') || !can('projects.edit')) return admin_denied();
        $project = $this->project(true);
        if($this->request->getPost('parent')!==null)return $this->childSave($project);
        try {
            $draft = $this->draft($project['slug']);
            $id = $this->request->getPost('draft_id');
            if (!$draft || !is_string($id) || !hash_equals($draft['id'], $id)) throw new InvalidArgumentException('Builder draft expired or changed in another tab. Reload/fetch again.');
            $raw = $this->request->getPost('selection');
            if (!is_string($raw) || strlen($raw) > 1048576) throw new InvalidArgumentException('Select filters and submit a valid selection.');
            try { $selection = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); }
            catch (\JsonException $exception) { throw new InvalidArgumentException('Invalid filter selection.'); }
            if (!is_array($selection)) throw new InvalidArgumentException('Invalid filter selection.');
            $count = (new ExplorerFilterBuilder(db_connect()))->save($project['slug'], $selection, $draft, (int) admin_user()['id'], $this->request->getFiles());
            session()->setFlashdata('success', $count . ' selected filters saved. Unselected saved filters were preserved.');
        } catch (\Throwable $exception) {
            log_message('error', 'Explorer save failed: type={type}.', ['type' => get_class($exception)]);
            session()->setFlashdata('error', $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'No settings were committed. Check filter storage/audit and retry.');
        }
        return redirect()->to(site_url('admin/property-explorer') . '?project=' . rawurlencode($project['slug']));
    }

    public function image(string $id)
    {
        $path = \App\Services\ExplorerLogoStorage::serve($id);
        if ($path === null) throw PageNotFoundException::forPageNotFound();
        return $this->response->setHeader('Cache-Control', 'public, max-age=86400')
            ->setHeader('X-Content-Type-Options', 'nosniff')->setContentType($path['mime'])
            ->setBody((string) file_get_contents($path['path']));
    }

    private function childUrl(string $slug,string $parent): string
    {
        return site_url('admin/property-explorer').'?'.http_build_query(['project'=>$slug,'parent'=>$parent]);
    }
    private function childDraft(array $context): array
    {
        $draft=session()->get('explorer_children_draft');
        return is_array($draft)&&($draft['project']??null)===$context['project']&&($draft['parent']??null)===$context['parent_id']&&($draft['scope']??null)===$context['fingerprint']&&(int)($draft['expires']??0)>time()?$draft:[];
    }
    private function newChildDraft(array $context): array
    {
        return ['project'=>$context['project'],'parent'=>$context['parent_id'],'scope'=>$context['fingerprint'],'expires'=>time()+900,'id'=>bin2hex(random_bytes(16)),'field'=>$context['child_field'],'options'=>[],'catalog'=>[]];
    }
    private function childIndex(array $project)
    {
        $context=null;$rows=[];$draft=[];$error='';
        try{
            $parent=\App\Services\ExplorerChildBuilder::id($this->request->getGet('parent'));
            $builder=new \App\Services\ExplorerChildBuilder(db_connect());$context=$builder->context($project['slug'],$parent);
            $draft=$this->childDraft($context);
            if(!$draft){$draft=$this->newChildDraft($context);session()->set('explorer_children_draft',$draft);}
            $rows=$builder->rows($context,$draft);
        }catch(\Throwable$exception){$error=$exception instanceof InvalidArgumentException?$exception->getMessage():'Child filter context is unavailable. No saved settings were changed.';}
        return view('admin/projects/explorer',[
            'pageTitle'=>'Filter Builder','tab'=>'explorer-layer2','projects'=>db_connect()->table('portal_projects')->select('slug,name')->orderBy('name')->get()->getResultArray(),
            'project'=>$project,'scope'=>$context,'childContext'=>$context,'isChild'=>true,'rows'=>$rows,'draft'=>$draft,'metadataError'=>$error,'icons'=>LegacyExplorer::ICONS,'childSummaries'=>$this->summaries($project['slug']),
            'autoDiscover'=>$context&&can('projects.edit')&&$this->request->getGet('discover')==='1'&&!isset($draft['catalog']['fields']),
        ]);
    }
    private function childFetch(array $project)
    {
        try{$parent=\App\Services\ExplorerChildBuilder::id($this->request->getPost('parent'));}
        catch(InvalidArgumentException$exception){session()->setFlashdata('error',$exception->getMessage());return redirect()->to(site_url('admin/property-explorer').'?project='.rawurlencode($project['slug']));}
        try{
            $builder=new \App\Services\ExplorerChildBuilder(db_connect());$context=$builder->context($project['slug'],$parent);
            $draft=$this->childDraft($context)?:$this->newChildDraft($context);
            $client=new ExplorerErpClient(new \App\Models\ErpSourceModel(db_connect()),config('ErpConnections'));
            // Rediscover on every request; an old browser catalog never authorizes a removed field.
            $catalog=$builder->catalog($context,[$client,'fetchTree']);
            if($this->request->getPost('operation')==='copy'){
                $source=\App\Services\ExplorerChildBuilder::id($this->request->getPost('copy_source'));
                $copy=$builder->copyDraft($context,$source,$catalog,[$client,'fetchTree']);
                $draft=array_replace($draft,$copy);$draft['fetched_at']=date('Y-m-d H:i:s');
                $message=$copy['copied'].' options copied to this draft. '.$copy['kept'].' existing options kept unchanged; '.$copy['skipped'].' unavailable values skipped. Review and Save Selected Filters to apply. Only this child row is copied, not deeper descendants.';
            }elseif($this->request->getPost('operation')==='values'){
                $field=\App\Services\ExplorerChildBuilder::field($this->request->getPost('field'));
                $options=$builder->fetch($context,$field,$catalog,[$client,'fetchTree']);
                $draft['field']=$field;$draft['options']=$options;$draft['fetched_at']=date('Y-m-d H:i:s');
                $message=count($options).' child options fetched. New values remain unchecked and unsaved.';
            }else $message='Safe field catalog discovered for this source and complete ancestor branch.';
            $draft['catalog']=$catalog;$draft['expires']=time()+900;session()->set('explorer_children_draft',$draft);session()->setFlashdata('success',$message);
        }catch(\Throwable$exception){
            log_message('error','Explorer child fetch failed: type={type}.',['type'=>get_class($exception)]);
            session()->setFlashdata('error',$exception instanceof InvalidArgumentException||$exception instanceof \App\Exceptions\ExplorerMetadataException?$exception->getMessage():'Child metadata/schema inspection failed. Existing settings and drafts were preserved.');
        }
        return redirect()->to($this->childUrl($project['slug'],$parent));
    }
    private function childSave(array $project)
    {
        try{$parent=\App\Services\ExplorerChildBuilder::id($this->request->getPost('parent'));}
        catch(InvalidArgumentException$exception){session()->setFlashdata('error',$exception->getMessage());return redirect()->to(site_url('admin/property-explorer').'?project='.rawurlencode($project['slug']));}
        try{
            $builder=new \App\Services\ExplorerChildBuilder(db_connect());$context=$builder->context($project['slug'],$parent);$draft=$this->childDraft($context);
            $id=$this->request->getPost('draft_id');
            if(!$draft||!is_string($id)||!hash_equals($draft['id'],$id))throw new InvalidArgumentException('Child draft expired or another branch/ancestor changed. Reload and fetch again.');
            $raw=$this->request->getPost('selection');
            if(!is_string($raw)||strlen($raw)>1048576)throw new InvalidArgumentException('Invalid child selection.');
            $selection=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
            if(!is_array($selection))throw new InvalidArgumentException('Invalid child selection.');
            $count=$builder->save($project['slug'],$parent,$selection,$draft,(int)admin_user()['id'],$this->request->getFiles());
            session()->remove('explorer_children_draft');session()->setFlashdata('success',$count.' child filters saved. Unselected saved children were preserved. Public child navigation remains disabled in C1.');
        }catch(\Throwable$exception){
            log_message('error','Explorer child save failed: type={type}.',['type'=>get_class($exception)]);
            session()->setFlashdata('error',$exception instanceof InvalidArgumentException?$exception->getMessage():'No child settings were committed. Check storage/audit and retry.');
        }
        return redirect()->to($this->childUrl($project['slug'],$parent));
    }
}
