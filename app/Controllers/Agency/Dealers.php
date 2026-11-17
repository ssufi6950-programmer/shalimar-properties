<?php
namespace App\Controllers\Agency;

final class Dealers extends \App\Controllers\BaseController
{
    protected $helpers=['url','form'];

    public function index()
    {
        $agency=service('agencyPortalSession')->user();
        if(!$agency)return redirect()->to(url_to('agency.login'));
        $data=(new \App\Services\AgencyPortalDealers(db_connect()))->search((int)$agency['portal_agency_id'],$this->request->getGet());
        $data['logins']=(new \App\Services\DealerCredentials(db_connect()))->statuses((int)$agency['portal_agency_id'],array_column($data['rows'],'membership_id'));
        return view('agency/dealers/index',['pageTitle'=>'My Dealers','agency'=>$agency,'activeNav'=>'dealers']+$data);
    }
}
