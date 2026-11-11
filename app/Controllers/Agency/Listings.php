<?php
namespace App\Controllers\Agency;

use App\Controllers\BaseController;

final class Listings extends BaseController
{
    protected $helpers = ['url','form'];

    public function index()
    {
        $agency = service('agencyPortalSession')->user();
        if (!$agency) return redirect()->to(url_to('agency.login'));
        $data = service('agencyPortalListings')->search((int)$agency['portal_agency_id'], $this->request->getGet());
        return view('agency/listings/index', [
            'pageTitle' => 'My Active Listings', 'agency' => $agency, 'activeNav' => 'listings',
        ] + $data);
    }

    public function show(int $id)
    {
        $agency = service('agencyPortalSession')->user();
        if (!$agency) return redirect()->to(url_to('agency.login'));
        $listing = service('agencyPortalListings')->find((int)$agency['portal_agency_id'], $id);
        if (!$listing) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        return view('agency/listings/show', [
            'pageTitle' => 'Listing ' . $listing['public']['reference'],
            'agency' => $agency, 'activeNav' => 'listings', 'listing' => $listing,
        ]);
    }
}
