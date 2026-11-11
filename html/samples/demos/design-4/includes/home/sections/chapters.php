<section class="sec-tight" id="projects">
  <div class="wrap">
    <div class="a-head rv"><span class="a-num"><?= e($T('num', 'I.')) ?></span><div><span class="kicker"><?= e($T('kicker', 'The communities')) ?></span><h2 class="display-sm"><?= $T('title_h', 'Three addresses, one standard') ?></h2></div></div>
    <?php foreach ($projects as $i => $p): $m = project_media($p); ?>
    <article class="a-chap<?= $i % 2 ? ' flip' : '' ?>">
      <figure class="a-ph rv"><img src="<?= e($m['cover_lg']) ?>" alt="<?= e($p['name']) ?>" loading="lazy"><figcaption>No. <?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></figcaption></figure>
      <div class="a-txt rv">
        <span class="a-logo"><?= project_logo_html($p, 'plogo') ?></span>
        <h3><?= e($p['name']) ?></h3>
        <p class="a-tag"><?= e($m['tagline']) ?> · <?= e($p['location']) ?></p>
        <ul class="a-feats"><?php foreach (array_slice($m['features'], 0, 6) as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
        <div class="a-facts"><span><b><?= (int)($liveByProject[$p['slug']] ?? 0) ?></b> live listings</span><span><b><?= (int)($byProject[$p['slug']] ?? 0) ?></b> deals this month</span><span>from <b><?= e(preg_replace('/\s*\(.*$/', '', (string)$p['price_from'])) ?></b></span></div>
        <a class="a-link" href="projects.php#<?= e($p['slug']) ?>">Discover <?= e($p['name']) ?> <i class="fa-solid fa-arrow-right-long"></i></a>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
</section>
