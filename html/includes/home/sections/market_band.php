<section class="sec-tight" id="market">
  <div class="wrap">
    <div class="h3-market rv">
      <div class="hm-copy">
        <span class="kicker light">Market intelligence</span>
        <h2 class="display-sm light"><?= $T('title_h', 'Negotiate with real numbers, <em>not rumours.</em>') ?></h2>
        <p>Explore SGC and Zalmi resale bookings with unlocked status, reported by transfer date. Transaction-price trends are shown only when verified data is available.</p>
        <a href="insights.php" class="btn btn-gold"><i class="fa-solid fa-chart-line"></i> Open the market report</a>
      </div>
      <div class="hm-viz">
        <div class="hm-head"><span>Deals per day · last 30 days</span><b data-market-chart-total><?= market_count($market['chart_total']) ?><?= $market['available'] ? ' deals' : '' ?></b></div>
        <?php if ($market['available']): ?>
        <svg class="spark" viewBox="0 0 300 96" preserveAspectRatio="none" role="img" aria-label="<?= e('ERP resales by transfer date over 30 days: '.implode('; ',array_map(static fn($d,$c)=>$d.': '.$c,array_keys($days),array_values($days)))) ?>">
          <defs><linearGradient id="spk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#DCC598" stop-opacity=".45"/><stop offset="1" stop-color="#DCC598" stop-opacity="0"/></linearGradient></defs>
          <path class="area" d="<?= $sparkArea ?>" fill="url(#spk)"/>
          <path class="line" d="<?= $spark ?>" pathLength="1" fill="none" stroke="#DCC598" stroke-width="1.6" vector-effect="non-scaling-stroke"/>
        </svg>
        <?php else: ?><p role="status"><?= e($market['message']) ?></p><?php endif; ?>
        <?php if ($market['last_updated']): ?><small class="hm-updated">Last updated <?= e((new DateTimeImmutable($market['last_updated']))->format('d M, g:i A')) ?> PKT<?= $market['stale'] ? ' · Last complete cached report' : '' ?></small><?php endif; ?>
        <div class="hm-kpis">
          <div><b data-market="today"><?= market_count($market['today']) ?></b><span>Deals Today</span></div>
          <div><b data-market="last_month"><?= market_count($market['last_month']) ?></b><span>Last Month</span></div>
          <div><b data-market="month"><?= market_count($market['month']) ?></b><span>This Month</span></div>
          <div title="<?= e($market['price_method']) ?>"><b data-market="yoy"><?= e(market_change($market['yoy'])) ?></b><span>Price vs Last Year</span></div>
        </div>
      </div>
    </div>
  </div>
</section>
