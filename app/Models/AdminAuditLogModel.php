<?php
namespace App\Models;
class AdminAuditLogModel extends \CodeIgniter\Model
{
    protected $table='admin_audit_logs';
    protected $returnType='array';
    protected $allowedFields=[];
    public function delete($id=null,bool $purge=false) {throw new \LogicException('Audit records are append-only. Use the restricted CLI retention command.');}
    public function update($id=null,$row=null): bool {throw new \LogicException('Audit records are append-only.');}
}
