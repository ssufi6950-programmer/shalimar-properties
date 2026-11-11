<?php
namespace App\Services;

class ErpDealerApi extends ErpAgencyApi
{
    public function dealers(array $source,string $agency,string $after='0',?string $maximum=null,int $limit=100):array
    {
        if(($source['code']??null)!=='SGC')throw new \RuntimeException('Shalimar agents must come from the configured SGC source.');
        $query=['agency_id'=>$agency,'after_id'=>$after,'limit'=>$limit];if($maximum!==null)$query['snapshot_max_id']=$maximum;
        return $this->directoryPage($source,$this->settings->dealerPaths[$source['code']]??null,$query);
    }
}
