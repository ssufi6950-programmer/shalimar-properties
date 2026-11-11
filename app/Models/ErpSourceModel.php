<?php

namespace App\Models;

use CodeIgniter\Model;
use DomainException;
use LogicException;

class ErpSourceModel extends Model
{
    protected $table = 'erp_sources';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name','code','base_url','auth_type','credential_ref','is_active','connection_status','last_checked_at','last_sync_at'];
    protected $useTimestamps = true;
    protected $beforeUpdate = ['protectReferencedCodes'];
    protected $validationRules = [
        'id'=>'permit_empty|is_natural_no_zero',
        'name'=>'required|max_length[120]',
        'code'=>'required|max_length[30]|regex_match[/\A[A-Z][A-Z0-9_-]*\z/]|is_unique[erp_sources.code,id,{id}]',
        'auth_type'=>'required|in_list[none,bearer,basic,api_key]',
        'credential_ref'=>'permit_empty|max_length[100]|regex_match[/\A[A-Za-z][A-Za-z0-9_.-]*\z/]',
        'base_url'=>'permit_empty|max_length[2048]',
        'is_active'=>'required|in_list[0,1]',
    ];
    protected $validationMessages = [
        'code'=>['is_unique'=>'This ERP source code is already in use.','regex_match'=>'Use an uppercase letter followed by letters, numbers, underscores or hyphens.'],
    ];

    /** All future portal tables must use erp_source_id with an ON DELETE/UPDATE RESTRICT FK. */
    public function referenceCount(int $id): int
    {
        $database=$this->db->getDatabase();
        $sourceTable=$this->db->prefixTable($this->table);
        $links=[];
        $foreignKeys=$this->db->query(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?',
            [$database,$database,$sourceTable,'id']
        )->getResultArray();
        $conventional=$this->db->query(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = ? AND TABLE_NAME <> ?',
            [$database,'erp_source_id',$sourceTable]
        )->getResultArray();
        foreach (array_merge($foreignKeys,$conventional) as $link) $links[$link['TABLE_NAME'].'.'.$link['COLUMN_NAME']]=$link;
        $count=0;
        foreach ($links as $link) $count += $this->db->table($link['TABLE_NAME'])->where($link['COLUMN_NAME'],$id)->countAllResults();
        return $count;
    }

    protected function protectReferencedCodes(array $event): array
    {
        if (!array_key_exists('code',$event['data'])) return $event;
        if (empty($event['id'])) throw new DomainException('ERP code changes require an explicit source ID.');
        foreach ((array)$event['id'] as $id) {
            $row=$this->find($id);
            if ($row && $row['code'] !== $event['data']['code'] && $this->referenceCount((int)$id)>0) {
                throw new DomainException('This ERP code is referenced by other records and cannot be changed.');
            }
        }
        return $event;
    }

    /** This module deliberately exposes no hard-delete path, including for unreferenced sources. */
    public function delete($id = null, bool $purge = false)
    {
        throw new LogicException('ERP sources must be deactivated, not deleted.');
    }

    public function activeOptions(): array
    {
        return $this->select('id,name,code')->where('is_active',1)->orderBy('name')->findAll();
    }

    public function databaseConnection(): \CodeIgniter\Database\BaseConnection
    {
        return $this->db;
    }
}
