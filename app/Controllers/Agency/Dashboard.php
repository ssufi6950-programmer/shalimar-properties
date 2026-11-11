<?php
namespace App\Controllers\Agency;

use App\Controllers\BaseController;

final class Dashboard extends BaseController
{
    protected $helpers = ['url'];

    public function index()
    {
        $agency = service('agencyPortalSession')->user();
        if (!$agency) return redirect()->to(url_to('agency.login'));
        $count = service('agencyPortalListings')->count((int)$agency['portal_agency_id']);
        return view('agency/dashboard/index', [
            'pageTitle' => 'Agency Dashboard', 'agency' => $agency, 'activeNav' => 'dashboard',
            'activeListings' => $count,
        ]);
    }
}
