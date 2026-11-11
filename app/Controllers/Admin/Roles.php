<?php
namespace App\Controllers\Admin;
use App\Services\AdminAccountService;
class Roles extends \App\Controllers\BaseController
{
    protected $helpers=['url','form','admin'];
    public function index(){if(!can('roles.view'))return admin_denied();return view('admin/security/manage',['pageTitle'=>'Roles & Permissions','tab'=>'roles','kind'=>'roles']);}
    public function data()
    {
        if(!can('roles.view'))return admin_denied();
        $db=db_connect();$query=$db->table('admin_roles');$search=$this->request->getGet('q');
        if(is_string($search)&&$search!=='')$query->groupStart()->like('name',substr($search,0,100))->orLike('slug',substr($search,0,80))->groupEnd();
        $rows=$query->orderBy('is_system','DESC')->orderBy('name')->get()->getResultArray();
        foreach($rows as &$row){$row['permission_keys']=array_column($db->table('admin_permissions p')->select('p.permission_key')->join('admin_role_permissions rp','rp.permission_id=p.id')->where('rp.role_id',$row['id'])->get()->getResultArray(),'permission_key');$row['user_count']=$db->table('admin_user_roles')->where('role_id',$row['id'])->countAllResults();}
        return $this->response->setJSON(['rows'=>$rows,'total'=>count($rows),'permissions'=>$db->table('admin_permissions')->orderBy('module')->orderBy('action')->get()->getResultArray(),'csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);
    }
    public function show(int $id)
    {
        if(!can('roles.view'))return admin_denied();$db=db_connect();$row=$db->table('admin_roles')->where('id',$id)->get()->getRowArray();
        if(!$row)return $this->response->setStatusCode(404)->setJSON(['message'=>'Role not found.']);
        $row['permission_keys']=array_column($db->table('admin_permissions p')->select('p.permission_key')->join('admin_role_permissions rp','rp.permission_id=p.id')->where('rp.role_id',$id)->get()->getResultArray(),'permission_key');
        $row['assigned_users']=$db->table('admin_users u')->select('u.id,u.name,u.username,u.status')->join('admin_user_roles ur','ur.user_id=u.id')->where('ur.role_id',$id)->get()->getResultArray();
        return $this->response->setJSON(['record'=>$row,'csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);
    }
    private function save(?int $id)
    {
        if(!can($id?'roles.edit':'roles.create'))return admin_denied();
        try{$input=$this->request->getJSON(true);if(!is_array($input))throw new \DomainException('Invalid role request.');
            $saved=(new AdminAccountService())->saveRole((int)admin_user()['id'],$id,$input);
            return $this->response->setJSON(['id'=>$saved,'message'=>'Role saved. Sessions for assigned users have been revoked.','csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);
        }catch(\App\Exceptions\AdminAuthorizationException $e){return admin_denied();}catch(\DomainException $e){return $this->response->setStatusCode(422)->setJSON(['message'=>$e->getMessage(),'csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);}
    }
    public function create(){return $this->save(null);}
    public function update(int $id){return $this->save($id);}
    public function delete(int $id)
    {
        if(!can('roles.delete'))return admin_denied();
        try{(new AdminAccountService())->deleteRole((int)admin_user()['id'],$id);return $this->response->setJSON(['message'=>'Unused custom role deleted.','csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);}
        catch(\App\Exceptions\AdminAuthorizationException $e){return admin_denied();}catch(\DomainException $e){return $this->response->setStatusCode(422)->setJSON(['message'=>$e->getMessage(),'csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);}
    }
}
