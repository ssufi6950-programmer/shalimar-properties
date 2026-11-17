<?php
namespace App\Controllers\Admin;

final class DealerCredentials extends \App\Controllers\BaseController
{
    protected $helpers=['admin','url','form'];
    public function activity()
    {
        if(!can('audit_logs.view'))return admin_denied();
        return view('admin/dealers/activity',['pageTitle'=>'Dealer Credential Activity','tab'=>'dealer-activity']+(new \App\Services\DealerCredentialHistory(db_connect()))->search($this->request->getGet()));
    }
    private function context(int $membership): array
    {
        $row=db_connect()->table('agency_dealer_memberships')->select('portal_agency_id')->where('id',$membership)->get()->getRowArray();
        if(!$row)throw new \DomainException('Membership unavailable.');return (new \App\Services\DealerCredentials(db_connect()))->membership((int)$row['portal_agency_id'],$membership);
    }
    public function show(int $membership)
    {
        if(!can('agencies.view')||!can('agencies.edit'))return admin_denied();
        try{$member=$this->context($membership);}catch(\DomainException $e){throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();}
        return $this->response->setHeader('Cache-Control','no-store, private')->setHeader('Referrer-Policy','no-referrer')->setBody(view('admin/dealers/manage',['pageTitle'=>'Dealer Login Recovery','tab'=>'agencies','member'=>$member,'adminLoginManagement'=>can('agencies.deactivate'),'loginBase'=>site_url('admin/dealer-logins')]));
    }
    public function action(int $membership,string $action='status')
    {
        if(!can('agencies.view')||!can('agencies.edit')||($action==='revoke'&&!can('agencies.deactivate')))return admin_denied();
        try{
            $member=$this->context($membership);$service=new \App\Services\DealerCredentials(db_connect());$agency=(int)$member['portal_agency_id'];
            if($action==='status')return $this->json($service->status($agency,$membership));
            $data=$this->request->getPost();if(array_diff(array_keys($data),['username','checkpoint',csrf_token()]))throw new \DomainException('Unexpected credential fields.');
            if(!is_string($data['checkpoint']??null)||!preg_match('/\A[a-f0-9]{64}\z/D',$data['checkpoint'])||!is_string($data['username']??'')||strlen($data['username']??'')>100)throw new \DomainException('Reload login status and check your input.');
            $actor=admin_user();return $this->json($service->mutate($agency,$membership,['role'=>'admin','id'=>(int)$actor['id'],'auth_version'=>(int)$actor['auth_version']],$action,$data['checkpoint'],['username'=>$data['username']??'']));
        }catch(\DomainException $e){return $this->json(['message'=>$e->getMessage()],409);}
    }
    private function json(array $data,int $status=200){return $this->response->setStatusCode($status)->setHeader('Cache-Control','no-store, private')->setHeader('Pragma','no-cache')->setHeader('Referrer-Policy','no-referrer')->setJSON($data+['csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);}
}
