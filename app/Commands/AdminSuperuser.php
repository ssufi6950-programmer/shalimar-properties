<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\AdminAuthService;
use App\Services\AdminAuditService;

class AdminSuperuser extends BaseCommand
{
    protected $group='Administration';
    protected $name='admin:create-superuser';
    protected $description='Bootstrap the first Super Admin, or recover an existing one using --recover.';
    protected $options=['--recover'=>'Recover an existing username; requires explicit CLI confirmation.'];
    public function run(array $params)
    {
        if(!is_cli())throw new \RuntimeException('CLI only.');
        date_default_timezone_set(config('App')->appTimezone);
        $db=db_connect();$recover=CLI::getOption('recover')!==null;
        $name=trim(CLI::prompt('Full name'));$username=strtolower(trim(CLI::prompt('Username')));$email=strtolower(trim(CLI::prompt('Email (optional)')))?:null;
        if(!$name||mb_strlen($name)>120||!preg_match('/\A[a-z][a-z0-9_.-]{2,59}\z/',$username)||($email!==null&&(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190))){CLI::error('Invalid name, username or email.');return EXIT_ERROR;}
        if($recover&&CLI::prompt('Type RECOVER to revoke this account sessions and set a temporary password')!=='RECOVER'){CLI::error('Cancelled.');return EXIT_ERROR;}
        $password=bin2hex(random_bytes(20)).'aA!9';$db->transBegin();
        try {
            $role=$db->query("SELECT * FROM admin_roles WHERE slug='super_admin' FOR UPDATE")->getRowArray();
            if(!$role)throw new \DomainException('Run the migrations and AdminSecuritySeeder first.');
            $existing=$db->table('admin_users')->where('username',$username)->get()->getRowArray();
            if(!$recover&&$db->table('admin_users')->countAllResults()>0)throw new \DomainException('Bootstrap is closed. Use Users management, or --recover for an existing Super Admin.');
            if($recover&&(!$existing||!$db->table('admin_user_roles')->where('user_id',$existing['id'])->where('role_id',$role['id'])->countAllResults()))throw new \DomainException('Recovery requires an existing Super Admin account.');
            if($email&&$db->table('admin_users')->where('email',$email)->where('id !=',$existing['id']??0)->countAllResults())throw new \DomainException('Email is already in use.');
            $now=date('Y-m-d H:i:s');$values=['name'=>$name,'username'=>$username,'email'=>$email,'status'=>'active','password_hash'=>AdminAuthService::passwordHash($password),'must_change_password'=>1,'auth_version'=>(int)($existing['auth_version']??0)+1,'password_changed_at'=>$now,'updated_at'=>$now];
            if($existing){$db->table('admin_users')->where('id',$existing['id'])->update($values);$id=(int)$existing['id'];}
            else{$db->table('admin_users')->insert($values+['created_at'=>$now]);$id=(int)$db->insertID();$db->table('admin_user_roles')->insert(['user_id'=>$id,'role_id'=>$role['id']]);}
            $db->table('admin_roles')->where('id',$role['id'])->update(['is_active'=>1]);
            (new AdminAuditService($db))->record($recover?'superuser.recovered':'superuser.bootstrapped','security',null,'user',$id);
            if(!$db->transStatus())throw new \RuntimeException('Bootstrap failed.');$db->transCommit();
            CLI::write('Username: '.$username,'green');CLI::write('One-time password (shown once): '.$password,'yellow');
            CLI::write('Store it securely. Password change is required on first sign-in. No password was stored in plaintext.');
        }catch(\Throwable $e){$db->transRollback();CLI::error($e instanceof \DomainException?$e->getMessage():'Super Admin creation failed. Check private server logs.');return EXIT_ERROR;}
        return EXIT_SUCCESS;
    }
}
