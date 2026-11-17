<?php
declare(strict_types=1);
namespace SmpApi;
if (!defined('SMP_API_BOOTSTRAPPED')) { http_response_code(404); exit; }
/** Authenticated SELECT-only inventory feed. Never returns unselected inventory. */
final class CompanyPropertiesController
{
    public static function page(): void
    {
        try {
            $after=$_GET['after_id']??'0';
            if (!is_string($after)||!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',$after)||(float)$after>2147483647) throw new CustomerApiFailure('invalid_request','Invalid inventory cursor.',400);
            $db=new CustomerDatabase(true);
            $db->requireColumns('pro_inventory',['id','company_id','smp_active']);
            $profile=require __DIR__.'/market-profile.php';
            $companyName=ApiEnvironment::get('SMP_API_MARKET_COMPANY_NAME')?:($profile['company_name']??'');
            if (!is_string($companyName)||trim($companyName)==='') throw new CustomerApiFailure('scope_unavailable','ERP company scope is not configured.',503);
            $companies=$db->select('SELECT id FROM companies WHERE BINARY name=BINARY ? LIMIT 2',[$companyName]);
            if(count($companies)!==1) throw new CustomerApiFailure('scope_unavailable','ERP company scope is missing or ambiguous.',503);
            $columns=$db->columns('pro_inventory');
            $fields=['id','company_id','smp_active'];
            foreach(['property_no','plot_no','file_no_series','file_no','head','sub_head','category','nature','size','size_code','sector','block_no','status','floor_no','tower'] as $field) if(isset($columns[$field])) $fields[]=$field;
            $rows=$db->select('SELECT `'.implode('`,`',$fields).'` FROM pro_inventory WHERE company_id=? AND BINARY smp_active=BINARY ? AND id>? ORDER BY id LIMIT 51',[(int)$companies[0]['id'],'yes',(int)$after]);
            $more=count($rows)>50; $rows=array_slice($rows,0,50);
            JsonResponse::send(200,['records'=>$rows,'has_more'=>$more,'next_after_id'=>$rows?(string)$rows[count($rows)-1]['id']:$after]);
        } catch(CustomerApiFailure $e) {
            JsonResponse::send($e->status,['error'=>['code'=>$e->errorCode,'message'=>$e->getMessage()]]);
        } catch(\Throwable $e) {
            error_log('[SMP API] Company inventory unavailable type='.get_class($e));
            JsonResponse::send(503,['error'=>['code'=>'company_inventory_unavailable','message'=>'Selected company inventory is unavailable. Check API schema and configuration.']]);
        }
    }
}
