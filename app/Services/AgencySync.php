<?php
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class AgencySync
{
    public function __construct(private BaseConnection $db) {}
    private static function id($value,bool $zero=false): string
    {
        if ((!is_int($value)&&!is_string($value))||!preg_match($zero?'/\A(?:0|[1-9][0-9]{0,17})\z/D':'/\A[1-9][0-9]{0,17}\z/D',(string)$value)) throw new RuntimeException('Invalid agency pagination/identity response.');return (string)$value;
    }
    private static function compare(string $a,string $b): int {return strlen($a)<=>strlen($b)?:strcmp($a,$b);}
    public static function validatePage(array $data,string $after,?string $maximum,?int $total,int $limit=100): array
    {
        if (($data['api_version']??null)!=='v1'||!is_array($data['agencies']??null)||!array_is_list($data['agencies'])||count($data['agencies'])>$limit||!is_bool($data['has_more']??null)||!is_int($data['total']??null)||$data['total']<0||$data['total']>10000) throw new RuntimeException('Invalid agency snapshot contract.');
        $max=self::id($data['snapshot_max_id']??null,true);if (($maximum!==null&&$max!==$maximum)||($total!==null&&$total!==$data['total'])) throw new RuntimeException('Agency snapshot changed during pagination. Retry synchronization.');
        $last=$after;$rows=[];
        foreach ($data['agencies'] as $row) {
            if (!is_array($row)) throw new RuntimeException('Invalid agency row.');$id=self::id($row['id']??null);
            if (self::compare($id,$last)<=0||self::compare($id,$max)>0) throw new RuntimeException('Agency snapshot has duplicate/out-of-order records.');
            $name=$row['name']??null;if (!is_string($name)||!mb_check_encoding($name,'UTF-8')||mb_strlen($name)>255) throw new RuntimeException('Invalid ERP agency name.');
            foreach (['authorize','locked','restricted'] as $key) if (!array_key_exists($key,$row)||($row[$key]!==null&&(!is_string($row[$key])||mb_strlen($row[$key])>100))) throw new RuntimeException('Invalid ERP authorization flags.');
            $status=AgencyDirectory::authorization($row['authorize'],$row['locked'],$row['restricted']);
            if (($row['authorization_status']??null)!==$status) throw new RuntimeException('ERP agency authorization proof is inconsistent.');
            $contact=[];foreach (['cell','cell2','phone','phone2','email','city','address'] as $key) {$value=$row[$key]??'';if (!is_string($value)||!mb_check_encoding($value,'UTF-8')||mb_strlen($value)>2000) throw new RuntimeException('Invalid agency contact response.');$contact[$key]=$value;}
            $rows[]=['id'=>$id,'name'=>$name,'normalized_name'=>AgencyDirectory::normalize($name),'authorize'=>$row['authorize'],'locked'=>$row['locked'],'restricted'=>$row['restricted'],'authorization_status'=>$status,'contact'=>$contact];$last=$id;
        }
        if (self::id($data['next_after_id']??null,true)!==$last||($data['has_more']&&!$rows)) throw new RuntimeException('Agency pagination did not advance.');
        return ['rows'=>$rows,'maximum'=>$max,'total'=>$data['total'],'after'=>$last,'has_more'=>$data['has_more']];
    }
    public function synchronize(int $sourceId,callable $fetch,?int $actor=null): array
    {
        $lock='agency_sync_'.substr(hash('sha256',$this->db->getDatabase().':'.$sourceId),0,44);
        if ((int)$this->db->query('SELECT GET_LOCK(?,0) AS acquired',[$lock])->getRow()->acquired!==1) throw new RuntimeException('This source’s agencies are already synchronizing.');
        try {
            $source=$this->db->table('erp_sources')->where('id',$sourceId)->get()->getRowArray();if (!$source||!(int)$source['is_active']) throw new RuntimeException('An enabled ERP source is required.');
            $records=[];$after='0';$maximum=null;$total=null;$complete=false;
            for ($page=0;$page<100;$page++) {
                $data=self::validatePage($fetch($source,$after,$maximum,100),$after,$maximum,$total);
                $maximum=$data['maximum'];$total=$data['total'];$after=$data['after'];$records=array_merge($records,$data['rows']);
                if (!$data['has_more']) {$complete=true;break;}
            }
            if (!$complete||count($records)!==$total) throw new RuntimeException('The complete agency snapshot was not received. Previous data retained.');
            $this->db->transBegin();
            try {
                $current=$this->db->query('SELECT * FROM erp_sources WHERE id=? FOR UPDATE',[$sourceId])->getRowArray();
                foreach (['code','base_url','auth_type','credential_ref','is_active','updated_at'] as $key) if (!$current||$source[$key]!==$current[$key]) throw new RuntimeException('ERP configuration changed during agency synchronization.');
                $now=$this->now();$this->db->table('agency_erp_links')->where('erp_source_id',$sourceId)->update(['present'=>0]);
                foreach ($records as $row) $this->db->query("INSERT INTO agency_erp_links (erp_source_id,erp_agency_id,erp_agency_name,normalized_name,erp_authorization_status,erp_authorize,erp_locked,erp_restricted,contact_json,first_discovered_at,last_seen_at,created_at,updated_at,present) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE erp_agency_name=VALUES(erp_agency_name),normalized_name=VALUES(normalized_name),erp_authorization_status=VALUES(erp_authorization_status),erp_authorize=VALUES(erp_authorize),erp_locked=VALUES(erp_locked),erp_restricted=VALUES(erp_restricted),contact_json=VALUES(contact_json),last_seen_at=VALUES(last_seen_at),updated_at=VALUES(updated_at),present=1",[$sourceId,$row['id'],$row['name'],$row['normalized_name'],$row['authorization_status'],$row['authorize'],$row['locked'],$row['restricted'],json_encode($row['contact'],JSON_THROW_ON_ERROR),$now,$now,$now,$now]);
                $this->db->table('agency_sync_runs')->insert(['erp_source_id'=>$sourceId,'status'=>'success','record_count'=>count($records),'message'=>'Complete agency snapshot synchronized.','completed_at'=>$now]);
                (new AdminAuditService($this->db))->record('agency.synchronized','agencies',$actor,'erp_source',$sourceId,null,['erp_source_id'=>$sourceId,'record_count'=>count($records)]);
                if($this->db->tableExists('shalimar_agent_settings'))(new ShalimarAgents($this->db))->flagAssignments();
                if (!$this->db->transStatus()||!$this->db->transCommit()) throw new RuntimeException('Agency synchronization could not commit.');
                $directory=new AgencyDirectory($this->db);
                return ['records'=>count($records),'authorized'=>$directory->authorizedLinks($sourceId)->countAllResults(),'unmapped'=>$directory->unmappedCount($sourceId)];
            } catch (\Throwable $e) {$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
        } catch (\Throwable $e) {
            if (isset($source)&&$source) $this->db->table('agency_sync_runs')->insert(['erp_source_id'=>$sourceId,'status'=>'failed','record_count'=>0,'message'=>'Agency sync failed; previous complete data and mappings retained.','completed_at'=>$this->now()]);
            throw $e;
        } finally {$this->db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
    private function now(): string {return (new \DateTimeImmutable('now',new \DateTimeZone(config('App')->appTimezone)))->format('Y-m-d H:i:s');}
}
