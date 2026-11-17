<?php
namespace App\Controllers\Dealer;

final class Auth extends \App\Controllers\BaseController
{
    protected $helpers=['url','form'];
    private function sessions(){return new \App\Services\DealerPortalSession(db_connect());}
    public function login(){return view('dealer/auth',['pageTitle'=>'Dealer Sign In','mode'=>'login','dealer'=>null,'error'=>session()->getFlashdata('dealer_error')]);}
    public function authenticate()
    {
        $name=$this->request->getPost('username');$password=$this->request->getPost('password');$ok=false;
        try{if(is_string($name)&&is_string($password)&&strlen($name)<=100&&strlen($password)<=4096)$ok=$this->sessions()->attempt($name,$password,$this->request->getIPAddress());}
        catch(\Throwable $e){log_message('error','Dealer sign-in unavailable ({type}). Check dedicated dealer rate-limit key and security storage.',['type'=>get_class($e)]);}
        if(!$ok)return redirect()->to(site_url('dealer/login'))->with('dealer_error','Unable to sign in. Check your credentials or ask your agency for assistance.');
        $user=$this->sessions()->user();return redirect()->to(site_url(!empty($user['must_change_password'])?'dealer/change-password':'dealer/dashboard'));
    }
    public function password(){return view('dealer/auth',['pageTitle'=>'Change Dealer Password','mode'=>'password','dealer'=>$this->sessions()->user(),'error'=>session()->getFlashdata('dealer_error')]);}
    public function changePassword()
    {
        $user=$this->sessions()->user();if(!$user)return redirect()->to(site_url('dealer/login'));
        $input=[];foreach(['current_password','password','confirmation'] as $field){$value=$this->request->getPost($field);if(!is_string($value)||strlen($value)>4096)return redirect()->to(site_url('dealer/change-password'))->with('dealer_error','Enter valid password fields.');$input[$field]=$value;}
        try{
            $result=(new \App\Services\DealerCredentials(db_connect()))->mutate((int)$user['portal_agency_id'],(int)$user['agency_dealer_membership_id'],['role'=>'dealer','id'=>(int)$user['id'],'auth_version'=>(int)$user['auth_version']],'password',\App\Services\DealerCredentials::checkpoint($user),$input);
            session()->regenerate(true);session()->set('dealer_auth_version',(int)$result['account']['auth_version']);
            return redirect()->to(site_url('dealer/dashboard'));
        }catch(\DomainException $e){return redirect()->to(site_url('dealer/change-password'))->with('dealer_error',$e->getMessage());}
    }
    public function logout(){$this->sessions()->logout();session()->regenerate(true);return redirect()->to(site_url('dealer/login'));}
}
