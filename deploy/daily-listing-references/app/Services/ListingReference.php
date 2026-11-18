<?php
declare(strict_types=1);
namespace App\Services;
use CodeIgniter\Database\BaseConnection;

/** Display reference is independent of the immutable listing primary key. */
final class ListingReference
{
    private bool $held=false;
    private string $lock;
    public function __construct(private BaseConnection $db)
    {
        $this->lock='smp.listing.ref.'.substr(hash('sha256',$db->getDatabase()),0,40);
    }
    public static function display(array $row):string
    {
        $payload=is_string($row['payload']??null)?json_decode($row['payload'],true):[];
        $reference=$payload['ref']??$row['ref']??null;
        return is_string($reference)&&trim($reference)!==''?$reference:'SP-'.(string)($row['id']??'');
    }
    /** Acquire BEFORE the listing transaction; all creation paths use the same lock order. */
    public function acquire():void
    {
        if($this->held)throw new \LogicException('Listing reference lock already held.');
        $row=$this->db->query('SELECT GET_LOCK(?,10) AS acquired',[$this->lock])->getRowArray();
        if((int)($row['acquired']??0)!==1)throw new \RuntimeException('Listing reference allocation is busy. Retry submission.');
        $this->held=true;
    }
    /** Called inside the locked creation transaction. Rollback never consumes a serial. */
    public function next(string $createdAt):string
    {
        if(!$this->held)throw new \LogicException('A listing reference lock is required.');
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$createdAt,new \DateTimeZone('Asia/Karachi'));
        if(!$date||$date->format('Y-m-d H:i:s')!==$createdAt)throw new \InvalidArgumentException('Invalid listing creation date.');
        $prefix='SP-'.$date->format('ymd');
        $value="JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(payload) THEN payload ELSE '{}' END,'$.ref'))";
        $row=$this->db->query('SELECT COALESCE(MAX(CAST(SUBSTRING('.$value.',?) AS UNSIGNED)),0) AS last_serial FROM portal_listings WHERE BINARY '.$value.' REGEXP ?', [strlen($prefix)+1,'^'.$prefix.'[1-9][0-9]*$'])->getRowArray();
        $last=(int)($row['last_serial']??0);
        if($last<0||$last>=999999999)throw new \RuntimeException('Daily listing reference capacity exceeded.');
        return $prefix.($last+1);
    }
    public function release():void
    {
        if(!$this->held)return;
        try{$this->db->query('SELECT RELEASE_LOCK(?) AS released',[$this->lock]);}
        catch(\Throwable $e){log_message('error','Listing reference lock release failed: type={type}',['type'=>get_class($e)]);}
        finally{$this->held=false;}
    }
}
