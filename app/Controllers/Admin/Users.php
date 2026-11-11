<?php
namespace App\Controllers\Admin;

use App\Services\AdminAccountService;
use App\Models\AdminUserModel;

class Users extends \App\Controllers\BaseController
{
    protected $helpers=['url','form','admin'];
    private function storageFailure(\Throwable $error)
    {
        log_message('error','Admin account operation failed ({type}).',['type'=>$error::class]);
        return $this->response->setStatusCode(500)->setJSON(['message'=>'The account change could not be saved. Please try again.','csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);
    }
    public function index()
    {
        if(!can('users.view'))return admin_denied();
        return view('admin/security/manage',['pageTitle'=>'Admin Users','tab'=>'users','kind'=>'users']);
    }
    private function publicUser(array $user): array
    {
        unset($user['password_hash']);
        $user['roles']=db_connect()->table('admin_roles r')->select('r.id,r.name,r.slug,r.is_active')->join('admin_user_roles ur','ur.role_id=r.id')->where('ur.user_id',$user['id'])->get()->getResultArray();
        return $user;
    }
    public function data()
    {
        if(!can('users.view'))return admin_denied();
        $db=db_connect();$query=$db->table('admin_users u')->select('u.id,u.name,u.username,u.email,u.status,u.must_change_password,u.last_login_at,u.created_at');
        $q=$this->request->getGet('q');if(is_string($q)&&$q!=='')$query->groupStart()->like('u.name',substr($q,0,120))->orLike('u.username',substr($q,0,120))->orLike('u.email',substr($q,0,120))->groupEnd();
        $status=$this->request->getGet('status');if(is_string($status)&&in_array($status,['active','inactive','suspended'],true))$query->where('u.status',$status);
        $role=filter_var($this->request->getGet('role'),FILTER_VALIDATE_INT);if($role)$query->join('admin_user_roles ur','ur.user_id=u.id')->where('ur.role_id',$role);
        $page=max(1,min(100000,(int)$this->request->getGet('page')));$total=(clone $query)->countAllResults();
        $rows=array_map(fn($row)=>$this->publicUser($row),$query->orderBy('u.id','DESC')->limit(15,($page-1)*15)->get()->getResultArray());
        return $this->response->setJSON(['rows'=>$rows,'total'=>$total,'page'=>$page,'perPage'=>15,'roles'=>$db->table('admin_roles')->orderBy('name')->get()->getResultArray(),'csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);
    }
    public function show(int $id)
    {
        if(!can('users.view'))return admin_denied();
        $user=(new AdminUserModel())->find($id);
        return $user?$this->response->setJSON(['record'=>$this->publicUser($user),'csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]):$this->response->setStatusCode(404)->setJSON(['message'=>'User not found.']);
    }
    private function save(?int $id)
    {
        if(!can($id?'users.edit':'users.create'))return admin_denied();
        try {
            $input=$this->request->getJSON(true);if(!is_array($input))throw new \DomainException('Invalid request.');
            $saved=(new AdminAccountService())->saveUser((int)admin_user()['id'],$id,$input);
            return $this->response->setJSON(['message'=>'User saved. Active sessions for an edited account have been revoked.','id'=>$saved,'csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);
        } catch(\App\Exceptions\AdminAuthorizationException $e){return admin_denied();}catch(\DomainException $e){return $this->response->setStatusCode(422)->setJSON(['message'=>$e->getMessage(),'csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);}
        catch(\Throwable $e){return $this->storageFailure($e);}
    }
    public function create(){return $this->save(null);}
    public function update(int $id){return $this->save($id);}
    public function status(int $id)
    {
        if(!can('users.deactivate'))return admin_denied();
        try{$input=$this->request->getJSON(true);if(!is_array($input)||!is_string($input['status']??null))throw new \DomainException('Select a valid status.');
            (new AdminAccountService())->setStatus((int)admin_user()['id'],$id,$input['status']);
            return $this->response->setJSON(['message'=>'Account status updated. Active sessions have been revoked.','csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);
        }catch(\App\Exceptions\AdminAuthorizationException $e){return admin_denied();}catch(\DomainException $e){return $this->response->setStatusCode(422)->setJSON(['message'=>$e->getMessage(),'csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);}
        catch(\Throwable $e){return $this->storageFailure($e);}
    }
    public function password(int $id)
    {
        if(!can('users.reset_password'))return admin_denied();
        try{$input=$this->request->getJSON(true);if(!is_array($input)||!is_string($input['password']??null)||!is_string($input['password_confirmation']??null))throw new \DomainException('Enter and confirm the new temporary password.');
            (new AdminAccountService())->resetPassword((int)admin_user()['id'],$id,$input['password'],$input['password_confirmation']);
            return $this->response->setJSON(['message'=>'Password reset. The user must change it on their next sign-in.','csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);
        }catch(\App\Exceptions\AdminAuthorizationException $e){return admin_denied();}catch(\DomainException $e){return $this->response->setStatusCode(422)->setJSON(['message'=>$e->getMessage(),'csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);}
        catch(\Throwable $e){return $this->storageFailure($e);}
    }
}
