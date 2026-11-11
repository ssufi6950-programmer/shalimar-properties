<?php
declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;
use PDO;

/** ERP-derived public aggregates only. Never reads portal listings, ownership or OTP records. */
final class ErpMarketIntelligenceService
{
    public const REPORT_BASIS='resale_transfer_date_v1';
    public const FRESH_SECONDS=300;
    public const STALE_SECONDS=1800;
    public const RETRY_SECONDS=60;
    private string $cacheDir;
    public function __construct(private PDO $pdo,private $transport=null,?string $cacheDir=null)
    {
        $this->transport??=new ErpMarketApi();$this->cacheDir=$cacheDir??(defined('WRITEPATH')?WRITEPATH:dirname(__DIR__,2).'/writable/').'cache/erp-market-intelligence';
    }
    public static function unavailable(?DateTimeImmutable $now=null,string $reason='Combined SGC + Zalmi transfer-date resale data is temporarily unavailable.'):array
    {
        $p=ErpMarketPeriods::make($now);
        return ['available'=>false,'activity_label'=>'ERP Resales by Transfer Date','today'=>null,'last_month'=>null,'month'=>null,
            'daily'=>[],'monthly'=>[],'chart_total'=>null,'yoy'=>null,'price_groups'=>[],'sizes'=>[],'last_month_rows'=>[],
            'projects'=>[],'project_month_counts'=>[],'price_method'=>'Actual completed resale consideration is not verified in these ERPs. Booking balances and transfer fees are not resale prices.',
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
    private function validate(array $data,array $source,array $request,DateTimeImmutable $now):array
    {
        if(($data['api_version']??null)!=='v1'||($data['source']??null)!==$source['code']||($data['timezone']??null)!=='Asia/Karachi'
            ||($data['complete']??null)!==true||($data['as_of']??null)!==$request['as_of']||($data['completion_basis']??null)!==self::REPORT_BASIS)throw new \RuntimeException('ERP market contract mismatch.');
        $p=ErpMarketPeriods::make($now);$expected=['today'=>[$p['today'],$p['tomorrow']],'last_month'=>[$p['last_month'],$p['month']],'this_month'=>[$p['month'],$p['next_month']]];$counts=[];
        foreach($expected as$key=>[$start,$end]){$period=$data['periods'][$key]??[];if(($period['start']??null)!==$start->format(DATE_ATOM)||($period['end']??null)!==$end->format(DATE_ATOM))throw new \RuntimeException('ERP market period mismatch.');$counts[$key]=self::count($period['completed_resales']??null);}
        $daily=[];for($i=29;$i>=0;$i--){$day=$p['today']->modify('-'.$i.' days')->format('Y-m-d');$daily[$day]=self::count($data['daily_deals'][$day]??null);}
        if(!is_array($data['daily_deals']??null)||array_keys($data['daily_deals'])!==array_keys($daily)||self::count($data['chart_total']??null)!==array_sum($daily)||end($daily)!==$counts['today'])throw new \RuntimeException('ERP market daily series mismatch.');
        $monthly=[];for($i=11;$i>=0;$i--){$month=$p['month']->modify('-'.$i.' months')->format('Y-m');$monthly[$month]=self::count($data['monthly_deals'][$month]??null);}
        if(!is_array($data['monthly_deals']??null)||array_keys($data['monthly_deals'])!==array_keys($monthly)||$monthly[$p['month']->format('Y-m')]!==$counts['this_month']||$monthly[$p['last_month']->format('Y-m')]!==$counts['last_month'])throw new \RuntimeException('ERP market monthly series mismatch.');
        $projects=$data['projects']??null;$breakdown=$data['breakdown']??null;
        if(!is_array($projects)||!array_is_list($projects)||count($projects)>500||!is_array($breakdown)||!array_is_list($breakdown)||count($breakdown)>6000)throw new \RuntimeException('ERP market dimensions invalid.');
        $safeProjects=[];foreach($projects as$row){foreach(['head_id','subhead_id']as$id)if(!is_string($row[$id]??null)||!preg_match('/\A[1-9][0-9]{0,17}\z/D',$row[$id]))throw new \RuntimeException('ERP market segment invalid.');$key=$row['head_id'].':'.$row['subhead_id'];if(isset($safeProjects[$key]))throw new \RuntimeException('Duplicate ERP market segment.');$safeProjects[$key]=['head_id'=>$row['head_id'],'subhead_id'=>$row['subhead_id'],'head_name'=>self::text($row['head_name']??null),'subhead_name'=>self::text($row['subhead_name']??null)];}
        $safeRows=[];$sums=array_fill_keys(array_keys($monthly),0);
        foreach($breakdown as$row){$pair=(string)($row['head_id']??'').':'.(string)($row['subhead_id']??'');$month=$row['month']??'';$category=$row['category']??null;
            if(!isset($safeProjects[$pair],$monthly[$month])||!is_string($category)||!in_array($category,['','shop','office','apartment','plot','house'],true))throw new \RuntimeException('ERP market breakdown invalid.');
            if(($request['category']??'')!==''&&$request['category']!==$category)throw new \RuntimeException('ERP market category scope mismatch.');
            if($request['pairs']!==null&&!array_filter($request['pairs'],static fn($p)=>(string)$p['head_id']===(string)$row['head_id']&&(string)$p['subhead_id']===(string)$row['subhead_id']))throw new \RuntimeException('ERP market project scope mismatch.');
            $key=$month.':'.$pair.':'.$category;if(isset($safeRows[$key]))throw new \RuntimeException('Duplicate ERP market breakdown.');$n=self::count($row['deals']??null);$sums[$month]+=$n;$safeRows[$key]=['month'=>$month,'head_id'=>$row['head_id'],'subhead_id'=>$row['subhead_id'],'category'=>$category,'deals'=>$n];
        }
        if($sums!==$monthly)throw new \RuntimeException('Incomplete ERP market breakdown.');
        if(($data['price_comparison']['supported']??null)!==false||($data['price_comparison']['basis']??null)!==null||($data['price_comparison']['change']??null)!==null)throw new \RuntimeException('Unverified ERP market price basis.');
        return ['counts'=>$counts,'daily'=>$daily,'monthly'=>$monthly,'projects'=>$safeProjects,'breakdown'=>array_values($safeRows)];
    }
    private function requests(array $filters,array $sources,DateTimeImmutable $now):array
    {
        if(!empty($filters['sector'])||!empty($filters['block']))throw new \InvalidArgumentException('Historical sector/block snapshots are not supported by the ERP report.');
        $category=$filters['category']??'';if(!is_string($category)||!in_array($category,['','shop','office','apartment','plot','house'],true))throw new \InvalidArgumentException('Unsupported ERP property category.');
        $out=[];foreach($sources as$s)$out[$s['code']]=['as_of'=>$now->format(DATE_ATOM),'pairs'=>null,'category'=>$category];
        $project=$filters['project']??'';if(!is_string($project))throw new \InvalidArgumentException('Invalid ERP report project.');
        if($project!==''){
            foreach($out as&$payload)$payload['pairs']=[];unset($payload);
            if(preg_match('~\A(SGC|ZALMI)/([1-9][0-9]{0,17})/([1-9][0-9]{0,17})\z~D',$project,$m))$out[$m[1]]['pairs']=[['head_id'=>$m[2],'subhead_id'=>$m[3]]];
            else{$q=$this->pdo->prepare("SELECT s.code,m.erp_head_id,m.erp_subhead_id FROM erp_project_mappings m JOIN erp_sources s ON s.id=m.erp_source_id WHERE m.project_slug=? AND s.code IN ('SGC','ZALMI')");$q->execute([$project]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);if(!$rows)throw new \InvalidArgumentException('No ERP reporting segment matches this project.');foreach($rows as$r)$out[$r['code']]['pairs'][]=['head_id'=>(string)$r['erp_head_id'],'subhead_id'=>(string)$r['erp_subhead_id']];}
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
        $result['message']=$stale?'Showing the last complete SGC + Zalmi ERP report. One or more sources could not be refreshed.':'Complete SGC + Zalmi ERP report.';return $result;
    }
    public function snapshot(array $filters=[],?DateTimeImmutable $now=null):array
    {
        $p=ErpMarketPeriods::make($now);$now=$p['now'];$epoch=$now->getTimestamp();
        $normalized=[];foreach(['project','sector','block','category']as$field){$value=$filters[$field]??'';if(!is_string($value)||strlen($value)>255)return self::unavailable($now,'Invalid reporting filter.');if($value!=='')$normalized[$field]=$value;}$filters=$normalized;
        $sources=$this->pdo->query("SELECT id,code,base_url,auth_type,credential_ref,is_active FROM erp_sources WHERE code IN ('SGC','ZALMI') ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
        if(count($sources)!==2||array_column($sources,'code')!==['SGC','ZALMI']||array_filter($sources,static fn($s)=>(int)$s['is_active']!==1))return self::unavailable($now,'Both SGC and Zalmi ERP sources must be enabled and configured for combined reporting.');
        try{$requests=$this->requests($filters,$sources,$now);}catch(\InvalidArgumentException $e){return self::unavailable($now,$e->getMessage());}
        $fingerprint=$sources;foreach($fingerprint as&$source){try{$source['credential_digest']=hash('sha256',ErpMarketApi::credential($source));}catch(\Throwable $e){$source['credential_digest']='unavailable';}}unset($source);
        $key=hash('sha256',json_encode([self::REPORT_BASIS,$fingerprint,$filters,$p['today']->format('Y-m-d'),ErpMarketApi::env('erp.allowedHosts'),ErpMarketApi::env('erp.marketPaths'),ErpMarketApi::env('erp.publicAddressOverrides')],JSON_THROW_ON_ERROR));
        if(!is_dir($this->cacheDir)&&!@mkdir($this->cacheDir,0700,true)&&!is_dir($this->cacheDir))return self::unavailable($now);
        $file=$this->cacheDir.'/'.$key.'.json';$entry=$this->readCache($file,$key,$epoch);
        if($entry&&$epoch-$entry['saved_at']<self::FRESH_SECONDS)return $this->cached($entry,false);
        $failureFile=$file.'.retry';$retryState=is_file($failureFile)?json_decode((string)file_get_contents($failureFile),true):null;
        $retry=is_array($retryState)?($retryState['until']??0):(is_int($retryState)?$retryState:0);
        if($retry>$epoch)return $entry?$this->cached($entry,true):self::unavailable($now,'Combined SGC + Zalmi transfer-date resale data is temporarily unavailable.');
        $lock=fopen($file.'.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);return $entry?$this->cached($entry,true):self::unavailable($now);}
        try{
            // Bound unauthenticated public filter traffic across ALL cache keys.
            $budget=fopen($this->cacheDir.'/refresh-budget.json','c+');if(!$budget||!flock($budget,LOCK_EX|LOCK_NB)){if($budget)fclose($budget);return $entry?$this->cached($entry,true):self::unavailable($now);}
            try{$window=json_decode(stream_get_contents($budget),true);if(!is_array($window)||$epoch-($window['start']??0)>=60||($window['start']??0)>$epoch)$window=['start'=>$epoch,'requests'=>0];
                if(($window['requests']??0)>=12)return $entry?$this->cached($entry,true):self::unavailable($now);
                $window['requests']++;rewind($budget);ftruncate($budget,0);fwrite($budget,json_encode($window,JSON_THROW_ON_ERROR));
            }finally{flock($budget,LOCK_UN);fclose($budget);}
            $reports=[];$failed=false;foreach($sources as$source){try{$payload=$requests[$source['code']];$data=is_callable($this->transport)?($this->transport)($source,$payload):$this->transport->fetch($source,$payload);$reports[$source['code']]=$this->validate($data,$source,$payload,$now);}catch(\Throwable $e){$failed=true;error_log('[ERP market] aggregate source='.$source['code'].' failure_type='.get_class($e));}}
            if($failed){file_put_contents($failureFile,json_encode(['until'=>$epoch+self::RETRY_SECONDS,'reason'=>'api']),LOCK_EX);return $entry?$this->cached($entry,true):self::unavailable($now,'Combined SGC + Zalmi transfer-date resale data is temporarily unavailable.');}
            $result=self::unavailable($now);$result['available']=true;$result['status']='fresh';$result['last_updated']=$now->format(DATE_ATOM);$result['message']='Complete SGC + Zalmi ERP report.';
            foreach(['today','last_month','month','chart_total']as$keyName)$result[$keyName]=0;$result['daily']=$reports['SGC']['daily'];$result['monthly']=$reports['SGC']['monthly'];
            foreach($result['daily']as$day=>&$n)$n+=$reports['ZALMI']['daily'][$day];unset($n);
            foreach($result['monthly']as$month=>&$n)$n+=$reports['ZALMI']['monthly'][$month];unset($n);
            $result['chart_total']=array_sum($result['daily']);$result['today']=$reports['SGC']['counts']['today']+$reports['ZALMI']['counts']['today'];$result['last_month']=$reports['SGC']['counts']['last_month']+$reports['ZALMI']['counts']['last_month'];$result['month']=$reports['SGC']['counts']['this_month']+$reports['ZALMI']['counts']['this_month'];
            $mappingRows=$this->pdo->query("SELECT s.code,m.erp_head_id,m.erp_subhead_id,m.project_slug FROM erp_project_mappings m JOIN erp_sources s ON s.id=m.erp_source_id WHERE s.code IN ('SGC','ZALMI')")->fetchAll(PDO::FETCH_ASSOC);$mapped=[];foreach($mappingRows as$m)$mapped[$m['code'].':'.$m['erp_head_id'].':'.$m['erp_subhead_id']]=$m['project_slug'];
            foreach($reports as$code=>$report){foreach($report['projects']as$pair=>$project){$token=$code.'/'.$project['head_id'].'/'.$project['subhead_id'];$result['projects'][$token]=['slug'=>$token,'name'=>$code.' · '.$project['head_name'].' / '.$project['subhead_name']];}
                foreach($report['breakdown']as$row){$project=$report['projects'][$row['head_id'].':'.$row['subhead_id']];$keyName=$code.':'.$row['head_id'].':'.$row['subhead_id'];
                    if($row['month']===$p['month']->format('Y-m')&&isset($mapped[$keyName]))$result['project_month_counts'][$mapped[$keyName]]=($result['project_month_counts'][$mapped[$keyName]]??0)+$row['deals'];
                    if($row['month']===$p['last_month']->format('Y-m'))$result['last_month_rows'][]=['source'=>$code,'project'=>$code.'/'.$row['head_id'].'/'.$row['subhead_id'],'project_name'=>$project['head_name'],'subhead_name'=>$project['subhead_name'],'category'=>$row['category'],'count'=>$row['deals']];
                }
            }
            $temp=$file.'.'.bin2hex(random_bytes(5)).'.tmp';if(file_put_contents($temp,json_encode(['key'=>$key,'saved_at'=>$epoch,'snapshot'=>$result],JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new \RuntimeException('Market cache write failed.');@chmod($temp,0600);if(!rename($temp,$file)){@unlink($temp);throw new \RuntimeException('Market cache commit failed.');}if(is_file($failureFile))unlink($failureFile);
            return $result;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
