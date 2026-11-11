<?php
namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\AdminSecurity;

class AdminSecuritySeeder extends Seeder
{
    public function run()
    {
        date_default_timezone_set(config('App')->appTimezone);
        $now=date('Y-m-d H:i:s');
        foreach (AdminSecurity::MODULES as $module=>$actions) foreach ($actions as $action) {
            $key=$module.'.'.$action;
            if (!$this->db->table('admin_permissions')->where('permission_key',$key)->countAllResults()) $this->db->table('admin_permissions')->insert(['module'=>$module,'action'=>$action,'permission_key'=>$key,'description'=>ucwords(str_replace('_',' ',$module.' '.$action)),'created_at'=>$now,'updated_at'=>$now]);
        }
        $all=array_column($this->db->table('admin_permissions')->get()->getResultArray(),'permission_key');
        $defaults=[
            'super_admin'=>$all,
            'admin'=>array_values(array_filter($all,fn($key)=>!str_starts_with($key,'users.')&&!str_starts_with($key,'roles.')&&!str_starts_with($key,'settings.')&&$key!=='erp_sources.manage_credentials')),
            'manager'=>['dashboard.view','properties.view','properties.edit','resale_listings.view','resale_listings.edit','resale_listings.approve','resale_listings.reject','customers.view','leads.view','leads.assign','leads.edit','reports.view'],
            'operator'=>['dashboard.view','properties.view','resale_listings.view','customers.view','leads.view','leads.edit'],
            'auditor'=>['dashboard.view','reports.view','audit_logs.view'],
        ];
        $this->db->transStart();
        foreach ($defaults as $slug=>$keys) {
            $existing=$this->db->table('admin_roles')->where('slug',$slug)->get()->getRowArray();
            if($existing) {
                // The protected Super Admin role always receives newly registered permissions.
                if($slug==='super_admin')foreach($this->db->table('admin_permissions')->get()->getResultArray()as$p)if(!$this->db->table('admin_role_permissions')->where('role_id',$existing['id'])->where('permission_id',$p['id'])->countAllResults()) {
                    $this->db->table('admin_role_permissions')->insert(['role_id'=>$existing['id'],'permission_id'=>$p['id']]);
                    $this->db->query('UPDATE admin_users SET auth_version=auth_version+1 WHERE id IN (SELECT user_id FROM admin_user_roles WHERE role_id=?)',[$existing['id']]);
                }
                continue; // Preserve other role edits on reruns.
            }
            $this->db->table('admin_roles')->insert(['name'=>ucwords(str_replace('_',' ',$slug)),'slug'=>$slug,'description'=>'System role','is_system'=>1,'is_active'=>1,'created_at'=>$now,'updated_at'=>$now]);
            $id=$this->db->insertID();
            foreach ($this->db->table('admin_permissions')->whereIn('permission_key',$keys)->get()->getResultArray() as $p) $this->db->table('admin_role_permissions')->insert(['role_id'=>$id,'permission_id'=>$p['id']]);
        }
        $this->db->transComplete();
        if (!$this->db->transStatus()) throw new \RuntimeException('Security role seeding failed.');
    }
}
