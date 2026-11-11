<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AddAdminRecordIndexes extends Migration
{
 private array $indexes=[
  'portal_listings'=>['admin_listing_source_property'=>'erp_source_id,erp_property_id,id','admin_listing_customer'=>'portal_customer_id,id','admin_listing_project_status'=>'project_slug,status,id'],
  'portal_customers'=>['admin_customer_status'=>'status,id'],
 ];
 public function up(){foreach($this->indexes as$table=>$indexes){$existing=array_column($this->db->query('SHOW INDEX FROM '.$table)->getResultArray(),'Key_name');foreach($indexes as$name=>$columns)if(!in_array($name,$existing,true))$this->db->query('ALTER TABLE '.$table.' ADD INDEX '.$name.' ('.$columns.')');}}
 public function down(){foreach($this->indexes as$table=>$indexes){$existing=array_column($this->db->query('SHOW INDEX FROM '.$table)->getResultArray(),'Key_name');foreach($indexes as$name=>$_)if(in_array($name,$existing,true))$this->db->query('ALTER TABLE '.$table.' DROP INDEX '.$name);}}
}
