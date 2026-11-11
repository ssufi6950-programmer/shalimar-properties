<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/market.php';

$f = ['project'=>get_str('project'),'sector'=>get_str('sector'),'block'=>get_str('block'),'category'=>get_str('category')];
$market = market_snapshot($f);
$projects = $market['projects'];
$marketProjectName = static fn(string $slug): string => (string)($projects[$slug]['name'] ?? $slug);
$daily = $market['daily'];
$monthly = array_map(static fn($count)=>['resale'=>$count],$market['monthly']);
$sizes = $market['sizes'];
$prices = $market['price_groups'];
$lastMonth = $market['last_month_rows'];
$lmLabel = $market['last_month_label'];
// CSV contains grouped public aggregates, never individual listing or owner records.
if (get_str('export') === 'csv') {
    if (!$market['available']) { http_response_code(503); header('Content-Type: text/plain; charset=utf-8'); exit('Transfer-date ERP resale data is temporarily unavailable.'); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="shalimar-erp-resale-transfers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Month','ERP Source','Project','Subhead','Property type','Resale transfers']);
    $safe = static fn($v)=>preg_match('/^[=+@\-\t\r\n]/',(string)$v)?"'".$v:$v;
    foreach ($lastMonth as $t) fputcsv($out,array_map($safe,[$lmLabel,$t['source'],$t['project_name'],$t['subhead_name'],$t['category'],$t['count']]));
    exit;
}

$active = 'insights';
$pageTitle = 'Market Insights — Shalimar Properties';
$metaDesc = 'Unlocked resale bookings reported by transfer date from SGC and Zalmi ERP APIs.';
require __DIR__ . '/includes/header.php';
$scope = $f['block'] ?: ($f['sector'] ?: ($f['project'] ? ($projects[$f['project']]['name'] ?? 'Selected ERP segment') : 'All scoped SGC + Zalmi ERP projects'));
$qs = http_build_query(array_filter($f));
?>
<main id="main">
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Market insights</span></div>
    <div class="page-hero-row">
      <div>
        <h1>Market insights for investors</h1>
        <p class="lede">ERP Resales by Transfer Date — resale bookings with unlocked status from SGC and Zalmi. Portal listings and asking prices are not used.</p>
      </div>

    </div>

    <form class="ins-filter" method="get" action="insights.php">
      <div class="fld"><label for="fp">Project</label>
        <select id="fp" name="project"><option value="">All projects</option>
          <?php foreach ($projects as $p): ?><option value="<?= e($p['slug']) ?>"<?= $f['project'] === $p['slug'] ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="fld"><label for="fs">Sector</label><select id="fs" disabled title="Historical sector snapshots are not verified"><option>Not available historically</option></select></div>
      <div class="fld"><label for="fb">Block</label><select id="fb" disabled title="Historical block snapshots are not verified"><option>Not available historically</option></select></div>
      <div class="fld"><label for="fc">Property type</label><select id="fc" name="category"><option value="">All types</option><?php foreach(['plot','house','apartment','office','shop'] as $category): ?><option value="<?= e($category) ?>"<?= $f['category']===$category?' selected':'' ?>><?= e(category_label($category)) ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-solid" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
      <?php if ($qs): ?><a class="btn btn-ghost" href="insights.php">Reset</a><?php endif; ?>
    </form>
  </div>
</section>

<section class="sec-tight" style="padding-top:8px">
  <div class="wrap">
    <p class="muted" style="margin:0 0 10px;font-size:13px">Showing: <b style="color:var(--ink)"><?= e($scope) ?></b><?php if($market['last_updated']): ?> · Last updated <?= e((new DateTimeImmutable($market['last_updated']))->format('d M Y, h:i A')) ?> PKT<?php endif; ?></p>
    <p class="muted" role="status"><?= e($market['message']) ?><?= $market['stale']?' Last complete cached report; totals are not partial.':'' ?></p>
    <div class="kpis">
      <div class="kpi rv"><small>Deals Today</small><b data-market="today"><?= market_count($market['today']) ?></b><span>Resale transfers, PKT</span></div>
      <div class="kpi rv" style="--d:60ms"><small>Last Month</small><b data-market="last_month"><?= market_count($market['last_month']) ?></b><span><?= e($lmLabel) ?></span></div>
      <div class="kpi rv" style="--d:120ms"><small>This Month</small><b data-market="month"><?= market_count($market['month']) ?></b><span><?= e($market['current_month_label']) ?> to date</span></div>
      <div class="kpi rv" style="--d:180ms"><small>Price vs Last Year</small><b data-market="yoy"><?= e(market_change($market['yoy'])) ?></b><span><?= $market['yoy']===null?'Actual resale price is not verified':'Actual resale-price comparison' ?></span></div>
    </div>

    <div class="chart-grid">
      <div class="chart-card rv"><h3>Deals per day · last 30 days</h3><p class="sub"><b data-market-chart-total><?= market_count($market['chart_total']) ?><?= $market['available']?' deals':'' ?></b> · 30 local dates, including today</p><div class="chart-box"><canvas id="chDaily" aria-label="Daily completed ERP resales chart" role="img"></canvas></div></div>
      <div class="chart-card rv" style="--d:80ms"><h3>Resale property sizes</h3><p class="sub">Historical sizes are not available from the verified reporting API.</p><p class="muted">Unavailable: no immutable transaction-time size snapshots.</p><div class="chart-box"><canvas id="chSizes" aria-label="Historical deal sizes are unavailable" role="img"></canvas></div></div>
    </div>
    <div class="chart-card rv" style="margin-top:14px"><h3>Monthly ERP resale transfers</h3><p class="sub">Last 12 calendar months, grouped by transfer date (PKT)</p><div class="chart-box" style="height:280px"><canvas id="chMonthly" aria-label="Monthly ERP resale transfers chart" role="img"></canvas></div></div>
  </div>
</section>

<section class="sec-tight" id="prices">
  <div class="wrap">
    <div class="sec-head"><div><span class="kicker">Price tracker</span><h2>Actual resale prices vs last year</h2><p>Actual completed resale consideration is not verified in the inspected ERP workflows. The resale form can carry forward installment balances; booking totals, transfer fees and commissions cannot be used as sale prices. Price comparison is N/A.</p></div></div>
    <div class="chart-grid cols-2" style="margin-top:0">
      <div class="table-wrap rv">
        <table class="tbl">
          <thead><tr><th>Comparable property group</th><th class="num">Same month last year</th><th class="num">Now</th><th class="num">Change</th></tr></thead>
          <tbody>
          <?php foreach ($prices as $r): ?>
            <tr>
              <td><b><?= e($r['block']) ?></b><br><span class="muted" style="font-size:12px"><?= e($marketProjectName($r['project'])) ?> · <?= e($r['sector']) ?> · <?= e($r['category']) ?> · <?= e($r['size'].' '.$r['unit']) ?><br><?= (int)$r['previous_count'] ?> / <?= (int)$r['current_count'] ?> observations (previous / current)</span></td>
              <td class="num"><?= $r['then'] > 0 ? e('PKR '.number_format($r['then']).' / '.$r['unit']) : '—' ?></td>
              <td class="num"><b><?= $r['now'] > 0 ? e('PKR '.number_format($r['now']).' / '.$r['unit']) : '—' ?></b></td>
              <td class="num"><?php if ($r['change'] !== null && $r['now'] > 0): ?><span class="<?= $r['change'] >= 0 ? 'up-txt' : 'down-txt' ?>"><?= $r['change'] >= 0 ? '▲' : '▼' ?> <?= number_format(abs($r['change']), 1) ?>%</span><?php else: ?>—<?php endif; ?></td>
            </tr>
          <?php endforeach; if (!$prices): ?><tr><td colspan="4" class="muted">N/A: verified actual resale consideration and comparable historical snapshots are unavailable.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="chart-card rv" style="--d:80ms"><h3>Year-on-year actual resale-price change</h3><p class="sub">Unavailable until verified actual consideration and comparable historical areas exist.</p><div class="chart-box" style="height:300px"><canvas id="chYoy" aria-label="Actual resale price history is unavailable" role="img"></canvas></div></div>
    </div>
  </div>
</section>

<section class="sec-tight" id="last-month">
  <div class="wrap">
    <div class="sec-head">
      <div><span class="kicker"><?= e($lmLabel) ?></span><h2>Last month's ERP resale transfers</h2>
        <p><?= market_count($market['last_month']) ?> ERP resale transfers, grouped public aggregates</p></div>
      <a class="btn btn-ghost btn-sm" href="insights.php?<?= e($qs ? $qs . '&' : '') ?>export=csv"><i class="fa-solid fa-download"></i> Download CSV</a>
    </div>
    <div class="table-wrap tall rv">
      <table class="tbl">
        <thead><tr><th>Month</th><th>ERP Source</th><th>Project</th><th>Subhead</th><th>Property type</th><th class="num">Resale transfers</th></tr></thead>
        <tbody>
        <?php foreach ($lastMonth as $t): ?>
          <tr><td><?= e($lmLabel) ?></td><td><?= e($t['source']) ?></td><td><?= e($t['project_name']) ?></td><td><?= e($t['subhead_name']) ?></td><td><?= e(category_label($t['category'])) ?></td><td class="num"><?= number_format($t['count']) ?></td></tr>
        <?php endforeach; if (!$lastMonth): ?><tr><td colspan="6" class="muted"><?= $market['available']?'No dated resale transfers for this period/filter.':'Combined transfer-date resale data is temporarily unavailable.' ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <p class="muted" style="font-size:12px;margin-top:10px">Each booking counts once when its sale type is resale and its locked status is no, within the configured reporting company. Dates use the booking transfer date in Pakistan time; creation and approval dates are not used. Locked bookings are excluded. Bookings without a valid transfer date cannot be included in date-wise totals. Head/Subhead active flags and Portal listing status do not affect these counts. <?= e($market['price_method']) ?></p>
  </div>
</section>
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
    data: { labels: daily.labels, datasets: [{ label: "ERP resale transfers", data: daily.data, backgroundColor: G, hoverBackgroundColor: GOLD, borderRadius: 5, maxBarThickness: 18 }] },
    options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 10 } }, y: { beginAtZero: true, grid, ticks: { precision: 0 } } } }
  });

  const sizes = <?= market_json(['labels' => array_keys($sizes), 'data' => array_values($sizes)]) ?>;
  new Chart(document.getElementById("chSizes"), {
    type: "bar",
    data: { labels: sizes.labels, datasets: [{ label: "ERP resale transfers", data: sizes.data, backgroundColor: sizes.data.map((_, i) => i === 0 ? GOLD : G), borderRadius: 6, maxBarThickness: 22 }] },
    options: { indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, grid, ticks: { precision: 0 } }, y: { grid: { display: false } } } }
  });

  const monthly = <?= market_json(['labels' => array_map(fn($m) => date('M y', strtotime($m . '-01')), array_keys($monthly)), 'types' => ['resale'=>'ERP resale transfers'], 'rows' => array_values($monthly)]) ?>;
  const colors = { sale: GD, resale: GOLD, transfer: BLUE, rent: GS };
  new Chart(document.getElementById("chMonthly"), {
    type: "bar",
    data: { labels: monthly.labels, datasets: Object.keys(monthly.types).map((k) => ({ label: monthly.types[k], data: monthly.rows.map((r) => r[k]), backgroundColor: colors[k], borderRadius: 3, maxBarThickness: 34 })) },
    options: { plugins: { legend: { position: "bottom" }, tooltip: { mode: "index", intersect: false } }, scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, grid, ticks: { precision: 0 } } } }
  });

  const yoy = <?= market_json(array_values(array_map(fn($r) => ['label' => $marketProjectName($r['project']).' / '.$r['category'].' / '.$r['size'].' '.$r['unit'].' / '.$r['block'], 'v' => round($r['change'], 1)], $prices))) ?>;
  new Chart(document.getElementById("chYoy"), {
    type: "bar",
    data: { labels: yoy.map((r) => r.label), datasets: [{ label: "% change", data: yoy.map((r) => r.v), backgroundColor: yoy.map((r) => (r.v >= 0 ? G : "#B4423A")), borderRadius: 5, maxBarThickness: 26 }] },
    options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => (c.raw >= 0 ? "+" : "") + c.raw + "%" } } }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 0 } }, y: { grid, ticks: { callback: (v) => v + "%" } } } }
  });
})();
</script>
</body>
</html>
