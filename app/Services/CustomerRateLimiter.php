<?php
namespace App\Services;
use App\Exceptions\CustomerFlowException;
final class CustomerRateLimiter
{
 public function __construct(private \CodeIgniter\Database\BaseConnection $db,private \App\Libraries\CustomerSecrets $secrets){}
 public function consume(array $dimensions,int $now,int $window=900):void
 {
  $buckets=[];foreach($dimensions as$purpose=>$data)$buckets[$this->secrets->digest('rate:'.$purpose,$data[0])]=(int)$data[1];ksort($buckets);$start=intdiv($now,$window)*$window;
  $this->db->transBegin();try{foreach($buckets as$bucket=>$maximum){$this->db->query('INSERT IGNORE INTO customer_rate_limits(bucket,window_start,hits) VALUES(?,?,0)',[$bucket,$start]);$row=$this->db->query('SELECT * FROM customer_rate_limits WHERE bucket=? FOR UPDATE',[$bucket])->getRowArray();$hits=(int)$row['window_start']===$start?(int)$row['hits']:0;if($hits>=$maximum)throw new CustomerFlowException(429,'Too many attempts. Please wait before trying again.','rate_limited');$this->db->table('customer_rate_limits')->where('bucket',$bucket)->update(['window_start'=>$start,'hits'=>$hits+1]);}if(!$this->db->transStatus())throw new \RuntimeException('Rate-limit storage failed.');$this->db->transCommit();}catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
 }
}
