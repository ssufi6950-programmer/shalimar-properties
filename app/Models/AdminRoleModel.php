<?php
namespace App\Models;
class AdminRoleModel extends \CodeIgniter\Model
{
    protected $table='admin_roles';
    protected $returnType='array';
    protected $allowedFields=['name','slug','description','is_system','is_active'];
    protected $useTimestamps=true;
}
