<?php
namespace App\Controllers\Admin;
use App\Services\AdminAuthService;
use App\Services\AdminAuditService;
class Profile extends \App\Controllers\BaseController
{
    protected $helpers=['url','form','admin'];
    public function index(){return view('admin/security/profile',['pageTitle'=>'My Profile','tab'=>'profile','passwordOnly'=>false,'error'=>session()->getFlashdata('profile_error')]);}
    public function password(){return view('admin/security/profile',['pageTitle'=>'Change Password','tab'=>'profile','passwordOnly'=>true,'error'=>session()->getFlashdata('profile_error')]);}
    public function update()
    {
        $user=admin_user();$name=$this->request->getPost('name');$email=$this->request->getPost('email');
        if(!is_string($name)||!is_string($email)||!trim($name)||mb_strlen($name)>120||(trim($email)!==''&&(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190)))return redirect()->to(site_url('admin/profile'))->with('profile_error','Enter a name and valid email.');
        $email=strtolower(trim($email))?:null;$db=db_connect();
        if($email&&$db->table('admin_users')->where('email',$email)->where('id !=',$user['id'])->countAllResults())return redirect()->to(site_url('admin/profile'))->with('profile_error','Email is already in use.');
        $db->transStart();$values=['name'=>trim($name),'email'=>$email,'updated_at'=>date('Y-m-d H:i:s')];$db->table('admin_users')->where('id',$user['id'])->update($values);
        (new AdminAuditService($db))->record('profile.updated','users',(int)$user['id'],'user',$user['id'],$user,$values);$db->transComplete();
        if(!$db->transStatus())throw new \RuntimeException('Profile change could not be saved.');
        return redirect()->to(site_url('admin/profile'))->with('profile_success','Profile updated.');
    }
    public function changePassword()
    {
        $user=admin_user();$current=$this->request->getPost('current_password');$new=$this->request->getPost('password');$confirmation=$this->request->getPost('password_confirmation');
        try {
            if(!is_string($current)||!is_string($new)||!is_string($confirmation)||$new!==$confirmation)throw new \DomainException('Enter your current password and matching new passwords.');
            $db=db_connect();$db->transBegin();
            $row=$db->query('SELECT * FROM admin_users WHERE id=? FOR UPDATE',[$user['id']])->getRowArray();
            if(!$row||!password_verify($current,$row['password_hash']))throw new \DomainException('Current password is incorrect.');
            AdminAuthService::validatePassword($new);if(password_verify($new,$row['password_hash']))throw new \DomainException('Choose a different new password.');
            $values=['password_hash'=>AdminAuthService::passwordHash($new),'must_change_password'=>0,'password_changed_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),'auth_version'=>(int)$row['auth_version']+1];
            if(!$db->table('admin_users')->where('id',$user['id'])->update($values))throw new \RuntimeException('Password update failed.');
            (new AdminAuditService($db))->record('password.changed','authentication',(int)$user['id'],'user',$user['id']);
            if(!$db->transStatus())throw new \RuntimeException('Password transaction failed.');$db->transCommit();
            session()->regenerate(true);session()->set('admin_auth_version',$values['auth_version']);
            return redirect()->to(site_url('admin/profile'))->with('profile_success','Password changed. Other sessions have been revoked.');
        }catch(\DomainException $e){if(isset($db))$db->transRollback();return redirect()->to(site_url('admin/change-password'))->with('profile_error',$e->getMessage());}
        catch(\Throwable $e){if(isset($db))$db->transRollback();log_message('error','Password change storage failure ({type}).',['type'=>$e::class]);return redirect()->to(site_url('admin/change-password'))->with('profile_error','The password could not be changed. Please try again.');}
    }
}
