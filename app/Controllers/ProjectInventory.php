<?php
declare(strict_types=1);
namespace App\Controllers;

final class ProjectInventory extends BaseController
{
    private function project(string $slug): array
    {
        if (!\App\Services\ProjectInventoryService::enabled()) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $pages=new \App\Services\PublicProjectPages(db_connect());
        $project=$pages->find($slug,true);
        if(!$project)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        return $project;
    }
    public function overview(string $slug): string
    {
        $project=$this->project($slug);
        return $this->renderInventoryPage($project, false);
    }
    public function map(string $slug): string
    {
        $project=$this->project($slug);
        return $this->renderInventoryPage($project, true);
    }

    private function renderInventoryPage(array $project, bool $mapMode): string
    {
        // The shared legacy site header is rendered inside CI4. Prevent it from
        // starting a second PHP session or replacing CI's request error handler.
        if (!defined('PORTAL_CI_RENDER')) define('PORTAL_CI_RENDER', true);
        return view('public/project_inventory',[
            'project'=>$project,
            'mapMode'=>$mapMode,
            'dataUrl'=>site_url('api/project-inventory/'.rawurlencode($project['slug'])),
        ]);
    }
    public function data(string $slug)
    {
        try {
            $project=$this->project($slug);
            $service=new \App\Services\ProjectInventoryService(db_connect(),new \App\Services\ProjectInventoryTransport(new \App\Models\ErpSourceModel(db_connect()),config('ErpConnections')));
            return $this->response->setJSON($service->load($project['slug']));
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) { throw $e; }
        catch (\Throwable $e) {
            log_message('error','Public project inventory unavailable: slug={slug}, type={type}',['slug'=>$slug,'type'=>get_class($e)]);
            return $this->response->setStatusCode(503)->setJSON(['available'=>false,'message'=>'Verified inventory is temporarily unavailable. Refresh to try again.']);
        }
    }
}
