<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';

$store=\App\Libraries\PortalCatalogStore::connect();
$mappings=$store->publicAgencyPerformanceDirectory();
$agencyOptions=[];
foreach($mappings as$row){$id=(int)$row['portal_agency_id'];$agencyOptions[$id]=['id'=>$id,'name'=>(string)$row['agency_name']];}
uasort($agencyOptions,static fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
$top=$_GET['top']??'5';
if(!is_string($top)||!in_array($top,['all','5','10','15'],true))$top='5';
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
$totalsByAgency=[];foreach($agencyOptions as$id=>$row)$totalsByAgency[$id]=['name'=>$row['name'],'sales'=>0,'rentals'=>0];
$sourceGroups=[];
foreach($mappings as$row)$sourceGroups[$row['code']][]=$row;
$failedSources=[];$succeededSources=[];
foreach($sourceGroups as$code=>$rows){
    $source=$rows[0];$erpIds=array_values(array_unique(array_map(static fn($row)=>(string)$row['erp_agency_id'],$rows)));
    try{
        $summary=(new \App\Services\ErpAgencyPerformanceApi())->summaries($source,$erpIds,'all',$dateFromValue,$dateToValue);$succeededSources[]=$code;
        foreach($rows as$row){$portalId=(int)$row['portal_agency_id'];$erpId=(string)$row['erp_agency_id'];if(!isset($totalsByAgency[$portalId],$summary[$erpId]))throw new \RuntimeException('ERP performance response omitted a mapped agency.');
            if($summary[$erpId]['sales_bookings']!==null)$totalsByAgency[$portalId]['sales']+=$summary[$erpId]['sales_bookings'];
            if($summary[$erpId]['rentals']!==null)$totalsByAgency[$portalId]['rentals']+=$summary[$erpId]['rentals'];
        }
    }catch(\Throwable$error){
        error_log('[Public agency performance] source='.preg_replace('/[^A-Z0-9_-]/','',(string)$code).' unavailable type='.get_class($error).' reason='.preg_replace('/[^A-Za-z0-9 .:_-]/','',substr($error->getMessage(),0,180)));
        $failedSources[]=$code;
    }
}
$ranking=array_values($totalsByAgency);
usort($ranking,static fn($a,$b)=>($b['sales']+$b['rentals'])<=>($a['sales']+$a['rentals'])?:strnatcasecmp($a['name'],$b['name']));
$topRows=$top==='all'?$ranking:array_slice($ranking,0,(int)$top);
$grandSales=array_sum(array_column($ranking,'sales'));$grandRentals=array_sum(array_column($ranking,'rentals'));
$grandActivities=$grandSales+$grandRentals;
$chartMax=$topRows ? max(1, $topRows[0]['sales']+$topRows[0]['rentals']) : 1;
$saleShare=$grandActivities ? 100 * $grandSales / $grandActivities : 0;

$fragment = ($_GET['_report'] ?? '') === '1';
$reportData = [
    'range'=>$range, 'top'=>$top, 'dateFrom'=>$dateFromValue, 'dateTo'=>$dateToValue,
    'periodLabel'=>$periodLabel, 'periodDisplay'=>$periodDisplay,
    'available'=>!empty($succeededSources),
    'chart'=>$topRows,
];
if ($fragment) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
}
if (!$fragment):
$active='agency-performance';$pageTitle='Agency Performance - Shalimar Properties';
$heroPhoto='ssc-business-bay';
$metaDesc='Compare verified sales and rental activity for authorized Shalimar Properties agencies.';
require __DIR__.'/includes/header.php';
?>
<style>
.agency-performance-page {
  --ap-ink:#132b22;
  --ap-muted:#50655b;
  --ap-green:#176449;
  --ap-gold:#bc812c;
  --ap-line:#dde8e0;
  --ap-surface:#f2f6f2;
  color:var(--ap-ink);
  background:var(--ap-surface);
}
.ap-hero {
  position:relative;
  overflow:hidden;
  padding:calc(var(--hdr,80px) + 44px) 0 78px;
  color:#fff;
  background:var(--hero-img) center/cover no-repeat,#0c3025;
}
.ap-hero::after {
  content:"";
  position:absolute;
  inset:0;
  pointer-events:none;
  background:
    linear-gradient(100deg,rgba(7,13,10,.88) 0%,rgba(14,75,49,.66) 55%,rgba(7,13,10,.4) 100%),
    repeating-linear-gradient(110deg,transparent 0 42px,#b9d9bd1e 43px,transparent 44px 85px);
}
.ap-hero-inner {position:relative;z-index:1;display:flex;align-items:flex-end;justify-content:space-between;gap:28px}
.ap-hero h1 {margin:10px 0 0;color:inherit;font-size:clamp(34px,4.4vw,58px);line-height:1.08;letter-spacing:-.04em}
.ap-hero p {margin:0;color:#d6e7dd;font-size:clamp(.95rem,2vw,1.08rem)}
.ap-crumbs {display:flex;align-items:center;gap:12px;font-size:.83rem;color:#d6e7dd}
.ap-crumbs a {color:inherit}
.ap-hero-period {flex:none;border-left:1px solid #a8d3bb65;padding-left:18px;white-space:nowrap}
.ap-hero-period span,.ap-hero-period time {display:block;font-size:.8rem;color:#c8ddd0}
.ap-hero-period strong {display:block;margin:3px 0;font-size:1.15rem;color:#fff}
.ap-body {position:relative;margin-top:-32px;padding-bottom:64px}
.ap-controls {position:relative;z-index:2;display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:16px 20px;background:#fff;border:1px solid var(--ap-line);border-radius:15px;box-shadow:0 8px 25px #122c2010}
.ap-control-label {display:block;margin:0 0 6px;padding:0;color:var(--ap-ink);font-size:.8rem;font-weight:700}
.ap-date-picker {position:relative;flex:0 1 310px}
.ap-date-trigger {display:flex;min-height:48px;align-items:center;gap:12px;width:100%;padding:5px 12px;text-align:left;background:#fff;color:var(--ap-ink);border:1px solid #cadacf;border-radius:9px;cursor:pointer}
.ap-date-trigger>i {color:var(--ap-green)}
.ap-date-trigger>i:last-child {margin-left:auto;font-size:.75rem}
#apDateText strong,#apDateText small {display:block}
#apDateText small {color:var(--ap-muted);font-size:.72rem}
.ap-date-menu {position:absolute;top:calc(100% + 8px);left:0;width:min(440px,calc(100vw - 44px));border:1px solid var(--ap-line);border-radius:12px;background:#fff;box-shadow:0 14px 30px #182d2630;overflow:hidden}
.ap-date-head {display:flex;justify-content:space-between;padding:12px 13px;border-bottom:1px solid var(--ap-line)}
.ap-date-head small,.ap-date-custom small {color:var(--ap-muted);font-size:.72rem}
.ap-date-body {display:grid;grid-template-columns:minmax(100px,.95fr) minmax(140px,1.3fr)}
.ap-date-presets,.ap-date-custom {display:grid;align-content:start;gap:5px;padding:10px}
.ap-date-presets {background:#f1f6f2}
.ap-date-presets button {border:1px solid transparent;background:transparent;color:var(--ap-ink);padding:8px;text-align:left;border-radius:7px;cursor:pointer}
.ap-date-presets button:hover,.ap-date-presets button[aria-pressed="true"] {background:#e0efe5;border-color:#cfdfd4;color:var(--ap-green)}
.ap-date-custom strong,.ap-date-custom label {font-size:.78rem}
.ap-date-custom input {min-width:0;width:100%;padding:6px 8px;color:var(--ap-ink);background:#fff;border:1px solid #cadacf;border-radius:7px}
.ap-rank-control {min-width:0;margin:0;padding:0;border:0}
.ap-rank-options {display:flex;flex-wrap:wrap;padding:4px;background:#edf4ef;border:1px solid var(--ap-line);border-radius:9px}
.ap-rank-options label {position:relative;cursor:pointer}
.ap-rank-options input {position:absolute;width:1px;height:1px;opacity:0}
.ap-rank-options span {display:block;padding:8px 12px;border-radius:6px;color:var(--ap-ink);font-size:.8rem;font-weight:650}
.ap-rank-options input:checked+span {background:var(--ap-green);color:#fff}
.ap-rank-options input:focus-visible+span {outline:2px solid var(--ap-green);outline-offset:2px}
.ap-update-status {margin:10px 0 0;color:var(--ap-muted);font-size:.84rem}
#apReport[aria-busy="true"] {opacity:.78}
.ap-line-panel {grid-column:1/-1}
.ap-line-box {position:relative;height:260px;margin-top:10px}
.agency-performance-page .ap-split-charts {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:24px}
.ap-bar-box {position:relative;height:260px;margin-top:10px}
@media(max-width:480px) {
  .ap-controls {gap:12px;padding:13px}
  .ap-date-picker {flex-basis:100%}
  .ap-line-box {height:220px}
  .ap-bar-box {height:220px}
}
.agency-performance-page .ap-kpis {padding:0;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:20px 0}
.ap-kpi,.ap-panel {min-width:0;background:#fff;border:1px solid var(--ap-line);border-radius:14px}
.ap-kpi {display:grid;gap:4px;padding:16px 18px}
.ap-kpi span,.ap-kpi small {color:var(--ap-muted);font-size:.8rem}
.ap-kpi strong {font-size:clamp(1.45rem,4vw,2.25rem);line-height:1.15;letter-spacing:-.04em;font-variant-numeric:tabular-nums}
.ap-kpi:first-child {background:#e4f1e8;border-color:#d4e9da}
.agency-performance-page .ap-charts {padding:0;display:grid;grid-template-columns:minmax(0,1.85fr) minmax(250px,1fr);gap:16px;margin-bottom:24px}
.ap-panel {padding:20px}
.ap-panel h2,.ap-table-panel h2 {margin:0;font-size:1.2rem;line-height:1.3}
.ap-panel p,.ap-table-panel p {margin:4px 0 0;color:var(--ap-muted);font-size:.83rem}
.ap-chart-list {display:grid;align-content:start;gap:12px;max-height:400px;overflow:auto;list-style:none;margin:18px 0 0;padding:0 6px 0 0;scrollbar-color:#afd0bd transparent}
.ap-chart-row {display:grid;grid-template-columns:minmax(105px,1.05fr) minmax(85px,1.4fr) 36px;align-items:center;gap:10px;min-height:23px;padding-bottom:8px;border-bottom:1px solid #eef3ef;font-size:.82rem}
.ap-chart-row:last-child {border-bottom:0}
.ap-chart-label {overflow-wrap:anywhere;line-height:1.25}
.ap-chart-total {font-weight:700;text-align:right;font-variant-numeric:tabular-nums}
.ap-track {height:10px;display:flex;gap:0;overflow:hidden;border-radius:20px;background:#edf3ee}
.ap-sale-fill,.ap-rent-fill {display:block;height:100%;flex:none}
.ap-sale-fill {background:var(--ap-green)}
.ap-rent-fill {background:var(--ap-gold)}
.ap-legend {display:flex;gap:14px;flex-wrap:wrap;list-style:none;padding:0;margin:15px 0 0}
.ap-legend li,.ap-mix-line {display:flex;align-items:center;gap:7px;color:var(--ap-muted);font-size:.8rem}
.ap-swatch {display:inline-block;width:9px;height:9px;border-radius:50%;background:var(--ap-green);flex:none}
.ap-swatch.rent {background:var(--ap-gold)}
.ap-donut {display:grid;place-items:center;width:min(164px,75%);aspect-ratio:1;margin:18px auto 14px;border-radius:50%;background:conic-gradient(var(--ap-green) 0 var(--ap-share),var(--ap-gold) var(--ap-share) 100%)}
.ap-donut.is-empty {background:#edf3ee}
.ap-donut-center {display:grid;place-content:center;place-items:center;width:76%;aspect-ratio:1;background:#fff;border-radius:50%}
.ap-donut-center strong {font-size:1.9rem;line-height:1.15}
.ap-donut-center small {color:var(--ap-muted);font-size:.72rem}
.ap-mix-line {justify-content:space-between;padding:9px 0;border-top:1px solid var(--ap-line)}
.ap-mix-line span {display:flex;align-items:center;gap:7px}
.ap-mix-line strong {color:var(--ap-ink)}
.ap-status {margin:15px 0;color:var(--ap-muted);font-size:.82rem}
.ap-alert {margin:17px 0;padding:12px 15px;border:1px solid #e4cc9e;border-radius:10px;color:#704d16;background:#fff8e9}
.agency-performance-page .ap-table-panel {padding:0;overflow:hidden}
.ap-table-head {padding:17px 20px}
.ap-table-wrap {overflow-x:auto}
.ap-rank-table {width:100%;min-width:520px;border-collapse:collapse;text-align:left}
.ap-rank-table th,.ap-rank-table td {padding:11px 15px;border-top:1px solid var(--ap-line)}
.ap-rank-table th {background:#edf4ef;color:#374e41;font-size:.76rem}
.ap-rank-table td {font-size:.88rem}
.ap-rank-table tbody tr:nth-child(even) {background:#f9fbf9}
.ap-rank-table .num {text-align:right;font-variant-numeric:tabular-nums}
.ap-rank-table tbody .num:last-child {font-weight:700;color:var(--ap-green)}
.ap-rank-table th:first-child,.ap-rank-table td:first-child {width:55px;text-align:center}
@media(max-width:750px) {
  .ap-hero {padding-top:calc(var(--hdr,72px) + 30px);padding-bottom:62px}
  .ap-hero-inner {display:grid;gap:18px}
  .ap-hero-period {padding-left:12px}
  .agency-performance-page .ap-charts {grid-template-columns:1fr}
  .agency-performance-page .ap-split-charts {grid-template-columns:1fr}
}
@media(max-width:480px) {
  .ap-body {padding-bottom:36px}
  .agency-performance-page .ap-kpis {gap:6px}
  .ap-kpi {padding:10px 8px}
  .ap-kpi span,.ap-kpi small {font-size:.7rem}
  .ap-panel {padding:14px}
  .ap-rank-table {min-width:0}
  .ap-rank-table th,.ap-rank-table td {padding:8px 6px;line-height:1.3}
  .ap-rank-table th:first-child,.ap-rank-table td:first-child {width:auto}
  .ap-rank-table th,.ap-rank-table td {font-size:.72rem}
}
</style>
<main id="main" class="agency-performance-page">
  <section class="ap-hero">
    <div class="wrap ap-hero-inner">
      <div>
        <nav class="ap-crumbs" aria-label="Breadcrumb"><a href="home">Home</a><span aria-hidden="true">/</span><a href="agencies">Agencies</a></nav>
        <h1>Agency Performance</h1>
        <p>Rank agencies by sales bookings and rental check-ins.</p>
      </div>
      <div class="ap-hero-period">
        <span>Reporting period</span>
        <strong id="apHeroPeriodLabel"><?= e($periodLabel) ?></strong>
        <span id="apHeroPeriodDisplay"><?= e($periodDisplay) ?></span>
      </div>
    </div>
  </section>

  <div class="wrap ap-body">
    <div class="ap-controls" aria-label="Reporting period and agency ranking">
      <div class="ap-date-picker">
        <span class="ap-control-label" id="apDateLabel">Date range</span>
        <button class="ap-date-trigger" type="button" id="apDateToggle" aria-haspopup="dialog" aria-expanded="false" aria-controls="apDateMenu" aria-labelledby="apDateLabel apDateText">
          <i class="fa-regular fa-calendar" aria-hidden="true"></i>
          <span id="apDateText"><strong><?= e($periodLabel) ?></strong><small><?= e($periodDisplay) ?></small></span>
          <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="ap-date-menu" id="apDateMenu" role="dialog" aria-label="Choose date range" hidden>
          <div class="ap-date-head"><strong>Date range</strong><small>Asia/Karachi</small></div>
          <div class="ap-date-body">
            <div class="ap-date-presets">
              <?php foreach ($periodPresets as $value => [$label]): ?>
                <button type="button" data-ap-range="<?= e($value) ?>" aria-pressed="<?= $range === $value ? 'true' : 'false' ?>"><?= e($label) ?></button>
              <?php endforeach; ?>
            </div>
            <div class="ap-date-custom">
              <strong>Custom range</strong>
              <label for="apFrom">From</label>
              <input id="apFrom" type="date" value="<?= e($dateFromValue) ?>" max="<?= e($today->format('Y-m-d')) ?>">
              <label for="apTo">To</label>
              <input id="apTo" type="date" value="<?= e($dateToValue) ?>" max="<?= e($today->format('Y-m-d')) ?>">
              <small id="apDateWarning" role="status" hidden>Choose a valid date range.</small>
            </div>
          </div>
        </div>
      </div>
      <fieldset class="ap-rank-control">
        <legend class="ap-control-label">Agency ranking</legend>
        <div class="ap-rank-options">
          <?php foreach (['5'=>'Top 5','10'=>'Top 10','15'=>'Top 15','all'=>'All'] as $value => $label): ?>
            <label><input type="radio" name="top" value="<?= $value ?>"<?= $top === (string)$value ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
    </div>
    <p id="apUpdateStatus" class="ap-update-status" role="status" hidden></p>
    <div id="apReport">
<?php endif; ?>

    <?php if ($failedSources): ?>
      <p class="ap-alert" role="status"><?= e(implode(', ', $failedSources)) ?> ERP data is temporarily unavailable. The report covers the available sources.</p>
    <?php endif; ?>
    <?php if (!$ranking || !$succeededSources): ?>
      <p class="ap-status" role="status">No agency performance data is available for this report.</p>
    <?php else: ?>
      <section class="ap-kpis" aria-label="Activity totals">
        <article class="ap-kpi"><span>Total activity</span><strong><?= number_format($grandActivities) ?></strong><small>All mapped agencies</small></article>
        <article class="ap-kpi"><span>Sale bookings</span><strong><?= number_format($grandSales) ?></strong></article>
        <article class="ap-kpi"><span>Rental check-ins</span><strong><?= number_format($grandRentals) ?></strong></article>
      </section>

      <section class="ap-charts" aria-label="Agency activity charts">
        <article class="ap-panel ap-line-panel">
          <h2>Sales and rentals by rank</h2>
          <p>Compare agency activity in ranking order.</p>
          <div class="ap-line-box"><canvas id="apLineChart" role="img" aria-label="Line chart comparing sales and rentals by agency rank"></canvas></div>
        </article>
        <article class="ap-panel">
          <h2>Activity by agency</h2>
          <p><?= count($topRows) ?> agencies ranked by total activity</p>
          <ul class="ap-legend"><li><span class="ap-swatch"></span>Sales</li><li><span class="ap-swatch rent"></span>Rentals</li></ul>
          <ol class="ap-chart-list" aria-label="Stacked activity chart">
            <?php foreach ($topRows as $row): ?>
              <li class="ap-chart-row">
                <span class="ap-chart-label"><?= e($row['name']) ?></span>
                <span class="ap-track" title="<?= (int)$row['sales'] ?> sales, <?= (int)$row['rentals'] ?> rentals"
                      role="img" aria-label="<?= (int)$row['sales'] ?> sales and <?= (int)$row['rentals'] ?> rentals">
                  <span class="ap-sale-fill" style="width:<?= round(100 * $row['sales'] / $chartMax, 3) ?>%"></span>
                  <span class="ap-rent-fill" style="width:<?= round(100 * $row['rentals'] / $chartMax, 3) ?>%"></span>
                </span>
                <span class="ap-chart-total"><?= number_format($row['sales'] + $row['rentals']) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        </article>
        <article class="ap-panel">
          <h2>Sales and rentals</h2>
          <p>Activity share across all mapped agencies</p>
          <div class="ap-donut<?= $grandActivities === 0 ? ' is-empty' : '' ?>" style="--ap-share:<?= round($saleShare, 3) ?>%" aria-hidden="true">
            <div class="ap-donut-center"><strong><?= number_format($grandActivities) ?></strong><small>Total activity</small></div>
          </div>
          <div class="ap-mix-line"><span><i class="ap-swatch" aria-hidden="true"></i>Sale bookings</span><strong><?= number_format($grandSales) ?><?= $grandActivities ? ' · '.round($saleShare).'%' : '' ?></strong></div>
          <div class="ap-mix-line"><span><i class="ap-swatch rent" aria-hidden="true"></i>Rental check-ins</span><strong><?= number_format($grandRentals) ?><?= $grandActivities ? ' · '.round(100 - $saleShare).'%' : '' ?></strong></div>
        </article>
      </section>

      <section class="ap-split-charts" aria-label="Separate sales and rentals charts">
        <article class="ap-panel">
          <h2>Sales by agency</h2>
          <p>Verified sale bookings per agency</p>
          <div class="ap-bar-box"><canvas id="apSaleChart" role="img" aria-label="Bar chart showing verified sales by agency"></canvas></div>
        </article>
        <article class="ap-panel">
          <h2>Rentals by agency</h2>
          <p>Verified rental check-ins per agency</p>
          <div class="ap-bar-box"><canvas id="apRentChart" role="img" aria-label="Bar chart showing verified rentals by agency"></canvas></div>
        </article>
      </section>

      <section class="ap-panel ap-table-panel" aria-labelledby="apRankingTitle">
        <div class="ap-table-head">
          <h2 id="apRankingTitle">Agency ranking</h2>
          <p><?= e($top === 'all' ? 'All agencies' : 'Top '.$top) ?> · <?= e(implode(' and ', $succeededSources)) ?> ERP records</p>
        </div>
        <div class="ap-table-wrap">
          <table class="ap-rank-table">
            <thead><tr><th scope="col">Rank</th><th scope="col">Agency</th><th scope="col" class="num">Sale bookings</th><th scope="col" class="num">Rental check-ins</th><th scope="col" class="num">Total</th></tr></thead>
            <tbody>
              <?php foreach ($topRows as $index => $row): ?>
                <tr><td><?= $index + 1 ?></td><td><?= e($row['name']) ?></td><td class="num"><?= number_format($row['sales']) ?></td><td class="num"><?= number_format($row['rentals']) ?></td><td class="num"><?= number_format($row['sales'] + $row['rentals']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
    <script id="apReportData" type="application/json"><?= json_encode($reportData, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<?php if ($fragment) exit; ?>
    </div>
  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const toggle = document.getElementById('apDateToggle');
    const menu = document.getElementById('apDateMenu');
    const from = document.getElementById('apFrom');
    const to = document.getElementById('apTo');
    const warning = document.getElementById('apDateWarning');
    const report = document.getElementById('apReport');
    const status = document.getElementById('apUpdateStatus');
    const topOptions = [...document.querySelectorAll('input[name="top"]')];
    const presets = [...menu.querySelectorAll('[data-ap-range]')];
    let current;
    let desired;
    let request;
    let customTimer;
    let lineChart;
    let saleChart;
    let rentChart;

    function drawLine(data) {
        lineChart?.destroy();
        const canvas = document.getElementById('apLineChart');
        if (!window.Chart || !canvas || data.length === 0) return;
        const chartFont = {family:'Inter, system-ui, sans-serif', size:11};
        function series(label, key, color) {
            return {label, data:data.map(row => row[key]), borderColor:color, backgroundColor:color,
                borderWidth:2, tension:.25, pointRadius:data.length > 15 ? 0 : 3, pointHoverRadius:5};
        }
        lineChart = new Chart(canvas, {
            type:'line',
            data:{labels:data.map(row => row.name),datasets:[series('Sales','sales','#176449'),series('Rentals','rentals','#bc812c')]},
            options:{
                responsive:true,maintainAspectRatio:false,animation:false,
                interaction:{mode:'index',intersect:false},
                scales:{
                    x:{grid:{display:false},ticks:{font:chartFont,maxRotation:0,maxTicksLimit:innerWidth < 550 ? 4 : 8,
                        callback:function(value) {
                            const label=this.getLabelForValue(value);
                            return label.length>14 ? label.slice(0,13)+'…' : label;
                        }}},
                    y:{beginAtZero:true,grid:{color:'#e8eee9'},ticks:{font:chartFont,precision:0}}
                },
                plugins:{legend:{position:'bottom',labels:{font:chartFont,usePointStyle:true,boxWidth:8}}}
            }
        });
    }

    function drawSeparateCharts(data) {
        saleChart?.destroy();
        saleChart = null;
        rentChart?.destroy();
        rentChart = null;
        const saleCanvas = document.getElementById('apSaleChart');
        const rentCanvas = document.getElementById('apRentChart');
        if (!window.Chart || data.length === 0) return;
        const chartFont = {family:'Inter, system-ui, sans-serif', size:11};
        function barConfig(title, key, color, hoverColor) {
            return {
                type: 'bar',
                data: {
                    labels: data.map(row => row.name),
                    datasets: [{
                        label: title,
                        data: data.map(row => row[key]),
                        backgroundColor: color,
                        hoverBackgroundColor: hoverColor,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 36
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: chartFont,
                                maxRotation: 0,
                                maxTicksLimit: innerWidth < 550 ? 4 : 8,
                                callback: function(value) {
                                    const label = this.getLabelForValue(value);
                                    return label.length > 14 ? label.slice(0, 13) + '…' : label;
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#e8eee9' },
                            ticks: { font: chartFont, precision: 0 }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { font: chartFont, usePointStyle: true, boxWidth: 8 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ' ' + context.dataset.label + ': ' + context.parsed.y;
                                }
                            }
                        }
                    }
                }
            };
        }
        if (saleCanvas) {
            saleChart = new Chart(saleCanvas, barConfig('Sale Bookings', 'sales', '#176449', '#1e7a5a'));
        }
        if (rentCanvas) {
            rentChart = new Chart(rentCanvas, barConfig('Rental Check-ins', 'rentals', '#bc812c', '#d49437'));
        }
    }

    function syncControls(state) {
        const dateText = document.getElementById('apDateText');
        dateText.querySelector('strong').textContent = state.periodLabel;
        dateText.querySelector('small').textContent = state.periodDisplay;
        document.getElementById('apHeroPeriodLabel').textContent = state.periodLabel;
        document.getElementById('apHeroPeriodDisplay').textContent = state.periodDisplay;
        from.value = state.dateFrom;
        to.value = state.dateTo;
        presets.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.apRange === state.range)));
        topOptions.forEach(option => { option.checked = option.value === state.top; });
    }

    function setMenu(open, focusBack = false) {
        menu.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (open) menu.querySelector('[aria-pressed="true"]')?.focus();
        if (focusBack) toggle.focus();
    }

    function buildUrl(state) {
        const url = new URL(location.pathname, location.origin);
        url.searchParams.set('range', state.range);
        url.searchParams.set('top', state.top);
        if (state.range === 'custom') {
            url.searchParams.set('date_from', state.dateFrom);
            url.searchParams.set('date_to', state.dateTo);
        }
        return url;
    }

    async function updateReport(state, historyMode = 'push') {
        desired = state;
        request?.abort();
        const controller = new AbortController();
        request = controller;
        const url = buildUrl(state);
        const endpoint = new URL(url);
        endpoint.searchParams.set('_report', '1');
        report.setAttribute('aria-busy', 'true');
        status.hidden = false;
        status.textContent = 'Updating report…';
        try {
            const response = await fetch(endpoint, {signal:controller.signal,headers:{Accept:'text/html'},cache:'no-store'});
            if (!response.ok) throw new Error('Report unavailable');
            const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
            const data = parsed.querySelector('#apReportData');
            if (!data) throw new Error('Invalid report');
            const next = JSON.parse(data.textContent);
            if (!next.available && current.available) throw new Error('Report unavailable');
            if (request !== controller) return;
            lineChart?.destroy();
            lineChart = null;
            saleChart?.destroy();
            saleChart = null;
            rentChart?.destroy();
            rentChart = null;
            report.replaceChildren(...parsed.body.childNodes);
            current = desired = next;
            syncControls(current);
            drawLine(current.chart);
            drawSeparateCharts(current.chart);
            const canonical = buildUrl(current);
            if (historyMode === 'push' && canonical.href !== location.href) history.pushState(null, '', canonical);
            status.hidden = true;
        } catch (error) {
            if (error.name === 'AbortError' || request !== controller) return;
            desired = current;
            syncControls(current);
            if (historyMode === 'none') history.replaceState(null, '', buildUrl(current));
            status.textContent = 'Report could not be updated. Please try again.';
        } finally {
            if (request === controller) report.removeAttribute('aria-busy');
        }
    }

    toggle.addEventListener('click', () => setMenu(menu.hidden));
    document.addEventListener('click', event => {
        if (!menu.hidden && !event.target.closest('.ap-date-picker')) setMenu(false);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !menu.hidden) setMenu(false, true);
    });
    presets.forEach(button => button.addEventListener('click', () => {
        clearTimeout(customTimer);
        warning.hidden = true;
        setMenu(false, true);
        updateReport({...desired,range:button.dataset.apRange});
    }));
    topOptions.forEach(option => option.addEventListener('change', () => {
        if (option.checked) updateReport({...desired,top:option.value});
    }));
    function updateCustomDates() {
        clearTimeout(customTimer);
        const valid = from.value && to.value && from.validity.valid && to.validity.valid && from.value <= to.value;
        warning.hidden = !!valid;
        if (!valid) return;
        customTimer = setTimeout(() => updateReport({...desired,range:'custom',dateFrom:from.value,dateTo:to.value}), 250);
    }
    [from,to].forEach(input => input.addEventListener('change', updateCustomDates));
    window.addEventListener('popstate', () => {
        clearTimeout(customTimer);
        const query = new URLSearchParams(location.search);
        updateReport({range:query.get('range') || 'this_month',top:query.get('top') || '5',
            dateFrom:query.get('date_from') || '',dateTo:query.get('date_to') || ''}, 'none');
    });

    current = desired = JSON.parse(document.getElementById('apReportData').textContent);
    drawLine(current.chart);
    drawSeparateCharts(current.chart);
}());
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
</body></html>
