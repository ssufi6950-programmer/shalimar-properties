<?php
namespace App\Services;

use DomainException;
use App\Exceptions\AdminAuthorizationException;

/** All identity/role writes share a transaction and lock the protected role before checking invariants. */
class AdminAccountService
{
    public function __construct(private ?\CodeIgniter\Database\BaseConnection $db=null) {$this->db??=db_connect();}
    public function transaction(callable $work): mixed
    {
        $this->db->transBegin();
        try {
            $this->db->query("SELECT id FROM admin_roles WHERE slug='super_admin' FOR UPDATE");
            $result=$work();
            $count=$this->db->query("SELECT COUNT(DISTINCT u.id) AS total FROM admin_users u JOIN admin_user_roles ur ON ur.user_id=u.id JOIN admin_roles r ON r.id=ur.role_id WHERE u.status='active' AND r.slug='super_admin' AND r.is_active=1")->getRowArray();
            if ((int)$count['total']<1) throw new DomainException('At least one active Super Admin must remain.');
            if (!$this->db->transStatus()) throw new \RuntimeException('The security change could not be saved.');
            $this->db->transCommit();return $result;
        } catch (\Throwable $e) {$this->db->transRollback();throw $e;}
    }
    private function actor(int $id,string $permission): AdminPermissionService
    {
        $p=new AdminPermissionService($this->db);
        if (!$p->hasPermission($id,$permission)) throw new AdminAuthorizationException('You do not have permission for this action.');
        return $p;
    }
    public function saveUser(int $actor,?int $id,array $input): int
    {
        return $this->transaction(function()use($actor,$id,$input) {
            $p=$this->actor($actor,$id?'users.edit':'users.create');$super=$p->isSuperAdmin($actor);
            $old=$id?$this->db->table('admin_users')->where('id',$id)->get()->getRowArray():null;
            if ($id&&!$old) throw new DomainException('User not found.');
            if ($id&&$p->hasSuperAdminRole($id)&&!$super) throw new AdminAuthorizationException('Only a Super Admin can manage this account.');
            $data=[];
            foreach (['name','username','email','status'] as $field) {if(isset($input[$field])&&!is_string($input[$field]))throw new DomainException('Invalid user fields.');$data[$field]=trim($input[$field]??'');}
            $data['username']=strtolower($data['username']);$data['email']=strtolower($data['email'])?:null;
            if (!$data['name']||mb_strlen($data['name'])>120||!preg_match('/\A[a-z][a-z0-9_.-]{2,59}\z/',$data['username'])||($data['email']!==null&&(!filter_var($data['email'],FILTER_VALIDATE_EMAIL)||strlen($data['email'])>190))||!in_array($data['status'],['active','inactive','suspended'],true)) throw new DomainException('Enter a name, valid username/email and account status.');
            if ($this->db->table('admin_users')->where('username',$data['username'])->where('id !=',$id??0)->countAllResults()||($data['email']!==null&&$this->db->table('admin_users')->where('email',$data['email'])->where('id !=',$id??0)->countAllResults())) throw new DomainException('Username or email is already in use.');
            if ($old&&$old['status']!==$data['status']&&!$p->hasPermission($actor,'users.deactivate')) throw new AdminAuthorizationException('Account status permission is required.');
            $roles=null;
            if (array_key_exists('role_ids',$input)) {
                if (!$p->hasPermission($actor,'users.assign_roles')) throw new AdminAuthorizationException('Role assignment permission is required.');
                if ($id===$actor&&!$super) throw new AdminAuthorizationException('You cannot change your own assigned roles.');
                $roles=$this->validateRoles($input['role_ids'],$p,$actor,$super);
            }
            if (!$id) {
                $password=$input['password']??'';if(!is_string($password)||$password!==($input['password_confirmation']??null)) throw new DomainException('Passwords must match.');
                AdminAuthService::validatePassword($password);$data['password_hash']=AdminAuthService::passwordHash($password);
                $data['must_change_password']=1;$data['created_by']=$actor;$data['created_at']=date('Y-m-d H:i:s');
            }
            $data['updated_by']=$actor;$data['updated_at']=date('Y-m-d H:i:s');
            if ($old) $data['auth_version']=(int)$old['auth_version']+1;
            $ok=$id?$this->db->table('admin_users')->where('id',$id)->update($data):$this->db->table('admin_users')->insert($data);
            if (!$ok) throw new \RuntimeException('User write failed.');
            $id??=(int)$this->db->insertID();
            if ($roles!==null) {
                $oldRoles=array_map('intval',array_column($this->db->table('admin_user_roles')->where('user_id',$id)->get()->getResultArray(),'role_id'));
                $this->db->table('admin_user_roles')->where('user_id',$id)->delete();
                foreach ($roles as $role) if(!$this->db->table('admin_user_roles')->insert(['user_id'=>$id,'role_id'=>$role])) throw new \RuntimeException('Role assignment failed.');
                (new AdminAuditService($this->db))->record('user.roles_changed','users',$actor,'user',$id,['role_ids'=>$oldRoles],['role_ids'=>$roles]);
            }
            (new AdminAuditService($this->db))->record($old?'user.updated':'user.created','users',$actor,'user',$id,$old,$data);
            return $id;
        });
    }
    private function validateRoles(mixed $ids,AdminPermissionService $p,int $actor,bool $super): array
    {
        if (!is_array($ids)||count($ids)>30) throw new DomainException('Select valid roles.');
        $roles=[];
        foreach ($ids as $id) {
            if (!filter_var($id,FILTER_VALIDATE_INT)||$id<1) throw new DomainException('Select valid roles.');
            $role=$this->db->table('admin_roles')->where('id',$id)->where('is_active',1)->get()->getRowArray();
            if (!$role) throw new DomainException('This role cannot be assigned.');
            if(!$super&&$role['slug']==='super_admin')throw new AdminAuthorizationException('This role cannot be assigned.');
            $keys=array_column($this->db->table('admin_permissions p')->select('p.permission_key')->join('admin_role_permissions rp','rp.permission_id=p.id')->where('rp.role_id',$id)->get()->getResultArray(),'permission_key');
            if (!$super&&!$p->hasAllPermissions($actor,$keys)) throw new AdminAuthorizationException('You cannot assign privileges outside your own access.');
            $roles[]=(int)$id;
        }
        return array_values(array_unique($roles));
    }
    public function resetPassword(int $actor,int $id,string $password,string $confirmation): void
    {
        $this->transaction(function()use($actor,$id,$password,$confirmation) {
            $p=$this->actor($actor,'users.reset_password');
            if ($p->hasSuperAdminRole($id)&&!$p->isSuperAdmin($actor)) throw new AdminAuthorizationException('Only a Super Admin can reset this account.');
            if($id===$actor)throw new DomainException('Use Change Password for your own account.');
            if($password!==$confirmation)throw new DomainException('Passwords must match.');
            AdminAuthService::validatePassword($password);
            if(!$this->db->table('admin_users')->where('id',$id)->countAllResults())throw new DomainException('User not found.');
            $this->db->table('admin_users')->where('id',$id)->set('auth_version','auth_version+1',false)->update(['password_hash'=>AdminAuthService::passwordHash($password),'must_change_password'=>1,'password_changed_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),'updated_by'=>$actor]);
            (new AdminAuditService($this->db))->record('user.password_reset','users',$actor,'user',$id);
        });
    }
    public function setStatus(int $actor,int $id,string $status): void
    {
        $this->transaction(function()use($actor,$id,$status){
            $p=$this->actor($actor,'users.deactivate');
            if(!in_array($status,['active','inactive','suspended'],true))throw new DomainException('Invalid account status.');
            if($p->hasSuperAdminRole($id)&&!$p->isSuperAdmin($actor))throw new AdminAuthorizationException('Only a Super Admin can manage this account.');
            $old=$this->db->table('admin_users')->where('id',$id)->get()->getRowArray();if(!$old)throw new DomainException('User not found.');
            $this->db->table('admin_users')->where('id',$id)->set('auth_version','auth_version+1',false)->update(['status'=>$status,'updated_at'=>date('Y-m-d H:i:s'),'updated_by'=>$actor]);
            (new AdminAuditService($this->db))->record('user.status_changed','users',$actor,'user',$id,$old,['status'=>$status]);
        });
    }
    public function saveRole(int $actor,?int $id,array $input): int
    {
        return $this->transaction(function()use($actor,$id,$input) {
            $p=$this->actor($actor,$id?'roles.edit':'roles.create');$super=$p->isSuperAdmin($actor);
            $old=$id?$this->db->table('admin_roles')->where('id',$id)->get()->getRowArray():null;
            if($id&&!$old)throw new DomainException('Role not found.');
            if($old)$old['permission_keys']=array_column($this->db->table('admin_permissions p')->select('p.permission_key')->join('admin_role_permissions rp','rp.permission_id=p.id')->where('rp.role_id',$id)->get()->getResultArray(),'permission_key');
            if($old&&$old['slug']==='super_admin')throw new DomainException('This protected system role cannot be changed.');
            if($old&&$old['is_system']&&!$super)throw new AdminAuthorizationException('This protected system role cannot be changed.');
            foreach(['name','slug','description']as$field)if(isset($input[$field])&&!is_string($input[$field]))throw new DomainException('Invalid role fields.');
            $data=['name'=>trim($input['name']??''),'slug'=>strtolower(trim($input['slug']??'')),'description'=>trim($input['description']??''),'is_active'=>filter_var($input['is_active']??1,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE)];
            if(!$data['name']||strlen($data['name'])>100||!preg_match('/\A[a-z][a-z0-9_-]{2,79}\z/',$data['slug'])||strlen($data['description'])>500||$data['is_active']===null)throw new DomainException('Enter a valid role name, slug, description and status.');
            if($old&&$old['is_system']&&$data['slug']!==$old['slug'])throw new DomainException('System role slugs cannot change.');
            if($this->db->table('admin_roles')->where('slug',$data['slug'])->where('id !=',$id??0)->countAllResults())throw new DomainException('Role slug is already in use.');
            $keys=null;
            if(array_key_exists('permission_keys',$input)) {
                if(!$p->hasPermission($actor,'roles.manage_permissions'))throw new AdminAuthorizationException('Permission management access is required.');
                $keys=$input['permission_keys'];
                if(!is_array($keys)||count($keys)>200||array_filter($keys,fn($k)=>!is_string($k)))throw new DomainException('Invalid permissions.');
                $known=array_column($this->db->table('admin_permissions')->get()->getResultArray(),'permission_key');
                if(array_diff($keys,$known))throw new DomainException('Unknown permission keys.');
                if(!$super&&!$p->hasAllPermissions($actor,$keys))throw new AdminAuthorizationException('You cannot grant these permissions.');
                $keys=array_values(array_unique($keys));
            }
            $data['is_active']=(int)$data['is_active'];$data['updated_at']=date('Y-m-d H:i:s');
            if(!$id){$data['is_system']=0;$data['created_at']=$data['updated_at'];}
            $ok=$id?$this->db->table('admin_roles')->where('id',$id)->update($data):$this->db->table('admin_roles')->insert($data);
            if(!$ok)throw new \RuntimeException('Role write failed.');
            $id??=(int)$this->db->insertID();
            if($keys!==null) {
                $this->db->table('admin_role_permissions')->where('role_id',$id)->delete();
                if($keys)foreach($this->db->table('admin_permissions')->whereIn('permission_key',$keys)->get()->getResultArray()as$perm)$this->db->table('admin_role_permissions')->insert(['role_id'=>$id,'permission_id'=>$perm['id']]);
            }
            $this->db->query('UPDATE admin_users SET auth_version=auth_version+1 WHERE id IN (SELECT user_id FROM admin_user_roles WHERE role_id=?)',[$id]);
            (new AdminAuditService($this->db))->record($old?'role.updated':'role.created','roles',$actor,'role',$id,$old,$data+['permission_keys'=>$keys??[]]);
            return $id;
        });
    }
    public function deleteRole(int $actor,int $id): void
    {
        $this->transaction(function()use($actor,$id){
            $this->actor($actor,'roles.delete');$old=$this->db->table('admin_roles')->where('id',$id)->get()->getRowArray();
            if(!$old||$old['is_system']||$this->db->table('admin_user_roles')->where('role_id',$id)->countAllResults())throw new DomainException('Only unused custom roles can be deleted.');
            $this->db->table('admin_role_permissions')->where('role_id',$id)->delete();$this->db->table('admin_roles')->where('id',$id)->delete();
            (new AdminAuditService($this->db))->record('role.deleted','roles',$actor,'role',$id,$old);
        });
    }
}
