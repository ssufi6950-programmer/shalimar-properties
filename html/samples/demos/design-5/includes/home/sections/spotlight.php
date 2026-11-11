<section class="sec-tight" id="projects">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker"><?= e($T('kicker', 'Under the Shalimar umbrella')) ?></span><h2 class="display-sm"><?= $T('title_h', 'Signature communities') ?></h2>
        <p><?= e($T('lede', 'Each project is developed and managed by Shalimar Group — explore its story, amenities and verified units.')) ?></p></div>
      <a href="projects.php" class="link-more">All projects <i class="fa-solid fa-arrow-right-long"></i></a>
    </div>
    <div class="spot rv" id="spot">
      <div class="spot-list" role="tablist" aria-label="Projects">
        <?php foreach ($projects as $i => $p): $m = project_media($p); ?>
        <button type="button" role="tab" class="spot-tab<?= $i ? '' : ' on' ?>" aria-selected="<?= $i ? 'false' : 'true' ?>" data-i="<?= $i ?>">
          <span class="st-no"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <span class="st-txt"><b><?= e($p['name']) ?></b><small><?= e($m['tagline']) ?></small></span>
          <span class="st-bar"><i></i></span>
        </button>
        <?php endforeach; ?>
      </div>
      <div class="spot-stage">
        <?php foreach ($projects as $i => $p): $m = project_media($p); $g = array_slice($m['gallery'], 1, 3); ?>
        <article class="spot-panel<?= $i ? '' : ' on' ?>" aria-hidden="<?= $i ? 'true' : 'false' ?>">
          <div class="sp-img"><img src="<?= e($m['cover_lg']) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></div>
          <?php if ($g): ?><div class="sp-thumbs">
            <?php foreach ($g as $gi): ?><a href="<?= e(photo($gi, true)) ?>" data-lightbox="p<?= $i ?>"><img src="<?= e(photo($gi)) ?>" alt="" loading="lazy"></a><?php endforeach; ?>
          </div><?php endif; ?>
          <div class="sp-card">
            <span class="sp-logo"><?= project_logo_html($p, 'plogo') ?></span>
            <h3><?= e($p['name']) ?></h3>
            <p class="sp-loc"><i class="fa-solid fa-location-dot"></i> <?= e($p['location']) ?></p>
            <?php if ($m['features']): ?><ul class="sp-feats"><?php foreach (array_slice($m['features'], 0, 4) as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <div class="sp-facts">
              <div><b><?= (int)($liveByProject[$p['slug']] ?? 0) ?></b><span>Live listings</span></div>
              <div><b><?= (int)($byProject[$p['slug']] ?? 0) ?></b><span>Deals this month</span></div>
              <div><b><?= e(preg_replace('/\s*\(.*$/', '', (string)$p['price_from'])) ?></b><span>Starting from</span></div>
            </div>
            <a class="btn btn-solid btn-sm" href="projects.php#<?= e($p['slug']) ?>">Explore <?= e($p['name']) ?> <i class="fa-solid fa-arrow-right-long"></i></a>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
