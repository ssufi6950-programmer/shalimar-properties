<section class="sec-tight" id="market">
  <div class="wrap a-market rv">
    <div><span class="kicker">Market intelligence</span><h2 class="display-sm"><?= $T('title_h', 'Negotiate with <em>real numbers</em>.') ?></h2><p class="muted">Daily and monthly deals, plot sizes and block prices — recorded by the Shalimar team as deals close.</p><a class="a-link" href="insights.php">Open the market report <i class="fa-solid fa-arrow-right-long"></i></a></div>
    <div class="a-mk"><b data-to="<?= (int)$kpi['today'] ?>">0</b><span>Deals today</span></div>
    <div class="a-mk"><b data-to="<?= (int)$kpi['last_month'] ?>">0</b><span>Deals last month</span></div>
    <div class="a-mk"><b><?= e(fmt_pkr($kpi['value30'], false)) ?></b><span>Traded in 30 days</span></div>
  </div>
</section>
