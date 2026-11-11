<?php
namespace App\Services;

class AdminPermissionService
{
    public function __construct(private ?\CodeIgniter\Database\BaseConnection $db=null) {$this->db??=db_connect();}
    public function getUserRoles(int $userId): array
    {
        return $this->db->table('admin_roles r')->select('r.*')->join('admin_user_roles ur','ur.role_id=r.id')->join('admin_users u','u.id=ur.user_id')->where('u.id',$userId)->where('u.status','active')->where('r.is_active',1)->get()->getResultArray();
    }
    public function getUserPermissions(int $userId): array
    {
        return array_column($this->db->table('admin_permissions p')->distinct()->select('p.permission_key')->join('admin_role_permissions rp','rp.permission_id=p.id')->join('admin_roles r','r.id=rp.role_id')->join('admin_user_roles ur','ur.role_id=r.id')->join('admin_users u','u.id=ur.user_id')->where('u.id',$userId)->where('u.status','active')->where('r.is_active',1)->get()->getResultArray(),'permission_key');
    }
    public function hasPermission(int $userId,string $key): bool {return in_array($key,$this->getUserPermissions($userId),true);}
    public function hasAnyPermission(int $userId,array $keys): bool {return (bool)array_intersect($keys,$this->getUserPermissions($userId));}
    public function hasAllPermissions(int $userId,array $keys): bool {return !array_diff($keys,$this->getUserPermissions($userId));}
    public function isSuperAdmin(int $id): bool {return in_array('super_admin',array_column($this->getUserRoles($id),'slug'),true);}
    public function hasSuperAdminRole(int $id): bool
    {
        return $this->db->table('admin_user_roles ur')->join('admin_roles r','r.id=ur.role_id')->where('ur.user_id',$id)->where('r.slug','super_admin')->countAllResults()>0;
    }
}
