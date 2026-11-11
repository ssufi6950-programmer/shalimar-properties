<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ErpSourceSeeder extends Seeder
{
    public function run()
    {
        foreach (['SGC'=>'SGC ERP', 'ZALMI'=>'Zalmi ERP'] as $code=>$name) {
            // Idempotent: never replace an administrator's existing configuration.
            if ($this->db->table('erp_sources')->where('code',$code)->countAllResults() !== 0) continue;
            $this->db->table('erp_sources')->insert([
                'name'=>$name,'code'=>$code,'base_url'=>null,'auth_type'=>'none',
                'credential_ref'=>null,'is_active'=>1,'connection_status'=>'not_configured',
                'last_checked_at'=>null,'last_sync_at'=>null,
                'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),
            ]);
        }
    }
}
