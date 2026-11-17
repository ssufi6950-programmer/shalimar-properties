<?php
declare(strict_types=1);
namespace SmpApi;
if (!defined('SMP_API_BOOTSTRAPPED')) { http_response_code(404); exit; }
/** Authenticated SELECT-only inventory feed. Never returns unselected inventory. */
final class CompanyPropertiesController
{
    private static function sizeLabel($value, $unitId, array $units): string
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) {
            return '';
        }

        $amount = (float) $value;
        $unit = $units[(string) $unitId] ?? null;
        $name = '';

        if ($unit !== null && ($unit['compound_unit'] ?? '') === 'no') {
            $name = trim((string) $unit['name']);
        } elseif ($unit !== null && ($unit['compound_unit'] ?? '') === 'yes') {
            $first = $units[(string) ($unit['first_half'] ?? '')] ?? null;
            $factor = $unit['unit_amount'] ?? null;

            if ($first !== null && ($first['compound_unit'] ?? '') === 'no'
                && trim((string) $first['name']) !== '' && is_numeric($factor)
                && is_finite((float) $factor) && (float) $factor > 0
                && is_finite($amount / (float) $factor)) {
                $amount /= (float) $factor;
                $name = trim((string) $first['name']);
            }
        }

        // Missing/malformed unit metadata must never invent a Marla label.
        $number = rtrim(rtrim(number_format($amount, 3, '.', ''), '0'), '.');
        return $number . ($name !== '' ? ' ' . $name : '');
    }
    /** Enrich only this already selected page; bounded batch reads, no owner contacts. */
    private static function summary(CustomerDatabase $db,array $rows):array
    {
        if(!$rows)return [];
        $ids=array_map(static function($row){return (int)$row['id'];},$rows);
        $slots=implode(',',array_fill(0,count($ids),'?'));
        $company=(int)$rows[0]['company_id'];
        $heads=[];$subs=[];$units=[];$floors=[];$owners=[];
        $headTable=ApiEnvironment::get('SMP_API_HEAD_TABLE')?:'item_location';
        $subTable=ApiEnvironment::get('SMP_API_SUBHEAD_TABLE')?:'item_sublocation';
        $parent=ApiEnvironment::get('SMP_API_PARENT_COLUMN')?:'location_id';
        foreach([$headTable,$subTable,$parent]as$id)if(!preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/D',$id))throw new CustomerApiFailure('schema_unavailable','Invalid catalog configuration.',503);
        foreach([[$headTable,'head'],[$subTable,'sub_head']]as$lookup){
            $columns=$db->columns($lookup[0]);
            $keys=array_values(array_unique(array_filter(array_column($rows,$lookup[1]),static function($v){return $v!==null&&$v!=='';})));
            if(!$keys||!isset($columns['id'],$columns['name']))continue;
            $extra=$lookup[1]==='sub_head'&&isset($columns[$parent])?',`'.$parent.'` AS parent_id':'';
            $found=$db->select('SELECT id,name'.$extra.' FROM `'.$lookup[0].'` WHERE id IN ('.implode(',',array_fill(0,count($keys),'?')).')',$keys);
            foreach($found as$r){if($lookup[1]==='head')$heads[(string)$r['id']]=$r['name'];else $subs[(string)$r['id']]=$r;}
        }
        $columns=$db->columns('property_unit');
        if(!array_diff(['id','name','compound_unit','unit_amount','first_half'],array_keys($columns)))foreach($db->select('SELECT id,name,compound_unit,unit_amount,first_half FROM property_unit LIMIT 1001')as$r)$units[(string)$r['id']]=$r;
        $columns=$db->columns('pro_inventory_floor');
        if(isset($columns['id'],$columns['sub_property_id'],$columns['title'])){
            $extra=isset($columns['category'])?',category':',NULL AS category';
            $found=$db->select('SELECT sub_property_id,title'.$extra.' FROM pro_inventory_floor WHERE sub_property_id IN ('.$slots.') ORDER BY id LIMIT 501',$ids);
            if(count($found)<=500)foreach($found as$r)$floors[(string)$r['sub_property_id']][]=$r;
        }
        $columns=$db->columns('pro_payment_booking');$ledger=$db->columns('ledger');
        $ownerSupported=!array_diff(['id','property_id','company_id','customer_ledger','locked','cancel','sale_type','property_status'],array_keys($columns))&&isset($ledger['id'],$ledger['name']);
        if($ownerSupported){
            // Inspect ALL unlocked candidates. Never resurrect a historical seller by skipping invalid bookings.
            $found=$db->select("SELECT b.id,b.property_id,b.customer_ledger,b.cancel,b.sale_type,b.property_status,l.name AS owner_name FROM pro_payment_booking b LEFT JOIN ledger l ON l.id=b.customer_ledger WHERE b.company_id=? AND b.locked='no' AND b.property_id IN (".$slots.') ORDER BY b.id LIMIT 501',array_merge([$company],$ids));
            if(count($found)>500)$ownerSupported=false;
            else foreach($found as$r)$owners[(string)$r['property_id']][]=$r;
        }
        foreach($rows as&$row){
            $id=(string)$row['id'];$head=(string)($row['head']??'');$sub=$subs[(string)($row['sub_head']??'')]??[];
            $row['head_name']=$heads[$head]??'';
            $row['subhead_name']=isset($sub['parent_id'])&&(string)$sub['parent_id']===$head?(string)$sub['name']:'';
            $row['size_label']=self::sizeLabel($row['size']??null,$row['size_code']??null,$units);
            $floor=$floors[$id]??[];
            $row['floor']=count($floor)===1?trim((string)$floor[0]['title']).(trim((string)$floor[0]['category'])!==''?' ('.ucfirst(trim((string)$floor[0]['category'])).')':''):'';
            $row['owner_name']='';$row['booking_id']=null;
            $row['owner_state']=$ownerSupported?'none':'unavailable';
            $bookings=$owners[$id]??[];
            if($ownerSupported&&count($bookings)>1)$row['owner_state']='unconfirmed';
            elseif($ownerSupported&&count($bookings)===1){
                $booking=$bookings[0];
                if(CurrentBookingRules::valid($booking)&&trim((string)$booking['owner_name'])!==''){
                    $row['owner_name']=(string)$booking['owner_name'];$row['booking_id']=(string)$booking['id'];$row['owner_state']='current';
                }else $row['owner_state']='unconfirmed';
            }
        }unset($row);
        return $rows;
    }
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
            $more=count($rows)>50; $rows=self::summary($db,array_slice($rows,0,50));
            JsonResponse::send(200,['records'=>$rows,'has_more'=>$more,'next_after_id'=>$rows?(string)$rows[count($rows)-1]['id']:$after]);
        } catch(CustomerApiFailure $e) {
            JsonResponse::send($e->status,['error'=>['code'=>$e->errorCode,'message'=>$e->getMessage()]]);
        } catch(\Throwable $e) {
            error_log('[SMP API] Company inventory unavailable type='.get_class($e));
            JsonResponse::send(503,['error'=>['code'=>'company_inventory_unavailable','message'=>'Selected company inventory is unavailable. Check API schema and configuration.']]);
        }
    }
}
