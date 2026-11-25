<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/market.php';

$f = [];
// Market Insights is intentionally shown as an all-project report; filter controls are hidden.
$market = market_snapshot($f);
$projects = $market['projects'];
$projectLogoList = projects();
$marketProjectName = static fn(string $slug): string => (string)($projects[$slug]['name'] ?? $slug);
$daily = $market['daily'];
$categories = $market['categories']??[];
unset($categories['']);
$prices = $market['price_groups'];
$lastMonth = market_sale_rent_rows($market['last_month_rows']);
$lmLabel = $market['last_month_label'];
$marketStatusMessage = str_replace(
    [
        'Combined SGC + Zalmi deal data is temporarily unavailable.',
        'Complete SGC + Zalmi report.',
        'Showing the last complete SGC + Zalmi report. One or more sources could not be refreshed.',
    ],
    [
        'Market deal data is temporarily unavailable.',
        'Market report is up to date.',
        'Showing the last complete market report. One or more data sources could not be refreshed.',
    ],
    (string) $market['message']
);
// CSV contains grouped public aggregates, never individual listing or owner records.
if (get_str('export') === 'csv') {
    if (!$market['available']) { http_response_code(503); header('Content-Type: text/plain; charset=utf-8'); exit('Deal data is temporarily unavailable.'); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="shalimar-deals.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Month','Project','Subhead','Property type','Sale Deals','Rental Deals','Deals']);
    $safe = static fn($v)=>preg_match('/^[=+@\-\t\r\n]/',(string)$v)?"'".$v:$v;
    foreach ($lastMonth as $t) fputcsv($out,array_map($safe,[$lmLabel,$t['project_name'],$t['subhead_name'],$t['category'],$t['sale_deals'],$t['rent_deals'],$t['count']]));
    exit;
}

$active = 'insights';
$showPriceTracker = false; // Enable after verified live price data is configured.
$pageTitle = 'Market Insights â€” Shalimar Properties';
$metaDesc = 'Sale, resale and property check-in activity across Shalimar communities.';
require __DIR__ . '/includes/header.php';
$scope = 'All Shalimar projects';
?>
<main id="main">
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="home">Home</a><span>/</span><span>Market insights</span></div>
    <div class="page-hero-row">
      <div>
        <h1>Market insights for investors</h1>
        <p class="lede">Sales, resales and property check-ins across Shalimar communities. Portal listings and asking prices are not used.</p>
        <?php if ($projectLogoList): ?>
          <div class="proj-tabs chips-scroll insights-project-logos" role="list" aria-label="Shalimar projects" style="margin-top:14px">
            <?php foreach ($projectLogoList as $projectCard): ?>
              <span class="proj-tab" role="listitem" aria-label="<?= e($projectCard['name']) ?>">
                <?= project_logo_html($projectCard, 'plogo') ?><span><?= e($projectCard['name']) ?></span>
              </span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>


  </div>
</section>

<section class="sec-tight" style="padding-top:8px">
  <div class="wrap">
    <p class="muted" style="margin:0 0 10px;font-size:13px">Showing: <b style="color:var(--ink)"><?= e($scope) ?></b><?php if($market['last_updated']): ?> Â· Last updated <?= e((new DateTimeImmutable($market['last_updated']))->format('d M Y, h:i A')) ?> PKT<?php endif; ?></p>
    <p class="muted" role="status"><?= e($marketStatusMessage) ?><?= $market['stale']?' Last complete cached report; totals are not partial.':'' ?></p>
    <div class="kpis kpis-deals">
      <div class="kpi rv"><small>Deals Today</small><b data-market="today"><?= market_count($market['today']) ?></b><span>Deals, PKT</span></div>
      <div class="kpi rv" style="--d:60ms"><small>Last Month</small><b data-market="last_month"><?= market_count($market['last_month']) ?></b><span><?= e($lmLabel) ?></span></div>
      <div class="kpi rv" style="--d:120ms"><small>This Month</small><b data-market="month"><?= market_count($market['month']) ?></b><span><?= e($market['current_month_label']) ?> to date</span></div>
    </div>

    <div class="chart-grid">
      <div class="chart-card rv"><h3>Deals per day Â· last 30 days</h3><p class="sub"><b data-market-chart-total><?= market_count($market['chart_total']) ?><?= $market['available']?' deals':'' ?></b> Â· 30 local dates, including today</p><div class="chart-box"><canvas id="chDaily" aria-label="Daily deals chart" role="img"></canvas></div></div>
      <?php require __DIR__.'/includes/insights-size-chart.php'; ?>
    </div>
    <?php require __DIR__.'/includes/insights-monthly-chart.php'; ?>
  </div>
</section>

<?php if ($showPriceTracker): ?>
<section class="sec-tight" id="prices">
  <div class="wrap">
    <div class="sec-head"><div><span class="kicker">Price tracker</span><h2>Actual resale prices vs last year</h2><p>Actual completed resale consideration is not verified in the available records. The resale form can carry forward installment balances; booking totals, processing fees and commissions cannot be used as sale prices. Price comparison is N/A.</p></div></div>
    <div class="chart-grid cols-2" style="margin-top:0">
      <div class="table-wrap rv">
        <table class="tbl">
          <thead><tr><th>Comparable property group</th><th class="num">Same month last year</th><th class="num">Now</th><th class="num">Change</th></tr></thead>
          <tbody>
          <?php foreach ($prices as $r): ?>
            <tr>
              <td><b><?= e($r['block']) ?></b><br><span class="muted" style="font-size:12px"><?= e($marketProjectName($r['project'])) ?> Â· <?= e($r['sector']) ?> Â· <?= e($r['category']) ?> Â· <?= e($r['size'].' '.$r['unit']) ?><br><?= (int)$r['previous_count'] ?> / <?= (int)$r['current_count'] ?> observations (previous / current)</span></td>
              <td class="num"><?= $r['then'] > 0 ? e('PKR '.number_format($r['then']).' / '.$r['unit']) : 'â€”' ?></td>
              <td class="num"><b><?= $r['now'] > 0 ? e('PKR '.number_format($r['now']).' / '.$r['unit']) : 'â€”' ?></b></td>
              <td class="num"><?php if ($r['change'] !== null && $r['now'] > 0): ?><span class="<?= $r['change'] >= 0 ? 'up-txt' : 'down-txt' ?>"><?= $r['change'] >= 0 ? 'â–²' : 'â–¼' ?> <?= number_format(abs($r['change']), 1) ?>%</span><?php else: ?>â€”<?php endif; ?></td>
            </tr>
          <?php endforeach; if (!$prices): ?><tr><td colspan="4" class="muted">N/A: verified actual resale consideration and comparable historical snapshots are unavailable.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="chart-card rv" style="--d:80ms"><h3>Year-on-year actual resale-price change</h3><p class="sub">Unavailable until verified actual consideration and comparable historical areas exist.</p><div class="chart-box" style="height:300px"><canvas id="chYoy" aria-label="Actual resale price history is unavailable" role="img"></canvas></div></div>
    </div>
  </div>
</section>
<?php endif; ?>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  if (!window.Chart || !<?= $market['available']?'true':'false' ?>) return;
  const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
  const G = "#166D47", GD = "#0E4B31", GOLD = "#B08D57", GS = "#DCC598", BLUE = "#4A7FB5", MUTED = "#9AA19C";
  Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
  Chart.defaults.font.size = 11.5;
  Chart.defaults.color = "#6A706C";
  Chart.defaults.animation = reduce ? false : { duration: 900, easing: "easeOutQuart" };
  Chart.defaults.plugins.legend.labels.boxWidth = 10;
  Chart.defaults.plugins.legend.labels.boxHeight = 10;
  Chart.defaults.maintainAspectRatio = false;
  const grid = { color: "rgba(30,36,33,.06)" };

  const daily = <?= market_json(['labels' => array_map(fn($d) => date('d M', strtotime($d)), array_keys($daily)), 'data' => array_values($daily)]) ?>;
  new Chart(document.getElementById("chDaily"), {
    type: "bar",
    data: { labels: daily.labels, datasets: [{ label: "Deals", data: daily.data, backgroundColor: G, hoverBackgroundColor: GOLD, borderRadius: 5, maxBarThickness: 18 }] },
    options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 10 } }, y: { beginAtZero: true, grid, ticks: { precision: 0 } } } }
  });

  const categories = <?= market_json(['labels' => array_map(static fn($category)=>$category===''?'Unassigned':ucwords($category),array_keys($categories)), 'data' => array_values($categories)]) ?>;
  if (document.getElementById("chCategories")) new Chart(document.getElementById("chCategories"), {
    type: "bar",
    data: { labels: categories.labels, datasets: [{ label: "Deals", data: categories.data, backgroundColor: categories.data.map((_, i) => i === 0 ? GOLD : G), borderRadius: 6, maxBarThickness: 22 }] },
    options: { indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, grid, ticks: { precision: 0 } }, y: { grid: { display: false } } } }
  });

  const monthly = <?= market_json(['labels' => array_map(fn($m) => date('M y', strtotime($m . '-01')), array_keys($market['monthly_by_type']??[])), 'rows' => array_values($market['monthly_by_type']??[])]) ?>;
  if (document.getElementById("chMonthly")) new Chart(document.getElementById("chMonthly"), {
    type: "bar",
    data: { labels: monthly.labels, datasets: [
      { label: "Sale Deals", data: monthly.rows.map((r) => r.sale_deals), backgroundColor: GD, borderRadius: 3, maxBarThickness: 34 },
      { label: "Rental Deals", data: monthly.rows.map((r) => r.rental_deals), backgroundColor: GS, borderRadius: 3, maxBarThickness: 34 }
    ] },
    options: { plugins: { legend: { position: "bottom" }, tooltip: { mode: "index", intersect: false } }, scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, grid, ticks: { precision: 0 } } } }
  });

  const yoyCanvas = document.getElementById("chYoy");
  if (yoyCanvas) {
  const yoy = <?= market_json(array_values(array_map(fn($r) => ['label' => $marketProjectName($r['project']).' / '.$r['category'].' / '.$r['size'].' '.$r['unit'].' / '.$r['block'], 'v' => round($r['change'], 1)], $prices))) ?>;
  new Chart(yoyCanvas, {
    type: "bar",
    data: { labels: yoy.map((r) => r.label), datasets: [{ label: "% change", data: yoy.map((r) => r.v), backgroundColor: yoy.map((r) => (r.v >= 0 ? G : "#B4423A")), borderRadius: 5, maxBarThickness: 26 }] },
    options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => (c.raw >= 0 ? "+" : "") + c.raw + "%" } } }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 0 } }, y: { grid, ticks: { callback: (v) => v + "%" } } } }
  });
  }
})();
</script>
</body>
</html>
