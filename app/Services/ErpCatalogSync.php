<?php
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

class ErpCatalogSync
{
    public function __construct(private BaseConnection $db) {}
    public static function validate(array $snapshot): array
    {
        if (($snapshot['complete']??null)!==true || ($snapshot['api_version']??null)!=='v1') throw new InvalidArgumentException('A complete v1 snapshot is required.');
        foreach (['heads','subheads'] as $kind) {
            if (!isset($snapshot[$kind]) || !is_array($snapshot[$kind]) || !array_is_list($snapshot[$kind]) || ($snapshot[$kind==='heads'?'head_count':'subhead_count']??null)!==count($snapshot[$kind])) throw new InvalidArgumentException('Snapshot counts or records are invalid.');
            $seen=[];
            foreach ($snapshot[$kind] as $row) {
                if (!is_array($row)) throw new InvalidArgumentException('Invalid catalog record.');
                foreach ($kind==='heads'?['id']:['id','head_id'] as $key) {
                    $id=$row[$key]??null;
                    if ((!is_int($id)&&!is_string($id)) || !preg_match($key==='head_id'?'/\A(?:0|[1-9][0-9]{0,17})\z/D':'/\A[1-9][0-9]{0,17}\z/D',(string)$id)) throw new InvalidArgumentException('Invalid ERP identifier.');
                }
                if (!is_string($row['name']??null) || mb_strlen($row['name'])>255 || !array_key_exists('smp_active',$row) || ($row['smp_active']!==null && (!is_string($row['smp_active']) || strlen($row['smp_active'])>65535))) throw new InvalidArgumentException('Invalid ERP string fields.');
                // A subhead ID cannot occur under two parents within one complete snapshot.
                if (isset($seen[(string)$row['id']])) throw new InvalidArgumentException('Duplicate ERP identifier.');
                $seen[(string)$row['id']]=true;
            }
        }
        // Existing ERP data can contain orphaned Subheads. Retain them; public joins require a present Head.
        return $snapshot;
    }
    /** Acquire before fetching to prevent older overlapping snapshots replacing newer ones. */
    public function synchronize(int $sourceId, callable $fetch): array
    {
        if (!is_cli()) { helper('admin'); if (!can('erp_sync.run')) throw new RuntimeException('Synchronization permission required.'); }
        $lock='smp_catalog_'.substr(hash('sha256',$this->db->getDatabase().':'.$sourceId),0,40);
        if ((int)$this->db->query('SELECT GET_LOCK(?,0) AS acquired',[$lock])->getRow()->acquired!==1) throw new RuntimeException('This ERP source is already synchronizing.');
        try {
            $source=$this->db->table('erp_sources')->where('id',$sourceId)->get()->getRowArray();
            if (!$source || !(int)$source['is_active']) throw new RuntimeException('An active ERP source is required.');
            $snapshot=self::validate($fetch($source));
            if (!$this->db->transBegin()) throw new \RuntimeException('Could not start a catalog transaction.');
            try {
                $current=$this->db->query('SELECT * FROM erp_sources WHERE id=? FOR UPDATE',[$sourceId])->getRowArray();
                foreach (['code','base_url','auth_type','credential_ref','is_active','updated_at'] as $key) if ($source[$key]!==$current[$key]) throw new RuntimeException('ERP configuration changed during synchronization.');
                $this->db->table('erp_heads')->where('erp_source_id',$sourceId)->update(['present'=>0]);
                $this->db->table('erp_subheads')->where('erp_source_id',$sourceId)->update(['present'=>0]);
                foreach ($snapshot['heads'] as $row) $this->db->query('INSERT INTO erp_heads (erp_source_id,erp_head_id,name,smp_active,present) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name),smp_active=VALUES(smp_active),present=1',[$sourceId,$row['id'],$row['name'],$row['smp_active']]);
                foreach ($snapshot['subheads'] as $row) $this->db->query('INSERT INTO erp_subheads (erp_source_id,erp_head_id,erp_subhead_id,name,smp_active,present) VALUES (?,?,?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name),smp_active=VALUES(smp_active),present=1',[$sourceId,$row['head_id'],$row['id'],$row['name'],$row['smp_active']]);
                // Do not edit mappings, project publication, listing status, or ownership.
                $now=(new \DateTimeImmutable('now',new \DateTimeZone(config('App')->appTimezone)))->format('Y-m-d H:i:s');
                $this->db->table('erp_sources')->where('id',$sourceId)->update(['last_sync_at'=>$now]);
                $this->db->table('erp_sync_runs')->insert(['erp_source_id'=>$sourceId,'completed_at'=>$now,'status'=>'success','message'=>count($snapshot['heads']).' Heads; '.count($snapshot['subheads']).' Subheads']);
                if (!$this->db->transStatus()) throw new RuntimeException('Catalog transaction failed.');
                if (!$this->db->transCommit()) throw new \RuntimeException('Could not commit the catalog transaction.');
                return ['heads'=>count($snapshot['heads']),'subheads'=>count($snapshot['subheads'])];
            } catch (\Throwable $e) { $this->db->transRollback(); $this->db->resetTransStatus(); throw $e; }
        } catch (\Throwable $e) {
            $this->db->table('erp_sync_runs')->insert(['erp_source_id'=>$sourceId,'completed_at'=>(new \DateTimeImmutable('now',new \DateTimeZone(config('App')->appTimezone)))->format('Y-m-d H:i:s'),'status'=>'failed','message'=>$e instanceof \App\Exceptions\ErpCatalogRequestException ? $e->getMessage() : 'Synchronization failed; previous complete snapshot retained.']);
            throw $e;
        } finally { $this->db->query('SELECT RELEASE_LOCK(?)',[$lock]); }
    }
}
