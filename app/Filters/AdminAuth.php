<?php
namespace App\Filters;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
class AdminAuth implements FilterInterface
{
    public function before(RequestInterface $request,$arguments=null)
    {
        helper(['url','admin']);
        service('response')->setHeader('Cache-Control','no-store, private');
        $path=trim($request->getUri()->getRoutePath(),'/');
        if($path==='admin/login')return null;
        $user=service('adminAuth')->user();
        $json=$request->isAJAX()||str_contains($request->getHeaderLine('Accept'),'application/json');
        if(!$user)return $json?service('response')->setStatusCode(401)->setJSON(['message'=>'Please sign in as an administrator.','login_url'=>url_to('admin.login')]):redirect()->route('admin.login');
        if($user['must_change_password']&&!in_array($path,['admin/change-password','admin/logout'],true))return $json?service('response')->setStatusCode(403)->setJSON(['message'=>'Change your temporary password before continuing.','code'=>'password_change_required','redirect_url'=>site_url('admin/change-password')]):redirect()->to(site_url('admin/change-password'));
        return(new AdminPermission())->before($request);
    }
    public function after(RequestInterface $request,ResponseInterface $response,$arguments=null){$response->setHeader('Cache-Control','no-store, private');}
}
