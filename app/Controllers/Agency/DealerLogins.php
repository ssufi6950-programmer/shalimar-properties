<?php
namespace App\Controllers\Agency;

final class DealerLogins extends \App\Controllers\BaseController
{
    protected $helpers=['url','form'];
    public function action(int $membership,string $action='status')
    {
        $user=service('agencyPortalSession')->user();if(!$user)return $this->json(['message'=>'Agency session expired.'],401);
        $service=new \App\Services\DealerCredentials(db_connect());
        try{
            if($action==='status')return $this->json($service->status((int)$user['portal_agency_id'],$membership));
            $data=$this->request->getPost();if(array_diff(array_keys($data),['checkpoint','username',csrf_token()]))throw new \DomainException('Unexpected credential fields.');
            $checkpoint=$data['checkpoint']??null;$name=$data['username']??'';
            if(!is_string($checkpoint)||!preg_match('/\A[a-f0-9]{64}\z/D',$checkpoint)||!is_string($name)||strlen($name)>100)throw new \DomainException('Reload login status and check the username.');
            return $this->json($service->mutate((int)$user['portal_agency_id'],$membership,['role'=>'agency','id'=>(int)$user['id'],'auth_version'=>(int)$user['auth_version']],$action,$checkpoint,['username'=>$name]));
        }catch(\DomainException $e){return $this->json(['message'=>$e->getMessage()],409);}
    }
    private function json(array $data,int $status=200){return $this->response->setStatusCode($status)->setHeader('Cache-Control','no-store, private')->setHeader('Pragma','no-cache')->setHeader('Referrer-Policy','no-referrer')->setJSON($data+['csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);}
}
