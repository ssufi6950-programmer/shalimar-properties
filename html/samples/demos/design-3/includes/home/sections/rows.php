<section class="sec-tight" id="projects">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker"><?= e($T('kicker', 'Our communities')) ?></span><h2 class="display-sm"><?= $T('title_h', 'Where Shalimar builds') ?></h2></div>
      <a href="projects.php" class="link-more">All projects <i class="fa-solid fa-arrow-right-long"></i></a>
    </div>
    <div class="pr-rows rv">
      <div class="pr-float" aria-hidden="true"><?php foreach ($projects as $i => $p): $m = project_media($p); ?><img data-k="<?= $i ?>" src="<?= e($m['cover']) ?>" alt=""<?= $i ? '' : ' class="on"' ?> loading="lazy"><?php endforeach; ?></div>
      <?php foreach ($projects as $i => $p): $m = project_media($p); ?>
      <a class="pr-row" data-k="<?= $i ?>" href="projects.php#<?= e($p['slug']) ?>">
        <span class="pr-no"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <span class="pr-thumb"><img src="<?= e($m['cover']) ?>" alt="" loading="lazy"></span>
        <span class="pr-name"><b><?= e($p['name']) ?></b><small><?= e($m['tagline']) ?></small></span>
        <span class="pr-loc"><i class="fa-solid fa-location-dot"></i> <?= e($p['location']) ?></span>
        <span class="pr-stat"><b><?= (int)($liveByProject[$p['slug']] ?? 0) ?></b> live listings</span>
        <span class="pr-logo"><?= project_logo_html($p, 'plogo') ?></span>
        <span class="pr-arrow"><i class="fa-solid fa-arrow-right-long"></i></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
