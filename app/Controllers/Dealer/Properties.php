<?php
namespace App\Controllers\Dealer;

final class Properties extends \App\Controllers\BaseController
{
    protected $helpers=['url','form'];
    private function user(){return (new \App\Services\DealerPortalSession(db_connect()))->user();}
    public function dashboard()
    {
        $user=$this->user();if(!$user)return redirect()->to(site_url('dealer/login'));
        return view('dealer/properties',['pageTitle'=>'Dealer Dashboard','dealer'=>$user,'dashboard'=>true,'total'=>(new \App\Services\DealerProperties(db_connect()))->count($user),'rows'=>[]]);
    }
    public function index()
    {
        $user=$this->user();if(!$user)return redirect()->to(site_url('dealer/login'));$page=$this->request->getGet('page');
        return view('dealer/properties',['pageTitle'=>'My Assigned Properties','dealer'=>$user,'dashboard'=>false]+(new \App\Services\DealerProperties(db_connect()))->search($user,is_scalar($page)?(int)$page:1));
    }
    public function show(int $id)
    {
        $user=$this->user();if(!$user)return redirect()->to(site_url('dealer/login'));
        try{$row=(new \App\Services\DealerProperties(db_connect()))->find($user,$id);}catch(\DomainException $e){throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();}
        return view('dealer/detail',['pageTitle'=>'Assigned Property','dealer'=>$user,'listing'=>$row]);
    }
    public function media(int $id,int $index)
    {
        $user=$this->user();if(!$user)return $this->response->setStatusCode(404)->setBody('Image unavailable.');
        try{$file=(new \App\Services\DealerProperties(db_connect()))->media($user,$id,$index);return $this->response->setHeader('Cache-Control','no-store, private')->setHeader('X-Content-Type-Options','nosniff')->setContentType($file['mime'])->setBody(file_get_contents($file['path']));}
        catch(\DomainException|\App\Exceptions\CustomerFlowException $e){return $this->response->setStatusCode(404)->setBody('Image unavailable.');}
    }
}
