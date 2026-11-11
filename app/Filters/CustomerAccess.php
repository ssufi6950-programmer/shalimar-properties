<?php
namespace App\Filters;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
final class CustomerAccess implements FilterInterface
{
 public function before(RequestInterface $request,$arguments=null)
 {
  if(!config('CustomerPortal')->enabled)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
  $path=trim($request->getUri()->getRoutePath(),'/');$public=['customer/login','customer/register','customer/verify-otp','customer/otp/request','customer/otp/verify','customer/otp/resend'];
  if(in_array($path,$public,true))return null;
  if(service('customerSession')->user())return null;
   if($path==='customer/post-property')session()->set('customer_resale_entry',true);
  $response=service('response')->setHeader('Cache-Control','no-store, private');
  if($request->isAJAX()||str_contains($request->getHeaderLine('Accept'),'application/json'))return $response->setStatusCode(401)->setJSON(['message'=>'Please sign in to view your properties.','login'=>url_to('customer.login')]);
  return redirect()->to(url_to('customer.login'));
 }
 public function after(RequestInterface $request,ResponseInterface $response,$arguments=null){$response->setHeader('Cache-Control','no-store, private');$response->setHeader('Referrer-Policy','same-origin');}
}
