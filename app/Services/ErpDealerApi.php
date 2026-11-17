<?php
namespace App\Services;

class ErpDealerApi extends ErpAgencyApi
{
    public function dealers(array $source,string $agency,string $after='0',?string $maximum=null,int $limit=100):array
    {
        if(!in_array($source['code']??null,['SGC','ZALMI'],true))throw new \RuntimeException('Shalimar agents require an enabled SGC or Zalmi source.');
        ShalimarAgents::identifier($agency);
        $query=['agency_id'=>$agency,'after_id'=>$after,'limit'=>$limit];if($maximum!==null)$query['snapshot_max_id']=$maximum;
        // API fails closed if ledger.sp_Agent is missing; never fall back to dealer=yes.
        return $this->directoryPage($source,$this->settings->dealerPaths[$source['code']]??'/dealers.php',$query);
    }
}
