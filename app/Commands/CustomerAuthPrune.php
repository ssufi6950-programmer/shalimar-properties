<?php
namespace App\Commands;
class CustomerAuthPrune extends \CodeIgniter\CLI\BaseCommand
{
 protected $group='Customers';protected $name='customer:prune-auth';protected $description='Remove OTP challenges and rate-limit windows older than one day.';
 public function run(array $params){$db=db_connect();$cutoff=time()-86400;$db->table('customer_otp_challenges')->where('expires_at <',$cutoff)->delete();$db->table('customer_rate_limits')->where('window_start <',$cutoff)->delete();\CodeIgniter\CLI\CLI::write('Expired customer authentication state pruned; accounts, links and properties preserved.');}
}
