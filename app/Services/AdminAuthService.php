<?php
namespace App\Services;

use App\Models\AdminUserModel;

class AdminAuthService
{
    private ?array $current=null;
    private bool $loaded=false;
    public static function passwordHash(string $password): string
    {
        return password_hash($password,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_BCRYPT,defined('PASSWORD_ARGON2ID')?['memory_cost'=>65536,'time_cost'=>3,'threads'=>1]:['cost'=>12]);
    }
    public static function validatePassword(string $password): void
    {
        if (strlen($password)<12 || strlen($password)>72 || !preg_match('/[a-z]/',$password) || !preg_match('/[A-Z]/',$password) || !preg_match('/\d/',$password) || !preg_match('/[^A-Za-z0-9]/',$password)) throw new \DomainException('Use 12–72 characters with uppercase, lowercase, a number and a symbol.');
    }
    public function user(): ?array
    {
        if ($this->loaded) return $this->current;
        $this->loaded=true;$s=session();$id=(int)$s->get('admin_user_id');
        if (!$id) return null;
        $config=config('AdminSecurity');
        $row=(new AdminUserModel())->find($id);
        if (!$row || $row['status']!=='active' || (int)$row['auth_version']!==(int)$s->get('admin_auth_version')
            || (int)$s->get('admin_authenticated_at')<=time()-$config->maximumLifetime
            || (int)$s->get('admin_last_activity')<=time()-$config->idleTimeout) {
            $this->clear();return null;
        }
        $s->set('admin_last_activity',time());
        return $this->current=$row;
    }
    public function clear(): void
    {
        session()->remove(['admin_user_id','admin_auth_version','admin_authenticated_at','admin_last_activity','admin_authenticated','admin_username','admin_role','admin_permissions','admin_expires_at']);
        $this->loaded=true;$this->current=null;
    }
    public function attempt(string $identifier,string $password,string $ip): bool
    {
        $db=db_connect();$db->transBegin();
        try {
            // One protected-role row serializes login limits and security-sensitive account mutations.
            $db->query("SELECT id FROM admin_roles WHERE slug = 'super_admin' FOR UPDATE");
            $cfg=config('AdminSecurity');$since=date('Y-m-d H:i:s',time()-$cfg->loginWindow);
            $row=(new AdminUserModel())->credentialByIdentifier(strtolower(trim($identifier)));
            $ipHash=hash('sha256',$ip);$identifierHash=hash('sha256',$row['username']??strtolower(trim($identifier)));
            $blocked=$db->table('admin_login_attempts')->where('created_at >=',$since)->where('ip_hash',$ipHash)->countAllResults()>=$cfg->ipAttempts
                || $db->table('admin_login_attempts')->where('created_at >=',$since)->where('identifier_hash',$identifierHash)->countAllResults()>=$cfg->accountAttempts;
            // Fixed valid work-factor hash ensures unknown usernames still perform password verification.
            $dummy=defined('PASSWORD_ARGON2ID')?'$argon2id$v=19$m=65536,t=3,p=1$Tm5yMkhmMVJPcG1XWGM1Vg$y4O78t/qwQ0pDlTaxlNRkgzs8V//ymdUhu7h7WuT8oA':'$2y$12$obCB3sdXMfVEmwGyhBjaJ.3xBxAPOuleMZEJG8tPtFvlooY.wvn4i';
            $matches=password_verify($password,$row['password_hash']??$dummy);
            if ($blocked || !$row || $row['status']!=='active' || !$matches) {
                if (!$blocked && !$db->table('admin_login_attempts')->insert(['ip_hash'=>$ipHash,'identifier_hash'=>$identifierHash,'created_at'=>date('Y-m-d H:i:s')])) throw new \RuntimeException('Login security storage failed.');
                (new AdminAuditService($db))->record($blocked?'login.throttled':'login.failed','authentication');
                $db->transCommit();return false;
            }
            $changes=['last_login_at'=>date('Y-m-d H:i:s'),'last_login_ip'=>$ip];
            $algorithm=defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_BCRYPT;
            $options=defined('PASSWORD_ARGON2ID')?['memory_cost'=>65536,'time_cost'=>3,'threads'=>1]:['cost'=>12];
            if (password_needs_rehash($row['password_hash'],$algorithm,$options)) $changes['password_hash']=self::passwordHash($password);
            if (!$db->table('admin_users')->where('id',$row['id'])->update($changes)) throw new \RuntimeException('Login update failed.');
            // Clear this account's failed attempts only; keep IP failures from other identifiers.
            $db->table('admin_login_attempts')->where('identifier_hash',$identifierHash)->delete();
            (new AdminAuditService($db))->record('login.success','authentication',(int)$row['id']);
            if (!$db->transStatus()) throw new \RuntimeException('Login transaction failed.');
            $db->transCommit();
            $s=session();$s->regenerate(true);
            $s->set(['admin_user_id'=>(int)$row['id'],'admin_auth_version'=>(int)$row['auth_version'],'admin_authenticated_at'=>time(),'admin_last_activity'=>time()]);
            $this->loaded=false;$this->current=null;return true;
        } catch (\Throwable $e) {$db->transRollback();throw $e;}
    }
}
