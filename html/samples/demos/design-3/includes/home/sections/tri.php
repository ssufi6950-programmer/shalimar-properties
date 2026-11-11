<section class="sec-tight" id="projects">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker"><?= e($T('kicker', 'The Shalimar Group')) ?></span><h2 class="display-sm"><?= $T('title_h', 'Three communities, one promise') ?></h2>
        <p><?= e($T('lede', 'Every project is planned, built and managed by Shalimar Group.')) ?></p></div>
      <a href="projects.php" class="link-more">All projects <i class="fa-solid fa-arrow-right-long"></i></a>
    </div>
    <div class="tri">
      <?php foreach ($projects as $i => $p): $m = project_media($p); ?>
      <a class="tri-card rv" style="--d:<?= $i * 110 ?>ms" href="projects.php#<?= e($p['slug']) ?>">
        <img src="<?= e($m['cover_lg']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
        <span class="tri-top"><em><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></em><span><i class="fa-solid fa-location-dot"></i> <?= e($p['city'] ?? '') ?></span></span>
        <span class="tri-body">
          <span class="tri-logo"><?= project_logo_html($p, 'plogo') ?></span>
          <b><?= e($p['name']) ?></b>
          <small><?= e($m['tagline']) ?></small>
          <span class="tri-facts"><span><strong><?= (int)($liveByProject[$p['slug']] ?? 0) ?></strong> live</span><span><strong><?= (int)($byProject[$p['slug']] ?? 0) ?></strong> deals / mo</span><span><strong><?= (int)($p['progress'] ?? 0) ?>%</strong> developed</span></span>
          <span class="tri-go">Explore the community <i class="fa-solid fa-arrow-right-long"></i></span>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
