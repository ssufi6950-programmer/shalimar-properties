<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/market.php';

$projects = projects();
$f = ['project' => get_str('project'), 'sector' => get_str('sector'), 'block' => get_str('block')];
if ($f['project'] !== '' && !isset($projects[$f['project']])) $f = ['project' => '', 'sector' => '', 'block' => ''];
if ($f['project'] === '') { $f['sector'] = ''; $f['block'] = ''; }
$tx = market_tx($f);
$lastMonth = market_last_month($tx);
$lmLabel = date('F Y', strtotime('first day of last month'));

// CSV export of last month's transactions
if (get_str('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="shalimar-transactions-' . date('Y-m', strtotime('first day of last month')) . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Project', 'Sector', 'Block', 'Size', 'Type', 'Category', 'Price (PKR)']);
    foreach ($lastMonth as $t) {
        fputcsv($out, [$t['date'], project_name($t['project']), $t['sector'], $t['block'], size_text($t['size'], $t['unit']), TX_TYPES[$t['type']] ?? $t['type'], $t['category'], $t['price']]);
    }
    exit;
}

$kpi = market_kpis($tx);
$daily = market_daily($tx, 30);
$monthly = market_monthly($tx, 12);
$sizes = market_sizes($tx, 8);
$prices = market_prices($tx);
$lmCounts = array_fill_keys(array_keys(TX_TYPES), 0);
foreach ($lastMonth as $t) $lmCounts[$t['type']]++;
$sampleCount = count(array_filter($tx, fn($t) => ($t['source'] ?? '') === 'sample'));

$active = 'insights';
$pageTitle = 'Market Insights — Shalimar Properties';
$metaDesc = 'Daily and monthly transactions, most-traded plot sizes and block-wise prices across Shalimar Group projects.';
require __DIR__ . '/includes/header.php';
$scope = $f['block'] ?: ($f['sector'] ?: ($f['project'] ? $projects[$f['project']]['name'] : 'All Shalimar projects'));
$qs = http_build_query(array_filter($f));
?>
<main id="main">
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Market insights</span></div>
    <div class="page-hero-row">
      <div>
        <h1>Market insights for investors</h1>
        <p class="lede">Transactions recorded across Shalimar projects — volumes, most-traded sizes and how prices moved by block versus a year ago.</p>
      </div>
      <?php if ($sampleCount): ?><span class="demo-flag"><i class="fa-solid fa-flask"></i> Includes <?= number_format($sampleCount) ?> sample transactions</span><?php endif; ?>
    </div>

    <form class="ins-filter" method="get" action="insights.php">
      <div class="fld"><label for="fp">Project</label>
        <select id="fp" name="project"><option value="">All projects</option>
          <?php foreach ($projects as $p): ?><option value="<?= e($p['slug']) ?>"<?= $f['project'] === $p['slug'] ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="fld"><label for="fs">Sector</label>
        <select id="fs" name="sector" data-depends="fp"><option value="">All sectors</option>
          <?php foreach ($projects as $p) foreach ($p['sectors'] as $sec => $bl): ?><option value="<?= e($sec) ?>" data-project="<?= e($p['slug']) ?>"<?= $f['sector'] === $sec ? ' selected' : '' ?>><?= e($sec) ?></option><?php endforeach; ?>
        </select></div>
      <div class="fld"><label for="fb">Block</label>
        <select id="fb" name="block" data-depends="fs" data-attr="sector"><option value="">All blocks</option>
          <?php foreach ($projects as $p) foreach ($p['sectors'] as $sec => $bl) foreach ($bl as $b): ?><option value="<?= e($b) ?>" data-sector="<?= e($sec) ?>"<?= $f['block'] === $b ? ' selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
        </select></div>
      <button class="btn btn-solid" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
      <?php if ($qs): ?><a class="btn btn-ghost" href="insights.php">Reset</a><?php endif; ?>
    </form>
  </div>
</section>

<section class="sec-tight" style="padding-top:8px">
  <div class="wrap">
    <p class="muted" style="margin:0 0 10px;font-size:13px">Showing: <b style="color:var(--ink)"><?= e($scope) ?></b> · updated <?= e(date('d M Y, h:i A')) ?> PKT</p>
    <div class="kpis">
      <div class="kpi rv"><small>Today</small><b data-to="<?= (int)$kpi['today'] ?>">0</b><span>transactions recorded</span></div>
      <div class="kpi rv" style="--d:60ms"><small><?= e(date('F')) ?> so far</small><b data-to="<?= (int)$kpi['month'] ?>">0</b>
        <span><?php if ($kpi['mtd_change'] !== null): ?><span class="<?= $kpi['mtd_change'] >= 0 ? 'up-txt' : 'down-txt' ?>"><?= $kpi['mtd_change'] >= 0 ? '▲' : '▼' ?> <?= number_format(abs($kpi['mtd_change']), 1) ?>%</span> vs same days last month<?php else: ?>this month<?php endif; ?></span></div>
      <div class="kpi rv" style="--d:120ms"><small>Value traded · 30 days</small><b><?= e(fmt_pkr($kpi['value30'], false)) ?></b><span>PKR, sales/resales/transfers</span></div>
      <div class="kpi rv" style="--d:180ms"><small>Price vs last year</small><b class="<?= ($kpi['yoy'] ?? 0) >= 0 ? 'up-txt' : 'down-txt' ?>"><?= $kpi['yoy'] === null ? '—' : (($kpi['yoy'] >= 0 ? '+' : '') . number_format($kpi['yoy'], 1) . '%') ?></b><span>avg. across blocks (plots/units)</span></div>
    </div>

    <div class="chart-grid">
      <div class="chart-card rv"><h3>Daily transactions</h3><p class="sub">Last 30 days · all transaction types</p><div class="chart-box"><canvas id="chDaily" aria-label="Daily transactions chart" role="img"></canvas></div></div>
      <div class="chart-card rv" style="--d:80ms"><h3>Most-traded sizes</h3><p class="sub">Sales, resales &amp; transfers · last 14 months</p><div class="chart-box"><canvas id="chSizes" aria-label="Most traded sizes chart" role="img"></canvas></div></div>
    </div>
    <div class="chart-card rv" style="margin-top:14px"><h3>Monthly transactions</h3><p class="sub">Last 12 months by type</p><div class="chart-box" style="height:280px"><canvas id="chMonthly" aria-label="Monthly transactions chart" role="img"></canvas></div></div>
  </div>
</section>

<section class="sec-tight" id="prices">
  <div class="wrap">
    <div class="sec-head"><div><span class="kicker">Price tracker</span><h2>Current price vs one year ago</h2><p>Average rate per marla (plots) or per sq ft (Zalmi X). Current = last 90 days; a year ago = same season last year.</p></div></div>
    <div class="chart-grid cols-2" style="margin-top:0">
      <div class="table-wrap rv">
        <table class="tbl">
          <thead><tr><th>Project / Sector / Block</th><th class="num">~1 year ago</th><th class="num">Now</th><th class="num">Change</th></tr></thead>
          <tbody>
          <?php foreach ($prices as $r): ?>
            <tr>
              <td><b><?= e($r['block']) ?></b><br><span class="muted" style="font-size:12px"><?= e(project_name($r['project'])) ?> · <?= e($r['sector']) ?></span></td>
              <td class="num"><?= $r['then'] > 0 ? e(fmt_rate($r['then'], $r['unit'])) : '—' ?></td>
              <td class="num"><b><?= $r['now'] > 0 ? e(fmt_rate($r['now'], $r['unit'])) : '—' ?></b></td>
              <td class="num"><?php if ($r['change'] !== null && $r['now'] > 0): ?><span class="<?= $r['change'] >= 0 ? 'up-txt' : 'down-txt' ?>"><?= $r['change'] >= 0 ? '▲' : '▼' ?> <?= number_format(abs($r['change']), 1) ?>%</span><?php else: ?>—<?php endif; ?></td>
            </tr>
          <?php endforeach; if (!$prices): ?><tr><td colspan="4" class="muted">No priced transactions for this filter.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="chart-card rv" style="--d:80ms"><h3>Year-on-year change by block</h3><p class="sub">% change in average rate</p><div class="chart-box" style="height:300px"><canvas id="chYoy" aria-label="Year on year change chart" role="img"></canvas></div></div>
    </div>
  </div>
</section>

<section class="sec-tight" id="last-month">
  <div class="wrap">
    <div class="sec-head">
      <div><span class="kicker"><?= e($lmLabel) ?></span><h2>Last month’s plots sold, resold &amp; transferred</h2>
        <p><?= count($lastMonth) ?> transactions · <?= (int)$lmCounts['sale'] ?> new sales · <?= (int)$lmCounts['resale'] ?> resales · <?= (int)$lmCounts['transfer'] ?> transfers · <?= (int)$lmCounts['rent'] ?> rental agreements</p></div>
      <a class="btn btn-ghost btn-sm" href="insights.php?<?= e($qs ? $qs . '&' : '') ?>export=csv"><i class="fa-solid fa-download"></i> Download CSV</a>
    </div>
    <div class="table-wrap tall rv">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Project</th><th>Sector</th><th>Block</th><th>Size</th><th>Type</th><th class="num">Price (PKR)</th></tr></thead>
        <tbody>
        <?php foreach ($lastMonth as $t): ?>
          <tr>
            <td><?= e(date('d M', strtotime($t['date']))) ?></td>
            <td><?= e(project_name($t['project'])) ?></td>
            <td><?= e($t['sector']) ?></td>
            <td><?= e($t['block']) ?></td>
            <td><?= e(size_text($t['size'], $t['unit'])) ?> <span class="muted" style="font-size:11.5px"><?= e(category_label($t['category'])) ?></span></td>
            <td><span class="pill <?= ['sale' => '', 'resale' => 'gold', 'transfer' => 'blue', 'rent' => 'muted'][$t['type']] ?? '' ?>"><?= e(TX_TYPES[$t['type']] ?? $t['type']) ?></span></td>
            <td class="num"><?= e(fmt_pkr($t['price'], false)) ?><?= $t['type'] === 'rent' ? ' /mo' : '' ?></td>
          </tr>
        <?php endforeach; if (!$lastMonth): ?><tr><td colspan="7" class="muted">No transactions recorded last month for this filter.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <p class="muted" style="font-size:12px;margin-top:10px">Transactions are recorded by the Shalimar admin team when a sale, resale, transfer or rental agreement is completed. Figures are indicative and not financial advice.</p>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  if (!window.Chart) return;
  const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
  const G = "#166D47", GD = "#0E4B31", GOLD = "#B08D57", GS = "#DCC598", BLUE = "#4A7FB5", MUTED = "#9AA19C";
  Chart.defaults.font.family = "'Manrope', system-ui, sans-serif";
  Chart.defaults.font.size = 11.5;
  Chart.defaults.color = "#6A706C";
  Chart.defaults.animation = reduce ? false : { duration: 900, easing: "easeOutQuart" };
  Chart.defaults.plugins.legend.labels.boxWidth = 10;
  Chart.defaults.plugins.legend.labels.boxHeight = 10;
  Chart.defaults.maintainAspectRatio = false;
  const grid = { color: "rgba(30,36,33,.06)" };

  const daily = <?= json_encode(['labels' => array_map(fn($d) => date('d M', strtotime($d)), array_keys($daily)), 'data' => array_values($daily)]) ?>;
  new Chart(document.getElementById("chDaily"), {
    type: "bar",
    data: { labels: daily.labels, datasets: [{ label: "Transactions", data: daily.data, backgroundColor: G, hoverBackgroundColor: GOLD, borderRadius: 5, maxBarThickness: 18 }] },
    options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 10 } }, y: { beginAtZero: true, grid, ticks: { precision: 0 } } } }
  });

  const sizes = <?= json_encode(['labels' => array_keys($sizes), 'data' => array_values($sizes)]) ?>;
  new Chart(document.getElementById("chSizes"), {
    type: "bar",
    data: { labels: sizes.labels, datasets: [{ label: "Deals", data: sizes.data, backgroundColor: sizes.data.map((_, i) => i === 0 ? GOLD : G), borderRadius: 6, maxBarThickness: 22 }] },
    options: { indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, grid, ticks: { precision: 0 } }, y: { grid: { display: false } } } }
  });

  const monthly = <?= json_encode(['labels' => array_map(fn($m) => date('M y', strtotime($m . '-01')), array_keys($monthly)), 'types' => TX_TYPES, 'rows' => array_values($monthly)]) ?>;
  const colors = { sale: GD, resale: GOLD, transfer: BLUE, rent: GS };
  new Chart(document.getElementById("chMonthly"), {
    type: "bar",
    data: { labels: monthly.labels, datasets: Object.keys(monthly.types).map((k) => ({ label: monthly.types[k], data: monthly.rows.map((r) => r[k]), backgroundColor: colors[k], borderRadius: 3, maxBarThickness: 34 })) },
    options: { plugins: { legend: { position: "bottom" }, tooltip: { mode: "index", intersect: false } }, scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, grid, ticks: { precision: 0 } } } }
  });

  const yoy = <?= json_encode(array_values(array_map(fn($r) => ['label' => $r['block'], 'v' => $r['change'] === null ? 0 : round($r['change'], 1)], array_filter($prices, fn($r) => $r['change'] !== null && $r['now'] > 0)))) ?>;
  new Chart(document.getElementById("chYoy"), {
    type: "bar",
    data: { labels: yoy.map((r) => r.label), datasets: [{ label: "% change", data: yoy.map((r) => r.v), backgroundColor: yoy.map((r) => (r.v >= 0 ? G : "#B4423A")), borderRadius: 5, maxBarThickness: 26 }] },
    options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => (c.raw >= 0 ? "+" : "") + c.raw + "%" } } }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 0 } }, y: { grid, ticks: { callback: (v) => v + "%" } } } }
  });
})();
</script>
</body>
</html>
