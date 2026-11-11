<?php
namespace App\Models;
class CustomerErpLinkModel extends \CodeIgniter\Model
{
 protected $table='customer_erp_links';
 protected $returnType='array';
 protected $allowedFields=['portal_customer_id', 'erp_source_id', 'erp_customer_id', 'verified_mobile', 'verified_at', 'created_at', 'updated_at'];
}
