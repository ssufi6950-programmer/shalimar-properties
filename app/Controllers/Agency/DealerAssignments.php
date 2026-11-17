<?php
namespace App\Controllers\Agency;

final class DealerAssignments extends \App\Controllers\BaseController
{
    protected $helpers=['url','form'];

    public function action(int $listing,string $action)
    {
        $agency=service('agencyPortalSession')->user();
        if(!$agency)return $this->json(['message'=>'Your agency session expired. Please sign in again.'],401);
        $service=new \App\Services\AgencyDealerAssignments(db_connect());
        $id=(int)$agency['portal_agency_id'];
        try {
            if($action==='dealers'){
                $q=$this->request->getGet('q');$page=$this->request->getGet('page');
                return $this->json($service->options($id,$listing,is_string($q)?$q:'',is_scalar($page)?max(1,(int)$page):1));
            }
            if($action==='assignment')return $this->json($service->current($id,$listing));
            if($action==='history')return $this->json(['rows'=>$service->history($id,$listing)]);
            $data=$this->request->getPost();
            if(array_diff(array_keys($data),['membership_id','checkpoint',csrf_token()]))throw new \DomainException('Unexpected assignment fields. Reload and try again.');
            $checkpoint=$data['checkpoint']??null;
            if(!is_string($checkpoint)||!preg_match('/\A[a-f0-9]{64}\z/D',$checkpoint))throw new \DomainException('Reload assignment details before saving.');
            $membership=null;
            if($action==='assign'){
                $value=$data['membership_id']??null;
                if(!is_string($value)||!preg_match('/\A[1-9][0-9]{0,17}\z/D',$value))throw new \DomainException('Choose a dealer.');
                $membership=(int)$value;
            }
            $service->change($id,(int)$agency['id'],(int)$agency['auth_version'],$listing,$membership,$checkpoint);
            return $this->json(['message'=>$membership===null?'Dealer unassigned.':'Dealer assignment saved.']+$service->current($id,$listing));
        }catch(\DomainException $e){return $this->json(['message'=>$e->getMessage()],409);}
    }

    private function json(array $data,int $status=200)
    {
        return $this->response->setStatusCode($status)->setHeader('Cache-Control','no-store, private')->setJSON($data+['csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);
    }
}
