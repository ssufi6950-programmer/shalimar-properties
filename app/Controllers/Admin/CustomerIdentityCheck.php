<?php
namespace App\Controllers\Admin;

use App\Libraries\CustomerIdentity;

final class CustomerIdentityCheck extends \App\Controllers\BaseController
{
    protected $helpers=['admin','url','form'];

    public function index()
    {
        if(!can('customers.edit'))return admin_denied();
        return $this->page();
    }

    public function check()
    {
        if(!can('customers.edit'))return admin_denied();
        $response=$this->response->setHeader('Cache-Control','no-store, private')->setHeader('Pragma','no-cache')->setHeader('Referrer-Policy','no-referrer');
        try {
            $cnic=CustomerIdentity::cnic((string)$this->request->getPost('cnic'));
            $mobile=CustomerIdentity::mobile((string)$this->request->getPost('mobile'));
            $sources=(new \App\Services\CustomerErpApi(config('CustomerPortal')))->diagnoseIdentities($cnic,$mobile);
            return $response->setBody($this->page($sources,null));
        } catch(\InvalidArgumentException $error) {
            return $response->setStatusCode(422)->setBody($this->page([], $error->getMessage()));
        } catch(\Throwable $error) {
            log_message('error','Admin customer identity diagnostic failed ({type}).',['type'=>get_class($error)]);
            return $response->setStatusCode(503)->setBody($this->page([], 'The identity check could not be completed. Check the portal error log.'));
        }
    }

    private function page(array $sources=[],?string $error=null):string
    {
        return view('admin/customers/identity_check',['pageTitle'=>'Customer Identity Check','tab'=>'customers','sources'=>$sources,'error'=>$error]);
    }
}
