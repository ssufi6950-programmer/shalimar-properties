<?php
namespace App\Filters;

final class DealerPortalAccess implements \CodeIgniter\Filters\FilterInterface
{
    public function before(\CodeIgniter\HTTP\RequestInterface $request,$arguments=null)
    {
        helper('url');$response=service('response');$response->setHeader('Cache-Control','no-store, private')->setHeader('Referrer-Policy','no-referrer');
        $user=(new \App\Services\DealerPortalSession(db_connect()))->user();
        $path=trim($request->getUri()->getRoutePath(),'/');
        if($path==='dealer/login')return $user?redirect()->to(site_url($user['must_change_password']?'dealer/change-password':'dealer/dashboard')):null;
        if(!$user)return redirect()->to(site_url('dealer/login'));
        if((int)$user['must_change_password']&&!in_array($path,['dealer/change-password','dealer/logout'],true))return redirect()->to(site_url('dealer/change-password'));
        return null;
    }
    public function after(\CodeIgniter\HTTP\RequestInterface $request,\CodeIgniter\HTTP\ResponseInterface $response,$arguments=null)
    {$response->setHeader('Cache-Control','no-store, private')->setHeader('Referrer-Policy','no-referrer')->setHeader('Pragma','no-cache');}
}
