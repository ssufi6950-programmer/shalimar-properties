<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class AgencyPortalAccess implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $path = trim($request->getUri()->getRoutePath(), '/');
        service('response')->setHeader('Cache-Control', 'no-store, private');
        if ($path === 'agency/login') {
            $user = service('agencyPortalSession')->user();
            if ($user) return redirect()->to(site_url(!empty($user['must_change_password']) ? 'agency/change-password' : 'agency/dashboard'));
            return null;
        }
        $user = service('agencyPortalSession')->user();
        if (!$user) return redirect()->to(url_to('agency.login'));
        if (!empty($user['must_change_password']) && !in_array($path, ['agency/change-password','agency/logout'], true)) {
            return redirect()->to(site_url('agency/change-password'));
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('Cache-Control', 'no-store, private')->setHeader('Referrer-Policy', 'same-origin');
    }
}
