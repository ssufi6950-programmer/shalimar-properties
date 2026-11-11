<?php
namespace App\Models;

use CodeIgniter\Model;

class AdminUserModel extends Model
{
    protected $table='admin_users';
    protected $returnType='array';
    protected $allowedFields=['name','username','email','password_hash','status','must_change_password','auth_version','last_login_at','last_login_ip','password_changed_at','created_by','updated_by'];
    protected $useTimestamps=true;
    protected $afterFind=['hideSecrets'];
    protected function hideSecrets(array $event): array
    {
        if (!is_array($event['data'])) return $event;
        if ($event['singleton']??false) unset($event['data']['password_hash']);
        else foreach ($event['data'] as &$row) if (is_array($row)) unset($row['password_hash']);
        return $event;
    }
    public function credentialByIdentifier(string $identifier): ?array
    {
        return $this->db->table($this->table)->groupStart()->where('username',$identifier)->orWhere('email',$identifier)->groupEnd()->get()->getRowArray();
    }
    public function delete($id=null,bool $purge=false) {throw new \LogicException('Deactivate admin accounts to preserve their history.');}
}
