<?php
declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;
use PDO;

/** ERP-derived public aggregates only. Never reads portal listings, ownership or OTP records. */
final class ErpMarketIntelligenceService
{
    public const REPORT_BASIS='sales_resales_checkin_deals_v1';
    public const DIMENSION_BASIS='inventory_head_subhead_v1';
    public const SIZE_BASIS='current_inventory_size_v1';
    public const SIZE_CHART_BASIS='selected_5_7_10_20_marla_v1';
    public const CATEGORY_CHART_BASIS='current_inventory_category_v2';
    public const TYPE_LABEL_BASIS='zalmi_office_outlet_labels_v1';
    public const MONTHLY_TYPE_BASIS='sale_deals_all_checkins_v2';
    public const FRESH_SECONDS=300;
    public const STALE_SECONDS=1800;
    public const RETRY_SECONDS=60;
    private string $cacheDir;
    public function __construct(private PDO $pdo,private $transport=null,?string $cacheDir=null)
    {
        $this->transport??=new ErpMarketApi();$this->cacheDir=$cacheDir??(defined('WRITEPATH')?WRITEPATH:dirname(__DIR__,2).'/writable/').'cache/erp-market-intelligence';
    }
    public static function unavailable(?DateTimeImmutable $now=null,string $reason='Combined SGC + Zalmi deal data is temporarily unavailable.'):array
    {
        $p=ErpMarketPeriods::make($now);
        return ['available'=>false,'activity_label'=>'ERP Deals','today'=>null,'last_month'=>null,'month'=>null,
            'deal_components'=>[],'daily'=>[],'monthly'=>[],'monthly_by_type'=>[],'monthly_types_available'=>false,'monthly_excluded_checkins'=>0,'chart_total'=>null,'yoy'=>null,'price_groups'=>[],'sizes'=>[],'sizes_available'=>false,'sizes_total'=>null,'categories'=>[],'categories_available'=>false,'categories_total'=>null,'sizes_grouped'=>false,'last_month_rows'=>[],
            'projects'=>[],'project_month_counts'=>[],'price_method'=>'Actual completed resale consideration is not verified in these records. Booking balances and processing fees are not sale prices.',
            'as_of'=>$p['now']->format(DATE_ATOM),'last_updated'=>null,'status'=>'unavailable','stale'=>false,'message'=>$reason,
            'last_month_label'=>$p['last_month']->format('F Y'),'current_month_label'=>$p['month']->format('F Y'),'prior_year_label'=>$p['year_ago']->format('F Y')];
    }
    private static function count($v):int
    {
        if(!is_int($v)||$v<0||$v>100000000)throw new \RuntimeException('Invalid ERP market count.');return $v;
    }
    private static function text($v):string
    {
        if(!is_string($v)||strlen($v)>1024||!mb_check_encoding($v,'UTF-8'))throw new \RuntimeException('Invalid ERP market label.');return $v;
    }
    private function validateSizes(array $data,array $periods,int $total):array
    {
        // Older source uploads can keep serving existing totals during the rollout.
        if(!array_key_exists('size_distribution',$data))return ['supported'=>false,'buckets'=>[]];
        $sizes=$data['size_distribution'];
        if(!is_array($sizes)||!is_bool($sizes['supported']??null)||($sizes['basis']??null)!==self::SIZE_BASIS||($sizes['start']??null)!==$periods['today']->modify('-29 days')->format(DATE_ATOM)||($sizes['end']??null)!==$periods['tomorrow']->format(DATE_ATOM)||self::count($sizes['total']??null)!==$total||!is_array($sizes['buckets']??null)||!array_is_list($sizes['buckets'])||count($sizes['buckets'])>5000)throw new \RuntimeException('Invalid property size report.');
        if(!$sizes['supported']){if($sizes['buckets']!==[])throw new \RuntimeException('Unsupported size report contains data.');return ['supported'=>false,'buckets'=>[]];}
        $buckets=[];$sum=0;
        foreach($sizes['buckets']as$row){
            if(!is_array($row)||!array_key_exists('size',$row)||!array_key_exists('unit',$row))throw new \RuntimeException('Invalid property size bucket.');
            $size=$row['size'];$unit=$row['unit'];$unknown=$size===null&&$unit===null;
            if(!$unknown&&(!is_string($size)||!preg_match('/\A(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,3})?\z/D',$size)||(float)$size<=0||(float)$size>1000000000000||rtrim(rtrim(number_format((float)$size,3,'.',''),'0'),'.')!==$size||!in_array($unit,['Marla','Sq ft','Kanal','Sq yd','Acre'],true)))throw new \RuntimeException('Invalid property size unit or value.');
            $key=$unknown?'unknown':$unit.':'.$size;$count=self::count($row['deals']??null);
            if(isset($buckets[$key])||$count===0)throw new \RuntimeException('Duplicate or empty property size bucket.');
            $sum+=$count;$buckets[$key]=['size'=>$size,'unit'=>$unit,'deals'=>$count];
        }
        if($sum!==$total)throw new \RuntimeException('Incomplete property size totals.');
        return ['supported'=>true,'buckets'=>array_values($buckets)];
    }
    private function validateCategories(array $data,array $periods,int $total):array
    {
        if(!array_key_exists('category_distribution',$data))return ['supported'=>false,'buckets'=>[]];$report=$data['category_distribution'];
        if(!is_array($report)||!is_bool($report['supported']??null)||($report['basis']??null)!==self::CATEGORY_CHART_BASIS||($report['start']??null)!==$periods['today']->modify('-29 days')->format(DATE_ATOM)||($report['end']??null)!==$periods['tomorrow']->format(DATE_ATOM)||self::count($report['total']??null)!==$total||!is_array($report['buckets']??null)||!array_is_list($report['buckets'])||count($report['buckets'])>100)throw new \RuntimeException('Invalid property category report.');
        if(!$report['supported']){if($report['buckets']!==[])throw new \RuntimeException('Unsupported category report contains data.');return ['supported'=>false,'buckets'=>[]];}
        $buckets=[];$sum=0;foreach($report['buckets']as$row){if(!is_array($row)||!is_string($row['category']??null)||($row['category']!==''&&!preg_match('/\A[a-z][a-z0-9 _-]{0,63}\z/D',$row['category']))||isset($buckets[$row['category']]))throw new \RuntimeException('Invalid property category bucket.');$n=self::count($row['deals']??null);$buckets[$row['category']]=$n;$sum+=$n;}if($sum!==$total)throw new \RuntimeException('Incomplete property category report.');return ['supported'=>true,'buckets'=>$buckets];
    }
    private function validate(array $data,array $source,array $request,DateTimeImmutable $now):array
    {
        if(($data['api_version']??null)!=='v1'||($data['source']??null)!==$source['code']||($data['timezone']??null)!=='Asia/Karachi'
            ||($data['complete']??null)!==true||($data['as_of']??null)!==$request['as_of']||($data['completion_basis']??null)!==self::REPORT_BASIS||($data['dimension_basis']??null)!==self::DIMENSION_BASIS)throw new \RuntimeException('ERP market contract mismatch.');
        $p=ErpMarketPeriods::make($now);$expected=['today'=>[$p['today'],$p['tomorrow']],'last_month'=>[$p['last_month'],$p['month']],'this_month'=>[$p['month'],$p['next_month']]];$counts=[];$components=[];
        foreach($expected as$key=>[$start,$end]){$period=$data['periods'][$key]??[];if(($period['start']??null)!==$start->format(DATE_ATOM)||($period['end']??null)!==$end->format(DATE_ATOM))throw new \RuntimeException('ERP market period mismatch.');$counts[$key]=self::count($period['completed_deals']??null);$components[$key]=['sale'=>self::count($period['sale_deals']??null),'resale'=>self::count($period['resale_deals']??null),'check_in'=>self::count($period['check_ins']??null)];if(array_sum($components[$key])!==$counts[$key])throw new \RuntimeException('ERP market component totals mismatch.');}
        $daily=[];for($i=29;$i>=0;$i--){$day=$p['today']->modify('-'.$i.' days')->format('Y-m-d');$daily[$day]=self::count($data['daily_deals'][$day]??null);}
        if(!is_array($data['daily_deals']??null)||array_keys($data['daily_deals'])!==array_keys($daily)||self::count($data['chart_total']??null)!==array_sum($daily)||end($daily)!==$counts['today'])throw new \RuntimeException('ERP market daily series mismatch.');
        $monthly=[];for($i=11;$i>=0;$i--){$month=$p['month']->modify('-'.$i.' months')->format('Y-m');$monthly[$month]=self::count($data['monthly_deals'][$month]??null);}
        if(!is_array($data['monthly_deals']??null)||array_keys($data['monthly_deals'])!==array_keys($monthly)||$monthly[$p['month']->format('Y-m')]!==$counts['this_month']||$monthly[$p['last_month']->format('Y-m')]!==$counts['last_month'])throw new \RuntimeException('ERP market monthly series mismatch.');
        $projects=$data['projects']??null;$breakdown=$data['breakdown']??null;
        if(!is_array($projects)||!array_is_list($projects)||count($projects)>500||!is_array($breakdown)||!array_is_list($breakdown)||count($breakdown)>6000)throw new \RuntimeException('ERP market dimensions invalid.');
        // Missing ERP inventory may use 0 for an unassigned reporting dimension.
        // Keep those deals in aggregates; this does not authorize a property mapping.
        $safeProjects=[];foreach($projects as$row){foreach(['head_id','subhead_id']as$id)if(!is_string($row[$id]??null)||!preg_match('/\A(?:0|[1-9][0-9]{0,17})\z/D',$row[$id]))throw new \RuntimeException('ERP market segment invalid.');$key=$row['head_id'].':'.$row['subhead_id'];if(isset($safeProjects[$key]))throw new \RuntimeException('Duplicate ERP market segment.');$safeProjects[$key]=['head_id'=>$row['head_id'],'subhead_id'=>$row['subhead_id'],'head_name'=>self::text($row['head_name']??null),'subhead_name'=>self::text($row['subhead_name']??null)];}
        $safeRows=[];$sums=array_fill_keys(array_keys($monthly),0);$componentSums=array_fill_keys(array_keys($monthly),['sale'=>0,'resale'=>0,'check_in'=>0]);
        foreach($breakdown as$row){$pair=(string)($row['head_id']??'').':'.(string)($row['subhead_id']??'');$month=$row['month']??'';$category=$row['category']??null;$kind=$row['deal_kind']??null;
            if(!is_string($kind)||!in_array($kind,['sale','resale','check_in'],true)||!isset($safeProjects[$pair],$monthly[$month])||!is_string($category)||!in_array($category,['','shop','office','apartment','plot','house'],true))throw new \RuntimeException('ERP market breakdown invalid.');
            if(($request['category']??'')!==''&&$request['category']!==$category)throw new \RuntimeException('ERP market category scope mismatch.');
            if($request['pairs']!==null&&!array_filter($request['pairs'],static fn($p)=>(string)$p['head_id']===(string)$row['head_id']&&(string)$p['subhead_id']===(string)$row['subhead_id']))throw new \RuntimeException('ERP market project scope mismatch.');
            $key=$month.':'.$pair.':'.$category.':'.$kind;if(isset($safeRows[$key]))throw new \RuntimeException('Duplicate ERP market breakdown.');$n=self::count($row['deals']??null);$sums[$month]+=$n;$componentSums[$month][$kind]+=$n;$safeRows[$key]=['month'=>$month,'head_id'=>$row['head_id'],'subhead_id'=>$row['subhead_id'],'category'=>$category,'deal_kind'=>$kind,'deals'=>$n];
        }
        if($sums!==$monthly)throw new \RuntimeException('Incomplete ERP market breakdown.');
        foreach(['last_month'=>$p['last_month']->format('Y-m'),'this_month'=>$p['month']->format('Y-m')]as$key=>$month)if($components[$key]!==$componentSums[$month])throw new \RuntimeException('ERP market component breakdown mismatch.');
        if(($data['price_comparison']['supported']??null)!==false||($data['price_comparison']['basis']??null)!==null||($data['price_comparison']['change']??null)!==null)throw new \RuntimeException('Unverified ERP market price basis.');
        $rentals=null;
        if(array_key_exists('monthly_rental_agreements',$data)||array_key_exists('rental_agreement_basis',$data)){
            if(($data['rental_agreement_basis']??null)!=='tenant_checkin_v1'||!is_array($data['monthly_rental_agreements']??null)||array_keys($data['monthly_rental_agreements'])!==array_keys($monthly))throw new \RuntimeException('Invalid monthly rental agreement report.');
            $rentals=[];foreach($monthly as$month=>$total){$n=self::count($data['monthly_rental_agreements'][$month]);if($n>$componentSums[$month]['check_in'])throw new \RuntimeException('Rental agreements exceed check-ins.');$rentals[$month]=$n;}
        }
        return ['counts'=>$counts,'components'=>$components,'daily'=>$daily,'monthly'=>$monthly,'monthly_components'=>$componentSums,'rental_monthly'=>$rentals,'projects'=>$safeProjects,'breakdown'=>array_values($safeRows),'sizes'=>$this->validateSizes($data,$p,array_sum($daily)),'categories'=>$this->validateCategories($data,$p,array_sum($daily))];
    }
    private function requests(array $filters,array $sources,DateTimeImmutable $now):array
    {
        if(!empty($filters['sector'])||!empty($filters['block']))throw new \InvalidArgumentException('Historical sector/block snapshots are not supported by this report.');
        $category=$filters['category']??'';if(!is_string($category)||!in_array($category,['','shop','office','apartment','plot','house'],true))throw new \InvalidArgumentException('Unsupported property category.');
        $out=[];foreach($sources as$s)$out[$s['code']]=['as_of'=>$now->format(DATE_ATOM),'pairs'=>null,'category'=>$category];
        $project=$filters['project']??'';if(!is_string($project))throw new \InvalidArgumentException('Invalid report project.');
        if($project!==''){
            foreach($out as&$payload)$payload['pairs']=[];unset($payload);
            if(preg_match('~\A(SGC|ZALMI)/(0|[1-9][0-9]{0,17})/(0|[1-9][0-9]{0,17})\z~D',$project,$m))$out[$m[1]]['pairs']=[['head_id'=>$m[2],'subhead_id'=>$m[3]]];
            else{$q=$this->pdo->prepare("SELECT s.code,m.erp_head_id,m.erp_subhead_id FROM erp_project_mappings m JOIN erp_sources s ON s.id=m.erp_source_id WHERE m.project_slug=? AND s.code IN ('SGC','ZALMI')");$q->execute([$project]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);if(!$rows)throw new \InvalidArgumentException('No reporting segment matches this project.');foreach($rows as$r)$out[$r['code']]['pairs'][]=['head_id'=>(string)$r['erp_head_id'],'subhead_id'=>(string)$r['erp_subhead_id']];}
        }return $out;
    }
    private function readCache(string $file,string $key,int $now):?array
    {
        if(!is_file($file)||filesize($file)>2097152)return null;$entry=json_decode((string)file_get_contents($file),true);
        if(!is_array($entry)||($entry['key']??null)!==$key||!is_int($entry['saved_at']??null)||$entry['saved_at']>$now||$now-$entry['saved_at']>self::STALE_SECONDS||($entry['snapshot']['available']??null)!==true)return null;
        return $entry;
    }
    private function cached(array $entry,bool $stale):array
    {
        $result=$entry['snapshot'];$result['status']=$stale?'stale':'cached';$result['stale']=$stale;
        $result['message']=$stale?'Showing the last complete SGC + Zalmi report. One or more sources could not be refreshed.':'Complete SGC + Zalmi report.';return $result;
    }
    public function snapshot(array $filters=[],?DateTimeImmutable $now=null):array
    {
        $p=ErpMarketPeriods::make($now);$now=$p['now'];$epoch=$now->getTimestamp();
        $normalized=[];foreach(['project','sector','block','category']as$field){$value=$filters[$field]??'';if(!is_string($value)||strlen($value)>255)return self::unavailable($now,'Invalid reporting filter.');if($value!=='')$normalized[$field]=$value;}$filters=$normalized;
        $sources=$this->pdo->query("SELECT id,code,base_url,auth_type,credential_ref,is_active FROM erp_sources WHERE code IN ('SGC','ZALMI') ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
        if(count($sources)!==2||array_column($sources,'code')!==['SGC','ZALMI']||array_filter($sources,static fn($s)=>(int)$s['is_active']!==1))return self::unavailable($now,'Both SGC and Zalmi sources must be enabled and configured for combined reporting.');
        try{$requests=$this->requests($filters,$sources,$now);}catch(\InvalidArgumentException $e){return self::unavailable($now,$e->getMessage());}
        $fingerprint=$sources;foreach($fingerprint as&$source){try{$source['credential_digest']=hash('sha256',ErpMarketApi::credential($source));}catch(\Throwable $e){$source['credential_digest']='unavailable';}}unset($source);
        $key=hash('sha256',json_encode([self::REPORT_BASIS,self::DIMENSION_BASIS,self::SIZE_BASIS,self::SIZE_CHART_BASIS,self::CATEGORY_CHART_BASIS,self::MONTHLY_TYPE_BASIS,self::TYPE_LABEL_BASIS,$fingerprint,$filters,$p['today']->format('Y-m-d'),ErpMarketApi::env('erp.allowedHosts'),ErpMarketApi::env('erp.marketPaths'),ErpMarketApi::env('erp.publicAddressOverrides')],JSON_THROW_ON_ERROR));
        if(!is_dir($this->cacheDir)&&!@mkdir($this->cacheDir,0700,true)&&!is_dir($this->cacheDir))return self::unavailable($now);
        $file=$this->cacheDir.'/'.$key.'.json';$entry=$this->readCache($file,$key,$epoch);
        if($entry&&$epoch-$entry['saved_at']<self::FRESH_SECONDS)return $this->cached($entry,false);
        $failureFile=$file.'.retry';$retryState=is_file($failureFile)?json_decode((string)file_get_contents($failureFile),true):null;
        $retry=is_array($retryState)?($retryState['until']??0):(is_int($retryState)?$retryState:0);
        if($retry>$epoch)return $entry?$this->cached($entry,true):self::unavailable($now,'Combined SGC + Zalmi deal data is temporarily unavailable.');
        $lock=fopen($file.'.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);return $entry?$this->cached($entry,true):self::unavailable($now);}
        try{
            // Bound unauthenticated public filter traffic across ALL cache keys.
            $budget=fopen($this->cacheDir.'/refresh-budget.json','c+');if(!$budget||!flock($budget,LOCK_EX|LOCK_NB)){if($budget)fclose($budget);return $entry?$this->cached($entry,true):self::unavailable($now);}
            try{$window=json_decode(stream_get_contents($budget),true);if(!is_array($window)||$epoch-($window['start']??0)>=60||($window['start']??0)>$epoch)$window=['start'=>$epoch,'requests'=>0];
                if(($window['requests']??0)>=12)return $entry?$this->cached($entry,true):self::unavailable($now);
                $window['requests']++;rewind($budget);ftruncate($budget,0);fwrite($budget,json_encode($window,JSON_THROW_ON_ERROR));
            }finally{flock($budget,LOCK_UN);fclose($budget);}
            $reports=[];$failed=false;foreach($sources as$source){try{$payload=$requests[$source['code']];$data=is_callable($this->transport)?($this->transport)($source,$payload):$this->transport->fetch($source,$payload);$reports[$source['code']]=$this->validate($data,$source,$payload,$now);}catch(\Throwable $e){$failed=true;error_log('[ERP market] aggregate source='.$source['code'].' failure_type='.get_class($e));}}
            if($failed){file_put_contents($failureFile,json_encode(['until'=>$epoch+self::RETRY_SECONDS,'reason'=>'api']),LOCK_EX);return $entry?$this->cached($entry,true):self::unavailable($now,'Combined SGC + Zalmi deal data is temporarily unavailable.');}
            $result=self::unavailable($now);$result['available']=true;$result['status']='fresh';$result['last_updated']=$now->format(DATE_ATOM);$result['message']='Complete SGC + Zalmi report.';
            foreach(['today','last_month','month','chart_total']as$keyName)$result[$keyName]=0;$result['daily']=$reports['SGC']['daily'];$result['monthly']=$reports['SGC']['monthly'];
            foreach($result['daily']as$day=>&$n)$n+=$reports['ZALMI']['daily'][$day];unset($n);
            foreach($result['monthly']as$month=>&$n)$n+=$reports['ZALMI']['monthly'][$month];unset($n);
            $result['chart_total']=array_sum($result['daily']);$result['today']=$reports['SGC']['counts']['today']+$reports['ZALMI']['counts']['today'];$result['last_month']=$reports['SGC']['counts']['last_month']+$reports['ZALMI']['counts']['last_month'];$result['month']=$reports['SGC']['counts']['this_month']+$reports['ZALMI']['counts']['this_month'];
            if($reports['SGC']['sizes']['supported']&&$reports['ZALMI']['sizes']['supported']){
                $result['sizes']=array_fill_keys(['5 Marla','7 Marla','10 Marla','20 Marla'],0);
                foreach($reports as$report)foreach($report['sizes']['buckets']as$bucket){
                    if($bucket['unit']!=='Marla')continue;$label=$bucket['size'].' Marla';
                    if(array_key_exists($label,$result['sizes']))$result['sizes'][$label]+=$bucket['deals'];
                }
                $result['sizes_available']=true;$result['sizes_total']=array_sum($result['sizes']);
            }
            if ($reports['SGC']['categories']['supported'] && $reports['ZALMI']['categories']['supported']) {
                $result['categories'] = [];
                foreach ($reports as $code => $report) {
                    foreach ($report['categories']['buckets'] as $category => $deals) {
                        $label = strtolower(ErpPropertyClassification::displayType($category, $code));
                        $result['categories'][$label] = ($result['categories'][$label] ?? 0) + $deals;
                    }
                }
                ksort($result['categories']);
                $result['categories_available'] = true;
                $result['categories_total'] = array_sum($result['categories']);
            }
            foreach(['today','last_month','this_month']as$period)foreach(['sale','resale','check_in']as$kind)$result['deal_components'][$period][$kind]=$reports['SGC']['components'][$period][$kind]+$reports['ZALMI']['components'][$period][$kind];
            foreach($result['monthly']as$month=>$total){
                $sale=$reports['SGC']['monthly_components'][$month]['sale']+$reports['ZALMI']['monthly_components'][$month]['sale'];
                $resale=$reports['SGC']['monthly_components'][$month]['resale']+$reports['ZALMI']['monthly_components'][$month]['resale'];
                $checkins=$reports['SGC']['monthly_components'][$month]['check_in']+$reports['ZALMI']['monthly_components'][$month]['check_in'];
                $result['monthly_by_type'][$month]=['sale_deals'=>$sale+$resale,'rental_deals'=>$checkins];
            }
            $result['monthly_types_available']=true;
            $mappingRows=$this->pdo->query("SELECT s.code,m.erp_head_id,m.erp_subhead_id,m.project_slug FROM erp_project_mappings m JOIN erp_sources s ON s.id=m.erp_source_id WHERE s.code IN ('SGC','ZALMI')")->fetchAll(PDO::FETCH_ASSOC);$mapped=[];foreach($mappingRows as$m)$mapped[$m['code'].':'.$m['erp_head_id'].':'.$m['erp_subhead_id']]=$m['project_slug'];
            foreach($reports as$code=>$report){foreach($report['projects']as$pair=>$project){$token=$code.'/'.$project['head_id'].'/'.$project['subhead_id'];$result['projects'][$token]=['slug'=>$token,'name'=>$code.' · '.$project['head_name'].' / '.$project['subhead_name']];}
                foreach($report['breakdown']as$row){$project=$report['projects'][$row['head_id'].':'.$row['subhead_id']];$keyName=$code.':'.$row['head_id'].':'.$row['subhead_id'];
                    if($row['month']===$p['month']->format('Y-m')&&isset($mapped[$keyName]))$result['project_month_counts'][$mapped[$keyName]]=($result['project_month_counts'][$mapped[$keyName]]??0)+$row['deals'];
                    if($row['month']===$p['last_month']->format('Y-m'))$result['last_month_rows'][]=['source'=>$code,'project'=>$code.'/'.$row['head_id'].'/'.$row['subhead_id'],'project_name'=>$project['head_name'],'subhead_name'=>$project['subhead_name'],'category'=>strtolower(ErpPropertyClassification::displayType($row['category'], $code)),'deal_kind'=>$row['deal_kind'],'count'=>$row['deals']];
                }
            }
            $temp=$file.'.'.bin2hex(random_bytes(5)).'.tmp';if(file_put_contents($temp,json_encode(['key'=>$key,'saved_at'=>$epoch,'snapshot'=>$result],JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new \RuntimeException('Market cache write failed.');@chmod($temp,0600);if(!rename($temp,$file)){@unlink($temp);throw new \RuntimeException('Market cache commit failed.');}if(is_file($failureFile))unlink($failureFile);
            return $result;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
