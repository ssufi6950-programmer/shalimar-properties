<?php $mon = [];
foreach ($tx as $tt) { $mk = substr($tt['date'], 0, 7); $mon[$mk] = ($mon[$mk] ?? 0) + 1; }
ksort($mon); $mon = array_slice($mon, -12, 12, true); $mmax = max(1, max($mon ?: [1])); ?>
<section class="sec-tight" id="market">
  <div class="wrap c-market rv">
    <div class="c-mcopy"><h2><?= $T('title_h', 'Prices, <mark>in public.</mark>') ?></h2><p>Every deal the Shalimar team records feeds the market report — daily, monthly and year-on-year.</p>
      <div class="c-mk"><div><b data-to="<?= (int)$kpi['today'] ?>">0</b><span>today</span></div><div><b data-to="<?= (int)$kpi['last_month'] ?>">0</b><span>last month</span></div><div><b><?= e(fmt_pkr($kpi['value30'], false)) ?></b><span>traded · 30d</span></div></div>
      <a href="insights.php" class="c-btn light">Open market report <i class="fa-solid fa-arrow-right"></i></a></div>
    <div class="c-bars" aria-label="Deals per month">
      <?php $j = 0; foreach ($mon as $mk => $mv): ?><div style="--h:<?= round($mv / $mmax * 100) ?>%;--d:<?= $j * 60 ?>ms"><i></i><small><?= date('M', strtotime($mk . '-01')) ?></small></div><?php $j++; endforeach; ?>
    </div>
  </div>
</section>
