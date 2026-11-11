<?php
namespace App\Models;
class PortalCustomerModel extends \CodeIgniter\Model
{
 protected $table='portal_customers';
 protected $returnType='array';
 protected $allowedFields=['cnic_hash', 'mobile_hash', 'display_name', 'verified_mobile', 'status', 'auth_version', 'created_at', 'updated_at'];
}
