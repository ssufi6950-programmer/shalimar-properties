<?php
namespace App\Controllers\Admin;
class Auth extends \App\Controllers\BaseController
{
    protected $helpers=['url','form','admin'];
    public function login()
    {
        if(admin_user())return redirect()->to(site_url('admin/profile'));
        return view('admin/auth/login',['error'=>session()->getFlashdata('login_error'),'bootstrapNeeded'=>db_connect()->table('admin_users')->countAllResults()===0]);
    }
    public function authenticate()
    {
        $identifier=$this->request->getPost('username');$password=$this->request->getPost('password');
        if(!is_string($identifier)||!is_string($password)||strlen($identifier)>190||strlen($password)>4096)return redirect()->route('admin.login')->with('login_error','Unable to sign in. Check your credentials or try again later.');
        try{$ok=service('adminAuth')->attempt($identifier,$password,$this->request->getIPAddress());}
        catch(\Throwable $e){log_message('error','Admin authentication storage failure ({type}).',['type'=>$e::class]);$ok=false;}
        if(!$ok)return redirect()->route('admin.login')->with('login_error','Unable to sign in. Check your credentials or try again later.');
        $user=admin_user();return redirect()->to(site_url($user['must_change_password']?'admin/change-password':(can('dashboard.view')?'admin/dashboard':'admin/profile')));
    }
    public function logout()
    {
        $user=admin_user();(new \App\Services\AdminAuditService())->record('logout','authentication',(int)$user['id']);
        session()->destroy();return redirect()->route('admin.login');
    }
}
