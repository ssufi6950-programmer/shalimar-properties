<section class="sec-tight" id="market">
  <div class="wrap">
    <div class="h3-market rv">
      <div class="hm-copy">
        <span class="kicker light">Market intelligence</span>
        <h2 class="display-sm light"><?= $T('title_h', 'Negotiate with real numbers, <em>not rumours.</em>') ?></h2>
        <p>Daily and monthly transactions, the most-traded plot sizes and block-wise prices against last year — recorded by the Shalimar team as deals close.</p>
        <a href="insights.php" class="btn btn-gold"><i class="fa-solid fa-chart-line"></i> Open the market report</a>
      </div>
      <div class="hm-viz">
        <div class="hm-head"><span>Deals per day · last 30 days</span><b><?= array_sum($days) ?> deals</b></div>
        <svg class="spark" viewBox="0 0 300 96" preserveAspectRatio="none" aria-hidden="true">
          <defs><linearGradient id="spk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#DCC598" stop-opacity=".45"/><stop offset="1" stop-color="#DCC598" stop-opacity="0"/></linearGradient></defs>
          <path class="area" d="<?= $sparkArea ?>" fill="url(#spk)"/>
          <path class="line" d="<?= $spark ?>" pathLength="1" fill="none" stroke="#DCC598" stroke-width="1.6" vector-effect="non-scaling-stroke"/>
        </svg>
        <div class="hm-kpis">
          <div><b data-to="<?= (int)$kpi['today'] ?>">0</b><span>Deals today</span></div>
          <div><b data-to="<?= (int)$kpi['last_month'] ?>">0</b><span>Last month</span></div>
          <div><b><?= e(fmt_pkr($kpi['value30'], false)) ?></b><span>Traded · 30 days</span></div>
          <div><b data-to="<?= number_format((float)($kpi['yoy'] ?? 0), 1, '.', '') ?>" data-suffix="%" data-prefix="<?= ($kpi['yoy'] ?? 0) >= 0 ? '+' : '' ?>">0</b><span>Price vs last year</span></div>
        </div>
      </div>
    </div>
  </div>
</section>
