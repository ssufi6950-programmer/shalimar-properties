<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';

$store=\App\Libraries\PortalCatalogStore::connect();
$mappings=$store->publicAgencyPerformanceDirectory();
$agencyOptions=[];
foreach($mappings as$row){$id=(int)$row['portal_agency_id'];$agencyOptions[$id]=['id'=>$id,'name'=>(string)$row['agency_name']];}
uasort($agencyOptions,static fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
$allAgencyIds=array_map('intval',array_keys($agencyOptions));
$hasCompanyFilter=isset($_GET['company_filter']);
$requestedCompanies=$_GET['companies']??null;
$selectedAgencyIds=$hasCompanyFilter&&is_array($requestedCompanies)?array_values(array_unique(array_filter(array_map(static fn($id)=>is_string($id)&&preg_match('/\A[1-9][0-9]{0,17}\z/D',$id)?(int)$id:0,$requestedCompanies),static fn($id)=>isset($agencyOptions[$id])))):$allAgencyIds;
$selectedSet=array_fill_keys($selectedAgencyIds,true);
$purpose=$_GET['purpose']??'all';if(!is_string($purpose)||!in_array($purpose,['all','sale','rent'],true))$purpose='all';
$top=$_GET['top']??'10';
if(!is_string($top)||!in_array($top,['5','10','15'],true))$top='10';
$top=(int)$top;
$pkt=new DateTimeZone('Asia/Karachi');
$today=new DateTimeImmutable('today',$pkt);
$thisMonthFrom=$today->modify('first day of this month');
$periodPresets=[
    'this_month'=>['This month',$thisMonthFrom,$today],
    'last_30'=>['Last 30 days',$today->modify('-29 days'),$today],
    'last_month'=>['Last month',$thisMonthFrom->modify('-1 month'),$thisMonthFrom->modify('-1 day')],
    'this_year'=>['This year',$today->setDate((int)$today->format('Y'),1,1),$today],
];
$range=$_GET['range']??'this_month';if(!is_string($range)||(!isset($periodPresets[$range])&&$range!=='custom'))$range='this_month';
$dateFrom=$periodPresets['this_month'][1];$dateTo=$periodPresets['this_month'][2];$periodLabel='This month';
if(isset($periodPresets[$range])){[$periodLabel,$dateFrom,$dateTo]=$periodPresets[$range];}
elseif($range==='custom'){
    $fromRaw=$_GET['date_from']??'';$toRaw=$_GET['date_to']??'';
    $parseDate=static function($value)use($pkt):?DateTimeImmutable{if(!is_string($value)||!preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D',$value))return null;$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,$pkt);return $date&&$date->format('Y-m-d')===$value?$date:null;};
    $customFrom=$parseDate($fromRaw);$customTo=$parseDate($toRaw);
    if($customFrom&&$customTo&&$customFrom<=$customTo&&$customTo<=$today&&$customFrom>=$today->modify('-2 years')){$dateFrom=$customFrom;$dateTo=$customTo;$periodLabel='Custom range';}
    else{$range='this_month';}
}
$dateFromValue=$dateFrom->format('Y-m-d');$dateToValue=$dateTo->format('Y-m-d');
$periodDisplay=$dateFrom->format('d M Y').' – '.$dateTo->format('d M Y');
$totalsByAgency=[];foreach($selectedAgencyIds as$id)$totalsByAgency[$id]=['id'=>$id,'name'=>$agencyOptions[$id]['name'],'sales'=>0,'sale_parties'=>0,'rentals'=>0];
$sourceGroups=[];
foreach($mappings as$row){$id=(int)$row['portal_agency_id'];if(isset($selectedSet[$id]))$sourceGroups[$row['code']][]=$row;}
$failedSources=[];$succeededSources=[];
foreach($sourceGroups as$code=>$rows){
    $source=$rows[0];$erpIds=array_values(array_unique(array_map(static fn($row)=>(string)$row['erp_agency_id'],$rows)));
    try{
        $summary=(new \App\Services\ErpAgencyPerformanceApi())->summaries($source,$erpIds,$purpose,$dateFromValue,$dateToValue);$succeededSources[]=$code;
        foreach($rows as$row){$portalId=(int)$row['portal_agency_id'];$erpId=(string)$row['erp_agency_id'];if(!isset($totalsByAgency[$portalId],$summary[$erpId]))throw new \RuntimeException('ERP performance response omitted a mapped agency.');
            if($summary[$erpId]['sales_bookings']!==null)$totalsByAgency[$portalId]['sales']+=$summary[$erpId]['sales_bookings'];
            if($summary[$erpId]['sale_party_entries']!==null)$totalsByAgency[$portalId]['sale_parties']+=$summary[$erpId]['sale_party_entries'];
            if($summary[$erpId]['rentals']!==null)$totalsByAgency[$portalId]['rentals']+=$summary[$erpId]['rentals'];
        }
    }catch(\Throwable$error){
        error_log('[Public agency performance] source='.preg_replace('/[^A-Z0-9_-]/','',(string)$code).' unavailable type='.get_class($error).' reason='.preg_replace('/[^A-Za-z0-9 .:_-]/','',substr($error->getMessage(),0,180)));
        $failedSources[]=$code;
    }
}
$ranking=array_values($totalsByAgency);
usort($ranking,static fn($a,$b)=>($b['sales']+$b['rentals'])<=>($a['sales']+$a['rentals'])?:strnatcasecmp($a['name'],$b['name']));
$topRows=array_slice($ranking,0,$top);
$grandSales=array_sum(array_column($ranking,'sales'));$grandRentals=array_sum(array_column($ranking,'rentals'));
$grandActivities=$grandSales+$grandRentals;
$previewMode=$failedSources!==[];
$chartData=['labels'=>array_column($topRows,'name'),'sales'=>array_column($topRows,'sales'),'rentals'=>array_column($topRows,'rentals'),'total'=>array_map(static fn($row)=>$row['sales']+$row['rentals'],$topRows)];
$chartJson=json_encode($chartData,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);

$active='agency-performance';$pageTitle='Agency Performance - Shalimar Properties';
$metaDesc='Compare verified sales and rental activity for authorized Shalimar Properties agencies.';
require __DIR__.'/includes/header.php';
?>
<style>
.agency-performance-page{--ap-green:#155f43;--ap-deep:#0c3828;--ap-gold:#c6a15e;--ap-line:#e2e9e3;--ap-muted:#68766e}
.agency-performance-hero{padding:calc(var(--hdr,80px) + 20px) 0 38px;background:radial-gradient(ellipse at 82% 0%,rgba(198,161,94,.2),transparent 38%),linear-gradient(125deg,#0c3828,#155f43 58%,#1b7050);color:#fff;overflow:hidden;position:relative}.agency-performance-hero:after{content:"";position:absolute;inset:0;background-image:linear-gradient(30deg,transparent 49%,rgba(255,255,255,.035) 50%,transparent 51%);background-size:42px 42px;pointer-events:none}.agency-performance-hero .wrap{position:relative;z-index:1}.agency-performance-hero .crumbs{color:rgba(255,255,255,.68)}.agency-performance-hero .crumbs a{color:#fff}.agency-performance-hero .kicker{display:inline-flex;color:#e5c987;margin-top:20px}.agency-performance-hero h1{max-width:760px;color:#fff;font-size:clamp(32px,5vw,54px);line-height:1.08;margin:12px 0 14px;letter-spacing:-.035em}.agency-performance-hero .lede{max-width:700px;color:rgba(255,255,255,.8);font-size:16px;line-height:1.7;margin:0}
.ap-content{padding:30px 0 64px}.ap-filter{display:grid;grid-template-columns:minmax(300px,1.55fr) minmax(190px,.8fr) auto;gap:16px;align-items:end;padding:22px;background:#fff;border:1px solid var(--ap-line);border-radius:18px;box-shadow:0 12px 36px rgba(23,54,37,.06)}.ap-field{min-width:0}.ap-field label{display:block;font-size:12px;font-weight:700;color:#526159;margin:0 0 7px}.ap-field select,.ap-multi-trigger{width:100%;height:48px;border:1px solid #dce5dd;border-radius:11px;background:#fff;padding:0 13px;color:#1b3428;font:500 14px Inter,system-ui,sans-serif}.ap-filter>.btn{min-height:48px;padding-inline:22px;white-space:nowrap}.ap-multiselect{position:relative}.ap-multi-trigger{display:flex;justify-content:space-between;align-items:center;text-align:left;cursor:pointer}.ap-multi-trigger:hover,.ap-field select:focus,.ap-multi-trigger:focus{border-color:#75a28b;outline:3px solid rgba(21,95,67,.1)}.ap-multi-menu{--ap-green:#155f43;--ap-line:#e2e9e3;position:fixed;z-index:10000;top:12px;left:12px;width:min(440px,calc(100vw - 24px));max-height:calc(100vh - 24px);overflow:hidden;padding:13px;background:#fff;border:1px solid var(--ap-line);border-radius:15px;box-shadow:0 18px 45px rgba(16,46,31,.24)}.ap-multi-menu[hidden]{display:none}.ap-search{width:100%;height:40px;border:1px solid #dce5dd;border-radius:9px;padding:0 11px;font:14px Inter,system-ui,sans-serif}.ap-multi-actions{display:flex;justify-content:space-between;gap:8px;padding:10px 1px;font-size:12px}.ap-multi-actions button{border:0;background:none;color:var(--ap-green);font:600 12px Inter,system-ui,sans-serif;cursor:pointer;padding:4px}.ap-company-list{max-height:min(270px,42vh);overflow:auto;border-top:1px solid #edf1ed;padding-top:5px}.ap-company-option{display:flex;align-items:center;gap:9px;padding:8px 5px;border-radius:7px;color:#34463c;font-size:13px;cursor:pointer}.ap-company-option:hover{background:#f4f7f4}.ap-company-option input{width:16px;height:16px;accent-color:var(--ap-green)}
.ap-table-wrap{overflow:auto;background:#fff;border:1px solid var(--ap-line);border-radius:16px;box-shadow:0 7px 22px rgba(23,54,37,.04)}.ap-rank-table{border-collapse:collapse;width:100%;min-width:560px}.ap-rank-table th,.ap-rank-table td{padding:13px 16px;border-bottom:1px solid #edf1ed;text-align:left;font-size:13px}.ap-rank-table th{background:#f6f8f5;color:#68766e;font-size:10px;letter-spacing:.07em;text-transform:uppercase}.ap-rank-table tbody tr:last-child td{border-bottom:0}.ap-rank-table tbody tr:hover{background:#fbfcfa}.ap-rank-table td:first-child{color:#879189;width:55px}.ap-rank-table td:nth-child(2){font-weight:650;color:#1d3e2e}.ap-rank-table .num{text-align:right;font-variant-numeric:tabular-nums}.ap-rank-table tfoot td{font-weight:700;background:#f8faf7;color:#173a2b}.ap-source-tag.pending{color:#805a16;background:#fff2d8}
.ap-empty,.ap-alert{display:flex;align-items:flex-start;gap:12px;border-radius:14px;padding:16px 18px;font-size:13px;line-height:1.6;margin-top:16px}.ap-empty{border:1px dashed #cbd8ce;color:#66756c;background:#fff}.ap-alert{border:1px solid #ecd8b7;color:#644b2a;background:linear-gradient(110deg,#fffaf0,#fff);box-shadow:0 7px 20px rgba(91,69,25,.05)}.ap-alert:before{content:"!";flex:none;display:grid;place-items:center;width:22px;height:22px;border-radius:50%;background:#f3e5c8;color:#805a16;font-size:13px;font-weight:800}
.ap-rank-choice{grid-column:1/-1;display:flex;align-items:center;gap:12px;padding-top:2px}.ap-rank-choice>span{font-size:12px;font-weight:700;color:#526159;white-space:nowrap}.ap-rank-options{display:inline-flex;padding:4px;background:#f1f5f1;border:1px solid #e0e8e1;border-radius:12px}.ap-rank-options label{position:relative;cursor:pointer}.ap-rank-options input{position:absolute;opacity:0;pointer-events:none}.ap-rank-options span{display:block;min-width:70px;padding:8px 13px;border-radius:8px;text-align:center;color:#617068;font-size:12px;font-weight:700;transition:background .18s,color .18s,box-shadow .18s}.ap-rank-options input:checked+span{background:#155f43;color:#fff;box-shadow:0 4px 12px rgba(21,95,67,.2)}.ap-rank-options input:focus-visible+span{outline:3px solid rgba(21,95,67,.2);outline-offset:2px}.ap-rank-options label:hover span{color:#155f43}.ap-rank-options label:hover input:checked+span{color:#fff}
.ap-filter{grid-template-columns:minmax(260px,1.35fr) minmax(170px,.65fr) minmax(230px,.85fr) auto;border-radius:12px;box-shadow:0 8px 24px rgba(23,54,37,.055)}.ap-date-picker{position:relative}.ap-date-trigger{width:100%;height:48px;display:flex;align-items:center;gap:11px;text-align:left;padding:0 13px;border:1px solid #dce5dd;border-radius:9px;background:#fff;color:#1b3428;cursor:pointer}.ap-date-trigger>i:first-child{color:#155f43}.ap-date-trigger>i:last-child{margin-left:auto;color:#758178;font-size:11px}.ap-date-trigger span{min-width:0}.ap-date-trigger b,.ap-date-trigger small{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ap-date-trigger b{font:650 13px Inter,system-ui,sans-serif}.ap-date-trigger small{margin-top:2px;color:#758178;font:11px Inter,system-ui,sans-serif}.ap-date-trigger:hover,.ap-date-trigger:focus{border-color:#75a28b;outline:3px solid rgba(21,95,67,.1)}.ap-date-menu{--ap-green:#155f43;--ap-line:#dfe7e0;position:fixed;z-index:10001;width:min(540px,calc(100vw - 24px));padding:0;background:#fff;border:1px solid var(--ap-line);border-radius:10px;box-shadow:0 22px 55px rgba(16,46,31,.24);overflow:hidden}.ap-date-menu[hidden]{display:none}.ap-date-head{display:flex;align-items:center;justify-content:space-between;padding:15px 17px;border-bottom:1px solid #e7ece8}.ap-date-head strong{font-size:14px;color:#173a2b}.ap-date-head span{font-size:11px;color:#7b877f}.ap-date-body{display:grid;grid-template-columns:170px 1fr}.ap-date-presets{padding:10px;border-right:1px solid #e7ece8;background:#f7f9f6}.ap-date-preset{display:block;width:100%;padding:10px 11px;border:0;border-radius:7px;background:transparent;color:#45564c;text-align:left;font:600 12px Inter,system-ui,sans-serif;cursor:pointer}.ap-date-preset:hover,.ap-date-preset.active{background:#e7f1eb;color:#155f43}.ap-date-custom{padding:17px}.ap-date-custom>span{display:block;margin-bottom:11px;color:#405147;font-size:12px;font-weight:700}.ap-date-inputs{display:grid;grid-template-columns:1fr 1fr;gap:10px}.ap-date-inputs label{font-size:10px;font-weight:700;color:#77837b;text-transform:uppercase;letter-spacing:.05em}.ap-date-inputs input{display:block;width:100%;height:42px;margin-top:6px;padding:0 9px;border:1px solid #d9e2da;border-radius:7px;color:#233d30;font:13px Inter,system-ui,sans-serif}.ap-date-apply{width:100%;height:40px;margin-top:14px;border:0;border-radius:7px;background:#155f43;color:#fff;font:700 12px Inter,system-ui,sans-serif;cursor:pointer}.ap-report-head{display:flex;justify-content:space-between;align-items:end;gap:18px;padding:27px 2px 14px}.ap-report-head span{display:block;color:#8a7042;font-size:10px;font-weight:800;letter-spacing:.13em;text-transform:uppercase}.ap-report-head h2{margin:6px 0 0;color:#173a2b;font-size:23px}.ap-period-chip{display:inline-flex!important;align-items:center;gap:7px;padding:8px 11px;border:1px solid #dce5dd;border-radius:8px;background:#fff!important;color:#526159!important;font-size:11px!important;letter-spacing:0!important;text-transform:none!important}.ap-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 14px}.ap-kpi{padding:18px 19px;background:#fff;border:1px solid #dfe7e0;border-radius:10px;box-shadow:0 4px 14px rgba(23,54,37,.035)}.ap-kpi span{display:block;color:#718078;font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.ap-kpi b{display:block;margin-top:8px;color:#155f43;font-size:30px;line-height:1.1;font-variant-numeric:tabular-nums}.ap-kpi small{display:block;margin-top:7px;color:#829087;font-size:11px}.ap-chart-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);gap:12px}.ap-chart-card{min-width:0;padding:18px;background:#fff;border:1px solid #dfe7e0;border-radius:10px;box-shadow:0 4px 14px rgba(23,54,37,.035)}.ap-chart-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding-bottom:13px;border-bottom:1px solid #edf1ed;margin-bottom:13px}.ap-chart-head h2{margin:0;color:#173a2b;font-size:16px}.ap-chart-head p{margin:4px 0 0;color:#7b877f;font-size:11px}.ap-chart-box{height:290px;position:relative}.ap-summary-row{display:flex;justify-content:space-between;align-items:end;gap:16px;margin:28px 0 12px}.ap-summary-row h2{margin:0;color:#173a2b;font-size:20px}.ap-summary-row p{margin:5px 0 0;color:#748078;font-size:12px}.ap-source-tags{display:flex;gap:7px;flex-wrap:wrap}.ap-source-tag{padding:6px 9px;border-radius:7px;background:#eaf3ed;color:#155f43;font-size:10px;font-weight:800}.ap-footnote{margin:15px 2px 0;color:#748078;font-size:11px;line-height:1.6}
@media(max-width:1050px){.ap-filter{grid-template-columns:1fr 1fr}.ap-filter>.btn{justify-self:end}.ap-rank-choice{grid-column:1/-1}.ap-chart-grid{grid-template-columns:1fr}.ap-chart-box{height:270px}}
@media(max-width:900px){.ap-filter>.btn{grid-column:1/-1}.ap-report-head{align-items:flex-start}.ap-chart-grid{grid-template-columns:1fr}.ap-chart-box{height:270px}}
@media(max-width:600px){.agency-performance-hero{padding:calc(var(--hdr,72px) + 20px) 0 27px}.agency-performance-hero h1{font-size:32px}.agency-performance-hero .lede{font-size:14px}.ap-content{padding:18px 0 40px}.ap-filter{grid-template-columns:1fr;gap:13px;padding:15px;border-radius:10px}.ap-filter>.btn{grid-column:auto;justify-self:stretch;width:100%}.ap-field label{font-size:11px}.ap-field select,.ap-multi-trigger{height:46px;font-size:14px}.ap-rank-choice{display:block}.ap-rank-choice>span{display:block;margin-bottom:7px}.ap-rank-options{display:grid;grid-template-columns:repeat(3,1fr);width:100%}.ap-rank-options span{min-width:0;padding-inline:8px}.ap-date-body{grid-template-columns:1fr}.ap-date-presets{display:grid;grid-template-columns:1fr 1fr;border-right:0;border-bottom:1px solid #e7ece8}.ap-date-custom{padding:14px}.ap-report-head{display:block;padding-top:22px}.ap-period-chip{margin-top:10px}.ap-kpis{gap:8px}.ap-kpi{padding:13px 11px}.ap-kpi span{font-size:9px;letter-spacing:.03em}.ap-kpi b{font-size:23px}.ap-kpi small{font-size:10px}.ap-chart-grid{gap:10px}.ap-chart-card{padding:14px 12px}.ap-chart-head h2{font-size:15px}.ap-chart-head p{font-size:10px}.ap-chart-box{height:240px}.ap-summary-row{display:block;margin:23px 0 12px}.ap-summary-row h2{font-size:18px}.ap-source-tags{margin-top:9px}}
@media(max-width:360px){.ap-kpi{padding:11px 8px}.ap-kpi b{font-size:20px}.ap-chart-box{height:220px}}
</style>
<main id="main" class="agency-performance-page">
  <section class="agency-performance-hero"><div class="wrap"><div class="crumbs"><a href="home">Home</a><span>/</span><a href="agencies">Agencies</a><span>/</span><span>Performance</span></div><span class="kicker">Agency intelligence</span><h1>Performance backed by ERP records.</h1><p class="lede">Compare authorized agency sales and rental activity from SGC and Zalmi ERP records. Select companies to focus the report or explore the top performers.</p></div></section>
  <section class="ap-content"><div class="wrap">
    <form class="ap-filter" method="get" action="agency-performance" id="apFilter" aria-label="Filter agency performance">
      <input type="hidden" name="company_filter" value="1">
      <input type="hidden" name="range" id="apRange" value="<?= e($range) ?>">
      <input type="hidden" name="date_from" id="apDateFrom" value="<?= e($dateFromValue) ?>">
      <input type="hidden" name="date_to" id="apDateTo" value="<?= e($dateToValue) ?>">
      <div class="ap-field"><label id="apCompanyLabel">Companies</label><div class="ap-multiselect">
        <button class="ap-multi-trigger" type="button" id="apMultiToggle" aria-haspopup="true" aria-expanded="false" aria-labelledby="apCompanyLabel apMultiText"><span id="apMultiText">All companies</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
        <div class="ap-multi-menu" id="apMultiMenu" hidden>
          <input class="ap-search" id="apCompanySearch" type="search" placeholder="Search companies" autocomplete="off" aria-label="Search companies">
          <div class="ap-multi-actions"><button type="button" id="apSelectAll">Select all</button><button type="button" id="apSelectNone">Clear selection</button></div>
          <div class="ap-company-list" id="apCompanyList">
            <?php foreach($agencyOptions as$option): ?><label class="ap-company-option" data-company-option><input type="checkbox" name="companies[]" value="<?= (int)$option['id'] ?>"<?= isset($selectedSet[$option['id']])?' checked':'' ?>><span><?= e($option['name']) ?></span></label><?php endforeach; ?>
          </div>
        </div>
      </div></div>
      <div class="ap-field"><label for="apPurpose">Activity</label><select name="purpose" id="apPurpose"><option value="all"<?= $purpose==='all'?' selected':'' ?>>Sales and rentals</option><option value="sale"<?= $purpose==='sale'?' selected':'' ?>>Sales</option><option value="rent"<?= $purpose==='rent'?' selected':'' ?>>Rentals</option></select></div>
      <div class="ap-field ap-period-field"><label id="apPeriodLabel">Reporting period</label><div class="ap-date-picker">
        <button class="ap-date-trigger" type="button" id="apDateToggle" aria-haspopup="dialog" aria-expanded="false" aria-labelledby="apPeriodLabel apDateText"><i class="fa-regular fa-calendar" aria-hidden="true"></i><span id="apDateText"><b><?= e($periodLabel) ?></b><small><?= e($periodDisplay) ?></small></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
        <div class="ap-date-menu" id="apDateMenu" role="dialog" aria-label="Choose reporting date range" hidden>
          <div class="ap-date-head"><strong>Date range</strong><span>Asia/Karachi</span></div>
          <div class="ap-date-body">
            <div class="ap-date-presets">
              <?php foreach($periodPresets as$key=>[$label,$from,$to]): ?><button type="button" class="ap-date-preset<?= $range===$key?' active':'' ?>" data-range="<?= e($key) ?>" data-date-from="<?= e($from->format('Y-m-d')) ?>" data-date-to="<?= e($to->format('Y-m-d')) ?>" data-label="<?= e($label) ?>"><?= e($label) ?></button><?php endforeach; ?>
              <button type="button" class="ap-date-preset<?= $range==='custom'?' active':'' ?>" data-custom-range>Custom range</button>
            </div>
            <div class="ap-date-custom"><span>Custom range</span><div class="ap-date-inputs"><label>From<input type="date" id="apCustomFrom" value="<?= e($dateFromValue) ?>" max="<?= e($today->format('Y-m-d')) ?>"></label><label>To<input type="date" id="apCustomTo" value="<?= e($dateToValue) ?>" max="<?= e($today->format('Y-m-d')) ?>"></label></div><button type="button" class="ap-date-apply" id="apDateApply">Apply custom range</button></div>
          </div>
        </div>
      </div></div>
      <button class="btn btn-solid" type="submit"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Apply filters</button>
      <div class="ap-rank-choice" role="group" aria-labelledby="apRankLabel">
        <span id="apRankLabel">Show ranked companies</span>
        <div class="ap-rank-options">
          <?php foreach([5,10,15] as$rankSize): ?><label><input type="radio" name="top" value="<?= $rankSize ?>"<?= $top===$rankSize?' checked':'' ?>><span>Top <?= $rankSize ?></span></label><?php endforeach; ?>
        </div>
      </div>
    </form>
    <?php if(!$selectedAgencyIds): ?><div class="ap-empty" role="status">Select at least one company to display the performance charts.</div><?php elseif(!$agencyOptions): ?><div class="ap-empty" role="status">No active mapped agencies are available.</div><?php else: ?>
      <div class="ap-report-head"><div><span>Agency performance report</span><h2>Verified activity overview</h2></div><span class="ap-period-chip"><i class="fa-regular fa-calendar" aria-hidden="true"></i><?= e($periodDisplay) ?></span></div>
      <section class="ap-kpis" aria-label="Selected agency totals">
        <?php if($purpose!=='rent'): ?><article class="ap-kpi"><span>Sale bookings</span><b><?= number_format($grandSales) ?></b><small>Across <?= count($selectedAgencyIds) ?> selected companies</small></article><?php endif; ?>
        <?php if($purpose==='sale'): ?><article class="ap-kpi"><span>Seller / buyer entries</span><b><?= number_format(array_sum(array_column($ranking,'sale_parties'))) ?></b><small>Agency linked ledger entries</small></article><?php endif; ?>
        <?php if($purpose!=='sale'): ?><article class="ap-kpi"><span>Rental check-ins</span><b><?= number_format($grandRentals) ?></b><small>Across <?= count($selectedAgencyIds) ?> selected companies</small></article><?php endif; ?>
        <article class="ap-kpi"><span>Total activity</span><b><?= number_format($grandActivities) ?></b><small>Bookings plus rental check-ins</small></article>
      </section>
      <div class="ap-chart-grid">
        <article class="ap-chart-card"><div class="ap-chart-head"><div><h2>Sales and rentals by company</h2><p>Line comparison · selected top <?= min($top,count($ranking)) ?> companies<?= $previewMode?' · preview values':'' ?></p></div><i class="fa-solid fa-chart-line" style="color:#155f43" aria-hidden="true"></i></div><div class="ap-chart-box"><canvas id="apLineChart" role="img" aria-label="Line chart comparing sales bookings and rental check-ins by company"></canvas></div></article>
        <article class="ap-chart-card"><div class="ap-chart-head"><div><h2>Top agency activity</h2><p>Bar ranking · bookings plus check-ins<?= $previewMode?' · preview values':'' ?></p></div><i class="fa-solid fa-chart-column" style="color:#c6a15e" aria-hidden="true"></i></div><div class="ap-chart-box"><canvas id="apBarChart" role="img" aria-label="Bar chart ranking agency activity"></canvas></div></article>
      </div>
      <div class="ap-summary-row"><div><h2>Top <?= $top ?> companies</h2><p>Ranked by sale bookings plus rental check-ins. Totals above include every selected company.</p></div><div class="ap-source-tags"><?php foreach($succeededSources as$code): ?><span class="ap-source-tag"><?= e($code) ?> ERP · verified</span><?php endforeach; ?><?php foreach($failedSources as$code): ?><span class="ap-source-tag pending"><?= e($code) ?> ERP · preview</span><?php endforeach; ?></div></div>
      <div class="ap-table-wrap"><table class="ap-rank-table"><thead><tr><th>#</th><th>Company</th><?php if($purpose!=='rent'): ?><th class="num">Sale bookings</th><?php endif; ?><?php if($purpose!=='sale'): ?><th class="num">Rental check-ins</th><?php endif; ?><th class="num">Total activity</th></tr></thead><tbody>
        <?php foreach($topRows as$index=>$row): ?><tr><td><?= $index+1 ?></td><td><?= e($row['name']) ?></td><?php if($purpose!=='rent'): ?><td class="num"><?= number_format($row['sales']) ?></td><?php endif; ?><?php if($purpose!=='sale'): ?><td class="num"><?= number_format($row['rentals']) ?></td><?php endif; ?><td class="num"><?= number_format($row['sales']+$row['rentals']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <p class="ap-footnote"><?= $previewMode?'Zero values are shown only to preview the dashboard layout while ERP data is unavailable. ':'Counts come from agency-linked ERP sales bookings and rental check-ins. ' ?>The line chart compares company totals; it is not a date-wise trend because the performance endpoint currently supplies aggregate counts. Customer names, contact details and individual booking records are not published.</p>
    <?php endif; ?>
  </div></section>
</main>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(()=>{
  const toggle=document.getElementById('apMultiToggle'),menu=document.getElementById('apMultiMenu'),list=document.getElementById('apCompanyList'),text=document.getElementById('apMultiText');
  if(toggle&&menu&&list){
    document.body.appendChild(menu);
    const boxes=[...list.querySelectorAll('input[type="checkbox"]')],all=boxes.length;
    const update=()=>{const n=boxes.filter(x=>x.checked).length;text.textContent=n===all?'All companies':n===0?'No companies selected':`${n} companies selected`;};update();
    const placeMenu=()=>{const r=toggle.getBoundingClientRect(),w=Math.min(440,window.innerWidth-24);menu.style.width=`${w}px`;menu.style.left=`${Math.max(12,Math.min(r.left,window.innerWidth-w-12))}px`;menu.style.top=`${Math.max(12,Math.min(r.bottom+8,window.innerHeight-menu.offsetHeight-12))}px`;};
    toggle.addEventListener('click',()=>{menu.hidden=!menu.hidden;toggle.setAttribute('aria-expanded',String(!menu.hidden));if(!menu.hidden){placeMenu();document.getElementById('apCompanySearch')?.focus();}});
    document.getElementById('apSelectAll')?.addEventListener('click',()=>{boxes.forEach(x=>x.checked=true);update();});
    document.getElementById('apSelectNone')?.addEventListener('click',()=>{boxes.forEach(x=>x.checked=false);update();});
    boxes.forEach(x=>x.addEventListener('change',update));
    document.getElementById('apCompanySearch')?.addEventListener('input',e=>{const q=e.target.value.trim().toLocaleLowerCase();list.querySelectorAll('[data-company-option]').forEach(row=>row.hidden=!row.textContent.toLocaleLowerCase().includes(q));});
    document.addEventListener('click',e=>{if(!e.target.closest('.ap-multiselect')&&!e.target.closest('#apMultiMenu')){menu.hidden=true;toggle.setAttribute('aria-expanded','false');}});
    window.addEventListener('resize',()=>{if(!menu.hidden)placeMenu();});window.addEventListener('scroll',()=>{if(!menu.hidden){menu.hidden=true;toggle.setAttribute('aria-expanded','false');}},{passive:true});
    document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!menu.hidden){menu.hidden=true;toggle.setAttribute('aria-expanded','false');toggle.focus();}});
  }
  const dateToggle=document.getElementById('apDateToggle'),dateMenu=document.getElementById('apDateMenu'),filter=document.getElementById('apFilter');
  if(dateToggle&&dateMenu&&filter){
    document.body.appendChild(dateMenu);
    const placeDateMenu=()=>{const r=dateToggle.getBoundingClientRect(),w=Math.min(540,window.innerWidth-24);dateMenu.style.width=`${w}px`;dateMenu.style.left=`${Math.max(12,Math.min(r.left,window.innerWidth-w-12))}px`;dateMenu.style.top=`${Math.max(12,Math.min(r.bottom+8,window.innerHeight-dateMenu.offsetHeight-12))}px`;};
    const closeDateMenu=()=>{dateMenu.hidden=true;dateToggle.setAttribute('aria-expanded','false');};
    dateToggle.addEventListener('click',()=>{dateMenu.hidden=!dateMenu.hidden;dateToggle.setAttribute('aria-expanded',String(!dateMenu.hidden));if(!dateMenu.hidden)placeDateMenu();});
    dateMenu.querySelectorAll('[data-range]').forEach(button=>button.addEventListener('click',()=>{document.getElementById('apRange').value=button.dataset.range;document.getElementById('apDateFrom').value=button.dataset.dateFrom;document.getElementById('apDateTo').value=button.dataset.dateTo;filter.requestSubmit();}));
    dateMenu.querySelector('[data-custom-range]')?.addEventListener('click',()=>document.getElementById('apCustomFrom')?.focus());
    document.getElementById('apDateApply')?.addEventListener('click',()=>{const from=document.getElementById('apCustomFrom').value,to=document.getElementById('apCustomTo').value;if(!from||!to||from>to)return;document.getElementById('apRange').value='custom';document.getElementById('apDateFrom').value=from;document.getElementById('apDateTo').value=to;filter.requestSubmit();});
    document.addEventListener('click',e=>{if(!e.target.closest('.ap-date-picker')&&!e.target.closest('#apDateMenu'))closeDateMenu();});
    window.addEventListener('resize',()=>{if(!dateMenu.hidden)placeDateMenu();});window.addEventListener('scroll',()=>{if(!dateMenu.hidden)closeDateMenu();},{passive:true});
    document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!dateMenu.hidden){closeDateMenu();dateToggle.focus();}});
  }
  document.querySelectorAll('.ap-rank-options input[name="top"]').forEach(radio=>radio.addEventListener('change',()=>document.getElementById('apFilter')?.requestSubmit()));
  const data=<?= $chartJson ?>,lineCanvas=document.getElementById('apLineChart'),barCanvas=document.getElementById('apBarChart');if(!window.Chart||!lineCanvas||!barCanvas||!data.labels.length)return;
  Chart.defaults.font.family="Inter, system-ui, sans-serif";Chart.defaults.font.size=11;Chart.defaults.color='#68766e';Chart.defaults.maintainAspectRatio=false;
  const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
  const common={responsive:true,maintainAspectRatio:false,animation:reduce?false:{duration:650},plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:8,padding:16}},tooltip:{mode:'index',intersect:false}}};
  const lineDatasets=[];
  <?php if($purpose!=='rent'): ?>lineDatasets.push({label:'Sale bookings',data:data.sales,borderColor:'#155f43',backgroundColor:'rgba(21,95,67,.10)',pointBackgroundColor:'#155f43',pointRadius:3,tension:.34,fill:true});<?php endif; ?>
  <?php if($purpose!=='sale'): ?>lineDatasets.push({label:'Rental check-ins',data:data.rentals,borderColor:'#c6a15e',backgroundColor:'rgba(198,161,94,.08)',pointBackgroundColor:'#c6a15e',pointRadius:3,tension:.34,fill:true});<?php endif; ?>
  new Chart(lineCanvas,{type:'line',data:{labels:data.labels,datasets:lineDatasets},options:{...common,interaction:{mode:'nearest',axis:'x',intersect:false},scales:{x:{grid:{display:false},ticks:{maxRotation:35,minRotation:0,autoSkip:true}},y:{beginAtZero:true,grid:{color:'rgba(30,36,33,.07)'},ticks:{precision:0}}}}});
  new Chart(barCanvas,{type:'bar',data:{labels:data.labels,datasets:[{label:'Total activity',data:data.total,backgroundColor:data.total.map((_,i)=>i===0?'#c6a15e':'#155f43'),borderRadius:6,maxBarThickness:25}]},options:{...common,indexAxis:'y',plugins:{...common.plugins,legend:{display:false}},scales:{x:{beginAtZero:true,grid:{color:'rgba(30,36,33,.07)'},ticks:{precision:0}},y:{grid:{display:false}}}}});
})();
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
</body></html>
